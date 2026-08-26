<?php

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreApprovalReview;
use App\Jobs\EvaluateProductApprovalContext;
use App\Services\ProductModerationService;
use App\Services\StoreModerationService;
use App\Services\StoreStateTransitionPolicy;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\Support\ProductSecurityFixtures;

function storeManagementPermission(): Permission
{
    return Permission::findOrCreate('Store Management', 'admin');
}

function storeModeratingAdmin(): mixed
{
    $admin = ProductSecurityFixtures::admin();
    $admin->givePermissionTo(storeManagementPermission());

    return $admin;
}

test('creating a store profile places the store in pending for admin review', function () {
    $vendor = ProductSecurityFixtures::vendor()['user']->forceFill(['user_type' => 'vendor']);

    $response = $this
        ->actingAs($vendor, 'web')
        ->put(route('vendor.store-profile.update'), [
            'name' => 'Fresh Marketplace',
            'currency' => 'MXN',
            'country' => 'MX',
            'timezone' => 'America/Mexico_City',
        ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('vendor.store-profile.index'));

    $store = Store::query()->where('seller_id', $vendor->getKey())->firstOrFail();

    expect($store->status)->toBe(Store::STATUS_PENDING)
        ->and($store->is_active)->toBeFalse()
        ->and($store->approved_at)->toBeNull();
});

test('editing an approved store returns it to pending without invalidating product content approval', function () {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['status' => 'approved', 'approved_at' => now()]);
    $product = ProductSecurityFixtures::product($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Initial submission.');
    $moderation->approve($product, null, 'Approved by admin.', $review->version);

    expect($product->fresh()->approved_status)->toBe(Product::APPROVAL_APPROVED);

    $this
        ->actingAs($vendor['user'], 'web')
        ->put(route('vendor.store-profile.update'), [
            'name' => 'Renamed Store',
            'currency' => 'MXN',
            'country' => 'MX',
            'timezone' => 'America/Mexico_City',
        ])
        ->assertSessionHasNoErrors();

    $store = $vendor['store']->fresh();

    expect($store->status)->toBe(Store::STATUS_PENDING)
        ->and($store->approved_at)->toBeNull()
        ->and($store->is_active)->toBeFalse();

    $product->refresh();

    expect($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->reviewed_version)->toBe(1)
        ->and($product->approved_at)->not->toBeNull()
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse();
});

test('an admin without the store management permission cannot view or moderate stores', function () {
    $admin = ProductSecurityFixtures::admin();
    $admin->givePermissionTo(Permission::findOrCreate('Product Management', 'admin'));
    $vendor = ProductSecurityFixtures::vendor();

    $this
        ->actingAs($admin, 'admin')
        ->get(route('admin.stores.index'))
        ->assertForbidden();

    $this
        ->actingAs($admin, 'admin')
        ->post(route('admin.stores.approve', $vendor['store']), [
            'moderation_version' => (int) $vendor['store']->moderation_version,
        ])
        ->assertForbidden();
});

test('an admin can approve an eligible store and re-evaluate its pending products', function () {
    Queue::fake();
    $admin = storeModeratingAdmin();
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['status' => 'pending']);
    $product = ProductSecurityFixtures::product($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $moderation->submit($product, $vendor['user'], 'Initial submission.');

    $this
        ->actingAs($admin, 'admin')
        ->post(route('admin.stores.approve', $vendor['store']), [
            'moderation_version' => (int) $vendor['store']->moderation_version,
        ])
        ->assertRedirect();

    $store = $vendor['store']->fresh();

    expect($store->status)->toBe(Store::STATUS_APPROVED)
        ->and($store->is_active)->toBeTrue()
        ->and($store->approved_at)->not->toBeNull()
        ->and($store->approved_by)->toBe($admin->getKey());

    $product->refresh();

    expect($product->moderation_version)->toBe(1)
        ->and($product->approved_status)->toBe(Product::APPROVAL_PENDING);

    Queue::assertPushed(
        EvaluateProductApprovalContext::class,
        fn($job): bool => $job->productId === $product->getKey()
            && $job->moderationVersion === 1
    );
});

test('an ineligible store cannot be approved', function () {
    $admin = storeModeratingAdmin();
    // A seller who is not a vendor account type cannot have an active store.
    $vendor = ProductSecurityFixtures::vendor(
        userOverrides: ['user_type' => 'user'],
        storeOverrides: ['status' => 'pending', 'approved_at' => null],
    );

    $this
        ->actingAs($admin, 'admin')
        ->post(route('admin.stores.approve', $vendor['store']), [
            'moderation_version' => (int) $vendor['store']->moderation_version,
        ])
        ->assertRedirect();

    expect($vendor['store']->fresh()->status)->toBe(Store::STATUS_PENDING)
        ->and($vendor['store']->fresh()->is_active)->toBeFalse();
});

test('rejecting a store requires a reason and fail-closes its approved products', function () {
    $admin = storeModeratingAdmin();
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['status' => 'approved', 'approved_at' => now()]);
    $product = ProductSecurityFixtures::product($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Initial submission.');
    $moderation->approve($product, null, 'Approved by admin.', $review->version);
    app(StoreModerationService::class)->submitForReview(
        $vendor['store'],
        $vendor['user'],
        'Store content changed and requires another review.',
    );
    $vendor['store']->refresh();

    $this
        ->actingAs($admin, 'admin')
        ->post(route('admin.stores.reject', $vendor['store']), [
            'moderation_version' => (int) $vendor['store']->moderation_version,
            'rejection_reason' => 'Policies and terms were not signed.',
        ])
        ->assertRedirect();

    $store = $vendor['store']->fresh();

    expect($store->status)->toBe(Store::STATUS_REJECTED)
        ->and($store->is_active)->toBeFalse()
        ->and($store->approved_at)->toBeNull();

    $product->refresh();

    expect($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->reviewed_version)->toBe(1)
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse();
});

test('suspending a store closes publication without invalidating product content approval', function () {
    $admin = storeModeratingAdmin();
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['status' => 'approved', 'approved_at' => now()]);
    $product = ProductSecurityFixtures::product($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Initial submission.');
    $moderation->approve($product, null, 'Approved by admin.', $review->version);

    $this
        ->actingAs($admin, 'admin')
        ->post(route('admin.stores.suspend', $vendor['store']), [
            'moderation_version' => (int) $vendor['store']->moderation_version,
            'reason' => 'Operated without required permits.',
        ])
        ->assertRedirect();

    $store = $vendor['store']->fresh();

    expect($store->status)->toBe(Store::STATUS_SUSPENDED)
        ->and($store->is_active)->toBeFalse()
        ->and($store->suspended_at)->not->toBeNull();

    $product->refresh();

    expect($product->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->reviewed_version)->toBe(1)
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse();
});

test('a suspended store can be reactivated by the admin', function () {
    $admin = storeModeratingAdmin();
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['status' => 'suspended', 'suspended_at' => now()]);

    $this
        ->actingAs($admin, 'admin')
        ->post(route('admin.stores.restore', $vendor['store']), [
            'moderation_version' => (int) $vendor['store']->moderation_version,
        ])
        ->assertRedirect();

    $store = $vendor['store']->fresh();

    expect($store->status)->toBe(Store::STATUS_APPROVED)
        ->and($store->is_active)->toBeTrue()
        ->and($store->suspended_at)->toBeNull()
        ->and($store->approved_at)->not->toBeNull();
});

