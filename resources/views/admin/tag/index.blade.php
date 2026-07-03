@extends('admin.layouts.app')

@stack('csss')
<style>
    .action-btn {
        width: 34px;
        height: 34px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .action-btn i {
        font-size: 1.1rem;
        line-height: 1;
    }

    /* Mobile */
    @media (max-width: 576px) {
        .action-btn {
            width: 42px;
            height: 42px;
        }

        .action-btn i {
            font-size: 1.35rem;
        }
    }
</style>

@section('contents')
    <div class="container-xl">

        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title mb-1">Product Tags</h2>
                    <div class="text-muted">
                        Manage product tags used to organize, classify, and filter products.
                    </div>
                </div>

                <div class="col-auto ms-auto">
                    <a href="{{ route('admin.tags.create') }}" class="btn btn-primary">
                        Create Tag
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-bottom">
                <div>
                    <h3 class="card-title mb-0">Tag list</h3>
                    <p class="card-subtitle">
                        Review, edit, or delete existing product tags.
                    </p>
                </div>
            </div>

            <div class="card-body p-0">
                @if ($tags->count())
                    <div class="table-responsive">
                        <table class="table table-vcenter table-hover card-table">
                            <thead>
                                <tr>
                                    <th class="w-1 text-muted">#</th>
                                    <th>Tag Name</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($tags as $tag)
                                    <tr>
                                        <td class="text-muted">
                                            {{ $loop->iteration }}
                                        </td>

                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="avatar avatar-sm bg-primary-lt text-primary">
                                                    {{ strtoupper(substr($tag->name, 0, 1)) }}
                                                </div>

                                                <div>
                                                    <div class="fw-semibold">
                                                        {{ $tag->name }}
                                                    </div>
                                                    <div class="text-muted small">
                                                        Tag ID: {{ $tag->id }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <td>
                                            @if ($tag->is_active == 1)
                                                <span class="badge bg-primary-lt">
                                                    Active
                                                </span>
                                            @else
                                                <span class="badge bg-danger-lt">
                                                    Inactive
                                                </span>
                                            @endif
                                        </td>

                                        <td>
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="{{ route('admin.tags.edit', $tag) }}"
                                                    class="btn btn-sm btn-outline-primary action-btn">
                                                    <i class="ti ti-edit"></i>
                                                </a>

                                                <a href="{{ route('admin.tags.destroy', $tag) }}"
                                                    class="btn btn-sm btn-outline-danger delete-item action-btn">
                                                    <i class="ti ti-trash"></i>
                                                </a>
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

                        <p class="empty-title">No tags found</p>
                        <p class="empty-subtitle text-muted">
                            Create your first tag to start organizing your products.
                        </p>

                        <div class="empty-action">
                            <a href="{{ route('admin.tags.create') }}" class="btn btn-primary">
                                Create tag
                            </a>
                        </div>
                    </div>
                @endif
            </div>

        </div>

    </div>
@endsection
