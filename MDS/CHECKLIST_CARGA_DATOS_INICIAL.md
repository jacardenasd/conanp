# CHECKLIST DE CARGA DE DATOS INICIAL
## Sistema de Evaluación del Desempeño CONANP (SED)
### Post-Implementación de Cambios Enero 2026

**Objetivo:** Verificar que TODOS los datos maestros estén completos y configurados para que el sistema funcione correctamente.

---

## 🔍 ANÁLISIS DE DATOS EXISTENTES vs REQUERIDOS

### 1. ✅ DATOS QUE YA EXISTEN (No requieren acción)

| Tabla | Registros | Estado | Nota |
|-------|-----------|--------|------|
| `usuarios` | ~1000 | Existente | Ya importados del sistema anterior |
| `unidades` | 173 | Existente | Todas las áreas de CONANP |
| `adscripciones` | 173 | Existente | Especificaciones por unidad |
| `competencias` | 5 | Existente | Fijas: Visión, Liderazgo, Orientación, Negociación, Trabajo |
| `competencias_descripcion` | ~80 | Existente | Comportamientos observables por nivel |
| `competencias_valores` | 30 | Existente | Pesos base por competencia/nivel |
| `competencias_pesos` | 6 | Existente | Ponderaciones por puesto |
| `capacitacion_categorias` | 15+ | Existente | Tipos de cursos |
| `capacitacion_modalidades` | 5+ | Existente | Online/Presencial/Híbrido |
| `capacitacion_finalidades` | 8+ | Existente | Propósitos de capacitación |
| `unidades_medida` | 10+ | Existente | Unidades para metas (cantidad, horas, etc) |
| `periodos` | 2025, 2026 | Existente | Años de evaluación |

---

## ❌ DATOS QUE FALTAN O REQUIEREN VALIDACIÓN

### CRÍTICO: Campos nuevos en tabla `usuarios`

#### Campo: `tipo_usuario`

**Estado:** ⚠️ NUEVO - Requiere validación y llenado

**Descripción:** Identifica tipo de contratación del usuario
- `1` = SPC (Acceso completo a evaluación)
- `2` = Primer Nivel de Ingreso (Acceso completo, igual que SPC)
- `3` = Eventual (Solo capacitación)
- `4` = Operativo (Solo capacitación)
- `5` = Otro (Solo capacitación)

**Verificación:**
```sql
-- Ver distribución actual
SELECT tipo_usuario, COUNT(*) FROM usuarios GROUP BY tipo_usuario;

-- Ver cuántos NULL/sin asignar
SELECT COUNT(*) FROM usuarios WHERE tipo_usuario IS NULL OR tipo_usuario = 0;

-- Ver ejemplos de usuarios sin tipo_usuario
SELECT user_id, username, puesto_nombre, tipo_usuario 
FROM usuarios WHERE tipo_usuario IS NULL 
LIMIT 10;
```

**Acciones requeridas:**

```sql
-- 1. Asignar SPC (tipo_usuario = 1)
--    Normalmente: Personal con acceso a evaluaciones (supervisores, RH)
UPDATE usuarios SET tipo_usuario = 1 
WHERE puesto_nombre LIKE '%Jefe%' 
   OR puesto_nombre LIKE '%Director%'
   OR puesto_nombre LIKE '%Coordinador%'
   OR user_id IN (SELECT DISTINCT jefe_id FROM usuarios WHERE jefe_id IS NOT NULL);

-- 2. Asignar Primer Nivel (tipo_usuario = 2)
--    Normalmente: Personal en primeros años de ingreso
UPDATE usuarios SET tipo_usuario = 2 
WHERE fecha_ingreso > DATE_SUB(NOW(), INTERVAL 3 YEAR)
  AND tipo_usuario IS NULL;

-- 3. Asignar resto a Eventual/Operativo/Otro (tipo_usuario = 3-5)
UPDATE usuarios SET tipo_usuario = 3 
WHERE tipo_usuario IS NULL;

-- 4. VALIDAR: Todos los usuarios deben tener tipo_usuario asignado
SELECT COUNT(*) FROM usuarios WHERE tipo_usuario IS NULL;
-- Resultado debe ser: 0
```

