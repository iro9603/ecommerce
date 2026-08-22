<?php

namespace App\Observers;

use App\Models\User;
use App\Services\SellerSecurityRevalidationService;

class SellerEmailModerationObserver
{
    public function __construct(
        private readonly SellerSecurityRevalidationService $revalidation,
    ) {}

    public function updated(User $user): void
    {
        if (! $user->wasChanged('email') || $user->user_type !== 'vendor') {
            return;
        }

        $previousEmail = (string) $user->getRawOriginal('email');
        $observedEmail = (string) $user->email;

        $this->revalidation->handle(
            $user,
            sprintf(
                'Seller primary email changed from %s to %s; store trust and existing product reviews were invalidated.',
                $previousEmail,
                $observedEmail,
            ),
            [
                'trigger' => 'seller_email_changed',
                'previous_email' => $previousEmail,
                'observed_email' => $observedEmail,
            ],
        );
    }
}
