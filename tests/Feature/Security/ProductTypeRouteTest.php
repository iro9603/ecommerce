<?php

use Spatie\Permission\Models\Permission;
use Tests\Support\ProductSecurityFixtures;

test('vendor product creation rejects an invalid product type with 404', function () {
    $vendor = ProductSecurityFixtures::vendor();

    $this
        ->actingAs($vendor['user'], 'web')
        ->get(route('vendor.products.create', ['type' => 'foo']))
        ->assertNotFound();
});

test('admin product creation rejects an invalid product type with 404', function () {
    $admin = ProductSecurityFixtures::admin();
    $admin->givePermissionTo(Permission::findOrCreate('Product Management', 'admin'));

    $this
        ->actingAs($admin, 'admin')
        ->get(route('admin.products.create', ['type' => 'foo']))
        ->assertNotFound();
});
