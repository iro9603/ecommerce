# Sistema de roles y permisos

## Objetivo

Este documento describe como funciona el sistema de roles y permisos del panel admin del ecommerce.

El proyecto usa el paquete Spatie Permission para controlar que administradores pueden acceder a secciones del panel, como KYC y Access Management.

## Conceptos principales

### Guard admin

El sistema admin usa el guard `admin`, definido en `config/auth.php`.

Los roles y permisos del panel administrativo deben crearse siempre con:

```php
'guard_name' => 'admin'
```

Esto evita mezclar permisos del panel admin con permisos de usuarios frontend, vendors u otros guards futuros.

### Modelo Admin

El modelo `App\Models\Admin` usa el trait:

```php
use Spatie\Permission\Traits\HasRoles;
```

Eso permite usar metodos como:

```php
$admin->hasRole('Super Admin');
$admin->hasAnyPermission(['KYC Management']);
$admin->syncRoles([$role]);
```

### Super Admin

El rol `Super Admin` tiene acceso total.

En `app/Providers/AppServiceProvider.php` existe un `Gate::before` que permite cualquier permiso si el usuario tiene ese rol:

```php
Gate::before(function ($user, $ability) {
    return $user->hasRole('Super Admin') ? true : null;
});
```

Adicionalmente, el helper `hasPermission()` tambien permite todo al `Super Admin`.

## Permisos actuales

Los permisos base se crean en `database/seeders/Admin/PermissionSeeder.php`.

| Permiso | Grupo | Guard | Uso principal |
| --- | --- | --- | --- |
| `KYC Management` | `KYC Management` | `admin` | Ver y gestionar solicitudes KYC |
| `Role Management` | `Access Management` | `admin` | Crear, editar y eliminar roles |
| `Role User Management` | `Access Management` | `admin` | Crear, editar y eliminar usuarios admin con roles |
| `Category Management` | `Product Category` | `admin` | Gestionar categorias del catalogo |
| `Tags Management` | `Product tags` | `admin` | Gestionar tags de productos |
| `Brand Management` | `Product Brands` | `admin` | Gestionar marcas de productos |
| `Product Management` | `Products` | `admin` | Crear, editar, aprobar y eliminar productos y sus subrecursos |
| `Store Auto-Approval Management` | `Products` | `admin` | Activar o desactivar autoaprobacion por tienda con auditoria |

`Store Auto-Approval Management` es deliberadamente independiente de `Product Management`. Un editor de productos no puede confiar en una tienda a menos que su rol reciba tambien ese permiso.

## Seeders

### PermissionSeeder

Archivo:

```text
database/seeders/Admin/PermissionSeeder.php
```

Responsabilidades:

- Crear permisos base del panel admin.
- Usar `updateOrCreate()` para que el seeder sea idempotente.
- Evitar IDs fijos.
- Limpiar la cache de permisos antes y despues de crear permisos.

Ejemplo del flujo:

```php
Permission::query()->updateOrCreate(
    [
        'name' => $permission['name'],
        'guard_name' => $permission['guard_name'],
    ],
    [
        'group_name' => $permission['group_name'],
    ]
);
```

### AdminSeeder

Archivo:

```text
database/seeders/Admin/AdminSeeder.php
```

Responsabilidades:

- Crear el admin inicial si no existe.
- Crear el rol `Super Admin` con `guard_name = admin`.
- Sincronizar todos los permisos admin al rol `Super Admin`.
- Asignar el rol al admin usando `syncRoles()`.
- No pisar la password si el admin ya existe.

El admin inicial actual se identifica por:

```text
riosirving04@gmail.com
```

Nota: si cambias la password manualmente en base de datos o desde una pantalla futura, volver a correr el seeder no deberia resetearla.

### Orden en DatabaseSeeder

Archivo:

```text
database/seeders/DatabaseSeeder.php
```

El orden correcto es:

```php
$this->call(PermissionSeeder::class);
$this->call(AdminSeeder::class);
```

