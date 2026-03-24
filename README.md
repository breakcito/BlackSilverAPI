# Black Silver - Sistema de Gestión Minera (SaaS)

Black Silver es una plataforma SaaS diseñada para la gestión integral de operaciones mineras. Este proyecto utiliza un stack moderno y una arquitectura diseñada para la escalabilidad y el mantenimiento independiente de módulos.

## Stack Tecnológico

- **Backend:** Laravel 12 (PHP 8.4)
- **Frontend:** React.js + Zustand + Material UI
- **Base de Datos:** MySQL

---

## Arquitectura de Software: Aislamiento por Vista

El proyecto sigue estrictamente el principio de **Aislamiento por Vista**. Cada módulo o vista debe ser autosuficiente, evitando acoplamientos innecesarios con otros módulos hermanos.

### 1. Backend (API)
**Ubicación:** `BlackSilverAPI/app/Views/[NombreModulo]`

La lógica de la API se organiza por vistas para garantizar que cada sección del frontend tenga su contraparte específica en el backend. Cada módulo se divide en cuatro capas:

*   **Endpoints:** Define las rutas específicas y accesibles para la vista.
*   **Controller:** Actúa como un orquestador ligero. Valida la entrada y delega la ejecución al Service.
*   **Service:** Contiene la lógica de negocio pura. Es el encargado de procesar la información y tomar decisiones.
*   **Data:** Capa de acceso a datos. Contiene consultas optimizadas (SQL puro o Eloquent) para proveer información al Service.

> [!IMPORTANT]
> **Regla de Oro:** Si dos vistas requieren datos similares, ambos archivos `Data` deben recurrir a métodos compartidos en los **Modelos de Eloquent** globales. No se permite comunicación directa entre Controllers o Services de distintas vistas.

### 2. Frontend
**Ubicación:** `blacksilver/src/views/[nombre-modulo]`

El frontend sigue una estructura de tres capas para separar la interfaz de la lógica y el estado:

*   **Presentation:** Contiene el archivo principal `.page.tsx` y sub-componentes visuales. No debe contener lógica compleja, cálculos, ni manejo de estados pesados; solo renderiza datos y emite eventos.
*   **Hooks:** Centralizan la lógica de la interfaz, el manejo de estados locales, efectos (`useEffect`), validaciones de formularios, y **cualquier cálculo o transformación de datos derivado del estado (ej. `useMemo` para calcular progresos o totales)**.
*   **Service:** Gestiona la comunicación con la API (Fetch/Axios). Define los **DTOs** (Data Transfer Objects) mediante Zustand para el manejo de estados globales de formularios e **Interfaces** para la comunicación de datos.

---

## Lineamientos de Desarrollo (API)

1.  **Prioridad a la Legibilidad:** El código debe ser legible, elegante y simple. La claridad es más importante que la micro-optimización. Documenta el *porqué* de la lógica compleja, no el *qué*.

2.  **Independencia y Abstracción:**
    *   Ninguna vista debe importar lógica (`Controller`, `Service`) de otra vista hermana.
    *   La funcionalidad compartida debe abstraerse en los **Modelos de Eloquent** (`app/Models`). Si dos vistas requieren datos similares, los archivos `Data` deben usar métodos en los modelos.

3.  **Firmas de Métodos Claras:**
    *   **No pasar parámetros en un único `array`**. Los métodos en `Services` y archivos `Data` deben tener parámetros explícitos y tipados. Esto clarifica las dependencias de la función.
    *   Si un parámetro es un array de objetos (ej. detalles de una factura), documenta su estructura con un comentario de bloque. Ejemplo:
        ```php
        /**
         * @param array $detalles // array de objetos ['producto_id' => int, 'cantidad' => int]
         */
        public function registrar(array $detalles) { ... }
        ```

4.  **Acceso a Datos:**
    *   Para entender la estructura de la base de datos, consulta siempre la carpeta `app/Models`.
    *   Usa **SQL puro** para consultas que involucren `JOINs` complejos. Es más explícito y a menudo más performante que el ORM para estos casos.
    *   Si diferentes vistas necesitan datos idénticos o muy similares, crea un método reutilizable en el modelo correspondiente. Si los filtros o la información requerida son muy distintos, crea una consulta específica por vista en su archivo `Data`.

5.  **Transacciones de Base de Datos:**
    *   Cualquier método de servicio que realice múltiples operaciones de escritura (registros, actualizaciones, eliminaciones) **debe** estar envuelto en una transacción (`DB::transaction()`). Esto garantiza la atomicidad y previene datos corruptos en caso de error.

6.  **Separación de Responsabilidades:**
    *   Si una funcionalidad o vista gestiona múltiples procesos (ej. "Requerimientos" y "Entregas"), no aglomeres toda la lógica en un solo `Controller` o `Service`. Sepáralos en archivos distintos para mantener la cohesión y simplicidad (`AprobacionController.php`, `RechazoController.php`).

7.  **Respuestas de API Estandarizadas:**
    *   Todas las respuestas de la API deben seguir una estructura consistente para éxitos, errores y validaciones, utilizando la clase `App\Shared\Responses\ApiResponse`.
        ```php
        // Estructura de éxito
        ['success' => true, 'data' => [...], 'message' => '...']

        // Estructura de error
        ['success' => false, 'data' => null, 'message' => 'Error...']
        ```