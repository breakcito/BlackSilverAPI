# Black Silver - API (Laravel)

Este es el repositorio del backend (API) de **Black Silver**, una plataforma SaaS diseñada para la gestión integral de operaciones mineras. El sistema está construido sobre Laravel 12 y sigue una arquitectura modular orientada a la mantenibilidad.

---

## 🛠️ Stack Tecnológico

- **Framework:** [Laravel 12](https://laravel.com/) (PHP 8.2+)
- **Autenticación:** [JWT Auth](https://php-open-source-saver.github.io/jwt-auth/)
- **Base de Datos:** MySQL / MariaDB
- **Herramientas de Desarrollo:**
    - [Sail](https://laravel.com/docs/sail) (Entorno Docker opcional)
    - [Pint](https://laravel.com/docs/pint) (Estilo de código)
    - [Artisan](https://laravel.com/docs/artisan) (Línea de comandos)

---

## 🏗️ Arquitectura: Aislamiento por Vista

Para mantener el sistema desacoplado, la lógica de la API se organiza por "Vistas" (correspondientes a los módulos del frontend). Cada módulo reside en `app/Views/[NombreModulo]`.

### Estructura de Capas por Módulo

Cada módulo se divide obligatoriamente en cuatro capas:

#### 1. Endpoints
- **Responsabilidad:** Definición de rutas y middleware.
- **Regla:** Solo deben contener la definición de la ruta y apuntar al método correspondiente del Controller.

#### 2. Controller
- **Responsabilidad:** Orquestación y validación inicial.
- **Regla:** Debe ser "delgado". Valida la entrada (`Request`) y delega la ejecución al **Service**. Retorna una `ApiResponse`.

#### 3. Service
- **Responsabilidad:** Lógica de negocio pura.
- **Regla:** Aquí se toman las decisiones, se procesan datos y se orquestan múltiples llamadas a la capa de **Data**.
- **Nota:** Si una operación afecta a varias tablas, **debe** usar transacciones.

#### 4. Data
- **Responsabilidad:** Acceso a datos y consultas.
- **Regla:** No contiene lógica de negocio. Realiza consultas optimizadas usando Eloquent o SQL puro según la complejidad.

---

## 📜 Reglas de Oro del Desarrollo

### 1. Independencia Total
Ninguna vista debe importar lógica (`Controller`, `Service`) de otra vista hermana. Si dos vistas requieren datos similares, la lógica de consulta debe moverse a los **Modelos de Eloquent** globales (`app/Models`).

### 2. Transacciones de Base de Datos
Cualquier método en un `Service` que realice más de una operación de escritura (insert, update, delete) debe estar envuelto en:
```php
DB::transaction(function () {
    // Lógica de múltiples escrituras
});
```

### 3. Firmas de Métodos Explícitas
No pases parámetros en `arrays` genéricos si puedes evitarlos. Usa parámetros tipados y explícitos.
```php
// MAL
public function crear(array $data) { ... }

// BIEN
public function crear(int $usuarioId, string $descripcion, float $monto) { ... }
```

### 4. Consultas de Alto Rendimiento
Usa **SQL puro** (vía `DB::select`) para consultas con múltiples `JOINs` o que impacten en el rendimiento. Eloquent es excelente para CRUD simple, pero el SQL puro es más explícito para reportes y listados complejos.

---

## 🚀 Workflow: Crear un Nuevo Endpoint

1. **Definir Ruta:** En `app/Views/[Modulo]/Endpoints.php`.
2. **Crear Data Layer:** Implementa los métodos de consulta necesarios en `[Modulo]Data.php`.
3. **Desarrollar Service:** Crea la lógica de negocio en `[Modulo]Service.php`.
4. **Implementar Controller:** Crea el método en `[Modulo]Controller.php` que une todo y valida la entrada.
5. **Estandarizar Respuesta:** Usa siempre la clase `ApiResponse`:
```php
return ApiResponse::success($data, "Operación exitosa");
```

---

## 🔧 Comandos Útiles

```bash
# Iniciar entorno de desarrollo
php artisan serve

# Limpiar caché de configuración
php artisan config:clear

# Ejecutar linter (Pint)
./vendor/bin/pint
```

---

## 🔒 Seguridad
- Todas las rutas sensibles deben estar protegidas por el middleware `auth:api`.
- Usa **Form Requests** o validaciones manuales estrictas en cada Controller.
- Nunca expongas datos sensibles en las respuestas JSON (usa `hidden` en Modelos o filtrado manual).
cess' => false, 'data' => null, 'message' => 'Error...']
        ```