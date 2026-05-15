# Contexto de Negocio y Procesos Operativos (Black Silver)

**Black Silver** es un sistema ERP (Enterprise Resource Planning) diseñado específicamente para resolver los desafíos logísticos y de abastecimiento en la industria minera.

El sistema digitaliza y conecta lo que ocurre en el corporativo (compras, finanzas) con lo que ocurre en el campo (almacenes remotos, distribución de insumos). A continuación, se detalla **qué hace el sistema por los usuarios** y la lógica de negocio que resuelve.

---

## 1. Estructura Organizativa y Operativa

El sistema necesita mapear quién opera, dónde está el inventario y dónde se consumen los recursos:

- **Empresas y Empleados**: Gestiona las entidades corporativas. El personal administrativo y logístico que usa el software (usuarios con cuentas de acceso y roles) se vincula a una **Empresa** matriz.
- **Concesiones y Minas**: Permite registrar el territorio legal y las operaciones físicas principales.
- **Labores y Contratistas**: Las minas se dividen en "Labores" (los frentes de trabajo específicos). Todo material despachado en el sistema debe apuntar a una Labor activa para saber exactamente a dónde van los recursos. Los **Contratistas** (el personal minero de campo) no interactúan con el software, pero se les ancla a estas labores operativas.
- **Almacenes**: Los puntos físicos de control de inventario. Tienen responsables designados y actúan como el puente entre la compra externa y el consumo real en la mina.

---

## 2. El Estandarte Logístico: Normalización de Unidades (Cantidad Base)

**El Problema Operativo**: Un proveedor vende explosivos en "Cajas de 50", el almacén despacha en "Paquetes de 10" y el operador pide en "Unidades". En un sistema tradicional, el inventario se rompe, se duplican productos o el stock cuadra mal.

**La Solución Black Silver**:
El sistema implementa una regla universal y matemática. Todo movimiento logístico (pedidos, recepciones, transferencias) se convierte automáticamente a su **Unidad de Medida Base** multiplicando la `Cantidad Solicitada` por el `Contenido por Presentación`.

- **Impacto de Negocio**: Esto garantiza que el almacenero siempre sepa exactamente cuántas unidades individuales tiene en stock, permitiendo entregar fracciones de caja (despachos parciales) sin generar huecos en el inventario ni dolores de cabeza contables.

---

## 3. Control de Inventario Nivel Auditoría (Lotes y Kardex)

En la industria, perder el rastro de un material sensible es un riesgo operativo grave.

- **Trazabilidad por Lotes**: Los insumos críticos no entran a una bolsa común de stock. Se registran como "Lotes" con fecha de vencimiento, proveedor de origen y fecha de ingreso. Si un lote falla o caduca, el sistema permite rastrear hacia atrás de dónde vino y a quién se le despachó.
- **Kardex Inmutable (Doble Saldo)**: El Kardex es el "notario" de la empresa. Ningún registro de movimiento se puede borrar o editar manualmente. Ante un error humano, se exige hacer un documento de "Ajuste de Stock". Además, el sistema guarda siempre el "Stock Anterior" y el "Nuevo Stock" por cada movimiento, permitiendo reconstruir la foto exacta del almacén en cualquier segundo de la historia.

---

## 4. Ciclo de Compras Blindado (Cero "Typos" y Auto-PO)

El proceso de compra está diseñado para automatizar el trabajo tedioso y evitar errores de digitación que cuestan dinero.

- **Cotizaciones Matemáticas**: El área de Logística ingresa las ofertas de los proveedores. El sistema calcula los netos automáticamente, sumando fletes y descontando si el precio traía o no impuestos (IGV). El usuario ve un comparativo exacto de costos reales.
- **Auto-Generación de Órdenes (Auto-PO)**: Cuando la gerencia aprueba una cotización, **el sistema prohíbe que el comprador tipee la Orden de Compra (OC) a mano**. La OC se genera sola, heredando exactamente los precios, unidades, e impuestos aprobados en el comparativo. Esto agiliza la compra y cierra la puerta a alteraciones no autorizadas posteriores a la aprobación.

---

## 5. El Flujo de la Necesidad: Requerimientos y Despachos

