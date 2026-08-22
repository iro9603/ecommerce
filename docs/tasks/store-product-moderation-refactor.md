Estoy trabajando en un ecommerce Laravel con un sistema de moderación versionada de Products y moderación de Stores.

Antes de modificar código, inspecciona cuidadosamente la implementación real del proyecto y lee la documentación existente:

* `README.md` del módulo de products/moderation
* `architecture.md`
* `store-moderation.md`
* `security-changes.md`
* `operations.md`
* tests relacionados con Products, Stores, auto-approval, SellerTypeRevalidation y publicación pública

NO asumas que la documentación y el código coinciden perfectamente. Primero identifica la implementación actual y después realiza los cambios.

El objetivo es corregir inconsistencias arquitectónicas entre `StoreModeration` y `ProductModeration` sin debilitar ninguna de las protecciones existentes.

# PRINCIPIOS QUE DEBEN CONSERVARSE

Estas invariantes son obligatorias:

1. Un vendor nunca controla:

   * `store_id`
   * `product_type`
   * aprobación
   * riesgo
   * moderation versions
   * flags administrativos
   * `auto_approve_products`

2. Toda mutación material de Product debe pasar por `ProductModerationService`.

3. Toda consulta pública de Products debe utilizar el contrato `Product::published()`.

4. Un producto aprobado sólo representa una versión concreta.

5. Un admin no puede aprobar una versión stale de Product.

6. Jobs atrasados nunca pueden aprobar una versión distinta de aquella para la que fueron creados.

7. Ownership debe volver a autorizarse después de `lockForUpdate()`.

8. Products digitales nunca se autoaprueban.

9. La confianza automática de una Store es explícita, revocable y auditada.

10. La pérdida de elegibilidad de Store/Seller debe cerrar publicación de forma fail-closed.

11. Nunca confiar únicamente en observers para la barrera final de publicación.

12. No introducir global scopes que oculten Products al dashboard de admin/vendor.

---

# PROBLEMA 1 — VERSIONAR LA MODERACIÓN DE STORES

Actualmente Products tienen:

* `moderation_version`
* `reviewed_version`
* fingerprint
* snapshot
* historial
* expected version

pero Stores no tienen una protección equivalente.

Esto permite una carrera como:

1. Admin abre Store versión A.
2. Vendor modifica el perfil y la Store pasa a B.
3. Admin conserva abierta la pestaña vieja.
4. Admin aprueba.
5. Backend bloquea la fila actual B y termina aprobando B aunque el admin revisó A.

Implementa moderación versionada de Store.

Diseña campos equivalentes, por ejemplo:

```text
stores.moderation_version
stores.reviewed_version
stores.moderation_fingerprint
stores.submitted_at
```

Crea una tabla de historial, preferentemente:

```text
store_approval_reviews

id
store_id
version
status
source
submitted_by
reviewed_by
submission_reason
decision_reason
snapshot
content_hash
submitted_at
reviewed_at
created_at
updated_at
```

Adapta `StoreModerationService` para que:

* `submitForReview()` cree o reutilice correctamente una versión.
* cambios materiales incrementen `moderation_version`.
* una revisión pendiente anterior pueda quedar `superseded`.
* `approve()`
* `reject()`
* `suspend()`
* `restore()`

trabajen con `expectedVersion`.

Si:

```text
expectedVersion !== moderation_version
```

la decisión administrativa debe abortarse.

El controlador debe devolver idealmente:

```text
HTTP 409 Conflict
```

con un mensaje como:

```text
Store information changed while you were reviewing it.
Please review the latest version before making a decision.
```

El formulario admin debe enviar `moderation_version`.

No sobrescribas silenciosamente una versión más reciente.

---

# PROBLEMA 2 — SEPARAR PRODUCT CONTENT SNAPSHOT DE EVALUATION CONTEXT

Actualmente el fingerprint del Product incluye información que no necesariamente constituye contenido material del Product, como:

* Store status
* Store suspension
* `auto_approve_products`
* seller user type
* email verification
* KYC

Esto crea inconsistencias:

un Product puede conservar:

```text
approved_status = approved
reviewed_version = moderation_version
```

