<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\AlertService;
use App\Services\StoreModerationService;
use App\Traits\FileUploadTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoreController extends Controller
{
    use FileUploadTrait;

    public function index(Request $request): View
    {
        $store = $request->user()->store;

        return view('vendor-dashboard.store-profile.index', compact('store'));
    }

    public function update(Request $request, StoreModerationService $moderation): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'long_description' => ['nullable', 'string'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'currency' => ['required', Rule::in(['MXN', 'USD'])],
            'country' => ['required', Rule::in(['MX', 'US'])],
            'timezone' => [
                'required',
                Rule::in([
                    'America/Mexico_City',
                    'America/Monterrey',
                    'America/Tijuana',
                ]),
            ],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'url', 'max:255'],
            'social_links.instagram' => ['nullable', 'url', 'max:255'],
            'social_links.tiktok' => ['nullable', 'url', 'max:255'],
            'social_links.youtube' => ['nullable', 'url', 'max:255'],
            'social_links.website' => ['nullable', 'url', 'max:255'],
        ]);

        $store = Store::query()->firstOrNew([
            'seller_id' => $request->user()->id,
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $this->uploadFile(
                $request->file('logo'),
                $store->logo,
                'uploads/stores',
            );
        } else {
            unset($validated['logo']);
        }

        if ($request->hasFile('banner')) {
            $validated['banner'] = $this->uploadFile(
                $request->file('banner'),
                $store->banner,
                'uploads/stores',
            );
        } else {
            unset($validated['banner']);
        }

        if ($request->has('social_links')) {
            $validated['social_links'] = collect($validated['social_links'] ?? [])
                ->filter()
                ->all();
        } else {
            unset($validated['social_links']);
        }

        if (! $store->exists) {
            $store->seller_id = $request->user()->id;
            $store->slug = $this->uniqueSlug($validated['name']);
        }

        $isNew = ! $store->exists;
        $wasApproved = $store->exists && $store->isApproved();

        $store->fill($validated);
        $hasMaterialChanges = $store->isDirty(StoreModerationService::materialFields());
        $store->save();

        // Only a new store or a material profile change creates a new
        // moderation version. A no-op save must not invalidate an approved
        // store or create an unnecessary review.
        $review = null;

        if ($isNew || $hasMaterialChanges) {
            $review = $moderation->submitForReview(
                $store,
                $request->user(),
                $wasApproved
                    ? 'The vendor updated the store profile after it was approved.'
                    : 'The vendor created or updated the store profile.',
            );
        }

        if ($review !== null) {
            AlertService::updated(
                $wasApproved
                    ? 'Store profile updated. The store is now pending review.'
                    : 'Store profile saved. The store is now pending review by an administrator.'
            );
        } else {
            AlertService::updated('Store profile saved.');
        }

        return redirect()->route('vendor.store-profile.index');
    }

    private function uniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'store';
        $slug = $baseSlug;
        $suffix = 2;

        while (Store::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
