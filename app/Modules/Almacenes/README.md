# Módulo API: Almacenes (Deep Dive)

Este módulo gestiona la infraestructura física y la trazabilidad de responsabilidades en los puntos de almacenamiento de la empresa.

## 🛠 Componentes del Módulo

### 1. Controlador (`AlmacenesController`)

Gestiona las peticiones de administración de locales.

- **`get_almacenes`**:
    - No requiere parámetros.
    - Delega directamente al servicio para obtener la lista consolidada.
- **`crear_almacen`**:
    - **Validaciones**:
        - `nombre`: Requerido, string, máx 128 carácteres.
        - `es_principal`: Requerido, booleano.
        - `descripcion`: Opcional, string.
    - **Proceso**: Valida la entrada y envía los datos limpios al servicio.

### 2. Controlador de Responsables (`ResponsablesController`)

Gestiona quién tiene el mando físico del almacén.

- **`nuevo_responsable`**:
    - **Validaciones**:
        - `id_almacen`, `id_empleado`: Requeridos, enteros.
        - `fecha_inicio`: Requerido, formato fecha.
    - **Lógica**: Asegura que el empleado sea válido y que no haya traslapes de mando.

### 3. Servicio (`AlmacenesService`)

Contiene la lógica de negocio y orquestación.

- **`crear_almacen`**:
    - **Regla de Unicidad**: Verifica mediante la capa de Data si el nombre ya existe (incluso si está inactivo) para evitar confusiones operativas.
    - **Persistencia**: Si es válido, invoca la creación y retorna el objeto recién creado con su ID.
- **`nuevo_responsable`**:
    - **Cierre de Ciclo**: Automáticamente busca al responsable actual del almacén y marca su fecha de fin como el día anterior al inicio del nuevo, manteniendo la continuidad perfecta.

### 4. Capa de Datos (`AlmacenesData`)

Interactúa con el modelo `Almacen` y ejecuta consultas optimizadas.

- **`get_almacenes`**:
    - Ejecuta un **SQL Raw** complejo que utiliza subconsultas para:
        - Obtener una lista concatenada (`GROUP_CONCAT`) de los responsables actuales.
        - Contar en tiempo real a cuántas minas abastece cada almacén (`minas_count`).
- **`crear_almacen`**: Utiliza Eloquent (`Almacen::insertGetId`) para registrar la nueva entidad con estado inicial "Activo".

## 📂 Esquema de Base de Datos Relacionada

- `almacen`: Tabla maestra de locales.
- `responsable_almacen`: Tabla pivot con historial de mandos (id_almacen, id_empleado, fecha_inicio, fecha_fin).
- `almacen_mina`: Define la red de abastecimiento.