Primero se crean permisos, luego el Super Admin puede sincronizarlos.

## Comandos utiles

Ejecutar seeders admin:

```bash
./vendor/bin/sail artisan db:seed --class="Database\\Seeders\\Admin\\PermissionSeeder"
./vendor/bin/sail artisan db:seed --class="Database\\Seeders\\Admin\\AdminSeeder"
```

Limpiar cache de permisos:

```bash
./vendor/bin/sail artisan permission:cache-reset
```

Compilar vistas:

```bash
./vendor/bin/sail artisan view:cache
```

Ver rutas admin:

```bash
./vendor/bin/sail artisan route:list --path=admin
```

## Helper de permisos

Archivo:

```text
app/Helpers/global_helpers.php
```

El helper:

```php
hasPermission(array $permissions): bool
```

Flujo:

1. Obtiene el usuario autenticado con `auth('admin')->user()`.
2. Si no hay admin autenticado, devuelve `false`.
3. Si el admin tiene rol `Super Admin`, devuelve `true`.
4. Si no es Super Admin, valida con `hasAnyPermission($permissions)`.

Ejemplo:

```php
@if (hasPermission(['Role Management', 'Role User Management']))
    ...
@endif
```

Este helper se usa principalmente en el sidebar para mostrar u ocultar items.

Importante: ocultar un item del sidebar no reemplaza la proteccion de rutas/controladores. El backend tambien debe validar permisos.

## Sidebar admin

Archivo:

```text
resources/views/admin/layouts/sidebar.blade.php
```

### KYC

El bloque KYC se muestra si el admin tiene:

```php
hasPermission(['KYC Management'])
```

### Access Management

El bloque Access Management se muestra si el admin tiene al menos uno de:

```php
hasPermission(['Role Management', 'Role User Management'])
```

Dentro del dropdown:

- `Role` se muestra con `Role Management`.
- `Role Users` se muestra con `Role User Management`.

## Proteccion de controladores

### RoleController

Archivo:

```text
app/Http/Controllers/Admin/RoleController.php
```

Middleware:

```php
new Middleware('permission:Role Management')
```

Responsabilidades:

- Listar solo roles con `guard_name = admin`.
- Crear roles admin.
- Editar solo roles admin.
- Validar que los permisos asignados existan en el guard `admin`.
- Bloquear edicion y eliminacion del rol `Super Admin`.
- Devolver `404` si se intenta operar un rol que no pertenece al guard `admin`.

Consultas clave:

```php
Role::query()
    ->where('guard_name', 'admin')
    ->withCount('permissions')
    ->get();
```

```php
Permission::query()
    ->where('guard_name', 'admin')
    ->orderBy('group_name')
    ->orderBy('name')
    ->get()
    ->groupBy('group_name');
```

### UserRoleController

Archivo:

```text
app/Http/Controllers/Admin/UserRoleController.php
```

Middleware:

```php
new Middleware('permission:Role User Management')
```

Responsabilidades:

- Listar usuarios admin con sus roles.
- Crear admins y asignarles un rol permitido.
- Editar admins y reemplazar su rol con `syncRoles()`.
- Excluir `Super Admin` de roles asignables.
- Bloquear edicion y eliminacion de usuarios que tengan rol `Super Admin`.
- Al eliminar un admin, limpiar sus roles con `syncRoles([])`.

Roles asignables:

```php
Role::query()
    ->where('guard_name', 'admin')
    ->where('name', '!=', 'Super Admin')
    ->orderBy('name')
    ->get();
```

Asignacion correcta:

```php
$admin->syncRoles([$role]);
```

Evitar usar `assignRole()` cuando el usuario debe tener un solo rol, porque puede acumular roles anteriores.

## Flujo para crear un rol

1. Un admin con permiso `Role Management` entra a Access Management > Role.
2. Abre crear rol.
3. El formulario carga permisos del guard `admin`.
4. El admin selecciona permisos.
5. `RoleController@store` valida:
   - Nombre requerido.
   - Nombre unico dentro de `guard_name = admin`.
   - Permisos existentes dentro de `guard_name = admin`.
