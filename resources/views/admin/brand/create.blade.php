@extends('admin.layouts.app')

@section('contents')
    <div class="container-xl">

        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title mb-1">Create brand</h2>
                    <div class="text-muted">
                        Create a new brand to help organize and classify products, posts, or content.
                    </div>
                </div>

                <div class="col-auto ms-auto">
                    <a href="{{ route('admin.brands.index') }}" class="btn btn-outline-secondary">
                        Back to brands
                    </a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.brands.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row row-cards">

                {{-- Brand information --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Brand Information</h3>
                                <p class="card-subtitle">
                                    Define the basic information for this brand. Brands help identify product manufacturers,
                                    improve organization, and make products easier to browse.
                                </p>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="" class="mb-3 form-label">Brand Logo</label>
                                    <x-input-image id="image-preview" name="brand_logo" />
                                    <x-input-error :messages="$errors->get('brand_logo')" class="mt-2" />
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label required">Brand Name</label>
                                <input type="text" class="form-control" name="name"
                                    placeholder="e.g. Summer Sale, New Arrival, Featured" value="{{ old('name') }}">
                                <small class="form-hint">
                                    Use the official brand name that will be displayed with its related products.
                                </small>
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-check form-switch form-switch-3">
                                    <input type="checkbox" class="form-check-input" name="status" id="status">
                                    <span class="form-check-label">Active</span>
                                </label>
                                <small class="form-hint">
                                    Active tags can be assigned and displayed in the system. Inactive tags will be hidden
                                    from regular use.
                                </small>
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
                                Create Brand
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
