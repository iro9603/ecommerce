@extends('admin.layouts.app')

@section('contents')
    <div class="container-xl">

        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title mb-1">All Role Users</h2>
                    <div class="text-muted">
                        Manage admin roles users and their assigned permissions.
                    </div>
                </div>

                <div class="col-auto ms-auto">
                    <a href="{{ route('admin.role-users.create') }}" class="btn btn-primary">
                        Create user
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-bottom">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 w-100">
                    <div>
                        <h3 class="card-title mb-1">Role Users</h3>
                        <p class="card-subtitle text-muted mb-0">
                            Search role users by name or email.
                        </p>
                    </div>

                    <form action="{{ url('admin/role-users') }}" method="GET" class="w-100" style="max-width: 420px;">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent">
                                <i class="ti ti-search"></i>
                            </span>

                            <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                                placeholder="Search users..." aria-label="Search users">

                            <button type="submit" class="btn btn-primary">
                                Search
                            </button>
                            @if (isset($_REQUEST['search']))
                                <a href="{{ url('admin/role-users') }}" class="btn btn-success">
                                    <i class="bi bi-trash">Limpiar</i>
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-header border-bottom">
                <div>
                    <h3 class="card-title mb-0">Role list</h3>
                    <p class="card-subtitle">
                        Review, edit or delete existing roles.
                    </p>
                </div>
            </div>

            <div class="card-body p-0">
                @if ($admins->count())
                    <div class="table-responsive">
                        <table class="table table-vcenter table-hover card-table">
                            <thead>
                                <tr>
                                    <th class="w-1 text-muted">#</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($admins as $admin)
                                    <tr>
                                        <td class="text-muted">
                                            {{ $loop->iteration }}
                                        </td>

                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar avatar-sm bg-primary-lt text-primary">
                                                    {{ strtoupper(substr($admin->name, 0, 1)) }}
                                                </div>

                                                <div>
                                                    <div class="fw-semibold">
                                                        {{ $admin->name }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="text-muted small">
                                                    {{ $admin->email }}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @foreach ($admin->getRoleNames() as $role)
                                                <span class="badge bg-primary-lt">
                                                    {{ $role }}
                                                </span>
                                            @endforeach
                                        </td>
                                        <th>
                                            @if ($admin->status == 1)
                                                <span class="badge bg-success text-white">Active</span>
                                            @elseif($admin->status == 0)
                                                <span class="badge bg-danger text-white">Inactive</span>
                                            @endif
                                        </th>
                                        <td>
                                            <div class="d-flex justify-content-end gap-2">
                                                @if (!$admin->hasRole('Super Admin') && $admin->status == 1)
                                                    <a href="{{ route('admin.role-users.edit', $admin) }}"
                                                        class="btn btn-sm btn-outline-primary">
                                                        Edit
                                                    </a>

                                                    <a href="{{ route('admin.role-users.destroy', $admin) }}"
                                                        class="btn btn-sm btn-outline-danger delete-item">
                                                        Delete
                                                    </a>
                                                @elseif(!$admin->hasRole('Super Admin') && $admin->status == 0)
                                                    <form action="{{ route('admin.user-role.restore', $admin->id) }}"
                                                        method="POST" id="miFormulario{{ $admin->id }}" class="d-line">
                                                        @csrf
                                                        <button type="submit" class="btn btn-warning btn-md"
                                                            onclick="preguntar{{ $admin->id }}(event)">
                                                            <i class="ti ti-rotate-clockwise">Restore</i>
                                                        </button>
                                                    </form>
                                                    <script>
                                                        function preguntar{{ $admin->id }}(event) {
                                                            event.preventDefault();
                                                            Swal.fire({
                                                                title: "Are you sure to restore this user?",
                                                                text: "",
                                                                icon: "warning",
                                                                showCancelButton: true,
                                                                confirmButtonColor: "#3085d6",
                                                                cancelButtonColor: "#d33",
                                                                confirmButtonText: "Restore"
                                                            }).then((result) => {
                                                                if (result.isConfirmed) {
                                                                    document.getElementById('miFormulario{{ $admin->id }}').submit();
                                                                }
                                                            })
                                                        }
                                                    </script>
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

            {{--  @if ($roles->count())
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
            @endif --}}
        </div>

    </div>
@endsection
