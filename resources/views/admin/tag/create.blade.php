@extends('admin.layouts.app')

@section('contents')
    <div class="container-xl">

        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title mb-1">Create Tag</h2>
                    <div class="text-muted">
                        Create a new tag to help organize and classify products, posts, or content.
                    </div>
                </div>

                <div class="col-auto ms-auto">
                    <a href="{{ route('admin.tags.index') }}" class="btn btn-outline-secondary">
                        Back to tags
                    </a>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.tags.store') }}" method="POST">
            @csrf

            <div class="row row-cards">

                {{-- Tag information --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Tag Information</h3>
                                <p class="card-subtitle">
                                    Define the basic information for this tag. Tags help group related content and make it
                                    easier to find.
                                </p>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label required">Tag Name</label>
                                <input type="text" class="form-control" name="name"
                                    placeholder="e.g. Summer Sale, New Arrival, Featured" value="{{ old('name') }}">
                                <small class="form-hint">
                                    Use a short and clear name that describes the content this tag will represent.
                                </small>
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-check form-switch form-switch-3">
                                    <input type="checkbox" class="form-check-input" name="status" id="status"
                                        value="1">
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
                                Create Tag
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </form>

    </div>
@endsection
