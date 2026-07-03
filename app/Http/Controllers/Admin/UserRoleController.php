<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller implements HasMiddleware
{
    public static function Middleware(): array
    {
        return [
            new Middleware('permission:Role User Management'),

        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $admins = Admin::query()
            ->with('roles')
            ->get();

        return view('admin.role-users.index', compact('admins'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = $this->assignableAdminRoles();

        return view('admin.role-users.create', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role' => [
                'required',
                Rule::exists('roles', 'id')->where(fn ($query) => $query
                    ->where('guard_name', 'admin')
                    ->where('name', '!=', 'Super Admin')),
            ],
        ]);

        $role = $this->findAssignableAdminRole($request->role);

        if ($role->name == 'Super Admin') {
            AlertService::error('You can not create Super Admin user.');

            return to_route('admin.role-users.index');
        }

        $admin = new Admin;
        $admin->name = $request->name;
        $admin->email = $request->email;
        $admin->password = Hash::make($request->password);
        $admin->save();

        // assign role

        $admin->syncRoles([$role]);

        AlertService::created();

        return redirect()->route('admin.role-users.index');
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
    public function edit(Admin $role_user)
    {
        $admin = $role_user;
        $roles = $this->assignableAdminRoles();

        return view('admin.role-users.edit', compact('admin', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Admin $role_user)
    {
        if ($role_user->hasRole('Super Admin')) {
            AlertService::error('You can not update Super Admin user.');

            return to_route('admin.role-users.index');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email,'.$role_user->id],
            'role' => [
                'required',
                Rule::exists('roles', 'id')->where(fn ($query) => $query
                    ->where('guard_name', 'admin')
                    ->where('name', '!=', 'Super Admin')),
            ],
        ]);

        $role = $this->findAssignableAdminRole($request->role);

        if ($role->name == 'Super Admin') {
            AlertService::error('You can not update Super Admin user.');

            return to_route('admin.role-users.index');
        }

        $admin = $role_user;
        $admin->name = $request->name;
        $admin->email = $request->email;
        if ($request->filled('password')) {
            $request->validate([
                'password' => ['required', 'confirmed', 'min:8'],
            ]);
            $admin->password = Hash::make($request->password);
        }
        $admin->save();

        // assign role

        $admin->syncRoles([$role]);

        AlertService::updated();

        return redirect()->route('admin.role-users.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Admin $role_user): JsonResponse
    {
        if ($role_user->hasRole('Super Admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'You can not delete Super Admin role.',
            ]);
        }

        try {
            $role_user->syncRoles([]);

            $role_user->delete();

            AlertService::deleted();

            return response()->json(['status' => 'success', 'message' => 'Deleted Successfully.']);
        } catch (\Throwable $th) {
            Log::error('Role Delete Error: ', $th);

            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
            ]);
        }
    }

    private function assignableAdminRoles()
    {
        return Role::query()
            ->where('guard_name', 'admin')
            ->where('name', '!=', 'Super Admin')
            ->orderBy('name')
            ->get();
    }

    private function findAssignableAdminRole(int|string $roleId): Role
    {
        return Role::query()
            ->where('guard_name', 'admin')
            ->where('name', '!=', 'Super Admin')
            ->findOrFail($roleId);
    }
}
