<?php

namespace App\Services;

use App\Jobs\EvaluateProductForApproval;
use App\Jobs\EvaluateProductApprovalContext;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductApprovalReview;
use App\Models\ProductModerationEvent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class ProductModerationService
{
    private readonly ProductModerationEventRecorder $eventRecorder;

    public function __construct(
        private readonly ProductRiskEvaluator $riskEvaluator,
        ?ProductModerationEventRecorder $eventRecorder = null,
    ) {
        $this->eventRecorder = $eventRecorder ?? new ProductModerationEventRecorder;
    }

    public function submit(
        Product $product,
        ?User $submittedBy = null,
        ?string $reason = null
    ): ProductApprovalReview {
        return $this->submitInternal($product, $submittedBy, $reason, false);
    }

    public function markForReview(
        Product $product,
        ?User $submittedBy = null,
        ?string $reason = null
    ): ProductApprovalReview {
        return $this->submit($product, $submittedBy, $reason);
    }

    /**
     * Force a new moderation version even when the product content hash has
     * not changed. This is used when seller security context changes and the
     * previous approval must be invalidated rather than reused.
     */
    public function forceRevalidate(
        Product $product,
        ?User $submittedBy = null,
        ?string $reason = null
    ): ProductApprovalReview {
        return $this->submitInternal($product, $submittedBy, $reason, true);
    }

    private function submitInternal(
        Product $product,
        ?User $submittedBy,
        ?string $reason,
        bool $forceNewVersion
    ): ProductApprovalReview {
        if (! $product->exists) {
            throw new LogicException('A product must be saved before it can be submitted for moderation.');
        }

        [$review, $version] = DB::transaction(function () use ($product, $submittedBy, $reason, $forceNewVersion): array {
            $current = Product::withTrashed()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $snapshot = $this->contentSnapshot($current);
            $fingerprint = $this->fingerprint($snapshot);
            $context = $this->evaluationContext($current);
            $contextHash = $this->fingerprint($context);
            $pendingReview = ProductApprovalReview::query()
                ->where('product_id', $current->getKey())
                ->where('version', (int) $current->moderation_version)
                ->where('status', ProductApprovalReview::STATUS_PENDING)
                ->first();

            if (
                ! $forceNewVersion
                && $current->approved_status === Product::APPROVAL_PENDING
                && (int) $current->moderation_version > 0
                && hash_equals((string) $current->moderation_fingerprint, $fingerprint)
                && $pendingReview !== null
            ) {
                $review = $this->pendingReview(
                    $current,
                    (int) $current->moderation_version,
                    $snapshot,
                    $fingerprint,
                    $context,
                    $contextHash,
                    $submittedBy,
                    $reason,
                );

                return [$review, (int) $current->moderation_version];
            }

            $supersededReviews = ProductApprovalReview::query()
                ->where('product_id', $current->getKey())
                ->where('status', ProductApprovalReview::STATUS_PENDING)
                ->lockForUpdate()
                ->get();

            foreach ($supersededReviews as $supersededReview) {
                $supersededReview->forceFill([
                    'status' => ProductApprovalReview::STATUS_SUPERSEDED,
                    'decision_reason' => 'Superseded by a newer product submission.',
                    'reviewed_at' => now(),
                ])->save();

                $this->eventRecorder->record(
                    $supersededReview,
                    ProductModerationEvent::TYPE_SUPERSEDED,
                    $submittedBy,
                    (string) $supersededReview->decision_reason,
                    metadata: ['superseded_by_version' => ((int) $current->moderation_version) + 1],
                    idempotencyKey: 'product-review:'.$supersededReview->getKey().':superseded',
                );
            }

            $version = ((int) $current->moderation_version) + 1;
            $submittedAt = now();

            $current->forceFill([
                'approved_status' => Product::APPROVAL_PENDING,
                'moderation_version' => $version,
                'reviewed_version' => null,
                'submitted_at' => $submittedAt,
                'approved_at' => null,
                'approved_by' => null,
                'moderation_reason' => $this->cleanReason($reason),
                'risk_level' => null,
                'risk_score' => null,
                'moderation_fingerprint' => $fingerprint,
                'reviewed_user_eligibility_epoch' => null,
                'reviewed_kyc_id' => null,
                'reviewed_kyc_eligibility_epoch' => null,
                'reviewed_store_eligibility_epoch' => null,
                'reviewed_context_hash' => null,
                'reviewed_policy_version' => null,
            ])->save();

            $review = ProductApprovalReview::query()->create([
                'product_id' => $current->getKey(),
                'version' => $version,
                'status' => ProductApprovalReview::STATUS_PENDING,
                'source' => ProductApprovalReview::SOURCE_AUTOMATIC,
                'submitted_by' => $submittedBy?->getKey(),
                'submission_reason' => $this->cleanReason($reason),
                'content_hash' => $fingerprint,
                'snapshot' => $snapshot,
                'evaluation_context' => $context,
                'context_hash' => $contextHash,
                'submitted_at' => $submittedAt,
            ]);

            $this->eventRecorder->record(
                $review,
                ProductModerationEvent::TYPE_SUBMITTED,
                $submittedBy,
                $review->submission_reason,
                metadata: ['forced_revalidation' => $forceNewVersion],
                idempotencyKey: 'product-review:'.$review->getKey().':submitted',
            );

            return [$review, $version];
        });

        EvaluateProductForApproval::dispatch(
            (int) $product->getKey(),
            $version,
            (string) $review->content_hash,
            (string) $review->context_hash,
        )->afterCommit();
        $product->refresh();

        return $review;
    }

    public function approve(
        Product $product,
        ?Admin $reviewer = null,
        ?string $reason = null,
        ?int $expectedVersion = null
    ): ?ProductApprovalReview {
        return $this->decide(
            $product,
            Product::APPROVAL_APPROVED,
            $reviewer,
            $reason ?? 'Approved by an administrator.',
            $expectedVersion,
        );
    }

    public function reject(
        Product $product,
        string $reason,
        ?Admin $reviewer = null,
        ?int $expectedVersion = null
    ): ?ProductApprovalReview {
        return $this->decide(
            $product,
            Product::APPROVAL_REJECTED,
            $reviewer,
            $reason,
            $expectedVersion,
        );
    }

    public function reevaluatePendingStoreContext(Store $store, string $reason): int
    {
        $count = 0;

        Product::query()
            ->where('store_id', $store->getKey())
            ->where('approved_status', Product::APPROVAL_PENDING)
            ->orderBy('id')
            ->each(function (Product $product) use ($reason, &$count): void {
                $dispatch = DB::transaction(function () use ($product, $reason): ?array {
                    $current = Product::query()
                        ->whereKey($product->getKey())
                        ->lockForUpdate()
                        ->first();

                    if (
                        ! $current
                        || $current->approved_status !== Product::APPROVAL_PENDING
                        || (int) $current->moderation_version < 1
                    ) {
                        return null;
                    }

                    $review = ProductApprovalReview::query()
                        ->where('product_id', $current->getKey())
                        ->where('version', (int) $current->moderation_version)
                        ->where('status', ProductApprovalReview::STATUS_PENDING)
                        ->lockForUpdate()
                        ->first();

                    if (! $review) {
                        return null;
                    }

                    $context = $this->evaluationContext($current);
                    $contextHash = $this->fingerprint($context);

                    $previousContextHash = (string) $review->context_hash;

                    $review->forceFill([
                        'evaluation_context' => $context,
                        'context_hash' => $contextHash,
                    ])->save();

                    if (! hash_equals($previousContextHash, $contextHash)) {
                        $this->eventRecorder->record(
                            $review,
                            ProductModerationEvent::TYPE_CONTEXT_REEVALUATED,
                            reason: $reason,
                            metadata: ['previous_context_hash' => $previousContextHash],
                            idempotencyKey: 'product-review:'.$review->getKey().':context:'.$contextHash,
                        );
                    }

                    return [
                        (int) $current->getKey(),
                        (int) $current->moderation_version,
                        (string) $review->content_hash,
                        $contextHash,
                    ];
                });

                if ($dispatch !== null) {
                    EvaluateProductApprovalContext::dispatch(
                        $dispatch[0],
                        $dispatch[1],
                        $dispatch[2],
                        $dispatch[3],
                    )->afterCommit();
                    $count++;
                }
            });

        return $count;
    }

    public function evaluatePending(
        int $productId,
        int $expectedVersion,
        ?string $expectedContentFingerprint = null,
        ?string $expectedContextHash = null,
    ): bool {
        if (
            ! $this->validFingerprint($expectedContentFingerprint)
            || ! $this->validFingerprint($expectedContextHash)
        ) {
            return false;
        }

        $coordinates = DB::table('products')
            ->leftJoin('stores', 'stores.id', '=', 'products.store_id')
            ->where('products.id', $productId)
            ->first(['products.store_id', 'stores.seller_id']);

        if ($coordinates === null || $coordinates->seller_id === null) {
            return false;
        }

        return DB::transaction(
            fn (): bool => $this->evaluatePendingLocked(
                $productId,
                $expectedVersion,
                $expectedContentFingerprint,
                $expectedContextHash,
                (int) $coordinates->store_id,
                (int) $coordinates->seller_id,
            ),
            3,
        );
    }

    private function evaluatePendingLocked(
        int $productId,
        int $expectedVersion,
        string $expectedContentFingerprint,
        string $expectedContextHash,
        int $storeId,
        int $sellerId,
    ): bool {
        $seller = DB::table('users')
            ->where('id', $sellerId)
            ->lockForUpdate()
            ->first(['id']);

        if ($seller === null) {
            return false;
        }

        $store = Store::query()->whereKey($storeId)->lockForUpdate()->first();

        if ($store === null || (int) $store->seller_id !== $sellerId) {
            return false;
        }

        DB::table('kycs')->where('user_id', $sellerId)->lockForUpdate()->get(['id']);

        $current = Product::query()->whereKey($productId)->lockForUpdate()->first();

        if ($current === null || (int) $current->store_id !== $storeId) {
            return false;
        }

        $review = ProductApprovalReview::query()
            ->where('product_id', $productId)
            ->where('version', $expectedVersion)
            ->lockForUpdate()
            ->first();
        $state = $this->pendingEvaluationState(
            $current,
            $review,
            $expectedVersion,
            $expectedContentFingerprint,
            $expectedContextHash,
        );

        if ($state === null) {
            return false;
        }

        $assessment = $this->riskEvaluator->evaluate($current);
        $current->refresh();
        $store->refresh();
        $review->refresh();

        if (
            (int) $current->store_id !== $storeId
            || (int) $store->seller_id !== $sellerId
        ) {
            return false;
        }

        $state = $this->pendingEvaluationState(
            $current,
            $review,
            $expectedVersion,
            $expectedContentFingerprint,
            $expectedContextHash,
        );

        if ($state === null) {
            return false;
        }

        $review->forceFill([
            'risk_score' => $assessment['score'],
            'risk_level' => $assessment['level'],
            'risk_reasons' => $assessment['reasons'],
            'evaluation_context' => $state['context'],
            'context_hash' => $state['context_hash'],
        ]);

        if ($assessment['auto_approvable']) {
            $this->automaticallyApprove(
                $current,
                $review,
                $expectedVersion,
                $assessment,
                $state['context'],
            );
        } else {
            $this->keepPendingAfterAssessment($current, $review, $assessment);
        }

        $review->save();

        $eventType = $assessment['auto_approvable']
            ? ProductModerationEvent::TYPE_AUTOMATIC_APPROVED
            : ProductModerationEvent::TYPE_AUTOMATIC_DECLINED;
        $riskResult = $this->riskResult($assessment);

        $this->eventRecorder->record(
            $review,
            $eventType,
            reason: $review->decision_reason,
            riskResult: $riskResult,
            metadata: [
                'expected_content_hash' => $expectedContentFingerprint,
                'expected_context_hash' => $expectedContextHash,
            ],
            idempotencyKey: implode(':', [
                'product-review',
                $review->getKey(),
                $eventType,
                $expectedContentFingerprint,
                $expectedContextHash,
                $this->fingerprint($riskResult),
            ]),
        );

        return true;
    }

    /**
     * @return array{content_hash: string, context: array<string, mixed>, context_hash: string}|null
     */
    private function pendingEvaluationState(
        Product $product,
        ?ProductApprovalReview $review,
        int $expectedVersion,
        string $expectedContentFingerprint,
        string $expectedContextHash,
    ): ?array {
        if (
            $product->approved_status !== Product::APPROVAL_PENDING
            || (int) $product->moderation_version !== $expectedVersion
            || ! hash_equals(
                $expectedContentFingerprint,
                (string) $product->moderation_fingerprint,
            )
            || $review === null
            || $review->status !== ProductApprovalReview::STATUS_PENDING
            || ! hash_equals($expectedContentFingerprint, (string) $review->content_hash)
            || ! hash_equals($expectedContextHash, (string) $review->context_hash)
        ) {
            return null;
        }

        $contentHash = $this->fingerprint($this->contentSnapshot($product));
        $context = $this->evaluationContext($product);
        $contextHash = $this->fingerprint($context);

        if (
            ! hash_equals($expectedContentFingerprint, $contentHash)
            || ! hash_equals($expectedContextHash, $contextHash)
        ) {
            return null;
        }

        return [
            'content_hash' => $contentHash,
            'context' => $context,
            'context_hash' => $contextHash,
        ];
    }

    private function validFingerprint(?string $fingerprint): bool
    {
        return $fingerprint !== null
            && preg_match('/\A[0-9a-f]{64}\z/D', $fingerprint) === 1;
    }

    private function automaticallyApprove(
        Product $product,
        ProductApprovalReview $review,
        int $expectedVersion,
        array $assessment,
        array $context,
    ): void {
        $decisionReason = 'Automatically approved after passing the product risk evaluation.';

        $this->ensureSingleActiveDefaultVariant($product);

        $product->forceFill([
            'approved_status' => Product::APPROVAL_APPROVED,
            'reviewed_version' => $expectedVersion,
            'approved_at' => now(),
            'approved_by' => null,
            'moderation_reason' => $decisionReason,
            'risk_score' => $assessment['score'],
            'risk_level' => $assessment['level'],
            ...$this->decisionEligibilityPins($context),
        ])->save();

        $review->forceFill([
            'status' => ProductApprovalReview::STATUS_APPROVED,
            'source' => ProductApprovalReview::SOURCE_AUTOMATIC,
            'decision_reason' => $decisionReason,
            'reviewed_at' => now(),
        ]);
    }

    private function ensureSingleActiveDefaultVariant(Product $product): void
    {
        $variants = $product->variants()
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $defaults = $variants
            ->filter(fn (ProductVariant $variant): bool => $variant->is_active && $variant->is_default)
            ->sortBy([
                ['position', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        foreach ($defaults->skip(1) as $duplicate) {
            $duplicate->forceFill(['is_default' => false])->save();
        }
    }

    private function keepPendingAfterAssessment(
        Product $product,
        ProductApprovalReview $review,
        array $assessment,
    ): void {
        $decisionReason = $this->manualReviewReason($assessment['reasons']);

        $product->forceFill([
            'moderation_reason' => $decisionReason,
            'risk_score' => $assessment['score'],
            'risk_level' => $assessment['level'],
        ])->save();

        $review->decision_reason = $decisionReason;
    }

    private function decide(
        Product $product,
        string $decision,
        ?Admin $reviewer,
        string $reason,
        ?int $expectedVersion
    ): ?ProductApprovalReview {
        $review = DB::transaction(function () use (
            $product,
            $decision,
            $reviewer,
            $reason,
            $expectedVersion
        ): ?ProductApprovalReview {
            $current = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            $this->ensureSingleActiveDefaultVariant($current);

            if (
                $expectedVersion !== null
                && (int) $current->moderation_version !== $expectedVersion
            ) {
                return null;
            }

            $version = (int) $current->moderation_version;
            $snapshot = $this->contentSnapshot($current);
            $fingerprint = $this->fingerprint($snapshot);
            $context = $this->evaluationContext($current);
            $contextHash = $this->fingerprint($context);

            if ($version < 1) {
                $version = 1;
                $current->forceFill([
                    'moderation_version' => $version,
                    'submitted_at' => $current->submitted_at ?? now(),
                    'moderation_fingerprint' => $fingerprint,
                ])->save();
            }

            $review = ProductApprovalReview::query()
                ->where('product_id', $current->getKey())
                ->where('version', $version)
                ->lockForUpdate()
                ->first();

            if ($review && ! hash_equals((string) $review->content_hash, $fingerprint)) {
                if ($expectedVersion !== null) {
                    return null;
                }

                $review->forceFill([
                    'status' => ProductApprovalReview::STATUS_SUPERSEDED,
                    'decision_reason' => 'Superseded because the product content changed before review.',
                    'reviewed_at' => now(),
                ])->save();

                $this->eventRecorder->record(
                    $review,
                    ProductModerationEvent::TYPE_SUPERSEDED,
                    $reviewer,
                    (string) $review->decision_reason,
                    metadata: ['superseded_by_version' => $version + 1],
                    idempotencyKey: 'product-review:'.$review->getKey().':superseded',
                );

                $version++;
                $current->forceFill([
                    'approved_status' => Product::APPROVAL_PENDING,
                    'moderation_version' => $version,
                    'reviewed_version' => null,
                    'submitted_at' => now(),
                    'approved_at' => null,
                    'approved_by' => null,
                    'moderation_fingerprint' => $fingerprint,
                ])->save();

                $review = null;
            }

            if (! $review) {
                $review = ProductApprovalReview::query()->create([
                    'product_id' => $current->getKey(),
                    'version' => $version,
                    'status' => ProductApprovalReview::STATUS_PENDING,
                    'source' => ProductApprovalReview::SOURCE_MANUAL,
                    'content_hash' => $fingerprint,
                    'snapshot' => $snapshot,
                    'evaluation_context' => $context,
                    'context_hash' => $contextHash,
                    'submitted_at' => $current->submitted_at ?? now(),
                ]);

                $this->eventRecorder->record(
                    $review,
                    ProductModerationEvent::TYPE_SUBMITTED,
                    $reviewer,
                    'Created for a manual moderation decision.',
                    idempotencyKey: 'product-review:'.$review->getKey().':submitted',
                );
            }

            $assessment = $this->riskEvaluator->evaluate($current);
            $decisionReason = $this->cleanReason($reason);
            $reviewedAt = now();

            $current->forceFill([
                'approved_status' => $decision,
                'reviewed_version' => $version,
                'approved_at' => $decision === Product::APPROVAL_APPROVED ? $reviewedAt : null,
                'approved_by' => $decision === Product::APPROVAL_APPROVED ? $reviewer?->getKey() : null,
                'moderation_reason' => $decisionReason,
                'risk_score' => $assessment['score'],
                'risk_level' => $assessment['level'],
                'moderation_fingerprint' => $fingerprint,
                ...$this->decisionEligibilityPins($context, $contextHash),
            ])->save();

            $review->forceFill([
                'status' => $decision,
                'source' => ProductApprovalReview::SOURCE_MANUAL,
                'reviewed_by' => $reviewer?->getKey(),
                'decision_reason' => $decisionReason,
                'risk_score' => $assessment['score'],
                'risk_level' => $assessment['level'],
                'risk_reasons' => $assessment['reasons'],
                'evaluation_context' => $context,
                'context_hash' => $contextHash,
                'reviewed_at' => $reviewedAt,
            ])->save();

            $this->eventRecorder->record(
                $review,
                $decision === Product::APPROVAL_APPROVED
                    ? ProductModerationEvent::TYPE_MANUAL_APPROVED
                    : ProductModerationEvent::TYPE_MANUAL_REJECTED,
                $reviewer,
                $decisionReason,
                $this->riskResult($assessment),
            );

            return $review;
        });

        $product->refresh();

        return $review;
    }

    private function pendingReview(
        Product $product,
        int $version,
        array $snapshot,
        string $fingerprint,
        array $context,
        string $contextHash,
        ?User $submittedBy,
        ?string $reason
    ): ProductApprovalReview {
        $review = ProductApprovalReview::query()->firstOrNew([
            'product_id' => $product->getKey(),
            'version' => $version,
        ]);
        $wasNew = ! $review->exists;
        $previousContextHash = (string) $review->context_hash;

        if (! $review->exists) {
            $review->forceFill([
                'status' => ProductApprovalReview::STATUS_PENDING,
                'source' => ProductApprovalReview::SOURCE_AUTOMATIC,
                'submitted_by' => $submittedBy?->getKey(),
                'submission_reason' => $this->cleanReason($reason),
                'submitted_at' => $product->submitted_at ?? now(),
            ]);
        }

        $review->forceFill([
            'content_hash' => $fingerprint,
            'snapshot' => $snapshot,
            'evaluation_context' => $context,
            'context_hash' => $contextHash,
        ])->save();

        if ($wasNew) {
            $this->eventRecorder->record(
                $review,
                ProductModerationEvent::TYPE_SUBMITTED,
                $submittedBy,
                $review->submission_reason,
                idempotencyKey: 'product-review:'.$review->getKey().':submitted',
            );
        } elseif (! hash_equals($previousContextHash, $contextHash)) {
            $this->eventRecorder->record(
                $review,
                ProductModerationEvent::TYPE_CONTEXT_REEVALUATED,
                $submittedBy,
                $this->cleanReason($reason) ?? 'Pending moderation context refreshed.',
                metadata: ['previous_context_hash' => $previousContextHash],
                idempotencyKey: 'product-review:'.$review->getKey().':context:'.$contextHash,
            );
        }

        return $review;
    }

    /**
     * @param  array{score: int, level: string, reasons: array<int, array<string, mixed>>, auto_approvable: bool}  $assessment
     * @return array{score: int, level: string, reasons: array<int, array<string, mixed>>, auto_approvable: bool}
     */
    private function riskResult(array $assessment): array
    {
        return [
            'score' => (int) $assessment['score'],
            'level' => (string) $assessment['level'],
            'reasons' => $assessment['reasons'],
            'auto_approvable' => (bool) $assessment['auto_approvable'],
        ];
    }

    private function contentSnapshot(Product $product): array
    {
        $productId = (int) $product->getKey();
        $brand = $product->brand_id
            ? DB::table('brands')
                ->where('id', $product->brand_id)
                ->first(['id', 'name', 'slug', 'is_active'])
            : null;

        return [
            'product' => [
                'id' => $productId,
                'store_id' => $product->store_id,
                'product_type' => $product->product_type,
                'brand_id' => $product->brand_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'special_price' => $product->special_price,
                'special_price_start' => $product->special_price_start,
                'special_price_end' => $product->special_price_end,
                'sku' => $product->sku,
                'manage_stock' => $product->manage_stock,
                'qty' => $product->qty,
                'in_stock' => $product->in_stock,
                'status' => $product->status,
                'is_featured' => $product->is_featured,
                'is_hot' => $product->is_hot,
                'is_new' => $product->is_new,
                'short_description_hash' => hash('sha256', (string) $product->short_description),
                'description_hash' => hash('sha256', (string) $product->description),
            ],
            'brand' => $brand === null ? null : (array) $brand,
            'category_ids' => DB::table('category_product')
                ->where('product_id', $productId)
                ->orderBy('category_id')
                ->pluck('category_id')
                ->map(fn ($id) => (int) $id)
                ->all(),
            'categories' => DB::table('categories')
                ->join(
                    'category_product as product_categories',
                    'product_categories.category_id',
                    '=',
                    'categories.id'
                )
                ->where('product_categories.product_id', $productId)
                ->orderBy('categories.id')
                ->get([
                    'categories.id',
                    'categories.parent_id',
                    'categories.name',
                    'categories.slug',
                    'categories.is_active',
                    'categories.deleted_at',
                ])
                ->map(fn (object $row) => (array) $row)
                ->all(),
            'tag_ids' => DB::table('product_tag')
                ->where('product_id', $productId)
                ->orderBy('tag_id')
                ->pluck('tag_id')
                ->map(fn ($id) => (int) $id)
                ->all(),
            'tags' => DB::table('tags')
                ->join(
                    'product_tag as product_tags',
                    'product_tags.tag_id',
                    '=',
                    'tags.id'
                )
                ->where('product_tags.product_id', $productId)
                ->orderBy('tags.id')
                ->get([
                    'tags.id',
                    'tags.name',
                    'tags.slug',
                    'tags.is_active',
                ])
                ->map(fn (object $row) => (array) $row)
                ->all(),
            'attribute_values' => $this->rows('product_attribute_values', $productId, [
                'attribute_id',
                'attribute_value_id',
            ]),
            'attributes' => DB::table('attributes')
                ->join(
                    'product_attribute_values as product_values',
                    'product_values.attribute_id',
                    '=',
                    'attributes.id'
                )
                ->where('product_values.product_id', $productId)
                ->select(['attributes.id', 'attributes.name', 'attributes.type'])
                ->distinct()
                ->orderBy('attributes.id')
                ->get()
                ->map(fn (object $row) => (array) $row)
                ->all(),
            'attribute_value_definitions' => DB::table('attribute_values')
                ->join(
                    'product_attribute_values as product_values',
                    'product_values.attribute_value_id',
                    '=',
                    'attribute_values.id'
                )
                ->where('product_values.product_id', $productId)
                ->select([
                    'attribute_values.id',
                    'attribute_values.attribute_id',
                    'attribute_values.value',
                    'attribute_values.color',
                ])
                ->distinct()
                ->orderBy('attribute_values.id')
                ->get()
                ->map(fn (object $row) => (array) $row)
                ->all(),
            'images' => $this->rows('product_images', $productId, ['id', 'path', 'order']),
            'files' => $this->rows('product_files', $productId, ['id', 'path', 'extension', 'size', 'sha256']),
            'variants' => $this->rows('product_variants', $productId, [
                'id',
                'name',
                'price',
                'special_price',
                'sku',
                'manage_stock',
                'qty',
                'in_stock',
                'is_default',
                'is_active',
                'position',
            ]),
            'variant_attribute_values' => DB::table('product_variant_attribute_value as values')
                ->join('product_variants as variants', 'variants.id', '=', 'values.product_variant_id')
                ->where('variants.product_id', $productId)
                ->orderBy('values.product_variant_id')
                ->orderBy('values.attribute_id')
                ->orderBy('values.attribute_value_id')
                ->get([
                    'values.product_variant_id',
                    'values.attribute_id',
                    'values.attribute_value_id',
                ])
                ->map(fn (object $row) => (array) $row)
                ->all(),
        ];
    }

    private function evaluationContext(Product $product): array
    {
        $store = Store::query()
            ->with('seller.kyc')
            ->whereKey($product->store_id)
            ->first();

        if ($store === null) {
            return [
                'store_id' => null,
                'store_status' => null,
                'store_is_active' => false,
                'store_not_suspended' => false,
                'auto_approve_products' => null,
                'auto_approval_grant_current' => false,
                'auto_approval_eligible' => false,
                'store_eligible' => false,
                'store_eligibility_epoch' => null,
                'store_moderation_version' => null,
                'store_reviewed_version' => null,
                'store_review_is_current' => false,
                'seller_id' => null,
                'seller_user_type' => null,
                'seller_email_verified' => null,
                'seller_eligibility_epoch' => null,
                'kyc_id' => null,
                'kyc_status' => null,
                'kyc_expires_on' => null,
                'kyc_eligibility_epoch' => null,
                'kyc_eligible' => false,
                'eligible' => false,
                'automatic_approval_enabled' => (bool) config('product_moderation.automatic_approval_enabled', true),
                'policy_version' => (string) config('product_moderation.policy_version'),
            ];
        }

        return [
            ...app(SellerEligibilityService::class)->storeSnapshot($store),
            'automatic_approval_enabled' => (bool) config('product_moderation.automatic_approval_enabled', true),
            'policy_version' => (string) config('product_moderation.policy_version'),
        ];
    }

    /** @return array<string, int|string|null> */
    private function decisionEligibilityPins(
        array $context,
        ?string $contextHash = null,
    ): array {
        return [
            'reviewed_user_eligibility_epoch' => $context['seller_eligibility_epoch'] === null
                ? null
                : (int) $context['seller_eligibility_epoch'],
            'reviewed_kyc_id' => $context['kyc_id'] === null
                ? null
                : (int) $context['kyc_id'],
            'reviewed_kyc_eligibility_epoch' => $context['kyc_eligibility_epoch'] === null
                ? null
                : (int) $context['kyc_eligibility_epoch'],
            'reviewed_store_eligibility_epoch' => $context['store_eligibility_epoch'] === null
                ? null
                : (int) $context['store_eligibility_epoch'],
            'reviewed_context_hash' => $contextHash ?? $this->fingerprint($context),
            'reviewed_policy_version' => (string) $context['policy_version'],
        ];
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $table, int $productId, array $columns): array
    {
        return DB::table($table)
            ->where('product_id', $productId)
            ->orderBy('id')
            ->get($columns)
            ->map(fn (object $row) => (array) $row)
            ->all();
    }

    private function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode(
            $snapshot,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
    }

    private function cleanReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : mb_substr($reason, 0, 5000);
    }

    /**
     * @param  array<int, array{message: string}>  $reasons
     */
    private function manualReviewReason(array $reasons): string
    {
        $messages = array_slice(array_column($reasons, 'message'), 0, 3);

        if ($messages === []) {
            return 'Manual review required because automatic approval is disabled.';
        }

        return 'Manual review required: '.implode(' ', $messages);
    }
}
