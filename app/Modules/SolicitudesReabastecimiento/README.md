# Módulo API: Solicitudes de Reabastecimiento (Deep Dive)

Gestiona el flujo de reposición de stock del almacén solicitante, abarcando desde la petición hasta la recepción física final.

## 🛠 Componentes del Módulo

### 1. Controladores (`SolicitudesController`, `RecepcionesController`)

- **`registrar_solicitud`**:
    - **Validación**: Valida cabecera (premura, almacén destino) y un array de detalles.
- **`registrar_recepcion_logistica`**:
    - **Validación**: Recibe los items a recepcionar, diferenciando si van a un **Lote Existente** o si se debe **Crear un Lote Nuevo** (con sus datos técnicos).

### 2. Servicios de Negocio

#### `SolicitudesService`

- **`crear_solicitud`**:
    - **Correlativo**: Genera un código `REB-XXXX` único por almacén.
    - **Normalización**: Calcula la `cantidad_solicitada_base` multiplicando por el contenido de la presentación.
    - **Log Inicial**: Registra en el Timeline el estado "Esperando Aprobación".

#### `RecepcionesService` (Altamente Complejo)

- **`registrar_recepcion_logistica` / `registrar_recepcion_prestamo`**:
    - **Gestión de Lotes Inteligente**:
        - Si el item es un "Nuevo Lote", el servicio genera un nuevo correlativo de lote, calcula el stock inicial y lo inserta.
        - Si es "Lote Existente", rebaja el stock actual.
    - **Impacto en Kardex**: Genera un asiento de **Ingreso** por cada item, arrastrando el costo y vinculándolo al Vale de Recepción.
    - **Propagación de Estados**:
        - Compara la cantidad recibida acumulada contra lo entregado por logística/préstamo.
        - Si se cubre el total, marca el item como "Recepción Completa".
        - Si todos los items de una entrega están completos, marca la cabecera de la entrega como "Recepción Completa".
    - **Trazabilidad Cruzada**: Inserta logs en el módulo de Reabastecimiento mencionando el número de Vale de Entrega desde donde proviene el material.

### 3. Capa de Datos (`RecepcionesData`, `SolicitudesDetalleData`)

- **Queries de Estado**:
    - `get_cantidad_recepcionada_total_base_detalle`: Sumariza todos los ingresos parciales previos para determinar si se completó la entrega.
- **Persistencia**: Maneja las tablas pivot de recepciones y el historial de incidencias.

## ⚙️ Reglas de Negocio en Recepción

- **Tolerancia a Incidencias**: Permite marcar una recepción "Con Incidencia", lo cual activa una bandera visual en el frontend y añade una nota especial en el log de trazabilidad.
- **Automatización de Lotes**: Al crear un nuevo lote desde recepción, hereda automáticamente el Almacén Destino de la solicitud original.

## 📂 Esquema de Base de Datos Relacionada

- `solicitud_reabastecimiento`: Cabecera del pedido.
- `reabastecimiento_recepcion`: Cabecera del ingreso de almacén.
- `reabastecimiento_recepcion_detalle`: Items recibidos.
- `lote_producto`: Creación o actualización de saldos.