**Impacto:**
- ❌ Sin esto: Usuarios verán/no verán "Avance de Evaluación" INCORRECTAMENTE
- ❌ Sin esto: Filtrados equivocados en index.php
- ✅ Con esto: Sistema funciona correctamente

---

### CRÍTICO: Variables de configuración

#### Tabla: `variables`

**Estado:** ⚠️ INSERTADAS pero requieren validación de VALORES

**Verificación:**
```sql
SELECT * FROM variables 
WHERE nombre IN (
  'bloquear_capacitacion_2025',
  'bloquear_metas_colectivas_2025',
  'permitir_devalidacion_rh',
  'mostrar_avance_evaluacion_tipos',
  'mensaje_datos_generales',
  'periodo_actual',
  'estatus_periodo_actual'
);
```

**Cada variable requiere:**

| Variable | Valor Actual | Valor Requerido | Descripción |
|----------|--------------|-----------------|-------------|
| `periodo_actual` | ? | 2026 (int) | Año actual de evaluación |
| `estatus_periodo_actual` | ? | 'Captura' o 'Evaluación' | Fase del período |
| `bloquear_capacitacion_2025` | 1 | 1 (bloqueado) o 0 (desbloqueado) | ¿Permite agregar capacitación 2025? |
| `bloquear_metas_colectivas_2025` | 1 | 1 (bloqueado) o 0 (desbloqueado) | ¿Permite agregar metas colectivas 2025? |
| `permitir_devalidacion_rh` | 1 | 1 (sí) o 0 (no) | ¿Super Admin puede desvalidar RH? |
| `mostrar_avance_evaluacion_tipos` | '1,2' | '1,2' (o personalizado) | Tipos de usuario que ven Avance |
| `mensaje_datos_generales` | ? | Texto personalizado | Mensaje en perfil "Mis Datos" |
| `nombre_sistema` | ? | "SED CONANP 2026" | Nombre que aparece en header |
| `version` | ? | "2.1.0" | Versión del sistema |

**Acciones requeridas:**

```sql
-- 1. Verificar valores existentes
SELECT nombre, valor FROM variables 
WHERE nombre IN ('periodo_actual', 'estatus_periodo_actual');

-- 2. Si no existen, insertarlos:
INSERT IGNORE INTO variables (nombre, valor, descripcion) VALUES
('periodo_actual', '2026', 'Año actual de evaluación'),
('estatus_periodo_actual', 'Captura', 'Captura | Evaluación | Cerrado'),
('bloquear_capacitacion_2025', '1', '1=bloqueado, 0=permite'),
('bloquear_metas_colectivas_2025', '1', '1=bloqueado, 0=permite'),
('permitir_devalidacion_rh', '1', '1=permite, 0=no permite'),
('mostrar_avance_evaluacion_tipos', '1,2', 'Tipos usuario separados por coma'),
('mensaje_datos_generales', 'En caso de identificar alguna corrección en los datos generales, envía un correo a capacitacion@conanp.gob.mx', 'Mensaje dinámico en perfil');

-- 3. VALIDAR: Todas deben estar presentes
SELECT COUNT(*) FROM variables;
-- Verificar que sea >= número anterior + 7 nuevas
```

**Impacto:**
- ❌ Sin valores: Sistema toma defaults incorrectos
- ❌ Período incorrecto: Metas/Evaluaciones cruzadas
- ❌ Estatus incorrecto: Bloqueos no funcionan
- ✅ Con valores correctos: Todo funciona

---

### IMPORTANTE: Datos en tabla `periodos`

#### Tabla: `periodos`

**Estado:** ✅ DEBE EXISTIR pero revisar estatus

**Verificación:**
```sql
SELECT * FROM periodos ORDER BY ano DESC;

-- Ejemplo de resultado esperado:
-- | ano | estatus | fecha_inicio | fecha_fin |
-- | 2026| Captura | 2026-01-01 | 2026-02-28 |
-- | 2025| Cerrado | 2025-01-01 | 2025-12-31 |
```

