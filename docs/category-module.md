# Modulo de categorias

## Objetivo

Este documento describe como funciona el modulo de categorias del panel admin del ecommerce.

El modulo permite crear, editar, eliminar, activar/desactivar y reordenar categorias en forma de arbol. El arbol soporta hasta 3 niveles:

```text
Nivel 1: Categoria raiz
Nivel 2: Subcategoria
Nivel 3: Subcategoria hija
```

La restriccion de profundidad se aplica en frontend para mejorar la experiencia, pero la regla definitiva vive en backend.

## Archivos principales

| Archivo | Responsabilidad |
| --- | --- |
| `resources/views/admin/category/index.blade.php` | Pantalla admin, formulario, arbol Nestable, AJAX y estados visuales. |
| `app/Http/Controllers/Admin/CategoryController.php` | Endpoints del CRUD, consulta del arbol y actualizacion de orden. |
| `app/Http/Requests/Admin/StoreCategoryRequest.php` | Validacion de creacion y normalizacion del slug. |
| `app/Http/Requests/Admin/UpdateCategoryRequest.php` | Validacion de actualizacion, slug unico y reglas de parent. |
| `app/Http/Requests/Admin/UpdateCategoryOrderRequest.php` | Validacion del payload de reordenamiento. |
| `app/Services/CategoryTreeService.php` | Reglas del arbol, armado de estructura anidada, posicion siguiente y persistencia del orden. |
| `app/Models/Category.php` | Modelo Eloquent, relaciones parent/children, casts y soft deletes. |
| `database/migrations/2026_06_18_184146_create_categories_table.php` | Estructura de tabla, indices, foreign key y columnas SEO/media. |
| `tests/Feature/Admin/CategoryManagementTest.php` | Cobertura de reglas principales del modulo. |

## Modelo de datos

La tabla `categories` contiene estos campos relevantes:

| Campo | Uso |
| --- | --- |
| `id` | Identificador primario. |
| `parent_id` | Categoria padre. `null` significa categoria raiz. |
| `name` | Nombre visible de la categoria. |
| `slug` | Identificador URL-friendly. Es unico en la tabla. |
| `image` | Imagen futura o actual de la categoria. |
| `icon` | Icono futuro o actual de la categoria. |
| `position` | Orden relativo dentro del mismo padre. |
| `is_active` | Indica si la categoria esta activa. |
| `meta_title` | Titulo SEO opcional. |
| `meta_description` | Descripcion SEO opcional. |
| `deleted_at` | Soft delete. |

El modelo `Category` usa `SoftDeletes`, por lo que `delete()` marca la categoria como eliminada sin borrar fisicamente el registro.

Nota: el `slug` sigue siendo unico a nivel de base de datos. Una categoria eliminada por soft delete puede seguir bloqueando la reutilizacion del mismo slug, a menos que se cambie la estrategia de indices en una migracion futura.

## Rutas

Las rutas viven en `routes/admin.php` dentro del grupo `auth:admin` y prefijo `admin`.

| Metodo | Ruta | Nombre | Uso |
| --- | --- | --- | --- |
| `GET` | `/admin/categories` | `admin.categories.index` | Muestra la pantalla admin. |
| `POST` | `/admin/categories` | `admin.categories.store` | Crea una categoria. |
| `GET` | `/admin/categories/nested` | `admin.categories.nested` | Devuelve el arbol completo. |
| `POST` | `/admin/categories/update-order` | `admin.categories.update-order` | Actualiza parent y position desde el arbol. |
| `GET` | `/admin/categories/{id}` | `admin.categories.show` | Devuelve una categoria para editar. |
| `PUT` | `/admin/categories/{id}` | `admin.categories.update` | Actualiza una categoria. |
| `DELETE` | `/admin/categories/{id}` | `admin.categories.destroy` | Elimina una categoria sin hijos activos. |

## Flujo de la pantalla admin

La vista `admin.category.index` tiene dos zonas:

1. Panel izquierdo: arbol de categorias.
2. Panel derecho: formulario de creacion/edicion.

Al cargar la pantalla:

1. Se limpia el formulario.
2. Se carga el dropdown de padres desde `admin.categories.nested`.
3. Se carga el arbol completo desde `admin.categories.nested`.
4. Se inicializa Nestable con `maxDepth: 3`.

Al hacer click en una categoria:

1. Se consulta `admin.categories.show`.
2. Se llena el formulario.
3. Se excluye la categoria seleccionada y su rama del dropdown de padres.
4. Se muestra el boton Delete.

Al guardar:

1. Si no hay `category-id`, se hace `POST` a `admin.categories.store`.
2. Si existe `category-id`, se hace `PUT` a `admin.categories.update`.
3. Si el backend responde correctamente, se limpia el formulario y se recarga el arbol.
4. Si hay errores de validacion, se muestran con `notyf`.

