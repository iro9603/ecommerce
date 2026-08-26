# Moderación y publicación de tiendas

Este documento describe el contrato vigente de moderación de Store. La fuente
de verdad es **StoreModerationService**, junto con
**StoreStateTransitionPolicy**, **SellerEligibilityService** y los observers de
elegibilidad y soft delete.

## Invariante de publicación

Una Store sólo es publicable cuando se cumplen simultáneamente:

- **status = approved**;
- **is_active = true**;
- **suspended_at** es nulo;
- **moderation_version > 0**;
- **reviewed_version = moderation_version**;
- el seller es vendor, tiene email verificado y KYC vigente.

El KYC debe estar approved, tener expiración no nula y
**document_expiry_date >= hoy** según **config('app.timezone')**. La fecha de
expiración es inclusiva; una fecha nula falla cerrada.

**Product::published()** añade las barreras del producto y compara los epochs
de seller, KYC y Store fijados en su decisión. Por ello, un Product puede
conservar **approved_status = approved** y quedar fuera del catálogo si cambia
su contexto de elegibilidad.

## Estados y transiciones formales

Los estados son **draft**, **pending**, **approved**, **rejected** y
**suspended**. Sólo se permiten estas transiciones:

| Acción | Origen | Destino | Efecto |
| --- | --- | --- | --- |
| submit | draft, pending, rejected, approved | pending | Desactiva, versiona si cambió el snapshot y revoca trust. |
| approve | pending | approved | Revisa la versión actual y activa. |
| reject | pending | rejected | Desactiva y registra el rechazo. |
| suspend | approved | suspended | Desactiva y registra la suspensión. |
| restore | suspended | approved | Revalida elegibilidad, revisa y reactiva. |

Todo otro par origen/acción produce un conflicto de dominio y deja la base sin
cambios. Reject/suspend propagan el HTTP 409; approve/restore lo presentan como
alerta con redirect en la UI actual. En particular, **approve** no sustituye a
**restore**, una Store suspendida no admite **submit**, y sólo una Store pending
puede rechazarse. El restore de una suspensión es distinto del restore de
Eloquent tras un soft delete.

Toda decisión de Store limpia **auto_approve_products** y sus pins. Aprobar o
restaurar una Store no vuelve a confiar automáticamente en ella.

## Versionado y decisiones stale

Cada envío material mantiene **moderation_version**, **reviewed_version**,
**submitted_at**, un SHA-256 en **moderation_fingerprint**, motivo y snapshot
en **store_approval_reviews**.

Los endpoints administrativos requieren **moderation_version**. Dentro de la
transacción se bloquean seller y Store y se compara esa versión con
**expectedVersion**:

- si coincide, se valida y aplica la transición;
- si no coincide, no se escribe ninguna decisión;
- JSON recibe 409;
- HTML vuelve con error de **moderation_version**.

El admin debe recargar y revisar la versión nueva; nunca debe forzarla desde el
cliente. Un submit pending sin cambios es idempotente si ya existe la revisión
pending de la misma versión y fingerprint. Un guardado no-op del perfil no
incrementa la versión.

## Actualización atómica del perfil y medios

Los campos materiales incluyen nombre, slug, logo, banner, contacto,
descripciones, dirección, currency, country, timezone, SEO, enlaces sociales y
seller. Un cambio material llama a **submitForReview()**; un cambio no material
no invalida la decisión.

**StoreController::update**:

1. sanitiza las descripciones y valida campos, MIME y tamaños;
2. escribe logo/banner nuevos en **public/uploads/stores**;
3. llama a **StoreModerationService::updateProfile()**;
4. el servicio bloquea seller y Store dentro de una transacción;
5. rechaza seller eliminado/no vendor y Store soft-deleted;
6. guarda perfil y revisión en la misma transacción;
7. después del commit borra los medios sustituidos.

Si falla upload, guardado o moderación, se eliminan los medios nuevos como
compensación. Con una transacción exterior se usan **afterCommit** y
**afterRollBack**. El filesystem no es transaccional: un fallo de compensación
puede dejar un archivo huérfano, pero no debe dejar un perfil parcialmente
aprobado. Los errores de borrado deben monitorearse.

Los medios de Store siguen siendo públicos bajo **public/uploads/stores**. No
deben confundirse con los medios de Product, que usan un disco privado y una
ruta controlada.

## is_active es una barrera de venta

**is_active** no es un alias del estado:

| Acción | status | is_active |
| --- | --- | --- |
| submit | pending | false |
| approve | approved | true |
| reject | rejected | false |
| suspend | suspended | false |
| restore | approved | true |
| soft delete / restore de Eloquent | pending | false |

Una escritura que deje **approved + is_active=false** permanece fail-closed.
No se corrige cambiando sólo el booleano: debe repetirse el flujo de moderación.

## Epochs, trust y protección ABA

Para impedir que una secuencia A → B → A reactive una decisión antigua existen
generaciones monotónicas:

- **users.eligibility_epoch** rota al cambiar tipo, email o verificación;
- **kycs.eligibility_epoch** rota al cambiar estado o expiración;
- **stores.eligibility_epoch** rota al cambiar seller, estado, actividad,
  suspensión, versiones o trust;
- el grant guarda **auto_approval_user_epoch**, **auto_approval_kyc_id**,
  **auto_approval_kyc_epoch** y **auto_approval_store_epoch**;
- cada decisión de Product guarda pins equivalentes y policy version.

Los observers rotan generaciones.
**SellerEligibilityInvalidationService** revoca trust, limpia pins, audita una
revocación existente y crea un **seller_eligibility_event** idempotente. No
necesita cambiar contenido ni **products.moderation_version**: el scope público
detecta el pin stale en cada consulta. Para volver a publicar se requiere una
decisión de producto con contexto vigente.

No cambiar estas columnas con SQL masivo, **query()->update()**,
**saveQuietly()** o eventos deshabilitados. Esas vías omiten rotación y
auditoría; una reversión ABA también fuera de Eloquent puede conservar una
generación antigua.

## Autoaprobación de productos

**auto_approve_products** es un grant separado. Requiere
**Store Auto-Approval Management** y razón de 10 a 1000 caracteres.

Al habilitarlo se bloquean Store, seller y KYC, se exige elegibilidad completa,
se incrementa el epoch de Store, se fijan los cuatro pins, se crea
**store_auto_approval_audits** y se reevalúan Products pending. Si se solicita
mantener enabled sobre un grant que ya es inelegible, primero se revoca y
audita fail-closed y luego se responde con error.

El cambio de epoch vuelve stale los pins de Products ya decididos. Su estado
puede seguir approved, pero no se publican hasta una decisión con el contexto
nuevo.

## Soft delete y restore

Antes del soft delete, **StoreSoftDeleteModerationObserver**:

- mueve la Store a pending;
- incrementa versiones de moderación y elegibilidad;
- fuerza **is_active=false**;
- revoca trust y decisión administrativa;
- mueve Products approved/pending a pending y limpia decisión y riesgo.

Antes del restore vuelve a dejar la Store pending, sin trust y con versión
nueva. Después intenta crear un nuevo envío. Si esa remoderación falla, reporta
la excepción, pero la Store permanece inactiva y sin revisión vigente.

El observer no actúa en force delete. Un borrado físico necesita un
procedimiento explícito de retención para auditorías, Products y archivos.
Product tiene una barrera análoga: soft delete/restore limpia decisión, pins y
riesgo, incrementa versión y exige remoderación.

## Defensa XSS

**short_description** y **long_description** se sanitizan en dos capas: el
controlador antes de validar y el servicio antes de persistir.
**ProductContentSanitizer** conserva una allowlist pequeña de formato, elimina
script, iframe, SVG, formularios, estilos y multimedia, retira atributos no
permitidos y limita enlaces a HTTP, HTTPS, mailto, tel, rutas locales o
fragmentos. Un target blank recibe **noopener noreferrer**.

El detalle admin vuelve a sanitizar antes de renderizar HTML. Sólo
**safeShortDescriptionHtml** y **safeLongDescriptionHtml** deben imprimirse sin
escape; el resto usa escape normal de Blade. Los enlaces sociales se validan
como URL y el formulario vendor no acepta campos administrativos.

## Administración y auditoría

Las rutas bajo **/admin/stores** requieren **Store Management**. Las acciones
approve, reject, suspend y restore reciben la versión actual; reject y suspend
requieren motivo.

Las decisiones quedan en **store_approval_reviews**, los cambios de trust en
**store_auto_approval_audits** y los cambios de elegibilidad en
**seller_eligibility_events**.

Para despliegue, reconciliación, monitoreo y troubleshooting consultar
[operations.md](product-moderation/operations.md).
