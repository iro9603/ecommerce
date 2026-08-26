<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAutoApprovalRequest;
use App\Models\Admin;
use App\Models\Kyc;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreAutoApprovalAudit;
use App\Models\User;
use App\Services\ProductModerationService;
use App\Services\SellerEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreAutoApprovalController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:Store Auto-Approval Management')];
    }

    public function update(
        StoreAutoApprovalRequest $request,
        Store $store,
        ProductModerationService $moderation,
        SellerEligibilityService $eligibilityService,
    ): JsonResponse {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin instanceof Admin, 401);

        $result = DB::transaction(function () use (
            $request,
            $store,
            $moderation,
            $eligibilityService,
            $admin
        ): array {
            $storeReference = Store::query()
                ->whereKey($store->getKey())
                ->firstOrFail(['id', 'seller_id']);

            if ($storeReference->seller_id !== null) {
                User::query()
                    ->whereKey($storeReference->seller_id)
                    ->lockForUpdate()
                    ->first();
            }

            $lockedStore = Store::query()
                ->whereKey($store->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedStore->seller_id !== $storeReference->seller_id) {
                throw ValidationException::withMessages([
                    'enabled' => 'The store seller changed concurrently. Retry the operation.',
                ]);
            }

            $lockedSeller = $lockedStore->seller_id === null
                ? null
                : User::query()->whereKey($lockedStore->seller_id)->first();
            $lockedKyc = $lockedSeller === null
                ? null
                : Kyc::query()
                    ->where('user_id', $lockedSeller->getKey())
                    ->lockForUpdate()
                    ->first();
            $lockedSeller?->setRelation('kyc', $lockedKyc);
            $lockedStore->setRelation('seller', $lockedSeller);
            $enabled = $request->boolean('enabled');
            $previous = (bool) $lockedStore->auto_approve_products;
            $eligibility = $eligibilityService->storeSnapshot($lockedStore);
            $reason = trim((string) $request->validated('reason'));

            if ($enabled && ! $eligibility['eligible']) {
                if ($previous) {
                    $failClosedReason = 'Automatic fail-closed trust revocation: '.$reason;
                    $lockedStore->forceFill([
                        'auto_approve_products' => false,
                        'eligibility_epoch' => ((int) $lockedStore->eligibility_epoch) + 1,
                        'auto_approval_user_epoch' => null,
                        'auto_approval_kyc_id' => null,
                        'auto_approval_kyc_epoch' => null,
                        'auto_approval_store_epoch' => null,
                    ])->save();

                    $audit = StoreAutoApprovalAudit::query()->create([
                        'store_id' => $lockedStore->getKey(),
                        'admin_id' => $admin->getKey(),
                        'previous_value' => true,
                        'new_value' => false,
                        'reason' => $failClosedReason,
                        'eligibility_snapshot' => $eligibility,
                        'pending_products_resubmitted' => 0,
                        'ip_address' => $request->ip(),
                        'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
                    ]);

                    return [
                        'changed' => true,
                        'enabled' => false,
                        'resubmitted_products' => 0,
                        'ineligible_request' => true,
                        'remoderation_audit_id' => $audit->getKey(),
                        'remoderation_reason' => $failClosedReason,
                    ];
                }

                throw ValidationException::withMessages([
                    'enabled' => 'The store must be approved, unsuspended, owned by a verified vendor, and have approved KYC.',
                ]);
            }

            if ($enabled === $previous) {
                return [
                    'changed' => false,
                    'enabled' => $enabled,
                    'resubmitted_products' => 0,
                ];
            }

            $nextStoreEpoch = ((int) $lockedStore->eligibility_epoch) + 1;
            $lockedStore->forceFill([
                'auto_approve_products' => $enabled,
                'eligibility_epoch' => $nextStoreEpoch,
                'auto_approval_user_epoch' => $enabled
                    ? $eligibility['seller_eligibility_epoch']
                    : null,
                'auto_approval_kyc_id' => $enabled ? $eligibility['kyc_id'] : null,
                'auto_approval_kyc_epoch' => $enabled
                    ? $eligibility['kyc_eligibility_epoch']
                    : null,
                'auto_approval_store_epoch' => $enabled ? $nextStoreEpoch : null,
            ])->save();

            $audit = StoreAutoApprovalAudit::query()->create([
                'store_id' => $lockedStore->getKey(),
                'admin_id' => $admin->getKey(),
                'previous_value' => $previous,
                'new_value' => $enabled,
                'reason' => $reason,
                'eligibility_snapshot' => $eligibility,
                'pending_products_resubmitted' => 0,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            ]);

            return [
                'changed' => true,
                'enabled' => $enabled,
                'resubmitted_products' => 0,
                'audit_id' => $audit->getKey(),
                'store_id' => $lockedStore->getKey(),
                'context_reason' => 'Store automatic approval setting changed: '.$reason,
            ];
        }, 5);

        if ($result['ineligible_request'] ?? false) {
            throw ValidationException::withMessages([
                'enabled' => 'Automatic approval was revoked because the store is no longer eligible. Review its seller and security status before enabling it again.',
            ]);
        }

        if ($result['changed'] && ! ($result['ineligible_request'] ?? false)) {
            $currentStore = Store::query()->findOrFail($result['store_id']);
            $resubmitted = $moderation->reevaluatePendingStoreContext(
                $currentStore,
                $result['context_reason'],
            );

            StoreAutoApprovalAudit::query()
                ->whereKey($result['audit_id'])
                ->update(['pending_products_resubmitted' => $resubmitted]);
            $result['resubmitted_products'] = $resubmitted;
        }

        return response()->json([
            ...$result,
            'status' => 'success',
            'message' => $result['changed']
                ? 'Store automatic product approval updated.'
                : 'Store automatic product approval was already in the requested state.',
        ]);
    }

}
