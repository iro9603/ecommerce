# Cambios de seguridad y problemas resueltos

[Volver al índice del módulo](README.md)

## Propósito

Esta guía explica qué vulnerabilidad o fallo existía, cómo se corrigió, qué prueba demuestra la corrección y qué consideración permanece.

Las severidades describen el impacto potencial antes de aplicar los cambios.

## Resumen

| Área | Severidad original | Qué se resolvió | Estado |
| --- | --- | --- | --- |
| Traversal y borrado recursivo | Crítica | Rutas aisladas, nombre hash y borrado canónico restringido. | Corregido y probado. |
| XSS almacenado | Alta | Allowlist HTML, sanitización en requests y escape de textarea. | Corregido y probado. |
| Ownership/IDOR/TOCTOU | Crítica | ProductPolicy, locks y reautorización transaccional. | Corregido y probado. |
| Bypass de moderación | Crítica | Versiones, snapshot, fingerprint, historial y versión esperada. | Corregido y probado. |
| Jobs atrasados | Alta | Job único que verifica versión y fingerprint dos veces. | Corregido y probado. |
| Vendor controlando campos admin | Alta | Requests separados y campos prohibidos. | Corregido. |
| Uploads disfrazados y abuso de disco | Alta | MIME real, manifests, cuotas, locks, TTL y storage privado. | Corregido y probado. |
| Publicación parcial | Alta | Scope `published()` aplicado a home, listado y detalle. | Corregido y probado. |
| Slugs duplicados por carrera | Media/Alta | Normalización backend e índice único de base de datos. | Corregido y probado. |
| Atributos compartidos | Alta | Se impide mutar una definición usada por otro producto. | Corregido y probado. |
| Email cambiado pero aún verificado | Alta | Se invalida verificación y se reenvía notificación. | Corregido y probado. |
| Explosión cartesiana de variantes | Alta | Límites de grupos, valores y combinaciones. | Corregido y probado. |
| Referencias cambiadas después de aprobar | Alta | Snapshot de definiciones y reenvío mediante observer/job. | Corregido y probado. |
| Autoaprobación sin control | Alta | Opt-in por tienda, permiso dedicado y auditoría. | Corregido y probado. |
| Seller deja de ser vendor | Crítica | Revalidación transaccional, revocación de confianza y barreras independientes. | Corregido y probado. |
| Borrado de archivos dirigido por columna de BD | Alta | Un único disco configurado gobierna escritura y borrado; la columna `disk` se elimina. | Corregido y probado. |

## 1. Traversal y borrado recursivo

### Problema original

El upload digital construía la carpeta temporal a partir del nombre enviado por el cliente y después llamaba a un borrado recursivo.

Un nombre manipulado como `..` podía resolver hacia el directorio padre. En el escenario crítico, el borrado alcanzaba `storage/app/private`, donde podían coexistir productos digitales y documentos privados.

El helper genérico de borrado tampoco comprobaba de forma canónica que el destino siguiera dentro de `public/`.

### Solución

`DigitalProductChunkUploadRequest`:

