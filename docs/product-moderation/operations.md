# Operación, despliegue y troubleshooting

[Volver al índice del módulo](README.md)

## Objetivo

Este runbook indica cómo configurar, desplegar y operar el módulo de moderación y seguridad de productos.

La implementación fue validada con migraciones en modo `--pretend`. No se debe asumir que una base de datos concreta ya tiene las migraciones aplicadas; comprobar siempre `migrate:status`.

## Prerrequisitos

- Base de datos disponible.
- Tabla `jobs`, `job_batches` y `failed_jobs` creada por la migración base de Laravel.
- Un worker de cola persistente en ambientes donde se espere autoevaluación.
- Cache compartida entre workers para `ShouldBeUnique`; la configuración actual usa database cache.
- Permisos de escritura en `storage/app/private` y `storage/framework`.
- Límites PHP/proxy mayores que el chunk configurado.
- Backup de base de datos y storage antes de aplicar las migraciones.
- Home, listado y detalle públicos disponibles y actualizados para usar `Product::published()`.

Producción debe usar `APP_ENV=production`, `APP_DEBUG=false` y una `APP_KEY` propia. `AdminSeeder` contiene credenciales fijas de desarrollo; no deben desplegarse como credencial válida y la cuenta inicial debe rotarse inmediatamente.

## Variables de entorno

### Moderación

| Variable | Default | Uso |
| --- | ---: | --- |
| `PRODUCT_AUTOMATIC_APPROVAL_ENABLED` | `true` | Kill switch global. `false` deja todo pendiente aunque la tienda sea confiable. |
| `PRODUCT_MAXIMUM_AUTOMATIC_RISK_SCORE` | `20` | Máximo score permitido. Actualmente cualquier motivo también bloquea por sí mismo. |

La autoaprobación sigue siendo opt-in por tienda aunque el interruptor global esté activo.

### Archivos digitales

| Variable | Default | Equivalencia |
| --- | ---: | --- |
| `PRODUCT_MAX_DIGITAL_FILE_SIZE_KB` | `262144` | 256 MiB por archivo. |
| `PRODUCT_MAX_DIGITAL_CHUNK_SIZE_KB` | `10240` | 10 MiB por chunk. |
| `PRODUCT_MAX_DIGITAL_CHUNKS` | `4096` | Máximo de chunks por upload. |
| `PRODUCT_MAX_ACTIVE_DIGITAL_UPLOADS` | `3` | Uploads incompletos por uploader. |
| `PRODUCT_DIGITAL_UPLOAD_TTL_HOURS` | `24` | Edad para considerar stale un upload incompleto. |
| `PRODUCT_MAX_DIGITAL_FILES_PER_PRODUCT` | `20` | Archivos registrados por producto. |
| `PRODUCT_MAX_DIGITAL_SIZE_PER_PRODUCT_KB` | `1048576` | 1 GiB acumulado por producto. |
| `PRODUCT_MAX_DIGITAL_SIZE_PER_STORE_KB` | `5242880` | 5 GiB acumulado por tienda. |

Si se cambia el chunk máximo, también revisar:

- `upload_max_filesize`;
- `post_max_size`;
- límites del reverse proxy/web server;
- timeout de la petición.

El proxy necesita aceptar el chunk, no el archivo completo.

### Variantes

| Variable | Default | Uso |
| --- | ---: | --- |
| `PRODUCT_MAX_VALUES_PER_ATTRIBUTE` | `50` | Valores permitidos por grupo. |
| `PRODUCT_MAX_ATTRIBUTE_GROUPS` | `6` | Grupos por producto. |
| `PRODUCT_MAX_VARIANT_COMBINATIONS` | `500` | Producto cartesiano máximo. |

### Cola

