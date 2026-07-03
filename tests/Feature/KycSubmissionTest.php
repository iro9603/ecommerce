<?php

use App\Models\Kyc;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a verified user can submit kyc information', function () {
    Storage::fake('local');

    $user = User::factory()->create([
        'user_type' => 'vendor',
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('kyc.store'), [
            'full_name' => 'Test Vendor',
            'date_of_birth' => '03/12/2000',
            'gender' => 'prefer_not_to_say',
            'nationality' => 'MX',
            'address_line_1' => '123 Test Street',
            'city' => 'Guadalajara',
            'state' => 'Jalisco',
            'postal_code' => '44100',
            'country' => 'MX',
            'document_type' => 'id_card',
            'document_number' => 'TEST-123',
            'document_country' => 'MX',
            'document_expiry_date' => '03/12/2030',
            'document_front' => UploadedFile::fake()->image('front.jpg'),
            'document_back' => UploadedFile::fake()->image('back.jpg'),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('vendor.dashboard'));

    $kyc = Kyc::query()->where('user_id', $user->id)->firstOrFail();

    expect($kyc->status)->toBe('pending')
        ->and($kyc->full_name)->toBe('Test Vendor')
        ->and($kyc->country)->toBe('MX')
        ->and($kyc->document_front_path)->not->toBeNull()
        ->and($kyc->document_back_path)->not->toBeNull();

    Storage::disk('local')->assertExists($kyc->document_front_path);
    Storage::disk('local')->assertExists($kyc->document_back_path);
});
