# Store & Product Moderation Refactor - Implementation Record

This document records the security-sensitive architectural refactor applied to
Store Moderation and Product Moderation. It is the implementation counterpart
to `docs/tasks/store-product-moderation-refactor.md`.

## 1. Purpose

The refactor makes Store and Product moderation independent, versioned,
auditable, and fail-closed:

- A Store approval represents one exact Store profile version.
- A Product approval represents one exact Product content version.
- Seller/Store eligibility is recorded separately from Product content.
- Publication requires current Product content approval **and** current Store
  profile approval **and** current seller eligibility.
- Store status changes do not churn Product versions.
- Seller eligibility losses revoke trust and invalidate prior Product approvals.

## 2. Actual pre-refactor findings

The implementation did not match the target documentation in these areas:

1. Store moderation had no version, fingerprint, history, or expected-version
   check.
2. Product fingerprints included Store status, suspension, auto-approval, seller
   type, email verification, and KYC.
3. KYC and email changes did not revoke Store trust or invalidate Product reviews.
4. Store status changes mutated Product approval state, coupling the two state
   machines.
5. `Product::published()` did not require the current Store moderation version.
6. Brand/Category/Tag changes only queued remoderation; they did not synchronously
   close publication.
7. Store updates always submitted for review, even when no material field changed.
8. Digital files had no SHA-256 and were assembled/stored while holding a Product
   database lock.
9. Vendor/admin product-type GET routes accepted invalid types.
10. Product and Store restore paths could restore stale approval/publication state.

## 3. Implemented architecture

### 3.1 Store moderation versioning

New Store columns:

```text
stores.moderation_version
stores.reviewed_version
stores.submitted_at
stores.moderation_fingerprint
stores.moderation_reason
```

New audit table:

```text
store_approval_reviews
```

`StoreModerationService` now:

- builds a material Store content snapshot and SHA-256 fingerprint;
- creates a new pending version only when a material field changes;
- marks older pending reviews as `superseded`;
- requires `expectedVersion` for approve, reject, suspend, and restore;
- returns `null` on a stale decision, allowing the controller to return `409`;
- appends a new history row for every administrative decision;
- does not invalidate Product content approvals when Store status changes.

Material Store fields include name, slug, logo, banner, phone, email,
descriptions, address, currency, country, SEO fields, social links, and seller
ID. Timezone is non-material.

### 3.2 Product content vs eligibility context

`ProductModerationService` now separates:

- **Content snapshot**: product commercial data, brand/category/tag definitions,
  images, digital files including `sha256`, attributes, values, and variants.
- **Evaluation context**: Store status, suspension, auto-approval, seller type,
  email verification, KYC, and global automatic-approval switch.

`products.moderation_fingerprint` and `product_approval_reviews.content_hash`
hash only content.

`product_approval_reviews.evaluation_context` and `context_hash` audit the
eligibility conditions under which a decision occurred.

`forceRevalidate()` creates a new Product review version even when content is
unchanged. It is used for seller-type, KYC, and email eligibility losses.

`reevaluatePendingStoreContext()` re-evaluates pending Products at their current
version when Store trust/status context changes, without duplicating content
versions.

### 3.3 KYC, email, and seller-type revalidation

New observers and service:

```text
KycStatusModerationObserver
SellerEmailModerationObserver
SellerSecurityRevalidationService
```

When eligibility is lost:

1. lock seller, then Stores;
2. revoke `auto_approve_products`;
3. synchronously force approved Products to pending and clear their decision;
4. write a `store_auto_approval_audits` row with trigger information;
5. call `forceRevalidate()` for affected approved/pending Products.

Returning to an eligible state does not restore trust or prior approval.

### 3.4 Synchronous Brand/Category/Tag invalidation

`ProductReferenceModerationObserver` now performs two phases:

1. Synchronous: previously approved affected Products are forced to pending and
   their approval fields are cleared before the worker runs.
2. Asynchronous: batches of 250 dispatch
   `ReevaluateProductsAfterReferenceChange` to rebuild snapshots and versions.

