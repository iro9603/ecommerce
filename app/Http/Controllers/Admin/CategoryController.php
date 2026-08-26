<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryOrderRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryTreeService;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller implements HasMiddleware
{
    static function Middleware(): array
    {
        return [
            new Middleware('permission:Category Management')

        ];
    }

    public function index(): View
    {
        return view('admin.category.index');
    }

    public function store(StoreCategoryRequest $request, CategoryTreeService $categories)
    {
        $data = $request->validated();
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;
        $data['position'] = $categories->nextPosition($data['parent_id']);

        $category = Category::create($data);

        return response()->json(['success' => true, 'message' => 'Category created successfully.',  'category' => $category]);
    }

    public function update(UpdateCategoryRequest $request, int $id)
    {
        $data = $request->validated();
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['is_active'] = $request->boolean('is_active');

        $category = DB::transaction(function () use ($id, $data): Category {
            $current = Category::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $current->forceFill($data)->save();

            return $current;
        }, 3);

        return response()->json(['success' => true, 'message' => 'Category updated successfully.',  'category' => $category]);
    }

    public function updateOrder(UpdateCategoryOrderRequest $request, CategoryTreeService $categories)
    {
        try {
            $categories->updateOrder($request->validated()['tree']);

            return response()->json(['success' => true, 'message' => 'Category order updated successfully.']);
        } catch (\Throwable $th) {
            Log::error('Category order update failed.', ['exception' => $th]);

            return response()->json(['success' => false, 'message' => 'Unable to update category order.'], 500);
        }
    }

    public function show(int $id)
    {
        $category = Category::findOrFail($id);

        return response()->json($category);
    }

    public function destroy(int $id)
    {
        $deleted = DB::transaction(function () use ($id): bool {
            $category = Category::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($category->children()->lockForUpdate()->exists()) {
                return false;
            }

            $category->delete();

            return true;
        }, 3);

        if (! $deleted) {
            return response()->json(['error' => true, 'message' => 'Category has children and cannot be deleted'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Category deleted successfully']);
    }

    public function getNestedCategories(CategoryTreeService $categories)
    {
        return response()->json($categories->nested());
    }
}
