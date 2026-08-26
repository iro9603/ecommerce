<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\AlertService;
use App\Traits\FileUploadTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;

class BrandController extends Controller implements HasMiddleware
{

    use FileUploadTrait;

    static function Middleware(): array
    {
        return [
            new Middleware('permission:Brand Management')

        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $brands = Brand::paginate(20);
        return view('admin.brand.index', compact('brands'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.brand.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'brand_logo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $logoPath = $this->uploadFile($request->file('brand_logo'));

        $brand = new Brand();
        $brand->name = $request->name;
        $brand->image = $logoPath;
        $brand->slug = \Str::slug($request->name);
        $brand->is_active = $request->has('status') ? 1 : 0;
        $brand->save();


        AlertService::created();

        return to_route('admin.brands.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Brand $brand)
    {
        return view('admin.brand.edit', compact('brand'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Brand $brand)
    {
        $request->validate([
            'brand_logo' => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $newImage = null;
        if ($request->hasFile('brand_logo')) {
            $newImage = $this->uploadFile($request->file('brand_logo'), null);
        }

        try {
            $oldImage = DB::transaction(function () use ($brand, $request, $newImage): ?string {
                $current = Brand::query()->whereKey($brand->getKey())->lockForUpdate()->firstOrFail();
                $oldImage = $current->image;
                $current->name = $request->name;
                $current->slug = \Str::slug($request->name);
                $current->is_active = $request->has('status') ? 1 : 0;

                if ($newImage !== null) {
                    $current->image = $newImage;
                }

                $current->save();

                return $oldImage;
            }, 3);
        } catch (\Throwable $exception) {
            if ($newImage !== null) {
                $this->deleteFile($newImage);
            }

            throw $exception;
        }

        if ($newImage !== null && $oldImage !== null && $oldImage !== $newImage) {
            $this->deleteMediaAfterCommit($oldImage);
        }


        AlertService::created();

        return to_route('admin.brands.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Brand $brand)
    {
        $image = DB::transaction(function () use ($brand): ?string {
            $current = Brand::query()->whereKey($brand->getKey())->lockForUpdate()->firstOrFail();
            $image = $current->image;
            $current->delete();

            return $image;
        }, 3);
        $this->deleteMediaAfterCommit($image);
        AlertService::deleted();
        return response()->json(['status' => 'success', 'message' => 'Brand deleted successfully.']);
    }

    private function deleteMediaAfterCommit(?string $path): void
    {
        if ($path === null) {
            return;
        }

        $connection = DB::connection();

        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit(fn () => $this->deleteFile($path));

            return;
        }

        $this->deleteFile($path);
    }
}
