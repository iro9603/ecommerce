<?php

namespace Tests\Support;

use App\Models\Admin;
use App\Models\Kyc;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;

final class ProductSecurityFixtures
{
    /**
     * @return array{user: User, store: Store, kyc: Kyc}
     */
    public static function vendor(
        array $userOverrides = [],
        array $storeOverrides = [],
        array $kycOverrides = []
    ): array {
        $identifier = Str::uuid()->toString();
        $user = User::factory()->create(array_merge([
            'user_type' => 'vendor',
            'email_verified_at' => now(),
        ], $userOverrides));

        $store = Store::forceCreate(array_merge([
            'seller_id' => $user->getKey(),
            'name' => 'Security Store ' . $identifier,
            'slug' => 'security-store-' . $identifier,
            'status' => 'approved',
            'is_active' => ($storeOverrides['status'] ?? 'approved') === 'approved',
            'approved_at' => now(),
            'suspended_at' => null,
            'auto_approve_products' => false,
            'moderation_version' => 1,
            'reviewed_version' => 1,
            'moderation_fingerprint' => 'fixture-store-hash',
            'submitted_at' => now(),
        ], $storeOverrides));

        $kyc = Kyc::forceCreate(array_merge([
            'user_id' => $user->getKey(),
            'status' => 'approved',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'verified_at' => now(),
            'full_name' => 'Security Vendor',
            'date_of_birth' => '1990-01-01',
            'nationality' => 'MX',
            'address_line_1' => '1 Test Street',
            'city' => 'Mexico City',
            'country' => 'MX',
            'document_type' => 'id_card',
            'document_number' => $identifier,
            'document_country' => 'MX',
            'document_expiry_date' => now()->addYear()->toDateString(),
            'document_front_path' => 'kyc/front.jpg',
        ], $kycOverrides));

        if ($store->auto_approve_products) {
            $store->forceFill([
                'auto_approval_user_epoch' => (int) $user->eligibility_epoch,
                'auto_approval_kyc_id' => $kyc->getKey(),
                'auto_approval_kyc_epoch' => (int) $kyc->eligibility_epoch,
                'auto_approval_store_epoch' => (int) $store->eligibility_epoch,
            ])->saveQuietly();
        }

        return compact('user', 'store', 'kyc');
    }

    public static function product(Store $store, array $overrides = []): Product
    {
        $identifier = Str::uuid()->toString();

        $attributes = array_merge([
            'store_id' => $store->getKey(),
            'product_type' => 'physical',
            'name' => 'Security Product ' . $identifier,
            'slug' => 'security-product-' . $identifier,
            'description' => '<p>Safe product description.</p>',
            'short_description' => '<p>Safe summary.</p>',
            'price' => 100,
            'manage_stock' => 'no',
            'in_stock' => true,
            'status' => 'active',
            'approved_status' => Product::APPROVAL_PENDING,
        ], $overrides);

        if (($attributes['approved_status'] ?? null) === Product::APPROVAL_APPROVED) {
            $store->loadMissing('seller.kyc');
            $attributes += [
                'reviewed_user_eligibility_epoch' => (int) $store->seller->eligibility_epoch,
                'reviewed_kyc_id' => $store->seller->kyc->getKey(),
                'reviewed_kyc_eligibility_epoch' => (int) $store->seller->kyc->eligibility_epoch,
                'reviewed_store_eligibility_epoch' => (int) $store->eligibility_epoch,
                'reviewed_context_hash' => hash('sha256', 'security-fixture-context'),
                'reviewed_policy_version' => (string) config('product_moderation.policy_version'),
            ];
        }

        return Product::forceCreate($attributes);
    }

    public static function admin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Security Reviewer',
            'email' => 'reviewer-' . Str::uuid() . '@example.com',
            'email_verified_at' => now(),
            'password' => 'password',
        ]);
    }
}
