Entendido. Corrección aplicada. Cada vista mantiene el control de su consulta en este caso específico sin centralizarla.

Aquí tienes el documento actualizado:

---

# Black Silver - API (Laravel)

Este es el repositorio del backend (API) de **Black Silver**, una plataforma SaaS diseñada para la gestión integral de operaciones mineras. El sistema está construido sobre Laravel 12 y sigue una arquitectura estricta de **Aislamiento por Vista** orientada a la mantenibilidad.

---

## 🛠️ Stack Tecnológico

- **Framework:** [Laravel 12](https://laravel.com/) (PHP 8.2+)
- **Autenticación:** [JWT Auth](https://php-open-source-saver.github.io/jwt-auth/)
- **Base de Datos:** MySQL / MariaDB
- **Herramientas de Desarrollo:** Sail, Pint, Artisan

---

## 🏗️ Arquitectura: Aislamiento por Vista

La lógica de la API se organiza por **Vistas** (correspondientes exactamente a las vistas del frontend). Cada vista reside en `app/Views/[NombreVista]`.

El flujo debe partir estrictamente en esta dirección: **Endpoints -> Controller -> Service -> Data**.

### Estructura de Capas por Vista

#### 1. Endpoints

- **Responsabilidad:** Definición de rutas y middleware.
- **Ubicación:** `app/Views/[Vista]/[Vista]Endpoints.php`
- **Regla:** Solo deben contener la definición de la ruta y apuntar al método correspondiente del Controller. Cero lógica.

#### 2. Controller (Por Caso de Uso / Proceso)

- **Responsabilidad:** Puerta de entrada, orquestación y validación inicial estricta.
- **Regla de Creación:** Se debe crear **un Controller por cada proceso o caso de uso** (Ej: `RegistroEntregaController.php`), no un solo controller gigante por vista.
- **Validación:** Debe validar obligatoriamente que las entradas (`Request`) tengan la forma y tipos correctos.
- **Flujo de Datos al Service:** **PROHIBIDO pasar un array genérico de `data`** al Service. Los datos de cabecera deben extraerse y pasarse como parámetros explícitos. _Excepción:_ Se permite enviar un array para "detalles" (ej. lista de items), pero el Service debe documentarlo.
- **Autenticación y Contexto:** Extraer el usuario autenticado así:
    ```php
    $authUser = $request->attributes->get('auth_user');
    ```
    _(Contiene: `id_usuario`, `id_rol`, `id_empleado`, `nombre`, `apellido`, `dni`, `ruc`, `carnet_extranjeria`, `pasaporte`, `fecha_nacimiento`, `path_foto`, `estado_empleado`, `estado_usuario`)_
- **Salida:** Retorna siempre una `ApiResponse`.

#### 3. Service (Por Caso de Uso / Proceso)

- **Responsabilidad:** Orquestación absoluta de la lógica de negocio pura.
- **Regla de Creación:** Se debe crear **un Service por cada proceso o caso de uso**, a la par de su Controller (Ej: `RegistroEntregaService.php`).
- **Regla Estricta:** **NO DEBE HACER USO DE MODELOS**. El Service ignora Eloquent. Solo usa la capa de `Data` para la base de datos.
- **Documentación de Arrays:** Si recibe un array de "detalles", documentar obligatoriamente mediante un DocBlock (`/** ... */`) qué llaves contiene.
- **Transacciones:** Operaciones que afecten a múltiples tablas **deben** usar `DB::transaction()`.

#### 4. Data

- **Responsabilidad:** Acceso a datos e interacción exclusiva con la base de datos.
- **Ubicación:** `app/Views/[Vista]/[Vista]Data.php`
- **Regla:** **Métodos tontos.** Cero lógica de negocio.
- **Uso de ORM vs SQL:** Eloquent solo para consultas simples. **PROHIBIDO usar sintaxis ORM** si las consultas usan joins, subconsultas o agrupaciones complejas. Usar **SQL puro** obligatoriamente.

---

## 📜 Reglas de Oro del Desarrollo

### 1. Independencia Total y Reutilización de Consultas

Ninguna vista debe importar lógica (`Controller`, `Service`) de otra. Para consultas en la capa `Data`:

- **Uso exacto en 2 Vistas:** Mover la consulta al **Modelo** correspondiente para que la capa Data de ambas vistas la reutilice.
- **Uso exacto en >2 Vistas:** Crear un archivo dedicado en una **Capa de Data Compartida**.
- **Consultas con Diferente Profundidad:** Si una consulta en una vista es base para otra, pero una de ellas requiere obtener más información (más joins, subconsultas, agrupaciones), **NO se extrae al modelo**. Ambas vistas tendrán esa consulta directamente ahí mismo en su propia capa de `Data`. La diferencia es que la capa Data de una vista añadirá esa información extra en su propia consulta, mientras que la otra mantendrá la versión base.

### 2. Uso Estricto de Enums

Evitar _strings_ planos o números mágicos para estados. **Uso obligatorio de Enums** para garantizar la trazabilidad del código.

### 3. Firmas de Métodos Explícitas

No pasar `arrays` genéricos en los Services. Usar parámetros tipados:

```php
// MAL
public function registrar(array $data) { ... }

// BIEN
public function registrar(int $usuarioId, string $descripcion, array $lotes) {
    // Nota: El array $lotes debe estar documentado en el DocBlock
}
```

---

## 🚀 Workflow: Crear un Nuevo Proceso

1. **Definir Ruta:** En `[Vista]Endpoints.php`.
2. **Data Layer:** Implementa los métodos SQL tontos en `[Vista]Data.php`.
3. **Desarrollar Service:** Crea `[Proceso]Service.php`, documenta arrays, inyecta la capa Data y programa la lógica pura.
4. **Implementar Controller:** Crea `[Proceso]Controller.php` para validar la entrada, extraer `$authUser`, y pasar parámetros explícitos al Service.
5. **Respuesta Estandarizada:** ```php
   return ApiResponse::success($data, "Operación exitosa");

````

---

## 🔧 Comandos Útiles

```bash
php artisan serve
php artisan config:clear
````

---

## 🔒 Seguridad

- Rutas sensibles protegidas por el middleware `auth:api`.
- Validaciones estrictas en cada Controller.
- Nunca exponer datos sensibles en las respuestas JSON.
