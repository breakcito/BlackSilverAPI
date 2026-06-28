# Módulo API: Categorías (Deep Dive)

Gestiona la taxonomía de productos y las reglas de negocio que definen qué áreas pueden consumir qué materiales.

## 🛠 Componentes del Módulo

### 1. Controlador (`CategoriasController`)

- **`crear_categoria`**:
    - **Validación**: Valida campos técnicos (`nombre`, `tipo_producto`) y flags operativos (`para_cocina`, `para_mina`).

### 2. Servicio de Clasificación (`CategoriasService`)

- **`crear_categoria`**:
    - **Validación de Regla de Negocio**: Obliga a que toda categoría pertenezca al menos a un área operativa (`para_cocina` o `para_mina`). No se permiten categorías "huérfanas".
    - **Gestión de Consumibles**: Si la categoría es marcada como `es_consumible`, el servicio orquesta la creación de vínculos en la tabla de relaciones consumidoras. Esto es vital para el módulo de Requerimientos, ya que filtra qué productos puede pedir cada labor.

### 3. Capa de Datos (`CategoriasData`)

- **Queries de Integridad**:
    - `verificar_nombre_duplicado`: Valida unicidad en el catálogo maestro.
- **Persistencia de Relaciones**:
    - `establecer_consumidoras`: Realiza un proceso de "Sync" (elimina relaciones previas e inserta las nuevas) para mantener limpia la tabla de vinculación de consumo.

## ⚙️ Reglas de Negocio

- **Filtro de Requerimientos**: El sistema utiliza la relación `categoria_consumidora` para validar en tiempo real si un usuario puede agregar un producto a su pedido basándose en la labor (centro de costo) seleccionada.
- **Tipo de Requerimiento**: Clasifica si los items son para Stock, Gasto Directo o Activo Fijo.

## 📂 Esquema de Base de Datos Relacionada

- `categoria`: Maestro de clasificaciones.
- `producto`: Vinculado a una categoría única.