**Acciones requeridas:**

```sql
-- 1. Verificar que exista 2026
SELECT COUNT(*) FROM periodos WHERE ano = 2026;
-- Si resultado es 0, insertar:
INSERT INTO periodos (ano, estatus, fecha_inicio, fecha_fin) VALUES
(2026, 'Captura', '2026-01-01', '2026-02-28');

-- 2. Verificar que 2025 esté Cerrado (no editable)
UPDATE periodos SET estatus = 'Cerrado' WHERE ano = 2025;

-- 3. Verificar que 2026 sea el período actual
UPDATE periodos SET estatus = 'Captura' WHERE ano = 2026;

-- 4. IMPORTANTE: Cambiar estatus en el momento de cambio de fase
--    Captura → Evaluación → Cerrado
--    NUNCA volver atrás sin autorización Super Admin
```

**Impacto:**
- ❌ Sin período correcto: Usuarios no pueden capturar metas
- ❌ Sin estatus: Bloqueos no funcionan
- ✅ Con período correcto: Sistema valida todo

---

## 📋 VALIDACIONES POR MÓDULO

### Módulo: METAS INDIVIDUALES

**Datos requeridos:**
```sql
-- Verificar que cada usuario tenga:
SELECT u.user_id, u.username, 
       COUNT(m.id) as metas_2026,
       u.jefe_id
FROM usuarios u
LEFT JOIN metas m ON u.user_id = m.user_id AND m.periodo = 2026
WHERE u.estatus = 1  -- activo
GROUP BY u.user_id
HAVING u.jefe_id IS NULL  -- ADVERTENCIA: sin jefe asignado
LIMIT 10;
```

**Verificar:**
- [ ] Todos los usuarios activos tienen `jefe_id` asignado
- [ ] La jerarquía es válida (no hay ciclos: A → B → C → A)
- [ ] No hay jefe_id que apunten a usuario inactivo

**Acciones:**
```sql
-- Identificar usuarios sin jefe asignado
SELECT user_id, username, puesto_nombre 
FROM usuarios 
WHERE jefe_id IS NULL AND estatus = 1 AND role = 1;

-- Asignar jefe (ejemplo: todos a usuario 1)
UPDATE usuarios SET jefe_id = 1 
WHERE jefe_id IS NULL AND estatus = 1 AND role = 1;

-- Validar jerarquía (detectar ciclos)
-- No hay función SQL nativa, requiere código PHP
```

**Impacto:**
- ❌ Sin jefe: Metas no se pueden evaluar
- ✅ Con jefe: Flujo jefe → usuario completo

---

### Módulo: METAS COLECTIVAS

**Datos requeridos:**
```sql
-- Verificar que existan metas colectivas para 2026
SELECT COUNT(*) FROM metas_colectivas WHERE periodo = 2026;

-- Verificar estructura
SELECT unidad_id, periodo, COUNT(*) as cantidad
FROM metas_colectivas
GROUP BY unidad_id, periodo
ORDER BY periodo DESC;
```

**Verificar:**
- [ ] Existen metas colectivas para 2026
- [ ] Cada metas_colectivas tiene unidad_id válida
- [ ] Todas las metas_colectivas 2025 tienen `bloqueado_captura = 1`

**Acciones:**
```sql
-- Verificar bloqueos 2025
SELECT COUNT(*) FROM metas_colectivas 
WHERE periodo = 2025 AND bloqueado_captura = 0;
-- Si > 0, bloquear:
UPDATE metas_colectivas SET bloqueado_captura = 1 
WHERE periodo = 2025 AND bloqueado_captura = 0;

-- Copiar estructura de 2025 a 2026 (si no existen)
INSERT INTO metas_colectivas (unidad_id, periodo, indicador, unidad, ponderacion)
SELECT unidad_id, 2026, indicador, unidad, ponderacion
FROM metas_colectivas
WHERE periodo = 2025 AND bloqueado_captura IS NULL;

-- Revisar ponderaciones (deben sumar 100% por unidad)
SELECT unidad_id, SUM(ponderacion) as total_ponderacion
FROM metas_colectivas
WHERE periodo = 2026
GROUP BY unidad_id
HAVING total_ponderacion != 100;
-- Si hay resultados, corregir ponderaciones
```

