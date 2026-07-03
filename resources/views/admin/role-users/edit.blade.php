@extends('admin.layouts.app')

@section('contents')
    <div class="container-xl">

        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title mb-1">Update User</h2>
                    <div class="text-muted">
                        Create a new admin user and assign a role.
                    </div>
                </div>

                <div class="col-auto ms-auto">
                    <a href="{{ route('admin.role-users.index') }}" class="btn btn-outline-secondary">
                        Back to users
                    </a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.role-users.update', $admin) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row row-cards">

                {{-- User information --}}
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">User Information</h3>
                                <p class="card-subtitle">
                                    Update the basic login information for this user.
                                </p>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-12">
                                    <label class="form-label required">Name</label>
                                    <input type="text" class="form-control" name="name" placeholder=""
                                        value="{{ $admin->name }}">
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label required">Email</label>
                                    <input type="email" class="form-control" name="email" placeholder=""
                                        value="{{ $admin->email }}">
                                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">Password</label>
                                    <input type="password" class="form-control" name="password" placeholder="">
                                    <small class="form-hint">
                                        Use a secure password with at least 8 characters.
                                    </small>
                                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label required">Confirm Password</label>
                                    <input type="password" class="form-control" name="password_confirmation" placeholder="">
                                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                {{-- Update Role assignment --}}
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Update Role Assignment</h3>
                                <p class="card-subtitle">
                                    Choose the permissions group for this user.
                                </p>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label required">Select Role</label>

                                <select name="role" class="form-select">
                                    <option value="">Select a role</option>

                                    @foreach ($roles as $role)
                                        @if ($role->name == 'Super Admin')
                                            @continue
                                        @endif
                                        <option @selected(in_array($role->name, $admin->getRoleNames()->toArray())) value="{{ $role->id }}">
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>

                                <x-input-error :messages="$errors->get('role')" class="mt-2" />
                            </div>

                            <div class="alert alert-info mb-0">
                                The selected role will determine what this user can access inside the admin panel.
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-body d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.role-users.index') }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>

                            <button type="submit" class="btn btn-primary">
                                Update User
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </form>

    </div>
@endsection