pero su fingerprint dejar de corresponder al snapshot actual simplemente porque cambió la confianza de Store.

Refactoriza conceptualmente la revisión de Product en dos partes:

## A. Product content snapshot

Debe contener datos cuya modificación representa una nueva versión del producto, por ejemplo:

* name
* slug
* SKU
* descriptions
* price
* special price
* offer dates
* stock
* product status
* brand
* categories
* tags
* images
* digital files
* attributes
* attribute values
* variants
* relevant commercial product configuration

Estos campos forman el fingerprint que vincula:

```text
moderation_version
reviewed_version
content_hash
```

## B. Eligibility / evaluation context

Debe registrar el contexto bajo el cual ocurrió la decisión:

* Store status
* Store suspended status
* seller user type
* email verification
* KYC
* `auto_approve_products`
* automatic approval global switch
* risk calculation context

Debe quedar auditado en `product_approval_reviews`, pero NO necesariamente convertir un cambio de elegibilidad en una modificación del contenido del Product.

No pierdas información de auditoría.

Mantén la capacidad de conocer:

```text
qué contenido se revisó
```

y:

```text
bajo qué condiciones se tomó la decisión
```

como conceptos separados.

---

# PROBLEMA 3 — KYC DEBE REVOCAR CONFIANZA FAIL-CLOSED

Actualmente `seller.user_type` tiene una revalidación fuerte:

* revoca confianza
* invalida aprobaciones
* fuerza productos a pending
* crea auditoría
* volver a vendor no restaura confianza automáticamente

Quiero el mismo principio para KYC.

Escenario que debe quedar imposible:

```text
KYC approved
Store trusted
Product approved

↓

KYC rejected

↓

KYC approved nuevamente

↓

Store recupera automáticamente confianza antigua
Product vuelve a aparecer usando una aprobación histórica
```

Cuando KYC deje de ser elegible:

```text
approved -> pending/rejected/expired/etc.
```

debe:

1. retirar inmediatamente la elegibilidad de publicación;
2. poner `auto_approve_products = false`;
3. invalidar de manera segura la confianza anterior;
4. auditar el evento;
5. impedir que volver a `KYC = approved` restaure confianza automáticamente.

El admin debe volver a habilitar explícitamente auto-approval si desea confiar nuevamente en esa Store.

Implementa esto de forma equivalente al patrón de `SellerTypeRevalidationService` cuando sea apropiado.

No dependas sólo del observer:

`Product::published()` y `ProductRiskEvaluator` deben seguir siendo barreras independientes.

---

# PROBLEMA 4 — DEFINIR QUÉ PASA SI STORE ESTÁ PENDING Y PRODUCT ES APROBADO

Actualmente un Product puede prepararse mientras Store está:

```text
draft
pending
approved
```

Eso debe conservarse.

Sin embargo, analiza este escenario:

```text
Store = pending
Product = pending
Admin aprueba manualmente Product
Product queda approved pero no publicable

después

Admin aprueba Store
```

Si el Product aprobado anteriormente no vuelve a evaluación, puede publicarse automáticamente aunque la revisión del producto ocurrió cuando la Store todavía no estaba aprobada.

Corrige esta ambigüedad.

Preferencia arquitectónica:

Store Moderation y Product Moderation deben ser máquinas de estados independientes.

La aprobación del Product debe significar:

```text
"Este contenido del Product fue aprobado."
```

La aprobación de Store debe significar:

```text
"Esta versión del perfil de Store fue aprobada."
```

Y la publicación debe exigir ambas condiciones.

El contexto de Store no debería hacer que la aprobación de contenido de Product represente otra cosa distinta.

Implementa el modelo más consistente con este principio.

No dupliques innecesariamente versiones de Product si únicamente cambia la Store.

---

# PROBLEMA 5 — PUBLICATION CONTRACT STORE + PRODUCT

Refuerza `Product::published()` para que el contrato completo sea explícito.

Conceptualmente debe requerir:

```text
PRODUCT

approved_status = approved
status = active
moderation_version > 0
reviewed_version = moderation_version

AND

STORE

status = approved
store moderation_version > 0
store reviewed_version = store moderation_version
not suspended

AND

SELLER

user_type = vendor
email verified
KYC approved
```

