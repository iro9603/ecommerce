@extends('admin.layouts.app')

@section('contents')
    <div class="container-xl">

        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title mb-1">Products</h2>
                    <div class="text-muted">
                        Manage products.
                    </div>
                </div>

                <div class="col-auto ms-auto">
                    <div class="dropdown">
                        <button class="btn btn-primary dropdown-toggle" type="button" id="dropdownMenuButton"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            Create Product
                        </button>

                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <a class="dropdown-item" href="{{ route('admin.products.create') }}">Physical</a>
                            <a class="dropdown-item" href="#">Digital</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-bottom">
                <div>
                    <h3 class="card-title mb-0">Role list</h3>
                    <p class="card-subtitle">
                        Review, edit or delete existing roles.
                    </p>
                </div>
            </div>

            <div class="card-body p-0">
                @if (1 == 1)
                    <div class="table-responsive">
                        <table class="table table-vcenter table-hover card-table">
                            <thead>
                                <tr>
                                    <th class="w-1 text-muted">Product ID </th>
                                    <th>Image</th>
                                    <th>Product Name</th>
                                    <th>Product Type</th>
                                    <th>Price</th>
                                    <th>Stock Status</th>
                                    <th>Quantity</th>
                                    <th>Created At</th>
                                    <th>Status</th>
                                    <th>Store</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($products as $product)
                                    <tr>
                                        <td class="text-muted">
                                            <div class="text-muted small">
                                                {{ $product->id }}
                                            </div>
                                        </td>

                                        <td>
                                            <img style="width:50px" src="{{ asset($product->primaryImage?->path) }}"
                                                alt="">
                                        </td>

                                        <td>
                                            <div class="d-flex align-items-center gap-3">

                                                <div>

                                                    <div class="fw-semibold">
                                                        <a
                                                            href="{{ route('admin.products.index') }}">{{ $product->name }}</a>
                                                    </div>

                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">

                                                <div>

                                                    <div class="fw-semibold">
                                                        {{ $product->product_type }}
                                                    </div>

                                                </div>

                                            </div>
                                        </td>
                                        <td>
                                            @if ($product->primaryVariant)
                                                @if ($product->primaryVariant?->special_price > 0)
                                                    <div class="fw-semibold">
                                                        {{ $product->primaryVariant->special_price }}

                                                    </div>
                                                    <div class="text-danger" style="text-decoration:line-through">
                                                        {{ $product->primaryVariant?->price }}
                                                    </div>
                                                @else
                                                    {{ $product->primaryVariant?->price }}
                                                @endif
                                            @else
                                                @if ($product->special_price > 0)
                                                    <div class="fw-semibold">
                                                        {{ $product->special_price }}

                                                    </div>
                                                    <div class="text-danger" style="text-decoration:line-through">
                                                        {{ $product->price }}
                                                    </div>
                                                @else
                                                    {{ $product->price }}
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            @if ($product->primaryVariant)
                                                @if ($product->primaryVariant?->in_stock == 1)
                                                    <small class="text-success">In Stock</small>
                                                @else
                                                    <small class="text-danger">Out of Stock</small>
                                                @endif
                                            @else
                                                @if ($product->in_stock == 1)
                                                    <small class="text-success">In Stock</small>
                                                @else
                                                    <small class="text-danger">Out of Stock</small>
                                                @endif
                                            @endif
                                        </td>

                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div>
                                                    <div class="fw-semibold">
                                                        @if ($product->primaryVariant)
                                                            @if ($product->primaryVariant->manage_stock == 1)
                                                                {{ $product->primaryVariant->qty }}
                                                            @else
                                                                ∞
                                                            @endif
                                                        @else
                                                            @if ($product->manage_stock == 'on')
                                                                {{ $product->qty }}
                                                            @else
                                                                ∞
                                                            @endif
                                                        @endif
                                                    </div>

                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">

                                                <div>

                                                    <div class="fw-semibold">
                                                        {{ date('Y-m-d', strtotime($product->created_at)) }}
                                                    </div>

                                                </div>
                                            </div>
                                        </td>

                                        <td>

                                            @if ($product->status == 'active')
                                                <span class="badge bg-success-lt">Active</span>
                                            @elseif($product->status == 'inactive')
                                                <span class="badge bg-secondary-lt">Inactive</span>
                                            @elseif($product->status == 'pending')
                                                <span class="badge bg-warning-lt">Pending</span>
                                            @elseif($product->status == 'draft')
                                                <span class="badge bg-secondary-lt">Draft</span>
                                            @endif

                                        </td>

                                        <td>
                                            {{ $product->store->name }}
                                        </td>

                                        <td>
                                            <div class="d-flex justify-content-end gap-2">

                                                <a href="{{ route('admin.products.edit', $product->id) }}"
                                                    class="btn btn-sm btn-outline-primary">
                                                    Edit
                                                </a>

                                                <a href="" class="btn btn-sm btn-outline-danger delete-item">
                                                    Delete
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

                        <p class="empty-title">No roles found</p>
                        <p class="empty-subtitle text-muted">
                            Start by creating your first admin role and assigning permissions.
                        </p>

                        <div class="empty-action">
                            <a href="" class="btn btn-primary">
                                Create role
                            </a>
                        </div>
                    </div>
                @endif
            </div>

            {{--  @if ($roles->count())
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="text-muted">
                        Showing {{ $roles->count() }} {{ Str::plural('role', $roles->count()) }}
                    </div>

                    @if (method_exists($roles, 'links'))
                        <div>
                            {{ $roles->links() }}
                        </div>
                    @endif
                </div>
            @endif --}}
        </div>

    </div>
@endsection
