<?php

namespace App\Console\Commands;

use App\Models\Kyc;
use App\Models\Store;
use App\Models\User;
use App\Services\SellerEligibilityInvalidationService;
use App\Services\SellerEligibilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileSellerEligibility extends Command
{
    protected $signature = 'security:reconcile-eligibility {--chunk=200}';

    protected $description = 'Reconcile expired KYC and stale automatic-approval grants.';

    public function handle(
        SellerEligibilityService $eligibility,
        SellerEligibilityInvalidationService $invalidation,
    ): int {
        $chunk = max(1, min(1000, (int) $this->option('chunk')));
        $today = $eligibility->today()->toDateString();
        $expired = 0;
        $staleTrust = 0;

        Kyc::query()
            ->where('status', 'approved')
            ->whereNotNull('document_expiry_date')
            ->whereDate('document_expiry_date', '<', $today)
            ->where(function ($query): void {
                $query->whereNull('expiration_reconciled_for')
                    ->orWhereColumn('expiration_reconciled_for', '!=', 'document_expiry_date');
            })
            ->orderBy('id')
            ->chunkById($chunk, function ($kycs) use ($invalidation, $today, &$expired): void {
                foreach ($kycs as $candidate) {
                    $processed = DB::transaction(function () use ($candidate, $invalidation, $today): bool {
                        $userId = (int) $candidate->user_id;
                        $seller = User::withTrashed()->whereKey($userId)->lockForUpdate()->first();

                        if ($seller === null) {
                            return false;
                        }

                        Store::withTrashed()
                            ->where('seller_id', $userId)
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get();
                        $kyc = Kyc::query()
                            ->whereKey($candidate->getKey())
                            ->where('user_id', $userId)
                            ->lockForUpdate()
                            ->first();

                        if (
                            $kyc === null
                            || $kyc->status !== 'approved'
                            || $kyc->document_expiry_date === null
                            || $kyc->document_expiry_date->format('Y-m-d') >= $today
                            || $kyc->expiration_reconciled_for?->format('Y-m-d')
                                === $kyc->document_expiry_date->format('Y-m-d')
                        ) {
                            return false;
                        }

                        $expiry = $kyc->document_expiry_date->format('Y-m-d');
                        $kyc->forceFill([
                            'eligibility_epoch' => ((int) $kyc->eligibility_epoch) + 1,
                            'expiration_reconciled_for' => $expiry,
                        ])->saveQuietly();

                        $invalidation->handle(
                            $seller,
                            'KYC document expired; automatic trust was revoked.',
                            [
                                'trigger' => 'kyc_expired',
                                'document_expiry_date' => $expiry,
                                'effective_on' => $today,
                            ],
                        );

                        return true;
                    }, 5);

                    $expired += $processed ? 1 : 0;
                }
            });

        Store::query()
            ->where('auto_approve_products', true)
            ->with('seller.kyc')
            ->orderBy('id')
            ->chunkById($chunk, function ($stores) use ($eligibility, $invalidation, &$staleTrust): void {
                foreach ($stores as $store) {
                    if ($eligibility->storeSnapshot($store)['auto_approval_eligible']) {
                        continue;
                    }

                    $seller = $store->seller;

                    if ($seller === null) {
                        continue;
                    }

                    $invalidation->handle(
                        $seller,
                        'A stale automatic-approval grant was reconciled fail-closed.',
                        ['trigger' => 'stale_trust_reconciled'],
                    );
                    $staleTrust++;
                }
            });

        $this->info(sprintf(
            'Reconciled %d expired KYC record(s) and %d stale trust grant(s).',
            $expired,
            $staleTrust,
        ));

        return self::SUCCESS;
    }
}
