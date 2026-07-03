<?php

use App\Models\Store;
use App\Models\User;

test('a vendor can open the store profile before creating a store', function () {
    $vendor = User::factory()->create([
        'user_type' => 'vendor',
    ]);

    $this
        ->actingAs($vendor)
        ->get(route('vendor.store-profile.index'))
        ->assertOk()
        ->assertViewHas('store', null)
        ->assertSee('id="logo-preview"', false)
        ->assertSee('id="logo-upload"', false)
        ->assertSee('id="logo-status"', false)
        ->assertSee('id="banner-preview"', false)
        ->assertSee('id="banner-upload"', false)
        ->assertSee('id="banner-status"', false)
        ->assertSee('Not uploaded');
});

test('a vendor can see saved store media status and filenames', function () {
    $vendor = User::factory()->create([
        'user_type' => 'vendor',
    ]);

    Store::forceCreate([
        'seller_id' => $vendor->id,
        'name' => 'Visual Store',
        'slug' => 'visual-store',
        'logo' => 'uploads/stores/existing-logo.png',
        'banner' => 'uploads/stores/existing-banner.png',
        'currency' => 'MXN',
        'country' => 'MX',
        'timezone' => 'America/Mexico_City',
    ]);

    $this
        ->actingAs($vendor)
        ->get(route('vendor.store-profile.index'))
        ->assertOk()
        ->assertSee('Uploaded')
        ->assertSee('existing-logo.png')
        ->assertSee('existing-banner.png')
        ->assertSee('background-image', false);
});

test('a vendor can create their store profile', function () {
    $vendor = User::factory()->create([
        'user_type' => 'vendor',
    ]);

    $response = $this
        ->actingAs($vendor)
        ->put(route('vendor.store-profile.update'), [
            'name' => 'Mercado Central',
            'phone' => '3312345678',
            'email' => 'ventas@example.com',
            'short_description' => 'Productos locales.',
            'currency' => 'MXN',
            'country' => 'MX',
            'timezone' => 'America/Mexico_City',
            'social_links' => [
                'instagram' => 'https://instagram.com/mercadocentral',
            ],
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('vendor.store-profile.index'));

    $store = Store::query()->where('seller_id', $vendor->id)->firstOrFail();

    expect($store->name)->toBe('Mercado Central')
        ->and($store->slug)->toBe('mercado-central')
        ->and($store->currency)->toBe('MXN')
        ->and($store->social_links['instagram'])->toBe('https://instagram.com/mercadocentral');
});

test('updating a store profile only changes the authenticated vendor store', function () {
    $vendor = User::factory()->create([
        'user_type' => 'vendor',
    ]);
    $otherVendor = User::factory()->create([
        'user_type' => 'vendor',
    ]);

    $store = Store::forceCreate([
        'seller_id' => $vendor->id,
        'name' => 'Original Store',
        'slug' => 'original-store',
        'logo' => 'uploads/existing-logo.png',
        'currency' => 'MXN',
        'country' => 'MX',
        'timezone' => 'America/Mexico_City',
    ]);
    $otherStore = Store::forceCreate([
        'seller_id' => $otherVendor->id,
        'name' => 'Other Store',
        'slug' => 'other-store',
        'currency' => 'MXN',
        'country' => 'MX',
        'timezone' => 'America/Mexico_City',
    ]);

    $this
        ->actingAs($vendor)
        ->put(route('vendor.store-profile.update'), [
            'name' => 'Updated Store',
            'currency' => 'MXN',
            'country' => 'MX',
            'timezone' => 'America/Mexico_City',
        ])
        ->assertSessionHasNoErrors();

    expect($store->refresh()->name)->toBe('Updated Store')
        ->and($store->slug)->toBe('original-store')
        ->and($store->logo)->toBe('uploads/existing-logo.png')
        ->and($otherStore->refresh()->name)->toBe('Other Store');
});