- exige UUID y nombre seguro;
- rechaza `/`, `\`, controles, `.`, `..` y nombres formados sólo por puntos;

`DigitalProductFileUploadService`:

- vuelve a validar que el nombre no sea vacío, `.`, `..`, traversal o controles;
- calcula la carpeta como SHA-256 de uploader, producto y UUID;
- sólo acepta carpetas con 64 caracteres hexadecimales;
- comprueba que el padre real sea exactamente el root de chunks;
- usa permisos `0700`;
- sólo entonces permite `deleteDirectory()`.

`FileUploadTrait::deleteFile()`:

- normaliza la entrada como ruta relativa y rechaza ruta vacía, traversal y directorios;
- resuelve `realpath()`;
- comprueba que el archivo real esté dentro de `public_path()`;
- elimina únicamente archivos regulares.

Los controladores digitales también verifican:

- que el archivo pertenezca al producto;
- que la ruta sea relativa;
- que empiece por `uploads/` o `product-files/`;
- que el disco de borrado coincida con `config('products.digital_upload.disk')`;
- que el disco de borrado nunca provenga de una columna de base de datos.

### Evidencia

- `DigitalProductFileUploadServiceTest` comprueba que la limpieza rechaza un directorio fuera del root.
- El mismo test rechaza un nombre traversal antes de crear carpeta.
- `FileUploadTraitTest` conserva un sentinel fuera de `public/`.

### Riesgo residual

Si la fila de base de datos se elimina pero el storage falla después del commit, puede quedar un archivo huérfano. No permite traversal, pero consume espacio. Conviene implementar en el futuro una reconciliación periódica entre `product_files` y storage.

## 2. XSS almacenado

### Problema original

`short_description` y `description` aceptaban HTML sin sanitizar. Las vistas de edición insertaban esos valores con salida sin escape dentro de un `<textarea>`.

Un vendor podía enviar un payload como:

```html
</textarea><img src=x onerror=alert(document.cookie)>
```

El código podía ejecutarse cuando un administrador abría el producto.

### Solución

`ProductContentSanitizer` analiza el HTML con DOM y sólo conserva:

```text
a, b, blockquote, br, code, em, h2, h3, h4, hr, i, li, ol,
p, pre, s, span, strong, table, tbody, td, th, thead, tr, u, ul
```

Elementos como `script`, `iframe`, `object`, `embed`, `form`, `style`, `svg`, `textarea`, audio y video se eliminan con todo su contenido.

Reglas adicionales:

- se eliminan comentarios y processing instructions;
- se eliminan atributos no incluidos en la allowlist;
- los links sólo aceptan `http`, `https`, `mailto`, `tel`, anchors o rutas relativas;
- se rechazan URLs protocol-relative `//host`;
- `_blank` fuerza `rel="noopener noreferrer"`;
- los requests admin y vendor sanitizan antes de validar y guardar;
- las vistas usan `{{ }}` en `textarea`;
- al editar contenido legacy también se sanea defensivamente en memoria;
- la migración devuelve aprobaciones legacy a revisión.

### Evidencia

- `ProductContentSanitizerTest` cubre escape de textarea, atributos ejecutables y contenido vacío.
- `StoredProductXssTest` guarda payload malicioso y confirma que la edición admin no lo ejecuta ni lo imprime sin escape.

### Riesgo residual

- El sanitizador no sustituye escape contextual ni una Content Security Policy.
- Escrituras directas por SQL pueden saltarlo.
- Una consulta que omita `published()` puede recuperar contenido legacy pendiente y exponerlo si una vista posterior lo renderiza como HTML sin escape o sanitización.
- Si se amplía mucho el rich text conviene adoptar una librería especializada y añadir fuzzing.

## 3. Separación de requests admin/vendor

### Problema original

El vendor compartía el contrato de entrada del admin y podía enviar datos que pertenecen a otro límite de confianza, por ejemplo tienda, aprobación o flags promocionales.

### Solución

Se crearon requests vendor separados. Prohíben explícitamente:

```text
store, store_id, product_type, approved_status,
is_featured/featured, is_hot/hot, is_new/new,
submitted_at, approved_at, approved_by,
moderation_reason, risk_level, risk_score,
moderation_version, reviewed_version
```

El controlador obtiene `store_id` de `request()->user()->store` y el tipo de una ruta limitada a `physical` o `digital`.

El admin conserva un request propio que puede manejar tienda, promoción y decisión.

### Evidencia

Las pruebas de policy y actualización posterior comprueban la frontera funcional. La persistencia se hace con asignación explícita, no mediante `$request->all()`.

### Riesgo residual

Debe añadirse una prueba HTTP específica que envíe todos los campos prohibidos y espere `422` sin mutaciones. Todo controlador futuro debe persistir sólo datos validados y no introducir mass assignment del request completo.

## 4. Ownership, IDOR y carrera TOCTOU

### Problema original

Las comprobaciones estaban dispersas. Algunas operaciones sobre imágenes, archivos o atributos podían confiar en IDs sin validar ownership de manera central.

Además, autorizar antes de una transacción no era suficiente. Si un admin reasignaba `store_id` entre el check y el `save`, un modelo stale podía permitir que el dueño anterior modificara un producto ajeno.

### Solución

`ProductPolicy` centraliza las abilities. Exige:

- usuario vendor;
- email verificado;
- KYC aprobado;
- tienda `draft`, `pending` o `approved`;
- tienda no suspendida;
- coincidencia entre producto y tienda del usuario.

Cada mutación vendor:

1. realiza autorización temprana cuando aplica;
2. abre transacción;
3. vuelve a cargar el producto con `lockForUpdate()`;
4. vuelve a autorizar el modelo bloqueado;
5. bloquea el recurso hijo;
6. confirma que sigue relacionado con ese producto;
7. muta y remodera dentro de la transacción.

