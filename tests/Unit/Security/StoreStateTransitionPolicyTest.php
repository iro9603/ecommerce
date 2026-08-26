<?php

use App\Models\Store;
use App\Services\StoreStateTransitionPolicy;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

test('store moderation transitions have explicit allowed origins', function () {
    $policy = new StoreStateTransitionPolicy;

    expect($policy->allowedOrigins())->toBe([
        StoreStateTransitionPolicy::ACTION_SUBMIT => ['draft', 'pending', 'rejected', 'approved'],
        StoreStateTransitionPolicy::ACTION_APPROVE => ['pending'],
        StoreStateTransitionPolicy::ACTION_REJECT => ['pending'],
        StoreStateTransitionPolicy::ACTION_SUSPEND => ['approved'],
        StoreStateTransitionPolicy::ACTION_RESTORE => ['suspended'],
    ]);
});

test('invalid store moderation transitions fail with a conflict', function (string $origin, string $action) {
    $store = new Store;
    $store->status = $origin;

    expect(fn () => (new StoreStateTransitionPolicy)->assertAllowed($store, $action))
        ->toThrow(ConflictHttpException::class);
})->with([
    'draft cannot restore' => ['draft', StoreStateTransitionPolicy::ACTION_RESTORE],
    'rejected cannot suspend' => ['rejected', StoreStateTransitionPolicy::ACTION_SUSPEND],
    'approved cannot be approved again' => ['approved', StoreStateTransitionPolicy::ACTION_APPROVE],
    'suspended cannot bypass restore through submit' => ['suspended', StoreStateTransitionPolicy::ACTION_SUBMIT],
]);
