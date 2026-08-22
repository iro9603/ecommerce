<?php

namespace App\Observers;

use App\Models\Kyc;
use App\Services\SellerSecurityRevalidationService;

class KycStatusModerationObserver
{
    public function __construct(
        private readonly SellerSecurityRevalidationService $revalidation,
    ) {}

    public function updated(Kyc $kyc): void
    {
        if (! $kyc->wasChanged('status')) {
            return;
        }

        $previousStatus = (string) $kyc->getRawOriginal('status');
        $observedStatus = (string) $kyc->status;

        if ($previousStatus !== 'approved' || $observedStatus === 'approved') {
            return;
        }

        $seller = $kyc->user;

        if (! $seller || $seller->user_type !== 'vendor') {
            return;
        }

        $this->revalidation->handle(
            $seller,
            sprintf(
                'Seller KYC status changed from %s to %s; store trust and existing product reviews were invalidated.',
                $previousStatus,
                $observedStatus,
            ),
            [
                'trigger' => 'kyc_status_changed',
                'previous_kyc_status' => $previousStatus,
                'observed_kyc_status' => $observedStatus,
            ],
        );
    }
}
