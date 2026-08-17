<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAutoApprovalRequest;
use App\Models\Admin;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreAutoApprovalAudit;
use App\Models\User;
use App\Services\ProductModerationService;
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
    ): JsonResponse {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin instanceof Admin, 401);

        $result = DB::transaction(function () use (
            $request,
            $store,
            $moderation,
            $admin
        ): array {
            $storeReference = Store::query()
                ->whereKey($store->getKey())
                ->firstOrFail(['id', 'seller_id']);

            // Lock seller before store. User type changes use the same order,
            // so an enable request cannot race a role transition or deadlock
            // because of inverted locks.
            if ($storeReference->seller_id !== null) {
                User::query()
                    ->whereKey($storeReference->seller_id)
                    ->lockForUpdate()
                    ->first();
            }

            $store = Store::query()
                ->whereKey($store->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($store->seller_id !== $storeReference->seller_id) {
                throw ValidationException::withMessages([
                    'enabled' => 'The store seller changed concurrently. Retry the operation.',
                ]);
            }

            $store->load(['seller.kyc']);
            $enabled = $request->boolean('enabled');
            $previous = (bool) $store->auto_approve_products;
            $eligibility = $this->eligibilitySnapshot($store);
            $reason = trim((string) $request->validated('reason'));

            if ($enabled && ! $eligibility['eligible']) {
                if ($previous) {
                    $failClosedReason = 'Automatic fail-closed trust revocation: '.$reason;
                    $store->forceFill(['auto_approve_products' => false])->save();
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

                    Product::withTrashed()
                        ->whereIn('id', $productIds)
                        ->where('approved_status', Product::APPROVAL_APPROVED)
                        ->update([
                            'approved_status' => Product::APPROVAL_PENDING,
                            'reviewed_version' => null,
                            'approved_at' => null,
                            'approved_by' => null,
                            'moderation_reason' => $failClosedReason,
                            'moderation_fingerprint' => null,
                            'risk_level' => null,
                            'risk_score' => null,
                            'updated_at' => now(),
                        ]);

                    $audit = StoreAutoApprovalAudit::query()->create([
                        'store_id' => $store->getKey(),
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
                        'remoderation_product_ids' => $productIds,
                        'remoderation_reason' => $failClosedReason,
                    ];
                }

                throw ValidationException::withMessages([
                    'enabled' => 'The store must be active, unsuspended, owned by a verified vendor, and have approved KYC.',
                ]);
            }

            if ($enabled === $previous) {
                return [
                    'changed' => false,
                    'enabled' => $enabled,
                    'resubmitted_products' => 0,
                ];
            }

            $store->forceFill(['auto_approve_products' => $enabled])->save();
            $resubmittedProducts = 0;

            Product::query()
                ->where('store_id', $store->getKey())
                ->where('approved_status', Product::APPROVAL_PENDING)
                ->orderBy('id')
                ->each(function (Product $product) use (
                    $moderation,
                    $reason,
                    &$resubmittedProducts
                ): void {
                    $moderation->submit(
                        $product,
                        null,
                        'Store automatic approval setting changed: '.$reason,
                    );
                    $resubmittedProducts++;
                });

            StoreAutoApprovalAudit::query()->create([
                'store_id' => $store->getKey(),
                'admin_id' => $admin->getKey(),
                'previous_value' => $previous,
                'new_value' => $enabled,
                'reason' => $reason,
                'eligibility_snapshot' => $eligibility,
                'pending_products_resubmitted' => $resubmittedProducts,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            ]);

            return [
                'changed' => true,
                'enabled' => $enabled,
                'resubmitted_products' => $resubmittedProducts,
            ];
        }, 5);

        if ($result['ineligible_request'] ?? false) {
            foreach ($result['remoderation_product_ids'] as $productId) {
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

                $moderation->submit($product, null, $result['remoderation_reason']);
                StoreAutoApprovalAudit::query()
                    ->whereKey($result['remoderation_audit_id'])
                    ->increment('pending_products_resubmitted');
            }

            throw ValidationException::withMessages([
                'enabled' => 'Automatic approval was revoked because the store is no longer eligible. Review its seller and security status before enabling it again.',
            ]);
        }

        return response()->json([
            ...$result,
            'status' => 'success',
            'message' => $result['changed']
                ? 'Store automatic product approval updated.'
                : 'Store automatic product approval was already in the requested state.',
        ]);
    }

    /**
     * @return array<string, bool|string|null>
     */
    private function eligibilitySnapshot(Store $store): array
    {
        $seller = $store->seller;

        $snapshot = [
            'store_status' => $store->status,
            'store_not_suspended' => $store->suspended_at === null,
            'seller_user_type' => $seller?->user_type,
            'seller_is_vendor' => $seller?->user_type === 'vendor',
            'seller_email_verified' => $seller?->email_verified_at !== null,
            'kyc_status' => $seller?->kyc?->status,
        ];

        $snapshot['eligible'] = $snapshot['store_status'] === 'active'
            && $snapshot['store_not_suspended']
            && $snapshot['seller_is_vendor']
            && $snapshot['seller_email_verified']
            && $snapshot['kyc_status'] === 'approved';

        return $snapshot;
    }
}
