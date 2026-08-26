# Operación, despliegue y troubleshooting

[Volver al índice](README.md)

Este runbook cubre moderación de Product y Store, elegibilidad de seller/KYC,
archivos digitales y medios privados de Product. No presupone que una base
concreta esté migrada ni que la suite global esté verde: antes de producción se
deben registrar los resultados del entorno de destino.

## Prerrequisitos

- backup recuperable de base de datos, **public/uploads** y storage privado;
- PHP 8.3 y extensiones usadas por Laravel, DOM y fileinfo;
- tablas **jobs**, **job_batches**, **failed_jobs**, **cache** y
  **cache_locks** disponibles si se usan los defaults database;
- un worker supervisado y un scheduler ejecutándose;
- cache compartido entre nodos para **ShouldBeUnique**, **withoutOverlapping**
  y **onOneServer**;
- usuario web y CLI con lectura/escritura sobre **storage/app/private** y
  **storage/framework**;
- límites PHP y proxy superiores al chunk configurado;
- permisos administrativos sembrados y asignados;
- **APP_ENV=production**, **APP_DEBUG=false** y APP_KEY propia.

**artisan down** no detiene workers. Con varias réplicas, el maintenance driver
file tampoco coordina todos los nodos; retirar tráfico en el balanceador o usar
un store de mantenimiento compartido.

## Variables de entorno exactas

### Moderación y cola

| Variable | Default efectivo | Uso |
| --- | ---: | --- |
| PRODUCT_AUTOMATIC_APPROVAL_ENABLED | true | Kill switch global de autoaprobación. |
| PRODUCT_MAXIMUM_AUTOMATIC_RISK_SCORE | 20 | Score máximo automático. |
| QUEUE_CONNECTION | database | Backend de jobs. |
| CACHE_STORE | database | Locks de jobs únicos y scheduler. |
| DB_QUEUE_RETRY_AFTER | 180 en .env.example | Reserva database; el fallback de config es 90 y no es seguro para el job de 120 s. |

**ReevaluateProductsAfterReferenceChange** tiene timeout 120 s y
**EvaluateProductForApproval** 60 s. El fallback 90 de
**DB_QUEUE_RETRY_AFTER** en config es insuficiente para el job de 120 s.
En producción database debe conservarse explícitamente:

~~~dotenv
QUEUE_CONNECTION=database
CACHE_STORE=database
DB_QUEUE_RETRY_AFTER=180
~~~

El timeout del worker debe ser mayor que 120 s y menor que retry_after; por
ejemplo **--timeout=150** con retry_after 180.

### Storage privado

| Variable | Default | Uso |
| --- | --- | --- |
| PRODUCT_MEDIA_DISK | private | Imágenes de Product. |
| PRODUCT_MEDIA_ALLOWED_DISKS | private | Allowlist separada por comas. |
| PRODUCT_DIGITAL_UPLOAD_DISK | private | Archivos digitales finales. |
| PRODUCT_DIGITAL_ALLOWED_DISKS | private | Allowlist separada por comas. |

Ambos servicios rechazan el disco si:

- no está en su allowlist o no existe en **filesystems.disks**;
- tiene visibility public;
- tiene **serve=true**;
- no tiene **throw=true**;
- el driver no es local o s3;
- siendo local, su root está dentro de **public** o
  **storage/app/public**.

El disco **private** incluido cumple el contrato: root
**storage/app/private**, visibility private, serve false y throw true. El disco
**local** incluido no lo cumple porque tiene serve true y throw false. El
**s3** incluido tampoco pasa mientras conserve throw false; cambiar sólo el
env a s3 no basta: la configuración desplegada debe hacerlo fail-fast y el
bucket debe ser privado.

**storage/app/private** debe existir antes de atender tráfico: el servicio de
media local exige un root existente. Los chunks se ensamblan siempre en
**storage/app/private/chunks**, incluso si el objeto final usa s3; el servicio
crea esa carpeta con modo 0700, pero el proceso necesita permiso sobre su
padre. El symlink de **storage:link** no se usa para Product media ni archivos
digitales.

Store logo/banner permanecen aparte en **public/uploads/stores**.

### Límites digitales

