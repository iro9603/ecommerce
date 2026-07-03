@extends('admin.layouts.app')

@section('contents')
    <div class="container-xl">

        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title mb-1">Settings</h2>
                    <div class="text-muted">
                        Manage your site information, avatar, email and security preferences.
                    </div>
                </div>

                <div class="col-auto ms-auto">

                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header border-bottom">
                <div>
                    <h3 class="card-title mb-1">Site Settings</h3>
                    <p class="card-subtitle">
                        Update your site information.
                    </p>
                </div>
            </div>

            <div class="card-body p-0">
                @yield('settings_contents')
            </div>
        </div>

    </div>
@endsection
