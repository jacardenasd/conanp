# CONANP Performance Evaluation System - AI Agent Instructions

## System Overview
PHP-based employee performance evaluation system for CONANP (Comisión Nacional de Áreas Naturales Protegidas). Manages individual/collective goals, competency assessments, training, and generates performance reports with multi-level approval workflows.

### Database: `evaluacion_conanp`
**Connection**: [config/db.php](config/db.php) (MySQL 5.7 via PDO, charset utf8)

**Core Tables & Relationships**:

1. **`usuarios`** - Sistema central de usuarios (~1000 registros)
   - `user_id` (PK), `username` (unique), `password` (bcrypt)
   - `role` (1=user, 2=admin, 3=superadmin)
   - `tipo_usuario` (1=SPC/acceso completo, 2+=solo capacitación)
   - `jefe_id` → FK a `usuarios.user_id` (jerarquía de reportes)
   - `unidad_id` → FK a `unidades.id`
   - `adscripcion_id` → FK a `adscripciones.id`
   - `puesto_nivel` (1-6) - determina competencias aplicables
   - `requiere_cambio_password`, `estatus` (1=activo)

2. **`metas`** - Metas individuales por usuario/periodo
   - `user_id` → FK a `usuarios`
   - `periodo` (año como int: 2025, 2026)
   - `indicador` (descripción), `unidad` (medida)
   - `ponderacion` (%, debe sumar 100% por usuario)
   - Escala de evaluación: `sobresaliente`, `satisfactorio`, `no_satisfactorio`, `no_aprobatorio`, `deficiente`
   - `resultado` (autoevaluación 0-100), `resultado_final` (evaluación jefe 0-100)

3. **`metas_colectivas`** - Metas compartidas por unidad administrativa
   - `unidad_id` → FK a `unidades`
   - `periodo`, `indicador`, `ponderacion`, `resultado`
   - Aplica a todos los usuarios de la unidad

4. **`calificaciones`** - Consolidado de resultados por usuario/periodo
   - `user_id`, `periodo` (clave única)
   - Puntajes por sección: `individuales`, `aportaciones_destacadas`, `actividades_extraordinarias`, `capacitacion`, `gerenciales`
   - Status por sección: `estatus_metas` (0=sin iniciar, 1=capturado, 2=autoevaluado, 3=aprobado por jefe)
   - Similar para: `estatus_aportaciones_destacadas`, `estatus_actividades_extraordinarias`, `estatus_gerenciales`, `estatus_capacitacion`

5. **`calificaciones_colectivas`** - Resultados de metas colectivas
   - `unidad_id`, `periodo`, `resultado`, `estatus`

6. **`competencias`** - 5 competencias gerenciales fijas
   - ID 1: Visión Estratégica
   - ID 2: Liderazgo  
   - ID 3: Orientación a Resultados
   - ID 4: Negociación
   - ID 5: Trabajo en Equipo

7. **`competencias_descripcion`** - ~80 comportamientos observables
   - `competencia_id` → FK a `competencias`
   - `nivel` (1-6, según puesto)
   - `descripcion` (texto del comportamiento)

8. **`competencias_evaluacion`** - Evaluaciones individuales
   - `user_id`, `descripcion_id`, `periodo`
   - `tipo` ('auto'=autoevaluación, 'jefe'=evaluación del jefe)
   - `evaluacion` (texto Likert), `valor` (0/20/50/80/100)

9. **`competencias_valores`** - Valores base por competencia/nivel
   - `competencia_id`, `nivel`, `valor`
   - Define peso de cada competencia según nivel de puesto

10. **`competencias_pesos`** - Ponderación de competencias
    - `nivel`, `vision`, `liderazgo`, `orientacion`, `negociacion`, `trabajo` (porcentajes)

11. **`capacitacion`** - Cursos tomados por usuarios
    - `user_id`, `periodo`, `nombre_curso`, `horas`, `calificacion`
    - `categoria` → FK, `modalidad`, `finalidad`
    - `validado` (jefe), `validado_rh` (RH)
    - `archivo_pdf` (constancia)

12. **`actividades_extraordinarias`** & **`aportaciones_destacadas`**
    - `user_id`, `periodo`, `descripcion`, `archivo_pdf`
    - `validado` (jefe), `validado_rh`, `estatus`, `comentarios`