| Variable | Default |
| --- | ---: |
| PRODUCT_MAX_DIGITAL_FILE_SIZE_KB | 262144 |
| PRODUCT_MAX_DIGITAL_CHUNK_SIZE_KB | 10240 |
| PRODUCT_MAX_DIGITAL_CHUNKS | 4096 |
| PRODUCT_MAX_ACTIVE_DIGITAL_UPLOADS | 3 |
| PRODUCT_DIGITAL_UPLOAD_TTL_HOURS | 24 |
| PRODUCT_MAX_DIGITAL_FILES_PER_PRODUCT | 20 |
| PRODUCT_MAX_DIGITAL_SIZE_PER_PRODUCT_KB | 1048576 |
| PRODUCT_MAX_DIGITAL_SIZE_PER_STORE_KB | 5242880 |

Si cambia el chunk máximo, ajustar **upload_max_filesize**,
**post_max_size**, reverse proxy y timeouts. El proxy recibe un chunk, no el
archivo completo.

### Variantes

| Variable | Default |
| --- | ---: |
| PRODUCT_MAX_VALUES_PER_ATTRIBUTE | 50 |
| PRODUCT_MAX_ATTRIBUTE_GROUPS | 6 |
| PRODUCT_MAX_VARIANT_COMBINATIONS | 500 |

## Efectos de migraciones

Revisar siempre **php artisan migrate:status**. Las migraciones relevantes son:

| Migración | Efecto no trivial |
| --- | --- |
| 2026_08_15_155959_add_is_active_to_stores | Añade el flag y deja todas las Stores legacy inactivas. |
| 2026_08_15_160000_add_moderation_fields_to_products_and_stores | Añade versionado de Product/trust y mueve aprobados legacy a pending. |
| 2026_08_15_160001_create_product_approval_reviews_table | Crea snapshots/versiones de revisión. |
| 2026_08_15_160002_enforce_unique_product_slugs | Renombra duplicados posteriores y crea products_slug_unique. |
| 2026_08_16_000000_create_store_auto_approval_audits_table | Crea auditoría de trust. |
| 2026_08_17_113849_add_store_approval_fields_to_stores | Añade aprobador, rechazo y motivo. |
| 2026_08_17_120000_drop_disk_from_product_files_table | Elimina disk como autoridad de borrado. |
| 2026_08_18_000001_add_store_moderation_columns_to_stores | Añade versionado y mueve Stores approved legacy a pending/inactive. |
| 2026_08_18_000002_create_store_approval_reviews_table | Crea historial de Store. |
| 2026_08_18_000003_add_product_evaluation_context | Separa contenido/contexto y vuelve pending los Products approved. |
| 2026_08_18_000004_add_sha256_to_product_files_table | Añade hash nullable que debe rellenarse. |
| 2026_08_24_000001_add_eligibility_generations | Añade epochs/pins y deshabilita todos los grants legacy. |
| 2026_08_24_000002_create_seller_eligibility_events_table | Crea la traza idempotente de cambios de elegibilidad; el flujo de aplicación inserta, pero el modelo no impone inmutabilidad frente a SQL directo. |
| 2026_08_24_000100_create_product_moderation_events_table | Crea el ledger de eventos de Product, append-only en la capa Eloquent. |

Los métodos down no revierten los cambios de datos: no restauran aprobaciones,
actividad, trust ni slugs anteriores. Bajar tablas borra historial. Bajar
**sha256** pierde hashes; bajar epochs elimina pins. Ninguna migración devuelve
al árbol público los medios ya movidos al disco privado.

## Preflight

1. Confirmar backup y un restore ensayado.
2. Registrar **migrate:status** y batches actuales.
3. Ejecutar **migrate --pretend --no-interaction** contra el mismo motor y
   schema de producción.
4. Contar Stores/Products por estado, hashes nulos y grants activos.
5. Confirmar espacio en storage privado y acceso al disco final.
6. Confirmar que **storage/app/private** existe y no es servible por HTTP.
7. Confirmar scheduler, cache compartido y supervisor de cola.
8. Pausar escrituras, uploads y workers antes de migrar.
9. Inventariar medios Product legacy y distinguir
   **public/uploads/stores**, que no se migra.
10. Ejecutar pruebas focalizadas y guardar el resultado; no inferir el estado
    de la suite global desde este documento.

