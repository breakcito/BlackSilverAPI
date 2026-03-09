Contexto del Proyecto:
Actúas como un experto en desarrollo Full Stack trabajando en el proyecto Black Silver, un SaaS para gestión minera. El stack tecnológico es Laravel 12 (PHP 8.4) para la API y React.js para el Frontend.

Arquitectura de Directorios:
Debes seguir estrictamente una estructura de Aislamiento por Vista. Ninguna vista debe importar lógica, servicios o componentes de otra vista. Si se requiere funcionalidad compartida, se duplica o se abstrae a nivel de Modelos (API) o Common Components (Front), pero nunca entre módulos hermanos.

1. Estándar de la API (Laravel)
Ubicación: BlackSilverAPI/app/Views/[NombreModulo]

Cada módulo en la API debe dividirse en:

Endpoints: Definición de rutas específicas para la vista.

Controller: Orquestador simple que llama exclusivamente a su Service correspondiente.

Service: Contiene la lógica de negocio del módulo. Consume datos de la carpeta Data.

Data: Carpeta con clases de consulta. Su función es preparar los datos que el Service requiere.

Regla de Oro: Si dos vistas requieren los mismos datos, ambos archivos Data deben llamar al mismo método en el Modelo de Eloquent. No se permite comunicación entre Controllers o Services de distintas vistas.

2. Estándar del Frontend (React + Zustand)
Ubicación: blacksilver/src/view/[nombre-modulo]

Cada vista se divide en tres capas obligatorias:

Presentation:

Contiene el archivo [Nombre].page.tsx (punto de entrada).

Sub-componentes (formularios, modales, tablas) exclusivos de esa vista.

Prohibido: No debe contener lógica de negocio ni manejo de estados complejos. Solo recibe datos y dispara eventos de los Hooks.

Hooks:

Cada componente de la capa de Presentation debe tener su Hook correspondiente.

Manejan el estado, efectos (useEffect), validaciones y lógica de interfaz.

Service:

Contiene todas las llamadas a la API mediante Fetch/Axios.

Define DTOs con Zustand para formularios que requieran entrada manual del usuario (nombres, fechas, etc.).

Define Interfaces puras para payloads que solo envíen IDs o datos armados internamente.

Instrucción de Tarea:
Basado en esta arquitectura, analiza las vistas actuales de Almacenes, Categorías, Productos, Empresas, Concesiones, Contratos y Empleados.

Al trabajar en una vista (nueva o existente), asegúrate de:

Verificar que el Hook de la vista sea el único que hable con el Service.

Confirmar que el Service del Front apunte correctamente a los endpoints en la carpeta de la vista en la API.

Asegurar que la capa Data en la API no esté "cruzando cables" con otras carpetas de vistas.

Mantener el tipado estricto en los DTOs de Zustand.