<?php

use Spatie\Permission\Models\Permission;
use Tests\Support\ProductSecurityFixtures;

test('stored XSS is sanitized before the admin product edit form is rendered', function () {
    $admin = ProductSecurityFixtures::admin();
    $admin->givePermissionTo(
        Permission::findOrCreate('Product Management', 'admin')
    );

    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'short_description' => '</textarea><img src=x onerror=STORED_XSS_ONERROR_MARKER>'
            .'<script>STORED_XSS_SCRIPT_MARKER</script><p>SAFE_HISTORICAL_SUMMARY</p>',
        'description' => '</textarea><img src=x onerror=STORED_XSS_ONERROR_MARKER>'
            .'<script>STORED_XSS_SCRIPT_MARKER</script><p>SAFE_HISTORICAL_CONTENT</p>',
    ]);

    $response = $this
        ->actingAs($admin, 'admin')
        ->get(route('admin.products.edit', $product));

    $response->assertOk()
        ->assertSee('SAFE_HISTORICAL_SUMMARY', false)
        ->assertSee('SAFE_HISTORICAL_CONTENT', false)
        ->assertDontSee('STORED_XSS_ONERROR_MARKER', false)
        ->assertDontSee('STORED_XSS_SCRIPT_MARKER', false)
        ->assertDontSee('</textarea><img', false);

    expect(strtolower($response->getContent()))->not->toContain('onerror=');
});
