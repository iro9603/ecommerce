# Pruebas y criterios de aceptación

[Volver al índice del módulo](README.md)

## Suite focalizada del cierre

Comando principal:

```bash
./vendor/bin/sail artisan test \
    tests/Feature/Security/SellerTypeRevalidationTest.php \
    tests/Feature/Security/StoreAutoApprovalControlTest.php \
    tests/Feature/Security/PublishedProductScopeTest.php \
    tests/Feature/Security/PublicProductCatalogTest.php
```

Resultado validado el 16 de agosto de 2026:

```text
17 passed
114 assertions
```

Esta suite demuestra el cierre de integración pública y revalidación continua del tipo de seller.

## Regresión ampliada de seguridad

```bash
./vendor/bin/sail artisan test tests/Feature/Security tests/Unit/Security
```

Resultado: `56 passed`, `380 assertions`.

La regresión administrativa de slug se ejecutó con:

```bash
./vendor/bin/sail artisan test tests/Feature/Admin/ProductSlugUpdateTest.php
```

Resultado: `4 passed`, `13 assertions`. En conjunto son 60 pruebas y 393 aserciones.

El formato se validó con Pint sobre los 13 archivos PHP modificados en este cierre: `13 files`, `0 issues`.

La suite ampliada y la prueba de slug son el criterio mínimo antes de modificar moderación, ownership, archivos, sanitización, variantes, referencias, slugs o confianza de tienda.

## Inventario de pruebas

### Autorización y ownership

| Archivo | Casos cubiertos |
| --- | --- |
| `ProductPolicyTest.php` | Owner, otra tienda, customer, email, KYC, onboarding y suspensión. |
| `VendorProductMutationToctouTest.php` | Modelo stale, reasignación concurrente, compensación de imagen y delete bloqueado. |
| `VendorSharedAttributeIsolationTest.php` | Atributo/valor compartido con otra tienda. |

### Moderación y publicación

| Archivo | Casos cubiertos |
| --- | --- |
| `ProductModerationServiceTest.php` | Incremento de versión, invalidación de aprobación, snapshot y cambio de definición. |
| `VendorProductPostApprovalUpdateTest.php` | Cambio material del vendor vuelve a pending. |
| `EvaluateProductForApprovalTest.php` | Autoaprobación elegible, tienda no confiable y producto sin precio vendible. |
| `PublishedProductScopeTest.php` | Producto/tienda activos, suspensión, versión revisada, email y KYC. |
| `PublicProductCatalogTest.php` | Integración HTTP de home, listado y detalle; 404 y aislamiento del workspace vendor. |
| `SellerTypeRevalidationTest.php` | Revocación, remoderación, auditoría, riesgo, job obsoleto, ABA/fast-path, retorno a vendor, rollback y soft-delete/restore. |
| `ProductReferenceModerationTest.php` | Cambio de Brand reenvía aprobados; Category y Tag usan el mismo observer pero faltan casos propios. |
| `AdminProductMutationModerationTest.php` | Imágenes, atributos, variantes, aprobación admin, recurso compartido y límites. |
| `StoreAutoApprovalControlTest.php` | Permiso, elegibilidad, auditoría, reenvío, idempotencia y revocación fail-closed de confianza obsoleta. |

### Archivos y XSS

| Archivo | Casos cubiertos |
| --- | --- |
| `DigitalProductFileUploadFeatureTest.php` | PDF válido, ensamblado, cuota persistente, aislamiento y TTL. |
| `DigitalProductFileUploadServiceTest.php` | Traversal y limpieza fuera del root. |
| `FileUploadTraitTest.php` | MIME frente a nombre `.php` y delete traversal. |
| `ProductContentSanitizerTest.php` | Escape de textarea, atributos ejecutables, null/vacío. |
| `StoredProductXssTest.php` | Payload persistido no rompe la edición admin. |

### Identidad y slug

| Archivo | Casos cubiertos |
| --- | --- |
| `ProfileEmailVerificationTest.php` | Cambio de email invalida y reenvía; email igual conserva estado. |
| `ProductSlugUpdateTest.php` | Update, validación unique, índice contra carrera y admin stale. |

