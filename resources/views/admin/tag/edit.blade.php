@extends('admin.layouts.app')

@section('contents')
    <div class="container-xl">

        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title mb-1">Update Tag</h2>
                    <div class="text-muted">
                        Edit the tag.
                    </div>
                </div>

                <div class="col-auto ms-auto">
                    <a href="{{ route('admin.tags.index') }}" class="btn btn-outline-secondary">
                        Back to tags
                    </a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.tags.update', $tag) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row row-cards">

                {{-- Role information --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Tag Information</h3>
                                <p class="card-subtitle">
                                    Update the tag used to classify products, posts, or content.
                                </p>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label required">Name</label>
                                <input type="text" class="form-control" name="name"
                                    placeholder="e.g. Summer Sale, New Arrival, Featured"
                                    value="{{ old('name', $tag->name) }}">
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-check form-switch form-switch-3">
                                    <input type="checkbox" @checked($tag->is_active) class="form-check-input"
                                        name="status" id="status">
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
                            <a href="{{ route('admin.tags.index') }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>

                            <button type="submit" class="btn btn-primary">
                                Update Tag
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </form>

    </div>
@endsection