Si se conserva `is_active`, asegúrate de que no pueda contradecir `status`.

Investiga si realmente necesitamos:

```text
stores.status
stores.is_active
```

como dos fuentes de verdad.

Si `is_active` solamente replica:

```text
approved = true
rest = false
```

considera eliminarlo mediante una migración segura o convertirlo en una semántica distinta y explícita.

Por ejemplo:

```text
selling_enabled
```

sólo si existe un caso real donde una Store aprobada pueda cerrar temporalmente sin perder aprobación.

No hagas una migración destructiva si existen dependencias que primero necesitan refactorización.

---

# PROBLEMA 6 — CAMBIOS DE BRAND/CATEGORY/TAG DEBEN CERRAR PUBLICACIÓN SINCRÓNICAMENTE

Actualmente:

```text
Brand / Category / Tag cambia
        ↓
observer
        ↓
afterCommit job
        ↓
products -> markForReview()
```

Esto deja una ventana donde la referencia ya cambió pero Products continúan approved/publicables.

Cambiar el diseño al patrón:

## Fase 1 — síncrona y fail-closed

Inmediatamente después de una mutación material de una referencia:

* identificar Products afectados;
* invalidar publication eligibility;
* Products previamente approved no deben seguir siendo publicables.

Esta fase debe ser lo más corta posible.

## Fase 2 — asíncrona

Después:

* crear/reconstruir snapshots;
* incrementar versiones donde corresponda;
* crear review history;
* reevaluar;
* despachar jobs.

La cola puede encargarse de la remoderación detallada, pero NO debe ser necesaria para cerrar publicación.

Mantén procesamiento por batches para no crear una transacción gigantesca.

Evalúa cuidadosamente deadlocks y orden de locks.

---

# PROBLEMA 7 — AUDITORÍA COMPLETA DE STORE

Quiero que las acciones administrativas de Store sean reconstruibles históricamente.

Debe ser posible responder:

```text
¿Quién envió la Store a revisión?
¿Qué versión revisó el admin?
¿Qué datos tenía la Store?
¿Quién la aprobó?
¿Por qué fue rechazada?
¿Quién la suspendió?
¿Por qué?
¿Quién la restauró?
¿Qué versión se restauró?
```

No depender únicamente de:

```text
approved_at
approved_by
rejected_at
rejection_reason
suspended_at
```

porque esas columnas representan estado actual y no historial completo.

Usa el nuevo historial versionado de Store como fuente de auditoría.

Mantén campos denormalizados en `stores` sólo si son útiles para consultas rápidas.

---

# PROBLEMA 8 — CAMBIOS DE STORE SIN MODIFICACIONES MATERIALES

Revisa `StoreController@update`.

No quiero que:

```text
Vendor abre la Store
no modifica nada
presiona Save
```

provoque:

```text
approved -> pending
productos afectados
nueva revisión innecesaria
```

Define explícitamente los campos materiales del perfil de Store.

Por ejemplo:

```text
name
legal name
description
business information
address
logo
banner
relevant contact/compliance information
```

Sólo si cambia al menos uno debe crearse una nueva moderation version.

Usa `wasChanged()` o una comparación equivalente de manera segura.

No consideres automáticamente material cualquier preferencia de UI, timezone, notificaciones u otro dato que no afecte lo que el admin aprobó.

---

# PROBLEMA 9 — CAMBIOS DE EMAIL

Actualmente cambiar email invalida:

```text
email_verified_at
```

Mantén ese comportamiento.

Además analiza explícitamente la relación con:

```text
Store auto-approval trust
Store approval
Products approved
```

No quiero que un cambio:

```text
email A verificado
↓
email B no verificado
↓
email B verificado
```

pueda restaurar accidentalmente una confianza que debería haber sido revisada.

Define una política consistente.

Preferencia:

* perder email verification bloquea publicación inmediatamente;
* la confianza automática de Store debe ser revocada cuando se cambia el email principal del seller;
* volver a verificar el nuevo email no debe reactivar `auto_approve_products` automáticamente.

