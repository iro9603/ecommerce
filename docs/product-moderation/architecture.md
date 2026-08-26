# Arquitectura de moderación y publicación

[Volver al índice](README.md)

## Alcance y principios

El agregado protegido es Product, junto con imágenes, archivos digitales,
referencias, atributos y variantes. Store y la identidad del seller forman el
contexto de seguridad que permite evaluarlo y publicarlo.

La arquitectura mantiene cuatro reglas:

1. Contenido y elegibilidad evolucionan de forma independiente.
2. Una decisión sólo vale para las coordenadas exactas que revisó.
3. La proyección actual y el historial de decisiones son estructuras distintas.
4. Toda exposición pública se decide en la consulta, no en la vista.

## Componentes

| Componente | Función |
| --- | --- |
| ProductModerationService | versiones, snapshots, fingerprints, evaluación y decisiones |
| ProductApprovalReview | proyección actual de una revisión |
| ProductModerationEvent | ledger append-only de moderación |
| ProductRiskEvaluator | riesgo y posibilidad de autoaprobación |
| SellerEligibilityService | predicado común de seller, KYC y Store |
| SellerEligibilityInvalidationService | revocación generacional y auditoría |
| EvaluateProductForApproval | evaluación inicial en cola |
| EvaluateProductApprovalContext | reevaluación de contexto pendiente |
| ProductReferenceModerationObserver | barrera de Brand, Category y Tag |
| ProductPolicy | permisos y ownership del vendor |
| ProductMediaStorageService | almacenamiento privado de imágenes |
| DigitalProductFileUploadService | carga privada e integridad de ProductFile |
| StoreModerationService | versión y decisiones de Store |
| StoreStateTransitionPolicy | máquina de estados de Store |
| ProductPageController | listado y detalle públicos |
| ProductCard | tarjeta compartida por catálogo y home |

## Dos planos de versión

### Contenido de Product

'moderation_version' aumenta cuando nace una revisión de contenido.
'moderation_fingerprint' es el SHA-256 de un snapshot estable que incluye:

- campos comerciales, tipo, Store asociada, precios, stock y flags;
- hashes del HTML enriquecido;
- Brand, Categories y Tags con sus propiedades relevantes;
- imágenes y orden;
- archivos digitales, incluido su SHA-256;
- atributos, valores, pivotes, variantes y combinaciones.

Las colecciones se ordenan antes de serializar. Una mutación material llama
'markForReview()' dentro de su transacción. Si Product ya está pending, el hash
no cambió y existe su proyección pending, el servicio puede reutilizarla. De
otro modo:

1. bloquea Product;
2. marca proyecciones pending anteriores como superseded;
3. incrementa 'moderation_version';
4. limpia la decisión y los pins anteriores;
5. crea ProductApprovalReview;
6. registra el evento submitted;
7. despacha la evaluación después del commit.

'reviewed_version = moderation_version' es necesario para publicar. Soft delete
y restore revocan la decisión anterior y fuerzan una coordenada nueva de forma
fail-closed.

### Contexto de elegibilidad

El contexto no forma parte del fingerprint de contenido. Contiene estado de
Store, seller, KYC, confianza automática, interruptor global y
'product_moderation.policy_version'. Se guarda como 'evaluation_context' y
'context_hash'.

Las generaciones independientes son:

- 'users.eligibility_epoch';
- 'kycs.eligibility_epoch', junto con la identidad del KYC;
- 'stores.eligibility_epoch';
- la versión de política.

Una decisión fija en Product las generaciones de User, KYC y Store, el ID del
KYC, el hash de contexto y la política revisada. Cambiar email, verificación,
'user_type', KYC, vigencia, estado de Store, 'is_active', suspensión o confianza
no incrementa la versión de contenido. Los observers rotan la generación
correspondiente o invalidan la concesión, y los pins anteriores dejan de
satisfacer la consulta pública.

El KYC es elegible si está aprobado, tiene expiración y esa fecha es mayor o
igual al día actual en 'config(app.timezone)'. El comando
'security:reconcile-eligibility' materializa expiraciones y revoca concesiones
obsoletas. Incluso antes de ejecutarlo, la consulta pública comprueba la fecha
actual y falla cerrada.

## Proyección e historial

### ProductApprovalReview

Existe una proyección por Product y versión. Contexto, riesgo, status, source,
reviewer y razones representan el estado más reciente. Una reevaluación o
decisión puede sobrescribirlos; esta tabla no es el historial inmutable.

### ProductModerationEvent