Configuración mínima recomendada:

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=180
```

`EvaluateProductForApproval` tiene timeout de 60 segundos y `ReevaluateProductsAfterReferenceChange` de 120. `retry_after` debe ser mayor que el job más largo para evitar que otro worker lo reserve antes de que termine.

Después de modificar `.env`:

```bash
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan config:cache
./vendor/bin/sail artisan queue:restart
```

## Permisos

| Permiso | Guard | Responsabilidad |
| --- | --- | --- |
| `Product Management` | `admin` | CRUD, archivos, atributos, variantes y decisiones de productos. |
| `Store Auto-Approval Management` | `admin` | Activar/desactivar confianza automática por tienda. |

`Product Management` no concede el segundo permiso. La separación evita que cualquier editor pueda confiar en una tienda.

Una instancia de `Admin` con rol `Super Admin` obtiene bypass por `Gate::before`. Un usuario web/vendor con un rol homónimo no lo obtiene.

Crear o actualizar permisos:

```bash
./vendor/bin/sail artisan db:seed --class='Database\Seeders\Admin\PermissionSeeder'
./vendor/bin/sail artisan permission:cache-reset
```

Después se asigna desde:

```text
Admin > Access Management > Role > Edit
```

La tarjeta de autoaprobación no aparece si el admin no tiene el permiso dedicado.

## Efecto de las migraciones

### `2026_08_15_160000_add_moderation_fields_to_products_and_stores`

- Añade campos, índices y foreign key de moderación.
- Añade `stores.auto_approve_products = false`.
- Cambia todos los productos legacy `approved` a `pending`.
- Usa la razón `Security review required after enabling versioned moderation.`.

Este cambio de estado es intencional: no existe snapshot que demuestre qué contenido de un producto legacy fue revisado.

El método `down()` elimina columnas, pero no vuelve a poner esos productos en `approved`.

### `2026_08_15_160001_create_product_approval_reviews_table`

- Crea historial versionado, snapshots, hashes y riesgo.
- El rollback elimina la tabla y su historial.

### `2026_08_15_160002_enforce_unique_product_slugs`

- Detecta duplicados.
- Conserva el slug del ID más antiguo.
- Renombra duplicados posteriores con `-{id}` y contador si es necesario.
- Crea `products_slug_unique`.

El rollback elimina el índice, pero no restaura los slugs anteriores.

### `2026_08_16_000000_create_store_auto_approval_audits_table`

- Crea la auditoría de confianza de tienda.
- El rollback elimina ese historial.

## Checklist previo al despliegue

1. Confirmar backup recuperable de base de datos y archivos.
2. Ejecutar la suite focalizada.
3. Confirmar que `/`, `/products` y `/products/{slug}` usan `published()`, e inventariar cualquier consumidor público nuevo.
4. Revisar cuántos productos aprobados pasarán a pending.
5. Revisar slugs duplicados y preparar redirects SEO si aplica.
6. Confirmar que existe worker y `DB_QUEUE_RETRY_AFTER > 120`.
7. Preparar cómo pausar Supervisor, systemd, Horizon o el gestor de workers.
8. Confirmar permisos de `storage/app/private`.
9. Verificar que PHP/proxy aceptan el tamaño de chunk.
10. Comunicar que productos legacy requerirán revisión.
11. Definir qué roles recibirán el permiso de confianza de tienda.
12. Confirmar `APP_DEBUG=false`, APP_KEY válida y rotación de la cuenta creada por seeders.

Consultas de diagnóstico previas:

```sql
SELECT approved_status, COUNT(*) AS total
FROM products
GROUP BY approved_status;

SELECT slug, COUNT(*) AS total
FROM products
GROUP BY slug
HAVING COUNT(*) > 1;
```

## Procedimiento de despliegue

Ejemplo seguro con ventana de mantenimiento:

Antes del bloque, pausar el gestor de workers y esperar a que terminen los jobs activos. `artisan down` sólo detiene tráfico HTTP; no detiene la cola.

```bash
./vendor/bin/sail artisan migrate:status
./vendor/bin/sail artisan migrate --pretend --no-interaction

./vendor/bin/sail artisan down --retry=60

