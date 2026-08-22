# Arquitectura y flujos del módulo de productos

[Volver al índice del módulo](README.md)

## Alcance

Este documento cubre:

- creación y edición de productos físicos y digitales;
- ownership y acceso de vendors;
- revisión manual y automática;
- historial versionado;
- publicación pública;
- archivos, imágenes, atributos y variantes;
- cambios de marca, categoría y tag;
- control de confianza por tienda;
- comportamiento del slug.

Los actores son:

| Actor | Responsabilidad |
| --- | --- |
| Vendor | Crea y mantiene productos exclusivamente de su tienda. No decide ownership, promociones ni aprobación. |
| Admin con `Product Management` | Crea, modifica, aprueba o rechaza productos y gestiona archivos/productos del catálogo. |
| Admin con `Store Auto-Approval Management` | Activa o desactiva la confianza automática de una tienda. |
| Worker de cola | Evalúa productos pendientes y reenvía productos afectados por referencias compartidas. |
| Observer de usuario | Revalida confianza y revisiones cuando cambia `seller.user_type`. |
| Storefront | Home, listado y detalle sólo exponen productos obtenidos con `published()`. |

## Archivos principales

| Archivo | Responsabilidad |
| --- | --- |
| `app/Models/Product.php` | Estados, relaciones y scope `published()`. |
| `app/Models/ProductApprovalReview.php` | Historial de una revisión por producto y versión. |
| `app/Models/StoreAutoApprovalAudit.php` | Auditoría del cambio de confianza de una tienda. |
| `app/Policies/ProductPolicy.php` | Abilities, elegibilidad del vendor y ownership. |
| `app/Services/ProductModerationService.php` | Versionado, snapshot, fingerprint y decisiones. |
| `app/Services/ProductRiskEvaluator.php` | Riesgo y autoaprobación. |
| `app/Services/ProductContentSanitizer.php` | Sanitización del HTML enriquecido. |
| `app/Services/DigitalProductFileUploadService.php` | Upload chunked privado sobre `config('products.digital_upload.disk')` y cuotas. |
| `app/Jobs/EvaluateProductForApproval.php` | Evaluación asíncrona de una versión. |
| `app/Jobs/ReevaluateProductsAfterReferenceChange.php` | Reenvío por cambios en referencias. |
| `app/Observers/ProductReferenceModerationObserver.php` | Observa Brand, Category y Tag. |
| `app/Observers/SellerTypeModerationObserver.php` | Detecta transiciones de `User.user_type`. |
| `app/Services/SellerTypeRevalidationService.php` | Revoca confianza, remodera productos y audita el cambio de tipo. |
| `app/Http/Controllers/Frontend/HomeController.php` | Obtiene las novedades públicas mediante `published()`. |
| `app/Http/Controllers/Frontend/ProductCatalogController.php` | Listado y detalle públicos con filtrado obligatorio. |
| `app/Http/Controllers/Frontend/VendorProductController.php` | Operaciones de producto del vendor. |
| `app/Http/Controllers/Frontend/VendorDigitalProductFileController.php` | Archivos digitales del vendor. |
| `app/Http/Controllers/Admin/ProductController.php` | Operaciones y decisiones del admin. |
| `app/Http/Controllers/Admin/AdminDigitalProductFileController.php` | Archivos digitales del admin. |
| `app/Http/Controllers/Admin/StoreAutoApprovalController.php` | Confianza y auditoría por tienda. |
| `config/product_moderation.php` | Interruptor y umbral de autoaprobación. |
| `config/products.php` | Límites de uploads y variantes. |

## Modelo de datos

### Campos de moderación en `products`

| Campo | Significado |
| --- | --- |
| `approved_status` | Estado de decisión: `pending`, `approved` o `rejected`. |
| `moderation_version` | Versión actual del contenido sometido a revisión. Comienza en `0` antes de la primera revisión. |
| `reviewed_version` | Versión que recibió la última decisión. Debe ser igual a `moderation_version` para publicar. |
| `submitted_at` | Momento en que se envió la versión actual. |
| `approved_at` | Fecha de aprobación; queda en `null` para pendiente o rechazado. |
| `approved_by` | Admin que aprobó. Es `null` en autoaprobaciones. |
| `moderation_reason` | Motivo de envío, decisión o causa por la que requiere revisión. |
| `risk_level` | `low`, `medium`, `high` o `critical`. |
| `risk_score` | Puntaje entre 0 y 100. |
| `moderation_fingerprint` | SHA-256 del snapshot de la versión actual. |

