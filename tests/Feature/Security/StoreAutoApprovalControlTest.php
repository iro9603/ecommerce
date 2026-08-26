<?php

use App\Jobs\EvaluateProductApprovalContext;
use App\Models\Product;
use App\Models\ProductApprovalReview;
use App\Models\StoreAutoApprovalAudit;
use App\Services\ProductModerationService;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\ProductSecurityFixtures;

function autoApprovalPermission(): Permission
{
    return Permission::findOrCreate('Store Auto-Approval Management', 'admin');
}

test('product managers cannot change store auto-approval without the dedicated permission', function () {
    $admin = ProductSecurityFixtures::admin();
    $admin->givePermissionTo(Permission::findOrCreate('Product Management', 'admin'));
    $vendor = ProductSecurityFixtures::vendor();

    $this
        ->actingAs($admin, 'admin')
        ->patchJson(route('admin.stores.product-auto-approval.update', $vendor['store']), [
            'enabled' => true,
            'reason' => 'Trusted after a manual store review.',
        ])
        ->assertForbidden();

    expect($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and(StoreAutoApprovalAudit::query()->exists())->toBeFalse();
});

test('an eligible store can be trusted with an audit and pending products are resubmitted', function () {
    Queue::fake();
    $admin = ProductSecurityFixtures::admin();
    $admin->givePermissionTo(autoApprovalPermission());
    $vendor = ProductSecurityFixtures::vendor();
    $product = ProductSecurityFixtures::product($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $oldReview = $moderation->submit($product, $vendor['user'], 'Initial submission.');
    Queue::fake();

    $response = $this
        ->actingAs($admin, 'admin')
        ->patchJson(route('admin.stores.product-auto-approval.update', $vendor['store']), [
            'enabled' => true,
            'reason' => 'Trusted after a manual store review.',
        ]);

    $response->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('changed', true)
        ->assertJsonPath('resubmitted_products', 1);

    $product->refresh();
    $newReview = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 1)
        ->sole();
    $audit = StoreAutoApprovalAudit::query()->sole();

    expect($vendor['store']->fresh()->auto_approve_products)->toBeTrue()
        ->and($oldReview->fresh()->status)->toBe(ProductApprovalReview::STATUS_PENDING)
        ->and($product->moderation_version)->toBe(1)
        ->and($newReview->status)->toBe(ProductApprovalReview::STATUS_PENDING)
        ->and((bool) $newReview->evaluation_context['auto_approve_products'])->toBeTrue()
        ->and($audit->admin_id)->toBe($admin->getKey())
        ->and($audit->previous_value)->toBeFalse()
        ->and($audit->new_value)->toBeTrue()
        ->and($audit->pending_products_resubmitted)->toBe(1);

    Queue::assertPushed(
        EvaluateProductApprovalContext::class,
        fn (EvaluateProductApprovalContext $job): bool => $job->productId === $product->getKey()
            && $job->moderationVersion === 1
    );

    $this
        ->actingAs($admin, 'admin')
        ->patchJson(route('admin.stores.product-auto-approval.update', $vendor['store']), [
            'enabled' => true,
            'reason' => 'Idempotent retry after the same review.',
        ])
        ->assertOk()
        ->assertJsonPath('changed', false);

    expect(StoreAutoApprovalAudit::query()->count())->toBe(1)
        ->and($product->fresh()->moderation_version)->toBe(1);
});

test('an ineligible store cannot be trusted but automatic approval can always be disabled', function () {
    $admin = ProductSecurityFixtures::admin();
    $admin->givePermissionTo(autoApprovalPermission());
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['status' => 'pending']);

    $this
        ->actingAs($admin, 'admin')
        ->patchJson(route('admin.stores.product-auto-approval.update', $vendor['store']), [
            'enabled' => true,
            'reason' => 'Attempted trust before store activation.',
        ])
        ->assertJsonValidationErrors('enabled');

    expect($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and(StoreAutoApprovalAudit::query()->exists())->toBeFalse();

    $vendor['store']->forceFill(['auto_approve_products' => true])->save();

    $this
        ->actingAs($admin, 'admin')
        ->patchJson(route('admin.stores.product-auto-approval.update', $vendor['store']), [
            'enabled' => false,
            'reason' => 'Disable automatic approval as a fail-safe.',
        ])
        ->assertOk()
        ->assertJsonPath('enabled', false);

    expect($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and(StoreAutoApprovalAudit::query()->count())->toBe(1);
});

test('an idempotent enable request revokes stale trust when the seller is no longer eligible', function () {
    Queue::fake();
    $admin = ProductSecurityFixtures::admin();
    $admin->givePermissionTo(autoApprovalPermission());
    $vendor = ProductSecurityFixtures::vendor(
        storeOverrides: ['auto_approve_products' => true],
    );
    $product = ProductSecurityFixtures::product($vendor['store']);
    $moderation = app(ProductModerationService::class);
    $review = $moderation->submit($product, $vendor['user'], 'Initial trusted review.');
    $moderation->approve(
        $product,
        null,
        'Approved before the seller became ineligible.',
        $review->version,
    );

    // Deliberately bypass model events to reproduce stale trust left by a
    // bulk/import write. The endpoint must still fail closed.
    $vendor['user']->forceFill(['user_type' => 'user'])->saveQuietly();

    $this
        ->actingAs($admin, 'admin')
        ->patchJson(route('admin.stores.product-auto-approval.update', $vendor['store']), [
            'enabled' => true,
            'reason' => 'Retry trust after an external seller account change.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('enabled');

    $audit = StoreAutoApprovalAudit::query()->sole();

    expect($vendor['store']->fresh()->auto_approve_products)->toBeFalse()
        ->and($product->fresh()->approved_status)->toBe(Product::APPROVAL_APPROVED)
        ->and($product->fresh()->moderation_version)->toBe(1)
        ->and($product->fresh()->reviewed_version)->toBe(1)
        ->and(Product::query()->published()->whereKey($product)->exists())->toBeFalse()
        ->and($audit->previous_value)->toBeTrue()
        ->and($audit->new_value)->toBeFalse()
        ->and($audit->admin_id)->toBe($admin->getKey())
        ->and($audit->eligibility_snapshot['seller_user_type'])->toBe('user');
});