./vendor/bin/sail artisan migrate --force
./vendor/bin/sail artisan db:seed --class='Database\Seeders\Admin\PermissionSeeder' --force
./vendor/bin/sail artisan permission:cache-reset
./vendor/bin/sail artisan optimize:clear
./vendor/bin/sail artisan config:cache
./vendor/bin/sail artisan view:cache
./vendor/bin/sail artisan queue:restart
```

Después de migrar y limpiar caches, reanudar/iniciar los workers con el código nuevo y confirmar que procesan un job de prueba. Sólo entonces abrir tráfico:

```bash
./vendor/bin/sail artisan up
```

Si todavía no existe un supervisor en desarrollo, iniciar en otra terminal:

```bash
./vendor/bin/sail artisan queue:work --queue=default --tries=3 --timeout=150
```

En producción debe usarse Supervisor, systemd u otro gestor que reinicie el worker. Su timeout debe ser mayor que 120 segundos y menor que `retry_after`.

El repositorio no incluye una configuración lista de Supervisor, systemd o Horizon; provisionarla forma parte del despliegue. El `queue:listen` de `composer dev` es sólo para desarrollo y no reemplaza un worker supervisado.

Después del despliegue:

1. asignar los permisos a roles no-Super Admin;
2. revisar productos legacy pendientes;
3. confirmar que se procesan jobs;
4. realizar smoke tests;
5. monitorear `failed_jobs`, logs y almacenamiento.

## Procedimiento administrativo de autoaprobación

### Activar

1. Entrar con un admin que tenga `Store Auto-Approval Management`.
2. Abrir la edición de un producto de la tienda.
3. Localizar `Store Auto-Approval`.
4. Verificar que la tienda esté activa y no suspendida.
5. Verificar que el seller sea vendor, tenga email verificado y KYC aprobado.
6. Activar el switch.
7. Escribir una razón de 10–1000 caracteres.
8. Guardar.

El backend guarda la auditoría y reenvía productos pendientes. Sólo un producto físico sin motivos bloqueantes podrá aprobarse automáticamente.

### Desactivar

La desactivación siempre está permitida, incluso si la tienda está suspendida o perdió KYC. Requiere razón y queda auditada.

Productos ya aprobados no se revocan por este switch. Para retirar publicación de inmediato:

- inactivar o suspender la tienda; o
- cambiar el estado del producto; o
- enviar el producto nuevamente a revisión.

### Cambio de `seller.user_type`

Toda actualización Eloquent ordinaria de `User.user_type` activa `SellerTypeModerationObserver` después del UPDATE. El observer ejecuta `SellerTypeRevalidationService` sincrónicamente. Su primera fase transaccional:

- bloquea seller y tiendas en orden estable;
- desactiva la confianza automática existente;
- vuelve inmediatamente a pending los productos aprobados y limpia su decisión;
- registra auditoría de sistema si la confianza estaba activa.

Con esa barrera ya persistida, una segunda fase llama `markForReview()` para aprobados/pendientes, crea versiones/snapshots y despacha una evaluación por producto. El contador `pending_products_resubmitted` avanza sólo por cada producto completado.

El storefront deja de exponer los productos del seller no-vendor desde la siguiente consulta porque `published()` revalida el tipo directamente. El evaluador también añade `seller_not_vendor`; regresar a `vendor` no reactiva confianza.

Para tiendas con muchos productos, programar el cambio en una ventana de baja actividad: la fase 1 mantiene locks sólo mientras asegura revocación/pending y la fase 2 remodera secuencialmente, por lo que la latencia crece con la cantidad de productos. Después, vigilar los jobs `EvaluateProductForApproval`.

Si falla la segunda fase, no reactivar confianza: los aprobados —incluidos soft-deleted— ya quedaron pending y sin fingerprint/revisión vigente. Revisar el log, comparar el contador de auditoría con los productos afectados y reintentar la remoderación controlada; puede haber productos pending cuya versión detallada todavía no se incrementó.

Al restaurar una tienda o producto después de una transición de tipo, confirmar que continúa sin confianza y pending; debe seguir el flujo normal de revisión, nunca recuperar la decisión antigua.

Para garantizar que el cambio de usuario y sus efectos se reviertan juntos, la operación que llama `User::save()` debe envolverlo en `DB::transaction()`. Sin esa transacción exterior el comportamiento sigue siendo fail-closed en dos fases: scope/riesgo bloquean al no-vendor inmediatamente y después el servicio confirma la revocación/remoderación en su propia transacción.

No modificar `users.user_type` con SQL directo, `User::query()->update()` ni `saveQuietly()`. Esas vías evitan observer, auditoría y remoderación. Mientras el tipo sea no-vendor las barreras de `published()` y riesgo lo rechazan, pero volver a vendor por la misma vía puede reexponer una aprobación antigua. Si ocurrió, desactivar confianza, reenviar productos mediante `SellerTypeRevalidationService` con el tipo anterior conocido y verificar manualmente auditoría/versiones antes de habilitar catálogo.

Los cambios de email y KYC sí afectan evaluación/publicación, pero también deben motivar una revisión administrativa de confianza.

### Respuesta idempotente

Si el valor solicitado ya está guardado y la tienda sigue siendo elegible:

- responde éxito con `changed = false`;
- no crea auditoría;
- no crea versión;
- no despacha jobs adicionales.

Excepción de seguridad: si se pide `enabled = true`, la base ya contiene true y la tienda dejó de ser elegible, no se acepta el no-op. El backend persiste primero la corrección —flag false, aprobados pending, decisión/fingerprint/riesgo limpios, auditoría admin y remoderación— y después responde 422 en `enabled`. La respuesta de error no significa que la revocación se haya revertido; recargar la tienda antes de reintentar.

## Operación de la cola

Jobs relevantes:

| Job | Cuándo se despacha | Comportamiento de fallo |
| --- | --- | --- |
| `EvaluateProductForApproval` | Después de `submit()`/`markForReview()`. | Producto sigue pending. Reintenta 3 veces. |
| `ReevaluateProductsAfterReferenceChange` | Update/delete de Brand, Category o Tag. | La referencia ya cambió, pero productos quedan en su estado anterior hasta reintento. |

La segunda fila implica una ventana asíncrona: hasta que el worker ejecute el job, un producto previamente aprobado puede seguir cumpliendo `published()` con la referencia nueva.

Una transición de `seller.user_type` no depende de un job para retirar publicación: revocación e invalidación ocurren sincrónicamente y sólo la evaluación de la nueva versión queda en cola.

Comandos útiles:

```bash
./vendor/bin/sail artisan queue:work --queue=default --tries=3 --timeout=150
./vendor/bin/sail artisan queue:restart
./vendor/bin/sail artisan queue:failed
```

Antes de reintentar un fallo, leer excepción y payload. Reintentar uno específico:

```bash
./vendor/bin/sail artisan queue:retry <uuid-del-job>
```

`afterCommit()` evita que el worker observe una versión que todavía no fue confirmada en base de datos.

## Monitoreo

### Productos pendientes antiguos

```sql
SELECT id, store_id, moderation_version, submitted_at,
       risk_level, risk_score, moderation_reason