`approved_status` y el campo comercial `status` son conceptos distintos:

- `approved_status` responde si una versión fue revisada.
- `status` responde si el producto está `active`, `inactive` o `draft`.
- Un producto puede estar aprobado pero no publicado por estar inactivo.

### Tabla `product_approval_reviews`

Cada fila representa una versión concreta. Existe una restricción única sobre `product_id + version`.

| Campo | Uso |
| --- | --- |
| `product_id`, `version` | Identifican la versión. |
| `status` | `pending`, `approved`, `rejected` o `superseded`. |
| `source` | `automatic` o `manual`. |
| `submitted_by` | Usuario vendor que originó el envío, cuando aplica. |
| `reviewed_by` | Admin que tomó la decisión manual. |
| `risk_score`, `risk_level`, `risk_reasons` | Evaluación persistida. |
| `submission_reason` | Motivo del envío a revisión. |
| `decision_reason` | Motivo de aprobación, rechazo, espera o supersesión. |
| `content_hash` | Fingerprint esperado de esa versión. |
| `snapshot` | JSON con el estado que se revisó. |
| `submitted_at`, `reviewed_at` | Fechas del ciclo de revisión. |

`superseded` sólo existe en el historial. Significa que una revisión pendiente dejó de ser vigente porque apareció una versión nueva.

### Confianza de tienda

`stores.auto_approve_products` es booleano, tiene valor inicial `false` y no es un campo controlable por el vendor.

La tabla `store_auto_approval_audits` guarda:

- tienda y admin;
- valor anterior y nuevo;
- razón obligatoria;
- snapshot de elegibilidad;
- cantidad de productos pendientes reenviados;
- IP y user-agent;
- fecha del cambio.

Una petición idempotente elegible no crea otra auditoría ni reenvía productos. Hay una excepción fail-closed: si se solicita `enabled = true`, el valor almacenado ya era true pero la elegibilidad actual es falsa, el backend revoca la confianza obsoleta, invalida productos, audita la corrección y responde 422.

## Máquina de estados

```text
Sin revisión
  moderation_version = 0
          |
          | submit()
          v
PENDING vN --------------------------+
  reviewed_version = null            |
          |                           | cambio material
          | job elegible              | submit()
          v                           |
APPROVED vN                          +--> PENDING vN+1
  reviewed_version = N                    revisión pendiente anterior
  approved_at != null                     pasa a SUPERSEDED
  approved_by = admin|null

PENDING vN -- rechazo manual --> REJECTED vN
  reviewed_version = N
  approved_at = null
  reviewed_by se conserva en el historial

APPROVED o REJECTED -- cambio material --> PENDING vN+1
```

Una nueva versión limpia:

- `reviewed_version`;
- `approved_at`;
- `approved_by`;
- riesgo anterior, hasta que el job vuelva a evaluar.

## Contrato de `ProductModerationService`

### `submit()` y `markForReview()`

1. Exigen que el producto ya exista.
2. Abren una transacción y bloquean el producto con `lockForUpdate()`.
3. Construyen snapshot y fingerprint.
4. Si sigue pendiente y el fingerprint no cambió, reutilizan la versión vigente.
5. Si cambió, marcan revisiones pendientes anteriores como `superseded`.
6. Incrementan `moderation_version`.
7. Dejan el producto en `pending` y crean la revisión.
8. Despachan `EvaluateProductForApproval` con `afterCommit()`.

`markForReview()` es un alias semántico de `submit()` para indicar que una mutación material invalidó la revisión anterior.

### `approve()` y `reject()`

- Bloquean el producto y aceptan una `expectedVersion`.
- Si la versión actual no coincide, devuelven `null`; el controlador responde HTTP 409 o vuelve a someter de forma controlada.
- Vuelven a calcular snapshot, fingerprint y riesgo.
- Guardan la decisión tanto en `products` como en `product_approval_reviews`.
- Una aprobación manual puede aceptar un producto con motivos de riesgo; el admin asume esa decisión y el riesgo queda auditado.
- El rechazo exige motivo en la interfaz administrativa.

### Snapshot y fingerprint

El snapshot contiene información suficiente para responder qué se revisó:

- seguridad de tienda: estado, suspensión y confianza automática;
- vendedor: `seller_id`, email verificado y KYC;
- campos comerciales, stock, precios, estado y promociones;
- hashes de descripción corta y contenido;
- marca con nombre, slug y estado;
- categorías con parent, nombre, slug, estado y soft delete;
- tags con nombre, slug y estado;
- atributos, valores y pivotes;
- imágenes con ruta y orden;
- archivos con ruta, extensión y tamaño;
- variantes y combinaciones de valores.

Las consultas del snapshot usan orden estable. El fingerprint es el SHA-256 del JSON resultante.

Una modificación que cambie el snapshot pero no genere una versión indica que una ruta de escritura omitió el servicio de moderación y debe corregirse.

## Flujo de creación por vendor

```text
POST /vendor/products/{physical|digital}/create
        |
        +--> middleware auth + verified + user_role:vendor
        +--> ProductPolicy::create
        +--> request vendor sanitiza y prohíbe campos administrativos
        |
        v
Transacción
        +--> store_id = tienda del usuario autenticado
        +--> product_type = segmento permitido de la ruta
        +--> categorías y tags
        +--> submit("Initial product submission by vendor")
        |
        v afterCommit
EvaluateProductForApproval(productId, version)
```

El vendor puede preparar productos mientras su tienda está en `draft`, `pending` o `approved`, siempre que tenga email verificado, KYC aprobado y no esté suspendido. La publicación y la autoaprobación sí requieren tienda `approved`.

## Cambios realizados por vendor

Generan una versión nueva cuando realmente modifican el estado material:

- nombre, slug, descripciones y SKU;
- precio, oferta y fechas;
- stock, cantidad y estado del producto;
- marca, categorías y tags;
- upload, eliminación o reordenamiento de imágenes;
- upload o eliminación de archivo digital;
- atributos y valores;
- variantes, precio, stock o flags de variante.

Cada mutación sensible vuelve a cargar y bloquear el producto, reautoriza ownership y después llama `markForReview()`.

## Flujo administrativo

### Crear

El admin crea el producto y `ProductModerationService::approve()` registra una aprobación manual versión 1 con el admin autenticado.

Para un producto digital, la posterior incorporación o eliminación de un archivo vuelve a dejarlo pendiente. Antes de aprobar manualmente un digital, el admin debe confirmar que existe un archivo utilizable aunque el sistema permita registrar una decisión manual con riesgo.

### Actualizar y decidir

El formulario envía `moderation_version` como campo oculto.

```text
Admin abre producto vN
        |
        v
POST update con moderation_version=N
        |
        +--> lockForUpdate()
        +--> versión actual != N -> HTTP 409
        |
        +--> approved_status=pending  -> submit()
        +--> approved_status=approved -> approve()
        +--> approved_status=rejected -> reject() con razón
```

El 409 evita que una pestaña vieja apruebe una versión enviada después.

### Mutaciones administrativas de subrecursos

- Imágenes, atributos y variantes: crean versión y se aprueban manualmente de inmediato con el admin actual. La operación queda auditada sin obligar a una segunda pantalla.
- Archivos digitales: crean versión pendiente y requieren una decisión explícita posterior.

## Evaluación automática

`EvaluateProductForApproval` implementa `ShouldQueue` y `ShouldBeUnique`.

| Propiedad | Valor |
| --- | --- |
| ID único | `{productId}:{moderationVersion}` |
| Intentos | 3 |
| Backoff | 5, 30 y 120 segundos |
| Timeout | 60 segundos |
| Unicidad | 3600 segundos |

El job verifica estado, versión y fingerprint antes de evaluar. Después vuelve a verificarlos bajo lock antes de decidir. Si la versión cambió, termina sin aprobar nada.

Una cola detenida deja los productos en `pending`; es un fallo seguro, aunque impide la automatización.

## Reglas de riesgo