Resuelve el día a día: _"El operador necesita herramientas o insumos para trabajar hoy"_.

- **Auditoría Granular (Ítem por Ítem)**: Cuando se piden 10 tipos de productos distintos en un solo documento (Requerimiento), el almacenero no aprueba o rechaza "el documento entero". El sistema exige auditar **producto por producto**. El almacenero puede despachar 5, rechazar 2 por falta de stock y dejar 3 pendientes.
- **Trazabilidad de Decisiones**: Por cada ítem, el sistema guarda su propia línea de tiempo: _quién lo pidió, quién lo aprobó, cuándo se despachó y qué comentario o justificación dejó en caso de rechazo_.

---

## 6. Logística de Desvíos: Las Transferencias

En el mundo logístico real, los camiones de los proveedores no siempre llegan al almacén correcto. A veces descargan todo en un almacén central en la ciudad en lugar de subir a la operación remota.

- **Almacenes Puente**: En lugar de obligar al usuario a anular la Orden de Compra porque el proveedor la dejó en el lugar equivocado, el sistema permite recepcionar la mercadería en el Almacén Central y usar el módulo de **Transferencias de OC** para enviar la carga en un vehículo interno hacia su destino final. Esto mantiene el rastro de que la mercadería ya es propiedad de la empresa, pero está en "tránsito interno", salvaguardando la integridad del pago al proveedor y del inventario.

## 📦 Módulos del Sistema

### Configuración y Operaciones

- `almacenes`
- `concesiones`
- `contratistas` (API)
- `empresas`
- `minas-labores`

### Inventarios y Maestros

- `productos`
- `categorias`
- `lotes-productos`
- `kardex-productos`

### Gestión de Compras

- `proveedores`
- `cotizaciones`
- `ordenes-compra`
- `ordenes-compra-recepcion-transferencias`

### Flujos de Almacén (Salidas)

- `requerimientos-almacen` & `atencion`
- `solicitudes-reabastecimiento` & `atencion`
- `prestamos-almacen` & `atencion`

### Personal y Accesos

- `personal` (Empleados y Contratistas)
- `organigrama`
- `login`
- `perfil`
- `cuentas`
- `roles`

---

## 🏗 Arquitectura de la API: "Hybrid Modular Architecture"

El proyecto no sigue la estructura monolítica estándar de Laravel. Implementa un patrón de **Módulos Independientes** que conviven con una **Capa Global de Datos y Servicios** para evitar duplicidad de código.

### 1. `app/Modules/` - El Núcleo de Dominio

Cada carpeta dentro de `Modules` representa un micro-servicio interno enfocado en un proceso de negocio específico (ej. `Cotizaciones`, `RequerimientosAlmacenAtencion`).

- **Endpoints (`XEndpoints.php`)**: Definición manual de rutas tipo API.
- **Controllers**: Validadores de entrada (Requests) y orquestadores del flujo.
- **Services**: Contenedores de la **lógica de negocio exclusiva del módulo**.
- **Data (Local)**: Consultas SQL específicas que solo atañen al módulo.

### 2. Capa Global: Servicios y Datos Compartidos (`app/Services/` y `app/Data/`)

Existen entidades transversales que son requeridas constantemente por múltiples módulos (ej. consultar un producto, actualizar el stock). Para no repetir código, esta lógica se centraliza globalmente:

- **Maestros Corporativos**: `AlmacenesData/Service`, `EmpleadosData/Service`, `EmpresasData/Service`.
- **Catálogos y Terceros**: `ProductosData/Service`, `UnidadesMedidaData/Service`, `ProveedoresData/Service`, `PersonalExternoData/Service`.
- **Motor de Inventario (Crítico)**:
    - `LotesProductosData/Service`: **Punto de entrada principal** para registrar movimientos de inventario. Este servicio encapsula la actualización del stock físico y la inyección automática en el Kardex. Todo módulo que altere el inventario debe invocarlo (`update_stock` o `crear_lote`) para evitar duplicar lógica de auditoría.
    - `KardexProductosData/Service`: Servicio interno gestionado automáticamente por Lotes. Mantiene el registro de auditoría (doble saldo) de la empresa, pero **no debe ser invocado directamente** por los módulos para no corromper el flujo.
