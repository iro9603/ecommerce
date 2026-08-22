@extends('admin.layouts.app')

@section('contents')
    <div class="container-xl">

        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title mb-1">Stores</h2>
                    <div class="text-muted">
                        Review and moderate vendor store applications.
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <div class="btn-list">
                <a href="{{ route('admin.stores.index') }}"
                    class="btn {{ !request()->query('status') ? 'btn-primary' : 'btn-outline-primary' }}">
                    All
                </a>
                <a href="{{ route('admin.stores.index', ['status' => 'pending']) }}"
                    class="btn {{ request()->query('status') === 'pending' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Pending
                </a>
                <a href="{{ route('admin.stores.index', ['status' => 'approved']) }}"
                    class="btn {{ request()->query('status') === 'approved' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Approved
                </a>
                <a href="{{ route('admin.stores.index', ['status' => 'rejected']) }}"
                    class="btn {{ request()->query('status') === 'rejected' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Rejected
                </a>
                <a href="{{ route('admin.stores.index', ['status' => 'suspended']) }}"
                    class="btn {{ request()->query('status') === 'suspended' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Suspended
                </a>
                <a href="{{ route('admin.stores.index', ['status' => 'draft']) }}"
                    class="btn {{ request()->query('status') === 'draft' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Draft
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-bottom">
                <div>
                    <h3 class="card-title mb-0">Store list</h3>
                    <p class="card-subtitle">
                        Review store applications and manage their approval status.
                    </p>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-vcenter table-hover card-table">
                        <thead>
                            <tr>
                                <th class="w-1 text-muted">#</th>
                                <th>Store</th>
                                <th>Seller</th>
                                <th>KYC</th>
                                <th>Status</th>
                                <th>Approved By</th>
                                <th>Submitted</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($stores as $store)
                                <tr>
                                    <td class="text-muted">
                                        {{ $loop->iteration }}
                                    </td>

                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            @if ($store->logo)
                                                <span class="avatar avatar-sm">
                                                    <img src="{{ asset($store->logo) }}" alt="{{ $store->name }}">
                                                </span>
                                            @else
                                                <span class="avatar avatar-sm bg-secondary-lt">
                                                    <i class="ti ti-building-store"></i>
                                                </span>
                                            @endif

                                            <div>
                                                <div class="fw-semibold">
                                                    <a href="{{ route('admin.stores.show', $store) }}">
                                                        {{ $store->name }}
                                                    </a>
                                                </div>
                                                <small class="text-muted">
                                                    {{ $store->city ?? '—' }}, {{ $store->country ?? '—' }}
                                                </small>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div>{{ $store->seller?->name ?? '—' }}</div>
                                        <small class="text-muted">{{ $store->seller?->email }}</small>
                                    </td>

                                    <td>
                                        @if ($store->seller?->kyc?->status === 'approved')
                                            <span class="badge bg-success-lt">Approved</span>
                                        @else
                                            <span
                                                class="badge bg-danger-lt">{{ ucfirst($store->seller?->kyc?->status ?? 'missing') }}</span>
                                        @endif
                                    </td>

                                    <td>
                                        @php
                                            $status = $store->status;
                                            $class = match ($status) {
                                                'approved' => 'bg-success-lt',
                                                'pending' => 'bg-warning-lt',
                                                'rejected' => 'bg-danger-lt',
                                                'suspended' => 'bg-danger-lt',
                                                default => 'bg-secondary-lt',
                                            };
                                        @endphp
                                        <span class="badge {{ $class }}">
                                            {{ ucfirst($status) }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ $store->approver?->name ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $store->created_at?->format('Y-m-d') }}
                                    </td>

                                    <td class="text-end">
                                        <a href="{{ route('admin.stores.show', $store) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="ti ti-eye fs-3"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="empty py-5">
                                            <div class="empty-img">
                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="icon icon-tabler icon-tabler-building-store" width="56"
                                                    height="56" viewBox="0 0 24 24" stroke-width="1.5"
                                                    stroke="currentColor" fill="none" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                    <path d="M3 21l18 0" />
                                                    <path
                                                        d="M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4" />
                                                    <path d="M5 21v-10.15" />
                                                    <path d="M19 21v-10.15" />
                                                    <path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4" />
                                                </svg>
                                            </div>

                                            <p class="empty-title">No stores found</p>
                                            <p class="empty-subtitle text-muted">
                                                There are no stores matching the current filter.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($stores->hasPages())
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="text-muted">
                        Showing {{ $stores->firstItem() }}–{{ $stores->lastItem() }} of {{ $stores->total() }}
                    </div>
                    <div>
                        {{ $stores->links() }}
                    </div>
                </div>
            @endif
        </div>

    </div>
@endsection
