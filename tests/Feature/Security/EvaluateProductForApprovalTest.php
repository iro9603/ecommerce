<?php

use App\Jobs\EvaluateProductForApproval;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductApprovalReview;
use App\Models\ProductImage;
use App\Models\Store;
use App\Services\ProductModerationService;
use App\Services\ProductRiskEvaluator;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\ProductSecurityFixtures;

function productReadyForAutomaticApproval(Store $store): Product
{
    $identifier = Str::uuid()->toString();
    $product = ProductSecurityFixtures::product($store);
    $category = Category::forceCreate([
        'name' => 'Approval Category '.$identifier,
        'slug' => 'approval-category-'.$identifier,
        'is_active' => true,
    ]);
    $product->categories()->attach($category);
    ProductImage::forceCreate([
        'product_id' => $product->getKey(),
        'path' => 'uploads/approval-'.$identifier.'.png',
        'order' => 1,
    ]);

    return $product;
}

function queuedAutomaticApprovalJob(
    Product $product,
    ProductApprovalReview $review
): EvaluateProductForApproval {
    return new EvaluateProductForApproval(
        (int) $product->getKey(),
        (int) $review->version,
        (string) $review->content_hash,
        (string) $review->context_hash,
    );
}

function productModerationWithPostAssessmentMutation(Closure $mutation): ProductModerationService
{
    $riskEvaluator = new class($mutation) extends ProductRiskEvaluator
    {
        public function __construct(private readonly Closure $mutation) {}

        public function evaluate(Product $product): array
        {
            $assessment = parent::evaluate($product);
            ($this->mutation)();

            return $assessment;
        }
    };

    return new ProductModerationService($riskEvaluator);
}

test('the evaluation job automatically approves a low risk product from a trusted store', function () {
    Queue::fake();
    config()->set('product_moderation.automatic_approval_enabled', true);

    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => true]
    );
    $product = productReadyForAutomaticApproval($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Ready for automatic review.');

    queuedAutomaticApprovalJob($product, $review)->handle($moderation);

    $product->refresh();
    $review->refresh();

    expect($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->reviewed_version)->toBe($review->version)
        ->and($product->approved_at)->not->toBeNull()
        ->and($product->approved_by)->toBeNull()
        ->and($product->risk_score)->toBe(0)
        ->and($product->risk_level)->toBe('low')
        ->and($review->status)->toBe(ProductApprovalReview::STATUS_APPROVED)
        ->and($review->source)->toBe(ProductApprovalReview::SOURCE_AUTOMATIC)
        ->and($review->risk_score)->toBe(0)
        ->and($review->risk_level)->toBe('low')
        ->and($review->risk_reasons)->toBe([])
        ->and($review->reviewed_by)->toBeNull()
        ->and($review->reviewed_at)->not->toBeNull();
});

test('the evaluation job keeps a product pending when its store is not trusted', function () {
    Queue::fake();
    config()->set('product_moderation.automatic_approval_enabled', true);

    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => false]
    );
    $product = productReadyForAutomaticApproval($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Requires store trust.');

    queuedAutomaticApprovalJob($product, $review)->handle($moderation);

    $product->refresh();
    $review->refresh();

    expect($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->reviewed_version)->toBeNull()
        ->and($product->approved_at)->toBeNull()
        ->and($product->risk_score)->toBe(0)
        ->and($product->risk_level)->toBe('low')
        ->and($review->status)->toBe(ProductApprovalReview::STATUS_PENDING)
        ->and($review->source)->toBe(ProductApprovalReview::SOURCE_AUTOMATIC)
        ->and($review->risk_score)->toBe(0)
        ->and($review->risk_level)->toBe('low')
        ->and(array_column($review->risk_reasons, 'code'))
        ->toContain('store_requires_manual_review');
});

test('the evaluation job keeps a product without a sellable price pending', function () {
    Queue::fake();
    config()->set('product_moderation.automatic_approval_enabled', true);

    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => true]
    );
    $product = productReadyForAutomaticApproval($vendor['store']);
    $product->forceFill(['price' => null])->save();
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Missing sellable price.');

    queuedAutomaticApprovalJob($product, $review)->handle($moderation);

    $product->refresh();
    $review->refresh();

    expect($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->risk_level)->toBe('critical')
        ->and(array_column($review->risk_reasons, 'code'))
        ->toContain('missing_sellable_price');
});

test('the evaluation job carries the exact submitted content and context hashes', function () {
    Queue::fake();

    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['auto_approve_products' => true]);
    $product = productReadyForAutomaticApproval($vendor['store']);
    $review = app(ProductModerationService::class)->submit(
        $product,
        $vendor['user'],
        'Capture the submitted job expectations.'
    );

    $job = queuedAutomaticApprovalJob($product, $review);

    expect($job->moderationVersion)->toBe((int) $review->version)
        ->and($job->expectedContentFingerprint)->toBe($review->content_hash)
        ->and($job->expectedContextHash)->toBe($review->context_hash);
});