Al reordenar con drag and drop:

1. Nestable serializa el arbol.
2. Se envia el payload a `admin.categories.update-order`.
3. El backend valida que el payload sea completo, sin duplicados y con profundidad maxima de 3.
4. Se actualizan `parent_id` y `position` dentro de una transaccion.

## Payload de arbol

El endpoint `admin.categories.nested` devuelve una lista anidada con esta forma:

```json
[
    {
        "id": 1,
        "parent_id": null,
        "name": "Clothing",
        "slug": "clothing",
        "is_active": true,
        "position": 0,
        "children_nested": [
            {
                "id": 2,
                "parent_id": 1,
                "name": "Shirts",
                "slug": "shirts",
                "is_active": true,
                "position": 0,
                "children_nested": []
            }
        ]
    }
]
```

El endpoint `admin.categories.update-order` espera el formato que genera Nestable:

```json
{
    "tree": [
        {
            "id": 1,
            "children": [
                { "id": 2 }
            ]
        }
    ]
}
```

Para evitar estados inconsistentes, el payload debe incluir todas las categorias existentes exactamente una vez.

## Validaciones de creacion y actualizacion

Las reglas principales son:

| Campo | Regla |
| --- | --- |
| `name` | Requerido, string, maximo 255 caracteres. |
| `slug` | Requerido, string, maximo 255 caracteres, unico. |
| `parent_id` | Opcional, entero, debe existir en `categories` y no estar eliminado por soft delete. |
| `is_active` | Opcional, booleano. |

Antes de validar, el backend normaliza `slug` con `Str::slug()` usando el slug enviado o, si viene vacio, el nombre de la categoria.

Ademas, el servicio `CategoryTreeService` aplica reglas de arbol:

- Una categoria solo puede usar como padre una categoria no eliminada.
- Una categoria no puede ser su propio padre.
- Una categoria no puede moverse debajo de uno de sus descendientes.
- El arbol no puede superar 3 niveles.
- Al actualizar una categoria con hijos, se valida la profundidad completa de su subarbol.

Estas reglas existen en backend porque el frontend puede ser manipulado o saltado.

## Reordenamiento

`CategoryTreeService::updateOrder()` actualiza el arbol dentro de una transaccion.

Por cada nodo recibido:

1. Se actualiza `parent_id` con el padre calculado por la estructura enviada.
2. Se actualiza `position` con el indice dentro de su lista de hermanos.
3. Se procesan los hijos recursivamente.

Si algo falla, el controlador registra la excepcion en logs y devuelve un mensaje generico al frontend.

## Seguridad y robustez

El modulo incluye estas protecciones:

- Los nombres se escapan en JavaScript antes de insertarse en HTML.
- Los errores AJAX se muestran de forma uniforme.
- El formulario deshabilita acciones durante guardado/eliminacion.
- El backend valida ciclos y profundidad, aunque la UI tambien limite el arbol.
- El payload de ordenamiento debe ser completo y no puede tener IDs duplicados.
- Los errores internos de reordenamiento no exponen el mensaje tecnico al usuario.

## Eliminacion

Una categoria solo puede eliminarse si no tiene hijos activos.

Actualmente el modelo usa soft deletes. Esto significa que `delete()` llena `deleted_at` y la categoria deja de aparecer en consultas normales de Eloquent.

Si en el futuro existen productos asociados a categorias, se debe agregar una regla adicional antes de eliminar, por ejemplo impedir eliminar categorias con productos activos o mover esos productos a otra categoria.

## Pruebas

Las pruebas del modulo estan en:

```text
tests/Feature/Admin/CategoryManagementTest.php
```

Cubren estos casos:

- Crear categoria con slug normalizado.
- Impedir usar una categoria eliminada como padre.
- Impedir que una categoria sea su propio padre.
- Impedir mover una categoria debajo de un descendiente.
- Impedir un cuarto nivel de profundidad.
- Rechazar payloads de ordenamiento incompletos.
- Permitir reordenamiento/reparenting con payload valido.

Para ejecutarlas:

```bash
php artisan test tests/Feature/Admin/CategoryManagementTest.php
```

## Mantenimiento futuro

Recomendaciones para futuras evoluciones:

- Agregar permisos especificos como `Category Management` cuando el panel tenga control granular por modulo.
- Si se agregan imagenes o iconos, mover la subida a un request/servicio dedicado y validar peso, extension y storage.
- Si el catalogo crece mucho, considerar paginacion/busqueda para el panel admin y cache para el arbol publico.
- Definir una estrategia para reutilizar slugs despues de soft delete si el negocio lo necesita.
- Agregar validacion contra productos antes de eliminar categorias.
- Considerar un `CategoryResource` si otros consumidores necesitan el arbol con una estructura estable y versionada.
