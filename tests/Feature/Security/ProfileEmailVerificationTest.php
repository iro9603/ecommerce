<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

test('changing an email clears verification and sends a new verification notification', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'verified@example.test',
        'email_verified_at' => now(),
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('profile.update'), [
            'name' => 'Verified User',
            'email' => 'new-address@example.test',
        ]);

    $response->assertRedirect();

    $user->refresh();

    expect($user->email)->toBe('new-address@example.test')
        ->and($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('keeping the same email preserves verification and sends no notification', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this
        ->actingAs($user)
        ->put(route('profile.update'), [
            'name' => 'Renamed User',
            'email' => $user->email,
        ])
        ->assertRedirect();

    expect($user->fresh()->email_verified_at)->not->toBeNull();

    Notification::assertNothingSent();
});
