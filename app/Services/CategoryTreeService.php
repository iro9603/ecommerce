<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CategoryTreeService
{
    public const MAX_DEPTH = 3;

    public function nested(int $maxDepth = self::MAX_DEPTH): array
    {
        $categories = Category::query()
            ->select(['id', 'parent_id', 'name', 'slug', 'is_active', 'position'])
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Category $category) => $category->parent_id ?? 0);

        return $this->buildNested($categories, null, 0, $maxDepth);
    }

    public function nextPosition(?int $parentId): int
    {
        return ((int) Category::query()
            ->where('parent_id', $parentId)
            ->max('position')) + 1;
    }

    public function parentValidationMessage(?int $parentId, ?Category $category = null): ?string
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Category::query()->find($parentId);

        if (! $parent) {
            return null;
        }

        if ($category) {
            if ($parent->is($category)) {
                return 'A category cannot be its own parent.';
            }

            if ($this->ancestorPathContains($parent, $category->id)) {
                return 'A category cannot be moved below one of its descendants.';
            }
        }

        $subtreeDepth = $category ? $this->subtreeDepth($category) : 1;

        if ($this->depth($parent) + $subtreeDepth > self::MAX_DEPTH) {
            return 'Maximum category depth is 3.';
        }

        return null;
    }

    public function orderTreeValidationMessage(array $nodes): ?string
    {
        $ids = [];
        $message = $this->collectOrderNodeIds($nodes, $ids, 1);

        if ($message) {
            return $message;
        }

        $existingIds = Category::query()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $submittedIds = collect($ids)
            ->sort()
            ->values()
            ->all();

        if ($submittedIds !== $existingIds) {
            return 'Category order payload must include every category exactly once.';
        }

        return null;
    }

    public function updateOrder(array $nodes): void
    {
        DB::transaction(function () use ($nodes) {
            $locked = Category::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Category $category): int => (int) $category->getKey());

            $this->updateTree($nodes, null, $locked);
        }, 3);
    }

    private function buildNested(Collection $groups, ?int $parentId, int $depth, int $maxDepth): array
    {
        if ($depth >= $maxDepth) {
            return [];
        }

        return $groups
            ->get($parentId ?? 0, collect())
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'parent_id' => $category->parent_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'is_active' => (bool) $category->is_active,
                'position' => $category->position,
                'children_nested' => $this->buildNested($groups, $category->id, $depth + 1, $maxDepth),
            ])
            ->values()
            ->all();
    }

    private function collectOrderNodeIds(array $nodes, array &$ids, int $depth): ?string
    {
        if ($depth > self::MAX_DEPTH) {
            return 'Maximum category depth is 3.';
        }

        foreach ($nodes as $node) {
            if (! is_array($node) || ! isset($node['id']) || ! is_numeric($node['id'])) {
                return 'Invalid category order payload.';
            }

            $id = (int) $node['id'];

            if (isset($ids[$id])) {
                return 'Category order payload contains duplicate categories.';
            }

            $ids[$id] = $id;

            if (isset($node['children'])) {
                if (! is_array($node['children'])) {
                    return 'Invalid category order payload.';
                }

                $message = $this->collectOrderNodeIds($node['children'], $ids, $depth + 1);

                if ($message) {
                    return $message;
                }
            }
        }

        return null;
    }

    private function updateTree(array $nodes, ?int $parentId, Collection $locked): void
    {
        foreach ($nodes as $position => $node) {
            $category = $locked->get((int) $node['id']);
            abort_unless($category instanceof Category, 422, 'Unknown category in order payload.');
            $category->forceFill([
                'parent_id' => $parentId,
                'position' => $position,
            ]);

            if ($category->isDirty()) {
                $category->save();
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $this->updateTree($node['children'], (int) $node['id'], $locked);
            }
        }
    }

    private function depth(Category $category): int
    {
        $depth = 1;
        $visited = [$category->id => true];
        $parent = $category->parent;

        while ($parent) {
            if (isset($visited[$parent->id])) {
                return self::MAX_DEPTH + 1;
            }

            $visited[$parent->id] = true;
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    private function subtreeDepth(Category $category, int $currentDepth = 1, array $visited = []): int
    {
        if (isset($visited[$category->id])) {
            return self::MAX_DEPTH + 1;
        }

        $visited[$category->id] = true;
        $maxDepth = $currentDepth;

        foreach ($category->children as $child) {
            $maxDepth = max(
                $maxDepth,
                $this->subtreeDepth($child, $currentDepth + 1, $visited)
            );
        }

        return $maxDepth;
    }

    private function ancestorPathContains(Category $category, int $ancestorId): bool
    {
        $current = $category;
        $visited = [];

        while ($current) {
            if ((int) $current->id === $ancestorId) {
                return true;
            }

            if (isset($visited[$current->id])) {
                return true;
            }

            $visited[$current->id] = true;
            $current = $current->parent;
        }

        return false;
    }
}
