<?php

namespace App\Observers;

use App\Models\User;
use App\Services\SellerTypeRevalidationService;

class SellerTypeModerationObserver
{
    public function __construct(
        private readonly SellerTypeRevalidationService $revalidation,
    ) {}

    public function updated(User $user): void
    {
        if (! $user->wasChanged('user_type')) {
            return;
        }

        $this->revalidation->handle(
            $user,
            (string) $user->getRawOriginal('user_type'),
        );
    }
}
