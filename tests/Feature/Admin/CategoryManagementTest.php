<?php

use App\Models\Admin;
use App\Models\Category;

beforeEach(function () {
    $this->admin = Admin::forceCreate([
        'name' => 'Category Manager',
        'email' => 'category-manager@example.com',
        'email_verified_at' => now(),
        'password' => 'password',
    ]);
});

function categoryRecord(array $attributes = []): Category
{
    static $sequence = 1;

    $name = $attributes['name'] ?? 'Category '.$sequence;
    $slug = $attributes['slug'] ?? 'category-'.$sequence;
    $sequence++;

    return Category::create(array_merge([
        'name' => $name,
        'slug' => $slug,
        'parent_id' => null,
        'position' => 1,
        'is_active' => true,
    ], $attributes));
}

test('an admin can create a category with a normalized slug', function () {
    $this
        ->actingAs($this->admin, 'admin')
        ->postJson(route('admin.categories.store'), [
            'name' => 'Cafe and Tea',
            'slug' => 'Cafe & Tea',
            'parent_id' => null,
            'is_active' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Category::query()->where('slug', 'cafe-tea')->exists())->toBeTrue();
});

test('a deleted category cannot be used as parent', function () {
    $deletedParent = categoryRecord(['name' => 'Deleted Parent', 'slug' => 'deleted-parent']);
    $deletedParent->delete();

    $this
        ->actingAs($this->admin, 'admin')
        ->postJson(route('admin.categories.store'), [
            'name' => 'Child',
            'slug' => 'child-of-deleted',
            'parent_id' => $deletedParent->id,
            'is_active' => 1,
        ])
        ->assertJsonValidationErrors('parent_id');

    expect(Category::query()->where('slug', 'child-of-deleted')->exists())->toBeFalse();
});

test('a category cannot be assigned as its own parent', function () {
    $category = categoryRecord();

    $this
        ->actingAs($this->admin, 'admin')
        ->putJson(route('admin.categories.update', $category->id), [
            'name' => $category->name,
            'slug' => $category->slug,
            'parent_id' => $category->id,
            'is_active' => 1,
        ])
        ->assertJsonValidationErrors('parent_id');

    expect($category->fresh()->parent_id)->toBeNull();
});

test('a category cannot be moved below one of its descendants', function () {
    $parent = categoryRecord(['name' => 'Parent', 'slug' => 'parent']);
    $child = categoryRecord(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);

    $this
        ->actingAs($this->admin, 'admin')
        ->putJson(route('admin.categories.update', $parent->id), [
            'name' => $parent->name,
            'slug' => $parent->slug,
            'parent_id' => $child->id,
            'is_active' => 1,
        ])
        ->assertJsonValidationErrors('parent_id');

    expect($parent->fresh()->parent_id)->toBeNull();
});

test('a fourth category level is rejected', function () {
    $root = categoryRecord(['name' => 'Root', 'slug' => 'root']);
    $child = categoryRecord(['name' => 'Child', 'slug' => 'root-child', 'parent_id' => $root->id]);
    $grandchild = categoryRecord(['name' => 'Grandchild', 'slug' => 'root-child-grandchild', 'parent_id' => $child->id]);

    $this
        ->actingAs($this->admin, 'admin')
        ->postJson(route('admin.categories.store'), [
            'name' => 'Too Deep',
            'slug' => 'too-deep',
            'parent_id' => $grandchild->id,
            'is_active' => 1,
        ])
        ->assertJsonValidationErrors('parent_id');

    expect(Category::query()->where('slug', 'too-deep')->exists())->toBeFalse();
});

test('category order payload must include every category exactly once', function () {
    $root = categoryRecord(['name' => 'Root', 'slug' => 'order-root']);
    $child = categoryRecord(['name' => 'Child', 'slug' => 'order-child', 'parent_id' => $root->id]);

    $this
        ->actingAs($this->admin, 'admin')
        ->postJson(route('admin.categories.update-order'), [
            'tree' => [
                ['id' => $root->id],
            ],
        ])
        ->assertJsonValidationErrors('tree');

    expect($child->fresh()->parent_id)->toBe($root->id);
});

test('an admin can reorder and reparent categories with a valid tree payload', function () {
    $firstRoot = categoryRecord(['name' => 'First Root', 'slug' => 'first-root', 'position' => 0]);
    $secondRoot = categoryRecord(['name' => 'Second Root', 'slug' => 'second-root', 'position' => 1]);
    $child = categoryRecord(['name' => 'Child', 'slug' => 'valid-order-child', 'parent_id' => $firstRoot->id, 'position' => 0]);

    $this
        ->actingAs($this->admin, 'admin')
        ->postJson(route('admin.categories.update-order'), [
            'tree' => [
                [
                    'id' => $secondRoot->id,
                    'children' => [
                        ['id' => $child->id],
                    ],
                ],
                ['id' => $firstRoot->id],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($secondRoot->fresh()->parent_id)->toBeNull()
        ->and($secondRoot->fresh()->position)->toBe(0)
        ->and($child->fresh()->parent_id)->toBe($secondRoot->id)
        ->and($child->fresh()->position)->toBe(0)
        ->and($firstRoot->fresh()->position)->toBe(1);
});