**Impacto:**
- ❌ Sin metas colectivas: Usuarios no ven su contexto
- ❌ Sin bloqueos 2025: Duplicados posibles
- ✅ Con estructura correcta: Evaluaciones válidas

---

### Módulo: COMPETENCIAS

**Datos requeridos:**
```sql
-- Verificar competencias base
SELECT * FROM competencias;
-- Debe haber 5 registros: Visión, Liderazgo, Orientación, Negociación, Trabajo

-- Verificar descripciones por nivel
SELECT competencia_id, nivel, COUNT(*) as cantidad_comportamientos
FROM competencias_descripcion
GROUP BY competencia_id, nivel
ORDER BY competencia_id, nivel;

-- Ejemplo esperado:
-- | competencia_id | nivel | cantidad_comportamientos |
-- | 1 | 1 | 3 |
-- | 1 | 2 | 4 |
-- ... (diferente cantidad por nivel de puesto)
```

**Verificar:**
- [ ] Existen 5 competencias
- [ ] Existen descripciones para cada nivel de puesto (1-6)
- [ ] Cada descripción tiene valores definidos en `competencias_valores`

**Acciones:**
```sql
-- Contar competencias
SELECT COUNT(*) FROM competencias;
-- Debe ser = 5

-- Contar descripciones
SELECT COUNT(*) FROM competencias_descripcion;
-- Debe ser 60+ (mínimo 5 competencias × 6 niveles × 2 descripciones)

-- Verificar valores por nivel
SELECT COUNT(*) FROM competencias_valores;
-- Debe ser 30 (5 competencias × 6 niveles)

-- IMPORTANTE: Si falta algo, importar desde respaldo anterior
```

**Impacto:**
- ❌ Sin competencias: No hay autoevaluación gerencial
- ❌ Sin descripciones: Usuarios no entienden qué evaluar
- ✅ Con estructura: Evaluaciones gerenciales funcionales

---

### Módulo: CAPACITACIÓN

**Datos requeridos:**
```sql
-- Verificar catálogos
SELECT COUNT(*) FROM capacitacion_categorias;  -- 15+ esperados
SELECT COUNT(*) FROM capacitacion_modalidades;  -- 5+ esperados
SELECT COUNT(*) FROM capacitacion_finalidades;  -- 8+ esperados

-- Verificar registros existentes
SELECT COUNT(*) FROM capacitacion WHERE periodo = 2025;
SELECT COUNT(*) FROM capacitacion WHERE periodo = 2026;
```

**Verificar:**
- [ ] Existen categorías de capacitación
- [ ] Existen modalidades (online, presencial, etc)
- [ ] Existen finalidades (desarrollo, obligatoria, etc)
- [ ] Todos los registros 2025 tienen `bloqueado_registro = 0` (para referencia)

**Acciones:**
```sql
-- Si faltan catálogos (importar plantilla)
-- Scripts estar en: capacitacion_importar_catalogos.sql

-- Verificar bloqueos 2025
SELECT COUNT(*) FROM capacitacion 
WHERE periodo = 2025 AND bloqueado_registro IS NULL;
-- Actualizar si es necesario:
UPDATE capacitacion SET bloqueado_registro = 0 WHERE periodo = 2025;

-- NO bloquear 2025 en tabla directamente
-- El bloqueo se hace en PHP (variable bloquear_capacitacion_2025)
```

**Impacto:**
- ❌ Sin catálogos: Usuarios no pueden seleccionar opción
- ✅ Con catálogos: Módulo completamente funcional

---

### Módulo: USUARIOS y ROLES

