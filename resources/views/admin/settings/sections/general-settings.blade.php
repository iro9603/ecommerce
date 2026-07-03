@extends('admin.settings.index')

@section('settings_contents')
    <div class="row g-0">
        {{-- Sidebar --}}
        <div class="col-12 col-lg-3 border-end bg-light">
            <div class="card-body">
                <h4 class="subheader">Account</h4>

                <div class="list-group list-group-transparent mb-4">
                    <a href="#" class="list-group-item list-group-item-action d-flex align-items-center active">
                        <span class="me-2">
                            <i class="ti ti-user"></i>
                        </span>
                        General Settings
                    </a>
                </div>

                {{-- <h4 class="subheader">Preferences</h4> --}}

                <div class="list-group list-group-transparent">
                    {{-- <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">
                        <span class="me-2">
                            <i class="ti ti-settings"></i>
                        </span>
                        General Settings
                    </a> --}}
                </div>
            </div>
        </div>

        {{-- Main content --}}
        <div class="col-12 col-lg-9 d-flex flex-column">
            <div class="card-body">

                <div class="mb-4">
                    <h2 class="mb-1">General Settings</h2>
                    <p class="text-muted mb-0">
                        Keep your site information up to date.
                    </p>
                </div>

                {{-- Business Profile --}}
                <div class="mb-5">
                    <div class="border-bottom pb-3 mb-4">
                        {{-- <h3 class="card-title mb-1">Business Profile</h3>
                        <p class="card-subtitle">
                            This information helps identify your account inside the admin panel.
                        </p> --}}
                    </div>

                    <form action="{{ route('admin.settings.general') }}" method="post">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label">Site Name</label>
                                <input type="text" class="form-control" name="site_name"
                                    value="{{ config('settings.site_name') }}">
                                <x-input-error :messages="$errors->get('site_name')" class="mt-2" />
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Site Email</label>
                                <input type="text" class="form-control" name="site_email"
                                    value="{{ config('settings.site_email') }}">
                                <x-input-error :messages="$errors->get('site_email')" class="mt-2" />
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Site Phone</label>
                                <input type="text" class="form-control" name="site_phone"
                                    value="{{ config('settings.site_phone') }}">
                                <x-input-error :messages="$errors->get('site_phone')" class="mt-2" />
                            </div>
                        </div>
                </div>

            </div>

            <div class="card-footer bg-transparent mt-auto">
                <div class="btn-list justify-content-end">
                    <button class="btn btn-primary" type="submit">
                        Save changes
                    </button>
                </div>
            </div>
            </form>
        </div>

    </div>
@endsection