Documenta esta decisión y añade pruebas.

---

# PROBLEMA 10 — HARDENING DE ARCHIVOS DIGITALES

No rompas la implementación actual de:

* chunks
* private storage
* MIME detection
* quotas
* traversal protections
* manifest
* locks
* TTL
* allowed disks

Añade a los archivos digitales un hash criptográfico de bytes, preferentemente:

```text
sha256
```

calculado sobre el archivo final ensamblado.

Inclúyelo en el Product content snapshot.

Una aprobación debería poder demostrar no sólo:

```text
ruta
extension
size
```

sino exactamente qué archivo fue revisado.

No uses el nombre suministrado por el cliente como autoridad.

---

# PROBLEMA 11 — RESTRICCIÓN DE PRODUCT TYPE EN GET

Actualmente el POST de creación restringe correctamente:

```text
physical
digital
```

pero el GET:

```text
/products/{type}/create
```

no tiene la misma restricción documentada.

Corrígelo tanto en vendor como admin usando route constraints o validación equivalente.

Un tipo inválido debe producir 404 o una respuesta consistente, no abrir un formulario ambiguo.

---

# PROBLEMA 12 — AUDITAR TODAS LAS CONSULTAS PÚBLICAS

Busca en TODO el repositorio cualquier consumidor público de `Product`.

No te limites a:

```text
/
 /products
 /products/{slug}
```

Busca especialmente:

* search
* search suggestions
* API
* cart
* checkout
* wishlist
* recommendations
* related products
* category pages
* brand pages
* tag pages
* home sections
* AJAX
* direct product endpoints
* feeds
* sitemap
* recently viewed
* orders que reconstruyan Product desde catálogo
* cualquier endpoint que entregue URLs de archivos/imágenes

Identifica consultas del estilo:

```php
Product::query()
Product::where(...)
Product::find(...)
Product::where('approved_status', 'approved')
```

en contexto público.

Todos los consumidores públicos del catálogo deben utilizar el contrato completo de publicación.

NO uses `published()` en admin/vendor donde necesitan ver pending/draft/rejected.

Añade tests HTTP de regresión para cada consumidor público encontrado.

---

# PROBLEMA 13 — SOFT DELETES Y RESTORES

Audita:

* Store soft-deleted
* Product soft-deleted
* Brand soft-deleted
* Category soft-deleted
* Tag soft-deleted

Quiero garantizar:

```text
delete -> restore
```

nunca restaure accidentalmente:

* auto approval trust
* aprobación antigua
* una review stale
* publicación anterior

Comprueba `withTrashed()` donde corresponda.

Añade pruebas específicas para restore.

---

# CONCURRENCIA Y ORDEN DE LOCKS

Antes de modificar transacciones, documenta el orden de locks que utiliza cada flujo.

Quiero un orden consistente en operaciones que involucren:

```text
User/Seller
Store
Product
Product subresources
```

Evita introducir deadlocks.

No mantengas locks mientras realizas operaciones lentas como:

* procesamiento pesado de archivos
* envío de emails
* dispatch innecesariamente lento
* operaciones externas

Usa `afterCommit()` cuando sea apropiado.

---

# TESTS OBLIGATORIOS

Añade tests que cubran como mínimo:

## Store stale approval

```text
Admin abre Store v1
Vendor modifica Store -> v2
Admin intenta aprobar expectedVersion=1
=> 409
=> Store v2 permanece pending
```

## Store reject stale

Mismo escenario para reject.

## Store suspend stale

Mismo escenario cuando sea semánticamente aplicable.

## Store no-op

```text
Store approved
Vendor guarda sin cambiar campos materiales
=> moderation_version no cambia
=> Store sigue approved
```

## Store material update

```text
Store approved vN
Vendor cambia dato material
=> pending vN+1
=> aprobación vieja ya no representa estado actual
```

## Product + Store independent moderation

```text
Product content aprobado
Store pending
=> no publicable

Store después aprobada
=> publicable sólo si ambas versiones son actuales
```

## KYC loss

```text
trusted Store
approved Product
KYC approved -> rejected
=> publicación bloqueada inmediatamente
=> trust false
=> volver KYC approved no reactiva trust
```

