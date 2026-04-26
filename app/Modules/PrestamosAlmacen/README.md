# Módulo API: Préstamos entre Almacenes (Deep Dive)

Gestiona la transferencia temporal de stock entre almacenes y su posterior retorno (reposición).

## 🛠 Componentes del Módulo

### 1. Controladores (`PrestamosAlmacenController`, `ReposicionesController`)

- **`get_resumen`**: Lista los préstamos filtrando por el almacén del usuario (ya sea como origen o destino).
- **`registrar_reposicion`**:
    - **Validación**: Valida los IDs de préstamo, personal que entrega/recibe, y un array de items con los lotes de donde saldrá la reposición.

### 2. Servicios de Negocio

#### `ReposicionesService` (Complejo)

- **`registrar_reposicion`**:
    - **Validación de Stock Previa**: Antes de cualquier cambio, consulta todos los lotes involucrados y valida que tengan saldo suficiente para la devolución.
    - **Lógica de Kardex Dual**:
        1. Rebaja el stock del almacén que está "devolviendo" el préstamo.
        2. Genera una salida en el Kardex del origen de la reposición.
        3. El ingreso en el almacén original se maneja en el submódulo de **Atención de Reposiciones**.
    - **Trazabilidad de Retorno**: Actualiza el campo `cantidad_repuesta` en el detalle del préstamo original. Esto permite saber si un préstamo ha sido devuelto parcial o totalmente.
    - **Historial Consolidado**: El método `get_historial` une la información de la reposición con las recepciones confirmadas, dando una visión 360° del estado del retorno.

#### `PrestamosService`

- Gestiona la aprobación técnica del préstamo y la visualización de la trazabilidad (Timeline) de cada item transferido.

### 3. Capa de Datos (`ReposicionesData`, `PrestamosData`)

- **SQL de Trazabilidad**: Cruza préstamos, entregas y reposiciones para reconstruir el ciclo de vida del material.
- **Correlativos RPS**: Genera números secuenciales únicos para las Guías de Reposición (`RPS-XXXX`).

## ⚙️ Reglas de Negocio

- **Cierre de Ciclo**: Un préstamo se considera "Cerrado" solo cuando la suma de las cantidades en las recepciones de reposición iguala a la cantidad prestada original.
- **Integridad de Lote**: La reposición debe realizarse indicando de qué lote físico del almacén deudor está saliendo la mercadería.

## 📂 Esquema de Base de Datos Relacionada

- `prestamo_almacen_reposicion`: Cabecera del documento de devolución.
- `prestamo_almacen_reposicion_detalle`: Items y lotes devueltos.
- `prestamo_almacen_detalle`: Mantiene el saldo pendiente de devolución (`cantidad_repuesta`).
- `kardex_producto`: Registra los movimientos físicos de salida por retorno.
