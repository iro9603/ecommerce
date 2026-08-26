<?php

use App\Jobs\ReevaluateProductsAfterReferenceChange;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductApprovalReview;
use App\Models\Tag;
use App\Services\ProductModerationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\ProductSecurityFixtures;

test('an authorized admin can create a tag inside the transactional flow', function () {
    $admin = ProductSecurityFixtures::admin();
    $admin->givePermissionTo(Permission::findOrCreate('Tags Management', 'admin'));
    $name = 'Transactional Tag '.Str::uuid();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.tags.store'), [
            'name' => $name,
            'status' => '1',
        ])
        ->assertRedirect(route('admin.tags.index'));

    $this->assertDatabaseHas('tags', [
        'name' => $name,
        'slug' => Str::slug($name),
        'is_active' => true,
    ]);
});

test('tag creation rolls back when a later model listener fails', function () {
    $admin = ProductSecurityFixtures::admin();
    $admin->givePermissionTo(Permission::findOrCreate('Tags Management', 'admin'));
    $name = 'Rolled Back Tag '.Str::uuid();

    Event::listen('eloquent.created: '.Tag::class, function (Tag $tag) use ($name): void {
        if ($tag->name === $name) {
            throw new RuntimeException('Injected failure after tag insert.');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($admin, 'admin')
        ->post(route('admin.tags.store'), [
            'name' => $name,
            'status' => '0',
        ]))->toThrow(RuntimeException::class, 'Injected failure after tag insert.');

    $this->assertDatabaseMissing('tags', ['name' => $name]);
});

function approvedProductForReferenceTest(array $attributes = []): Product
{
    $vendor = ProductSecurityFixtures::vendor(storeOverrides: ['is_active' => true]);

    return ProductSecurityFixtures::product($vendor['store'], array_merge([
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => 1,
        'reviewed_version' => 1,
        'approved_at' => now(),
    ], $attributes));
}

function queuedReferenceJobFor(Product $product): ReevaluateProductsAfterReferenceChange
{
    $queuedJob = null;
    Queue::assertPushed(
        ReevaluateProductsAfterReferenceChange::class,
        function (ReevaluateProductsAfterReferenceChange $job) use ($product, &$queuedJob): bool {
            if (! in_array($product->getKey(), $job->productIds, true)) {
                return false;
            }

            $queuedJob = $job;

            return true;
        },
    );

    expect($queuedJob)->toBeInstanceOf(ReevaluateProductsAfterReferenceChange::class);

    return $queuedJob;
}

function assertReferencePublicationBarrier(Product $product): void
{
    $current = Product::query()->findOrFail($product->getKey());

    expect($current->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($current->reviewed_version)->toBeNull()
        ->and($current->approved_at)->toBeNull()
        ->and(Product::query()->published()->whereKey($product->getKey())->exists())->toBeFalse();
}

test('changing a shared catalogue reference resubmits approved products', function () {
    Queue::fake();
    $vendor = ProductSecurityFixtures::vendor();
    $brand = Brand::forceCreate([
        'name' => 'Reviewed Brand',
        'slug' => 'reviewed-brand-'.Str::uuid(),
    ]);
    $product = ProductSecurityFixtures::product($vendor['store'], [
        'brand_id' => $brand->getKey(),
        'approved_status' => Product::APPROVAL_APPROVED,
        'moderation_version' => 1,
        'reviewed_version' => 1,
        'approved_at' => now(),
    ]);

    $brand->forceFill(['name' => 'Changed Brand'])->save();
    assertReferencePublicationBarrier($product);
    $queuedJob = queuedReferenceJobFor($product);
    $queuedJob->handle(app(ProductModerationService::class));

    $product->refresh();
    $review = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 2)
        ->sole();

    expect($product->approved_status)->toBe(Product::APPROVAL_PENDING)
        ->and($product->moderation_version)->toBe(2)
        ->and($product->reviewed_version)->toBeNull()
        ->and($product->approved_at)->toBeNull()
        ->and($review->snapshot['brand']['name'])->toBe('Changed Brand');
});

test('a reference update and its publication barrier roll back together after a later listener fails', function () {
    Queue::fake();
    $brand = Brand::forceCreate([
        'name' => 'Atomic Brand',
        'slug' => 'atomic-brand-'.Str::uuid(),
        'is_active' => true,
    ]);
    $product = approvedProductForReferenceTest(['brand_id' => $brand->getKey()]);
    $barrierColumns = [
        'approved_status',
        'reviewed_version',
        'approved_at',
        'approved_by',
        'moderation_reason',
        'moderation_fingerprint',
        'risk_level',
        'risk_score',
        'updated_at',
    ];
    $originalBrandState = collect($brand->fresh()->getRawOriginal())
        ->only(['name', 'updated_at'])
        ->all();
    $originalProductState = collect($product->fresh()->getRawOriginal())
        ->only($barrierColumns)
        ->all();
    $barrierObserved = false;

    Event::listen(
        'eloquent.updated: '.Brand::class,
        function (Brand $updatedBrand) use ($brand, $product, &$barrierObserved): void {
            if (! $updatedBrand->is($brand)) {
                return;
            }

            $barrierObserved = Product::query()
                ->whereKey($product->getKey())
                ->value('approved_status') === Product::APPROVAL_PENDING;

            throw new RuntimeException('Injected failure after reference publication barrier.');
        },
    );

    expect(fn () => DB::transaction(function () use ($brand): void {
        $brand->forceFill(['name' => 'Brand mutation that must roll back'])->save();
    }))->toThrow(
        RuntimeException::class,
        'Injected failure after reference publication barrier.',
    );

    expect($barrierObserved)->toBeTrue()
        ->and(
            collect($brand->fresh()->getRawOriginal())
                ->only(['name', 'updated_at'])
                ->all()
        )->toBe($originalBrandState)
        ->and(
            collect($product->fresh()->getRawOriginal())
                ->only($barrierColumns)
                ->all()
        )->toBe($originalProductState);
});

test('deleting a brand closes publication before the worker runs', function () {
    Queue::fake();
    $brand = Brand::forceCreate([
        'name' => 'Brand to delete',
        'slug' => 'brand-delete-'.Str::uuid(),
        'is_active' => true,
    ]);
    $product = approvedProductForReferenceTest(['brand_id' => $brand->getKey()]);

    DB::transaction(fn () => $brand->delete());

    assertReferencePublicationBarrier($product);
    $job = queuedReferenceJobFor($product);
    $job->handle(app(ProductModerationService::class));

    $review = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 2)
        ->sole();

    expect($brand->newQuery()->whereKey($brand->getKey())->exists())->toBeFalse()
        ->and($review->snapshot['brand'])->toBeNull();
});

test('updating a category closes publication before the worker runs', function () {
    Queue::fake();
    $category = Category::forceCreate([
        'name' => 'Category before update',
        'slug' => 'category-update-'.Str::uuid(),
        'is_active' => true,
    ]);
    $product = approvedProductForReferenceTest();
    $product->categories()->attach($category);

    DB::transaction(function () use ($category): void {
        $category->forceFill(['name' => 'Category after update'])->save();
    });

    assertReferencePublicationBarrier($product);
    $job = queuedReferenceJobFor($product);
    $job->handle(app(ProductModerationService::class));
    $review = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 2)
        ->sole();
    $snapshotCategory = collect($review->snapshot['categories'])
        ->firstWhere('id', $category->getKey());

    expect($category->fresh()->name)->toBe('Category after update')
        ->and($snapshotCategory['name'])->toBe('Category after update');
});

