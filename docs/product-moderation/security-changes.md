# Cambios de seguridad y riesgos residuales

[Volver al índice del módulo](README.md)

## Estado del documento

Fecha de corte: 24 de agosto de 2026.

Este documento registra el estado real del refactor de Store y Product Moderation. Distingue controles implementados y comprobados de riesgos que todavía requieren medidas operativas o trabajo posterior. No sustituye el runbook de despliegue ni convierte una suite global con fallos en una suite verde.

## Resumen ejecutivo

| Área | Fallo que se cerró | Defensa actual |
| --- | --- | --- |
| Moderación de Store | Decisiones sobre una versión obsoleta y transiciones arbitrarias. | Versión esperada, fingerprint, historial, locks y política explícita de transiciones. |
| Actualización de Store | Persistencia parcial entre perfil, media y moderación. | Transacción única, bloqueo de seller/Store, compensación de media y no-op real. |
| XSS almacenado | HTML activo en descripciones de Store y Product y breakout de textarea. | Sanitización por allowlist al escribir y escape contextual al renderizar. |
| Moderación de Product | Aprobación no vinculada al contenido exacto e historial mutable. | Snapshot y hash de contenido, versión revisada y ledger append-only de eventos. |
| Elegibilidad | KYC/email/tipo/Store podían cambiar sin invalidar una decisión anterior. | Servicio central, epochs, pins, invalidación sincrónica y reconciliación. |
| Publicación | Filtros parciales podían exponer productos inelegibles. | Scope published con contrato completo y consultas públicas desde ese scope. |
| Ownership y TOCTOU | IDs hijos y modelos stale podían cruzar tiendas. | Policy, recarga y reautorización bajo lock, locks del grafo y compensación. |
| Referencias compartidas | Brand, Category o Tag cambiaban después de aprobar. | Invalidación fail-closed sincrónica y reconstrucción asíncrona after-commit. |
| Archivos digitales | Traversal, MIME falso, carrera de cuota y disco dirigido por BD. | Paths restringidos, MIME real, manifests, locks, SHA-256 y disco configurado. |
| Imágenes de producto | Archivos de productos pendientes quedaban servibles desde public. | Storage privado allowlisted y endpoint de entrega autorizado. |
| Slug | La validación previa no cerraba una carrera de unicidad. | Índice único y traducción selectiva de esa violación a error de validación. |
| Soft delete/restore | Restaurar podía revivir confianza o aprobación previa. | Invalidación fail-closed en delete y restore; exige nueva moderación. |

## 1. Store: versión, transición y atomicidad

Store mantiene moderation_version, reviewed_version, fingerprint, timestamps y revisiones. Una decisión administrativa acepta expectedVersion; si no coincide con la versión bloqueada, no decide y la capa HTTP responde conflicto.

La política de dominio permite únicamente:

| Acción | Origen permitido |
| --- | --- |
| submit | draft, pending, rejected o approved |
| approve | pending |
| reject | pending |
| suspend | approved |
| restore | suspended |

Así, submit no sirve para sacar una tienda de suspended y approve no reemplaza restore. Las pruebas ejercitan también approve, reject, suspend y restore con versión stale y comprueban ausencia de mutación.

La actualización material del perfil bloquea seller y Store, valida de nuevo al vendor, sanea descripciones y persiste perfil y revisión dentro de una transacción. Invalida actividad/confianza al volver a pending, elimina media reemplazada sólo después del commit y compensa media nueva si falla. Timezone es material. Un request sin cambio material no crea versión ni revisión.

Fallo cerrado: no queda un perfil nuevo persistido con la decisión anterior si falla la moderación, ni una Store suspended puede saltar al flujo de submit.

## 2. XSS almacenado

ProductContentSanitizer aplica una allowlist de elementos y atributos. Elimina elementos activos, handlers, comentarios peligrosos y esquemas de URL no permitidos. Los enlaces con target blank reciben noopener y noreferrer. Requests de Product y Store saneán antes de persistir, y los textarea se renderizan con escape.

La defensa usa saneamiento de HTML enriquecido al escribir y escape contextual al renderizar.

Fallo cerrado: payloads de cierre de textarea, script, iframe, onerror y javascript URL no llegan a un contexto ejecutable en las rutas comprobadas.

Riesgo residual: no sustituye una Content Security Policy. SQL directo puede introducir contenido que no pasó por el servicio; cada consumidor nuevo debe escapar según el contexto.

