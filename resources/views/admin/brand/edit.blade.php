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

        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title mb-1">Update Brand</h2>
                    <div class="text-muted">
                        Update the brand details used to identify and organize products.
                    </div>
                </div>

                <div class="col-auto ms-auto">
                    <a href="{{ route('admin.brands.index') }}" class="btn btn-outline-secondary">
                        Back to brands
                    </a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.brands.update', $brand) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row row-cards">

                {{-- Brand information --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Brand Information</h3>
                                <p class="card-subtitle">
                                    Update the brand information used to identify, organize, and filter products.
                                </p>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row g-4 align-items-start">

                                {{-- Brand logo --}}
                                <div class="col-12 col-md-4 col-lg-3">
                                    <label class="form-label">Brand Logo</label>

                                    <div class="brand-logo-upload">
                                        <x-input-image id="image-preview" name="brand_logo" :image="$brand->image ? asset($brand->image) : null" />
                                    </div>

                                    <small class="form-hint mt-2">
                                        Recommended: PNG, JPG or WebP.
                                    </small>

                                    <x-input-error :messages="$errors->get('brand_logo')" class="mt-2" />
                                </div>

                                {{-- Brand fields --}}
                                <div class="col-12 col-md-8 col-lg-9">
                                    <div class="mb-3">
                                        <label class="form-label required">Brand Name</label>

                                        <input type="text" class="form-control" name="name"
                                            placeholder="e.g. Nike, Apple, Samsung" value="{{ old('name', $brand->name) }}">

                                        <small class="form-hint">
                                            Use the official brand name that will be displayed with its related products.
                                        </small>

                                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                    </div>

                                    <div class="mb-3">
                                        <label for="status" class="form-check form-switch form-switch-3">
                                            <input type="checkbox" @checked($brand->is_active) class="form-check-input"
                                                name="status" id="status">

                                            <span class="form-check-label">Active</span>
                                        </label>

                                        <small class="form-hint">
                                            Active brands can be assigned to products and displayed in the store. Inactive
                                            brands will be hidden from regular use.
                                        </small>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-body d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.brands.index') }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>

                            <button type="submit" class="btn btn-primary">
                                Update Brand
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </form>

    </div>
@endsection
@push('scripts')
    <script type="text/javascript">
        $(document).ready(function() {
            $.uploadPreview({
                input_field: "#brand_logo-upload",
                preview_box: "#image-preview",
                label_field: "#brand_logo-label",
                label_default: "Choose File",
                label_selected: "Change File",
                no_label: false
            });
        });
    </script>
@endpush
