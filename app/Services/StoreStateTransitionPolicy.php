<?php

namespace App\Services;

use App\Models\Store;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class StoreStateTransitionPolicy
{
    public const ACTION_SUBMIT = 'submit';

    public const ACTION_APPROVE = 'approve';

    public const ACTION_REJECT = 'reject';

    public const ACTION_SUSPEND = 'suspend';

    public const ACTION_RESTORE = 'restore';

    /** @var array<string, list<string>> */
    private const ORIGINS = [
        self::ACTION_SUBMIT => [
            Store::STATUS_DRAFT,
            Store::STATUS_PENDING,
            Store::STATUS_REJECTED,
            Store::STATUS_APPROVED,
        ],
        self::ACTION_APPROVE => [Store::STATUS_PENDING],
        self::ACTION_REJECT => [Store::STATUS_PENDING],
        self::ACTION_SUSPEND => [Store::STATUS_APPROVED],
        self::ACTION_RESTORE => [Store::STATUS_SUSPENDED],
    ];

    public function assertAllowed(Store $store, string $action): void
    {
        if (! in_array($store->status, self::ORIGINS[$action] ?? [], true)) {
            throw new ConflictHttpException(sprintf(
                'Store transition %s -> %s is not allowed.',
                (string) $store->status,
                $action,
            ));
        }
    }

    /** @return array<string, list<string>> */
    public function allowedOrigins(): array
    {
        return self::ORIGINS;
    }
}
