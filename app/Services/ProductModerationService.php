<?php

namespace App\Services;

use App\Jobs\EvaluateProductForApproval;
use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductApprovalReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class ProductModerationService
{
    public function __construct(private readonly ProductRiskEvaluator $riskEvaluator) {}

    public function submit(
        Product $product,
        ?User $submittedBy = null,
        ?string $reason = null
    ): ProductApprovalReview {
        if (! $product->exists) {
            throw new LogicException('A product must be saved before it can be submitted for moderation.');
        }

        [$review, $version] = DB::transaction(function () use ($product, $submittedBy, $reason): array {
            $current = Product::withTrashed()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $snapshot = $this->snapshot($current);
            $fingerprint = $this->fingerprint($snapshot);
            $pendingReview = ProductApprovalReview::query()
                ->where('product_id', $current->getKey())
                ->where('version', (int) $current->moderation_version)
                ->where('status', ProductApprovalReview::STATUS_PENDING)
                ->first();

            if (
                $current->approved_status === Product::APPROVAL_PENDING
                && (int) $current->moderation_version > 0
                && hash_equals((string) $current->moderation_fingerprint, $fingerprint)
                && $pendingReview !== null
            ) {
                $review = $this->pendingReview(
                    $current,
                    (int) $current->moderation_version,
                    $snapshot,
                    $fingerprint,
                    $submittedBy,
                    $reason
                );

                return [$review, (int) $current->moderation_version];
            }

            ProductApprovalReview::query()
                ->where('product_id', $current->getKey())
                ->where('status', ProductApprovalReview::STATUS_PENDING)
                ->update([
                    'status' => ProductApprovalReview::STATUS_SUPERSEDED,
                    'decision_reason' => 'Superseded by a newer product submission.',
                    'reviewed_at' => now(),
                ]);

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
                'submitted_at' => $submittedAt,
            ]);

            return [$review, $version];
        });

        EvaluateProductForApproval::dispatch((int) $product->getKey(), $version)->afterCommit();
        $product->refresh();

        return $review;
    }

    public function markForReview(
        Product $product,
        ?User $submittedBy = null,
        ?string $reason = null
    ): ProductApprovalReview {
        return $this->submit($product, $submittedBy, $reason);
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
            $expectedVersion
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
            $expectedVersion
        );
    }

    public function evaluatePending(int $productId, int $expectedVersion): bool
    {
        $product = Product::query()
            ->with(['store.seller.kyc'])
            ->find($productId);

        if (
            ! $product
            || $product->approved_status !== Product::APPROVAL_PENDING
            || (int) $product->moderation_version !== $expectedVersion
        ) {
            return false;
        }

        $submittedReview = ProductApprovalReview::query()
            ->where('product_id', $productId)
            ->where('version', $expectedVersion)
            ->where('status', ProductApprovalReview::STATUS_PENDING)
            ->first();

        if (
            ! $submittedReview
            || ! hash_equals($submittedReview->content_hash, $this->fingerprint($this->snapshot($product)))
        ) {
            return false;
        }

        $assessment = $this->riskEvaluator->evaluate($product);

        return DB::transaction(function () use ($productId, $expectedVersion, $assessment): bool {
            $current = Product::query()->whereKey($productId)->lockForUpdate()->first();

            // A queued evaluation may finish after a vendor has submitted a
            // newer version. It must never review or approve that new version.
            if (
                ! $current
                || $current->approved_status !== Product::APPROVAL_PENDING
                || (int) $current->moderation_version !== $expectedVersion
            ) {
                return false;
            }

            $review = ProductApprovalReview::query()
                ->where('product_id', $productId)
                ->where('version', $expectedVersion)
                ->lockForUpdate()
                ->first();

            if (! $review || $review->status !== ProductApprovalReview::STATUS_PENDING) {
                return false;
            }

            if (! hash_equals($review->content_hash, $this->fingerprint($this->snapshot($current)))) {
                return false;
            }

            $review->forceFill([
                'risk_score' => $assessment['score'],
                'risk_level' => $assessment['level'],
                'risk_reasons' => $assessment['reasons'],
            ]);

            if ($assessment['auto_approvable']) {
                $decisionReason = 'Automatically approved after passing the product risk evaluation.';

                $current->forceFill([
                    'approved_status' => Product::APPROVAL_APPROVED,
                    'reviewed_version' => $expectedVersion,
                    'approved_at' => now(),
                    'approved_by' => null,
                    'moderation_reason' => $decisionReason,
                    'risk_score' => $assessment['score'],
                    'risk_level' => $assessment['level'],
                ])->save();

                $review->forceFill([
                    'status' => ProductApprovalReview::STATUS_APPROVED,
                    'source' => ProductApprovalReview::SOURCE_AUTOMATIC,
                    'decision_reason' => $decisionReason,
                    'reviewed_at' => now(),
                ]);
            } else {
                $decisionReason = $this->manualReviewReason($assessment['reasons']);

                $current->forceFill([
                    'moderation_reason' => $decisionReason,
                    'risk_score' => $assessment['score'],
                    'risk_level' => $assessment['level'],
                ])->save();

                $review->decision_reason = $decisionReason;
            }

            $review->save();

            return true;
        });
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

            if (
                $expectedVersion !== null
                && (int) $current->moderation_version !== $expectedVersion
            ) {
                return null;
            }

            $version = (int) $current->moderation_version;
            $snapshot = $this->snapshot($current);
            $fingerprint = $this->fingerprint($snapshot);

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

            if ($review && ! hash_equals($review->content_hash, $fingerprint)) {
                if ($expectedVersion !== null) {
                    return null;
                }

                $review->forceFill([
                    'status' => ProductApprovalReview::STATUS_SUPERSEDED,
                    'decision_reason' => 'Superseded because the product content changed before review.',
                    'reviewed_at' => now(),
                ])->save();

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
                    'submitted_at' => $current->submitted_at ?? now(),
                ]);
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
            ])->save();

            $review->forceFill([
                'status' => $decision,
                'source' => ProductApprovalReview::SOURCE_MANUAL,
                'reviewed_by' => $reviewer?->getKey(),
                'decision_reason' => $decisionReason,
                'risk_score' => $assessment['score'],
                'risk_level' => $assessment['level'],
                'risk_reasons' => $assessment['reasons'],
                'reviewed_at' => $reviewedAt,
            ])->save();

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
        ?User $submittedBy,
        ?string $reason
    ): ProductApprovalReview {
        $review = ProductApprovalReview::query()->firstOrNew([
            'product_id' => $product->getKey(),
            'version' => $version,
        ]);

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
        ])->save();

        return $review;
    }

    private function snapshot(Product $product): array
    {
        $productId = (int) $product->getKey();
        $brand = $product->brand_id
            ? DB::table('brands')
                ->where('id', $product->brand_id)
                ->first(['id', 'name', 'slug', 'is_active'])
            : null;

        return [
            'store_security' => $this->storeSecuritySnapshot($product),
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
            'files' => $this->rows('product_files', $productId, ['id', 'path', 'extension', 'size']),
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

    /** @return array<string, mixed>|null */
    private function storeSecuritySnapshot(Product $product): ?array
    {
        $store = DB::table('stores')
            ->leftJoin('users', 'users.id', '=', 'stores.seller_id')
            ->leftJoin('kycs', 'kycs.user_id', '=', 'users.id')
            ->where('stores.id', $product->store_id)
            ->first([
                'stores.id',
                'stores.status',
                'stores.suspended_at',
                'stores.auto_approve_products',
                'users.id as seller_id',
                'users.user_type as seller_user_type',
                'users.email_verified_at',
                'kycs.status as kyc_status',
            ]);

        return $store === null ? null : (array) $store;
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
