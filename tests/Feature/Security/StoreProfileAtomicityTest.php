<?php

use App\Models\Store;
use App\Models\StoreApprovalReview;
use App\Services\SellerEligibilityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\ProductSecurityFixtures;

test('store profile and moderation review roll back as one unit', function () {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['is_active' => true]);
    $store = $vendor['store'];
    $fields = [
        'name', 'short_description', 'status', 'is_active', 'moderation_version',
        'reviewed_version', 'moderation_fingerprint', 'approved_at',
    ];
    $before = $store->only($fields);
    $reviewCount = StoreApprovalReview::query()->where('store_id', $store->getKey())->count();
    $event = 'eloquent.creating: '.StoreApprovalReview::class;
    Event::listen($event, static function (): never {
        throw new RuntimeException('Injected review failure.');
    });

    try {
        $this->withoutExceptionHandling();

        expect(fn () => $this->actingAs($vendor['user'], 'web')
            ->put(route('vendor.store-profile.update'), [
                'name' => 'Mutation that must roll back',
                'short_description' => '<strong>Changed</strong>',
                'currency' => 'MXN',
                'country' => 'MX',
                'timezone' => 'America/Tijuana',
            ]))->toThrow(RuntimeException::class, 'Injected review failure.');
    } finally {
        Event::forget($event);
    }

    expect($store->fresh()->only($fields))->toEqual($before)
        ->and(StoreApprovalReview::query()->where('store_id', $store->getKey())->count())
        ->toBe($reviewCount);
});

test('eligibility snapshot failure rolls back the persisted store and review changes exactly', function () {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['is_active' => true]);
    $store = $vendor['store'];

    StoreApprovalReview::query()->create([
        'store_id' => $store->getKey(),
        'version' => (int) $store->moderation_version,
        'status' => StoreApprovalReview::STATUS_PENDING,
        'source' => StoreApprovalReview::SOURCE_SYSTEM,
        'submission_reason' => 'Existing pending review.',
        'snapshot' => ['name' => $store->name],
        'eligibility_snapshot' => ['eligible' => true],
        'content_hash' => str_repeat('a', 64),
        'submitted_at' => now()->subMinute(),
    ]);

    $storeBefore = Store::query()->whereKey($store)->firstOrFail()->getRawOriginal();
    $reviewsBefore = StoreApprovalReview::query()
        ->where('store_id', $store->getKey())
        ->orderBy('id')
        ->get()
        ->map(fn (StoreApprovalReview $review): array => $review->getRawOriginal())
        ->all();

    $this->partialMock(SellerEligibilityService::class)
        ->shouldReceive('storeSnapshot')
        ->once()
        ->andReturnUsing(function (Store $persistedStore): never {
            expect($persistedStore->name)->toBe('Persisted mutation that must roll back')
                ->and(Store::query()->whereKey($persistedStore)->value('name'))
                ->toBe('Persisted mutation that must roll back');

            throw new RuntimeException('Injected eligibility snapshot failure.');
        });

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($vendor['user'], 'web')
        ->put(route('vendor.store-profile.update'), [
            'name' => 'Persisted mutation that must roll back',
            'short_description' => '<strong>Changed snapshot input</strong>',
            'currency' => 'USD',
            'country' => 'US',
            'timezone' => 'America/Tijuana',
        ]))->toThrow(RuntimeException::class, 'Injected eligibility snapshot failure.');

    $storeAfter = Store::query()->whereKey($store)->firstOrFail()->getRawOriginal();
    $reviewsAfter = StoreApprovalReview::query()
        ->where('store_id', $store->getKey())
        ->orderBy('id')
        ->get()
        ->map(fn (StoreApprovalReview $review): array => $review->getRawOriginal())
        ->all();

    expect($storeAfter)->toBe($storeBefore)
        ->and($reviewsAfter)->toBe($reviewsBefore);
});

test('failed store review keeps old media and removes the uncommitted upload', function () {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['is_active' => true]);
    $directory = public_path('uploads/stores');
    File::ensureDirectoryExists($directory);
    $oldPath = 'uploads/stores/old-'.Str::uuid().'.png';
    File::put(public_path($oldPath), 'old-store-logo');
    $vendor['store']->forceFill(['logo' => $oldPath])->saveQuietly();
    $beforeFiles = collect(File::files($directory))->map->getFilename()->sort()->values()->all();
    $event = 'eloquent.creating: '.StoreApprovalReview::class;
    Event::listen($event, static function (): never {
        throw new RuntimeException('Injected media review failure.');
    });

    try {
        $this->withoutExceptionHandling();

        expect(fn () => $this->actingAs($vendor['user'], 'web')
            ->put(route('vendor.store-profile.update'), [
                'name' => 'Media rollback store',
                'logo' => UploadedFile::fake()->image('replacement.png', 80, 80),
                'currency' => 'MXN',
                'country' => 'MX',
                'timezone' => 'America/Mexico_City',
            ]))->toThrow(RuntimeException::class, 'Injected media review failure.');

        $afterFiles = collect(File::files($directory))->map->getFilename()->sort()->values()->all();

        expect($vendor['store']->fresh()->logo)->toBe($oldPath)
            ->and(File::exists(public_path($oldPath)))->toBeTrue()
            ->and($afterFiles)->toEqual($beforeFiles);
    } finally {
        Event::forget($event);
        File::delete(public_path($oldPath));
    }
});

test('timezone is moderated material and creates a new store version', function () {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: [
        'timezone' => 'America/Mexico_City',
        'is_active' => true,
    ]);

    $this->actingAs($vendor['user'], 'web')
        ->put(route('vendor.store-profile.update'), [
            'name' => $vendor['store']->name,
            'currency' => 'MXN',
            'country' => 'MX',
            'timezone' => 'America/Tijuana',
        ])
        ->assertSessionHasNoErrors();

    $store = $vendor['store']->fresh();
    $review = StoreApprovalReview::query()
        ->where('store_id', $store->getKey())
        ->where('version', 2)
        ->sole();

    expect($store->timezone)->toBe('America/Tijuana')
        ->and($store->status)->toBe(Store::STATUS_PENDING)
        ->and($store->is_active)->toBeFalse()
        ->and($store->moderation_version)->toBe(2)
        ->and($review->snapshot['timezone'])->toBe('America/Tijuana')
        ->and($review->content_hash)->toBe($store->moderation_fingerprint);
});
