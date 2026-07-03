@extends('admin.layouts.app')

@php
    $status = $kyc_request->status ?? 'pending';
    $statusOptions = [
        'pending' => [
            'label' => 'Pending review',
            'class' => 'is-pending',
            'description' => 'This request is waiting for an administrator to begin the review.',
        ],
        'under_review' => [
            'label' => 'Under review',
            'class' => 'is-review',
            'description' => 'The identity information and documents are currently being reviewed.',
        ],
        'approved' => [
            'label' => 'Approved',
            'class' => 'is-approved',
            'description' => 'The applicant identity has been verified successfully.',
        ],
        'rejected' => [
            'label' => 'Rejected',
            'class' => 'is-rejected',
            'description' => 'The request needs corrections before it can be approved.',
        ],
    ];
    $statusDetails = $statusOptions[$status] ?? $statusOptions['pending'];

    $formatDate = static function ($value, string $format = 'M d, Y'): string {
        if (!$value) {
            return 'Not provided';
        }

        try {
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    };

    $fullName = $kyc_request->full_name ?: 'Unnamed applicant';
    $initials = collect(preg_split('/\s+/', trim($fullName)))
        ->filter()
        ->take(2)
        ->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $address = collect([
        $kyc_request->address_line_1,
        $kyc_request->address_line_2,
        $kyc_request->city,
        $kyc_request->state,
        $kyc_request->postal_code,
        $kyc_request->country,
    ])
        ->filter()
        ->implode(', ');
    $documentType = str($kyc_request->document_type ?? 'Not provided')
        ->replace('_', ' ')
        ->title();
    $gender = str($kyc_request->gender ?? 'Not provided')
        ->replace('_', ' ')
        ->title();
    $documentExpired = $kyc_request->document_expiry_date
        ? \Carbon\Carbon::parse($kyc_request->document_expiry_date)->isPast()
        : false;

@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/admin/css/kyc/kyc.css') }}">
@endpush

@section('contents')
    <div class="container-xl kyc-shell py-4">
        <div class="kyc-page-heading">
            <div>
                <div class="kyc-eyebrow">Identity verification</div>
                <h1 class="kyc-title">KYC request #{{ str_pad($kyc_request->id, 5, '0', STR_PAD_LEFT) }}</h1>
                <p class="kyc-subtitle">
                    Review the applicant profile, submitted evidence and current verification status.
                </p>
            </div>

            <div class="btn-list">
                <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon">
                        <path d="M6 9V2h12v7" />
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                        <path d="M6 14h12v8H6z" />
                    </svg>
                    Print
                </button>
                <a href="{{ route('admin.kyc.index') }}" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon">
                        <path d="M15 6l-6 6l6 6" />
                    </svg>
                    Back to requests
                </a>
            </div>
        </div>

        <section class="kyc-card kyc-hero">
            <div class="kyc-hero-content">
                <div class="kyc-avatar">{{ $initials ?: 'KYC' }}</div>
                <div>
                    <h2 class="kyc-hero-name">{{ $fullName }}</h2>
                    <div class="kyc-hero-meta">
                        <span>{{ $kyc_request->user?->email ?? 'Email unavailable' }}</span>
                        <span>{{ str($kyc_request->user?->user_type ?? 'user')->title() }} account</span>
                        <span>Submitted {{ $formatDate($kyc_request->submitted_at) }}</span>
                    </div>
                </div>
                <span class="kyc-status {{ $statusDetails['class'] }}">{{ $statusDetails['label'] }}</span>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-lg-8">
                <section class="kyc-card kyc-section">
                    <div class="kyc-section-header">
                        <span class="kyc-section-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <circle cx="12" cy="7" r="4" />
                                <path d="M5.5 21a6.5 6.5 0 0 1 13 0" />
                            </svg>
                        </span>
                        <div>
                            <h2>Applicant identity</h2>
                            <p>Personal details supplied during verification.</p>
                        </div>
                    </div>

                    <div class="kyc-facts">
                        <div class="kyc-fact">
                            <span class="kyc-label">Full legal name</span>
                            <span class="kyc-value">{{ $fullName }}</span>
                        </div>
                        <div class="kyc-fact">
                            <span class="kyc-label">Date of birth</span>
                            <span class="kyc-value">{{ $formatDate($kyc_request->date_of_birth) }}</span>
                        </div>
                        <div class="kyc-fact">
                            <span class="kyc-label">Gender</span>
                            <span class="kyc-value">{{ $gender }}</span>
                        </div>
                        <div class="kyc-fact">
                            <span class="kyc-label">Nationality</span>
                            <span class="kyc-value">{{ strtoupper($kyc_request->nationality ?? 'Not provided') }}</span>
                        </div>
                    </div>
                </section>

                <section class="kyc-card kyc-section">
                    <div class="kyc-section-header">
                        <span class="kyc-section-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <circle cx="8" cy="10" r="2" />
                                <path d="M6 15c.8-1.3 3.2-1.3 4 0M14 9h4M14 13h4" />
                            </svg>
                        </span>
                        <div>
                            <h2>Identity document</h2>
                            <p>Document metadata provided by the applicant.</p>
                        </div>
                    </div>

                    <div class="kyc-facts">
                        <div class="kyc-fact">
                            <span class="kyc-label">Document type</span>
                            <span class="kyc-value">{{ $documentType }}</span>
                        </div>
                        <div class="kyc-fact">
                            <span class="kyc-label">Document number</span>
                            <span class="kyc-value">{{ $kyc_request->document_number ?: 'Not provided' }}</span>
                        </div>
                        <div class="kyc-fact">
                            <span class="kyc-label">Issuing country</span>
                            <span
                                class="kyc-value">{{ strtoupper($kyc_request->document_country ?? 'Not provided') }}</span>
                        </div>
                        <div class="kyc-fact">
                            <span class="kyc-label">Expiration date</span>
                            <span class="kyc-value">
                                {{ $formatDate($kyc_request->document_expiry_date) }}
                                @if ($documentExpired)
                                    <small>Document appears to be expired</small>
                                @endif
                            </span>
                        </div>
                    </div>
                </section>

                <section class="kyc-card kyc-section">
                    <div class="kyc-section-header">
                        <span class="kyc-section-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M12 21s6-4.35 6-10a6 6 0 1 0-12 0c0 5.65 6 10 6 10z" />
                                <circle cx="12" cy="11" r="2" />
                            </svg>
                        </span>
                        <div>
                            <h2>Residential address</h2>
                            <p>Address declared by the applicant.</p>
                        </div>
                    </div>

                    <div class="kyc-address">
                        <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M3 11l9-8l9 8" />
                            <path d="M5 10v10h14V10M9 20v-6h6v6" />
                        </svg>
                        <p>{{ $address ?: 'No residential address was provided.' }}</p>
                    </div>
                </section>

                <section class="kyc-card kyc-section">
                    <div class="kyc-section-header">
                        <span class="kyc-section-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                <path d="M5 8V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2h-5" />
                                <circle cx="6" cy="14" r="3" />
                                <path d="M4 19c.7-1.3 3.3-1.3 4 0" />
                            </svg>
                        </span>
                        <div>
                            <h2>Submitted evidence</h2>
                            <p>Private files open through signed links that expire after 10 minutes.</p>
                        </div>
                    </div>

                    <div class="kyc-document-grid">
                        @foreach ($documents as $document)
                            @php
                                $fileExists =
                                    $document['path'] &&
                                    \Illuminate\Support\Facades\Storage::disk('local')->exists($document['path']);
                                $temporaryUrl = $fileExists
                                    ? \Illuminate\Support\Facades\Storage::disk('local')->temporaryUrl(
                                        $document['path'],
                                        now()->addMinutes(10),
                                    )
                                    : null;
                            @endphp

                            <article class="kyc-document {{ $fileExists ? 'has-file' : '' }}">
                                <div class="kyc-document-top">
                                    <span class="kyc-document-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                            <path d="M5 5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2z" />
                                            <path d="M9 13h6M9 17h3" />
                                        </svg>
                                    </span>
                                    <div>
                                        <h3>{{ $document['label'] }}</h3>
                                        <p>{{ $document['description'] }}</p>
                                    </div>
                                </div>

                                @if ($fileExists)
                                    <div class="kyc-file-meta">
                                        <span class="kyc-file-name" title="{{ basename($document['path']) }}">
                                            {{ basename($document['path']) }}
                                        </span>

                                        <div class="kyc-file-actions">
                                            <a href="{{ $temporaryUrl }}" target="_blank" rel="noopener"
                                                class="kyc-secure-link">
                                                Open securely

                                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path
                                                        d="M14 3h7v7M10 14L21 3M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5" />
                                                </svg>
                                            </a>

                                            <a href="{{ route('admin.kyc.download', [$kyc_request->id, $document['type']]) }}"
                                                class="btn btn-primary btn-sm">
                                                Download
                                            </a>
                                        </div>
                                    </div>
                                @else
                                    <div class="kyc-file-state">
                                        {{ $document['required'] ? 'Required file is unavailable.' : 'Not submitted.' }}
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            </div>

            <div class="col-lg-4">
                <aside class="kyc-card kyc-aside">
                    <div class="kyc-review-summary">
                        <span class="kyc-status {{ $statusDetails['class'] }}">{{ $statusDetails['label'] }}</span>
                        <h2>Review progress</h2>
                        <p>{{ $statusDetails['description'] }}</p>
                    </div>

                    <div class="kyc-timeline">
                        <div class="kyc-timeline-item is-complete">
                            <span class="kyc-timeline-dot"></span>
                            <div>
                                <div class="kyc-timeline-title">Request submitted</div>
                                <div class="kyc-timeline-date">
                                    {{ $formatDate($kyc_request->submitted_at, 'M d, Y · H:i') }}</div>
                            </div>
                        </div>
                        <div
                            class="kyc-timeline-item {{ in_array($status, ['under_review', 'approved', 'rejected']) ? 'is-complete' : 'is-current' }}">
                            <span class="kyc-timeline-dot"></span>
                            <div>
                                <div class="kyc-timeline-title">Identity review</div>
                                <div class="kyc-timeline-date">
                                    {{ $kyc_request->reviewed_at ? $formatDate($kyc_request->reviewed_at, 'M d, Y · H:i') : 'Awaiting review' }}
                                </div>
                            </div>
                        </div>
                        <div
                            class="kyc-timeline-item {{ in_array($status, ['approved', 'rejected']) ? 'is-complete' : '' }}">
                            <span class="kyc-timeline-dot"></span>
                            <div>
                                <div class="kyc-timeline-title">Final decision</div>
                                <div class="kyc-timeline-date">
                                    @if ($status === 'approved')
                                        Approved {{ $formatDate($kyc_request->verified_at, 'M d, Y · H:i') }}
                                    @elseif ($status === 'rejected')
                                        Corrections requested
                                    @else
                                        No decision yet
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="kyc-review-meta">
                        <div>
                            <span class="kyc-label">Assigned reviewer</span>
                            <span class="kyc-value">
                                {{ $kyc_request->reviewer?->name ?? 'Not assigned' }}
                            </span>
                        </div>
                        <div>
                            <span class="kyc-label">Last updated</span>
                            <span class="kyc-value">{{ $formatDate($kyc_request->updated_at, 'M d, Y · H:i') }}</span>
                        </div>
                        <div>
                            <span class="kyc-label">Provider reference</span>
                            <span class="kyc-value">{{ $kyc_request->provider_reference ?: 'Manual verification' }}</span>
                        </div>
                        <div>
                            <form action="{{ route('admin.kyc.update', $kyc_request) }}" method="post"
                                class="kyc-decision-form">
                                @csrf
                                @method('PUT')

                                <div>
                                    <label for="kyc-status" class="form-label">Review decision</label>
                                    <select name="status" id="kyc-status"
                                        class="form-select @error('status') is-invalid @enderror" required>
                                        <option value="">Select a decision</option>
                                        <option value="under_review" @selected(old('status', $kyc_request->status) === 'under_review')>
                                            Mark as under review
                                        </option>
                                        <option value="approved" @selected(old('status', $kyc_request->status) === 'approved')>
                                            Approve request
                                        </option>
                                        <option value="rejected" @selected(old('status', $kyc_request->status) === 'rejected')>
                                            Reject request
                                        </option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div>
                                    <label for="review-notes" class="form-label">Internal review notes</label>
                                    <textarea name="review_notes" id="review-notes"
                                        class="form-control @error('review_notes') is-invalid @enderror" rows="3"
                                        maxlength="2000"
                                        placeholder="Optional notes visible to administrators">{{ old('review_notes', $kyc_request->review_notes) }}</textarea>
                                    @error('review_notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div id="rejection-reason-field">
                                    <label for="rejected-reason" class="form-label">Rejection reason</label>
                                    <textarea name="rejected_reason" id="rejected-reason"
                                        class="form-control @error('rejected_reason') is-invalid @enderror" rows="3"
                                        maxlength="2000"
                                        placeholder="Explain what the applicant needs to correct">{{ old('rejected_reason', $kyc_request->rejected_reason) }}</textarea>
                                    @error('rejected_reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <button class="btn btn-primary w-100" type="submit">
                                    Save review decision
                                </button>
                            </form>
                        </div>
                    </div>

                    @if ($kyc_request->rejected_reason)
                        <div class="kyc-note is-rejected">
                            <strong>Rejection reason</strong><br>
                            {{ $kyc_request->rejected_reason }}
                        </div>
                    @elseif ($kyc_request->review_notes)
                        <div class="kyc-note">
                            <strong>Reviewer notes</strong><br>
                            {{ $kyc_request->review_notes }}
                        </div>
                    @endif
                </aside>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const status = document.getElementById('kyc-status');
            const rejectionField = document.getElementById('rejection-reason-field');
            const rejectionReason = document.getElementById('rejected-reason');

            const syncRejectionField = () => {
                const isRejected = status.value === 'rejected';

                rejectionField.hidden = !isRejected;
                rejectionReason.required = isRejected;
            };

            status.addEventListener('change', syncRejectionField);
            syncRejectionField();
        });
    </script>
@endpush
