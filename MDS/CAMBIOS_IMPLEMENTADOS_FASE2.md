# 📋 CAMBIOS IMPLEMENTADOS - CONANP SED

**Fecha:** Enero 2025  
**Versión:** Fase 2 - Bloqueadores y Validaciones  
**Estado:** ✅ 8 de 12 cambios completados

---

## 📊 ESTADO DE IMPLEMENTACIÓN

| # | Cambio | Archivo(s) | Estado | Descripción |
|---|--------|-----------|--------|-----------|
| 1 | Avance Evaluación | `index.php` | ✅ | Filtrar recuadro por `tipo_usuario` (SPC + Primer Nivel) |
| 2 | Bloqueo Capacitación 2025 | `capacitacion_guardar.php` | ✅ | Variable `bloquear_capacitacion_2025` |
| 3 | Bloqueo Metas Colectivas 2025 | `admin_metas_colectivas.php` | ✅ | Variable `bloquear_metas_colectivas_2025` |
| 4 | Finalizar Metas PDF | `generar_reporte_individual.php` | ✅ | Botón FINALIZAR antes de descargar Excel |
| 5 | Validación Cascada | `mis_actividades_extraordinarias.php`<br>`mis_aportaciones_destacadas.php` | ✅ | Flujo: Jefe valida → RH valida → bloquea |
| 6 | Flujo TERMINAR | `mis_colaboradores.php` | ✅ | Usuario/Jefe TERMINA y bloquea definitivamente |
| 7 | Bloqueo Competencias | `evaluar_competencias.php`<br>`admin_evaluar_competencias.php` (NEW) | ✅ | Bloquear autoevaluación tras validación jefe |
| 8 | Descarga Cédula | `reporte_cedula_resultados.php` | ✅ | Validar: estatus_metas=3, colectivas≥2, gerenciales=3 |
| 9 | Paridad Primer Nivel | `index.php` | ✅ | Incluido en cambio #1 |
| 10 | Semáforos Correctos | `index.php` | ⏳ | Ajustar lógica: Rojo→Amarillo→Verde |
| 11 | Bloqueos Perfil | `editar_datos_personales.php` | ✅ | 8 campos permanentemente bloqueados |
| 12 | Asignación tipo_usuario | `importar_usuarios_asistente.php` | ⏳ | Importar usuarios.csv |

---

## 🔧 CAMBIOS DETALLADOS

### ✅ Cambio #4: Metas Individuales - Botón FINALIZAR

**Archivo:** `generar_reporte_individual.php`

**Modificaciones:**

1. **Agregar headers de sesión y funciones:**
   ```php
   require 'includes/session.php';
   require 'includes/finalizaciones.php';
   checkLogin();
   ```

2. **Validar estado de finalización:**
   ```php
   $stmt_final = $pdo->prepare("SELECT finalizado_metas FROM calificaciones WHERE user_id = ? AND periodo = ?");
   $stmt_final->execute([$user_id, $periodo]);
   $metas_finalizadas = $stmt_final->fetch()['finalizado_metas'] ?? 0;
   ```

3. **Procesar POST para finalizar:**
   - Validar que existan metas
   - UPDATE `calificaciones`: `finalizado_metas=1, fecha_finalizacion_metas=NOW(), usuario_finalizacion_id=?`
   - Mostrar mensaje de confirmación

4. **UI Pre-Descarga:**
   - Si NO hay `confirmar_descarga=1`, mostrar página con:
     - Estado actual (✏️ EN EDICIÓN o 🔒 FINALIZADO)
     - Botón **Descargar Reporte (Excel)**
     - Botón **FINALIZAR METAS** (solo si no está finalizado)
     - Confirmación: "¿Estás seguro de FINALIZAR?"

5. **Comportamiento post-FINALIZAR:**
   - `finalizado_metas=1` en tabla `calificaciones`
   - Botón FINALIZAR desaparece, muestra "🔒 METAS FINALIZADAS"
   - En `mi_metas_individuales.php`: Deshabilitar edición si `finalizado_metas=1`

**Dependencias SQL:** 
- Columns: `calificaciones.finalizado_metas, fecha_finalizacion_metas, usuario_finalizacion_id`
- ⚠️ Requiere ejecución de SQL migration

---

### ✅ Cambio #5: Actividades/Aportaciones - Validación Cascada

**Archivos:** 
- `mis_actividades_extraordinarias.php`
- `mis_aportaciones_destacadas.php`

**Modificaciones:**

