@extends('frontend.layouts.app')

@section('contents')
    <x-frontend.breadcrumb :items="[
        [
            'label' => 'Home',
            'url' => '/',
        ],
        [
            'label' => 'Login',
        ],
    ]" />
    <div class="page-content pt-150 pb-135">
        <div class="container">
            <div class="row">
                <div class="col-xl-8 col-lg-10 col-md-12 m-auto">
                    <div class="row">
                        <div class="col-lg-6 col-md-8 offset-lg-3">
                            <!-- Session Status -->
                            <x-auth-session-status class="mb-4" :status="session('status')" />
                            <div class="login_wrap widget-taber-content background-white">
                                <div class="padding_eight_all bg-white">
                                    <div class="heading_s1 mb-4">
                                        <h4 class="mb-5">Kyc Verification</h4>
                                    </div>
                                    <form method="post" action="{{ route('kyc.store') }}" enctype="multipart/form-data">
                                        @csrf

                                        <x-input-error :messages="$errors->get('kyc')" class="mb-3" />

                                        <div class="form-group">
                                            <label for="full_name" class="font-weight-bold">Full Name <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" id="full_name" name="full_name"
                                                value="{{ old('full_name', auth()->user()->name) }}" required autofocus />
                                            <x-input-error :messages="$errors->get('full_name')" class="mt-2" />
                                        </div>
                                        <div class="form-group">
                                            <label for="date_of_birth" class="font-weight-bold">Date of Birth <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" id="date_of_birth" name="date_of_birth"
                                                value="{{ old('date_of_birth') }}" placeholder="03/12/2000" required
                                                class="datepicker" />
                                            <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
                                        </div>
                                        <div class="form-group">
                                            <label for="gender" class="font-weight-bold">Gender <span
                                                    class="text-danger">*</span></label>
                                            <select name="gender" id="gender" class="form-control" required>
                                                <option value="">Select</option>
                                                <option value="male" @selected(old('gender') === 'male')>Male</option>
                                                <option value="female" @selected(old('gender') === 'female')>Female</option>
                                                <option value="other" @selected(old('gender') === 'other')>Other</option>
                                                <option value="prefer_not_to_say" @selected(old('gender') === 'prefer_not_to_say')>
                                                    Prefer not to say
                                                </option>
                                            </select>
                                            <x-input-error :messages="$errors->get('gender')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="nationality" class="font-weight-bold">Nationality code <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" id="nationality" name="nationality"
                                                value="{{ old('nationality') }}" maxlength="2" placeholder="MX" required />
                                            <x-input-error :messages="$errors->get('nationality')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="address_line_1" class="font-weight-bold">Address <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" id="address_line_1" name="address_line_1"
                                                value="{{ old('address_line_1') }}" required />
                                            <x-input-error :messages="$errors->get('address_line_1')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="address_line_2" class="font-weight-bold">Address line 2</label>
                                            <input type="text" id="address_line_2" name="address_line_2"
                                                value="{{ old('address_line_2') }}" />
                                            <x-input-error :messages="$errors->get('address_line_2')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="city" class="font-weight-bold">City <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" id="city" name="city" value="{{ old('city') }}" required />
                                            <x-input-error :messages="$errors->get('city')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="state" class="font-weight-bold">State</label>
                                            <input type="text" id="state" name="state" value="{{ old('state') }}" />
                                            <x-input-error :messages="$errors->get('state')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="postal_code" class="font-weight-bold">Postal code</label>
                                            <input type="text" id="postal_code" name="postal_code"
                                                value="{{ old('postal_code') }}" maxlength="20" />
                                            <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="country" class="font-weight-bold">Country code <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" id="country" name="country" value="{{ old('country') }}"
                                                maxlength="2" placeholder="MX" required />
                                            <x-input-error :messages="$errors->get('country')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="document_type" class="font-weight-bold">Document Type <span
                                                    class="text-danger">*</span></label>
                                            <select name="document_type" id="document_type" class="form-control" required>
                                                <option value="">Select</option>
                                                <option value="id_card" @selected(old('document_type') === 'id_card')>ID Card</option>
                                                <option value="passport" @selected(old('document_type') === 'passport')>Passport</option>
                                                <option value="driving_license" @selected(old('document_type') === 'driving_license')>
                                                    Driving license
                                                </option>
                                            </select>
                                            <x-input-error :messages="$errors->get('document_type')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="document_number" class="font-weight-bold">Document number <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" id="document_number" name="document_number"
                                                value="{{ old('document_number') }}" required />
                                            <x-input-error :messages="$errors->get('document_number')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="document_country" class="font-weight-bold">Issuing country code
                                                <span class="text-danger">*</span></label>
                                            <input type="text" id="document_country" name="document_country"
                                                value="{{ old('document_country') }}" maxlength="2" placeholder="MX"
                                                required />
                                            <x-input-error :messages="$errors->get('document_country')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="document_expiry_date" class="font-weight-bold">Document expiry
                                                date</label>
                                            <input type="text" id="document_expiry_date" name="document_expiry_date"
                                                value="{{ old('document_expiry_date') }}" placeholder="03/12/2030"
                                                class="datepicker" />
                                            <x-input-error :messages="$errors->get('document_expiry_date')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="document_front">Document Front <span
                                                    class="text-danger">*</span></label>
                                            <input type="file" id="document_front" name="document_front"
                                                accept="image/jpeg,image/png,application/pdf" required>
                                            <x-input-error :messages="$errors->get('document_front')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="document_back">Document back</label>
                                            <input type="file" id="document_back" name="document_back"
                                                accept="image/jpeg,image/png,application/pdf">
                                            <x-input-error :messages="$errors->get('document_back')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="selfie">Selfie</label>
                                            <input type="file" id="selfie" name="selfie"
                                                accept="image/jpeg,image/png">
                                            <x-input-error :messages="$errors->get('selfie')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <label for="proof_of_address">Proof of address</label>
                                            <input type="file" id="proof_of_address" name="proof_of_address"
                                                accept="image/jpeg,image/png,application/pdf">
                                            <x-input-error :messages="$errors->get('proof_of_address')" class="mt-2" />
                                        </div>

                                        <div class="form-group">
                                            <button type="submit" class="btn btn-heading btn-block hover-up"
                                                name="">Submit</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
