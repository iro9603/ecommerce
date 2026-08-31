<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class ProductPricing
{
    /**
     * Resolve the effective public price for a product or variant.
     *
     * A special price is active only when it is not null and the current
     * date is within the optional start/end window. The same rules are used
     * by Product (which has temporal fields) and ProductVariant (which does
     * not, so its start/end are passed as null).
     *
     * @return array{
     *     regular_price: float|null,
     *     special_price: float|null,
     *     effective_price: float|null,
     *     has_active_special: bool
     * }
     */
    public static function resolve(
        ?float $regularPrice,
        ?float $specialPrice,
        ?Carbon $start,
        ?Carbon $end,
        ?Carbon $now = null,
    ): array {
        $now ??= Carbon::now();
        $regularPrice = $regularPrice !== null ? (float) $regularPrice : null;
        $specialPrice = $specialPrice !== null ? (float) $specialPrice : null;

        $hasActiveSpecial = $specialPrice !== null
            && ($start === null || $now->greaterThanOrEqualTo($start->startOfDay()))
            && ($end === null || $now->lessThanOrEqualTo($end->endOfDay()));

        $effectivePrice = $hasActiveSpecial ? $specialPrice : $regularPrice;

        return [
            'regular_price' => $regularPrice,
            'special_price' => $hasActiveSpecial ? $specialPrice : null,
            'effective_price' => $effectivePrice,
            'has_active_special' => $hasActiveSpecial,
        ];
    }
}
