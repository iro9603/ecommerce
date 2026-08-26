# Ecommerce Laravel

Aplicación de comercio electrónico con catálogo público, panel vendor, panel administrativo y moderación versionada de Store y Product.

## Estado de validación

La remediación técnica y de seguridad fue validada el 24 de agosto de 2026 con:

- suite focal de seguridad: **152 tests, 936 aserciones, 0 fallos**;
- migración fresca y upgrade desde un esquema representativo de `origin/main`: **2 tests, 67 aserciones**;
- regresiones admin de slug y KYC: **10 tests, 47 aserciones**.

La suite global conserva 16 fallos históricos ajenos a este cierre. El detalle y la clasificación están en [Pruebas](docs/product-moderation/testing.md); no se declara la suite global completamente verde.

## Documentación del dominio

- [Índice de moderación y seguridad](docs/product-moderation/README.md)
- [Arquitectura Product](docs/product-moderation/architecture.md)
- [Moderación Store](docs/store-moderation.md)
- [Cambios de seguridad](docs/product-moderation/security-changes.md)
- [Operación y despliegue](docs/product-moderation/operations.md)
- [Pruebas y criterios de aceptación](docs/product-moderation/testing.md)

## Arranque local

Requisitos: Docker, Laravel Sail, Composer y Node.js.

```bash
cp .env.example .env
composer install
npm install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
npm run dev
```

Para procesar moderación asíncrona:

```bash
./vendor/bin/sail artisan queue:work --queue=default --tries=3 --timeout=150
```

El scheduler debe ejecutar `security:reconcile-eligibility` cada hora. En producción use una sola instancia del scheduler o un backend de locks compatible con `onOneServer()`.

## Configuración obligatoria de storage

Los archivos digitales y las imágenes Product no admiten degradación a un disco público:

```dotenv
PRODUCT_DIGITAL_UPLOAD_DISK=private
PRODUCT_DIGITAL_ALLOWED_DISKS=private
PRODUCT_MEDIA_DISK=private
PRODUCT_MEDIA_ALLOWED_DISKS=private
```

El disco permitido debe ser privado, no servido directamente y configurado con errores de escritura fail-fast. Las imágenes Product se entregan mediante una ruta controlada que vuelve a comprobar publicación, ownership o permiso administrativo.

## Contratos de seguridad

- Una aprobación Store representa exactamente una `moderation_version` revisada.
- Una aprobación Product representa exactamente una versión de contenido; cambios de KYC, email, tipo de seller o confianza cambian epochs de elegibilidad, no la versión de contenido.
- Los jobs deciden sólo si versión, fingerprint de contenido y hash de contexto siguen coincidiendo bajo lock.
- `Product::published()` es obligatorio para todo consumidor público.
- KYC vencido, `is_active = false`, tipo Product desconocido y grants con pins obsoletos fallan cerrados.
- Store/Product restore nunca recupera aprobación o confianza anterior.
- Los archivos digitales y media Product se almacenan fuera de `public/`.
- HTML permitido se sanitiza antes de persistir y vuelve a protegerse en el render.

SQL directo, imports y escrituras con eventos deshabilitados pueden saltar servicios y observers. Esas operaciones requieren una invalidación explícita y las mismas pruebas de seguridad.

## Pruebas

```bash
./vendor/bin/sail artisan test tests/Feature/Security tests/Unit/Security --compact
./vendor/bin/sail artisan test tests/Feature/Database/ModerationMigrationCompatibilityTest.php --compact
./vendor/bin/sail artisan test --compact
```

Antes de desplegar, siga el orden de [Operación y despliegue](docs/product-moderation/operations.md), incluya todos los archivos nuevos del working tree en el artefacto y ejecute los backfills requeridos. Este repositorio no debe desplegarse copiando únicamente archivos ya tracked mientras existan fuentes nuevas sin incluir en el commit de release.

## Licencia

Este proyecto usa Laravel, distribuido bajo licencia MIT. Revise la política de licencia del producto antes de redistribuir el código de la aplicación.
