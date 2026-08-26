# Moderación y publicación de productos

## Propósito

Este módulo impide que una decisión de moderación sobreviva a cambios en el
contenido o en la elegibilidad del vendedor y de su tienda. El contrato cubre
creación y edición, autoaprobación, revisión manual, catálogo público, imágenes,
archivos digitales y mutaciones concurrentes.

## Respuesta corta

- Un producto creado por un vendor queda pendiente; el flujo administrativo
  puede registrarlo como aprobado manualmente.
- Una tienda sólo puede autoaprobar productos físicos si un administrador le
  concedió confianza explícita y esa concesión sigue ligada a las generaciones
  actuales de usuario, KYC y tienda.
- Los productos digitales siempre requieren revisión manual.
- Un cambio material crea una nueva versión de contenido.
- Un cambio de email, tipo de usuario, KYC, estado de tienda o política no crea
  una versión de contenido: cambia el contexto de elegibilidad y vuelve
  obsoletos sus pins.
- Una aprobación no implica publicación. Toda lectura pública debe pasar por
  `Product::published()`.

## Diseño en una vista

```text
mutación material
  -> snapshot de contenido + content_hash
  -> moderation_version N
  -> ProductApprovalReview (proyección de vN)
  -> evento submitted
  -> job(producto, N, content_hash, context_hash)
       -> valida y bloquea usuario, tienda, KYC, producto y revisión
       -> aprueba automáticamente o conserva pending
       -> registra un ProductModerationEvent

cambio de elegibilidad
  -> rota eligibility_epoch y/o invalida la concesión de confianza
  -> no incrementa moderation_version del producto
  -> una aprobación con pins anteriores deja de ser publicable
```

La separación es deliberada:

- `moderation_version` y `moderation_fingerprint` describen contenido del
  producto.
- `evaluation_context` y `context_hash` describen vendedor, KYC, tienda,
  confianza automática y versión de política.
- Los campos `reviewed_*_eligibility_epoch`, `reviewed_kyc_id` y
  `reviewed_policy_version` fijan el contexto aceptado por una decisión.

## Revisión e historial

`ProductApprovalReview` es la proyección mutable de una versión: refleja su
estado y evaluación más recientes. No debe interpretarse como un ledger
inmutable.

`ProductModerationEvent` es el historial append-only. Conserva snapshot,
hashes, contexto, resultado de riesgo, actor, razón y metadatos para eventos
como envío, supersesión, reevaluación de contexto y decisiones automáticas o
manuales. El modelo impide actualizaciones y borrados por Eloquent; los flujos
de aplicación sólo insertan eventos.

## Publicación y catálogo

Una tienda está habilitada para vender únicamente si su revisión vigente está
aprobada, está activa, no está suspendida y su seller continúa siendo un vendor
con email verificado y KYC aprobado no vencido.

`Product::published()` añade a ese contrato:

- producto activo y de tipo `physical` o `digital`;
- decisión aprobada sobre la versión de contenido actual;
- versión de política actual;
- coincidencia de los pins de usuario, KYC y tienda de la decisión.

Los consumidores públicos actuales son:

| Superficie | Implementación |
| --- | --- |
| Home | `HomeController`, hasta 12 productos con `published()` |
| `/products` | `ProductPageController::index`, catálogo paginado |
| `/products/{slug}` | `ProductPageController::show`, 404 si deja de ser publicable |
| Tarjetas | componente `Frontend\\ProductCard` y su vista |
| Imágenes | `ProductMediaController`, con autorización por publicación, admin u ownership |

`ProductPageController` y `ProductCard` son los puntos vigentes; no existe
un controlador de catálogo alternativo que pueda omitir el scope.

## Controles de seguridad principales

1. Los jobs incluyen versión, `content_hash` y `context_hash`; una
   coordenada obsoleta termina sin decidir.
2. Las mutaciones recargan con `lockForUpdate()`, vuelven a autorizar el
   producto bloqueado y bloquean los hijos dentro del mismo scope.
3. Brand, Category y Tag aplican una barrera sincrónica antes y después de su
   cambio; el trabajo en cola completa la nueva revisión después del commit.
4. `ProductContentSanitizer` protege la escritura y se vuelve a aplicar en
   superficies de lectura con HTML enriquecido.
5. Las imágenes de producto viven en un disco privado allowlisted y sólo se
   sirven por el controlador controlado.
6. Los archivos digitales se ensamblan en área privada, validan MIME real,
   tamaño, cuotas y SHA-256, y persisten `ProductFile` junto con la
   remoderación en una transacción.
7. El slug tiene validación de aplicación y restricción única; sólo una
   colisión real de `products.slug` se transforma en error de validación.
8. El borrado y la restauración de Product o Store revocan publicación y
   confianza de forma fail-closed.
9. Las transiciones de Store se validan mediante
   `StoreStateTransitionPolicy`; una acción fuera de su origen permitido
   produce un conflicto de dominio y no modifica la base.

SQL directo, actualizaciones masivas o `saveQuietly()` fuera de los servicios
pueden omitir observers. Imports y nuevas rutas de escritura deben invocar
explícitamente los servicios y barreras del módulo.

## Componentes centrales

| Componente | Responsabilidad |
| --- | --- |
| `ProductModerationService` | snapshot, versiones, decisiones, contexto y eventos |
| `ProductRiskEvaluator` | reglas de riesgo y autoaprobación |
| `SellerEligibilityService` | predicado único de seller/tienda/KYC |
| `SellerEligibilityInvalidationService` | revocación de confianza y auditoría generacional |
| `EvaluateProductForApproval` | evaluación asíncrona protegida contra stale jobs |
| `ProductModerationEventRecorder` | inserción del ledger append-only |
| `ProductReferenceModerationObserver` | barrera y remoderación de referencias |
| `ProductMediaStorageService` | imágenes privadas y rutas normalizadas |
| `DigitalProductFileUploadService` | chunks, cuotas, integridad y persistencia |
| `StoreModerationService` | snapshot, versión y decisiones de tienda |
| `StoreStateTransitionPolicy` | orígenes permitidos para cada transición |
| `Product::published()` | única consulta base para exposición pública |

## Documentación relacionada

- [Arquitectura y flujos](architecture.md)
- [Cambios de seguridad](security-changes.md)
- [Operación y despliegue](operations.md)
- [Pruebas](testing.md)

## Regla para extender el módulo

Una nueva mutación material debe validar el request por actor, bloquear y
reautorizar el agregado actual, guardar y llamar a
`ProductModerationService::markForReview()` en la misma transacción. Una nueva
lectura pública debe comenzar por `Product::query()->published()` y usar URLs
controladas para las imágenes.
