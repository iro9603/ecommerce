<?php

use App\Models\ProductApprovalReview;
use App\Models\ProductModerationEvent;
use App\Services\ProductModerationEventRecorder;
use App\Services\ProductModerationService;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ProductSecurityFixtures;

test('real moderation flow appends submit automatic decline and manual approval events', function () {
    Queue::fake();

    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'approved_status' => 'approved',
        'moderation_version' => 3,
        'reviewed_version' => 3,
        'moderation_fingerprint' => str_repeat('f', 64),
        'approved_at' => now(),
    ]);
    $service = app(ProductModerationService::class);

    $review = $service->submit($product, $vendor['user'], 'Submit content version four.');

    expect($review->version)->toBe(4)
        ->and($service->evaluatePending(
            (int) $product->getKey(),
            4,
            (string) $review->content_hash,
            (string) $review->context_hash,
        ))->toBeTrue()
        ->and($service->evaluatePending(
            (int) $product->getKey(),
            4,
            (string) $review->content_hash,
            (string) $review->context_hash,
        ))->toBeTrue();

    $product->refresh();
    $service->approve(
        $product,
        ProductSecurityFixtures::admin(),
        'Approved after manual evidence review.',
        4,
    );

    $events = ProductModerationEvent::query()->orderBy('id')->get();
    $review->refresh();

    expect($events)->toHaveCount(3)
        ->and($events->pluck('event_type')->all())->toBe([
            ProductModerationEvent::TYPE_SUBMITTED,
            ProductModerationEvent::TYPE_AUTOMATIC_DECLINED,
            ProductModerationEvent::TYPE_MANUAL_APPROVED,
        ])
        ->and($events->pluck('version')->unique()->values()->all())->toBe([4])
        ->and($events[0]->snapshot)->toBe($review->snapshot)
        ->and($events[0]->content_hash)->toBe($review->content_hash)
        ->and($events[1]->risk_result['auto_approvable'])->toBeFalse()
        ->and($events[1]->risk_result['reasons'])->not->toBeEmpty()
        ->and($events[2]->source)->toBe(ProductApprovalReview::SOURCE_MANUAL)
        ->and($events[2]->reason)->toBe('Approved after manual evidence review.')
        ->and($review->status)->toBe(ProductApprovalReview::STATUS_APPROVED)
        ->and(ProductApprovalReview::query()->where('product_id', $product->getKey())->count())->toBe(1);
});

test('product decision events preserve every snapshot for the same content version', function () {
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'moderation_version' => 4,
        'reviewed_version' => null,
    ]);
    $review = ProductApprovalReview::query()->create([
        'product_id' => $product->getKey(),
        'version' => 4,
        'status' => ProductApprovalReview::STATUS_PENDING,
        'source' => ProductApprovalReview::SOURCE_AUTOMATIC,
        'content_hash' => str_repeat('a', 64),
        'snapshot' => ['name' => 'Snapshot A'],
        'evaluation_context' => ['policy_version' => 'policy-a'],
        'context_hash' => str_repeat('b', 64),
        'submitted_at' => now(),
    ]);
    $recorder = app(ProductModerationEventRecorder::class);

    $recorder->record(
        $review,
        ProductModerationEvent::TYPE_SUBMITTED,
        $vendor['user'],
        'Submitted v4.',
    );

    $review->forceFill([
        'source' => ProductApprovalReview::SOURCE_MANUAL,
        'status' => ProductApprovalReview::STATUS_APPROVED,
        'snapshot' => ['name' => 'Snapshot B projection'],
        'evaluation_context' => ['policy_version' => 'policy-b'],
        'context_hash' => str_repeat('c', 64),
        'decision_reason' => 'Manual approval.',
        'reviewed_at' => now(),
    ])->save();

    $recorder->record(
        $review,
        ProductModerationEvent::TYPE_MANUAL_APPROVED,
        ProductSecurityFixtures::admin(),
        'Manual approval.',
        ['score' => 7, 'level' => 'low'],
    );

    $events = ProductModerationEvent::query()->orderBy('id')->get();

    expect($events)->toHaveCount(2)
        ->and($events[0]->version)->toBe(4)
        ->and($events[0]->snapshot)->toBe(['name' => 'Snapshot A'])
        ->and($events[0]->evaluation_context)->toBe(['policy_version' => 'policy-a'])
        ->and($events[1]->version)->toBe(4)
        ->and($events[1]->snapshot)->toBe(['name' => 'Snapshot B projection'])
        ->and($events[1]->evaluation_context)->toBe(['policy_version' => 'policy-b'])
        ->and($events[1]->risk_result)->toMatchArray(['score' => 7, 'level' => 'low']);
});

test('product moderation events reject mutation and deletion through the model', function () {
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store']);
    $review = ProductApprovalReview::query()->create([
        'product_id' => $product->getKey(),
        'version' => 1,
        'status' => ProductApprovalReview::STATUS_PENDING,
        'source' => ProductApprovalReview::SOURCE_AUTOMATIC,
        'content_hash' => str_repeat('d', 64),
        'snapshot' => ['name' => 'Immutable'],
        'evaluation_context' => [],
        'context_hash' => str_repeat('e', 64),
        'submitted_at' => now(),
    ]);
    $event = app(ProductModerationEventRecorder::class)->record(
        $review,
        ProductModerationEvent::TYPE_SUBMITTED,
    );

    expect(function () use ($event): void {
        $event->reason = 'tampered';
        $event->save();
    })->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $event->delete())
        ->toThrow(LogicException::class, 'append-only');

    expect(ProductModerationEvent::query()->count())->toBe(1);
});