1. **Agregar headers:**
   ```php
   require 'includes/finalizaciones.php';
   ```

2. **Procesar acciones POST:**
   ```php
   if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
       $accion = $_POST['accion'];
       $id = intval($_POST['id'] ?? 0);
       $tabla = 'actividades_extraordinarias'; // o 'aportaciones_destacadas'
       
       if ($accion === 'validar_jefe') {
           validar_por_jefe($pdo, $tabla, $id, $_SESSION['user_id'], 1);
       } elseif ($accion === 'rechazar_jefe') {
           validar_por_jefe($pdo, $tabla, $id, $_SESSION['user_id'], 0);
       } elseif ($accion === 'validar_rh') {
           validar_por_rh($pdo, $tabla, $id, $_SESSION['user_id'], 1);
       } elseif ($accion === 'rechazar_rh') {
           validar_por_rh($pdo, $tabla, $id, $_SESSION['user_id'], 0);
       }
   }
   ```

3. **UI con botones de validación:**
   - Usuario ve registro capturado
   - Jefe ve: Botón "✓ Validar" | Botón "✗ Rechazar"
   - Tras validación jefe, RH ve: Botón "✓ Validar RH" | Botón "✗ Rechazar"
   - Tras validación RH: `bloqueado_edicion=1`, no puede editarse

**Flujo Completo:**
```
USUARIO → Carga actividad (estatus=1)
      ↓
JEFE → Valida (usuario_validacion_jefe_id=?, fecha_validacion_jefe=NOW())
      ↓
RH → Valida (usuario_validacion_rh_id=?, fecha_validacion_rh=NOW())
      ↓
✓ FINALIZADO (bloqueado_edicion=1)
```

**Dependencias SQL:**
- Columns: `usuario_validacion_jefe_id, fecha_validacion_jefe, usuario_validacion_rh_id, fecha_validacion_rh, rechazado_por_jefe, rechazado_por_rh, bloqueado_edicion`
- ⚠️ Requiere ejecución de SQL migration

---

### ✅ Cambio #6: Mis Colaboradores - Flujo TERMINAR

**Archivo:** `mis_colaboradores.php`

**Modificaciones:**

1. **Agregar handlers POST:**
   ```php
   if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
       $meta_id = intval($_POST['meta_id'] ?? 0);
       
       if ($_POST['accion'] === 'usuario_termina') {
           usuario_termino_captura($pdo, $meta_id, $_SESSION['user_id']);
       } elseif ($_POST['accion'] === 'jefe_termina') {
           jefe_termino_evaluacion($pdo, $meta_id, $_SESSION['user_id']);
       }
   }
   ```

2. **Botones en tabla de colaboradores:**
   - **Usuario:** "Terminar Captura" → `usuario_termino_captura=1`
   - **Jefe:** "Terminar Evaluación" → `jefe_evaluo=1, metas_finalizadas=1`

3. **Comportamiento:**
   - Tras terminar usuario: Botón desaparece, muestra "✅ Propuesta finalizada"
   - Tras terminar jefe: Bloqueo permanente, ni usuario ni jefe pueden cambiar

**Flujo:**
```
USUARIO → Carga meta → Click "Terminar Captura" → usuario_termino_captura=1
       ↓
JEFE → Evalúa → Click "Terminar Evaluación" → jefe_evaluo=1, metas_finalizadas=1
       ↓
🔒 BLOQUEADO DEFINITIVAMENTE
```

**Dependencias SQL:**
- Columns: `metas.usuario_termino_captura, fecha_termino_usuario, jefe_evaluo, fecha_evaluacion_jefe, metas_finalizadas`
- ⚠️ Requiere ejecución de SQL migration

---

### ✅ Cambio #7: Autoevaluación Gerencial - Bloqueos

**Archivos:**
- `evaluar_competencias.php`
- `admin_evaluar_competencias.php` (NUEVO)

**Modificaciones evaluar_competencias.php:**

1. **Verificar bloqueo antes de permitir edición:**
   ```php
   $stmt = $pdo->prepare("SELECT bloqueado_edicion FROM competencias_evaluacion 
       WHERE user_id = ? AND periodo = ? AND tipo = 'auto' LIMIT 1");
   $bloqueado = $stmt->fetch()['bloqueado_edicion'] == 1;
   
   if ($bloqueado) {
       // Mostrar página: "🔒 Autoevaluación Bloqueada"
       exit;
   }
   ```