test('a rejected or pending store cannot expose publishable products', function () {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['status' => 'rejected']);
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => 1,
        'reviewed_version' => 1,
    ]);

    $this
        ->actingAs($vendor['user'], 'web')
        ->get("/products/{$product->slug}")
        ->assertNotFound();
});

test('a store no-op save does not create a new moderation version', function () {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['status' => 'approved', 'approved_at' => now()]);
    $store = $vendor['store'];
    $originalVersion = (int) $store->moderation_version;

    $this
        ->actingAs($vendor['user'], 'web')
        ->put(route('vendor.store-profile.update'), [
            'name' => $store->name,
            'currency' => 'MXN',
            'country' => 'MX',
            'timezone' => 'America/Mexico_City',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('vendor.store-profile.index'));

    $store->refresh();

    expect($store->status)->toBe(Store::STATUS_APPROVED)
        ->and((int) $store->moderation_version)->toBe($originalVersion)
        ->and((int) $store->reviewed_version)->toBe($originalVersion);
});

test('a stale admin approval cannot decide a newer store version', function () {
    $admin = storeModeratingAdmin();
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['status' => 'pending', 'approved_at' => null]);
    $store = $vendor['store'];

    $this
        ->actingAs($vendor['user'], 'web')
        ->put(route('vendor.store-profile.update'), [
            'name' => 'Newer Store Version',
            'currency' => 'MXN',
            'country' => 'MX',
            'timezone' => 'America/Mexico_City',
        ])
        ->assertSessionHasNoErrors();

    $store->refresh();

    expect((int) $store->moderation_version)->toBe(2)
        ->and($store->status)->toBe(Store::STATUS_PENDING);

    $this
        ->actingAs($admin, 'admin')
        ->postJson(route('admin.stores.approve', $store), [
            'moderation_version' => 1,
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Store information changed while you were reviewing it. Please review the latest version before making a decision.');

    expect($store->fresh()->status)->toBe(Store::STATUS_PENDING)
        ->and((int) $store->fresh()->moderation_version)->toBe(2);
});

test('store submissions accept every domain-policy origin', function (string $origin) {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: [
        'status' => $origin,
        'is_active' => $origin === Store::STATUS_APPROVED,
        'approved_at' => $origin === Store::STATUS_APPROVED ? now() : null,
        'suspended_at' => $origin === Store::STATUS_SUSPENDED ? now() : null,
    ]);

    $review = app(StoreModerationService::class)->submitForReview(
        $vendor['store'],
        $vendor['user'],
        'Material store content was submitted.',
    );

    $store = $vendor['store']->fresh();

    expect($review)->toBeInstanceOf(StoreApprovalReview::class)
        ->and($review->status)->toBe(StoreApprovalReview::STATUS_PENDING)
        ->and($store->status)->toBe(Store::STATUS_PENDING)
        ->and((int) $store->moderation_version)->toBe(2);
})->with([
    'draft' => Store::STATUS_DRAFT,
    'pending' => Store::STATUS_PENDING,
    'rejected' => Store::STATUS_REJECTED,
    'approved' => Store::STATUS_APPROVED,
]);

