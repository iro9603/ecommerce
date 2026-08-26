<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreApprovalReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreModerationService
{
    public function __construct(
        private readonly ProductModerationService $moderation,
        private readonly ProductContentSanitizer $contentSanitizer,
        private readonly SellerEligibilityService $eligibility,
        private readonly StoreStateTransitionPolicy $transitions,
    ) {}

    public function reevaluatePendingProducts(Store $store, string $reason): int
    {
        return $this->moderation->reevaluatePendingStoreContext($store, $reason);
    }

    /**
     * @return array<int, string>
     */
    public static function materialFields(): array
    {
        return [
            'name',
            'slug',
            'logo',
            'banner',
            'phone',
            'email',
            'short_description',
            'long_description',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'currency',
            'country',
            'timezone',
            'seo_title',
            'seo_description',
            'social_links',
            'seller_id',
        ];
    }

    /**
     * @return array{store: Store, review: ?StoreApprovalReview, was_approved: bool, replaced_media: list<string>}
     */
    public function updateProfile(User $seller, array $attributes): array
    {
        $attributes = $this->sanitizeProfileContent($attributes);

        return DB::transaction(function () use ($seller, $attributes): array {
            $lockedSeller = User::withTrashed()
                ->whereKey($seller->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $lockedSeller->trashed() || $lockedSeller->user_type !== 'vendor',
                403,
                'Only an active vendor can update a store profile.',
            );

            $current = Store::withTrashed()
                ->where('seller_id', $lockedSeller->getKey())
                ->lockForUpdate()
                ->first();

            abort_if(
                $current?->trashed(),
                409,
                'A deleted store profile must be restored before it can be updated.',
            );

            $isNew = $current === null;
            $current ??= new Store();

            if ($isNew) {
                $current->seller_id = $lockedSeller->getKey();
                $current->slug = $this->uniqueSlug((string) $attributes['name']);
            }

            $wasApproved = ! $isNew && $current->isApproved();
            $replacedMedia = [];

            foreach (['logo', 'banner'] as $field) {
                if (
                    array_key_exists($field, $attributes)
                    && $current->{$field}
                    && $current->{$field} !== $attributes[$field]
                ) {
                    $replacedMedia[] = (string) $current->{$field};
                }
            }

            $current->fill($attributes);
            $hasMaterialChanges = $isNew || $current->isDirty(self::materialFields());

            if ($current->isDirty() || $isNew) {
                $current->save();
            }

            $review = $hasMaterialChanges
                ? $this->submitForReview(
                    $current,
                    $lockedSeller,
                    $wasApproved
                        ? 'The vendor updated the store profile after it was approved.'
                        : 'The vendor created or updated the store profile.',
                )
                : null;

            $current->refresh();

            return [
                'store' => $current,
                'review' => $review,
                'was_approved' => $wasApproved,
                'replaced_media' => array_values(array_unique($replacedMedia)),
            ];
        }, 5);
    }

    private function sanitizeProfileContent(array $attributes): array
    {
        foreach (['short_description', 'long_description'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = $this->contentSanitizer->sanitize($attributes[$field]);
            }
        }

        return $attributes;
    }

    public function submitForReview(
        Store $store,
        ?User $submittedBy = null,
        ?string $reason = null
    ): ?StoreApprovalReview {
        return DB::transaction(function () use ($store, $submittedBy, $reason): ?StoreApprovalReview {
            $current = $this->lockStore($store);
            $this->transitions->assertAllowed($current, StoreStateTransitionPolicy::ACTION_SUBMIT);

            $snapshot = $this->snapshot($current);
            $fingerprint = $this->contentHash($snapshot);
            $version = (int) $current->moderation_version;

            if (
                $version > 0
                && $current->status === Store::STATUS_PENDING
                && hash_equals((string) $current->moderation_fingerprint, $fingerprint)
            ) {
                $pending = StoreApprovalReview::query()
                    ->where('store_id', $current->getKey())
                    ->where('version', $version)
                    ->where('status', StoreApprovalReview::STATUS_PENDING)
                    ->first();

                if ($pending !== null) {
                    return $pending;
                }
            }

            StoreApprovalReview::query()
                ->where('store_id', $current->getKey())
                ->where('status', StoreApprovalReview::STATUS_PENDING)
                ->update([
                    'status' => StoreApprovalReview::STATUS_SUPERSEDED,
                    'decision_reason' => 'Superseded by a newer store submission.',
                    'reviewed_at' => now(),
                ]);

            $newVersion = $version + 1;
            $submittedAt = now();

            $current->forceFill([
                'status' => Store::STATUS_PENDING,
                'is_active' => false,
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'suspended_at' => null,
                'moderation_version' => $newVersion,
                'reviewed_version' => null,
                'moderation_fingerprint' => $fingerprint,
                'moderation_reason' => $this->cleanReason($reason),
                'submitted_at' => $submittedAt,
                'auto_approve_products' => false,
                'auto_approval_user_epoch' => null,
                'auto_approval_kyc_id' => null,
                'auto_approval_kyc_epoch' => null,
                'auto_approval_store_epoch' => null,
            ])->save();

            return StoreApprovalReview::query()->create([
                'store_id' => $current->getKey(),
                'version' => $newVersion,
                'status' => StoreApprovalReview::STATUS_PENDING,
                'source' => $submittedBy === null
                    ? StoreApprovalReview::SOURCE_SYSTEM
                    : StoreApprovalReview::SOURCE_MANUAL,
                'submitted_by' => $submittedBy?->getKey(),
                'submission_reason' => $this->cleanReason($reason),
                'snapshot' => $snapshot,
                'eligibility_snapshot' => $this->eligibilitySnapshot($current),
                'content_hash' => $fingerprint,
                'submitted_at' => $submittedAt,
            ]);
        }, 5);
    }

    public function approve(
        Store $store,
        Admin $admin,
        ?int $expectedVersion = null,
        ?string $reason = null
    ): ?StoreApprovalReview {
        return $this->decide(
            $store,
            $admin,
            StoreStateTransitionPolicy::ACTION_APPROVE,
            Store::STATUS_APPROVED,
            StoreApprovalReview::STATUS_APPROVED,
            $reason ?? 'Approved by an administrator.',
            $expectedVersion,
        );
    }

    public function reject(
        Store $store,
        Admin $admin,
        string $reason,
        ?int $expectedVersion = null
    ): ?StoreApprovalReview {
        return $this->decide(
            $store,
            $admin,
            StoreStateTransitionPolicy::ACTION_REJECT,
            Store::STATUS_REJECTED,
            StoreApprovalReview::STATUS_REJECTED,
            $reason,
            $expectedVersion,
        );
    }

    public function suspend(
        Store $store,
        Admin $admin,
        string $reason,
        ?int $expectedVersion = null
    ): ?StoreApprovalReview {
        return $this->decide(
            $store,
            $admin,
            StoreStateTransitionPolicy::ACTION_SUSPEND,
            Store::STATUS_SUSPENDED,
            StoreApprovalReview::STATUS_SUSPENDED,
            $reason,
            $expectedVersion,
        );
    }

    public function restore(
        Store $store,
        Admin $admin,
        ?int $expectedVersion = null
    ): ?StoreApprovalReview {
        return $this->decide(
            $store,
            $admin,
            StoreStateTransitionPolicy::ACTION_RESTORE,
            Store::STATUS_APPROVED,
            StoreApprovalReview::STATUS_RESTORED,
            'Restored by an administrator.',
            $expectedVersion,
        );
    }

    private function decide(
        Store $store,
        Admin $admin,
        string $action,
        string $storeStatus,
        string $reviewStatus,
        string $reason,
        ?int $expectedVersion
    ): ?StoreApprovalReview {
        return DB::transaction(function () use (
            $store,
            $admin,
            $action,
            $storeStatus,
            $reviewStatus,
            $reason,
            $expectedVersion
        ): ?StoreApprovalReview {
            $current = $this->lockStore($store);
            $version = (int) $current->moderation_version;

            if ($expectedVersion !== null && $expectedVersion !== $version) {
                return null;
            }

            $this->transitions->assertAllowed($current, $action);

            if (in_array($storeStatus, [Store::STATUS_APPROVED], true)) {
                $this->assertEligibleForApproval($current);
            }

            $reviewedAt = now();
            $snapshot = $this->snapshot($current);
            $fingerprint = $this->contentHash($snapshot);
            $decisionReason = $this->cleanReason($reason);

            $forceFill = [
                'status' => $storeStatus,
                'is_active' => $storeStatus === Store::STATUS_APPROVED,
                'reviewed_version' => $version,
                'moderation_reason' => $decisionReason,
                'approved_at' => $storeStatus === Store::STATUS_APPROVED ? $reviewedAt : null,
                'approved_by' => $storeStatus === Store::STATUS_APPROVED ? $admin->getKey() : null,
                'rejected_at' => $storeStatus === Store::STATUS_REJECTED ? $reviewedAt : null,
                'rejection_reason' => $storeStatus === Store::STATUS_REJECTED ? $decisionReason : null,
                'suspended_at' => $storeStatus === Store::STATUS_SUSPENDED ? $reviewedAt : null,
                'auto_approve_products' => false,
                'auto_approval_user_epoch' => null,
                'auto_approval_kyc_id' => null,
                'auto_approval_kyc_epoch' => null,
                'auto_approval_store_epoch' => null,
            ];

            if ($storeStatus === Store::STATUS_APPROVED) {
                $forceFill['rejected_at'] = null;
                $forceFill['rejection_reason'] = null;
                $forceFill['suspended_at'] = null;
            }

            $current->forceFill($forceFill)->save();

            return StoreApprovalReview::query()->create([
                'store_id' => $current->getKey(),
                'version' => $version,
                'status' => $reviewStatus,
                'source' => StoreApprovalReview::SOURCE_MANUAL,
                'reviewed_by' => $admin->getKey(),
                'decision_reason' => $decisionReason,
                'snapshot' => $snapshot,
                'eligibility_snapshot' => $this->eligibilitySnapshot($current),
                'content_hash' => $fingerprint,
                'submitted_at' => $current->submitted_at ?? $reviewedAt,
                'reviewed_at' => $reviewedAt,
            ]);
        }, 5);
    }

    private function lockStore(Store $store): Store
    {
        $sellerId = (int) $store->seller_id;
        abort_if($sellerId < 1, 409, 'The store seller association changed.');

        User::withTrashed()->whereKey($sellerId)->lockForUpdate()->firstOrFail();

        return Store::query()
            ->whereKey($store->getKey())
            ->where('seller_id', $sellerId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertEligibleForApproval(Store $store): void
    {
        $store->load(['seller.kyc']);

        $eligible = $this->eligibility->sellerSnapshot($store->seller)['eligible'];

        abort_unless($eligible, 422, 'The store cannot be approved: the seller must be a verified vendor with approved KYC.');
    }

    private function snapshot(Store $store): array
    {
        $snapshot = [
            'id' => $store->getKey(),
        ];

        foreach (self::materialFields() as $field) {
            $snapshot[$field] = $store->{$field};
        }

        return $snapshot;
    }

    private function eligibilitySnapshot(Store $store): array
    {
        return $this->eligibility->storeSnapshot($store);
    }

    private function contentHash(array $snapshot): string
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

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'store';
        $slug = $baseSlug;
        $suffix = 2;

        while (Store::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
