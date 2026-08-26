<?php

namespace App\Services;

use Illuminate\Database\QueryException;

class ProductSlugConflictDetector
{
    public function causedByProductSlug(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        if (! in_array($sqlState, ['23000', '23505', '19'], true)) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'products_slug_unique')
            || (
                str_contains($message, 'unique constraint failed')
                && str_contains($message, 'products.slug')
            );
    }
}