Consultas útiles:

~~~sql
SELECT status, is_active, COUNT(*) FROM stores GROUP BY status, is_active;
SELECT approved_status, COUNT(*) FROM products GROUP BY approved_status;
SELECT COUNT(*) AS missing_sha256 FROM product_files WHERE sha256 IS NULL;
SELECT COUNT(*) AS trusted_stores FROM stores WHERE auto_approve_products = 1;
SELECT slug, COUNT(*) FROM products GROUP BY slug HAVING COUNT(*) > 1;
~~~

## Despliegue con ventana de mantenimiento

El orden recomendado es:

1. retirar tráfico/activar mantenimiento en la release anterior;
2. detener el gestor de workers y esperar jobs activos;
3. tomar backup final;
4. desplegar código y env nuevos;
5. crear/verificar directorios privados y credenciales;
6. inspeccionar SQL pretend y ejecutar migraciones;
7. sembrar permisos;
8. migrar media Product pública a privada;
9. rellenar hashes digitales;
10. ejecutar una reconciliación de elegibilidad;
11. reconstruir caches;
12. reiniciar y reanudar workers/scheduler;
13. hacer smoke tests antes de devolver tráfico.

Comandos de la release nueva:

~~~bash
php artisan migrate:status
php artisan migrate --pretend --no-interaction
php artisan migrate --force --no-interaction

php artisan db:seed --class='Database\Seeders\Admin\PermissionSeeder' --force
php artisan permission:cache-reset
~~~

Si el deploy usa Sail, prefijar los comandos con **./vendor/bin/sail**. En un
release basado en symlinks, ejecutar desde el directorio de la release nueva,
no desde un path que el swap vaya a eliminar.

## Migración de media Product a storage privado

El comando procesa sólo filas de **product_images**:

~~~bash
php artisan products:migrate-media-private --dry-run
php artisan products:migrate-media-private
~~~

El dry-run:

- normaliza cada path y valida el MIME real del archivo público;
- muestra **Would migrate** sin escribir ni borrar;
- considera correcto que el objeto ya exista en privado;
- marca error si no existe ni la copia pública ni la privada;
- termina con código distinto de cero si hubo fallos.

La ejecución real bloquea Product e imagen, copia por stream al disco privado,
compensa la copia si la transacción revierte y sólo después elimina la copia
pública. El path de base de datos no cambia, por lo que no cambia por sí mismo
el fingerprint de contenido. El comando es reentrante: si sólo existe la copia
privada, no la vuelve a escribir.

No abrir tráfico si **failed > 0**. Repetir el dry-run después de la ejecución:
debe quedar sin errores. **Migrated: 0** en ese segundo dry-run es normal.

El comando ignora archivos huérfanos sin fila en **product_images**. Inventariar
los archivos planos restantes:

~~~bash
find public/uploads -maxdepth 1 -type f ! -name '.gitkeep' -print
~~~

No borrar a ciegas: clasificar cada huérfano y conservar
**public/uploads/stores**. Ningún path referenciado por ProductImage debe seguir
teniendo una copia directamente servible bajo public.

Las imágenes se entregan por **product-media.show**. La ruta responde sólo si
el Product está publicado o el solicitante es owner/admin autorizado y añade
no-store y nosniff.

## Backfill de hashes digitales

Después de migrar schema y verificar el disco configurado:

~~~bash
php artisan products:backfill-file-hashes --batch=100
~~~

El comando incluye Products soft-deleted, abre cada objeto por stream, calcula
SHA-256, actualiza tamaño y hash y manda el Product a revisión. No tiene modo
dry-run. Captura errores por archivo y continúa; por ello su código de salida
puede ser éxito aunque haya advertencias. Guardar stdout/stderr y comprobar:

~~~sql
SELECT id, product_id, path
FROM product_files
WHERE sha256 IS NULL
ORDER BY product_id, id;
~~~

No abrir descargas ni considerar terminado el backfill mientras queden hashes
nulos sin una excepción documentada. Si cambió el disco configurado, primero
demostrar que todos los paths existen en ese mismo disco.

## Reconciliación de elegibilidad

Ejecución manual inicial:

~~~bash
php artisan security:reconcile-eligibility --chunk=200
~~~

El chunk se limita a 1–1000. El comando:

