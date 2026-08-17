@extends('vendor-dashboard.layouts.app')

@push('styles')
    <style>
        .store-branding-grid {
            align-items: stretch;
        }

        .store-upload-panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            height: 100%;
            padding: 1rem;
        }

        .store-upload-heading {
            align-items: center;
            display: flex;
            gap: .75rem;
            justify-content: space-between;
            margin-bottom: .75rem;
        }

        .store-upload-heading .form-label {
            margin-bottom: 0;
        }

        .store-upload-status {
            border-radius: 999px;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .02em;
            padding: .35rem .6rem;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .store-upload-status.is-uploaded {
            background: #dcfce7;
            color: #166534;
        }

        .store-upload-status.is-empty {
            background: #e2e8f0;
            color: #475569;
        }

        .store-upload-status.is-selected {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .store-upload-status.is-error {
            background: #fee2e2;
            color: #b91c1c;
        }

        .store-media-preview {
            align-items: center;
            background-color: #eef2f6;
            background-position: center;
            background-repeat: no-repeat;
            border: 2px dashed #b8c3cf;
            border-radius: .85rem;
            display: flex;
            justify-content: center;
            margin: 0 auto 1rem !important;
            overflow: hidden;
            position: relative;
            transition: border-color .2s ease, box-shadow .2s ease;
            width: 100%;
        }

        .store-media-preview:hover,
        .store-media-preview.is-dragging {
            border-color: var(--tblr-primary);
            box-shadow: 0 0 0 3px rgba(6, 111, 209, .1);
        }

        .store-logo-preview {
            aspect-ratio: 1;
            background-size: contain;
            max-width: 18rem;
        }

        .store-banner-preview {
            aspect-ratio: 16 / 6;
            background-size: cover;
            min-height: 13rem;
        }

        .store-media-preview input {
            cursor: pointer;
            inset: 0;
            opacity: 0;
            position: absolute;
            width: 100%;
            z-index: 2;
        }

        .store-media-preview label {
            backdrop-filter: blur(8px);
            background: rgba(15, 23, 42, .78);
            border-radius: 999px;
            bottom: .85rem;
            color: #fff;
            cursor: pointer;
            font-size: .75rem;
            font-weight: 700;
            left: 50%;
            max-width: calc(100% - 1.5rem);
            overflow: hidden;
            padding: .45rem .8rem;
            pointer-events: none;
            position: absolute;
            text-overflow: ellipsis;
            transform: translateX(-50%);
            white-space: nowrap;
            z-index: 1;
        }

        .store-media-preview.has-preview::after {
            background: linear-gradient(transparent, rgba(15, 23, 42, .28));
            bottom: 0;
            content: "";
            height: 35%;
            left: 0;
            pointer-events: none;
            position: absolute;
            right: 0;
        }

        .store-preview-meta {
            align-items: center;
            display: flex;
            gap: .55rem;
            justify-content: space-between;
        }

        .store-preview-filename {
            color: var(--tblr-secondary);
            font-size: .75rem;
            max-width: 70%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 767.98px) {
            .store-banner-preview {
                min-height: 10rem;
            }
        }
    </style>
@endpush

@section('contents')
    <div class="container-xl">
        @php
            $logoUploaded = filled($store?->logo);
            $bannerUploaded = filled($store?->banner);
        @endphp

        <div class="page-header d-print-none mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="page-title mb-1">Update Store Profile</h2>
                    <div class="text-muted">
                        Manage your store information, branding, address, SEO and social links.
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('vendor.store-profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row row-cards">

                {{-- Branding --}}
                <div class="col-12">
                    <div class="card">
                        @php
                            $status = $store->status;

                            $statusConfig = match ($status) {
                                'draft' => [
                                    'class' => 'bg-secondary-subtle text-secondary',
                                    'icon' => 'ti-file-pencil',
                                    'label' => 'Draft',
                                ],
                                'pending' => [
                                    'class' => 'bg-warning-subtle text-warning',
                                    'icon' => 'ti-clock-hour-4',
                                    'label' => 'Pending Review',
                                ],
                                'rejected' => [
                                    'class' => 'bg-danger-subtle text-danger',
                                    'icon' => 'ti-circle-x',
                                    'label' => 'Rejected',
                                ],
                                'active' => [
                                    'class' => 'bg-success-subtle text-success',
                                    'icon' => 'ti-circle-check',
                                    'label' => 'Active',
                                ],
                                'suspended' => [
                                    'class' => 'bg-danger-subtle text-danger',
                                    'icon' => 'ti-ban',
                                    'label' => 'Suspended',
                                ],
                                default => [
                                    'class' => 'bg-secondary-subtle text-secondary',
                                    'icon' => 'ti-help-circle',
                                    'label' => ucfirst($status),
                                ],
                            };
                        @endphp

                        <div class="card-header">
                            <div
                                class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 w-100">

                                <div>
                                    <h3 class="card-title mb-1">Branding</h3>
                                    <p class="card-subtitle text-muted mb-0">
                                        Upload your store logo and banner.
                                    </p>
                                </div>

                                <div class="text-md-end">
                                    <div class="text-muted small mb-1">
                                        Store status
                                    </div>

                                    <span class="badge {{ $statusConfig['class'] }} px-3 py-2">
                                        <i class="ti {{ $statusConfig['icon'] }} me-1"></i>
                                        {{ $statusConfig['label'] }}
                                    </span>
                                </div>

                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-4 store-branding-grid">
                                <div class="col-md-5">
                                    <div class="store-upload-panel">
                                        <div class="store-upload-heading">
                                            <label class="form-label">Logo</label>
                                            <span id="logo-status"
                                                class="store-upload-status {{ $logoUploaded ? 'is-uploaded' : 'is-empty' }}">
                                                {{ $logoUploaded ? 'Uploaded' : 'Not uploaded' }}
                                            </span>
                                        </div>

                                        <x-input-image id="logo-preview" name="logo"
                                            class="store-media-preview store-logo-preview" :image="$store?->logo ? asset($store->logo) : null" />

                                        <div class="store-preview-meta">
                                            <small class="form-hint m-0">Square image, up to 2 MB.</small>
                                            <span id="logo-filename" class="store-preview-filename">
                                                {{ $logoUploaded ? basename($store->logo) : 'No file selected' }}
                                            </span>
                                        </div>
                                    </div>

                                    <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                                </div>

                                <div class="col-md-7">
                                    <div class="store-upload-panel">
                                        <div class="store-upload-heading">
                                            <label class="form-label">Banner</label>
                                            <span id="banner-status"
                                                class="store-upload-status {{ $bannerUploaded ? 'is-uploaded' : 'is-empty' }}">
                                                {{ $bannerUploaded ? 'Uploaded' : 'Not uploaded' }}
                                            </span>
                                        </div>

                                        <x-input-image id="banner-preview" name="banner"
                                            class="store-media-preview store-banner-preview" :image="$store?->banner ? asset($store->banner) : null" />

                                        <div class="store-preview-meta">
                                            <small class="form-hint m-0">Recommended ratio 16:6, up to 4 MB.</small>
                                            <span id="banner-filename" class="store-preview-filename">
                                                {{ $bannerUploaded ? basename($store->banner) : 'No file selected' }}
                                            </span>
                                        </div>
                                    </div>

                                    <x-input-error :messages="$errors->get('banner')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Basic information --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Basic Information</h3>
                                <p class="card-subtitle">Main store details and contact information.</p>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-12">
                                    <label class="form-label required">Store Name</label>
                                    <input type="text" class="form-control" name="name" placeholder="Enter store name"
                                        value="{{ old('name', $store->name ?? '') }}">
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone"
                                        placeholder="Enter phone number" value="{{ old('phone', $store->phone ?? '') }}">
                                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email"
                                        placeholder="Enter email address" value="{{ old('email', $store->email ?? '') }}">
                                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                {{-- Descriptions --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Descriptions</h3>
                                <p class="card-subtitle">Describe your store for customers.</p>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-12">
                                    <label class="form-label">Short Description</label>
                                    <textarea name="short_description" class="form-control tinymce-editor" rows="5"
                                        placeholder="Write a brief description...">{{ old('short_description', $store->short_description ?? '') }}</textarea>

                                    <small class="form-hint">
                                        Maximum recommended length: 500 characters.
                                    </small>

                                    <x-input-error :messages="$errors->get('short_description')" class="mt-2" />
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Long Description</label>
                                    <textarea name="long_description" class="form-control tinymce-editor" rows="8"
                                        placeholder="Write a full description...">{{ old('long_description', $store->long_description ?? '') }}</textarea>

                                    <x-input-error :messages="$errors->get('long_description')" class="mt-2" />
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                {{-- Address --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Address</h3>
                                <p class="card-subtitle">Store location and shipping-related information.</p>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-12">
                                    <label class="form-label">Address Line 1</label>
                                    <input type="text" class="form-control" name="address_line_1"
                                        placeholder="Street, number, neighborhood"
                                        value="{{ old('address_line_1', $store->address_line_1 ?? '') }}">
                                    <x-input-error :messages="$errors->get('address_line_1')" class="mt-2" />
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Address Line 2</label>
                                    <input type="text" class="form-control" name="address_line_2"
                                        placeholder="Apartment, suite, references, etc."
                                        value="{{ old('address_line_2', $store->address_line_2 ?? '') }}">
                                    <x-input-error :messages="$errors->get('address_line_2')" class="mt-2" />
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" placeholder="City"
                                        value="{{ old('city', $store->city ?? '') }}">
                                    <x-input-error :messages="$errors->get('city')" class="mt-2" />
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">State</label>
                                    <input type="text" class="form-control" name="state" placeholder="State"
                                        value="{{ old('state', $store->state ?? '') }}">
                                    <x-input-error :messages="$errors->get('state')" class="mt-2" />
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Postal Code</label>
                                    <input type="text" class="form-control" name="postal_code"
                                        placeholder="Postal code"
                                        value="{{ old('postal_code', $store->postal_code ?? '') }}">
                                    <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                {{-- Regional settings --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Regional Settings</h3>
                                <p class="card-subtitle">Currency, country and timezone settings.</p>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-4">
                                    <label class="form-label">Currency</label>
                                    <select name="currency" class="form-select">
                                        <option value="MXN" @selected(old('currency', $store->currency ?? 'MXN') === 'MXN')>
                                            MXN - Mexican Peso
                                        </option>
                                        <option value="USD" @selected(old('currency', $store->currency ?? '') === 'USD')>
                                            USD - US Dollar
                                        </option>
                                    </select>
                                    <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Country</label>
                                    <select name="country" class="form-select">
                                        <option value="MX" @selected(old('country', $store->country ?? 'MX') === 'MX')>
                                            Mexico
                                        </option>
                                        <option value="US" @selected(old('country', $store->country ?? '') === 'US')>
                                            United States
                                        </option>
                                    </select>
                                    <x-input-error :messages="$errors->get('country')" class="mt-2" />
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Timezone</label>
                                    <select name="timezone" class="form-select">
                                        <option value="America/Mexico_City" @selected(old('timezone', $store->timezone ?? 'America/Mexico_City') === 'America/Mexico_City')>
                                            America/Mexico_City
                                        </option>
                                        <option value="America/Monterrey" @selected(old('timezone', $store->timezone ?? '') === 'America/Monterrey')>
                                            America/Monterrey
                                        </option>
                                        <option value="America/Tijuana" @selected(old('timezone', $store->timezone ?? '') === 'America/Tijuana')>
                                            America/Tijuana
                                        </option>
                                    </select>
                                    <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                {{-- SEO --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">SEO</h3>
                                <p class="card-subtitle">Improve how your store appears in search engines.</p>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-12">
                                    <label class="form-label">SEO Title</label>
                                    <input type="text" class="form-control" name="seo_title" placeholder="SEO title"
                                        value="{{ old('seo_title', $store->seo_title ?? '') }}">
                                    <small class="form-hint">
                                        Recommended length: 50–60 characters.
                                    </small>
                                    <x-input-error :messages="$errors->get('seo_title')" class="mt-2" />
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">SEO Description</label>
                                    <textarea name="seo_description" class="form-control" rows="3" placeholder="SEO description">{{ old('seo_description', $store->seo_description ?? '') }}</textarea>
                                    <small class="form-hint">
                                        Recommended length: 150–160 characters.
                                    </small>
                                    <x-input-error :messages="$errors->get('seo_description')" class="mt-2" />
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                {{-- Social links --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Social Links</h3>
                                <p class="card-subtitle">Add your store social media profiles.</p>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label">Facebook</label>
                                    <input type="url" class="form-control" name="social_links[facebook]"
                                        placeholder="https://facebook.com/yourstore"
                                        value="{{ old('social_links.facebook', $store->social_links['facebook'] ?? '') }}">
                                    <x-input-error :messages="$errors->get('social_links.facebook')" class="mt-2" />
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Instagram</label>
                                    <input type="url" class="form-control" name="social_links[instagram]"
                                        placeholder="https://instagram.com/yourstore"
                                        value="{{ old('social_links.instagram', $store->social_links['instagram'] ?? '') }}">
                                    <x-input-error :messages="$errors->get('social_links.instagram')" class="mt-2" />
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">TikTok</label>
                                    <input type="url" class="form-control" name="social_links[tiktok]"
                                        placeholder="https://tiktok.com/@yourstore"
                                        value="{{ old('social_links.tiktok', $store->social_links['tiktok'] ?? '') }}">
                                    <x-input-error :messages="$errors->get('social_links.tiktok')" class="mt-2" />
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">YouTube</label>
                                    <input type="url" class="form-control" name="social_links[youtube]"
                                        placeholder="https://youtube.com/@yourstore"
                                        value="{{ old('social_links.youtube', $store->social_links['youtube'] ?? '') }}">
                                    <x-input-error :messages="$errors->get('social_links.youtube')" class="mt-2" />
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Website</label>
                                    <input type="url" class="form-control" name="social_links[website]"
                                        placeholder="https://yourstore.com"
                                        value="{{ old('social_links.website', $store->social_links['website'] ?? '') }}">
                                    <x-input-error :messages="$errors->get('social_links.website')" class="mt-2" />
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-body d-flex justify-content-end gap-2">
                            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>

                            <button type="submit" class="btn btn-primary">
                                Update Store
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </form>

    </div>
@endsection

@push('scripts')
    <script>
        tinymce.init({
            selector: 'textarea.tinymce-editor',

            license_key: 'gpl',

            height: 400,

            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount'
            ],

            toolbar: 'undo redo | blocks | bold italic backcolor | ' +
                'alignleft aligncenter alignright alignjustify | ' +
                'bullist numlist outdent indent | link image media table | ' +
                'removeformat | code fullscreen preview | help',

            content_style: 'body { font-family: Helvetica, Arial, sans-serif; font-size: 16px }'
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const setupImagePreview = ({
                inputId,
                previewId,
                labelId,
                filenameId,
                statusId,
                emptyLabel,
                uploadedText = 'Uploaded',
                emptyText = 'Not uploaded',
                selectedText = 'Selected, save changes'
            }) => {
                const input = document.getElementById(inputId);
                const preview = document.getElementById(previewId);
                const label = document.getElementById(labelId);
                const filename = document.getElementById(filenameId);
                const status = document.getElementById(statusId);

                if (!input || !preview || !label || !filename) {
                    return;
                }

                const initialFilename = filename.textContent;
                const hasInitialPreview = Boolean(preview.style.backgroundImage);
                const setStatus = (state, text) => {
                    if (!status) {
                        return;
                    }

                    status.classList.remove('is-uploaded', 'is-empty', 'is-selected', 'is-error');
                    status.classList.add(`is-${state}`);
                    status.textContent = text;
                };

                if (hasInitialPreview) {
                    preview.classList.add('has-preview');
                    label.textContent = 'Change image';
                } else {
                    label.textContent = emptyLabel;
                }

                input.addEventListener('change', () => {
                    const file = input.files?.[0];

                    if (!file) {
                        label.textContent = hasInitialPreview ? 'Change image' : emptyLabel;
                        filename.textContent = hasInitialPreview ? initialFilename : 'No file selected';
                        setStatus(hasInitialPreview ? 'uploaded' : 'empty', hasInitialPreview ?
                            uploadedText :
                            emptyText);
                        return;
                    }

                    if (!file.type.startsWith('image/')) {
                        input.value = '';
                        filename.textContent = 'Invalid image file';
                        setStatus('error', 'Invalid file');
                        return;
                    }

                    const reader = new FileReader();

                    reader.addEventListener('load', (event) => {
                        preview.style.backgroundImage = `url("${event.target.result}")`;
                        preview.classList.add('has-preview');
                        label.textContent = 'Change image';
                        filename.textContent = file.name;
                        setStatus('selected', selectedText);
                    });

                    reader.readAsDataURL(file);
                });

                ['dragenter', 'dragover'].forEach((eventName) => {
                    input.addEventListener(eventName, () => preview.classList.add('is-dragging'));
                });

                ['dragleave', 'drop'].forEach((eventName) => {
                    input.addEventListener(eventName, () => preview.classList.remove('is-dragging'));
                });
            };

            setupImagePreview({
                inputId: 'logo-upload',
                previewId: 'logo-preview',
                labelId: 'logo-label',
                filenameId: 'logo-filename',
                statusId: 'logo-status',
                emptyLabel: 'Choose logo',
            });

            setupImagePreview({
                inputId: 'banner-upload',
                previewId: 'banner-preview',
                labelId: 'banner-label',
                filenameId: 'banner-filename',
                statusId: 'banner-status',
                emptyLabel: 'Choose banner',
            });
        });
    </script>
@endpush
