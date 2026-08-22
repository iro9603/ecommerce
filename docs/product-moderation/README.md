# Módulo de productos, moderación y seguridad

## Objetivo

Esta documentación describe el funcionamiento actual del módulo de productos después de la incorporación de moderación versionada, autoaprobación controlada y protecciones de seguridad para vendors y administradores.

El objetivo del módulo es que una aprobación represente una versión concreta del producto y no un estado que pueda conservarse después de cambiar el contenido. También protege los límites de ownership, los archivos, el HTML enriquecido, los slugs y la generación de variantes.

Estado documentado: **17 de agosto de 2026**.

## Respuesta corta sobre la aprobación

El administrador no tiene que aprobar manualmente todos los productos.

- Por defecto, cada tienda tiene `auto_approve_products = false`; sus productos quedan pendientes hasta una revisión humana.
- Un administrador con `Store Auto-Approval Management` puede confiar explícitamente en una tienda elegible y dejar una razón auditada.
- Un producto físico completo y sin motivos de riesgo puede aprobarse automáticamente por medio de la cola.
- Los productos digitales siempre requieren revisión manual.
- Un cambio material posterior crea una versión nueva y vuelve a poner el producto en revisión.
- Aprobado y publicable no son sinónimos: la consulta pública también debe cumplir `Product::published()`.
- Si cambia `seller.user_type`, se revoca la confianza automática y se invalidan las revisiones vigentes de sus productos.

## Mapa de la documentación

- [Arquitectura y flujos](architecture.md): modelos, estados, versionado, snapshots, permisos, rutas, autoaprobación y contrato de publicación.
- [Cambios de seguridad](security-changes.md): vulnerabilidades encontradas, solución aplicada, pruebas y riesgos residuales.
- [Operación y despliegue](operations.md): variables de entorno, migraciones, permisos, worker, monitoreo, rollback y troubleshooting.
- [Pruebas](testing.md): cobertura automatizada, comandos, resultado validado y pruebas manuales recomendadas.

## Flujo general

```text
Vendor o admin guarda el producto
        |
        v
ProductModerationService::submit()/markForReview()
        |
        +--> snapshot + fingerprint SHA-256
        +--> moderation_version = N
        +--> approved_status = pending
        +--> revisión histórica pending
        |
        v  afterCommit
EvaluateProductForApproval(productId, N)
        |
        +--> tienda confiable + producto físico + sin riesgos
        |       -> approved, reviewed_version = N
        |
        +--> no elegible o digital
                -> permanece pending para revisión manual

Cualquier cambio material posterior
        -> versión N+1
        -> aprobación anterior deja de representar el contenido actual
```

## Invariantes del módulo

Estas reglas deben mantenerse en cualquier evolución futura:

1. Toda mutación material debe pasar por `ProductModerationService`.
2. Toda consulta pública debe aplicar `Product::query()->published()`.
3. El vendor nunca decide `store_id`, promociones ni estado de aprobación.
4. El ownership se autoriza de nuevo después de bloquear el producto dentro de la transacción.
5. Una decisión administrativa debe indicar la versión esperada.
6. Un job atrasado nunca puede decidir una versión distinta de la que recibió.
7. Los archivos digitales se almacenan en un único disco configurado (`config('products.digital_upload.disk')`) de forma privada, su tipo final se obtiene del MIME real y el borrado nunca elige el disco desde la base de datos.
8. Los productos digitales no se autoaprueban.
9. La confianza de una tienda es explícita, revocable y auditada.
10. `approved_status = approved` por sí solo no autoriza publicación.
11. El seller debe seguir siendo `vendor` en cada evaluación y en cada consulta pública.

Estas invariantes son contratos de desarrollo, no interceptores globales. SQL directo, imports o controladores nuevos pueden saltar los flujos de invalidación; los observers cubren cambios de Brand, Category, Tag y `User.user_type`, y las barreras de publicación/riesgo vuelven a consultar la elegibilidad actual.

## Componentes principales

| Componente | Responsabilidad |
| --- | --- |
| `ProductPolicy` | Acceso vendor, ownership y abilities de productos. |
| `ProductModerationService` | Versiones, snapshots, fingerprints, decisiones e historial. |
| `ProductRiskEvaluator` | Riesgo y elegibilidad para autoaprobación. |
| `EvaluateProductForApproval` | Evaluación asíncrona, única por producto y versión. |
| `ProductContentSanitizer` | Allowlist de HTML y eliminación de XSS. |
| `DigitalProductFileUploadService` | Chunks, MIME, cuotas, ensamblado y limpieza segura. |
| `ProductReferenceModerationObserver` | Invalida revisiones cuando cambian marca, categoría o tag. |
| `SellerTypeModerationObserver` / `SellerTypeRevalidationService` | Revocan confianza e invalidan revisiones cuando cambia el tipo del seller. |
| `StoreAutoApprovalController` | Confianza por tienda, elegibilidad y auditoría. |
| `Product::published()` | Contrato para exponer un producto al público. |
| `HomeController` / `ProductCatalogController` | Aplican el contrato a home, listado y detalle públicos. |

## Integración pública actual

El scope ya está integrado en los consumidores públicos de producto presentes en el repositorio:

- `/`: `HomeController` obtiene hasta 12 novedades publicables;
- `/products`: `ProductCatalogController::index()` pagina de 24 en 24;
- `/products/{slug}`: `ProductCatalogController::show()` resuelve el slug dentro de `published()` y responde 404 si deja de ser elegible.

`published()` continúa siendo un scope local, no un global scope. Todo consumidor público futuro —búsqueda, API, carrito, checkout u otra colección— debe comenzar con:

```php
$products = Product::query()
    ->published()
    ->get();
```

Las consultas administrativas y de vendor no deben usarlo porque necesitan ver borradores, pendientes y rechazados.

## Content vs eligibility context

Product moderation now separates:

- Product content snapshot and SHA-256 content fingerprint.
- Seller/Store eligibility context stored on `product_approval_reviews.evaluation_context` and `context_hash`.

Store trust/status changes re-evaluate pending Products at their existing Product version. Seller type, KYC, and email losses force a new Product review version and revoke Store auto-approval trust. `Product::published()` additionally requires the current Store moderation version.

## Implemented content/eligibility refactor

Full implementation details are in:

```text
docs/refactor-implementation.md
```

The current Product moderation implementation separates Product content
fingerprints from Seller/Store evaluation context, adds Store version checks to
`Product::published()`, revokes trust on KYC/email/seller-type losses,
invalidates Brand/Category/Tag references synchronously, hashes digital files
with SHA-256, and protects Product/Store restore paths.