El upload de imagen elimina el archivo físico si la autorización transaccional o la moderación fallan.

### Evidencia

- `ProductPolicyTest` cubre owner, otra tienda, cliente, KYC, email, onboarding y suspensión.
- `VendorProductMutationToctouTest` usa un producto stale y confirma que el upload de imagen y el delete quedan bloqueados y que el archivo físico se compensa.
- `VendorSharedAttributeIsolationTest` cubre contaminación entre tiendas.

### Riesgo residual

Toda nueva mutación debe repetir el patrón lock + reautorización. Una policy evaluada únicamente en middleware o FormRequest no cierra TOCTOU.

## 5. Bypass de moderación después de aprobar

### Problema original

`approved_status = approved` no demostraba qué contenido había sido revisado. Un vendor podía modificar datos, imágenes, archivos, atributos o variantes después de la aprobación y conservar el estado.

También era posible que un formulario admin o job atrasado decidiera una versión diferente.

### Solución

- Cada envío tiene `moderation_version`.
- Cada decisión registra `reviewed_version`.
- El snapshot captura todo el contenido material.
- El fingerprint vincula la decisión al snapshot.
- Un cambio crea versión N+1 y limpia aprobación.
- Revisiones pendientes anteriores quedan `superseded`.
- Admin envía `expectedVersion`; una pestaña obsoleta recibe 409.
- El job se identifica por producto + versión y compara hash antes y después del lock.
- Imágenes, archivos, atributos, variantes, stock, estado y taxonomías forman parte del ciclo.

### Evidencia

- `ProductModerationServiceTest` valida versiones y fingerprints.
- `VendorProductPostApprovalUpdateTest` confirma que un cambio vendor vuelve a pending.
- `AdminProductMutationModerationTest` cubre subrecursos admin.
- `ProductReferenceModerationTest` cubre Brand directamente. Category y Tag comparten el observer, pero todavía no tienen un caso propio.
- `ProductSlugUpdateTest` cubre revisión admin stale.

### Riesgo residual

- SQL directo o nuevos controladores que omitan el servicio pueden romper la invariante.
- El snapshot de archivo registra metadata, no hash de bytes; el storage debe considerarse inmutable desde la aplicación.
- Un admin con `Product Management` puede modificar y aprobar en el mismo flujo. Queda auditado, pero no existe separación de funciones de cuatro ojos.

## 6. Autoaprobación insegura o sin consentimiento

### Problema original

Automatizar sólo a partir de un estado simple podía aprobar tiendas no verificadas, productos incompletos o contenido cuya versión cambió. Tampoco existía una acción auditada para confiar en una tienda.

### Solución

La autoaprobación requiere todas las capas:

1. interruptor global habilitado;
2. `store.auto_approve_products = true`;
3. tienda activa y no suspendida;
4. al activar la confianza se comprobó seller vendor, email verificado y KYC aprobado;
5. versión y fingerprint vigentes;
6. producto con precio vendible, categoría e imagen;
7. precios, stock, variantes y markup válidos;
8. ausencia de cualquier motivo bloqueante;
9. score menor o igual al umbral.

Los digitales siempre añaden un motivo bloqueante.

La confianza por tienda:

- usa permiso dedicado;
- exige razón;
- guarda admin, IP, user-agent y snapshot de elegibilidad;
- es idempotente mientras la confianza almacenada siga siendo elegible;
- un enable idempotente pero inelegible revoca confianza obsoleta, invalida productos y responde 422 después de persistir la corrección;
- permite desactivar incluso cuando la tienda es inelegible;
- reenvía productos pendientes con la configuración nueva.

La elegibilidad del tipo de cuenta ya no depende sólo de la activación inicial:

- `ProductRiskEvaluator` recarga seller/KYC y bloquea con `seller_not_vendor`;
- `published()` exige `users.user_type = vendor` en cada consulta;
- el fingerprint incorpora `seller_user_type`;
- `SellerTypeModerationObserver` llama `SellerTypeRevalidationService` en cada transición;
- la primera fase del servicio desactiva confianza, fuerza aprobados a pending y audita antes de generar snapshots;
- la segunda fase remodera aprobados/pendientes uno por uno e incrementa el contador de auditoría;
- tiendas y productos soft-deleted se invalidan también, para que restaurarlos no recupere confianza ni aprobación;
- el fast-path exige una review pending y no puede reutilizar una review aprobada en una transición ABA;
- volver a `vendor` no recupera confianza ni aprobación automáticamente.

