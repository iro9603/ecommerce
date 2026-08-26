# Pruebas y criterios de aceptación

[Volver al índice del módulo](README.md)

## Estado autoritativo

Fecha de corte: 24 de agosto de 2026.

| Suite | Resultado |
| --- | ---: |
| tests/Feature/Security + tests/Unit/Security | 152 passed, 936 assertions, 0 failed |
| ModerationMigrationCompatibilityTest | 2 passed, 67 assertions |
| ProductSlugUpdateTest + KycStatusUpdateTest | 10 passed, 47 assertions |
| Suite completa | 193 passed, 16 failed, 1162 assertions |
| Pint completo | 60 style issues sobre 239 archivos |

La primera ejecución focal mostró un artefacto temporal del entorno de pruebas. No se usó como evidencia. El resultado limpio y autoritativo actual terminó con 152 pruebas, 936 aserciones y cero fallos.

La suite completa no está verde. Pint tampoco está verde. Los números anteriores deben conservarse juntos y no resumirse como un cierre global sin fallos.

## Comandos de reproducción

Desde la raíz del proyecto:

    php artisan test tests/Feature/Security tests/Unit/Security --compact

    php artisan test tests/Feature/Database/ModerationMigrationCompatibilityTest.php --compact

    php artisan test         tests/Feature/Admin/ProductSlugUpdateTest.php         tests/Feature/Admin/KycStatusUpdateTest.php         --compact

    php artisan test --compact

    ./vendor/bin/pint --test

No ejecute suites que comparten la misma base de datos de testing en paralelo. Para resultados de cierre, use una base limpia, workers detenidos y el mismo valor efectivo de config('app.timezone') que producción.

## Criterio mínimo de aceptación

Un cambio de Store/Product Moderation sólo puede aceptarse si:

1. la suite focal termina con cero fallos;
2. la compatibilidad de migraciones prueba fresh y upgrade desde origin/main;
3. los tests admin de slug y KYC siguen verdes;
4. cualquier fallo global está reproducido y clasificado;
5. no aparecen nuevos archivos públicos de Product;
6. git diff --check no reporta errores de whitespace;
7. Blade y route:list pueden construirse en el entorno objetivo;
8. la documentación conserva riesgos residuales reales.

Un fallo focal bloquea el cierre. Un fallo global preexistente no se oculta: se documenta con nombre, conteo y causa.

## Mapa obligatorio de invariantes

| Invariante | Prueba principal | Inyección de fallo |
| --- | --- | --- |
| Store decide sólo su versión actual | StoreModerationTest | expectedVersion anterior en approve/reject/suspend/restore |
| Transiciones Store son explícitas | StoreStateTransitionPolicyTest, StoreModerationTest | approve desde approved, suspend desde pending, submit desde suspended |
| Perfil Store y moderación son atómicos | StoreProfileAtomicityTest | excepción dentro del flujo de moderación |
| XSS no sale de textarea/HTML | StoredStoreXssTest, StoredProductXssTest, ProductContentSanitizerTest | cierre textarea, script, iframe, onerror, javascript URL |
| Product approval corresponde al contenido | ProductModerationServiceTest | mutación material después de aprobar |
| Historial conserva todas las decisiones | ProductDecisionHistoryTest | decline automático seguido de approve manual y retry |
| Job stale no decide versión nueva | EvaluateProductForApprovalTest | Job vN ejecutado después de crear vN+1 |
| Elegibilidad no se reutiliza por ABA | KycEligibilityEpochTest, SellerTypeRevalidationTest | reemplazar KYC o salir/volver a vendor |
| Expiración temporal falla cerrado | KycExpirationReconciliationTest | cruzar document_expiry_date sin request del usuario |
| Email/KYC invalidan confianza | KycEmailRevalidationTest | email no verificado o KYC deja de ser válido |
| is_active y type cierran publicación | EligibilityAdjacentInvariantTest, ProductTypeRouteTest | Store inactiva o product_type desconocido |
| Scope público es obligatorio | PublishedProductScopeTest, PublicProductCatalogTest | GET directo por slug inelegible |
| Policy y locks cierran IDOR/TOCTOU | ProductPolicyTest, VendorProductMutationToctouTest | reasignar Store/ProductImage entre check y lock |
| Hijos compartidos no contaminan | VendorSharedAttributeIsolationTest, AdminProductMutationModerationTest | atributo/valor/pivote usado por otro Product |
| Brand/Category/Tag invalidan sincrónicamente | ProductReferenceModerationTest | cambiar referencia aprobada antes de correr worker |
| Digital es privado e íntegro | DigitalProductPrivateStorageTest, DigitalProductFileUploadFeatureTest | path/MIME/tamaño/hash/disco incorrectos |
| Delete digital no usa disco de BD | DigitalProductFileDeleteFeatureTest | disco configurado distinto al default |
| Hash legacy es fail-closed | BackfillProductFileHashesTest | archivo inexistente o bytes cambiados |
| Media Product no es URL pública | ProductMediaAuthorizationTest | usuario anónimo solicita imagen pending |
| Soft restore no revive aprobación | SoftDeleteRestoreTest | delete y restore de Product/Store aprobados |
| Slug race produce error controlado | ProductSlugUpdateTest, ProductSlugConflictDetectorTest | violación de products_slug_unique y QueryException ajena |
| Ruta de tipo rechaza valores libres | ProductTypeRouteTest | GET create con tipo arbitrario |

