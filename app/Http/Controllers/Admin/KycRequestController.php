<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kyc;
use App\Services\AlertService;
use App\Services\MailService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycRequestController extends Controller implements HasMiddleware
{
    public $subject;
    public $body;

    static function Middleware(): array
    {
        return [
            new Middleware('permission:KYC Management')

        ];
    }

    public function index(): View
    {
        $kycRequests = Kyc::query()
            ->with('user')
            ->latest('submitted_at')
            ->paginate(25);

        return view('admin.kyc.index', compact('kycRequests'));
    }

    public function pending(): View
    {
        $kycRequests = Kyc::query()
            ->with('user')
            ->where('status', 'pending')
            ->latest('submitted_at')
            ->paginate(25);

        return view('admin.kyc.pending', compact('kycRequests'));
    }

    public function rejected(): View
    {
        $kycRequests = Kyc::query()
            ->with('user')
            ->where('status', 'rejected')
            ->latest('submitted_at')
            ->paginate(25);

        return view('admin.kyc.rejected', compact('kycRequests'));
    }

    public function show(Kyc $kyc_request): View
    {
        $kyc_request->load(['user', 'reviewer']);

        $documents = [
            [
                'label' => 'Document front',
                'type' => 'front',
                'description' => 'Primary identity image',
                'path' => $kyc_request->document_front_path,
                'required' => true,
            ],
            [
                'label' => 'Document back',
                'type' => 'back',
                'description' => 'Reverse side of the document',
                'path' => $kyc_request->document_back_path,
                'required' => $kyc_request->document_type !== 'passport',
            ],
            [
                'label' => 'Identity selfie',
                'type' => 'selfie',
                'description' => 'Applicant identity confirmation',
                'path' => $kyc_request->selfie_path,
                'required' => false,
            ],
            [
                'label' => 'Proof of address',
                'type' => 'proof_of_address',
                'description' => 'Residential address evidence',
                'path' => $kyc_request->proof_of_address_path,
                'required' => false,
            ],
        ];

        return view('admin.kyc.show', compact('kyc_request', 'documents'));
    }

    public function download(Kyc $kyc_request, string $type): StreamedResponse
    {
        $column = match ($type) {
            'front' => 'document_front_path',
            'back' => 'document_back_path',
            'selfie' => 'selfie_path',
            'proof_of_address' => 'proof_of_address_path',
            default => abort(404),
        };

        $path = $kyc_request->{$column};

        if (! $path || ! Storage::disk('private')->exists($path)) {
            abort(404, 'File not found.');
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $filename = Str::slug($kyc_request->full_name ?: 'kyc-request') . '-' . $type;

        if ($extension) {
            $filename .= '.' . $extension;
        }

        return Storage::disk('private')->download($path, $filename);
    }

    public function update(Kyc $kyc_request, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'approved', 'rejected'])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'rejected_reason' => [
                Rule::requiredIf($request->input('status') === 'rejected'),
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $status = $validated['status'];

        $kyc_request->status = $status;
        $kyc_request->reviewed_by = $request->user('admin')->id;
        $kyc_request->review_notes = $validated['review_notes'] ?? null;

        if ($status === 'under_review') {
            $kyc_request->reviewed_at = null;
            $kyc_request->verified_at = null;
            $kyc_request->rejected_reason = null;
        }

        if ($status === 'approved') {
            $kyc_request->reviewed_at = now();
            $kyc_request->verified_at = now();
            $kyc_request->rejected_reason = null;
        }

        if ($status === 'rejected') {
            $kyc_request->reviewed_at = now();
            $kyc_request->verified_at = null;
            $kyc_request->rejected_reason = $validated['rejected_reason'];
        }

        $kyc_request->save();


        if ($status === 'approved') {
            $this->subject = 'KYC Application has been approved.';
            $this->body = 'Congratulations! Your KYC Application has been approved.';
        } elseif ($status === 'rejected') {
            $this->subject = 'KYC Application has been rejected.';
            $this->body = 'Sorry! Your KYC Application has been rejected.';
        }

        MailService::send(
            to: $kyc_request->user->email,
            subject: $this->subject,
            body: $this->body
        );

        AlertService::updated('KYC status updated successfully.');

        return redirect()->route('admin.kyc.show', $kyc_request);
    }
}