| Código | Puntos | Causa |
| --- | ---: | --- |
| `missing_store` | 100 | No existe tienda. |
| `store_not_approved` | 100 | La tienda no está aprobada. |
| `store_suspended` | 100 | La tienda está suspendida. |
| `store_requires_manual_review` | 0 | La tienda no tiene confianza explícita. |
| `missing_seller` | 100 | La tienda no tiene vendedor. |
| `seller_email_not_verified` | 60 | Email sin verificar. |
| `seller_kyc_not_approved` | 100 | KYC ausente o no aprobado. |
| `digital_product_requires_manual_review` | 30 | Todo producto digital es manual. |
| `digital_product_without_file` | 40 | Digital sin archivo. |
| `missing_sellable_price` | 80 | No hay precio base válido ni variante activa con precio. |
| `missing_category` | 15 | No hay categoría. |
| `missing_image` | 15 | No hay imagen. |
| `negative_price` | 100 | Precio base negativo. |
| `negative_special_price` | 100 | Precio especial negativo. |
| `negative_stock` | 100 | Stock administrado negativo. |
| `special_price_above_price` | 40 | Oferta mayor al precio regular. |
| `invalid_variant_price_or_stock` | 100 | Alguna variante tiene precio/stock incoherente. |
| `dangerous_markup` | 100 | Se detectó markup ejecutable o inseguro. |

Niveles:

- `low`: 0–20;
- `medium`: 21–49;
- `high`: 50–79;
- `critical`: 80–100.

Actualmente todos los motivos de la tabla son bloqueantes, incluso los que suman pocos puntos. El umbral configurable es una segunda barrera y permite evolucionar reglas futuras no bloqueantes.

El interruptor global `PRODUCT_AUTOMATIC_APPROVAL_ENABLED=false` fuerza revisión manual aunque la tienda sea confiable.

## Confianza automática por tienda

El control aparece en la edición admin de productos físicos y digitales cuando:

- el producto tiene tienda;
- el admin tiene `Store Auto-Approval Management`.

Para activar, la tienda debe:

- estar `approved`;
- no tener `suspended_at`;
- pertenecer a un usuario `vendor`;
- tener email verificado;
- tener KYC `approved`.

La razón debe tener entre 10 y 1000 caracteres. El backend prohíbe que el request cambie `status`, `seller_id`, `approved_at` o `suspended_at`.

Al cambiar el flag:

1. se bloquea primero el seller y después la tienda;
2. se valida la elegibilidad actual, incluso antes de aceptar un enable idempotente;
3. se guarda el flag;
4. se reenvían todos sus productos pendientes para generar snapshots con el valor nuevo;
5. se registra auditoría con admin, razón, elegibilidad, IP y user-agent.

Si un enable aparentemente idempotente encuentra confianza obsoleta e inelegible, `StoreAutoApprovalController` aplica la barrera fail-closed: guarda `false`, fuerza aprobados a pending, limpia decisión, fingerprint y riesgo, audita con el admin, remodera y finalmente devuelve 422. El error HTTP no revierte la corrección de seguridad.

Desactivar manualmente siempre está permitido, incluso si la tienda dejó de ser elegible. Esta acción administrativa afecta productos pendientes y envíos futuros; no revoca por sí sola productos ya aprobados. Para retirada inmediata por otros motivos debe cambiarse el producto o suspenderse/inactivarse la tienda.

### Revalidación continua de `seller.user_type`

`AppServiceProvider` registra `SellerTypeModerationObserver` sobre `User`. En cada evento `updated`, el observer actúa sólo cuando `wasChanged('user_type')` y entrega el tipo anterior a `SellerTypeRevalidationService::handle()`.

El servicio trabaja en dos fases:

1. En una transacción de seguridad bloquea primero al seller y después todas sus tiendas —incluidas soft-deleted—, siempre en el mismo orden que `StoreAutoApprovalController`.
2. Desactiva `auto_approve_products`, reúne productos aprobados/pendientes —también soft-deleted— y convierte inmediatamente cada aprobado a pending, limpiando `reviewed_version`, `approved_at`, `approved_by`, fingerprint y evaluación de riesgo.
3. Si existía confianza, registra una auditoría de sistema con `admin_id = null`, trigger `seller_user_type_changed`, tipo anterior, tipo observado, tipo actual, elegibilidad y contador inicial en cero.
4. Tras asegurar el cierre, llama `markForReview()` producto por producto —con soporte `withTrashed()`— para crear snapshot/versión detallados y despachar `EvaluateProductForApproval`.
5. Incrementa `pending_products_resubmitted` después de cada remoderación completada.

Separar las fases evita que un error al construir un snapshot revierta la barrera principal: la tienda conserva confianza desactivada y ningún producto previamente aprobado queda publicable. La segunda fase puede quedar parcial y debe poder reintentarse operacionalmente.