**Datos requeridos:**
```sql
-- Verificar estructura de usuarios
SELECT COUNT(*) FROM usuarios;
-- Debe ser ~1000

-- Verificar roles
SELECT role, COUNT(*) FROM usuarios GROUP BY role;
-- Esperado:
-- | role | cantidad |
-- | 1 | ~900 |  (usuarios normales)
-- | 2 | ~50 |   (administradores)
-- | 3 | ~5 |    (super admins)

-- Verificar tipo_usuario (NUEVO)
SELECT tipo_usuario, COUNT(*) FROM usuarios GROUP BY tipo_usuario;
-- Después de llenar, algo como:
-- | tipo_usuario | cantidad |
-- | 1 | ~100 | (SPC)
-- | 2 | ~50 | (Primer Nivel)
-- | 3 | ~500 | (Eventual)
-- | 4 | ~300 | (Operativo)
-- | 5 | ~50 | (Otro)
```

**Verificar:**
- [ ] Todos los usuarios tienen tipo_usuario asignado (NO NULL)
- [ ] La jerarquía de jefes es válida
- [ ] Usuarios inactivos tienen `estatus = 0`
- [ ] Super Admin tiene al menos 1 usuario (role = 3)

**Acciones:**
```sql
-- Contar usuarios sin tipo_usuario (CRÍTICO)
SELECT COUNT(*) FROM usuarios WHERE tipo_usuario IS NULL OR tipo_usuario = 0;
-- Si > 0, DEBE asignarse (ver sección anterior)

-- Asegurar que hay Super Admin
SELECT COUNT(*) FROM usuarios WHERE role = 3 AND estatus = 1;
-- Resultado debe ser >= 1

-- Si no hay, crear:
INSERT INTO usuarios (username, password, role, tipo_usuario, estatus)
VALUES ('admin_conanp', PASSWORD('temporal123'), 3, 1, 1);
```

**Impacto:**
- ❌ Sin tipo_usuario: Filtros no funcionan
- ❌ Sin Super Admin: No hay quién desbloquee cosas
- ✅ Con estructura: Sistema operativo

---

## 📊 CHECKLIST DE IMPLEMENTACIÓN

### Pre-Implementación (Antes de SQL)

- [ ] Backup completo de BD
- [ ] Verificar estructura de usuarios existentes
- [ ] Documentar distribución actual de tipo_usuario
- [ ] Revisar período actual en sistema

### Post-SQL (Después de ejecutar migración)

- [ ] Ejecutar script SQL migration completo
- [ ] Verificar que todas las columnas nuevas existan
- [ ] Verificar que tabla `tipo_usuario_catalogo` fue creada
- [ ] Verificar que nuevas variables fueron insertadas

### Carga de Datos (CRÍTICO)

- [ ] Asignar `tipo_usuario` a TODOS los usuarios (ningún NULL)
- [ ] Validar que `periodo_actual` = 2026
- [ ] Validar que `estatus_periodo_actual` es correcto
- [ ] Validar variables de bloqueo (capacitación, colectivas)
- [ ] Verificar que todos los usuarios tengan `jefe_id` válido

### Validación de Datos

- [ ] Contar usuarios por tipo_usuario (distribution check)
- [ ] Verificar jerarquía de jefes (sin ciclos)
- [ ] Verificar período 2025 está "Cerrado"
- [ ] Verificar período 2026 está "Captura"
- [ ] Verificar metas colectivas 2025 tienen `bloqueado_captura = 1`

### Pruebas Funcionales

- [ ] Usuario SPC puede ver Avance de Evaluación
- [ ] Usuario Primer Nivel puede ver Avance de Evaluación
- [ ] Usuario Eventual NO puede ver Avance de Evaluación
- [ ] Intentar agregar Capacitación 2025 → Bloqueado
- [ ] Agregar Capacitación 2026 → Permitido
- [ ] Intentar agregar Meta Colectiva 2025 → Bloqueado
- [ ] Editar Perfil (usuario normal) → Campos bloqueados correctamente
- [ ] Finalizar metas → Bloquea edición posterior
- [ ] Super Admin puede desbloquear registros