### 3.5 Publication contract

`Product::published()` now requires:

- Product `approved_status = approved`
- Product `status = active`
- Product `moderation_version > 0`
- Product `reviewed_version = moderation_version`
- Store `status = approved`
- Store not suspended
- Store `moderation_version > 0`
- Store `reviewed_version = moderation_version`
- Seller is vendor
- Seller email verified
- Seller KYC approved

The scope remains local and is used by Home and Product Catalog. Admin/vendor
queries intentionally do not use it.

### 3.6 Digital files

- `product_files.sha256` stores the SHA-256 of the final assembled file.
- Hash is included in the Product content snapshot.
- `ProductRiskEvaluator` blocks auto-approval when a digital file has no hash.
- Vendor/admin digital controllers now perform chunk assembly, MIME detection,
  storage write, and hashing **outside** the Product database transaction.
- A short transaction only persists the `ProductFile` and calls
  `markForReview()`.
- `products:backfill-file-hashes` backfills legacy rows.

### 3.7 Soft deletes and restores

`ProductSoftDeleteModerationObserver` and
`StoreSoftDeleteModerationObserver` clear approval state on delete/restore so a
restored record cannot return to public state without review.

Brand and Tag remain hard-delete and have no restore risk today.

### 3.8 Product-type routes

Vendor and admin `GET /products/{type}/create` now use
`whereIn('type', ['physical', 'digital'])`, so invalid types return `404`.

## 4. Migrations

New migrations only. No historical migration was changed.

```text
2026_08_18_000001_add_store_moderation_columns_to_stores.php
2026_08_18_000002_create_store_approval_reviews_table.php
2026_08_18_000003_add_product_evaluation_context.php
2026_08_18_000004_add_sha256_to_product_files_table.php
```

Fail-closed data behavior:

- Existing approved Stores are moved to `pending`.
- Existing approved Products are moved to `pending`.
- No synthetic review history is invented.
- Existing product reviews remain as historical rows.

## 5. Files changed

### New

- `app/Models/StoreApprovalReview.php`
- `app/Services/SellerSecurityRevalidationService.php`
- `app/Jobs/EvaluateProductApprovalContext.php`
- `app/Observers/KycStatusModerationObserver.php`
- `app/Observers/SellerEmailModerationObserver.php`
- `app/Observers/ProductSoftDeleteModerationObserver.php`
- `app/Observers/StoreSoftDeleteModerationObserver.php`
- `app/Console/Commands/BackfillProductFileHashes.php`
- The four migrations listed above.

### Modified

- Store models/controllers/services/views/routes
- Product models/services/risk/jobs/observers/controllers/routes
- Digital product upload/delete controllers and upload service
- `AppServiceProvider`
- Test fixtures and security tests

## 6. Tests

Focused security suite:

```text
tests/Feature/Security tests/Unit/Security
77 passed, 492 assertions
```

Admin slug regression:

```text
tests/Feature/Admin/ProductSlugUpdateTest.php
4 passed, 13 assertions
```

Migration dry run:

```text
./vendor/bin/sail artisan migrate --pretend --no-interaction
```

Full suite:

```text
110 passed, 20 failed, 623 assertions
```

The 20 full-suite failures are pre-existing and unrelated to this refactor:
Category/KYC permission fixtures, Breeze password/profile route mismatches, and
vendor registration copy.

## 7. Deployment checklist

1. Pause queue workers before applying migrations.
2. Run `migrate --pretend`, then `migrate --force`.
3. Run `products:backfill-file-hashes`.
4. Re-approve affected Stores and Products because migrations reset approvals to
   pending.
5. Restart workers after deployment.
6. Verify the focused security suite and public catalog endpoints.

## 8. Residual risks

- `stores.is_active` remains denormalized compatibility state.
- Brand/Tag remain hard-delete; soft-delete support would require its own work.
- Legacy digital files require hash backfill before digital approval.
- SQL direct writes, `query()->update()`, and `saveQuietly()` can still bypass
  observers; runtime `published()`/risk barriers remain as secondary defenses.