## Matriz de failure injection

### Versión stale

Secuencia:

1. abrir Store o Product vN;
2. crear vN+1;
3. enviar decisión con expectedVersion N.

Esperado: conflicto, ningún campo de decisión cambia y no aparece un evento que afirme revisar vN+1.

### Job stale y retry

Secuencia:

1. encolar evaluación de Product vN;
2. crear vN+1;
3. ejecutar el job viejo;
4. repetir el mismo job/evento.

Esperado: vN+1 permanece intacta y la clave idempotente impide duplicar eventos.

### Carrera de ownership

Secuencia:

1. cargar Product/hijo como owner;
2. reasignar store_id o product_id;
3. continuar con el modelo stale.

Esperado: reautorización bajo lock rechaza; DB no cambia; un upload físico se compensa.

### Fallo entre storage y DB

Inyectar excepción después de escribir archivo y antes de completar moderación.

Esperado: no queda fila parcial ni Product aprobado; el archivo nuevo se elimina. Para borrado post-commit fallido, el sistema debe reportar y la reconciliación operativa debe detectar el huérfano.

### ABA de KYC/elegibilidad

Cambiar KYC A por B, o perder y recuperar tipo/email/KYC sin nueva concesión.

Esperado: epochs y pins no coinciden; confianza y aprobación anteriores no vuelven; published excluye.

### Referencia compartida

Cambiar Brand, Category o Tag de un Product aprobado sin ejecutar worker.

Esperado inmediato: el Product deja de publicar. Después del worker: snapshot/contexto reconstruido y versión correcta.

### Storage inseguro

Configurar media con visibility public, serve true, throw false, disco no allowlisted o root local público.

Esperado: ProductMediaStorageService falla antes de almacenar/leer.

### Colisión de slug

Forzar la constraint products_slug_unique después de pasar validación, y por separado lanzar una QueryException ajena.

Esperado: la primera se traduce a 422; la segunda se relanza para no ocultar fallos de infraestructura.

## Inventario de la suite focal

### Store, historial y XSS

- StoreModerationTest
- StoreProfileAtomicityTest
- StoreAutoApprovalControlTest
- StoreStateTransitionPolicyTest
- StoredStoreXssTest
- StoredProductXssTest
- ProductContentSanitizerTest
- ProductDecisionHistoryTest

### Elegibilidad y publicación

- KycEligibilityEpochTest
- KycEmailRevalidationTest
- KycExpirationReconciliationTest
- SellerTypeRevalidationTest
- EligibilityAdjacentInvariantTest
- EvaluateProductForApprovalTest
- PublishedProductScopeTest
- PublicProductCatalogTest
- ProductTypeRouteTest
- ProfileEmailVerificationTest

### Ownership y mutaciones

- ProductPolicyTest
- VendorProductMutationToctouTest
- VendorProductPostApprovalUpdateTest
- VendorSharedAttributeIsolationTest
- AdminProductMutationModerationTest
- ProductReferenceModerationTest
- ProductModerationServiceTest

### Storage y archivos

- DigitalProductFileUploadFeatureTest
- DigitalProductFileDeleteFeatureTest
- DigitalProductPrivateStorageTest
- DigitalProductFileUploadServiceTest
- BackfillProductFileHashesTest
- ProductMediaAuthorizationTest
- FileUploadTraitTest

### Slug

- ProductSlugConflictDetectorTest
- ProductSlugUpdateTest se ejecuta en la regresión admin adicional.

## Compatibilidad de migraciones

ModerationMigrationCompatibilityTest debe conservar dos caminos:

| Camino | Demostración |
| --- | --- |
| Fresh | Todas las migraciones crean un esquema coherente desde cero. |
| Upgrade | Un esquema origin/main acepta sólo migraciones aditivas nuevas y queda fail-closed. |

