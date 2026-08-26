<?php

use App\Models\StoreApprovalReview;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\ProductSecurityFixtures;

test('store rich text is sanitized before persistence review and rendering', function () {
    $vendor = ProductSecurityFixtures::vendor();
    $payload = '</textarea><img src=x onerror=alert(1)>'
        .'<script>alert(2)</script>'
        .'<a href=javascript:alert(3)>Unsafe link</a>'
        .'<strong>Allowed text</strong>';

    $this->actingAs($vendor['user'], 'web')
        ->put(route('vendor.store-profile.update'), [
            'name' => $vendor['store']->name,
            'short_description' => $payload,
            'long_description' => '<p>Safe paragraph</p>'.$payload,
            'currency' => 'MXN',
            'country' => 'MX',
            'timezone' => 'America/Mexico_City',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('vendor.store-profile.index'));

    $store = $vendor['store']->fresh();
    $review = StoreApprovalReview::query()
        ->where('store_id', $store->getKey())
        ->where('version', $store->moderation_version)
        ->sole();

    foreach ([$store->short_description, $store->long_description] as $html) {
        expect(strtolower((string) $html))
            ->not->toContain('<script')
            ->not->toContain('onerror=')
            ->not->toContain('javascript:')
            ->not->toContain('</textarea>');
    }

    expect($store->short_description)->toContain('<strong>Allowed text</strong>')
        ->and($review->snapshot['short_description'])->toBe($store->short_description)
        ->and($review->snapshot['long_description'])->toBe($store->long_description)
        ->and($review->content_hash)->toBe($store->moderation_fingerprint);

    $admin = ProductSecurityFixtures::admin();
    $admin->givePermissionTo(Permission::findOrCreate('Store Management', 'admin'));

    foreach ([
        $this->actingAs($admin, 'admin')->get(route('admin.stores.show', $store)),
        $this->actingAs($vendor['user'], 'web')->get(route('vendor.store-profile.index')),
    ] as $response) {
        $response->assertOk()
            ->assertDontSee('alert(1)', false)
            ->assertDontSee('alert(2)', false)
            ->assertDontSee('javascript:alert(3)', false)
            ->assertDontSee('</textarea><img', false);
    }
});

test('historical unsafe store content is sanitized at both read boundaries', function () {
    $vendor = ProductSecurityFixtures::vendor();
    $legacy = '</textarea><img src=x onerror=alert(9)>'
        .'<script>alert(8)</script><a href=javascript:alert(7)>Legacy</a>';
    DB::table('stores')->where('id', $vendor['store']->getKey())->update([
        'short_description' => $legacy,
        'long_description' => $legacy,
    ]);

    $admin = ProductSecurityFixtures::admin();
    $admin->givePermissionTo(Permission::findOrCreate('Store Management', 'admin'));

    foreach ([
        $this->actingAs($admin, 'admin')->get(route('admin.stores.show', $vendor['store'])),
        $this->actingAs($vendor['user'], 'web')->get(route('vendor.store-profile.index')),
    ] as $response) {
        $response->assertOk()
            ->assertDontSee('&lt;script', false)
            ->assertDontSee('alert(9)', false)
            ->assertDontSee('alert(8)', false)
            ->assertDontSee('javascript:alert(7)', false)
            ->assertDontSee('</textarea><img', false);
    }
});

test('public product detail sanitizes historical rich text before raw rendering', function () {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['is_active' => true]);
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'approved_status' => 'approved',
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);
    $legacy = '<img src=x onerror=product_xss_marker>'
        .'<script>product_script_marker</script>'
        .'<a href=javascript:product_url_marker>Unsafe</a>'
        .'<strong>Allowed product text</strong>';
    DB::table('products')->where('id', $product->getKey())->update([
        'short_description' => $legacy,
        'description' => $legacy,
    ]);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('<strong>Allowed product text</strong>', false)
        ->assertDontSee('product_xss_marker', false)
        ->assertDontSee('product_script_marker', false)
        ->assertDontSee('javascript:product_url_marker', false);
});