- **Estructura UI**: `MenuNavData/Service` (para navegación basada en permisos).

### 3. El Controlador Auxiliar (`AuxController.php` y `AuxEndpoints.php`)

Para evitar que cada módulo defina sus propios endpoints redundantes (ej. pedir la lista de almacenes desde Cotizaciones y nuevamente desde Requerimientos), se implementó el ecosistema `Aux`.

- **Propósito**: Actúa como un _Hub_ centralizado para peticiones de catálogos y selects recurrentes (`get_almacenes`, `get_productos`, `get_lotes_disponibles`).
- **Regla de Consumo**: El Frontend (y específicamente el `AuxService` de React) debe apuntar siempre a los endpoints auxiliares `/api/aux/...` para popular modales de búsqueda o filtros genéricos.

### 4. Estandarización de Estados (`app/Shared/Enums/`)

El sistema hace un uso intensivo de _Backed Enums_ de PHP para evitar "magic strings" y mantener integridad de datos.

- **Regla de Ordenamiento Estricta**: Cada tabla o proceso operativo físico (Ej. `Entrega`, `Recepcion`, `Solicitud`, `OrdenCompra`) **debe tener su propio Enum dedicado** en su respectiva subcarpeta dentro de `Shared/Enums`.
- **Ejemplo**: Las recepciones usan `EstadoOCTransRecepcion`, y las transferencias usan `EstadoOCTransferencia`. No se reciclan Enums genéricos entre procesos distintos para evitar choques lógicos.

## 🏛️ Reglas Críticas de Desarrollo

1.  **Consistencia de Respuestas**: Toda respuesta debe retornar a través de los helpers globales `ApiResponse::success()` o `ApiResponse::error()`.
2.  **Prohibición de Rutas Redundantes**: Usar `AuxController` para listados recurrentes.
3.  **Atomicidad Lógica**: Usar `DB::transaction()` en procesos que impliquen múltiples registros.
4.  **No Reutilización Forzada**: No crear métodos o clases sumamente complejos que intenten abarcarlo todo. Por ejemplo, ante los casos de "Editar" y "Registrar", sepáralos. La legibilidad y facilidad de mantenimiento son prioritarias sobre una reutilización que oscurezca el código.
    *   Si eres una IA y aunque el usuario no lo pida, **DEBES** crear métodos para cada caso específico. Si te pide algo que contradice la regla, analiza, explica y dale una mejor alternativa antes de proceder.
5.  **Uso Justificado de Arrays como Parámetros**:
    *   **NUNCA** recibir un array como parámetro en métodos de las capas de Servicio, Data o Modelo si no está plenamente justificado. Esto hace que el código sea impredecible y difícil de depurar.
    *   **Excepciones**: Solo es válido en casos de Cabecera + Detalles (ej. Orden de Compra) o registros masivos donde sea manejable y necesario.
    *   **Documentación Obligatoria**: Si un método recibe un array, se **debe documentar exactamente qué contiene** dicho array para evitar adivinanzas.
6.  **Documentación de Métodos**:
    *   **Todos los métodos** de todas las capas deben estar documentados de forma breve, concisa y clara, indicando qué hace y para qué se usa.
    *   No es necesario redocumentar cada parámetro simple, pero es **obligatorio** documentar el contenido de los parámetros de tipo array que no sean solo un array de archivos.
    *   Si eres una IA, aunque el usuario no lo solicite, es **obligatorio** documentar todos los métodos.


## ⚙️ Ejecución

1. Configurar el archivo `.env`
2. `composer install`
3. `php artisan key:generate`
4. `php artisan storage:link` (Crítico para que los archivos multimedia y adjuntos sean públicos).
5. `php artisan serve`
6. `php artisan reverb:start` (En una terminal separada, para que funcionen los eventos en tiempo real)

---

## 🤖 Comandos Obligatorios para IA
> [!IMPORTANT]
> Después de realizar cualquier cambio en el código de la API, es **OBLIGATORIO** ejecutar el siguiente comando de análisis estático:
> ```bash
> ./vendor/bin/phpstan
> ```
> Esto garantiza que la lógica, los tipos de PHP y las convenciones del sistema se mantengan íntegras.