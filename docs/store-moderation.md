# Moderation de tiendas (Store approval flow)

Este documento describe el flujo de aprobación de tiendas creado para que un
administrador revise y active la tienda de un vendor, y para que cualquier
cambio de perfil desactive la tienda (y revise sus productos) hasta una nueva
aprobación.

## Roles y estados de la tienda

El campo `stores.status` admite:

| Estado | Significado |
| --- | --- |
| `draft` | Creada pero aún no enviada. |
| `pending` | Enviada a revisión; requiere decisión de un admin. |
| `approved` | Aprobada y publicada; `is_active = true`. |
| `rejected` | Rechazada por un admin con motivo. |
| `suspended` | Suspendida por un admin; no vende. |

La aprobación también registra:
- `approved_at` / `approved_by` (admin que aprobó la última vez).
- `rejected_at` / `rejection_reason` (al rechazar).
- `suspended_at` (al suspender).

## Reglas de cierre (fail-closed)

- Un producto solo se publica si su tienda está `approved` (`status = approved`)
  y `suspended_at` es nulo (ver `Product::scopePublished` y
  `ProductRiskEvaluator`).
- Si la tienda sale de `approved` (se edita, se rechaza o se suspende), todos sus
  productos `approved` se marcan `pending`, se limpian `reviewed_version`,
  `approved_at`, `approved_by`, el fingerprint y la evaluación de riesgo.
  Así nada conserva una publicación anterior y la decisión no re-aprueba datos
  obsoletos.
- Al volver a aprobar la tienda, los productos pendientes se re-envían por el
  evaluador (`ProductModerationService::submit`) para decidir de nuevo si se
  auto-aprueban.

## `StoreModerationService`

Servicio central en `app/Services/StoreModerationService.php`. Cada transición
bloquea la tienda (`lockForUpdate`) y opera en una transacción.

| Método | Efecto |
| --- | --- |
| `submitForReview(Store, reason)` | Pone la tienda `pending`, `is_active=false` y limpia la aprobación previa. Si estaba `approved`, invalida productos. |
| `approve(Store, Admin)` | Activa la tienda, graba `approved_at`/`approved_by` y re-envía productos pendientes. Exige eligible. |
| `reject(Store, Admin, reason)` | Marca `rejected` con motivo; invalida productos si estaba `approved`. |
| `suspend(Store, Admin, reason)` | Marca `suspended` con `suspended_at`; invalida productos si estaba `approved`. |
| `restore(Store, Admin)` | Re-aprueba una tienda suspendida (misma lógica que `approve`). |

Para aprobar se exige que el seller sea `vendor`, tenga email verificado y KYC
`approved`; en caso contrario el servicio aborta con 422. Una tienda suspendida
**sí** puede aprobarse/restaurarse: `approve()`/`restore()` son justamente la
acción que levanta la suspensión (`suspended_at` vuelve a `null`).

Cada acción mantiene `is_active` de forma coherente:

| Acción | `status` | `is_active` |
| --- | --- | --- |
| `approve` / `restore` | `approved` | `true` |
| `reject` | `rejected` | `false` |
| `suspend` | `suspended` | `false` |
| `submitForReview` (editar perfil) | `pending` | `false` |

## Alta de cuentas vendor

El registro normal (`/register`) crea siempre una cuenta `user` (cliente). La
opción «I am a vendor» fue retirada de esa vista. Quien quiera vender debe
registrarse por la ruta dedicada:

```text
GET  /vendor/register  vendor.register         (vista vendor-register)
POST /vendor/register  vendor.register.store   (crea cuenta user_type=vendor)
```

Controladores:
- `app/Http/Controllers/Auth/RegisteredUserController.php` → siempre `user`.
- `app/Http/Controllers/Auth/VendorRegisterController.php` → siempre `vendor`.

Vistas:
- `resources/views/auth/register.blade.php` (solo cliente).
- `resources/views/auth/vendor-register.blade.php` (vendor).

Tras registrarse, el vendor es dirigido a `vendor.dashboard`; como su email aún
no está verificado, el middleware `verified` lo envía a la pantalla de
verificación antes de poder usar el dashboard.

Cada controlador de autenticación y de verificación redirige según el tipo de
cuenta mediante `User::homeRoute()` (devuelve `vendor.dashboard` para vendors y
`dashboard` para clientes). Esto aplica a login, registro, prompt de
verificación, enlace de verificación (`VerifyEmailController`), reenvío de
correo y confirmación de contraseña. Así, un vendor nunca aterriza en el
dashboard de cliente tras activar su correo o iniciar sesión.

## Flujo del vendor

Cuando el vendor crea o actualiza su perfil
(`StoreController@update` en `app/Http/Controllers/Frontend/StoreController.php`),
después de guardar se invoca `StoreModerationService::submitForReview`. Esto:

1. Pone la tienda en `pending`.
2. Si la tienda estaba `approved`, invalida sus productos aprobados.
3. Muestra un aviso indicando que la tienda quedó en revisión.

El vendor no puede cambiar `status`, `approved_at` ni `suspended_at` desde el
formulario; esos campos no forman parte de `$validated`.

## Panel admin

**Permiso:** `Store Management` (protected via middleware `permission:Store Management`).

Rutas en `routes/admin.php`:

```text
GET    /admin/stores                 admin.stores.index      (listado + filtro por estado)
GET    /admin/stores/{store}         admin.stores.show       (detalle + acciones)
POST   /admin/stores/{store}/approve admin.stores.approve
POST   /admin/stores/{store}/reject  admin.stores.reject     (motivo obligatorio)
POST   /admin/stores/{store}/suspend admin.stores.suspend    (motivo obligatorio)
POST   /admin/stores/{store}/restore admin.stores.restore
```

Vistas:
- `resources/views/admin/store/index.blade.php`
- `resources/views/admin/store/show.blade.php`

El sidebar muestra **Stores** si el admin tiene `Store Management`
(`resources/views/admin/layouts/sidebar.blade.php`).

## Notas de operación

- Ejecutar el seeder de permisos tras añadir el permiso:
  ```bash
  php artisan db:seed --class=Database\\Seeders\\Admin\\PermissionSeeder
  php artisan permission:cache-reset
  ```
- Ejecutar la migración que añade los campos de aprobación (`approved_by`,
  `rejected_at`, `rejection_reason`) a `stores`:
  ```bash
  php artisan migrate
  ```
- Aprobar una tienda con seller no elegible devuelve 422; el endpoint admin
  captura la excepción y redirige con un mensaje.

## Versioned store moderation

Stores now use the same versioned-review pattern as Products:

- `stores.moderation_version`, `reviewed_version`, `submitted_at`, `moderation_fingerprint`, and `moderation_reason`.
- `store_approval_reviews` is an append-only audit history.
- Admin decisions require the current `moderation_version`; stale decisions receive 409.
- Material Store profile changes create a new pending version. A no-op save does not invalidate an approved Store.
- Store status changes no longer invalidate Product content approvals. Publication is blocked by the Store being pending/rejected/suspended or its moderation version being stale.

## Implemented versioned Store moderation

The refactor is recorded in detail in:

```text
docs/refactor-implementation.md
```

In the current implementation:

- Store decisions are versioned and audited through `store_approval_reviews`.
- Admin forms send `moderation_version`; stale decisions return `409`.
- Material Store updates create a new pending version.
- A no-op save does not invalidate an approved Store.
- Store status changes no longer invalidate Product content approvals. Instead,
  `Product::published()` closes publication while the Store is not current or
  approved.

