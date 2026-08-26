<?php

namespace App\Observers;

use App\Models\Kyc;
use App\Services\SellerEligibilityInvalidationService;

class KycStatusModerationObserver
{
    public function __construct(
        private readonly SellerEligibilityInvalidationService $invalidation,
    ) {}

    public function updating(Kyc $kyc): void
    {
        if (! $kyc->isDirty(['status', 'document_expiry_date'])) {
            return;
        }

        $kyc->eligibility_epoch = ((int) $kyc->getRawOriginal('eligibility_epoch')) + 1;
        $kyc->expiration_reconciled_for = null;
    }

    public function updated(Kyc $kyc): void
    {
        if (! $kyc->wasChanged(['status', 'document_expiry_date'])) {
            return;
        }

        $previousStatus = (string) $kyc->getRawOriginal('status');
        $observedStatus = (string) $kyc->status;

        $seller = $kyc->user;

        if (! $seller) {
            return;
        }

        $this->invalidation->handle(
            $seller,
            sprintf(
                'Seller KYC eligibility inputs changed from status %s to %s; prior trust generations were invalidated.',
                $previousStatus,
                $observedStatus,
            ),
            [
                'trigger' => 'kyc_status_changed',
                'previous_kyc_status' => $previousStatus,
                'observed_kyc_status' => $observedStatus,
                'previous_kyc_expiry' => $kyc->getRawOriginal('document_expiry_date'),
                'observed_kyc_expiry' => $kyc->document_expiry_date?->format('Y-m-d'),
            ],
        );
    }
}
