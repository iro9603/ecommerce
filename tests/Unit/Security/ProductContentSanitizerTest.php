<?php

use App\Services\ProductContentSanitizer;

test('it removes stored XSS payloads that try to escape a textarea', function () {
    $payload = '</textarea><img src=x onerror=alert(1)><script>alert(2)</script>'
        .'<p onclick=alert(3)>Safe <strong>description</strong></p>';

    $sanitized = (new ProductContentSanitizer)->sanitize($payload);
    $lowercase = strtolower((string) $sanitized);

    expect($lowercase)->not->toContain('</textarea')
        ->not->toContain('<img')
        ->not->toContain('<script')
        ->not->toContain('onerror')
        ->not->toContain('onclick')
        ->and($sanitized)->toBe('<p>Safe <strong>description</strong></p>');
});

test('it strips executable attributes from otherwise valid rich text', function () {
    $sanitized = (new ProductContentSanitizer)->sanitize(
        '<p onclick=alert(1)>Safe <strong style=color:red>description</strong></p>'
    );

    expect($sanitized)->toContain('<p>Safe <strong>description</strong></p>')
        ->not->toContain('onclick')
        ->not->toContain('style=');
});

test('it preserves null and empty content', function () {
    $sanitizer = new ProductContentSanitizer;

    expect($sanitizer->sanitize(null))->toBeNull()
        ->and($sanitizer->sanitize(''))->toBe('');
});