Cada hecho inserta una fila independiente con Product, revisión, versión, tipo,
actor, hashes, snapshot, contexto, riesgo, razón, metadatos y fecha. Los tipos
actuales son submitted, superseded, context_reevaluated, automatic_declined,
automatic_approved, manual_approved y manual_rejected.

ProductModerationEventRecorder genera una 'event_key'. Los jobs usan claves
deterministas para que un reintento no duplique el mismo resultado automático.
El modelo rechaza update y delete por Eloquent. Las auditorías deben leer este
ledger; ProductApprovalReview es sólo la proyección operativa.

## Evaluación asíncrona y stale jobs

Los jobs transportan cuatro coordenadas:

~~~text
product_id
moderation_version
expected_content_hash
expected_context_hash
~~~

Su identificador único incluye las cuatro. Un hash ausente o que no sea SHA-256
hexadecimal se rechaza. Durante 'evaluatePending()' se bloquean en orden seller,
Store, KYC, Product y Review. El servicio valida antes de calcular riesgo,
recarga el agregado y vuelve a validar bajo lock antes de decidir.

El job termina sin efectos si cambió Store o seller, la versión o fingerprint
de Product, la Review, el snapshot o el contexto. Si todo sigue vigente,
aprueba automáticamente o conserva pending y registra el evento. Tiene 3
intentos, backoff de 5, 30 y 120 segundos y timeout de 60 segundos.

Las decisiones manuales reciben 'expectedVersion'. Una pestaña administrativa
obsoleta no puede decidir una versión posterior.

## Autoaprobación

La autoaprobación exige simultáneamente:

- interruptor global habilitado;
- Product físico y sin motivo bloqueante;
- Store aprobada, activa, no suspendida y con revisión vigente;
- seller vendor con email verificado;
- KYC aprobado y no vencido;
- concesión explícita 'auto_approve_products';
- pins actuales de usuario, KYC y Store para esa concesión.

Un producto digital siempre añade
'digital_product_requires_manual_review'. También bloquea si falta archivo o si
un ProductFile no tiene SHA-256.

La confianza de Store no es controlable por el vendor. Se liga a las
generaciones observadas; un cambio posterior la vuelve obsoleta, la revoca y la
audita. Nunca se restaura automáticamente.

## Contrato de publicación de Product y Store

No existe un segundo scope público en Store.
'SellerEligibilityService::applyPublishableStoreQuery()' centraliza su
predicado y 'Product::published()' lo incorpora.

Una Store habilitada para vender debe cumplir:

1. 'status = approved';
2. 'is_active = true';
3. no estar suspendida;
4. tener versión positiva y revisada;
5. pertenecer a un vendor con email verificado y KYC vigente.

Un Product publicable debe cumplir además:

1. 'approved_status = approved';
2. 'status = active';
3. tipo physical o digital;
4. versión positiva y revisada;
5. política revisada igual a la actual;
6. pins actuales de User, KYC y Store.

El scope es opt-in. Admin y vendor no lo usan porque necesitan ver borradores y
pendientes. Todo consumidor público debe comenzar con:

~~~php
Product::query()->published()
~~~

Los consumidores actuales son HomeController, ProductPageController::index y
ProductPageController::show. El detalle busca el slug dentro del scope y
responde 404 al perder elegibilidad. ProductCard comparte la representación de
catálogo y home.

## Moderación y transiciones de Store

StoreModerationService::updateProfile sanitiza y guarda los cambios materiales
junto con el envío a revisión. Store tiene snapshot, fingerprint y versión
propios; no reutiliza la versión de Product. Enviar a revisión desactiva
'is_active', limpia la aprobación y revoca confianza automática.

StoreStateTransitionPolicy sólo permite:

| Acción | Origen | Destino |
| --- | --- | --- |
| submit | draft, pending, rejected o approved | pending |
| approve | pending | approved |
| reject | pending | rejected |
| suspend | approved | suspended |
| restore | suspended | approved |

Aprobar o restaurar exige seller elegible. Toda decisión comprueba la versión
esperada; una coordenada stale no modifica Store. Una transición fuera de la
tabla lanza un conflicto de dominio; la capa HTTP puede propagar el 409 o
presentarlo como alerta/redirect en la UI. Sólo Store aprobada queda activa.

Los observers de soft delete incrementan la generación y revocan publicación y
confianza. Restore no recupera una aprobación anterior.

## Referencias compartidas

Brand, Category y Tag pertenecen al snapshot. Su observer cierra la ventana
entre escritura y worker:

1. antes de update, delete o restore captura los Product IDs y convierte
   inmediatamente cualquier aprobado en pending;
2. después de la operación vuelve a aplicar la barrera;
3. divide los IDs en lotes de 250 y despacha
   ReevaluateProductsAfterReferenceChange con 'afterCommit()';