El fast-path de `submit()` sólo reutiliza una versión si existe además una review pending de esa versión. Esto impide que una secuencia rápida vendor → otro tipo → vendor reutilice el fingerprint de una review ya aprobada. Restaurar una tienda o producto soft-deleted tampoco recupera confianza ni aprobación anteriores.

Cualquier transición invalida la decisión anterior, incluso volver a `vendor`. La confianza nunca se restaura automáticamente: un admin debe evaluar la tienda y activarla otra vez de forma explícita.

Además del flujo del observer hay tres defensas independientes:

- `published()` consulta `users.user_type = vendor` en cada request;
- `ProductRiskEvaluator` recarga seller/KYC y añade `seller_not_vendor` con score 100;
- el snapshot contiene `seller_user_type`, por lo que cambia el fingerprint y un job de una versión anterior no puede aprobar contenido obsoleto.

`updated` ocurre después de persistir el cambio del usuario. El diseño completo también es bifásico y fail-closed:

1. desde que el nuevo tipo es visible, `published()` y riesgo bloquean inmediatamente a un no-vendor;
2. la primera fase del servicio persiste revocación, estado pending y auditoría antes de producir snapshots;
3. la segunda fase completa versiones/revisiones sin poder restaurar una aprobación anterior.

Si el caller envuelve el `User::save()` en `DB::transaction()`, las transacciones internas se anidan y todo el cambio se revierte en conjunto ante una excepción. Sin esa transacción exterior no debe afirmarse atomicidad entre el UPDATE de `users` y los efectos secundarios, aunque las barreras runtime y el cierre previo a snapshots mantienen la publicación cerrada.

`DB::table(...)->update()`, `User::query()->update()` y `saveQuietly()` omiten observers. Mientras el tipo permanezca no-vendor, scope y riesgo siguen bloqueando; una secuencia ABA hecha por esas vías —vendor → otro tipo → vendor— puede restaurar la elegibilidad runtime sin haber revocado confianza ni revisiones. Los cambios de rol deben pasar por una operación Eloquent controlada y, para máxima atomicidad, por una transacción exterior.

## Autorización vendor

`ProductPolicy` define:

- `viewAny`, `view`, `create`, `update`, `delete`;
- `restore`, `forceDelete`;
- `uploadImages`, `reorderImages`;
- `manageDigitalFiles`;
- `manageAttributes`, `manageVariants`.

Condiciones comunes:

1. `user_type === vendor`;
2. email verificado;
3. KYC aprobado;
4. tienda `draft`, `pending` o `approved`;
5. tienda no suspendida;
6. para un producto existente, `product.store_id === user.store.id`.

`manageDigitalFiles` exige además `product_type === digital`.

### Defensa TOCTOU

No basta con autorizar el modelo recibido por route binding porque el ownership podría cambiar antes de guardar.

Las mutaciones siguen este patrón:

```php
DB::transaction(function () use ($product) {
    $current = Product::query()
        ->whereKey($product->getKey())
        ->lockForUpdate()
        ->firstOrFail();

    Gate::authorize('update', $current);

    // Mutación y moderación dentro de la misma transacción.
});
```

Imágenes, variantes y archivos también se bloquean y se comprueba que sigan perteneciendo al producto bloqueado. Si un upload de imagen ya creó el archivo físico y falla la transacción, el controlador lo elimina como compensación.

## Requests separados por límite de confianza

### Vendor

Archivos:

- `VendorProductRequest`;
- `ProductStoreRequest`;
- `ProductUpdateRequest`;
- `DigitalProductChunkUploadRequest`;
- `ReorderProductImagesRequest`.

El vendor no puede enviar:

- `store` o `store_id`;
- `product_type`;
- `approved_status`;
- `is_featured`, `is_hot`, `is_new` ni aliases;
- fechas, admin, razón, riesgo o versiones de moderación.

El backend asigna explícitamente tienda y tipo. No se usa `$request->all()` para persistir.

Validaciones comerciales relevantes:

- slug normalizado y único;
- descripción corta máximo 2000 y contenido máximo 100000;
- precios entre 0 y 99,999,999.99, con dos decimales;
- precio especial menor o igual al regular;
- fechas de oferta ordenadas;
- cantidad entera no negativa cuando se administra stock;
- al menos una categoría y máximo 20;
- máximo 50 tags;
- estados permitidos definidos por allowlist.

