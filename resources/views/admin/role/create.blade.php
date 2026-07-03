@extends('admin.layouts.app')

@section('contents')
    <div class="container-xl">

        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title mb-1">Create Role</h2>
                    <div class="text-muted">
                        Create a new role and assign the permissions it should have.
                    </div>
                </div>

                <div class="col-auto ms-auto">
                    <a href="{{ route('admin.role.index') }}" class="btn btn-outline-secondary">
                        Back to roles
                    </a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.role.store') }}" method="POST">
            @csrf

            <div class="row row-cards">

                {{-- Role information --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Role Information</h3>
                                <p class="card-subtitle">
                                    Define the role name that will be used to identify this permission group.
                                </p>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label required">Role Name</label>
                                <input type="text" class="form-control" name="role"
                                    placeholder="Example: Store Manager" value="{{ old('role') }}">
                                <x-input-error :messages="$errors->get('role')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Permissions --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Permissions</h3>
                                <p class="card-subtitle">
                                    Select the permissions that this role will have.
                                </p>
                            </div>
                        </div>

                        <div class="card-body">
                            <x-input-error :messages="$errors->get('permissions')" class="mb-3" />

                            <div class="row g-3">
                                @foreach ($permissions as $groupName => $permission)
                                    <div class="col-md-6 col-xl-4">
                                        <div class="card h-100 border">
                                            <div class="card-header bg-light">
                                                <div>
                                                    <h4 class="card-title mb-0">
                                                        {{ ucfirst($groupName) }}
                                                    </h4>
                                                    <div class="text-muted small">
                                                        {{ $permission->count() }}
                                                        {{ $permission->count() == 1 ? 'permission' : 'permissions' }}
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="card-body">
                                                <div class="divide-y">
                                                    @foreach ($permission as $item)
                                                        <label class="form-check py-2">
                                                            <input type="checkbox" name="permissions[]"
                                                                class="form-check-input" value="{{ $item->name }}"
                                                                @checked(collect(old('permissions', []))->contains($item->name))>

                                                            <span class="form-check-label">
                                                                {{ $item->name }}
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-body d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.role.index') }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>

                            <button type="submit" class="btn btn-primary">
                                Create Role
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </form>

    </div>
@endsection
