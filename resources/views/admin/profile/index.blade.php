@extends('admin.layouts.app')

@push('styles')
    <style>
        .brand-logo-upload {
            width: 130px;
            height: 130px;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            background-color: #f8fafc;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-logo-upload #image-preview {
            width: 110px !important;
            height: 110px !important;
            max-width: 110px !important;
            max-height: 110px !important;
            border-radius: 8px;
            background-size: contain !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
        }

        .brand-logo-upload #image-preview label {
            bottom: 8px;
            top: auto;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(226, 232, 240, 0.95);
            white-space: nowrap;
        }

        .brand-logo-upload #image-preview input[type="file"] {
            cursor: pointer;
        }

        @media (max-width: 576px) {
            .brand-logo-upload {
                width: 180px;
                height: 180px;
                margin-left: auto;
                margin-right: auto;
            }

            .brand-logo-upload #image-preview {
                width: 160px !important;
                height: 160px !important;
                max-width: 160px !important;
                max-height: 160px !important;
            }

            .brand-logo-upload #image-preview label {
                bottom: 12px;
                font-size: 0.85rem;
            }
        }
    </style>
@endpush

@section('contents')
    <div class="container-xl">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Update profile</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3 brand-logo-upload">
                                <x-input-image id="image-preview" name="avatar" :image="asset(auth('admin')->user()->avatar)" />
                                <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label required">Name</label>
                                    <input type="text" class="form-control" name="name" placeholder=""
                                        value="{{ auth('admin')->user()->name }}">
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label required">Email</label>
                                    <input type="email" class="form-control" name="email" placeholder=""
                                        value="{{ auth('admin')->user()->email }}">
                                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Account</button>
                </form>
            </div>
        </div>
        <div class="card mt-5">
            <div class="card-header">
                <h3 class="card-title">Update password</h3>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.profile.password.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="row mt-30">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label required">Current password</label>
                                <input type="password" class="form-control" name="current_password" placeholder="">
                                <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label required">Password</label>
                                <input type="password" class="form-control" name="password" placeholder="">
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label required">Confirm Password</label>
                                <input type="password" class="form-control" name="password_confirmation" placeholder="">
                                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                            </div>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Update
                                password</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script type="text/javascript">
        $(document).ready(function() {
            $.uploadPreview({
                input_field: "#image-upload", // Default: .image-upload
                preview_box: "#image-preview", // Default: .image-preview
                label_field: "#image-label", // Default: .image-label
                label_default: "Choose File", // Default: Choose File
                label_selected: "Change File", // Default: Change File
                no_label: false // Default: false
            });
        });
    </script>
@endpush