## 3. Product: contenido versionado e historial inmutable

La aprobación corresponde a una versión y a un SHA-256 del snapshot de contenido. Cubre datos comerciales, descripciones, estado, precios, stock, imágenes, archivos digitales con hash, taxonomías, atributos, valores, pivotes y variantes. La elegibilidad se registra aparte y no altera el hash de contenido.

ProductApprovalReview es la proyección vigente. ProductModerationEvent es el ledger append-only y conserva submitted, superseded, context_reevaluated, automatic_approved/declined y manual_approved/rejected. El recorder usa clave idempotente para que un retry no duplique el evento. Los eventos previos retienen snapshot, contexto y riesgo aunque cambie la proyección.

Fallo cerrado: una decisión manual posterior no borra evidencia de un decline automático sobre la misma versión, y un job viejo no decide la versión nueva.

Riesgo residual: una ruta que omita ProductModerationService rompe versionado. La inmutabilidad Eloquent no protege contra una cuenta de BD con UPDATE o DELETE directos.

## 4. Elegibilidad, KYC y protección contra ABA

SellerEligibilityService define elegibilidad para policies, publicación, riesgo, Store y reconciliación. El seller debe ser vendor, tener email verificado y KYC approved con expiración no nula. La fecha es válida durante todo el día local; null o fecha anterior a hoy falla cerrado.

Epochs de User, KYC y Store, junto con pins en confianza y decisiones, impiden reutilizar concesiones tras una secuencia ABA. Un cambio relevante rota generación, revoca autoaprobación, invalida decisiones afectadas sin fingir cambio de contenido, registra SellerEligibilityEvent y reevalúa bajo locks. La reconciliación programada cubre expiración por tiempo y deriva. Volver a ser elegible no restaura confianza ni aprobación.

Fallo cerrado: sustituir KYC, expirarlo, cambiar email o salir y volver a vendor no hace vigente una decisión ligada a epochs viejos.

Riesgo residual crítico: SQL directo, query update, saveQuietly o eventos deshabilitados pueden saltar observers. Scopes y riesgo mantienen barreras runtime, y el reconciliador repara parte de la deriva, pero las escrituras deben usar servicios. Producción requiere privilegios mínimos y auditoría.

## 5. Contrato de publicación

Product::published exige approved_status approved, status active, product_type physical/digital, versión revisada actual, pins vigentes, Store approved/is_active/no suspendida/revisada y seller vendor con email verificado y KYC vigente.

Home, catálogo y detalle consultan desde published. El detalle resuelve el slug dentro del scope y da 404 si pierde elegibilidad. Admin/vendor conservan acceso a borradores porque el scope no es global.

Fallo cerrado: conocer un slug no abre un Product pending, inactive, de tipo desconocido o Store inelegible.

Riesgo residual: toda búsqueda, API, carrito, recomendador o checkout público nuevo debe adoptar el scope y una prueba HTTP.

## 6. Ownership, IDOR y TOCTOU

Las mutaciones vendor autorizan inicialmente y vuelven a autorizar Product recargado con lockForUpdate. Luego bloquean y validan imagen, archivo, atributo, valor, variante y pivotes. En reorder, cada ID enviado se bloquea y vuelve a validar contra el Product; el flujo actual no exige que el payload sea el conjunto completo de imágenes. Si upload falla después de escribir storage, compensa el archivo.

Fallo cerrado: reasignar product_id o store_id entre check y save no permite mutar recursos de otra tienda.

Riesgo residual: todo subrecurso nuevo debe repetir lock, relación y reautorización; middleware/policy antes de la transacción no basta.

## 7. Brand, Category y Tag

Los cambios compartidos usan barrera de dos fases: invalidación sincrónica y bloqueada de productos aprobados, seguida de reconstrucción/remoderación en lotes after-commit. Cubre update y delete.

Fallo cerrado: nombre, slug, estado o jerarquía ya no cambian silenciosamente lo aprobado mientras espera el worker.

Riesgo residual: SQL directo no dispara observers. Toda referencia nueva debe entrar al snapshot y a la barrera.

## 8. Archivos digitales e imágenes

