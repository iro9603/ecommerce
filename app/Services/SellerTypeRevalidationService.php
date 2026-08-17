<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreAutoApprovalAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SellerTypeRevalidationService
{
    public function __construct(
        private readonly ProductModerationService $moderation,
    ) {}

    public function handle(User $seller, string $previousUserType): int
    {
        if (! $seller->exists || $previousUserType === (string) $seller->user_type) {
            return 0;
        }

        $plans = DB::transaction(function () use ($seller, $previousUserType): array {
            // Always acquire the seller before its stores. The admin trust
            // endpoint follows the same order to avoid lock inversion.
            $currentSeller = User::query()
                ->whereKey($seller->getKey())
                ->lockForUpdate()
                ->first();

            if (! $currentSeller) {
                return [];
            }

            $stores = Store::withTrashed()
                ->where('seller_id', $currentSeller->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $plans = [];

            foreach ($stores as $store) {
                $previousTrust = (bool) $store->auto_approve_products;

                // Any role transition invalidates an earlier trust decision.
                // Becoming a vendor again never restores it automatically.
                if ($previousTrust) {
                    $store->forceFill(['auto_approve_products' => false])->saveQuietly();
                }

                $reason = sprintf(
                    'Seller account type transition %s to %s was observed (current type: %s); store trust and existing product reviews were invalidated.',
                    $previousUserType,
                    $seller->user_type,
                    $currentSeller->user_type,
                );
                $productIds = Product::withTrashed()
                    ->where('store_id', $store->getKey())
                    ->whereIn('approved_status', [
                        Product::APPROVAL_APPROVED,
                        Product::APPROVAL_PENDING,
                    ])
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                // Fail closed before producing the detailed snapshots. If a
                // later resubmission fails, no previously approved product can
                // become visible when the account type changes again.
                Product::withTrashed()
                    ->whereIn('id', $productIds)
                    ->where('approved_status', Product::APPROVAL_APPROVED)
                    ->update([
                        'approved_status' => Product::APPROVAL_PENDING,
                        'reviewed_version' => null,
                        'approved_at' => null,
                        'approved_by' => null,
                        'moderation_reason' => $reason,
                        'moderation_fingerprint' => null,
                        'risk_level' => null,
                        'risk_score' => null,
                        'updated_at' => now(),
                    ]);

                $auditId = null;

                if ($previousTrust) {
                    $auditId = StoreAutoApprovalAudit::query()->create([
                        'store_id' => $store->getKey(),
                        'admin_id' => null,
                        'previous_value' => true,
                        'new_value' => false,
                        'reason' => $reason,
                        'eligibility_snapshot' => $this->eligibilitySnapshot(
                            $store,
                            $currentSeller,
                            $previousUserType,
                            (string) $seller->user_type,
                        ),
                        'pending_products_resubmitted' => 0,
                        'ip_address' => request()?->ip(),
                        'user_agent' => mb_substr((string) request()?->userAgent(), 0, 2000),
                    ])->getKey();
                }

                $plans[] = [
                    'audit_id' => $auditId,
                    'product_ids' => $productIds,
                    'reason' => $reason,
                ];
            }

            return $plans;
        }, 5);

        $resubmitted = 0;

        foreach ($plans as $plan) {
            foreach ($plan['product_ids'] as $productId) {
                $product = Product::withTrashed()->find($productId);

                if (
                    ! $product
                    || ! in_array($product->approved_status, [
                        Product::APPROVAL_APPROVED,
                        Product::APPROVAL_PENDING,
                    ], true)
                ) {
                    continue;
                }

                $this->moderation->markForReview($product, null, $plan['reason']);
                $resubmitted++;

                if ($plan['audit_id'] !== null) {
                    StoreAutoApprovalAudit::query()
                        ->whereKey($plan['audit_id'])
                        ->increment('pending_products_resubmitted');
                }
            }
        }

        return $resubmitted;
    }

    /** @return array<string, bool|string|null> */
    private function eligibilitySnapshot(
        Store $store,
        User $seller,
        string $previousUserType,
        string $observedUserType,
    ): array {
        $seller->loadMissing('kyc');

        $snapshot = [
            'trigger' => 'seller_user_type_changed',
            'previous_seller_user_type' => $previousUserType,
            'observed_seller_user_type' => $observedUserType,
            'seller_user_type' => $seller->user_type,
            'store_status' => $store->status,
            'store_not_suspended' => $store->suspended_at === null,
            'seller_is_vendor' => $seller->user_type === 'vendor',
            'seller_email_verified' => $seller->email_verified_at !== null,
            'kyc_status' => $seller->kyc?->status,
        ];

        $snapshot['eligible'] = $snapshot['store_status'] === 'active'
            && $snapshot['store_not_suspended']
            && $snapshot['seller_is_vendor']
            && $snapshot['seller_email_verified']
            && $snapshot['kyc_status'] === 'approved';

        return $snapshot;
    }
}
