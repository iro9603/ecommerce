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
            'name' => 'Security Store '.$identifier,
            'slug' => 'security-store-'.$identifier,
            'status' => 'active',
            'approved_at' => now(),
            'suspended_at' => null,
            'auto_approve_products' => false,
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
            'document_front_path' => 'kyc/front.jpg',
        ], $kycOverrides));

        return compact('user', 'store', 'kyc');
    }

    public static function product(Store $store, array $overrides = []): Product
    {
        $identifier = Str::uuid()->toString();

        return Product::forceCreate(array_merge([
            'store_id' => $store->getKey(),
            'product_type' => 'physical',
            'name' => 'Security Product '.$identifier,
            'slug' => 'security-product-'.$identifier,
            'description' => '<p>Safe product description.</p>',
            'short_description' => '<p>Safe summary.</p>',
            'price' => 100,
            'manage_stock' => 'no',
            'in_stock' => true,
            'status' => 'active',
            'approved_status' => Product::APPROVAL_PENDING,
        ], $overrides));
    }

    public static function admin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Security Reviewer',
            'email' => 'reviewer-'.Str::uuid().'@example.com',
            'email_verified_at' => now(),
            'password' => 'password',
        ]);
    }
}