Resultado autoritativo: 2 passed, 67 assertions.

Además del test automatizado, antes de producción:

    php artisan migrate:status
    php artisan migrate --pretend --no-interaction

Los backfills deben procesar chunks, poder reanudarse y no publicar una fila incompleta. No se modifican migraciones históricas para acomodar el refactor.

## Regresión admin adicional

Se ejecutaron juntas:

- tests/Feature/Admin/ProductSlugUpdateTest.php
- tests/Feature/Admin/KycStatusUpdateTest.php

Resultado: 10 passed, 47 assertions.

KycStatusUpdate necesita el permiso KYC Management en el fixture; ProductSlugUpdate cubre validación, constraint y stale update. No se debe debilitar middleware para hacer verde un fixture.

## Clasificación exacta de la suite completa

Resultado: 193 passed, 16 failed, 1162 assertions.

| Suite | Fallos | Clasificación |
| --- | ---: | --- |
| CategoryManagementTest | 7 | Fixture sin permiso Category Management. |
| KycDocumentDownloadTest | 2 | Fixture sin permiso KYC Management. |
| PasswordUpdateTest | 2 | Rutas esperadas por el test están obsoletas/no disponibles. |
| VendorRegistrationTest | 1 | Falta el enlace de UI esperado. |
| ProfileTest | 4 | Verbos o rutas esperados están obsoletos. |

Total: 16. Son preexistentes respecto del refactor auditado. Siguen siendo deuda real; esta clasificación no equivale a una suite global verde ni autoriza ignorar fallos nuevos en esas áreas.

## Pint y verificaciones estáticas

El comando de repositorio:

    ./vendor/bin/pint --test

no está verde: reporta 60 style issues sobre 239 archivos, principalmente preexistentes. No documentar 0 issues ni files passed como resultado global.

Comprobaciones adicionales:

    git diff --check

    git ls-files --modified --others --exclude-standard '*.php'         | xargs -r -n1 php -l

    php artisan view:cache

    php artisan route:list --path=products -vv
    php artisan route:list --path=admin/stores -vv

Las advertencias de conversión CRLF/LF no son por sí mismas errores de whitespace. Un error real de diff --check sí debe corregirse.

## Pruebas operativas de storage

### Media privada local

- confirmar que el root no está bajo public ni storage/app/public;
- solicitar imagen published como anónimo y esperar 200;
- solicitar imagen pending como anónimo y esperar 404;
- solicitarla como owner/admin autorizado y esperar 200;
- comprobar no-store y nosniff;
- confirmar que no hay binarios Product planos en public/uploads.

### S3

Los tests locales sólo validan configuración y contrato de aplicación. En staging real comprobar:

- Block Public Access habilitado;
- sin ACL/bucket policy públicas ni website hosting;
- IAM mínimo limitado a bucket/prefijo;
- TLS y cifrado en reposo;
- listing anónimo denegado;
- GET directo al objeto denegado;
- endpoint controlado funciona y no filtra URL pública;
- borrado, retry, timeout, logging y lifecycle.

Una prueba con Storage::fake no demuestra la bucket policy.

### Malware scanning

Hoy no existe garantía antivirus. Al integrarlo, añadir estados quarantine, clean, infected y failed. Inyectar timeout, motor caído, muestra EICAR en entorno seguro y archivo ambiguo. Sólo clean puede distribuirse; timeout/error debe fallar cerrado. MIME y SHA-256 no reemplazan scanning.

## Pruebas manuales mínimas

1. Vendor elegible crea Product físico: queda pending con versión/evento.
2. Admin decide con versión actual; pestaña stale obtiene conflicto.
3. Vendor cambia contenido aprobado: nueva versión pending.
4. KYC expira: Store/Product dejan de publicar sin request adicional.
5. Store approved pasa a suspended y sólo restore puede reactivarla.
6. Cambiar Tag de Product aprobado lo retira antes del worker.
7. Imagen pending no es accesible anónimamente; owner/admin sí.
8. Digital con hash ausente no se autoaprueba.
9. Soft delete/restore no recupera confianza ni aprobación.
10. Slug concurrente devuelve validación y no oculta otra excepción SQL.

## Regla para nuevas pruebas

Cada campo, subrecurso o consumer público nuevo debe demostrar:

- si es material, cambia snapshot/hash y versión;
- invalida decisión anterior;
- conserva historial append-only;
- rechaza otro owner y una reasignación concurrente;
- un job stale no decide la nueva versión;
- un estado inelegible no aparece públicamente;
- los fallos de DB/storage se compensan o quedan detectables;
- los tests usan permisos reales, no relajan middleware.