test('store decisions accept every domain-policy origin', function (
    string $action,
    string $origin,
    string $target,
    string $reviewStatus,
) {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: [
        'status' => $origin,
        'is_active' => $origin === Store::STATUS_APPROVED,
        'approved_at' => $origin === Store::STATUS_APPROVED ? now() : null,
        'suspended_at' => $origin === Store::STATUS_SUSPENDED ? now() : null,
    ]);
    $admin = ProductSecurityFixtures::admin();
    $store = $vendor['store'];
    $version = (int) $store->moderation_version;
    $moderation = app(StoreModerationService::class);

    $review = match ($action) {
        StoreStateTransitionPolicy::ACTION_APPROVE => $moderation->approve($store, $admin, $version),
        StoreStateTransitionPolicy::ACTION_REJECT => $moderation->reject($store, $admin, 'Rejected by domain-policy test.', $version),
        StoreStateTransitionPolicy::ACTION_SUSPEND => $moderation->suspend($store, $admin, 'Suspended by domain-policy test.', $version),
        StoreStateTransitionPolicy::ACTION_RESTORE => $moderation->restore($store, $admin, $version),
    };

    $store->refresh();

    expect($review)->toBeInstanceOf(StoreApprovalReview::class)
        ->and($review->status)->toBe($reviewStatus)
        ->and($store->status)->toBe($target)
        ->and((int) $store->moderation_version)->toBe($version);
})->with([
    'approve pending' => [
        StoreStateTransitionPolicy::ACTION_APPROVE,
        Store::STATUS_PENDING,
        Store::STATUS_APPROVED,
        StoreApprovalReview::STATUS_APPROVED,
    ],
    'reject pending' => [
        StoreStateTransitionPolicy::ACTION_REJECT,
        Store::STATUS_PENDING,
        Store::STATUS_REJECTED,
        StoreApprovalReview::STATUS_REJECTED,
    ],
    'suspend approved' => [
        StoreStateTransitionPolicy::ACTION_SUSPEND,
        Store::STATUS_APPROVED,
        Store::STATUS_SUSPENDED,
        StoreApprovalReview::STATUS_SUSPENDED,
    ],
    'restore suspended' => [
        StoreStateTransitionPolicy::ACTION_RESTORE,
        Store::STATUS_SUSPENDED,
        Store::STATUS_APPROVED,
        StoreApprovalReview::STATUS_RESTORED,
    ],
]);

