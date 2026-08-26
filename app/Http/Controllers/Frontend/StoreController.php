<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\AlertService;
use App\Services\ProductContentSanitizer;
use App\Services\StoreModerationService;
use App\Traits\FileUploadTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoreController extends Controller
{
    use FileUploadTrait;

    public function index(Request $request, ProductContentSanitizer $contentSanitizer): View
    {
        $store = $request->user()->store;

        if ($store !== null) {
            $store->short_description = $contentSanitizer->sanitize($store->short_description);
            $store->long_description = $contentSanitizer->sanitize($store->long_description);
        }

        return view('vendor-dashboard.store-profile.index', compact('store'));
    }

    public function update(
        Request $request,
        StoreModerationService $moderation,
        ProductContentSanitizer $contentSanitizer,
    ): RedirectResponse
    {
        foreach (['short_description', 'long_description'] as $field) {
            if ($request->exists($field)) {
                $request->merge([$field => $contentSanitizer->sanitize($request->input($field))]);
            }
        }

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

        if ($request->has('social_links')) {
            $validated['social_links'] = collect($validated['social_links'] ?? [])
                ->filter()
                ->all();
        } else {
            unset($validated['social_links']);
        }

        $newMedia = [];

        try {
            foreach (['logo', 'banner'] as $field) {
                if (! $request->hasFile($field)) {
                    unset($validated[$field]);

                    continue;
                }

                $path = $this->uploadFile($request->file($field), null, 'uploads/stores');

                if ($path === null) {
                    throw new \RuntimeException('The store media upload could not be completed.');
                }

                $validated[$field] = $path;
                $newMedia[] = $path;
            }

            $result = $moderation->updateProfile($request->user(), $validated);
        } catch (\Throwable $exception) {
            $this->deleteStoreMedia($newMedia);

            throw $exception;
        }

        $this->scheduleMediaCleanup($newMedia, $result['replaced_media']);
        $review = $result['review'];
        $wasApproved = $result['was_approved'];

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

    private function scheduleMediaCleanup(array $newMedia, array $replacedMedia): void
    {
        $connection = DB::connection();

        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit(fn () => $this->deleteStoreMedia($replacedMedia));
            $connection->afterRollBack(fn () => $this->deleteStoreMedia($newMedia));

            return;
        }

        $this->deleteStoreMedia($replacedMedia);
    }

    private function deleteStoreMedia(array $paths): void
    {
        $defaults = ['/default/avatar.png', '/default/banner.png', '/default/shop.png'];

        foreach (array_unique($paths) as $path) {
            if (is_string($path) && ! in_array($path, $defaults, true)) {
                $this->deleteFile($path);
            }
        }
    }

}