test('a legacy evaluation job without signed hashes fails closed', function () {
    Queue::fake();
    config()->set('product_moderation.automatic_approval_enabled', true);

    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['auto_approve_products' => true]);
    $product = productReadyForAutomaticApproval($vendor['store']);
    $review = app(ProductModerationService::class)->submit(
        $product,
        $vendor['user'],
        'Legacy jobs must never infer mutable expectations.',
    );

    (new EvaluateProductForApproval($product->getKey(), (int) $review->version))
        ->handle(app(ProductModerationService::class));

    expect($product->fresh()->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->fresh()->reviewed_version)->toBeNull()
        ->and($review->fresh()->status)->toBe(ProductApprovalReview::STATUS_PENDING)
        ->and($review->fresh()->risk_score)->toBeNull();
});

test('the evaluation job cannot approve after store trust is revoked during evaluation', function () {
    Queue::fake();
    config()->set('product_moderation.automatic_approval_enabled', true);

    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['auto_approve_products' => true]);
    $product = productReadyForAutomaticApproval($vendor['store']);
    $submission = app(ProductModerationService::class)->submit(
        $product,
        $vendor['user'],
        'Trust is current at submission time.'
    );
    $moderation = productModerationWithPostAssessmentMutation(function () use ($vendor): void {
        $vendor['store']->newQuery()
            ->whereKey($vendor['store']->getKey())
            ->update(['auto_approve_products' => false]);
    });

    queuedAutomaticApprovalJob($product, $submission)->handle($moderation);

    $product = Product::query()->findOrFail($product->getKey());
    $submission->refresh();

    expect($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->reviewed_version)->toBeNull()
        ->and($submission->status)->toBe(ProductApprovalReview::STATUS_PENDING);
});

test('the evaluation job cannot approve after seller kyc changes during evaluation', function () {
    Queue::fake();
    config()->set('product_moderation.automatic_approval_enabled', true);

    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['auto_approve_products' => true]);
    $product = productReadyForAutomaticApproval($vendor['store']);
    $submission = app(ProductModerationService::class)->submit(
        $product,
        $vendor['user'],
        'KYC is current at submission time.'
    );
    $moderation = productModerationWithPostAssessmentMutation(function () use ($vendor): void {
        $vendor['kyc']->newQuery()
            ->whereKey($vendor['kyc']->getKey())
            ->update(['status' => 'rejected']);
    });

    queuedAutomaticApprovalJob($product, $submission)->handle($moderation);

    $product = Product::query()->findOrFail($product->getKey());
    $submission->refresh();

    expect($vendor['kyc']->fresh()->status)->toBe('rejected')
        ->and($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->reviewed_version)->toBeNull()
        ->and($submission->status)->toBe(ProductApprovalReview::STATUS_PENDING);
});

test('the evaluation job cannot approve after the store moderation version changes', function () {
    Queue::fake();
    config()->set('product_moderation.automatic_approval_enabled', true);

    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['auto_approve_products' => true]);
    $product = productReadyForAutomaticApproval($vendor['store']);
    $submission = app(ProductModerationService::class)->submit(
        $product,
        $vendor['user'],
        'Store version is current at submission time.'
    );
    $moderation = productModerationWithPostAssessmentMutation(function () use ($vendor): void {
        $store = $vendor['store']->fresh();
        $store->newQuery()->whereKey($store->getKey())->update([
            'moderation_version' => ((int) $store->moderation_version) + 1,
        ]);
    });

    queuedAutomaticApprovalJob($product, $submission)->handle($moderation);

    $product = Product::query()->findOrFail($product->getKey());
    $submission->refresh();

    expect($vendor['store']->fresh()->moderation_version)
        ->toBeGreaterThan((int) $vendor['store']->moderation_version)
        ->and($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->reviewed_version)->toBeNull()
        ->and($submission->status)->toBe(ProductApprovalReview::STATUS_PENDING);
});

test('a job for an older product moderation version cannot approve the newer version', function () {
    Queue::fake();
    config()->set('product_moderation.automatic_approval_enabled', true);

    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['auto_approve_products' => true]);
    $product = productReadyForAutomaticApproval($vendor['store']);
    $submission = app(ProductModerationService::class)->submit(
        $product,
        $vendor['user'],
        'Product version is current at submission time.'
    );
    $newVersion = ((int) $submission->version) + 1;
    Product::query()->whereKey($product->getKey())->update(['moderation_version' => $newVersion]);

    queuedAutomaticApprovalJob($product, $submission)
        ->handle(app(ProductModerationService::class));

    $product = Product::query()->findOrFail($product->getKey());

    expect($product->moderation_version)->toBe($newVersion)
        ->and($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->reviewed_version)->toBeNull()
        ->and($submission->fresh()->status)->toBe(ProductApprovalReview::STATUS_PENDING);
});

test('the evaluation job cannot approve when the current product fingerprint differs', function () {
    Queue::fake();
    config()->set('product_moderation.automatic_approval_enabled', true);

    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['auto_approve_products' => true]);
    $product = productReadyForAutomaticApproval($vendor['store']);
    $submission = app(ProductModerationService::class)->submit(
        $product,
        $vendor['user'],
        'Fingerprint is current at submission time.'
    );
    Product::query()->whereKey($product->getKey())->update([
        'moderation_fingerprint' => str_repeat('f', 64),
    ]);

    queuedAutomaticApprovalJob($product, $submission)
        ->handle(app(ProductModerationService::class));

    $product = Product::query()->findOrFail($product->getKey());

    expect($product->moderation_fingerprint)->not->toBe($submission->content_hash)
        ->and($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->reviewed_version)->toBeNull()
        ->and($submission->fresh()->status)->toBe(ProductApprovalReview::STATUS_PENDING);
});