El upload digital usa UUID, manifest inmutable, offsets/tamaños exactos, locks, root aislado, MIME real, extensión derivada, cuotas y SHA-256. La fase física ocurre antes de la transacción corta que bloquea Product y persiste ProductFile; si falla, compensa. Escritura y borrado usan sólo products.digital_upload.disk, nunca una columna controlable en BD.

ProductMediaStorageService acepta discos allowlisted, privados, fail-fast y driver local/S3. En local rechaza roots públicos, valida MIME y paths bajo uploads. ProductMediaController entrega sólo Product publicado, admin con Product Management o seller dueño; responde no-store y nosniff. Los binarios históricos Product salieron de public/uploads y hay comando de migración privada.

Riesgos residuales:

- MIME no es análisis antimalware. Documentos, comprimidos, imágenes y polyglots requieren scanning y cuarentena antes de distribución.
- La bucket policy S3 no la prueban tests locales. Producción debe bloquear acceso/listing/ACL públicos, exigir TLS y cifrado, limitar IAM al prefijo y registrar accesos; no activar website hosting.
- Hay que probar streaming, errores, latencia y borrado contra el proveedor S3 real.
- Una caída posterior al commit puede dejar objetos huérfanos; se necesita reconciliación filas/storage.

## 9. Slug, tipos y soft delete

Str::slug más products_slug_unique cierra la carrera. ProductSlugConflictDetector sólo traduce esa constraint a validación; otras QueryException se relanzan. Rutas create restringen physical/digital.

Observers de soft delete rotan generaciones y limpian revisión, confianza y pins. Restore sigue fail-closed y fuerza remoderación.

Fallo cerrado: la colisión tiene respuesta controlada, errores SQL ajenos siguen visibles y restore no revive permisos derivados.

## 10. Migraciones y legacy

El refactor usa migraciones aditivas y preserva las históricas. La compatibilidad se comprueba desde base fresca y desde esquema origin/main más migraciones nuevas. Backfills en chunks dejan filas dudosas no publicables, sin inventar revisiones ni confianza.

El despliegue debe coordinar workers, dry-run, migrate, hash backfill, migración de media privada y reconciliación.

## Modos de fallo y respuesta esperada

| Inyección | Respuesta segura |
| --- | --- |
| expectedVersion stale | Conflicto; no cambia decisión ni historial vigente. |
| Job vN cuando ya existe vN+1 | No-op; no altera vN+1. |
| Reasignación concurrente | 403/404/conflicto; sin mutación y con compensación. |
| Fallo de moderación de Store | Rollback de perfil y compensación de media. |
| Expiración/cambio de KYC | Revoca confianza, invalida pins y excluye publicación. |
| Retry del evento automático | Una fila por clave idempotente. |
| Violación products_slug_unique | Validación; otras excepciones SQL se propagan. |
| Disco público/no allowlisted | Excepción fail-fast. |
| Restore Product/Store | No publicable hasta nueva revisión. |
| Cambio Brand/Category/Tag | Invalida antes del job. |

## Evidencia al 24 de agosto de 2026

| Ejecución | Resultado |
| --- | ---: |
| Feature/Security + Unit/Security | 152 passed, 936 assertions, 0 failed |
| Compatibilidad de migraciones | 2 passed, 67 assertions |
| ProductSlugUpdate + KycStatusUpdate | 10 passed, 47 assertions |
| Suite completa | 193 passed, 16 failed, 1162 assertions |
| Pint del repositorio | 60 style issues en 239 archivos |

El primer run focal produjo un artefacto temporal del entorno. Se descartó y se repitió limpio; 152/936/0 es el resultado autoritativo actual.

Los 16 fallos globales están fuera del refactor: CategoryManagement 7 por permiso faltante; KycDocumentDownload 2 por permiso faltante; PasswordUpdate 2 por rutas obsoletas; VendorRegistration 1 por enlace UI faltante; Profile 4 por verbos/rutas obsoletos. La suite completa y Pint no están verdes.

## Hardening pendiente

1. Prohibir o monitorizar SQL directo y alertar cambios sin evento.
2. Validar bucket policy, IAM, cifrado, auditoría y lifecycle S3 reales.
3. Integrar malware scanning con cuarentena antes de descarga.
4. Reconciliar objetos huérfanos y fallos de borrado.
5. Aplicar CSP y fuzzing a HTML enriquecido.
6. Considerar separación de funciones editor/aprobador.
7. Corregir los 16 tests históricos y 67 incidencias de estilo por separado.