2. **Comportamiento:**
   - Usuario completa autoevaluación (tipo='auto')
   - Jefe evalúa (tipo='jefe')
   - Jefe valida → `bloquear_competencias_evaluacion()` → `bloqueado_edicion=1`
   - Usuario ya no puede editar

**Nuevo archivo admin_evaluar_competencias.php:**

- **Acceso:** Solo Super Admin (`checkLogin(3)`)
- **Funcionalidad:**
  1. Listar todos los usuarios con estado de competencias
  2. Mostrar columnas:
     - Usuario, Puesto, Nivel
     - Auto-evaluación: ✓ Capturada / -
     - Evaluación Jefe: 2/5 competencias / -
     - Estado Bloqueo: 🔒 BLOQUEADO / 🔓 ABIERTO
  3. Botones:
     - 🔒 BLOQUEAR (si abierto)
     - 🔓 DESBLOQUEAR (si bloqueado, solo Super Admin)

**Dependencias SQL:**
- Columns: `competencias_evaluacion.bloqueado_edicion, fecha_validacion_jefe, usuario_validacion_jefe_id`
- ⚠️ Requiere ejecución de SQL migration

---

### ✅ Cambio #8: Descarga Cédula - Condiciones

**Archivo:** `reporte_cedula_resultados.php`

**Modificaciones:**

1. **Agregar validación de prerequ**isitos:**
   ```php
   $stmt = $pdo->prepare("SELECT estatus_metas, estatus_colectivas, estatus_gerenciales 
       FROM calificaciones WHERE user_id = ? AND periodo = ?");
   $calificaciones = $stmt->fetch();
   
   $puede_descargar = ($estatus_metas == 3) 
       && ($estatus_colectivas >= 2) 
       && ($estatus_gerenciales == 3);
   ```

2. **Si NO cumple requisitos:**
   - Mostrar página HTML con estado de cada sección
   - ✅ Metas Individuales: Evaluadas y aprobadas (Estado: 3)
   - ❌ Metas Colectivas: Pendiente (Estado: 0)
   - ❌ Autoevaluación Gerencial: Pendiente (Estado: 0)
   - No permitir descargar

3. **Si cumple todos:**
   - Generar Excel desde plantilla `cedula_resultados.xlsx`
   - Descargar archivo

**Estados esperados:**
- `estatus_metas = 3` (evaluado y aprobado por jefe)
- `estatus_colectivas ≥ 2` (evaluado)
- `estatus_gerenciales = 3` (evaluado y aprobado)

**Dependencias SQL:**
- Columns: `calificaciones.estatus_colectivas`
- ⚠️ Requiere ejecución de SQL migration

---

## 📝 FUNCIONES AUXILIARES CREADAS

**Archivo:** `includes/finalizaciones.php`

Todas las siguientes funciones utilizan la tabla `calificaciones` u otras tablas de evaluación:

### 1. `finalizar_metas_pdf($pdo, $user_id, $periodo, $usuario_id_finalizacion)`
- Marca metas como finalizadas
- Bloquea edición

### 2. `metas_finalizadas($pdo, $user_id, $periodo)`
- Verifica si metas están bloqueadas

### 3. `usuario_termino_captura($pdo, $meta_id, $user_id)`
- Usuario termina su captura
- Sets: `usuario_termino_captura=1, fecha_termino_usuario=NOW()`

### 4. `jefe_termino_evaluacion($pdo, $meta_id, $jefe_id)`
- Jefe termina evaluación
- Sets: `jefe_evaluo=1, fecha_evaluacion_jefe=NOW(), metas_finalizadas=1`

### 5. `validar_por_jefe($pdo, $tabla, $registro_id, $jefe_id, $estado=1)`
- Jefe valida actividad/aportación
- Sets: `usuario_validacion_jefe_id, fecha_validacion_jefe, rechazado_por_jefe`

### 6. `validar_por_rh($pdo, $tabla, $registro_id, $rh_id, $estado=1)`
- RH valida después del jefe
- Sets: `usuario_validacion_rh_id, fecha_validacion_rh, rechazado_por_rh`

### 7. `revocar_validacion_rh($pdo, $tabla, $registro_id, $super_admin_id)`
- Solo Super Admin puede revocar validación RH
- Requires variable: `permitir_devalidacion_rh`

### 8. `bloquear_competencias_evaluacion($pdo, $user_id, $periodo, $jefe_id)`
- Bloquea edición de competencias tras validación jefe
- Sets: `bloqueado_edicion=1, fecha_validacion_jefe, usuario_validacion_jefe_id`