FROM products
WHERE approved_status = 'pending'
ORDER BY submitted_at ASC;
```

Una cola grande puede significar:

- worker detenido;
- tienda sin confianza;
- producto digital;
- producto incompleto o con riesgo;
- job fallido;
- versión/fingerprint cambiado antes de evaluar.

### Historial por producto

```sql
SELECT product_id, version, status, source, risk_level,
       risk_score, submitted_at, reviewed_at,
       submission_reason, decision_reason
FROM product_approval_reviews
WHERE product_id = :product_id
ORDER BY version DESC;
```

### Auditoría de tienda

```sql
SELECT store_id, admin_id, previous_value, new_value,
       reason, pending_products_resubmitted, ip_address, created_at
FROM store_auto_approval_audits
WHERE store_id = :store_id
ORDER BY created_at DESC;
```

### Uso de almacenamiento digital

```sql
SELECT p.store_id,
       COUNT(pf.id) AS files,
       SUM(pf.size) AS bytes
FROM product_files pf
JOIN products p ON p.id = pf.product_id
GROUP BY p.store_id
ORDER BY bytes DESC;
```

### Jobs fallidos

```sql
SELECT uuid, failed_at, exception
FROM failed_jobs
ORDER BY failed_at DESC;
```

## Smoke tests posteriores

1. Vendor elegible crea un físico completo; queda pending y el job lo evalúa.
2. Tienda sin confianza conserva el producto pending con razón.
3. Admin activa confianza con razón y aparece auditoría.
4. Físico completo de tienda confiable puede pasar a approved.
5. Digital permanece pending.
6. Vendor cambia un aprobado; sube versión y vuelve a pending.
7. Admin intenta decidir desde una pestaña vieja; recibe 409.
8. Home, listado y detalle muestran el aprobado elegible y devuelven 404 para un slug no publicable.
9. Cambiar seller de `vendor` a otro tipo desactiva confianza, crea versión pending y retira el producto; volver a vendor no restaura confianza.
10. Producto aprobado pero tienda suspendida no aparece en una consulta `published()`.
11. Upload PDF válido termina en storage privado.
12. Nombre `..` o metadata inconsistente recibe 422 y no crea carpeta insegura.

## Troubleshooting

### El vendor recibe 403 en productos

Comprobar:

- `user_type = vendor`;
- email verificado;
- KYC `approved`;
- tienda existente;
- tienda `draft`, `pending` o `active`;
- `suspended_at IS NULL`;
- ownership del producto.

Después de cambiar email, perder acceso hasta verificar el nuevo correo es comportamiento esperado.

### El admin no ve la tarjeta de autoaprobación

Comprobar:

- el producto tiene tienda;
- el admin tiene `Store Auto-Approval Management`;
- el permiso usa guard `admin`;
- cache de Spatie limpia.

```bash
./vendor/bin/sail artisan permission:cache-reset
```

### La activación de confianza devuelve 422

La tienda debe estar activa, no suspendida, tener seller vendor, email verificado y KYC aprobado. La razón debe tener al menos 10 caracteres.

### Un producto permanece pending

Revisar en este orden:

1. worker activo;
2. `failed_jobs`;
3. interruptor global;
4. confianza de tienda;
5. `risk_reasons` de la revisión;
6. tipo digital;
7. imagen, categoría y precio vendible;
8. precios/stock de variantes;
9. versión y fingerprint.

### Producto aprobado no aparece en el catálogo

Comprobar todas las condiciones de `published()`:

- producto activo;
- versión revisada igual a versión actual;
- tienda activa y no suspendida;
- seller con `user_type = vendor`;
- email verificado;
- KYC aprobado.

En las rutas actuales, confirmar que la petición llega a `HomeController` o `ProductCatalogController`. En cualquier consumidor nuevo, confirmar además que la propia consulta llama `published()`.

### El admin recibe 409 al guardar

La versión cambió desde que abrió el formulario. Recargar, revisar el contenido nuevo y volver a decidir. No se debe forzar la versión desde DevTools.

### Upload digital devuelve 422

Comprobar:

- nombre y extensión declarada;
- UUID;
- índice, offset, conteo y tamaños;
- tamaño real del chunk;
- cuota de producto/tienda/uploader;
- MIME final permitido.

Un 429 corresponde al throttle de la ruta.

### Error de variantes

Revisar grupos, valores y combinaciones contra `config/products.php`. La operación inválida se revierte; no aumentar límites sin medir memoria, CPU y tamaño de tabla.

### Error de slug duplicado

Buscar incluyendo soft-deleted. La validación responde antes en el caso normal; una colisión concurrente puede llegar desde el índice único.

## Rollback

No tratar estas migraciones como completamente reversibles a nivel de datos:

- productos legacy no recuperan automáticamente `approved`;
- slugs renombrados no recuperan el valor anterior;
- eliminar tablas borra historial y auditoría.

Procedimiento seguro:

1. poner la aplicación en mantenimiento;
2. detener workers;
3. identificar batch exacto con `migrate:status` y la tabla `migrations`;
4. restaurar código compatible;
5. restaurar backup de base de datos si se necesita recuperar estados/slugs/historial;
6. restaurar storage si el incidente involucra archivos;
7. limpiar caches;
8. reiniciar workers y validar antes de levantar mantenimiento.

No ejecutar un rollback ciego por cantidad de pasos si el batch contiene migraciones ajenas.

En el entorno inspeccionado las migraciones aparecen en el mismo batch inicial, por lo que un rollback genérico podría afectar mucho más que este módulo. El rollback lógico preferido es:

1. establecer `PRODUCT_AUTOMATIC_APPROVAL_ENABLED=false`;
2. reconstruir config cache;
3. reiniciar workers;
4. desactivar confianza por tienda mediante una acción auditada cuando sea necesario;
5. conservar tablas, snapshots e historial mientras se investiga.

## Mantenimiento futuro

- Crear comando programado para reconciliar archivos y limpiar chunks sin actividad.
- Crear un reconciliador periódico de elegibilidad que detecte confianza/aprobaciones obsoletas cuando SQL masivo, `query()->update()`, `saveQuietly()` o eventos deshabilitados hayan omitido observers.
- Añadir malware scanning antes de distribuir archivos.
- Aplicar `published()` y una regresión HTTP a cada nuevo consumidor público.
- Revisar `failed_jobs` y productos pendientes antiguos con alertas.
- Conservar auditorías de confianza según la política de retención.
- Ejecutar pruebas de seguridad después de cambios de catálogo, storage, KYC o permisos.
