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

- **Responsabilidad:** Puerta de entrada y validación inicial estricta.
- **Regla de Creación:** Se debe crear **un Controller por cada proceso o caso de uso** (Ej: `RegistroEntregaController.php`).
- **Validación:** Validar obligatoriamente las entradas (`Request`).
- **Flujo de Datos al Service:** Extraer datos de cabecera y pasarlos como parámetros explícitos.
- **Importaciones:** Usar declaciones `use` al inicio del archivo, evitar namespaces en línea.
- **Salida:** Retorna directamente la respuesta del Service formateada como JSON:
    ```php
    return response()->json(MiService::ejecutar($request->datos));
    ```

#### 3. Service (Por Caso de Uso / Proceso)

- **Responsabilidad:** Orquestación absoluta de la lógica de negocio pura.
- **Regla Estricta:** **ÉTATICO**. Todos los métodos deben ser `public static`.
- **Salida:** Debe retornar siempre una instancia de `ApiResponse`.
- **Relación con Modelos:** Preferencia por usar la capa de `Data`. Si se usan modelos directamente, asegurar que sea para operaciones simples.
- **Transacciones:** Operaciones multi-tabla **deben** usar `DB::transaction()`.

#### 4. Data

- **Responsabilidad:** Acceso a datos.
- **Uso de ORM vs SQL:** Eloquent es bienvenido para consultas simples y joins manejables. Reservar SQL puro solo para consultas extremadamente complejas (agrupaciones masivas, subconsultas pesadas).
- **Regla Estricta:** **ESTÁTICO**. Todos los métodos deben ser `public static`.

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