### Evidencia

- `EvaluateProductForApprovalTest` cubre tienda confiable, no confiable y producto sin precio vendible.
- `StoreAutoApprovalControlTest` cubre permiso, elegibilidad, auditoría, idempotencia, reenvío y revocación fail-closed de un enable obsoleto.
- `SellerTypeRevalidationTest` cubre revocación sincrónica, nueva versión, auditoría, job obsoleto, retorno a vendor y rollback atómico.

### Riesgo residual

Desactivar la confianza no revoca productos ya aprobados. Para retirada inmediata debe suspenderse/inactivarse la tienda o cambiar el estado del producto.

La excepción es una transición Eloquent de `seller.user_type`: el evento revoca confianza y fuerza aprobados a pending en una primera transacción; sólo después genera snapshots/versiones individualmente. Si esa segunda fase falla, puede quedar incompleta, pero no reaparece una aprobación previa. Como el observer es `updated`, la atomicidad con el UPDATE de `users` requiere que el caller envuelva el save en `DB::transaction()`; sin ella, scope/riesgo cierran publicación antes de los efectos secundarios.

SQL directo, `query()->update()` y `saveQuietly()` no disparan el observer. Mientras el seller sea no-vendor, `published()` y el evaluador lo bloquean, pero una transición ABA por esas vías puede volver a `vendor` sin revocación auditada ni remoderación. Los cambios de tipo deben pasar por la operación controlada y acompañarse de revisión manual si se detectó un bypass.

## 7. Publicación pública obligatoria

### Problema original

Filtrar sólo por `approved_status` podía mostrar:

- producto inactivo;
- tienda inactiva o suspendida;
- versión nueva aún no revisada;
- seller que ya no es vendor;
- vendedor con email sin verificar;
- vendedor sin KYC aprobado.

### Solución

`Product::published()` exige todas esas condiciones y que `reviewed_version === moderation_version`.

Los consumidores públicos presentes usan el scope desde el inicio de la consulta:

- `HomeController::index()`: 12 novedades en `/`;
- `ProductCatalogController::index()`: catálogo paginado en `/products`;
- `ProductCatalogController::show()`: detalle por slug en `/products/{slug}`, con 404 si el producto no es publicable.

El detalle no usa route model binding de `Product`: busca el slug expresamente dentro de `published()`, por lo que volver el producto pending o cambiar la elegibilidad del seller lo retira en la siguiente petición.

### Evidencia

`PublishedProductScopeTest` cubre estado de producto, tienda, suspensión, versionado, email y KYC. `SellerTypeRevalidationTest` prueba la barrera de identidad. `PublicProductCatalogTest` verifica home, listado, detalle, exclusión inmediata, 404 por acceso directo y que el workspace vendor conserva sus borradores.

### Riesgo residual

El scope es local, deliberadamente no global, para no ocultar pendientes/rechazados a admin y vendor. Por tanto, cada consumidor público futuro debe usar:

```php
Product::query()->published()
```

La integración actual cubre home, listado y detalle. Búsqueda, API, carrito, checkout o cualquier endpoint futuro deben añadir el mismo contrato y su regresión HTTP antes de desplegarse.

## 8. Uploads, MIME y agotamiento de disco

### Problema original

- extensión y nombre controlados por el cliente;
- chunks inconsistentes;
- colisión entre vendors;
- tamaño total declarado falso;
- uploads y archivos ilimitados;
- producto cartesiano y archivos capaces de agotar recursos.

### Solución de archivos digitales

El request valida:

- UUID;
- índice y total de chunks;
- offset esperado;
- tamaño declarado y tamaño real de cada chunk;
- conteo calculado a partir de tamaño total/chunk;
- tamaño máximo.

El servicio:

- mantiene manifest inmutable;
- usa locks de metadata y ensamblado;
- exige todos los chunks;
- verifica tamaño final exacto;
- detecta MIME con `finfo`;
- deriva extensión del MIME;
- usa UUID para el nombre físico;
- guarda el producto digital en el disco `config('products.digital_upload.disk')` —por defecto `local`, ruta `storage/app/private/product-files/{product_id}`—;
- aplica cuotas por uploader, producto y tienda;
- elimina chunks stale al reservar uploads nuevos.

Allowlist funcional de extensiones declaradas:

