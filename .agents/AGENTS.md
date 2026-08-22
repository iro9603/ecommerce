# Ecommerce Laravel

## Environment

- Laravel 13
- PHP 8.5
- MySQL
- Laravel Sail

## Commands

Run PHP commands through Sail:

./vendor/bin/sail artisan
./vendor/bin/sail test

## Engineering rules

- Never modify historical migrations unless explicitly justified.
- Preserve existing user changes.
- Do not use mass assignment with request->all().
- Product mutations must respect ProductModerationService.
- Public Product queries must respect Product::published().
- Use transactions and lockForUpdate() for sensitive moderation mutations.
- Never expose vendor-controlled moderation fields.
- Add regression tests for security-sensitive changes.
- Do not mark a task complete until relevant tests have been executed.