<?php

namespace App\Services;

use App\Models\Kyc;
use App\Models\Product;
use App\Models\SellerEligibilityEvent;
use App\Models\Store;
use App\Models\StoreAutoApprovalAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SellerEligibilityInvalidationService
{
    public function __construct(
        private readonly SellerEligibilityService $eligibility,
    ) {}

    /** @param array<string, mixed> $extra */
    public function handle(User $seller, string $reason, array $extra = []): int
    {
        if (! $seller->exists) {
            return 0;
        }

        return DB::transaction(function () use ($seller, $reason, $extra): int {
            $currentSeller = User::withTrashed()
                ->whereKey($seller->getKey())
                ->lockForUpdate()
                ->first();

            if ($currentSeller === null) {
                return 0;
            }

            $stores = Store::withTrashed()
                ->where('seller_id', $currentSeller->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $kyc = Kyc::query()
                ->where('user_id', $currentSeller->getKey())
                ->lockForUpdate()
                ->first();
            $currentSeller->setRelation('kyc', $kyc);
            $sellerSnapshot = $this->eligibility->sellerSnapshot($currentSeller);
            $trigger = (string) ($extra['trigger'] ?? 'seller_eligibility_changed');
            $eventKey = implode(':', [
                $trigger,
                $currentSeller->getKey(),
                (int) $currentSeller->eligibility_epoch,
                $kyc?->getKey() ?? 'none',
                $kyc === null ? 'none' : (int) $kyc->eligibility_epoch,
            ]);

            SellerEligibilityEvent::query()->firstOrCreate(
                ['event_key' => $eventKey],
                [
                    'user_id' => $currentSeller->getKey(),
                    'kyc_id' => $kyc?->getKey(),
                    'trigger' => $trigger,
                    'user_epoch' => (int) $currentSeller->eligibility_epoch,
                    'kyc_epoch' => $kyc === null ? null : (int) $kyc->eligibility_epoch,
                    'eligible' => (bool) $sellerSnapshot['eligible'],
                    'snapshot' => array_merge($sellerSnapshot, $extra),
                    'occurred_at' => now(),
                ],
            );

            $affected = 0;

            foreach ($stores as $store) {
                $previousTrust = (bool) $store->auto_approve_products;
                $hasGrant = $store->auto_approval_user_epoch !== null
                    || $store->auto_approval_kyc_id !== null
                    || $store->auto_approval_kyc_epoch !== null
                    || $store->auto_approval_store_epoch !== null;

                if ($previousTrust || $hasGrant) {
                    $store->forceFill([
                        'auto_approve_products' => false,
                        'eligibility_epoch' => ((int) $store->eligibility_epoch) + 1,
                        'auto_approval_user_epoch' => null,
                        'auto_approval_kyc_id' => null,
                        'auto_approval_kyc_epoch' => null,
                        'auto_approval_store_epoch' => null,
                    ])->saveQuietly();
                }

                $productCount = Product::withTrashed()
                    ->where('store_id', $store->getKey())
                    ->whereIn('approved_status', [
                        Product::APPROVAL_APPROVED,
                        Product::APPROVAL_PENDING,
                    ])
                    ->count();
                $affected += $productCount;

                if ($previousTrust) {
                    $store->setRelation('seller', $currentSeller);
                    StoreAutoApprovalAudit::query()->create([
                        'store_id' => $store->getKey(),
                        'admin_id' => null,
                        'previous_value' => true,
                        'new_value' => false,
                        'reason' => $reason,
                        'eligibility_snapshot' => array_merge(
                            $this->eligibility->storeSnapshot($store),
                            $extra,
                        ),
                        'pending_products_resubmitted' => 0,
                        'ip_address' => request()?->ip(),
                        'user_agent' => mb_substr((string) request()?->userAgent(), 0, 2000),
                    ]);
                }
            }

            return $affected;
        }, 5);
    }
}