## Email change

```text
trusted Store
email cambia
=> email_verified_at null
=> publicación bloqueada
=> trust revocada
=> verificar nuevo email no reactiva trust
```

## Seller user type

Mantener todas las pruebas existentes.

## Brand/Category/Tag

Cada una debe demostrar que:

```text
reference changes
=> Product deja de ser publicable antes de depender del worker
```

Añade casos separados de:

* Brand
* Category
* Tag

## Digital file hash

```text
upload file A
approve
replace with file B
=> SHA-256 cambia
=> nueva Product version / pending
```

## Invalid product type

```text
GET /vendor/products/foo/create
GET /admin/products/foo/create

=> 404
```

## Public consumers

Cada endpoint público encontrado debe excluir:

* Product pending
* Product inactive
* stale reviewed_version
* Store pending
* Store suspended
* stale Store reviewed_version
* seller non-vendor
* email unverified
* KYC not approved

---

# MIGRACIONES

Crea migraciones nuevas.

NO modifiques migraciones históricas ya desplegables salvo que confirmes explícitamente que el proyecto todavía no ha sido desplegado y haya una razón fuerte para hacerlo.

Las migraciones deben:

* ser compatibles con datos existentes;
* usar defaults fail-closed;
* no convertir Products o Stores automáticamente a approved sin historial válido;
* crear índices útiles;
* crear foreign keys adecuadas;
* considerar soft deletes;
* no borrar auditoría existente.

Si legacy Stores aprobadas no tienen snapshot/version válido, el comportamiento seguro es remoderarlas o migrarlas de manera explícita, no inventar una revisión histórica.

Explica cualquier decisión de migración de datos.

---

# DOCUMENTACIÓN

Actualiza al terminar:

* `README.md`
* `architecture.md`
* `store-moderation.md`
* `security-changes.md`
* `operations.md`
* documentación de testing

La documentación debe describir el sistema REAL después de los cambios.

No afirmes que algo está probado si no existe el test correspondiente y no fue ejecutado.

---

# PROCEDIMIENTO DE TRABAJO

Trabaja en este orden:

1. Inspecciona el código actual.
2. Identifica archivos y flujos afectados.
3. Resume brevemente las inconsistencias encontradas entre código y documentación.
4. Diseña el cambio antes de editar.
5. Implementa migraciones.
6. Implementa Store versioning.
7. Refactoriza Product snapshot vs evaluation context.
8. Implementa revocación de confianza por KYC/email.
9. Corrige invalidación síncrona de referencias.
10. Ajusta `published()`.
11. Audita consumidores públicos.
12. Implementa hashes de archivos.
13. Añade tests.
14. Ejecuta tests focalizados.
15. Ejecuta la suite completa si es viable.
16. Actualiza documentación.

NO realices un rewrite completo innecesario.

Prioriza cambios incrementales que preserven el comportamiento correcto existente.

---

# VALIDACIÓN FINAL

Antes de dar el trabajo por terminado, verifica explícitamente estas invariantes:

```text
Store approval represents one exact Store version.

Product approval represents one exact Product content version.

Changing Store information cannot preserve Store approval.

Changing Product content cannot preserve Product approval.

Store eligibility changes cannot make stale Product content become reviewed.

A stale admin tab cannot decide a newer Store or Product version.

Losing KYC/email/vendor eligibility closes publication immediately.

Restoring eligibility does not silently restore trust.

Auto approval remains opt-in.

Digital products remain manual review only.

Jobs cannot approve stale versions.

Public catalogue requires BOTH current Store approval
AND current Product approval
AND current seller eligibility.

Vendor cannot alter moderation/admin fields.

Direct public consumers cannot bypass published().
```

Finalmente entrégame:

1. resumen de cambios;
2. archivos modificados;
3. migraciones creadas;
4. cambios importantes de arquitectura;
5. tests creados;
6. tests ejecutados y resultado;
7. riesgos residuales;
8. pasos de despliegue;
9. cualquier decisión que requiera revisión humana antes de producción.

No ocultes errores de tests ni marques como corregido algo que únicamente quedó documentado.
