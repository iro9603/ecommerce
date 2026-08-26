<?php

namespace App\Services;

use App\Models\Kyc;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class SellerEligibilityService
{
    public function today(?CarbonInterface $at = null): CarbonImmutable
    {
        $timezone = (string) config('app.timezone', 'UTC');

        return $at === null
            ? CarbonImmutable::now($timezone)->startOfDay()
            : CarbonImmutable::instance($at)->setTimezone($timezone)->startOfDay();
    }

    public function isKycEligible(?Kyc $kyc, ?CarbonInterface $at = null): bool
    {
        if (
            $kyc === null
            || $kyc->status !== 'approved'
            || $kyc->document_expiry_date === null
        ) {
            return false;
        }

        return $kyc->document_expiry_date->format('Y-m-d')
            >= $this->today($at)->format('Y-m-d');
    }

    public function applyEligibleKycQuery(
        Builder $query,
        ?CarbonInterface $at = null,
    ): Builder {
        return $query
            ->where('status', 'approved')
            ->whereNotNull('document_expiry_date')
            ->whereDate('document_expiry_date', '>=', $this->today($at)->toDateString());
    }

    public function applyEligibleSellerQuery(
        Builder $query,
        ?CarbonInterface $at = null,
    ): Builder {
        return $query
            ->where('user_type', 'vendor')
            ->whereNotNull('email_verified_at')
            ->whereHas('kyc', fn (Builder $kycQuery): Builder => $this->applyEligibleKycQuery($kycQuery, $at));
    }

    public function applyPublishableStoreQuery(
        Builder $query,
        ?CarbonInterface $at = null,
    ): Builder {
        return $query
            ->where('status', Store::STATUS_APPROVED)
            ->where('is_active', true)
            ->whereNull('suspended_at')
            ->where($query->qualifyColumn('moderation_version'), '>', 0)
            ->whereColumn(
                $query->qualifyColumn('reviewed_version'),
                $query->qualifyColumn('moderation_version'),
            )
            ->whereHas('seller', fn (Builder $sellerQuery): Builder => $this->applyEligibleSellerQuery($sellerQuery, $at));
    }

    /** @return array<string, mixed> */
    public function sellerSnapshot(?User $seller, ?CarbonInterface $at = null): array
    {
        $seller?->loadMissing('kyc');
        $kyc = $seller?->kyc;
        $kycEligible = $this->isKycEligible($kyc, $at);
        $eligible = $seller !== null
            && $seller->user_type === 'vendor'
            && $seller->email_verified_at !== null
            && $kycEligible;

        return [
            'seller_id' => $seller?->getKey(),
            'seller_user_type' => $seller?->user_type,
            'seller_email_verified' => $seller?->email_verified_at !== null,
            'seller_eligibility_epoch' => $seller === null
                ? null
                : (int) $seller->eligibility_epoch,
            'kyc_id' => $kyc?->getKey(),
            'kyc_status' => $kyc?->status,
            'kyc_expires_on' => $kyc?->document_expiry_date?->format('Y-m-d'),
            'kyc_eligibility_epoch' => $kyc === null
                ? null
                : (int) $kyc->eligibility_epoch,
            'kyc_eligible' => $kycEligible,
            'eligible' => $eligible,
        ];
    }

    /** @return array<string, mixed> */
    public function storeSnapshot(Store $store, ?CarbonInterface $at = null): array
    {
        $store->loadMissing('seller.kyc');
        $seller = $this->sellerSnapshot($store->seller, $at);
        $current = (int) $store->moderation_version > 0
            && (int) $store->reviewed_version === (int) $store->moderation_version;
        $storeEligible = $store->status === Store::STATUS_APPROVED
            && (bool) $store->is_active
            && $store->suspended_at === null
            && $current
            && $seller['eligible'];
        $grantCurrent = (bool) $store->auto_approve_products
            && $store->auto_approval_user_epoch !== null
            && $store->auto_approval_kyc_id !== null
            && $store->auto_approval_kyc_epoch !== null
            && $store->auto_approval_store_epoch !== null
            && $seller['kyc_id'] !== null
            && (int) $store->auto_approval_user_epoch === (int) $seller['seller_eligibility_epoch']
            && (int) $store->auto_approval_kyc_id === (int) $seller['kyc_id']
            && (int) $store->auto_approval_kyc_epoch === (int) $seller['kyc_eligibility_epoch']
            && (int) $store->auto_approval_store_epoch === (int) $store->eligibility_epoch;

        return [
            ...$seller,
            'store_id' => $store->getKey(),
            'store_status' => $store->status,
            'store_is_active' => (bool) $store->is_active,
            'store_not_suspended' => $store->suspended_at === null,
            'store_moderation_version' => (int) $store->moderation_version,
            'store_reviewed_version' => $store->reviewed_version === null
                ? null
                : (int) $store->reviewed_version,
            'store_review_is_current' => $current,
            'store_eligibility_epoch' => (int) $store->eligibility_epoch,
            'auto_approve_products' => (bool) $store->auto_approve_products,
            'auto_approval_grant_current' => $grantCurrent,
            'seller_eligible' => (bool) $seller['eligible'],
            'store_eligible' => $storeEligible,
            'eligible' => $storeEligible,
            'auto_approval_eligible' => $storeEligible && $grantCurrent,
        ];
    }

    public function canManageProducts(User $seller): bool
    {
        $seller->loadMissing(['kyc', 'store']);

        return $this->sellerSnapshot($seller)['eligible']
            && in_array($seller->store?->status, [
                Store::STATUS_DRAFT,
                Store::STATUS_PENDING,
                Store::STATUS_APPROVED,
            ], true)
            && $seller->store?->suspended_at === null;
    }
}
