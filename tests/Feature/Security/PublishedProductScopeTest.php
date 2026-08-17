<?php

use App\Models\Product;
use Tests\Support\ProductSecurityFixtures;

test('published products must be approved and active in an active non-suspended store', function () {
    $activeVendor = ProductSecurityFixtures::vendor();
    $published = ProductSecurityFixtures::product($activeVendor['store'], [
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);

    ProductSecurityFixtures::product($activeVendor['store'], [
        'approved_status' => Product::APPROVAL_PENDING,
        'status' => 'active',
    ]);

    ProductSecurityFixtures::product($activeVendor['store'], [
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'inactive',
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);

    ProductSecurityFixtures::product($activeVendor['store'], [
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 2,
        'reviewed_version' => 1,
    ]);

    $inactiveVendor = ProductSecurityFixtures::vendor(storeOverrides: ['status' => 'pending']);
    ProductSecurityFixtures::product($inactiveVendor['store'], [
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);

    $suspendedVendor = ProductSecurityFixtures::vendor(storeOverrides: ['suspended_at' => now()]);
    ProductSecurityFixtures::product($suspendedVendor['store'], [
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);

    $unverifiedVendor = ProductSecurityFixtures::vendor(
        userOverrides: ['email_verified_at' => null]
    );
    ProductSecurityFixtures::product($unverifiedVendor['store'], [
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);

    $rejectedVendor = ProductSecurityFixtures::vendor(
        kycOverrides: ['status' => 'rejected']
    );
    ProductSecurityFixtures::product($rejectedVendor['store'], [
        'approved_status' => Product::APPROVAL_APPROVED,
        'status' => 'active',
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);

    expect(Product::query()->published()->pluck('id')->all())
        ->toBe([$published->getKey()]);
});