```text
jpg, jpeg, png, gif, webp, avif, bmp, tif, tiff,
pdf, txt, csv, rtf, docx, xlsx, pptx, odt, ods, odp, epub,
mp3, wav, ogg, flac, m4a, aac,
mp4, webm, ogv, mov, zip, 7z
```

La extensión sólo es un primer filtro; el MIME ensamblado es la autoridad final.

### Evidencia

- `DigitalProductFileUploadFeatureTest`: PDF válido, cuotas, aislamiento y TTL.
- `DigitalProductFileUploadServiceTest`: traversal y limpieza aislada.
- `FileUploadTraitTest`: un PNG llamado `.php` termina con extensión de imagen.

### Riesgo residual

- MIME no es antivirus. ZIP, 7z, documentos y polyglots deben pasar por malware scanning antes de distribución.
- La cuota de tienda no usa un lock único de tienda; dos productos distintos podrían terminar simultáneamente y excederla temporalmente.
- Las imágenes de producto permanecen en `public/uploads`; conocer su URL puede permitir acceder a una imagen de un producto pendiente. Para confidencialidad estricta deben moverse a storage privado con entrega autorizada.
- La limpieza TTL se ejecuta al reservar un upload nuevo; no existe todavía un comando programado para tiendas sin actividad.
- Al descargar conviene usar `Content-Disposition: attachment` y `X-Content-Type-Options: nosniff`.

## 9. Slug y carrera de unicidad

### Problema original

La validación `unique` ocurre antes del insert/update. Dos requests concurrentes podían validar el mismo slug y crear duplicados porque la tabla no tenía restricción única.

### Solución

- `Str::slug()` normaliza en backend;
- update ignora exclusivamente el producto actual;
- la migración corrige duplicados legacy;
- la base de datos crea `products_slug_unique`.

### Evidencia

`ProductSlugUpdateTest` cubre actualización, duplicado por validación y carrera detenida por la base de datos.

### Riesgo residual

Una colisión concurrente queda bloqueada por la base, pero todavía puede manifestarse como `QueryException` genérica. Puede mejorarse capturando la violación única y respondiendo HTTP 422 o 409.

La migración cambia slugs duplicados con sufijos de ID. Deben revisarse redirects y SEO. Los soft-deleted continúan reservando slug.

## 10. Atributos globales compartidos

### Problema original

`Attribute` y `AttributeValue` son globales. Editar una definición desde un producto podía cambiar silenciosamente otros productos o tiendas sin crearles una versión nueva.

### Solución

Antes de editar se rechaza el atributo o valor si está asociado a cualquier otro producto. El borrado sólo elimina la definición cuando queda sin usos.

El snapshot incluye nombres, tipos, valores, colores y pivotes.

### Evidencia

- `VendorSharedAttributeIsolationTest` cubre otra tienda.
- `AdminProductMutationModerationTest` cubre recurso compartido en admin.
- `ProductModerationServiceTest` confirma que cambiar una definición altera fingerprint.

### Riesgo residual

Es una invariante de aplicación sobre un modelo global. A largo plazo, atributos propiedad del producto/tienda o clonado de definiciones reducen el riesgo de contaminación y carreras.

## 11. Cambio de email sin reverificación

### Problema original

El vendor podía cambiar email y conservar `email_verified_at`, manteniendo privilegios con una dirección nunca verificada.

### Solución

- `User` implementa `MustVerifyEmail`;
- si cambia email se establece `email_verified_at = null`;
- se envía una notificación nueva;
- rutas, policy, riesgo y `published()` vuelven a exigir verificación.

### Evidencia

`ProfileEmailVerificationTest` cubre cambio y no-cambio de email. También hay casos en policy y publicación.

### Riesgo residual

Imports, scripts admin o SQL que cambien email deben invalidar la verificación de la misma manera. Como hardening adicional se puede exigir contraseña reciente.

## 12. DoS por variantes

### Problema original

La combinación cartesiana de valores crece exponencialmente. Sin límite podía agotar memoria, CPU y base de datos.

### Solución

Antes de materializar o borrar variantes se validan:

- máximo 50 valores por atributo;
- máximo 6 grupos;
- máximo 500 combinaciones.

El cálculo usa división para detectar exceso sin overflow y la transacción revierte cualquier cambio inválido.

### Evidencia

`AdminProductMutationModerationTest` prueba límite de valores y producto cartesiano.

### Riesgo residual

Falta una prueba HTTP equivalente del endpoint vendor, aunque usa la misma protección. Datos legacy ya enormes pueden requerir migración/limpieza. Para volúmenes mayores conviene regeneración asíncrona.