6. Se crea el rol con `guard_name = admin`.
7. Se sincronizan permisos con `syncPermissions()`.

## Flujo para asignar rol a un admin

1. Un admin con permiso `Role User Management` entra a Access Management > Role Users.
2. Crea o edita un usuario admin.
3. El formulario muestra roles admin asignables, excepto `Super Admin`.
4. El controlador valida que el rol exista en `guard_name = admin`.
5. El usuario admin recibe exactamente ese rol usando:

```php
$admin->syncRoles([$role]);
```

## Flujo para el sidebar

1. Laravel renderiza `resources/views/admin/layouts/sidebar.blade.php`.
2. Cada bloque llama a `hasPermission([...])`.
3. Si el admin es `Super Admin`, todos los bloques se muestran.
4. Si no es Super Admin, se revisan sus permisos reales.
5. El item solo se muestra si tiene al menos uno de los permisos solicitados.

Ejemplo:

```php
@if (hasPermission(['Role Management', 'Role User Management']))
    Access Management
@endif
```

## Como agregar un nuevo permiso admin

Ejemplo: agregar gestion de productos.

### 1. Agregar permisos al seeder

En `PermissionSeeder.php`:

```php
[
    'name' => 'Product Management',
    'guard_name' => 'admin',
    'group_name' => 'Catalog Management',
],
```

Si quieres granularidad:

```php
[
    'name' => 'Product View',
    'guard_name' => 'admin',
    'group_name' => 'Catalog Management',
],
[
    'name' => 'Product Create',
    'guard_name' => 'admin',
    'group_name' => 'Catalog Management',
],
[
    'name' => 'Product Edit',
    'guard_name' => 'admin',
    'group_name' => 'Catalog Management',
],
[
    'name' => 'Product Delete',
    'guard_name' => 'admin',
    'group_name' => 'Catalog Management',
],
```

### 2. Ejecutar seeder

```bash
./vendor/bin/sail artisan db:seed --class="Database\\Seeders\\Admin\\PermissionSeeder"
./vendor/bin/sail artisan permission:cache-reset
```

### 3. Proteger controlador

```php
public static function Middleware(): array
{
    return [
        new Middleware('permission:Product Management'),
    ];
}
```

O por acciones:

```php
public static function Middleware(): array
{
    return [
        new Middleware('permission:Product View', only: ['index', 'show']),
        new Middleware('permission:Product Create', only: ['create', 'store']),
        new Middleware('permission:Product Edit', only: ['edit', 'update']),
        new Middleware('permission:Product Delete', only: ['destroy']),
    ];
}
```

### 4. Agregar al sidebar

```php
@if (hasPermission(['Product Management']))
    <li class="nav-item">
        ...
    </li>
@endif
```

### 5. Asignar permiso a un rol

Desde el panel:

```text
Access Management > Role > Edit
```

Selecciona el nuevo permiso y guarda.

## Recomendaciones de permisos granulares

Actualmente algunos permisos son amplios. Para una administracion mas segura, conviene evolucionar a permisos granulares.

### KYC

- `KYC View`
- `KYC Review`
- `KYC Approve`
- `KYC Reject`
- `KYC Download Documents`

### Roles

- `Role View`
- `Role Create`
- `Role Edit`
- `Role Delete`

### Usuarios admin

- `Admin User View`
- `Admin User Create`
- `Admin User Edit`
- `Admin User Delete`

### Tiendas

- `Store View`
- `Store Approve`
- `Store Suspend`
- `Store Feature`

### Productos

- `Product View`
- `Product Create`
- `Product Edit`
- `Product Delete`
- `Product Approve`

## Buenas practicas