### 9. `registro_bloqueado($pdo, $tabla, $periodo)`
- Verifica si tabla está bloqueada (capacitación 2025, metas 2025)

### 10. `obtener_variable($nombre)`
- Lee variable de configuración desde BD

---

## 🗄️ COLUMNAS SQL REQUERIDAS

**Tabla: `calificaciones`**
```sql
ALTER TABLE calificaciones ADD COLUMN finalizado_metas TINYINT(1) DEFAULT 0;
ALTER TABLE calificaciones ADD COLUMN fecha_finalizacion_metas DATETIME NULL;
ALTER TABLE calificaciones ADD COLUMN usuario_finalizacion_id INT NULL;
ALTER TABLE calificaciones ADD COLUMN estatus_colectivas INT DEFAULT 0;
```

**Tabla: `metas`**
```sql
ALTER TABLE metas ADD COLUMN usuario_termino_captura TINYINT(1) DEFAULT 0;
ALTER TABLE metas ADD COLUMN fecha_termino_usuario DATETIME NULL;
ALTER TABLE metas ADD COLUMN jefe_evaluo TINYINT(1) DEFAULT 0;
ALTER TABLE metas ADD COLUMN fecha_evaluacion_jefe DATETIME NULL;
ALTER TABLE metas ADD COLUMN metas_finalizadas TINYINT(1) DEFAULT 0;
```

**Tabla: `actividades_extraordinarias` & `aportaciones_destacadas`**
```sql
ALTER TABLE [tabla] ADD COLUMN usuario_validacion_jefe_id INT NULL;
ALTER TABLE [tabla] ADD COLUMN fecha_validacion_jefe DATETIME NULL;
ALTER TABLE [tabla] ADD COLUMN usuario_validacion_rh_id INT NULL;
ALTER TABLE [tabla] ADD COLUMN fecha_validacion_rh DATETIME NULL;
ALTER TABLE [tabla] ADD COLUMN rechazado_por_jefe TINYINT(1) DEFAULT 0;
ALTER TABLE [tabla] ADD COLUMN rechazado_por_rh TINYINT(1) DEFAULT 0;
ALTER TABLE [tabla] ADD COLUMN bloqueado_edicion TINYINT(1) DEFAULT 0;
```

**Tabla: `competencias_evaluacion`**
```sql
ALTER TABLE competencias_evaluacion ADD COLUMN bloqueado_edicion TINYINT(1) DEFAULT 0;
ALTER TABLE competencias_evaluacion ADD COLUMN fecha_validacion_jefe DATETIME NULL;
ALTER TABLE competencias_evaluacion ADD COLUMN usuario_validacion_jefe_id INT NULL;
```

⚠️ **CRÍTICO:** Ejecutar `migraciones_cambios_cliente_enero_2026.sql` antes de usar estos cambios.

---

## 📋 CHECKLIST DE IMPLEMENTACIÓN

- [x] Cambio #1: Avance Evaluación (index.php)
- [x] Cambio #2: Bloqueo Capacitación (capacitacion_guardar.php)
- [x] Cambio #3: Bloqueo Metas Colectivas (admin_metas_colectivas.php)
- [x] Cambio #4: Finalizar Metas PDF (generar_reporte_individual.php)
- [x] Cambio #5: Validación Cascada (mis_actividades_extraordinarias.php, mis_aportaciones_destacadas.php)
- [x] Cambio #6: Flujo TERMINAR (mis_colaboradores.php)
- [x] Cambio #7: Bloqueo Competencias (evaluar_competencias.php, admin_evaluar_competencias.php)
- [x] Cambio #8: Descarga Cédula (reporte_cedula_resultados.php)
- [x] Cambio #9: Paridad Primer Nivel (incluido en #1)
- [x] Cambio #11: Bloqueos Perfil (editar_datos_personales.php)
- [ ] Cambio #10: Semáforos (pendiente)
- [ ] Cambio #12: Importación usuarios (pendiente)

---

## 🚀 PRÓXIMOS PASOS

1. **URGENTE:** Ejecutar migration SQL en phpMyAdmin
2. Verificar cambios #4-#8 en desarrollo
3. Implementar cambio #10 (Semáforos)
4. Ejecutar cambio #12 (Importación usuarios.csv)
5. Testing completo de workflows
6. Deploy a producción

---

**Autor:** GitHub Copilot  
**Fecha:** Enero 2025  
**Versión:** 2.0
