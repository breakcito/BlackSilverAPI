# Módulo API: Requerimientos de Almacén (Deep Dive)

Este módulo gestiona la creación y trazabilidad de las solicitudes de materiales desde el frente de trabajo (labores) hacia los almacenes.

## 🛠 Componentes del Módulo

### 1. Controlador (`RequerimientosController`)

- **`crear_requerimiento`**:
    - **Validaciones**:
        - `id_mina`, `id_almacen_destino`: Requeridos, enteros.
        - `premura`: Valida contra el Enum `Premura` (Baja, Media, Alta, Crítica).
        - `detalles`: Array con al menos 1 item. Cada item valida `id_producto`, `id_unidad_medida`, `contenido_por_presentacion` y `cantidad_solicitada` (mín 0.01).
- **`get_requerimientos`**: Obtiene el perfil del usuario autenticado de los atributos del request para filtrar por `id_empleado_solicitante`.

### 2. Servicio (`RequerimientosService`)

- **`crear_requerimiento` (Transaccional)**:
    - **Correlativo**: Solicita un nuevo número secuencial único (`REQ-XXXX`) filtrado por el almacén de destino.
    - **Cálculo de Cantidad Base**: Multiplica automáticamente la `cantidad_solicitada` por el `contenido_por_presentacion` para normalizar el stock en el Kardex.
    - **Trazabilidad**: Por cada detalle insertado, genera el primer hito en la tabla de trazabilidad ("Requerimiento Generado") vinculado al empleado que lo creó.
    - **Consistencia**: Retorna el objeto del requerimiento completo, incluyendo las labores asociadas, para confirmación inmediata en el frontend.

### 3. Capa de Datos (`RequerimientosData` y `RequerimientosDetalleData`)

- **Queries de Filtrado**:
    - `get_minas`: Filtra solo las unidades mineras donde el empleado tiene labores asignadas activas.
    - `get_almacenes_by_mina`: Cruza la tabla `almacen_mina` para retornar solo los puntos de despacho autorizados para esa operación.
- **Persistencia**:
    - `crear_requerimiento`: Inserta en la tabla `requerimiento_almacen`.
    - `asignar_labor`: Maneja la relación muchos-a-muchos entre requerimientos y frentes de trabajo (`requerimiento_almacen_labor`).

## ⚙️ Lógica de Trazabilidad

El sistema implementa un log granular por cada item solicitado (`requerimiento_almacen_detalle_trazabilidad`). Esto permite saber:

1. Quién generó el pedido.
2. Quién lo aprobó/rechazó en almacén.
3. Quién realizó el despacho físico.

## 📂 Esquema de Base de Datos Relacionada

- `requerimiento_almacen`: Cabecera del pedido.
- `requerimiento_almacen_detalle`: Items, cantidades y unidades.
- `requerimiento_almacen_labor`: Imputación de costos por frente minero.
- `requerimiento_almacen_detalle_trazabilidad`: Historial de estados por item.
