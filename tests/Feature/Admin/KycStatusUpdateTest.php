<?php

use App\Models\Admin;
use App\Models\Kyc;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->admin = Admin::forceCreate([
        'name' => 'KYC Reviewer',
        'email' => 'status-reviewer@example.com',
        'email_verified_at' => now(),
        'password' => 'password',
    ]);
    $this->admin->givePermissionTo(Permission::findOrCreate('KYC Management', 'admin'));

    $user = User::factory()->create();

    $this->kyc = Kyc::forceCreate([
        'user_id' => $user->id,
        'status' => 'pending',
        'submitted_at' => now(),
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
        'document_expiry_date' => now()->addYear()->toDateString(),
        'document_front_path' => 'kyc/test/front.pdf',
    ]);
});

test('an admin can mark a kyc request as under review', function () {
    $response = $this
        ->actingAs($this->admin, 'admin')
        ->put(route('admin.kyc.update', $this->kyc), [
            'status' => 'under_review',
            'review_notes' => 'Documents are being checked.',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.kyc.show', $this->kyc));

    $this->kyc->refresh();

    expect($this->kyc->status)->toBe('under_review')
        ->and($this->kyc->reviewed_by)->toBe($this->admin->id)
        ->and($this->kyc->review_notes)->toBe('Documents are being checked.')
        ->and($this->kyc->reviewed_at)->toBeNull()
        ->and($this->kyc->verified_at)->toBeNull();
});

test('approving a kyc request records the final review timestamps', function () {
    $this
        ->actingAs($this->admin, 'admin')
        ->put(route('admin.kyc.update', $this->kyc), [
            'status' => 'approved',
            'review_notes' => 'Identity matches the submitted document.',
        ])
        ->assertSessionHasNoErrors();

    $this->kyc->refresh();

    expect($this->kyc->status)->toBe('approved')
        ->and($this->kyc->reviewed_by)->toBe($this->admin->id)
        ->and($this->kyc->reviewed_at)->not->toBeNull()
        ->and($this->kyc->verified_at)->not->toBeNull()
        ->and($this->kyc->rejected_reason)->toBeNull();
});

test('rejecting a kyc request requires and stores a reason', function () {
    $this
        ->actingAs($this->admin, 'admin')
        ->from(route('admin.kyc.show', $this->kyc))
        ->put(route('admin.kyc.update', $this->kyc), [
            'status' => 'rejected',
        ])
        ->assertSessionHasErrors('rejected_reason')
        ->assertRedirect(route('admin.kyc.show', $this->kyc));

    expect($this->kyc->fresh()->status)->toBe('pending');

    $this
        ->actingAs($this->admin, 'admin')
        ->put(route('admin.kyc.update', $this->kyc), [
            'status' => 'rejected',
            'rejected_reason' => 'The document image is unreadable.',
        ])
        ->assertSessionHasNoErrors();

    $this->kyc->refresh();

    expect($this->kyc->status)->toBe('rejected')
        ->and($this->kyc->rejected_reason)->toBe('The document image is unreadable.')
        ->and($this->kyc->reviewed_at)->not->toBeNull()
        ->and($this->kyc->verified_at)->toBeNull();
});

test('an unsupported kyc status is rejected', function () {
    $this
        ->actingAs($this->admin, 'admin')
        ->put(route('admin.kyc.update', $this->kyc), [
            'status' => 'verified',
        ])
        ->assertSessionHasErrors('status');

    expect($this->kyc->fresh()->status)->toBe('pending');
});

test('an admin cannot approve a kyc request with an expired document', function () {
    $this->kyc->forceFill([
        'document_expiry_date' => now()->subDay()->toDateString(),
    ])->saveQuietly();

    $this
        ->actingAs($this->admin, 'admin')
        ->put(route('admin.kyc.update', $this->kyc), [
            'status' => 'approved',
        ])
        ->assertSessionHasErrors('document_expiry_date');

    expect($this->kyc->fresh()->status)->toBe('pending');
});