### Post-Validación

- [ ] Log de cambios muestra auditoría correctamente
- [ ] Variables se pueden editar desde admin
- [ ] Todas las pruebas funcionales pasaron
- [ ] Performance aceptable (sin queries lentas)

---

## 🗄️ SCRIPTS SQL PARA CARGAR/VALIDAR DATOS

### Script 1: Diagnóstico de Usuarios

```sql
-- Guardar como: diagnostico_usuarios.sql
SELECT 
  'DIAGNÓSTICO DE USUARIOS' as seccion,
  COUNT(*) as total_usuarios,
  SUM(CASE WHEN estatus = 1 THEN 1 ELSE 0 END) as activos,
  SUM(CASE WHEN estatus = 0 THEN 1 ELSE 0 END) as inactivos,
  SUM(CASE WHEN tipo_usuario IS NULL THEN 1 ELSE 0 END) as sin_tipo_usuario,
  SUM(CASE WHEN jefe_id IS NULL THEN 1 ELSE 0 END) as sin_jefe
FROM usuarios;

SELECT 'DISTRIBUCIÓN POR TIPO_USUARIO', tipo_usuario, COUNT(*) 
FROM usuarios GROUP BY tipo_usuario;

SELECT 'DISTRIBUCIÓN POR ROLE', role, COUNT(*) 
FROM usuarios GROUP BY role;

SELECT 'DISTRIBUCIÓN POR PUESTO_NIVEL', puesto_nivel, COUNT(*) 
FROM usuarios GROUP BY puesto_nivel;
```

### Script 2: Asignar Tipo_Usuario (Template)

```sql
-- PLANTILLA (personalizar según lógica de CONANP)

-- 1. SPC: Supervisores, Directores, Coordinadores
UPDATE usuarios SET tipo_usuario = 1 
WHERE (
  puesto_nombre LIKE '%Jefe%' 
  OR puesto_nombre LIKE '%Director%'
  OR puesto_nombre LIKE '%Coordinador%'
  OR puesto_nombre LIKE '%Especialista%'
) AND tipo_usuario IS NULL;

-- 2. Primer Nivel: Ingreso reciente (último 3 años)
UPDATE usuarios SET tipo_usuario = 2 
WHERE DATE_ADD(fecha_ingreso, INTERVAL 3 YEAR) > NOW()
  AND tipo_usuario IS NULL
  AND role = 1;

-- 3. Eventual: Contratados por proyecto
UPDATE usuarios SET tipo_usuario = 3 
WHERE puesto_nombre LIKE '%Eventual%' 
  AND tipo_usuario IS NULL;

-- 4. Operativo: Personal de apoyo
UPDATE usuarios SET tipo_usuario = 4 
WHERE puesto_nombre LIKE '%Operativo%' 
  OR puesto_nombre LIKE '%Asistente%'
  AND tipo_usuario IS NULL;

-- 5. Resto: Otro
UPDATE usuarios SET tipo_usuario = 5 
WHERE tipo_usuario IS NULL;

-- VALIDACIÓN: Verificar que no quedan NULL
SELECT COUNT(*) FROM usuarios WHERE tipo_usuario IS NULL;
```

### Script 3: Validar Jerarquía

```sql
-- Script para detectar problemas de jerarquía
SELECT u.user_id, u.username, u.jefe_id, j.username as jefe_nombre
FROM usuarios u
LEFT JOIN usuarios j ON u.jefe_id = j.user_id
WHERE u.jefe_id IS NOT NULL
  AND j.user_id IS NULL  -- PROBLEMA: jefe no existe
  AND u.estatus = 1;

-- Si hay resultados, necesita corrección:
-- UPDATE usuarios SET jefe_id = NULL WHERE jefe_id = [ID_NO_EXISTE]
```

### Script 4: Configurar Variables

