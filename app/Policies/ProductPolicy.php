<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Verified vendors may author products while their store is onboarding.
     * Publication and automatic approval still require an active store.
     */
    public function viewAny(User $user): bool
    {
        return $this->canManageProducts($user);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->ownsProduct($user, $product);
    }

    public function create(User $user): bool
    {
        return $this->canManageProducts($user);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->ownsProduct($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->ownsProduct($user, $product);
    }

    public function restore(User $user, Product $product): bool
    {
        return $this->ownsProduct($user, $product);
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $this->ownsProduct($user, $product);
    }

    public function uploadImages(User $user, Product $product): bool
    {
        return $this->ownsProduct($user, $product);
    }

    public function reorderImages(User $user, Product $product): bool
    {
        return $this->ownsProduct($user, $product);
    }

    public function manageDigitalFiles(User $user, Product $product): bool
    {
        return $product->product_type === 'digital'
            && $this->ownsProduct($user, $product);
    }

    public function manageAttributes(User $user, Product $product): bool
    {
        return $this->ownsProduct($user, $product);
    }

    public function manageVariants(User $user, Product $product): bool
    {
        return $this->ownsProduct($user, $product);
    }

    private function ownsProduct(User $user, Product $product): bool
    {
        if (! $this->canManageProducts($user)) {
            return false;
        }

        return (int) $product->store_id === (int) $user->store->getKey();
    }

    private function canManageProducts(User $user): bool
    {
        if ($user->user_type !== 'vendor') {
            return false;
        }

        $user->loadMissing(['kyc', 'store']);

        return $user->kyc?->status === 'approved'
            && $user->email_verified_at !== null
            && in_array($user->store?->status, ['draft', 'pending', 'approved'], true)
            && $user->store?->suspended_at === null;
    }
}
