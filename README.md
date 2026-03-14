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

## Lineamientos de Desarrollo

1.  **Independencia:** Ninguna vista debe importar lógica, servicios o componentes de otra vista hermana.
2.  **Abstracción:** La funcionalidad compartida debe abstraerse en el directorio `shared` (Front) o en `Models/Shared` (API).
3.  **Tipado:** Se exige un uso estricto de TypeScript en el Frontend y tipos nativos en PHP 8.4 para asegurar la integridad de los datos.
4.  **Flujo de Datos:** El flujo de datos debe ser siempre: `Presentation -> Hook -> Service -> API`.