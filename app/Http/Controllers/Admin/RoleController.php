<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AlertService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller implements HasMiddleware
{
    public static function Middleware(): array
    {
        return [
            new Middleware('permission:Role Management'),

        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $roles = Role::query()
            ->where('guard_name', 'admin')
            ->withCount('permissions')
            ->get();

        return view('admin.role.index', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $permissions = $this->adminPermissions();

        return view('admin.role.create', compact('permissions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'role' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where('guard_name', 'admin'),
            ],
            'permissions' => ['required', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'admin'),
            ],
        ]);

        $role = Role::create(['name' => $request->role, 'guard_name' => 'admin']);
        $role->syncPermissions($request->permissions);

        AlertService::created();

        return redirect()->route('admin.role.index');
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
    public function edit(Role $role)
    {
        $this->abortIfNotAdminGuard($role);

        $permissions = $this->adminPermissions();

        return view('admin.role.edit', compact('role', 'permissions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {
        $this->abortIfNotAdminGuard($role);

        if ($role->name == 'Super Admin') {
            AlertService::error('You can not update Super Admin role.');

            return to_route('admin.role.index');
        }

        $request->validate([
            'role' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->ignore($role->id)
                    ->where('guard_name', 'admin'),
            ],
            'permissions' => ['required', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'admin'),
            ],
        ]);

        $role->update([
            'name' => $request->role,
        ]);
        $role->syncPermissions($request->permissions);

        AlertService::updated();

        return redirect()->route('admin.role.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role): JsonResponse
    {
        $this->abortIfNotAdminGuard($role);

        if ($role->name == 'Super Admin') {
            return response()->json([
                'status' => 'error',
                'message' => 'You can not delete Super Admin role.',
            ]);
        }

        try {
            DB::beginTransaction();

            // remove role user

            $role->users()->detach();

            // detach permission from role

            $role->permissions()->detach();

            $role->delete();

            DB::commit();

            AlertService::deleted();

            return response()->json(['status' => 'success', 'message' => 'Deleted successfully.']);
        } catch (\Throwable $th) {
            DB::rollback();
            Log::error('Role Delete Error:', $th);

            return response()->json(['status' => 'error', 'message' => $th->getMessage()], 500);
        }
    }

    private function adminPermissions()
    {
        return Permission::query()
            ->where('guard_name', 'admin')
            ->orderBy('group_name')
            ->orderBy('name')
            ->get()
            ->groupBy('group_name');
    }

    private function abortIfNotAdminGuard(Role $role): void
    {
        abort_unless($role->guard_name === 'admin', 404);
    }
}
