<?php

use App\Models\Admin;
use App\Models\Kyc;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('an authenticated admin can download each supported kyc document type', function () {
    Storage::fake('private');

    $admin = Admin::forceCreate([
        'name' => 'KYC Reviewer',
        'email' => 'reviewer@example.com',
        'email_verified_at' => now(),
        'password' => 'password',
    ]);
    $user = User::factory()->create();

    $paths = [
        'front' => "kyc/{$user->id}/front.pdf",
        'back' => "kyc/{$user->id}/back.pdf",
        'selfie' => "kyc/{$user->id}/selfie.jpg",
        'proof_of_address' => "kyc/{$user->id}/proof.pdf",
    ];

    foreach ($paths as $path) {
        Storage::disk('private')->put($path, 'test document');
    }

    $kyc = Kyc::forceCreate([
        'user_id' => $user->id,
        'full_name' => 'Test Vendor',
        'date_of_birth' => '2000-12-03',
        'gender' => 'prefer_not_to_say',
        'nationality' => 'MX',
        'address_line_1' => '123 Test Street',
        'city' => 'Guadalajara',
        'country' => 'MX',
        'document_type' => 'id_card',
        'document_number' => 'TEST-123',
        'document_country' => 'MX',
        'document_front_path' => $paths['front'],
        'document_back_path' => $paths['back'],
        'selfie_path' => $paths['selfie'],
        'proof_of_address_path' => $paths['proof_of_address'],
    ]);

    foreach ($paths as $type => $path) {
        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.kyc.download', [
                'kyc_request' => $kyc,
                'type' => $type,
            ]));

        $response
            ->assertOk()
            ->assertDownload('test-vendor-'.$type.'.'.pathinfo($path, PATHINFO_EXTENSION));
    }
});

test('the kyc download route rejects unsupported or missing documents', function () {
    Storage::fake('private');

    $admin = Admin::forceCreate([
        'name' => 'KYC Reviewer',
        'email' => 'reviewer@example.com',
        'email_verified_at' => now(),
        'password' => 'password',
    ]);
    $user = User::factory()->create();

    $kyc = Kyc::forceCreate([
        'user_id' => $user->id,
        'full_name' => 'Test Vendor',
        'date_of_birth' => '2000-12-03',
        'gender' => 'prefer_not_to_say',
        'nationality' => 'MX',
        'address_line_1' => '123 Test Street',
        'city' => 'Guadalajara',
        'country' => 'MX',
        'document_type' => 'passport',
        'document_number' => 'TEST-123',
        'document_country' => 'MX',
        'document_front_path' => "kyc/{$user->id}/missing.pdf",
    ]);

    $this
        ->actingAs($admin, 'admin')
        ->get(route('admin.kyc.download', [
            'kyc_request' => $kyc,
            'type' => 'front',
        ]))
        ->assertNotFound();

    $this
        ->actingAs($admin, 'admin')
        ->get("/admin/kyc-requests/{$kyc->id}/documents/tax_record/download")
        ->assertNotFound();
});