4. el job llama 'markForReview()' también sobre soft-deleted.

Si la cola se detiene, Product permanece no publicable. SQL directo sobre
referencias o pivotes omite el observer y debe aplicar la barrera explícita.

## Ownership y defensa TOCTOU

La autorización del route binding no basta. Las mutaciones siguen este patrón:

1. leen sólo coordenadas candidatas;
2. bloquean Store y Product actuales;
3. vuelven a autorizar al vendor sobre Product bloqueado;
4. bloquean el hijo limitado por 'product_id';
5. bloquean el resto del grafo que se modificará;
6. persisten y remoderan antes del commit.

Esto impide reasignar una imagen o ProductFile durante la petición. El reorder
bloquea cada ID enviado y vuelve a verificar que pertenezca al Product; no
afirma que el payload sea el conjunto completo de imágenes actuales. Atributos,
valores, pivotes, variantes y combinaciones se bloquean como grafo. Ownership y
'product_type' se comprueban tras el lock.

Si se creó un objeto de storage antes de una transacción fallida, el flujo
ejecuta o registra su eliminación compensatoria.

## XSS y contenido enriquecido

ProductContentSanitizer aplica una allowlist a las descripciones:

- en requests de create/update de Product;
- en cambios de perfil de Store;
- otra vez al cargar formularios y detalle público.

ProductPageController entrega variables sanitizadas a la vista. Como segunda
barrera, ProductRiskEvaluator detecta markup ejecutable y bloquea la
autoaprobación. Una vista nueva no debe renderizar HTML de base de datos sin
pasar por el sanitizador.

## Imágenes privadas de Product

Las imágenes usan 'config(products.media.disk)'. ProductMediaStorageService sólo
acepta un disco allowlisted, privado, fail-fast, local o S3. Para local rechaza
roots bajo 'public/' y 'storage/app/public'.

ProductImage::controlledUrl genera 'product-media.show'.
ProductMediaController entrega el stream sólo cuando Product es publicable, un
admin tiene Product Management o el usuario es propietario de Store. Una
denegación responde 404. La respuesta usa MIME allowlisted, no-store y nosniff.

'products:migrate-media-private --dry-run' inventaría imágenes legacy. Sin
dry-run bloquea Product e imagen, copia al disco privado conservando la ruta
lógica y después elimina la copia pública. Cualquier fallo hace que el comando
termine con error. Logo y banner de Store son otro flujo.

## ProductFile y archivos digitales

El upload por chunks protege carpeta derivada de uploader/Product/UUID,
manifiesto inmutable, locks de cuota y ensamblado, y límites persistentes por
Product y Store.

Al completar:

1. ensambla en área privada;
2. comprueba tamaño y MIME real con finfo;
3. deriva la extensión desde una allowlist;
4. calcula SHA-256;
5. escribe en el disco privado configurado;
6. bloquea Store, Product y ProductFiles;
7. reautoriza el Product digital;
8. inserta ProductFile y remodera en la misma transacción.

Si persistencia o moderación fallan, elimina el objeto y compensa un rollback
exterior. En una baja, ProductFile y remoderación se confirman juntos y después
se elimina la ruta normalizada del mismo disco. La base nunca elige el disco.

'products:backfill-file-hashes' calcula SHA-256 por stream para filas legacy y
remodera cada Product afectado.

## Slug, tipo y flags de venta

Create sólo acepta physical o digital. El vendor no decide tipo, Store,
aprobación ni flags administrativos. 'published()' vuelve a rechazar tipos
desconocidos y Store inactiva.

La garantía concurrente del slug es 'products_slug_unique'.
ProductSlugConflictDetector convierte en 422 sólo una violación identificable
de esa restricción; cualquier otra QueryException se relanza.

## Cómo extender el agregado

Para una nueva mutación material:

1. validar una allowlist del actor;
2. bloquear Product y el subgrafo;
3. reautorizar el modelo bloqueado;
4. guardar y remoderar en la misma transacción;
5. incluir el dato en el snapshot con orden determinista;
6. compensar objetos externos;
7. probar ownership reasignado, stale job y no-op.

Para una nueva fuente de elegibilidad:

1. añadirla a SellerEligibilityService;
2. darle generación o identidad estable;
3. incluirla en contexto, pins y published;
4. invalidar concesiones al cambiar;
5. reconciliar cambios dependientes del tiempo.

Para una nueva lectura pública:

1. comenzar por 'Product::query()->published()';
2. usar 'controlledUrl()' para imágenes;
3. sanitizar HTML enriquecido;
4. probar Store inactiva/suspendida, seller/KYC stale, pins y política stale.