- Usar siempre `guard_name = admin` para roles/permisos del panel admin.
- Usar `syncRoles()` cuando un admin debe tener un solo rol.
- Usar `syncPermissions()` para reemplazar permisos de un rol.
- No crear permisos con IDs fijos en seeders.
- Limpiar cache con `permission:cache-reset` despues de modificar permisos.
- No confiar solo en el sidebar para seguridad.
- Proteger controladores o rutas con middleware.
- No permitir editar ni eliminar `Super Admin`.
- Filtrar roles/permisos por guard en consultas y validaciones.
- Agregar tests para cada permiso importante.

## Troubleshooting

### El sidebar no muestra un item aunque el permiso existe

Revisar:

- Que el permiso tenga `guard_name = admin`.
- Que el admin tenga el rol correcto.
- Que el rol tenga el permiso correcto.
- Que `hasPermission([...])` reciba exactamente el nombre del permiso.
- Que la cache este limpia:

```bash
./vendor/bin/sail artisan permission:cache-reset
```

### El admin recibe 403 aunque ve el item en sidebar

Posibles causas:

- El sidebar usa un permiso diferente al middleware del controlador.
- El middleware del controlador usa otro nombre.
- El rol fue actualizado pero la cache de permisos no se limpio.

### El usuario conserva roles viejos

Usar:

```php
$admin->syncRoles([$role]);
```

No usar:

```php
$admin->assignRole($role);
```

si el usuario debe tener un solo rol.

### Error de guard mismatch

Normalmente ocurre cuando se mezclan roles/permisos de guards distintos.

Revisar que todo lo admin tenga:

```php
'guard_name' => 'admin'
```

## Checklist antes de agregar un modulo admin

- Crear permiso en `PermissionSeeder`.
- Ejecutar seeder.
- Limpiar cache de permisos.
- Proteger controlador o rutas.
- Agregar item al sidebar con `hasPermission()`.
- Asignar permiso a un rol desde el panel.
- Probar con Super Admin.
- Probar con admin que tiene permiso.
- Probar con admin que no tiene permiso.
- Agregar tests de acceso `200/403`.

## Pruebas recomendadas

Crear tests feature para:

- Admin con `KYC Management` puede acceder a KYC.
- Admin sin `KYC Management` recibe `403`.
- Admin con `Role Management` puede acceder a roles.
- Admin con `Role User Management` puede acceder a usuarios admin.
- Admin con `Role Management` pero sin `Role User Management` no puede entrar a usuarios admin.
- El sidebar muestra Access Management si tiene `Role Management` o `Role User Management`.
- El sidebar no muestra Access Management si no tiene ninguno.
- No se puede editar/eliminar `Super Admin`.
- Un admin actualizado no conserva roles anteriores.

## Archivos principales

| Archivo | Responsabilidad |
| --- | --- |
| `config/auth.php` | Define guards `web` y `admin` |
| `config/permission.php` | Configuracion de Spatie Permission |
| `app/Models/Admin.php` | Modelo admin con `HasRoles` |
| `app/Providers/AppServiceProvider.php` | Permiso global para `Super Admin` |
| `app/Helpers/global_helpers.php` | Helper `hasPermission()` para vistas |
| `database/seeders/Admin/PermissionSeeder.php` | Permisos admin base |
| `database/seeders/Admin/AdminSeeder.php` | Usuario y rol `Super Admin` |
| `app/Http/Controllers/Admin/RoleController.php` | Gestion de roles |
| `app/Http/Controllers/Admin/UserRoleController.php` | Gestion de admins y asignacion de roles |
| `resources/views/admin/layouts/sidebar.blade.php` | Visibilidad de items por permiso |

## Estado actual del sistema

El sistema ya tiene:

- Guard admin separado.
- Modelo Admin con Spatie HasRoles.
- Seeders idempotentes para permisos y Super Admin.
- Sidebar filtrado por permisos.
- Controladores protegidos con middleware.
- Consultas filtradas por `guard_name = admin`.
- Uso de `syncRoles()` para evitar acumulacion de roles.

Pendiente recomendado:

- Dividir permisos amplios en permisos granulares.
- Agregar auditoria para cambios de roles/permisos.
- Agregar tests feature de acceso.
- Agregar politicas o permisos por accion para modulos grandes.