```sql
-- Insertar/Actualizar variables de configuración
REPLACE INTO variables (nombre, valor, descripcion) VALUES
('periodo_actual', '2026', 'Año actual de evaluación'),
('estatus_periodo_actual', 'Captura', 'Fase: Captura | Evaluación | Cerrado'),
('bloquear_capacitacion_2025', '1', '1=bloqueado, 0=permite captura 2025'),
('bloquear_metas_colectivas_2025', '1', '1=bloqueado, 0=permite captura 2025'),
('permitir_devalidacion_rh', '1', '1=Super Admin puede desvalidar RH'),
('mostrar_avance_evaluacion_tipos', '1,2', 'Tipos usuario (1=SPC, 2=Primer Nivel)'),
('mensaje_datos_generales', 'En caso de identificar alguna corrección en los datos generales, envía correo a capacitacion@conanp.gob.mx', 'Mensaje dinámico en perfil'),
('nombre_sistema', 'SED CONANP 2026', 'Nombre del sistema en header'),
('version', '2.1.0', 'Versión actual del sistema');
```

---

## ⚠️ PROBLEMAS COMUNES Y SOLUCIONES

### Problema 1: Usuario ve "Avance de Evaluación" pero no debería
**Causa:** tipo_usuario no asignado correctamente o NULL
**Solución:**
```sql
SELECT tipo_usuario FROM usuarios WHERE user_id = [ID];
UPDATE usuarios SET tipo_usuario = 1 WHERE user_id = [ID];
```

### Problema 2: No puedo agregar metas colectivas 2026
**Causa:** Variable `bloquear_metas_colectivas_2025` = 1 bloqueado TODO
**Solución:**
```sql
UPDATE variables SET valor = '0' 
WHERE nombre = 'bloquear_metas_colectivas_2025';
```

### Problema 3: Usuarios ven período equivocado
**Causa:** Variable `periodo_actual` no es 2026
**Solución:**
```sql
UPDATE variables SET valor = '2026' WHERE nombre = 'periodo_actual';
```

### Problema 4: Jefe no puede ver a sus colaboradores
**Causa:** Usuario no tiene `jefe_id` asignado
**Solución:**
```sql
-- Verificar
SELECT jefe_id FROM usuarios WHERE user_id = [COLABORADOR];
-- Asignar si es NULL
UPDATE usuarios SET jefe_id = [ID_JEFE] WHERE user_id = [COLABORADOR];
```

### Problema 5: Campos en Perfil no están bloqueados
**Causa:** Sesión no captura `$_SESSION['role']` correctamente
**Solución:**
- Verificar `login.php` está grabando `$_SESSION['role']`
- Logout y re-login para refrescar sesión
- Ver logs de error: `error_log`

---

## 📋 RESUMEN: QUÉ REQUIERE CARGA/VALIDACIÓN

### CRÍTICO (DEBE HACERSE):
1. ✅ Asignar `tipo_usuario` a TODOS los usuarios
2. ✅ Validar/insertar variables de configuración
3. ✅ Verificar período 2026 existe y es actual
4. ✅ Validar jerarquía de jefes (sin NULL ni ciclos)

### IMPORTANTE (VERIFICAR):
5. ✅ Metas colectivas 2025 tienen `bloqueado_captura = 1`
6. ✅ Competencias y descripciones existen (5 competencias)
7. ✅ Catálogos de capacitación completos
8. ✅ Existe Super Admin (role = 3)

### RECOMENDADO (REVISAR):
9. ✅ Distribución de usuarios por tipo_usuario es realista
10. ✅ Período 2025 está marcado como "Cerrado"
11. ✅ No hay usuarios con estatus = 1 e inactivos de facto
12. ✅ Mensajes de usuario son correctos (españolizado)

---

## 📞 APOYO Y VALIDACIÓN

Si tienes dudas sobre qué datos cargar:

**Contacto:** tecnologia@conanp.gob.mx  
**Adjuntar:**
- Resultado de script diagnóstico
- Distribución esperada de tipo_usuario
- Lista de unidades/jefes criticos

Podemos generar scripts personalizados para tu estructura específica.

---

**Última actualización:** 15 de Enero de 2026  
**Versión:** 1.0
