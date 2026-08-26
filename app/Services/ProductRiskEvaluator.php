<?php

namespace App\Services;

use App\Models\Product;

class ProductRiskEvaluator
{
    private ?SellerEligibilityService $eligibility = null;

    public function __construct(?SellerEligibilityService $eligibility = null)
    {
        $this->eligibility = $eligibility ?? app(SellerEligibilityService::class);
    }

    /**
     * @return array{
     *     score: int,
     *     level: string,
     *     reasons: array<int, array{code: string, score: int, message: string, blocks_automatic_approval: bool}>,
     *     auto_approvable: bool
     * }
     */
    public function evaluate(Product $product): array
    {
        // Security relationships must be refreshed for every assessment. A
        // previously loaded seller must not preserve stale eligibility data.
        $product->load(['store.seller.kyc', 'variants', 'files']);
        $product->loadCount(['categories', 'files', 'images']);

        $reasons = [];

        if (! $product->store) {
            $this->addReason($reasons, 'missing_store', 100, 'The product does not belong to a store.');
        } else {
            $storeEligibility = $this->eligibility()->storeSnapshot($product->store);

            if ($storeEligibility['store_status'] !== 'approved') {
                $this->addReason($reasons, 'store_not_approved', 100, 'The store is not approved.');
            }

            if (! $storeEligibility['store_not_suspended']) {
                $this->addReason($reasons, 'store_suspended', 100, 'The store is suspended.');
            }

            if (! $storeEligibility['store_is_active']) {
                $this->addReason(
                    $reasons,
                    'store_selling_disabled',
                    100,
                    'The store is not enabled for selling.'
                );
            }

            if (! $storeEligibility['store_review_is_current']) {
                $this->addReason(
                    $reasons,
                    'store_review_not_current',
                    100,
                    'The current store content has not been approved.'
                );
            }

            if (! $storeEligibility['auto_approval_grant_current']) {
                $this->addReason(
                    $reasons,
                    'store_requires_manual_review',
                    0,
                    'The store has not been explicitly trusted for automatic product approval.'
                );
            }

            $seller = $product->store->seller;

            if (! $seller) {
                $this->addReason($reasons, 'missing_seller', 100, 'The store does not have a seller.');
            } else {
                if ($storeEligibility['seller_user_type'] !== 'vendor') {
                    $this->addReason(
                        $reasons,
                        'seller_not_vendor',
                        100,
                        'The store seller does not have the vendor account type.'
                    );
                }

                if (! $storeEligibility['seller_email_verified']) {
                    $this->addReason($reasons, 'seller_email_not_verified', 60, 'The seller email is not verified.');
                }

                if ($storeEligibility['kyc_status'] !== 'approved') {
                    $this->addReason($reasons, 'seller_kyc_not_approved', 100, 'The seller KYC is not approved.');
                } elseif ($storeEligibility['kyc_expires_on'] === null) {
                    $this->addReason($reasons, 'seller_kyc_expiry_missing', 100, 'The seller KYC has no expiration date.');
                } elseif (! $storeEligibility['kyc_eligible']) {
                    $this->addReason($reasons, 'seller_kyc_expired', 100, 'The seller KYC document has expired.');
                }
            }
        }

        if (! in_array($product->product_type, ['physical', 'digital'], true)) {
            $this->addReason(
                $reasons,
                'unknown_product_type',
                100,
                'The product type is not supported.'
            );
        } elseif ($product->product_type === 'digital') {
            $this->addReason(
                $reasons,
                'digital_product_requires_manual_review',
                30,
                'Digital products require manual review.'
            );

            if ($product->files_count === 0) {
                $this->addReason($reasons, 'digital_product_without_file', 40, 'The digital product does not have a file.');
            } elseif ($product->files->contains(fn ($file): bool => $file->sha256 === null)) {
                $this->addReason($reasons, 'digital_file_hash_missing', 40, 'The digital product has a file without a SHA-256 content hash.');
            }
        }

        $hasBasePrice = $product->price !== null && (float) $product->price >= 0;
        $hasSellableVariant = $product->variants->contains(
            fn($variant): bool => (bool) $variant->is_active
                && $variant->price !== null
                && (float) $variant->price >= 0
        );

        if (! $hasBasePrice && ! $hasSellableVariant) {
            $this->addReason(
                $reasons,
                'missing_sellable_price',
                80,
                'The product needs a valid base price or an active priced variant.'
            );
        }

        if ($product->categories_count === 0) {
            $this->addReason($reasons, 'missing_category', 15, 'The product does not have a category.');
        }

        if ($product->images_count === 0) {
            $this->addReason($reasons, 'missing_image', 15, 'The product does not have an image.');
        }

        if ($product->price !== null && (float) $product->price < 0) {
            $this->addReason($reasons, 'negative_price', 100, 'The product price is negative.');
        }

        if ($product->special_price !== null && (float) $product->special_price < 0) {
            $this->addReason($reasons, 'negative_special_price', 100, 'The product special price is negative.');
        }

        if ($product->manage_stock === 'yes' && $product->qty !== null && (int) $product->qty < 0) {
            $this->addReason($reasons, 'negative_stock', 100, 'The product stock is negative.');
        }

        if (
            $product->price !== null
            && $product->special_price !== null
            && (float) $product->special_price > (float) $product->price
        ) {
            $this->addReason(
                $reasons,
                'special_price_above_price',
                40,
                'The special price is greater than the regular price.'
            );
        }

        $invalidVariant = $product->variants->contains(function ($variant): bool {
            return ($variant->price !== null && (float) $variant->price < 0)
                || ($variant->special_price !== null && (float) $variant->special_price < 0)
                || ($variant->qty !== null && (int) $variant->qty < 0)
                || (
                    $variant->price !== null
                    && $variant->special_price !== null
                    && (float) $variant->special_price > (float) $variant->price
                );
        });

        if ($invalidVariant) {
            $this->addReason(
                $reasons,
                'invalid_variant_price_or_stock',
                100,
                'At least one product variant has an invalid price or stock value.'
            );
        }

        if ($this->containsDangerousMarkup($product->short_description, $product->description)) {
            $this->addReason(
                $reasons,
                'dangerous_markup',
                100,
                'The product content contains executable or unsafe markup.'
            );
        }

        $score = min(100, array_sum(array_column($reasons, 'score')));
        $blocksAutomaticApproval = collect($reasons)->contains(
            fn(array $reason) => $reason['blocks_automatic_approval']
        );
        $maximumScore = max(0, min(100, (int) config('product_moderation.maximum_automatic_risk_score', 20)));

        return [
            'score' => $score,
            'level' => $this->riskLevel($score),
            'reasons' => $reasons,
            'auto_approvable' => (bool) config('product_moderation.automatic_approval_enabled', true)
                && ! $blocksAutomaticApproval
                && $score <= $maximumScore,
        ];
    }

    /**
     * @param  array<int, array{code: string, score: int, message: string, blocks_automatic_approval: bool}>  $reasons
     */
    private function addReason(
        array &$reasons,
        string $code,
        int $score,
        string $message,
        bool $blocksAutomaticApproval = true
    ): void {
        $reasons[] = [
            'code' => $code,
            'score' => $score,
            'message' => $message,
            'blocks_automatic_approval' => $blocksAutomaticApproval,
        ];
    }

    private function containsDangerousMarkup(?string ...$values): bool
    {
        foreach ($values as $value) {
            if (! $value) {
                continue;
            }

            if (preg_match(
                '/<\s*(?:script|iframe|object|embed|form|meta|link)\b|on[a-z]+\s*=|(?:java|vb)script\s*:/iu',
                $value
            ) === 1) {
                return true;
            }
        }

        return false;
    }

    private function riskLevel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'critical',
            $score >= 50 => 'high',
            $score >= 21 => 'medium',
            default => 'low',
        };
    }

    private function eligibility(): SellerEligibilityService
    {
        return $this->eligibility ??= app(SellerEligibilityService::class);
    }
}
