<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Kyc;
use App\Services\AlertService;
use App\Traits\FileUploadTrait;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class KycController extends Controller
{
    use FileUploadTrait;

    public function index(): View | RedirectResponse
    {
        $kyc = auth('web')->user()->kyc;

        if ($kyc?->isEligibleAt() || $kyc?->status === 'pending') {
            return redirect()->route('vendor.dashboard');
        }

        return view('frontend.pages.kyc');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'max:255', 'string'],
            'date_of_birth' => ['required', 'date_format:d/m/Y'],
            'gender' => ['required', 'in:male,female,other,prefer_not_to_say'],
            'nationality' => ['required', 'alpha', 'size:2'],
            'address_line_1' => ['required', 'max:255', 'string'],
            'address_line_2' => ['nullable', 'max:255', 'string'],
            'city' => ['required', 'max:255', 'string'],
            'state' => ['nullable', 'max:255', 'string'],
            'postal_code' => ['nullable', 'max:20', 'string'],
            'country' => ['required', 'alpha', 'size:2'],
            'document_type' => ['required', 'in:id_card,passport,driving_license'],
            'document_number' => ['required', 'max:255', 'string'],
            'document_country' => ['required', 'alpha', 'size:2'],
            'document_expiry_date' => ['required', 'date_format:d/m/Y', 'after_or_equal:today'],
            'document_front' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10000'],
            'document_back' => [
                'required_unless:document_type,passport',
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:10000',
            ],
            'selfie' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10000'],
            'proof_of_address' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10000'],
        ]);

        $userId = $request->user()->id;

        if (Kyc::query()->where('user_id', $userId)->exists()) {
            return back()->withErrors([
                'kyc' => 'You have already submitted your KYC information.',
            ]);
        }

        $directory = "kyc/{$userId}";
        $uploadedPaths = [];

        try {
            $uploadedPaths['document_front_path'] = $this->uploadPrivateFile(
                $request->file('document_front'),
                path: $directory,
            );

            foreach (
                [
                    'document_back' => 'document_back_path',
                    'selfie' => 'selfie_path',
                    'proof_of_address' => 'proof_of_address_path',
                ] as $input => $column
            ) {
                if ($request->hasFile($input)) {
                    $uploadedPaths[$column] = $this->uploadPrivateFile(
                        $request->file($input),
                        path: $directory,
                    );
                }
            }

            if (! $uploadedPaths['document_front_path']) {
                throw new \RuntimeException('The identity document could not be stored.');
            }

            $kyc = new Kyc;
            $kyc->user_id = $userId;
            $kyc->status = 'pending';
            $kyc->submitted_at = now();
            $kyc->full_name = $validated['full_name'];
            $kyc->date_of_birth = Carbon::createFromFormat('d/m/Y', $validated['date_of_birth']);
            $kyc->gender = $validated['gender'];
            $kyc->nationality = strtoupper($validated['nationality']);
            $kyc->address_line_1 = $validated['address_line_1'];
            $kyc->address_line_2 = $validated['address_line_2'] ?? null;
            $kyc->city = $validated['city'];
            $kyc->state = $validated['state'] ?? null;
            $kyc->postal_code = $validated['postal_code'] ?? null;
            $kyc->country = strtoupper($validated['country']);
            $kyc->document_type = $validated['document_type'];
            $kyc->document_number = $validated['document_number'];
            $kyc->document_country = strtoupper($validated['document_country']);
            $kyc->document_expiry_date = isset($validated['document_expiry_date'])
                ? Carbon::createFromFormat('d/m/Y', $validated['document_expiry_date'])
                : null;

            foreach ($uploadedPaths as $column => $path) {
                $kyc->{$column} = $path;
            }

            $kyc->save();
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(array_filter($uploadedPaths));
            report($exception);

            return back()->withErrors([
                'kyc' => 'We could not save your KYC information. Please try again.',
            ]);
        }

        AlertService::created('Your KYC has been submitted successfully! Please wait for admin approval.');

        return redirect()
            ->route('vendor.dashboard');
    }
}
