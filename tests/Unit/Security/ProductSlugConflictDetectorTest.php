<?php

use App\Services\ProductSlugConflictDetector;
use Illuminate\Database\QueryException;

function uniqueQueryException(string $message, string $sqlState = '23000'): QueryException
{
    $previous = new PDOException($message, (int) $sqlState);
    $previous->errorInfo = [$sqlState, 1062, $message];

    return new QueryException('mysql', 'update products set slug = ?', ['collision'], $previous);
}

test('it recognizes only the named product slug unique constraint', function () {
    $detector = new ProductSlugConflictDetector;

    expect($detector->causedByProductSlug(uniqueQueryException(
        "Duplicate entry 'collision' for key 'products.products_slug_unique'",
    )))->toBeTrue()
        ->and($detector->causedByProductSlug(uniqueQueryException(
            "Duplicate entry 'email@example.com' for key 'users.users_email_unique'",
        )))->toBeFalse();
});

test('it recognizes the equivalent sqlite product slug constraint message', function () {
    $detector = new ProductSlugConflictDetector;

    expect($detector->causedByProductSlug(uniqueQueryException(
        'UNIQUE constraint failed: products.slug',
        '19',
    )))->toBeTrue();
});