test('deleting a category closes publication before the worker runs', function () {
    Queue::fake();
    $category = Category::forceCreate([
        'name' => 'Category to delete',
        'slug' => 'category-delete-'.Str::uuid(),
        'is_active' => true,
    ]);
    $product = approvedProductForReferenceTest();
    $product->categories()->attach($category);

    DB::transaction(fn () => $category->delete());

    assertReferencePublicationBarrier($product);
    $job = queuedReferenceJobFor($product);
    $job->handle(app(ProductModerationService::class));
    $review = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 2)
        ->sole();
    $snapshotCategory = collect($review->snapshot['categories'])
        ->firstWhere('id', $category->getKey());

    expect(Category::withTrashed()->findOrFail($category->getKey())->trashed())->toBeTrue()
        ->and($snapshotCategory['deleted_at'])->not->toBeNull();
});

test('updating a tag closes publication before the worker runs', function () {
    Queue::fake();
    $tag = Tag::forceCreate([
        'name' => 'Tag before update',
        'slug' => 'tag-update-'.Str::uuid(),
        'is_active' => true,
    ]);
    $product = approvedProductForReferenceTest();
    $product->tags()->attach($tag);

    DB::transaction(function () use ($tag): void {
        $tag->forceFill(['name' => 'Tag after update'])->save();
    });

    assertReferencePublicationBarrier($product);
    $job = queuedReferenceJobFor($product);
    $job->handle(app(ProductModerationService::class));
    $review = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 2)
        ->sole();
    $snapshotTag = collect($review->snapshot['tags'])->firstWhere('id', $tag->getKey());

    expect($tag->fresh()->name)->toBe('Tag after update')
        ->and($snapshotTag['name'])->toBe('Tag after update');
});

test('deleting a tag closes publication before the worker runs', function () {
    Queue::fake();
    $tag = Tag::forceCreate([
        'name' => 'Tag to delete',
        'slug' => 'tag-delete-'.Str::uuid(),
        'is_active' => true,
    ]);
    $product = approvedProductForReferenceTest();
    $product->tags()->attach($tag);

    DB::transaction(fn () => $tag->delete());

    assertReferencePublicationBarrier($product);
    $job = queuedReferenceJobFor($product);
    $job->handle(app(ProductModerationService::class));
    $review = ProductApprovalReview::query()
        ->where('product_id', $product->getKey())
        ->where('version', 2)
        ->sole();

    expect(Tag::query()->whereKey($tag->getKey())->exists())->toBeFalse()
        ->and($review->snapshot['tag_ids'])->toBe([])
        ->and($review->snapshot['tags'])->toBe([]);
});