- encuentra KYC approved cuyo expiry es anterior al día local;
- incrementa su epoch una sola vez por fecha expirada;
- guarda **expiration_reconciled_for**;
- revoca trust y registra evento/auditoría;
- detecta grants activos cuyo snapshot ya no es elegible y los revoca.

**routes/console.php** lo programa hourly con **withoutOverlapping** y
**onOneServer**. Producción debe ejecutar **php artisan schedule:run** cada
minuto, o un **schedule:work** supervisado. En varias réplicas, CACHE_STORE debe
ser compartido y soportar locks; un cache local por nodo rompe la garantía.

La reconciliación es red de seguridad, no sustituto de observers. No detecta de
forma fiable una secuencia ABA ejecutada enteramente con SQL directo.

## Caches, workers y scheduler

Después de cambiar código/env:

~~~bash
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan permission:cache-reset
php artisan queue:restart
~~~

**queue:restart** sólo envía la señal por cache; no inicia procesos. El gestor
Supervisor/systemd/Horizon debe arrancarlos de nuevo y mantenerlos vivos.
Ejemplo de worker para el backend default:

~~~bash
php artisan queue:work --queue=default --tries=3 --timeout=150
~~~

Confirmar que usa la release nueva, procesa un job de prueba y respeta
retry_after. Reiniciar también el proceso persistente de scheduler si se usa
**schedule:work**.

## Smoke tests antes de abrir tráfico

1. Store pending sólo acepta approve/reject con versión actual.
2. Una decisión stale devuelve 409 y no cambia Store.
3. Suspend sólo acepta approved; restore sólo suspended.
4. Store approved pero inactive no publica Products.
5. KYC expira al día siguiente de su fecha local y revoca trust.
6. Cambiar email/tipo/KYC rota epoch; volver al valor anterior no revive pins.
7. Product aprobado con pin stale no aparece en home/listado/detalle.
8. Imagen Product pública/owner/admin responde; no autorizada responde 404.
9. No existe copia pública del medio Product migrado.
10. Upload digital crea SHA-256 en el disco allowlisted y privado.
11. Worker procesa evaluación y el scheduler registra reconciliaciones.

Pruebas focalizadas disponibles, sin afirmar resultado por adelantado:

~~~bash
php artisan test tests/Feature/Security/StoreModerationTest.php
php artisan test tests/Feature/Security/StoreProfileAtomicityTest.php
php artisan test tests/Feature/Security/KycEligibilityEpochTest.php
php artisan test tests/Feature/Security/KycExpirationReconciliationTest.php
php artisan test tests/Feature/Security/ProductMediaAuthorizationTest.php
php artisan test tests/Feature/Security/BackfillProductFileHashesTest.php
php artisan test tests/Feature/Database/ModerationMigrationCompatibilityTest.php
~~~

Ejecutar además la suite completa del release y clasificar cualquier fallo como
causado, expuesto o preexistente antes del go/no-go.

## Monitoreo

### Cola y jobs

~~~bash
php artisan queue:failed
~~~

Alertar por jobs antiguos, crecimiento de **jobs**, fallos de
**EvaluateProductForApproval**, **EvaluateProductApprovalContext** y
**ReevaluateProductsAfterReferenceChange**, y ausencia de workers.

### Moderación y elegibilidad

~~~sql
SELECT id, store_id, moderation_version, submitted_at, moderation_reason
FROM products
WHERE approved_status = 'pending'
ORDER BY submitted_at;

SELECT id, product_id, version, event_type, occurred_at
FROM product_moderation_events
ORDER BY occurred_at DESC;

SELECT id, user_id, trigger, user_epoch, kyc_epoch, eligible, occurred_at
FROM seller_eligibility_events
ORDER BY occurred_at DESC;

SELECT id, user_id, document_expiry_date, expiration_reconciled_for
FROM kycs
WHERE status = 'approved'
  AND document_expiry_date < :today
  AND (expiration_reconciled_for IS NULL
       OR expiration_reconciled_for <> document_expiry_date);

SELECT id, seller_id
FROM stores
WHERE auto_approve_products = 1
  AND (
    auto_approval_user_epoch IS NULL
    OR auto_approval_kyc_id IS NULL
    OR auto_approval_kyc_epoch IS NULL
    OR auto_approval_store_epoch IS NULL
    OR auto_approval_store_epoch <> eligibility_epoch
  );
