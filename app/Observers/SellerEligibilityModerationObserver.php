<?php

namespace App\Observers;

use App\Models\User;
use App\Services\SellerEligibilityInvalidationService;

class SellerEligibilityModerationObserver
{
    private const FIELDS = ['email', 'email_verified_at', 'user_type'];

    public function __construct(
        private readonly SellerEligibilityInvalidationService $invalidation,
    ) {}

    public function updating(User $user): void
    {
        if (! $user->isDirty(self::FIELDS)) {
            return;
        }

        $user->eligibility_epoch = ((int) $user->getRawOriginal('eligibility_epoch')) + 1;
    }

    public function updated(User $user): void
    {
        if (! $user->wasChanged(self::FIELDS)) {
            return;
        }

        $changed = array_values(array_intersect(
            self::FIELDS,
            array_keys($user->getChanges()),
        ));
        $trigger = in_array('user_type', $changed, true)
            ? 'seller_user_type_changed'
            : (in_array('email', $changed, true)
                ? 'seller_email_changed'
                : 'seller_email_verification_changed');

        $this->invalidation->handle(
            $user,
            'Seller identity eligibility changed; prior trust generations were invalidated.',
            [
                'trigger' => $trigger,
                'changed_fields' => $changed,
                'previous_seller_user_type' => $user->getRawOriginal('user_type'),
                'observed_seller_user_type' => $user->user_type,
                'previous_email' => $user->getRawOriginal('email'),
                'observed_email' => $user->email,
            ],
        );
    }
}