test('invalid store transition origins leave the database unchanged', function (
    string $action,
    string $origin,
) {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: [
        'status' => $origin,
        'is_active' => $origin === Store::STATUS_APPROVED,
        'approved_at' => $origin === Store::STATUS_APPROVED ? now() : null,
        'suspended_at' => $origin === Store::STATUS_SUSPENDED ? now() : null,
    ]);
    $admin = ProductSecurityFixtures::admin();
    $store = $vendor['store']->fresh();
    $before = $store->getRawOriginal();
    $reviewCount = StoreApprovalReview::query()->where('store_id', $store->getKey())->count();
    $version = (int) $store->moderation_version;
    $moderation = app(StoreModerationService::class);

    $transition = fn () => match ($action) {
        StoreStateTransitionPolicy::ACTION_SUBMIT => $moderation->submitForReview($store, $vendor['user'], 'Invalid submission origin.'),
        StoreStateTransitionPolicy::ACTION_APPROVE => $moderation->approve($store, $admin, $version),
        StoreStateTransitionPolicy::ACTION_REJECT => $moderation->reject($store, $admin, 'Invalid rejection origin.', $version),
        StoreStateTransitionPolicy::ACTION_SUSPEND => $moderation->suspend($store, $admin, 'Invalid suspension origin.', $version),
        StoreStateTransitionPolicy::ACTION_RESTORE => $moderation->restore($store, $admin, $version),
    };

    expect($transition)->toThrow(ConflictHttpException::class);

    expect($store->fresh()->getRawOriginal())->toBe($before)
        ->and(StoreApprovalReview::query()->where('store_id', $store->getKey())->count())->toBe($reviewCount);
})->with([
    'submit from suspended' => [StoreStateTransitionPolicy::ACTION_SUBMIT, Store::STATUS_SUSPENDED],
    'approve from approved' => [StoreStateTransitionPolicy::ACTION_APPROVE, Store::STATUS_APPROVED],
    'reject from rejected' => [StoreStateTransitionPolicy::ACTION_REJECT, Store::STATUS_REJECTED],
    'suspend from pending' => [StoreStateTransitionPolicy::ACTION_SUSPEND, Store::STATUS_PENDING],
    'restore from pending' => [StoreStateTransitionPolicy::ACTION_RESTORE, Store::STATUS_PENDING],
]);

test('stale store decisions leave the database unchanged', function (
    string $action,
    string $origin,
) {
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: [
        'status' => $origin,
        'is_active' => $origin === Store::STATUS_APPROVED,
        'approved_at' => $origin === Store::STATUS_APPROVED ? now() : null,
        'suspended_at' => $origin === Store::STATUS_SUSPENDED ? now() : null,
    ]);
    $admin = ProductSecurityFixtures::admin();
    $store = $vendor['store']->fresh();
    $before = $store->getRawOriginal();
    $reviewCount = StoreApprovalReview::query()->where('store_id', $store->getKey())->count();
    $staleVersion = (int) $store->moderation_version - 1;
    $moderation = app(StoreModerationService::class);

    $review = match ($action) {
        StoreStateTransitionPolicy::ACTION_APPROVE => $moderation->approve($store, $admin, $staleVersion),
        StoreStateTransitionPolicy::ACTION_REJECT => $moderation->reject($store, $admin, 'Stale rejection.', $staleVersion),
        StoreStateTransitionPolicy::ACTION_SUSPEND => $moderation->suspend($store, $admin, 'Stale suspension.', $staleVersion),
        StoreStateTransitionPolicy::ACTION_RESTORE => $moderation->restore($store, $admin, $staleVersion),
    };

    expect($review)->toBeNull()
        ->and($store->fresh()->getRawOriginal())->toBe($before)
        ->and(StoreApprovalReview::query()->where('store_id', $store->getKey())->count())->toBe($reviewCount);
})->with([
    'stale approve' => [StoreStateTransitionPolicy::ACTION_APPROVE, Store::STATUS_PENDING],
    'stale reject' => [StoreStateTransitionPolicy::ACTION_REJECT, Store::STATUS_PENDING],
    'stale suspend' => [StoreStateTransitionPolicy::ACTION_SUSPEND, Store::STATUS_APPROVED],
    'stale restore' => [StoreStateTransitionPolicy::ACTION_RESTORE, Store::STATUS_SUSPENDED],
]);