### Admin

El admin sí puede seleccionar tienda, promociones y decisión. En update debe enviar:

- `approved_status`;
- `moderation_version`;
- `approval_reason` cuando rechaza.

El request de confianza de tienda requiere `enabled` booleano y `reason` de 10–1000 caracteres.

## Contrato de publicación pública

`Product::published()` exige simultáneamente:

1. `approved_status = approved`;
2. `status = active`;
3. `moderation_version > 0`;
4. `reviewed_version = moderation_version`;
5. tienda `approved`;
6. tienda no suspendida;
7. seller con `user_type = vendor`;
8. seller con email verificado;
9. KYC aprobado.

Uso correcto:

```php
$product = Product::query()
    ->published()
    ->where('slug', $slug)
    ->firstOrFail();
```

Uso incorrecto:

```php
// Insuficiente: no comprueba versión, estado/suspensión de tienda,
// tipo/email del seller ni KYC.
$product = Product::query()
    ->where('approved_status', 'approved')
    ->where('slug', $slug)
    ->firstOrFail();
```

El scope es opt-in y está integrado explícitamente en:

| Método | URI | Controlador | Consulta |
| --- | --- | --- | --- |
| GET | `/` | `HomeController::index` | Últimos 12 productos publicables. |
| GET | `/products` | `ProductCatalogController::index` | Catálogo publicable paginado de 24 en 24. |
| GET | `/products/{slug}` | `ProductCatalogController::show` | Busca el slug dentro de `published()`; si no cumple, 404. |

Las consultas de admin y vendor no lo usan porque deben mostrar borradores, pendientes y rechazados. Todo consumidor público futuro debe aplicarlo explícitamente; no existe un global scope que lo haga por él.

## Cambios en referencias compartidas

Marca, categoría y tag forman parte del snapshot. Si cambian después de aprobar:

1. `ProductReferenceModerationObserver` obtiene productos afectados;
2. divide IDs en grupos de 250;
3. despacha `ReevaluateProductsAfterReferenceChange` después del commit;
4. el job llama `markForReview()` para cada producto.

La invalidación es asíncrona. Entre el commit de la referencia y la ejecución del worker existe una ventana en la que el producto puede conservar su aprobación anterior. La cola debe estar operativa y monitoreada.

El job tiene tres intentos y timeout de 120 segundos.

Cambios directos mediante SQL no disparan observers y deben ir acompañados de una operación explícita de remoderación.

## Slug

El slug tiene tres capas:

1. JavaScript ofrece una vista previa al escribir el nombre.
2. Los requests vuelven a normalizar con `Str::slug()` y validan unicidad.
3. El índice `products_slug_unique` impide duplicados concurrentes.

En update se ignora únicamente el ID actual. Cambiar el slug es material y crea una versión nueva.

La migración de índice único:

- conserva el primer producto de cada slug duplicado;
- renombra los siguientes como `{slug}-{id}`;
- si todavía colisiona, añade un contador;
- limita el resultado a 255 caracteres;
- finalmente crea el índice único.

Los productos soft-deleted siguen reservando su slug.

## Atributos y variantes

Los atributos y valores son modelos compartibles. Para impedir contaminación entre productos:

- no se puede editar una definición usada por otro producto;
- se valida tanto el atributo como cada `AttributeValue`;
- el borrado quita el pivot del producto actual;
- una definición sólo se elimina cuando queda huérfana;
- admin y vendor aplican la misma protección.

Límites predeterminados de generación:

- 50 valores por atributo;
- 6 grupos de atributos;
- 500 combinaciones.

El número potencial se valida antes de materializar el producto cartesiano y antes de borrar variantes existentes. Si se supera, la transacción se revierte.

## Rutas vendor

Todas están bajo `/vendor`, nombres `vendor.*` y middleware `auth`, `verified`, `user_role:vendor`.

