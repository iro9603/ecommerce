<?php

use App\Policies\ProductPolicy;
use Tests\Support\ProductSecurityFixtures;

test('an eligible vendor can manage only products owned by its store', function () {
    $owner = ProductSecurityFixtures::vendor();
    $otherVendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($owner['store']);
    $policy = new ProductPolicy;

    expect($policy->viewAny($owner['user']))->toBeTrue()
        ->and($policy->create($owner['user']))->toBeTrue();

    foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
        expect($policy->{$ability}($owner['user'], $product))->toBeTrue()
            ->and($policy->{$ability}($otherVendor['user'], $product))->toBeFalse();
    }

    foreach (['uploadImages', 'reorderImages', 'manageAttributes', 'manageVariants'] as $ability) {
        expect($policy->{$ability}($owner['user'], $product))->toBeTrue()
            ->and($policy->{$ability}($otherVendor['user'], $product))->toBeFalse();
    }

    expect($policy->manageDigitalFiles($owner['user'], $product))->toBeFalse();

    $product->product_type = 'digital';

    expect($policy->manageDigitalFiles($owner['user'], $product))->toBeTrue()
        ->and($policy->manageDigitalFiles($otherVendor['user'], $product))->toBeFalse();
});

test('an approved vendor may manage products while its store is onboarding', function (string $status) {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: [
        'status' => $status,
        'approved_at' => null,
    ]);
    $product = ProductSecurityFixtures::product($vendor['store']);
    $policy = new ProductPolicy;

    expect($policy->viewAny($vendor['user']))->toBeTrue()
        ->and($policy->create($vendor['user']))->toBeTrue()
        ->and($policy->update($vendor['user'], $product))->toBeTrue();
})->with(['draft', 'pending']);

test('a vendor without approved KYC cannot access product management', function () {
    $vendor = ProductSecurityFixtures::vendor(kycOverrides: ['status' => 'pending']);
    $product = ProductSecurityFixtures::product($vendor['store']);
    $policy = new ProductPolicy;

    expect($policy->viewAny($vendor['user']))->toBeFalse()
        ->and($policy->create($vendor['user']))->toBeFalse()
        ->and($policy->update($vendor['user'], $product))->toBeFalse();
});

test('a vendor with an unverified email cannot access product management', function () {
    $vendor = ProductSecurityFixtures::vendor(userOverrides: ['email_verified_at' => null]);
    $product = ProductSecurityFixtures::product($vendor['store']);
    $policy = new ProductPolicy;

    expect($policy->viewAny($vendor['user']))->toBeFalse()
        ->and($policy->create($vendor['user']))->toBeFalse()
        ->and($policy->update($vendor['user'], $product))->toBeFalse();
});

test('a rejected or suspended store cannot manage products', function (array $storeOverrides) {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: $storeOverrides);
    $product = ProductSecurityFixtures::product($vendor['store']);
    $policy = new ProductPolicy;

    expect($policy->viewAny($vendor['user']))->toBeFalse()
        ->and($policy->create($vendor['user']))->toBeFalse()
        ->and($policy->update($vendor['user'], $product))->toBeFalse();
})->with([
    'rejected store' => [['status' => 'rejected']],
    'suspended status' => [['status' => 'suspended']],
    'suspension timestamp' => [['status' => 'active', 'suspended_at' => now()]],
]);

test('a regular customer cannot use vendor product abilities', function () {
    $customer = ProductSecurityFixtures::vendor(userOverrides: ['user_type' => 'user']);
    $product = ProductSecurityFixtures::product($customer['store']);
    $policy = new ProductPolicy;

    expect($policy->viewAny($customer['user']))->toBeFalse()
        ->and($policy->update($customer['user'], $product))->toBeFalse();
});
