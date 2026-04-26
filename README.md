# BlackSilver API - Documentación Técnica Integral

Backend robusto diseñado para la gestión integral de operaciones mineras y logística ERP. Construido sobre **Laravel 12** y **PHP 8.2+**, utiliza una arquitectura modular personalizada que garantiza escalabilidad, desacoplamiento y una trazabilidad absoluta de cada movimiento operativo.

## 🏗 Arquitectura del Sistema: "Hybrid Modular Architecture"

El proyecto no sigue la estructura estándar de Laravel. Implementa un patrón de **Módulos Independientes** que conviven con una **Capa Global de Datos y Servicios**.

### 📁 Organización de la Aplicación (`/app`)

#### 1. `Modules/` - El Núcleo del Negocio
Es el corazón del ERP. Cada carpeta representa un dominio funcional (ej. `Almacenes`, `Cotizaciones`) que actúa como un micro-servicio interno.
- **Endpoints (`XEndpoints.php`)**: Definición de rutas tipo API. Se registran manualmente en `bootstrap/app.php`.
- **Controllers**: Validadores de entrada y orquestadores de flujo.
- **Services**: Contenedores de la **lógica de negocio pesada**. Aquí se manejan cálculos, transacciones financieras y validaciones de stock en tiempo real.
- **Data (Local)**: Consultas SQL específicas que solo atañen al módulo.

#### 2. `Data/` - Capa de Acceso a Datos Global (DAL)
A diferencia de los modelos Eloquent puros, se utilizan clases `Data` para centralizar consultas complejas (SQL Raw optimizado) y operaciones de persistencia compartidas.
- **Ejemplo**: `KardexProductosData.php` es el único punto de entrada autorizado para registrar movimientos de stock, asegurando que todos los módulos (Requerimientos, Compras, Préstamos) sigan la misma regla de auditoría.

#### 3. `Shared/` - Utilidades Transversales
- **`Responses/`**: Estandarización vía `ApiResponse`. Toda petición retorna un JSON predecible.
- **`Helpers/`**:
    - `ArchivoHelper`: Gestión centralizada de documentos, fotos de empleados y archivos adjuntos.
    - `CorrelativoHelper`: El "notario" del sistema. Genera y reserva números de documentos (OC, REQ, LOT) garantizando que no haya huecos ni duplicados.
- **`Enums/`**: Diccionario maestro de estados (`Pendiente`, `Aprobado`, `En_Despacho`) y tipos (`Metálico`, `Ferretería`).

#### 4. `Middlewares/` - Seguridad Perimetral
- **`JwtAuthMiddleware`**: No solo valida el token; inyecta el contexto del usuario (`id_empleado`, `id_rol`) en la petición para que los servicios operen con la identidad correcta.

#### 5. `Models/` - El Mapa Relacional
Más de 60 modelos Eloquent que definen las relaciones de integridad referencial. El sistema utiliza intensivamente tablas de **Logs de Seguimiento** (ej. `requerimiento_almacen_detalle_log`) para reconstruir el historial de cualquier item.

---

## 🔒 Seguridad y Contexto de Usuario

### Autenticación JWT
Se utiliza `PHPOpenSourceSaver\JWTAuth`. El token no es opaco; contiene un payload enriquecido:
- **Claims Personalizados**: `id_usuario`, `id_rol`, `id_empleado`.
- **Validación Dual**: El sistema verifica que tanto la cuenta de usuario como el contrato del empleado estén en estado `Activo` antes de autorizar cualquier operación.

### Control de Acceso (RBAC)
Los permisos no son solo booleanos; se basan en una estructura jerárquica:
- **Módulos -> Submódulos -> Acciones**.
- El middleware protege las rutas, mientras que el frontend consume la estructura de permisos para renderizar la navegación dinámicamente.

---

## ⚙️ Patrones de Diseño y Reglas Críticas

### 1. Normalización de Unidades (La "Unidad Base")
Para evitar errores de inventario, el sistema maneja dos cantidades:
- **`cantidad_solicitada`**: En la unidad de compra/pedido (ej. 1 Caja).
- **`cantidad_base`**: El equivalente en la unidad mínima (ej. 10 Unidades).
Todos los cálculos de stock y Kardex se realizan sobre la **unidad base**.

### 2. Trazabilidad "Timeline"
Cada cambio de estado en un documento genera un hito. Esto permite saber:
- Quién pidió, quién aprobó, quién despachó y quién recibió.
- Comentarios asociados a cada decisión técnica.

### 3. Consultas N+1 y Eager Loading
En módulos críticos como `Cotizaciones` o `Atención`, se prefiere el uso de **SQL Raw** y la indexación manual de arrays para manejar volúmenes masivos de datos (ej. comparar 50 cotizaciones con 100 items cada una) sin degradar el rendimiento.

---

## 📖 Documentación de Módulos (Deep Dive)

Cada módulo cuenta con su propia documentación técnica detallada. Si necesitas profundizar en un flujo específico, consulta los READMEs internos:

> [!IMPORTANT]
> **Rutas de Documentación Específica:**
> - [Atención de Requerimientos](app/Modules/RequerimientosAlmacenAtencion/README.md): Lógica de despacho y stock.
> - [Reabastecimiento Logístico](app/Modules/SolicitudesReabastecimiento/README.md): Gestión de préstamos e ingresos.
> - [Cotizaciones y Compras](app/Modules/Cotizaciones/README.md): El flujo financiero desde la oferta hasta la OC.

---

## 🚀 Guía de Inicio Rápido

1. **Dependencias**: `composer install`
2. **Entorno**: Configurar `.env` (BD y JWT_SECRET).
3. **Migraciones**: `php artisan migrate` (Carga la estructura y catálogos maestros).
4. **Desarrollo**: `php artisan serve`

> **BlackSilver API** es un sistema vivo. Mantener la modularidad y respetar la capa de `Shared` es vital para la integridad del ERP.
