@extends('admin.layouts.app')
<style>
    .store-banner {
        width: 100%;
        aspect-ratio: 3 / 1;
        background: #f6f8fa;
    }

    .store-banner img {
        object-fit: contain;
        object-position: center;
    }

    .store-logo {
        height: 100%;
        min-height: 200px;
    }

    .store-logo img {
        max-width: 75%;
        max-height: 160px;
        object-fit: contain;
    }
</style>
@section('contents')
    <div class="container-xl">

        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <a href="{{ route('admin.stores.index') }}" class="text-muted small">
                        <i class="ti ti-arrow-left me-1"></i> Back to stores
                    </a>
                    <h2 class="page-title mb-1">{{ $store->name }}</h2>
                </div>

                <div class="col-auto ms-auto">
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
                    <span class="badge {{ $class }} fs-5">
                        {{ ucfirst($status) }}
                    </span>
                </div>
            </div>
        </div>

        <div class="row row-cards">

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Store details</h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @if ($store->banner && $store->logo)
                                <div class="row g-3">

                                    {{-- Banner --}}
                                    <div class="col-md-8">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body p-2">
                                                <div class="mb-2 d-flex align-items-center justify-content-between">
                                                    <span class="fw-semibold small text-secondary">
                                                        Store Banner
                                                    </span>

                                                    <i class="ti ti-photo text-secondary"></i>
                                                </div>

                                                <div class="store-banner rounded overflow-hidden">
                                                    <img src="{{ asset($store->banner) }}" alt="{{ $store->name }} banner"
                                                        class="w-100 h-100">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Logo --}}
                                    <div class="col-md-4">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body p-2">

                                                <div class="mb-2 d-flex align-items-center justify-content-between">
                                                    <span class="fw-semibold small text-secondary">
                                                        Store Logo
                                                    </span>

                                                    <i class="ti ti-building-store text-secondary"></i>
                                                </div>

                                                <div
                                                    class="store-logo d-flex align-items-center justify-content-center rounded bg-light">
                                                    <img src="{{ asset($store->logo) }}" alt="{{ $store->name }} logo"
                                                        class="img-fluid">
                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                </div>
                            @endif

                            <div class="col-md-4">
                                <label class="form-label text-muted">Contact phone</label>
                                <div>{{ $store->phone ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted">Contact email</label>
                                <div>{{ $store->email ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted">Country / Currency</label>
                                <div>{{ $store->country ?? '—' }} · {{ $store->currency ?? '—' }}</div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-muted">City</label>
                                <div>{{ $store->city ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted">State</label>
                                <div>{{ $store->state ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted">Postal code</label>
                                <div>{{ $store->postal_code ?? '—' }}</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-muted">Address</label>
                                <div>{!! e($store->address_line_1 ?? '—') . ($store->address_line_2 ? ', ' . e($store->address_line_2) : '') !!}</div>
                            </div>

                            @if ($store->short_description)
                                <div class="col-12">
                                    <label class="form-label text-muted">Short description</label>
                                    <div class="border rounded p-3 bg-secondary-subtle">
                                        {!! $safeShortDescriptionHtml !!}
                                    </div>
                                </div>
                            @endif

                            @if ($store->long_description)
                                <div class="col-12">
                                    <label class="form-label text-muted">Long description</label>
                                    <div class="border rounded p-3 bg-secondary-subtle">
                                        {!! $safeLongDescriptionHtml !!}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Seller</h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label text-muted">Name</label>
                                <div>{{ $store->seller?->name ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted">Email</label>
                                <div>{{ $store->seller?->email ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted">Account type</label>
                                <div>
                                    @if ($store->seller?->user_type === 'vendor')
                                        <span class="badge bg-success-lt">Vendor</span>
                                    @else
                                        <span
                                            class="badge bg-danger-lt">{{ ucfirst($store->seller?->user_type ?? 'missing') }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-muted">Email verified</label>
                                <div>
                                    @if ($store->seller?->email_verified_at)
                                        <span class="badge bg-success-lt">Verified</span>
                                    @else
                                        <span class="badge bg-danger-lt">Not verified</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted">KYC status</label>
                                <div>
                                    @if ($store->seller?->kyc?->status === 'approved')
                                        <span class="badge bg-success-lt">Approved</span>
                                    @else
                                        <span
                                            class="badge bg-danger-lt">{{ ucfirst($store->seller?->kyc?->status ?? 'missing') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted">Products</label>
                                <div>{{ $store->products_count ?? $store->products->count() }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Moderation actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="d-flex gap-2 flex-wrap">
                                    <form action="{{ route('admin.stores.approve', $store) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="moderation_version" value="{{ $store->moderation_version }}">
                                        <button type="submit" class="btn btn-success" @disabled($store->status === 'approved')>
                                            <i class="ti ti-circle-check me-1"></i> Approve
                                        </button>
                                    </form>

                                    <form action="{{ route('admin.stores.restore', $store) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="moderation_version" value="{{ $store->moderation_version }}">
                                        <button type="submit" class="btn btn-outline-primary" @disabled($store->status !== 'suspended')>
                                            <i class="ti ti-rotate me-1"></i> Restore
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <form action="{{ route('admin.stores.reject', $store) }}" method="POST"
                                    onsubmit="return confirm('Reject this store? Any approved products will be taken offline.');">
                                    @csrf
                                        <input type="hidden" name="moderation_version" value="{{ $store->moderation_version }}">
                                    <label class="form-label">Reject store</label>
                                    <textarea name="rejection_reason" class="form-control mb-2" rows="2" maxlength="2000" required
                                        placeholder="Reason for rejection (min 10 characters)"></textarea>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="ti ti-circle-x me-1"></i> Reject with reason
                                    </button>
                                </form>
                            </div>

                            <div class="col-md-6">
                                <form action="{{ route('admin.stores.suspend', $store) }}" method="POST"
                                    onsubmit="return confirm('Suspend this store? Any approved products will be taken offline.');">
                                    @csrf
                                        <input type="hidden" name="moderation_version" value="{{ $store->moderation_version }}">
                                    <label class="form-label">Suspend store</label>
                                    <textarea name="reason" class="form-control mb-2" rows="2" maxlength="2000" required
                                        placeholder="Reason for suspension (min 10 characters)"></textarea>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="ti ti-ban me-1"></i> Suspend with reason
                                    </button>
                                </form>
                            </div>
                        </div>

                        @if ($store->rejection_reason)
                            <div class="alert alert-danger mt-4 mb-0">
                                <strong>Rejection reason:</strong> {{ $store->rejection_reason }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>

    </div>
@endsection