13. **Catálogos**:
    - `unidades` (173) - Unidades administrativas de CONANP
    - `adscripciones` (173) - Adscripciones específicas por unidad
    - `periodos` - Años de evaluación con `estatus` (Captura/Evaluación)
    - `capacitacion_categorias`, `capacitacion_modalidades`, `capacitacion_finalidades`
    - `unidades_medida` - Para medir logro de metas
    - `mensajes`, `mensajes_respuestas` - Sistema de notificaciones internas

## Architecture Patterns

### Session-Based Multi-Role System
**Always require these includes at file top:**
```php
require 'config/db.php';          // Provides $pdo PDO connection
require 'includes/session.php';    // Provides checkLogin($requiredRole)
require 'includes/variables.php';  // Provides obtener_variable($nombre)
checkLogin();  // or checkLogin(2) for admin, checkLogin(3) for superadmin
```

**Session variables** (set in [login.php](login.php#L44-L55)):
- `$_SESSION['user_id']`, `$_SESSION['periodo']` (current evaluation period)
- `$_SESSION['role']` (1=user, 2=admin, 3=superadmin)
- `$_SESSION['tipo_usuario']` (1=SPC/full access, 2+=limited to training)
- `$_SESSION['unidad_id']`, `$_SESSION['adscripcion_id']`, `$_SESSION['jefe_id']`

### Navigation & Access Control
Navigation menu: [assets/main_navigation.php](assets/main_navigation.php)
- `role == 3`: Full admin panel access (catalogues, evaluations, system)
- `tipo_usuario == 1`: SPC users see full evaluation features
- `tipo_usuario != 1`: Redirected to [mi_capacitacion.php](mi_capacitacion.php) (training only)

Admin pages organized in 4 sections ([main_navigation.php](assets/main_navigation.php#L2-L9)):
1. **Catalogues** (`$admin2`): Units, Assignments, Positions, Users, Training categories
2. **Evaluations** (`$admin3`): Goals (individual/collective), Training, Grades, Reports
3. **System** (`$admin4`): Announcements, Messages, Files, Calendar, Variables
4. **Periods**: Evaluation period management

### File Naming Conventions
- `admin_*.php`: Admin-only pages (require `checkLogin(2)` or higher)
- `mi_*.php`: User's own data (my goals, my evaluation, my training)
- `mis_*.php`: User's own plural items (my collaborators)
- `*_agregar.php`: Create new record forms
- `*_editar.php`: Edit existing record forms
- `*_eliminar.php`: Delete operations
- `*_guardar.php`: Form processing/save endpoints

### Page Structure Template
Standard HTML structure used across system:
```php
<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';
checkLogin();  // Add role parameter if admin page

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
// ... more variables from DB
?>
<!DOCTYPE html>
<html>
<head>
    <link href="assets/css/ltr/all.min.css" rel="stylesheet">
    <!-- Limitless template assets -->
</head>
<body>
<?php require_once('assets/main_navbar.php'); ?>
<div class="page-content">
    <?php require_once('assets/main_navigation.php'); ?>
    <div class="content-wrapper">
        <div class="content-inner">
            <div class="content"><?php /* Page content */ ?></div>
        </div>
        <?php require_once('assets/footer.php'); ?>
    </div>
</div>
</body>
</html>
```

## Critical Workflows

### Evaluation Period Flow
1. **Capture** (`estatus_periodo = 'Captura'`): Users define goals in `metas` table
2. **Self-Evaluation** (`estatus_periodo = 'Evaluación'`): Users propose results (`resultado` field)
3. **Manager Review**: Managers approve/adjust (`resultado_final` field)
4. **Competency Assessment**: Likert-scale evaluations in `competencias_evaluacion` (tipo='auto'/'jefe')
5. **Final Calculation**: [cierre_periodo_evaluar.php](cierre_periodo_evaluar.php#L50-L70) calculates weighted scores to `calificaciones` table

**Status tracking** in `calificaciones` table:
- `estatus_metas`: 0=not started, 1=captured, 2=self-evaluated, 3=manager-approved
- `estatus_aportaciones_destacadas`, `estatus_actividades_extraordinarias`: Similar status flags
- `estatus_gerenciales`: Competency evaluation status

### User Data Validation
[verificarDatosUsuario()](includes/variables.php#L28-L50) enforces required fields:
- Redirects to [editar_datos_personales.php](editar_datos_personales.php) if missing: `jefe_id`, `adscripcion_id`, `unidad_id`, `puesto_nombre`
- Called on every page to ensure data integrity

### Report Generation
Excel reports use **PhpSpreadsheet** with templates from `plantillas/` directory:
- Load template: `IOFactory::createReader('Xlsx')->load('plantillas/file.xlsx')`
- Write data to specific cells: `$sheet->setCellValue("A1", $value)`
- Save to `reportes/` directory with timestamp naming
- See [generar_excel_individual.php](generar_excel_individual.php) for pattern

PDF reports use **DomPDF**:
- Typically generate from HTML templates in `plantillas/` directory
- Both libraries installed via Composer ([composer.json](composer.json))

### Competency Evaluation System
**Likert-scale options** (constante en todo el sistema):
```php
['Muy Característico' => 100, 'Característico' => 80, 
 'Poco Característico' => 50, 'No es Característico' => 20, 'No Aplica' => 0]
```

**Lógica de cálculo** ([guardar_competencias.php](guardar_competencias.php#L32-L70)):
1. Obtener nivel del puesto (`usuarios.puesto_nivel` 1-6)
2. Cargar valores base de `competencias_valores` filtrados por nivel
3. Usuario evalúa ~12-15 comportamientos (depende del nivel) con escala Likert
4. Guardar en `competencias_evaluacion` con `tipo='auto'` (autoevaluación)
5. Jefe evalúa los mismos comportamientos con `tipo='jefe'`
6. Calcular promedio ponderado usando `competencias_pesos`:
   - Promedio de valores por competencia (excluyendo valor=0)
   - Multiplicar por peso según nivel de puesto
   - Dividir entre suma de pesos
7. Guardar calificación final en `calificaciones.gerenciales` (0-100)

**Ejemplo de SQL de cálculo** (usado en [guardar_competencias_colaborador.php](guardar_competencias_colaborador.php#L87-L112)):
```sql
SELECT ROUND(SUM(avg_valor * peso) / SUM(peso), 2) AS calificacion_final
FROM usuarios u
JOIN (
    SELECT user_id, competencia_id, AVG(valor) AS avg_valor
    FROM competencias_evaluacion
    WHERE periodo = ? AND tipo = 'jefe' AND valor > 0
    GROUP BY user_id, competencia_id
) AS eval ON eval.user_id = u.user_id
JOIN competencias_pesos ON u.puesto_nivel = competencias_pesos.nivel
```

## Common Gotchas

### Password Handling
- First-time login: Check `requiere_cambio_password` flag → force redirect to [cambiar_password.php](cambiar_password.php)
- Passwords hashed with `password_verify()` and `password_hash()`

### Redirect After Login
[login.php](login.php#L62-L67) checks `$_SESSION['redirect_after_login']` to return users to protected pages

### Frontend Assets
- **Template**: Limitless Bootstrap theme (assets/css, assets/js)
- **Icons**: Phosphor icons (`ph-*` classes), Icomoon icons (`icon-*` classes)
- **DataTables**: Used extensively for admin tables ([datatables_basic.js](assets/demo/pages/datatables_basic.js))
- **Select2**: Dropdown enhancement for complex selects

### Query Patterns
**Always use PDO prepared statements** to prevent SQL injection:
```php
$stmt = $pdo->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
```

### Error Reporting
Development mode enabled in most files:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## Key Business Rules

### Goal Weighting
- Individual goals (`metas`): Sum of `ponderacion` must equal 100%
- Validated in [cierre_periodo_evaluar.php](cierre_periodo_evaluar.php#L42-L47)

### Hierarchical Evaluation
- Users evaluate themselves (`tipo='auto'`)
- Managers evaluate direct reports (`tipo='jefe'`) 
- Manager ID stored in `usuarios.jefe_id`

### Collective Goals
- Assigned at unit level (`unidades` table)
- Shared by all users in `metas_colectivas.unidad_id`
- Separate workflow/tables: `metas_colectivas`, `calificaciones_colectivas`

### Training Module
- Available to all users regardless of `tipo_usuario`
- Files in `capacitacion/` directory
- Tracks courses, categories, modalities, purposes

## Development Commands
- **Environment**: MAMP local server (Windows)
- **Database**: Access via phpMyAdmin or MySQL client
- **No build process**: Direct PHP execution
- **Testing**: Manual testing through UI, no automated test suite

## When Making Changes
1. **Always check `estatus_periodo`** before allowing data modifications
2. **Verify `role` and `tipo_usuario`** for access control
3. **Update both user and admin views** if changing data structures
4. **Test period closure flows** - most critical/complex part of system
5. **Check message system** ([mensajes.php](mensajes.php)) - used for workflow notifications
