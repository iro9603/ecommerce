<?php

/** check user has permission */

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