## 13. Referencias modificadas después de la revisión

### Problema original

Una revisión almacenaba IDs de marca/categoría/tag, pero cambiar el nombre, slug o estado de esas referencias modificaba lo que el cliente veía sin cambiar la versión del producto.

### Solución

- El snapshot guarda definiciones, no sólo IDs.
- `ProductReferenceModerationObserver` observa update/delete.
- Reúne productos afectados y los divide en lotes de 250.
- Despacha un job después del commit.
- El job vuelve a someter cada producto.

La aprobación no se invalida dentro del mismo commit: existe una ventana hasta que el worker procesa el job.

### Evidencia

`ProductReferenceModerationTest` modifica una referencia y confirma nueva versión.

### Riesgo residual

SQL directo no dispara observers. Nuevas referencias compartidas deben añadirse al snapshot y a un observer o servicio equivalente.

## 14. Disco de almacenamiento digital controlado por configuración

### Problema original

La tabla `product_files` conservaba una columna `disk` (`local`, `public`, `s3`) y el borrado de archivos la usaba como autoridad para elegir el `Storage` disk:

```php
$disk = in_array($file->disk, ['local', 'public', 's3'], true) ? $file->disk : 'local';
Storage::disk($disk)->delete($path);
```

La columna nunca controlaba la escritura: todos los uploads iban a `storage/app/private` (disco `local`). Cualquier valor distinto —por ejemplo `s3`— hacía que el borrado intentara eliminar en un disco ajeno y dejara el archivo real huérfano en `local`. Un registro tampeado por SQL directo, un bug de persistencia o un valor legacy podían romper la correspondencia entre la fila y el archivo físico.

### Solución

- El disco de subida y borrado es un único valor de configuración: `config('products.digital_upload.disk')` (`PRODUCT_DIGITAL_UPLOAD_DISK`, por defecto `local`).
- El servicio de upload y ambos controladores (admin y vendor) leen la misma configuración para escribir y para compensar/eliminar.
- El borrado ya no consulta la base de datos: la columna `disk` se elimina con una migración (`2026_08_17_120000_drop_disk_from_product_files_table`).
- La migración es defensiva (`hasTable`/`hasColumn`) y su `down()` restaura la columna con default `local` para entornos que reviertan el cambio.

### Evidencia

- `DigitalProductFileDeleteFeatureTest` borra por HTTP como vendor y elimina la fila y el archivo del disco configurado.
- El mismo test delega en `AdminDigitalProductFileController::destroy` con `products.digital_upload.disk = s3` y comprueba que el archivo desaparece de `s3`, no de un disco hardcodeado.
- `DigitalProductFileUploadFeatureTest` usa el disco configurado en todo el flujo.
- Una prueba de esquema confirma que `product_files` ya no tiene columna `disk`.

### Riesgo residual

- Si la fila se elimina pero el borrado del archivo falla después del commit, puede quedar un archivo huérfano que consume espacio (no permite traversal ni afecta a otro disco). Conviene la reconciliación periódica de `product_files` contra storage.
- El disco es un solo valor para todo el catálogo digital; si se necesita aislar tiendas por storage, debe migrarse a un esquema de múltiples discos con la misma regla: escritura y borrado siempre desde la misma fuente de configuración.

## Prioridades de hardening pendientes

1. Crear un reconciliador periódico que repare confianza/revisiones obsoletas causadas por SQL, `query()->update()`, `saveQuietly()` o eventos deshabilitados.
2. Incorporar antivirus, hash de bytes y descarga forzada para digitales.
3. Capturar la colisión única de slug como respuesta 422/409.
4. Hacer atómica la cuota total de tienda.
5. Añadir reconciliación de archivos huérfanos y limpieza programada de chunks.
6. Añadir prueba explícita de spoofing de todos los campos prohibidos del vendor.
7. Añadir prueba HTTP vendor para límites de variantes.
8. Considerar separación entre quien edita y quien aprueba en escenarios de mayor cumplimiento.
9. Aplicar CSP y pruebas de fuzzing al HTML enriquecido.
10. Añadir regresiones específicas para cambios de Category y Tag.

## Store & Product Moderation Refactor

See `docs/refactor-implementation.md` for the complete implementation record:
versioned Store moderation, Product content/eligibility separation, KYC/email
revalidation, synchronous reference invalidation, digital file hashes, and
soft-delete safety.