## Casos críticos que no deben eliminarse

### Traversal

```text
name = ".."
```

Resultado esperado:

- ValidationException/422;
- no se crea carpeta fuera del hash;
- ningún sentinel fuera del root cambia.

### XSS de textarea

```html
</textarea><img src=x onerror=alert(1)>
```

Resultado esperado:

- no aparece `onerror`;
- no aparece un cierre capaz de escapar del textarea;
- el contenido permitido se conserva.

### TOCTOU

```text
1. El vendor carga el producto como owner.
2. store_id cambia antes de la mutación.
3. El objeto en memoria sigue stale.
4. La recarga bajo lock debe denegar la acción.
```

Resultado esperado:

- 403;
- sin cambios de base de datos;
- archivo físico compensado cuando aplica.

### Job obsoleto

```text
Job(v1) existe
Vendor crea v2
Job(v1) se ejecuta
```

Resultado esperado:

- v2 no se aprueba;
- estado, versión y revisión de v2 permanecen intactos.

### Revisión admin obsoleta

```text
Formulario abierto con moderation_version=1
Producto cambia a version=2
Admin guarda la pestaña vieja
```

Resultado esperado: HTTP 409 y ninguna decisión sobre v2.

### Seller deja de ser vendor

```text
Tienda confiable + producto v1 aprobado
seller.user_type cambia de vendor a user
```

Resultado esperado sincrónico:

- confianza desactivada;
- producto v2 pending y aprobación anterior invalidada;
- auditoría de sistema con tipo anterior/nuevo;
- home, listado, detalle y `published()` dejan de exponerlo;
- job v1 no puede aprobar v2;
- evaluación v2 incluye `seller_not_vendor`;
- volver a vendor crea otra versión, pero no restaura la confianza.

Cuando el caller envuelve el save en `DB::transaction()` y la transacción se revierte, también deben permanecer intactos tipo, confianza, producto y auditoría. Sin transacción exterior, la garantía es fail-closed bifásica, no atomicidad del UPDATE de usuario con los efectos del servicio.

### Acceso público directo

```text
GET /products/{slug}
El slug existe, pero el producto está pending/inactive,
su versión no está revisada o el seller ya no es vendor
```

Resultado esperado: HTTP 404. La resolución del slug debe ocurrir dentro de `published()`, no mediante un binding de producto sin filtro.

## Validaciones estáticas y de framework

### Sintaxis de archivos cambiados

```bash
git ls-files --modified --others --exclude-standard '*.php' \
    | xargs -r -n1 php -l
```

### Pint sobre archivos cambiados

```bash
git ls-files --modified --others --exclude-standard '*.php' \
    | grep -v '^resources/' \
    | xargs -r ./vendor/bin/pint --test
```

Resultado validado:

```text
57 files passed
```

### Blade

```bash
./vendor/bin/sail artisan view:cache
```

Resultado esperado:

```text
Blade templates cached successfully.
```

### Rutas

```bash
./vendor/bin/sail artisan route:list --path=products -vv
./vendor/bin/sail artisan route:list --path=products/digital -vv
./vendor/bin/sail artisan route:list --path=admin/stores -vv
```

Comprobar:

- público: `home`, `products.index` y `products.show` llegan a los controladores de storefront;
- digital admin: `auth:admin`, `Product Management` y throttle de upload;
- digital vendor: `auth`, `verified`, `user_role:vendor` y throttle;
- confianza: `auth:admin` y `Store Auto-Approval Management`.

### Migraciones sin aplicar

```bash
./vendor/bin/sail artisan migrate --pretend --no-interaction
```

Debe mostrar las cuatro migraciones sin error SQL.

### Diff

```bash
git diff --check
```

Las advertencias CRLF/LF no son errores de whitespace. Se observaron advertencias conocidas en las dos vistas `digital-edit.blade.php`.

## Estado de la suite global

Comando:

```bash
./vendor/bin/sail artisan test --compact
```

Resultado observado:

```text
70 passed
20 failed
406 assertions
```

Los 20 fallos ya existían fuera del módulo modificado o corresponden a fixtures/rutas desactualizados:

| Suite | Fallos | Causa observada |
| --- | ---: | --- |
| `CategoryManagementTest` | 7 | El fixture no asigna `Category Management`; recibe 403. |
| `KycDocumentDownloadTest` | 2 | El fixture no asigna `KYC Management`; recibe 403. |
| `KycStatusUpdateTest` | 4 | El fixture no asigna `KYC Management`; recibe 403. |
| `PasswordUpdateTest` | 2 | Prueba rutas de password no disponibles; recibe 404. |
| `RegistrationTest` | 1 | No envía el campo obligatorio `user_type`. |
| `ProfileTest` | 4 | Prueba verbos/rutas Breeze que esta aplicación no expone; recibe 405. |

La suite focalizada de seguridad/moderación debe permanecer verde aunque la suite histórica siga teniendo esos fallos. Es recomendable actualizar esos tests por separado para recuperar una suite global completamente verde.

## Pruebas manuales recomendadas

### Vendor elegible

1. Email verificado, KYC aprobado y tienda draft/pending/active.
2. Crear producto físico.
3. Confirmar `store_id` de su tienda y `approved_status = pending`.
4. Confirmar review versión 1.
5. Intentar editar un producto ajeno y esperar 403.

### Autoaprobación

1. Usar tienda activa, no suspendida y vendor elegible.
2. Asignar el permiso dedicado al admin.
3. Activar confianza con razón.
4. Confirmar audit y reenvío de pendientes.
5. Crear físico con categoría, imagen y precio.
6. Procesar cola y confirmar aprobación automática.
7. Crear digital y confirmar que permanece pending.

### Cambio posterior

1. Partir de v1 aprobada.
2. Cambiar slug, stock, imagen, atributo o variante como vendor.
3. Confirmar v2 pending y limpieza de aprobación.
4. Ejecutar job v1 y confirmar que no toca v2.

### Publicación

Probar `/`, `/products` y `/products/{slug}`. El elegible debe aparecer en home/listado y abrir detalle; cada exclusión debe ocultarlo y hacer que el acceso directo responda 404:

- producto inactive;
- aprobación pending/rejected;
- versión no revisada;
- tienda draft/pending/suspended;
- seller que no es vendor;
- email sin verificar;
- KYC no aprobado.

Confirmar también que `/vendor/products` continúa mostrando los borradores propios: el scope público no debe convertirse en global.

### Upload digital

1. Subir un PDF real en un chunk.
2. Confirmar ruta privada y extensión `pdf`.
3. Repetir con nombre traversal y metadata inconsistente.
4. Superar cuota de producto y esperar 422.
5. Eliminar y confirmar relación producto/archivo.

### XSS

Probar:

- `script`;
- `iframe`;
- evento `onerror`;
- `javascript:` en href;
- breakout de textarea;
- link `_blank`.

Confirmar que sólo queda HTML permitido y que `_blank` contiene `noopener noreferrer`.

## Cobertura pendiente recomendada

1. Request vendor con todos los campos administrativos prohibidos.
2. Endpoint vendor excediendo límites de variantes.
3. Respuesta HTTP controlada ante colisión única concurrente de slug.
4. Descarga digital con headers seguros y autorización.
5. Cuota de tienda bajo finalización simultánea en productos distintos.
6. Reconciliación de archivos huérfanos.
7. Malware scanning cuando se incorpore el servicio.
8. Casos específicos de actualización/eliminación de Category y Tag.

## Regla para nuevas pruebas

Cada nuevo campo o subrecurso material debe incluir como mínimo:

- snapshot/fingerprint cambia;
- versión incrementa;
- aprobación anterior se invalida;
- ownership de otra tienda se rechaza;
- job antiguo no decide la versión nueva;
- admin stale recibe conflicto;
- consulta pública no expone estado no elegible.