| Método | URI | Nombre |
| --- | --- | --- |
| GET | `/vendor/products` | `vendor.products.index` |
| GET | `/vendor/products/{type}/create` | `vendor.products.create` |
| POST | `/vendor/products/{type}/create` | `vendor.products.store` |
| GET | `/vendor/products/physical/{product}/edit` | `vendor.products.edit` |
| POST | `/vendor/products/physical/{product}/update` | `vendor.products.update` |
| POST | `/vendor/products/images/upload/{product}` | `vendor.products.images.upload` |
| DELETE | `/vendor/products/images/{image}` | `vendor.products.images.destroy` |
| POST | `/vendor/products/images/reorder` | `vendor.products.images.reorder` |
| POST | `/vendor/products/attributes/{product}/store` | `vendor.products.attributes.store` |
| DELETE | `/vendor/products/{product}/attributes/{attribute}` | `vendor.products.attributes.destroy` |
| POST | `/vendor/products/variants/{product}/update` | `vendor.products.variants.update` |
| GET | `/vendor/products/digital/{product}/edit` | `vendor.digital-products.edit` |
| POST | `/vendor/products/digital/file-upload` | `vendor.digital-products.file.upload` |
| DELETE | `/vendor/products/digital/{product}/{file}` | `vendor.digital-products.file.destroy` |
| DELETE | `/vendor/products/{product}` | `vendor.products.destroy` |

El `POST /products/{type}/create` sólo acepta `physical` o `digital`. El GET del formulario no tiene actualmente esa restricción de ruta y el controlador ignora el segmento; conviene aplicar el mismo `whereIn` al GET. El upload digital usa `throttle:300,1` además de cuotas persistentes.

La vista digital reutiliza actualmente el endpoint de update cuya URI contiene `/physical/`. Es una convención heredada de routing, no una decisión de tipo; el tipo persistido no se toma del request de update.

## Rutas admin

Todas están bajo `/admin` y `auth:admin`. Las rutas de producto requieren `Product Management`.

| Método | URI | Nombre |
| --- | --- | --- |
| GET | `/admin/products` | `admin.products.index` |
| GET | `/admin/products/{type}/create` | `admin.products.create` |
| POST | `/admin/products/{type}/create` | `admin.products.store` |
| GET | `/admin/products/physical/{product}/edit` | `admin.products.edit` |
| POST | `/admin/products/physical/{product}/update` | `admin.products.update` |
| POST | `/admin/products/images/upload/{product}` | `admin.products.images.upload` |
| DELETE | `/admin/products/images/{image}` | `admin.products.images.destroy` |
| POST | `/admin/products/images/reorder` | `admin.products.images.reorder` |
| POST | `/admin/products/attributes/{product}/store` | `admin.products.attributes.store` |
| DELETE | `/admin/products/{product}/attributes/{attribute}` | `admin.products.attributes.destroy` |
| POST | `/admin/products/variants/{product}/update` | `admin.products.variants.update` |
| GET | `/admin/products/digital/{product}/edit` | `admin.digital-products.edit` |
| POST | `/admin/products/digital/file-upload` | `admin.digital-products.file.upload` |
| DELETE | `/admin/products/digital/{product}/{file}` | `admin.digital-products.file.destroy` |
| DELETE | `/admin/products/{product}` | `admin.products.destroy` |

El upload digital también usa `throttle:300,1`.

Igual que en vendor, el POST de creación restringe `type`, pero el GET `/products/{type}/create` no tiene todavía el mismo `whereIn`.

Ruta separada de confianza:

| Método | URI | Nombre | Permiso |
| --- | --- | --- | --- |
| PATCH | `/admin/stores/{store}/product-auto-approval` | `admin.stores.product-auto-approval.update` | `Store Auto-Approval Management` |

## Reglas para extender el módulo

Al agregar una nueva mutación material:

1. crear o ampliar un request para el actor correcto;
2. prohibir campos de otro límite de confianza;
3. autorizar con la ability correspondiente;
4. abrir transacción;
5. recargar con `lockForUpdate()`;
6. reautorizar el modelo bloqueado;
7. guardar la mutación;
8. llamar `markForReview()` dentro de la misma transacción;
9. incluir el nuevo dato en `snapshot()`;
10. añadir prueba de versión, ownership y job obsoleto cuando aplique.

Al agregar una consulta pública:

1. comenzar con `Product::query()->published()`;
2. no sustituirlo por un filtro parcial;
3. probar tienda inactiva/suspendida, versión obsoleta, tipo/email del seller y KYC;
4. evitar exponer URLs de archivos o imágenes de productos no publicables.

## Store & Product Moderation Refactor

See `docs/refactor-implementation.md` for the complete implementation record:
versioned Store moderation, Product content/eligibility separation, KYC/email
revalidation, synchronous reference invalidation, digital file hashes, and
soft-delete safety.

