<?php

/** check user has permission */

use App\Models\User;
use Illuminate\Support\Facades\Auth;

if (!function_exists('hasPermission')) {
    function hasPermission(array $permissions): bool
    {
        $admin = auth('admin')->user();

        if (!$admin) {
            return false;
        }

        if ($admin->hasRole('Super Admin')) {
            return true;
        }

        return $admin->hasAnyPermission($permissions);
    }
}

if (!function_exists('user')) {
    function user(): User | null
    {
        return Auth::user('web');
    }
}
