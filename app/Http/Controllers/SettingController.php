<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AlertService;
use App\Services\SettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;


class SettingController extends Controller
{
    function index(): View
    {
        return view('admin.settings.sections.general-settings');
    }

    function generalSettings(Request $request): RedirectResponse
    {
        $validateData = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'site_email' => ['nullable', 'email', 'max:255'],
            'site_phone' => ['nullable', 'string', 'max:255']
        ]);

        foreach ($validateData as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        $settings = app()->make(SettingService::class);
        $settings->clearCacheSettings();

        AlertService::updated();

        return redirect()->back();
    }
}
