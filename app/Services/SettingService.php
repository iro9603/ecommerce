<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SettingService
{
    public function getSettings(): array
    {
        try {
            return Cache::rememberForever('settings', function () {
                return Setting::query()->pluck('value', 'key')->toArray();
            });
        } catch (Throwable $throwable) {
            Log::warning('Unable to load application settings.', [
                'message' => $throwable->getMessage(),
            ]);

            return [];
        }
    }

    public function setSettings(): void
    {
        $settings = $this->getSettings();
        config()->set('settings', $settings);
    }

    public function clearCacheSettings(): void
    {
        Cache::forget('settings');
    }
}
