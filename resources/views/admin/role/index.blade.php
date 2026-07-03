@extends('admin.layouts.app')

@section('contents')
    <div class="container-xl">

        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title mb-1">Roles</h2>
                    <div class="text-muted">
                        Manage admin roles and their assigned permissions.
                    </div>
                </div>

                <div class="col-auto ms-auto">
                    <a href="{{ route('admin.role.create') }}" class="btn btn-primary">
                        Create role
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-bottom">
                <div>
                    <h3 class="card-title mb-0">Role list</h3>
                    <p class="card-subtitle">
                        Review, edit or delete existing roles.
                    </p>
                </div>
            </div>

            <div class="card-body p-0">
                @if ($roles->count())
                    <div class="table-responsive">
                        <table class="table table-vcenter table-hover card-table">
                            <thead>
                                <tr>
                                    <th class="w-1 text-muted">#</th>
                                    <th>Role Name</th>
                                    <th>Permissions</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($roles as $role)
                                    <tr>
                                        <td class="text-muted">
                                            {{ $loop->iteration }}
                                        </td>

                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar avatar-sm bg-primary-lt text-primary">
                                                    {{ strtoupper(substr($role->name, 0, 1)) }}
                                                </div>

                                                <div>
                                                    <div class="fw-semibold">
                                                        {{ $role->name }}
                                                    </div>
                                                    <div class="text-muted small">
                                                        Role ID: {{ $role->id }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            <span class="badge bg-primary-lt">
                                                {{ $role->permissions_count }}
                                                {{ Str::plural('permission', $role->permissions_count) }}
                                            </span>
                                        </td>

                                        <td>
                                            <div class="d-flex justify-content-end gap-2">
                                                @if ($role->name != 'Super Admin')
                                                    <a href="{{ route('admin.role.edit', $role) }}"
                                                        class="btn btn-sm btn-outline-primary">
                                                        Edit
                                                    </a>

                                                    <a href="{{ route('admin.role.destroy', $role) }}"
                                                        class="btn btn-sm btn-outline-danger delete-item">
                                                        Delete
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty py-5">
                        <div class="empty-img">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-shield-lock"
                                width="56" height="56" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 3l8 4v5c0 5 -3.5 9 -8 10c-4.5 -1 -8 -5 -8 -10v-5l8 -4" />
                                <path d="M12 11m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                                <path d="M12 12l0 2.5" />
                            </svg>
                        </div>

                        <p class="empty-title">No roles found</p>
                        <p class="empty-subtitle text-muted">
                            Start by creating your first admin role and assigning permissions.
                        </p>

                        <div class="empty-action">
                            <a href="{{ route('admin.role.create') }}" class="btn btn-primary">
                                Create role
                            </a>
                        </div>
                    </div>
                @endif
            </div>

            @if ($roles->count())
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="text-muted">
                        Showing {{ $roles->count() }} {{ Str::plural('role', $roles->count()) }}
                    </div>

                    @if (method_exists($roles, 'links'))
                        <div>
                            {{ $roles->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>

    </div>
@endsection
