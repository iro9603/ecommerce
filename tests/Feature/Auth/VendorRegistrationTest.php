<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

test('the vendor registration screen can be rendered', function () {
    $response = $this->get(route('vendor.register'));

    $response->assertStatus(200);
});

test('email verification redirects a vendor to the vendor dashboard', function () {
    $user = User::factory()->unverified()->create(['user_type' => 'vendor']);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);

    $response->assertRedirect(route('vendor.dashboard', absolute: false) . '?verified=1');
});

test('a vendor can register through the dedicated route', function () {
    $response = $this->post(route('vendor.register.store'), [
        'name' => 'Vendor Owner',
        'email' => 'vendor@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'vendor@example.com')->firstOrFail();

    expect($user->user_type)->toBe('vendor');

    // The controller redirects towards the vendor dashboard; the verified
    // middleware on that route will then send the new vendor to the email
    // verification screen on the follow-up request.
    $response->assertRedirect(route('vendor.dashboard', absolute: false));
});

test('the regular registration route always creates a customer account', function () {
    $response = $this->post(route('register'), [
        'name' => 'Regular Buyer',
        'email' => 'buyer@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        // A stale/injected user_type value must not promote the account to vendor.
        'user_type' => 'vendor',
    ]);

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'buyer@example.com')->firstOrFail();

    expect($user->user_type)->toBe('user');

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('the vendor registration screen does not render the user_type radio choose', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertDontSee('id="vendor"', false)
        ->assertSee('Register as a vendor')
        ->assertSee(route('vendor.register'));
});

test('vendors must provide all required fields on the dedicated route', function () {
    $this->post(route('vendor.register.store'), [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
    ])->assertSessionHasErrors(['name', 'email', 'password']);
});