~~~

La última consulta es sólo un chequeo estructural parcial: detecta pins nulos y
el epoch propio de Store, pero no compara los pins de User/KYC. La comprobación
autoritativa es `SellerEligibilityService::storeSnapshot()` —que compara los
cuatro pins— y el reconciliador; no use esa SQL aislada para declarar un grant
vigente.

Usar para **:today** la fecha de **config('app.timezone')**, no asumir que
CURRENT_DATE de la base comparte zona horaria.

### Storage

- ejecutar periódicamente el dry-run de media y alertar por errores;
- alertar por **product_files.sha256 IS NULL**;
- monitorear uso de **storage/app/private**, chunks antiguos y bucket;
- reconciliar objetos sin fila y filas sin objeto;
- revisar que no reaparezcan copias Product bajo public.

No borrar chunks activos por edad sin respetar locks y metadata del upload; el
repositorio aún no incluye un comando de limpieza de chunks.

## Troubleshooting

### Product image devuelve 404

Comprobar, en orden:

1. autorización: Product publicado, owner o admin con Product Management;
2. fila ProductImage y asociación actual al Product;
3. config cache y valores PRODUCT_MEDIA_DISK/PRODUCT_MEDIA_ALLOWED_DISKS;
4. disco private, fail-fast y root existente;
5. objeto presente con el mismo path;
6. MIME permitido.

No restaurar la disponibilidad copiando el archivo a public.

### El migrador de media falla

- **Neither ... exists**: restaurar desde backup o corregir el objeto faltante;
- MIME no permitido: poner el registro en cuarentena y revisar el contenido;
- fallo al borrar public: mantener mantenimiento, corregir permisos y repetir;
- disco no allowlisted/private: corregir config, limpiar cache y reintentar.

### Un Product aprobado no aparece

Verificar estado active, tipo physical/digital conocido, versión revisada,
policy version, Store approved/active/no suspendida, seller vendor/verificado,
KYC vigente y todos los pins de epochs. **approved_status** por sí solo no es
evidencia de publicación.

### Trust no se puede habilitar

La Store debe estar approved, active, con revisión vigente, no suspendida y con
seller/KYC elegibles. El grant anterior puede haberse revocado antes de
devolver 422; recargar la Store y revisar la auditoría.

### KYC parece expirar un día antes/después

Comparar el valor efectivo de **config('app.timezone')**, la fecha persistida y
el día calculado por **SellerEligibilityService::today()**. La fecha almacenada
es inclusiva. Reiniciar procesos persistentes después de cambiar la
configuración de timezone o su cache.

### Hash backfill terminó pero quedan nulos

El comando continúa tras errores individuales. Revisar warnings, existencia y
permisos del objeto en PRODUCT_DIGITAL_UPLOAD_DISK y repetir sólo después de
corregir la causa.

### Decisión admin no se aplica

Una versión stale devuelve 409. Una transición inválida produce conflicto de
dominio: reject/suspend propagan 409, mientras approve/restore muestran alerta
y redirect en la UI actual. En todos los casos, recargar y no alterar
**moderation_version** manualmente.

## Rollback y contención

No usar **migrate:rollback --step=N** a ciegas: un batch puede incluir
migraciones ajenas y los down no reconstruyen datos. Un rollback completo
requiere:

1. mantenimiento y workers detenidos;
2. batch exacto identificado;
3. código compatible con el schema restaurado;
4. restore de base para recuperar estados, slugs, pins e historiales;
5. restore coordinado de storage público/privado;
6. caches reconstruidos;
7. smoke tests antes de tráfico.

El migrador de media elimina copias públicas; bajar código no las recrea.
Restaurar sólo base sin storage deja referencias rotas, y restaurar sólo
storage puede reexponer archivos.

Como contención lógica, sin bajar schema:

~~~dotenv
PRODUCT_AUTOMATIC_APPROVAL_ENABLED=false
~~~

Después ejecutar **config:cache**, **queue:restart** y verificar workers. Esto
detiene nuevas autoaprobaciones, pero no sustituye una investigación ni corrige
datos ya inconsistentes.
