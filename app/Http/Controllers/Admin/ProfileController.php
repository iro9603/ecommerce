<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AlertService;
use App\Traits\FileUploadTrait;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    use FileUploadTrait;
    public function index(): View
    {
        return view('admin.profile.index');
    }

    public function profileUpdate(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'unique:admins,email,' . auth('admin')->user()->id],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = auth('admin')->user();
        if ($request->hasFile('avatar')) {

            $filepath = $this->uploadFile($request->file('avatar'), $user->avatar);
            $filepath ? $user->avatar = $filepath : null;
        }
        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();

        AlertService::updated();

        return redirect()->back();
    }

    public function passwordUpdate(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = auth('admin')->user();

        $user->password = Hash::make($request->password);
        $user->save();

        AlertService::updated();

        return redirect()->back();
    }
}
