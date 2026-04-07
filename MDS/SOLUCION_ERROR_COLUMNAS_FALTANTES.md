# 🚨 ERROR: Columnas faltantes en BD
## Solución: Ejecutar SQL Migration
### 16 de Enero de 2026

---

## 🔴 EL PROBLEMA

```
Error 1054 - Unknown column 'estatus_colectivas' in 'calificaciones'
```

**Causa:** El código PHP que modifiqué intenta usar columnas que **aún no existen** en la base de datos.

Las columnas NO existen porque el **script SQL de migración NO ha sido ejecutado**.

---

## ✅ LA SOLUCIÓN

Debes ejecutar **ANTES** que cualquier código PHP use esas columnas:

```sql
migraciones_cambios_cliente_enero_2026.sql
```

### Paso 1: Backup (CRÍTICO - haz esto PRIMERO)
```bash
cd C:\MAMP\htdocs\conanp
mysqldump -u root -p evaluacion_conanp > backup_16_enero_2026.sql
```

### Paso 2: Ejecutar el SQL migration

**Opción A: Desde terminal (recomendado)**
```bash
mysql -u root -p evaluacion_conanp < migraciones_cambios_cliente_enero_2026.sql
```

**Opción B: Desde phpMyAdmin**
1. Abre: http://localhost/phpmyadmin
2. Click en base de datos: `evaluacion_conanp`
3. Menu: "SQL"
4. Copy/Paste contenido de: `migraciones_cambios_cliente_enero_2026.sql`
5. Click "Ejecutar"

---

## 📋 QUÉ AGREGA EL SQL MIGRATION

El script crea estas **nuevas columnas**:

### Tabla: `calificaciones`
```sql
-- NUEVAS columnas que faltaban:
ALTER TABLE `calificaciones` ADD:
  - finalizado_metas (TINYINT)
  - fecha_finalizacion_metas (DATETIME)
  - usuario_finalizacion_id (INT) -- FK a usuarios
  - finalizado_colectivas (TINYINT) -- ← AQUÍ está estatus_colectivas
  - fecha_finalizacion_colectivas (DATETIME)
  - usuario_finalizacion_colectivas_id (INT)
```

### Tabla: `metas`
```sql
ALTER TABLE `metas` ADD:
  - usuario_termino_captura (TINYINT)
  - fecha_termino_usuario (DATETIME)
  - jefe_evaluo (TINYINT)
  - fecha_evaluacion_jefe (DATETIME)
  - metas_finalizadas (TINYINT)
```

### Tabla: `capacitacion`
```sql
ALTER TABLE `capacitacion` ADD:
  - bloqueado_registro (TINYINT)
  - usuario_devalidacion_id (INT) -- FK a usuarios
  - fecha_devalidacion (DATETIME)
  - motivo_devalidacion (VARCHAR)
```

### Tabla: `metas_colectivas`
```sql
ALTER TABLE `metas_colectivas` ADD:
  - bloqueado_captura (TINYINT)
  - motivo_bloqueo (VARCHAR)
```

### Tabla: `actividades_extraordinarias`
```sql
ALTER TABLE `actividades_extraordinarias` ADD:
  - usuario_validacion_jefe_id (INT) -- FK
  - fecha_validacion_jefe (DATETIME)
  - usuario_validacion_rh_id (INT) -- FK
  - fecha_validacion_rh (DATETIME)
  - rechazado_por_jefe (TINYINT)
  - rechazado_por_rh (TINYINT)
```

### Tabla: `aportaciones_destacadas`
```sql
-- IDEM a actividades_extraordinarias
```

### Tabla: `competencias_evaluacion`
```sql
ALTER TABLE `competencias_evaluacion` ADD:
  - bloqueado_edicion (TINYINT)
  - fecha_validacion_jefe (DATETIME)
  - usuario_validacion_jefe_id (INT) -- FK
```

### Nueva tabla: `tipo_usuario_catalogo`
```sql
CREATE TABLE tipo_usuario_catalogo (
  id INT PRIMARY KEY,
  codigo VARCHAR(50),
  nombre VARCHAR(100),
  descripcion TEXT
)
```

### Nueva tabla: `variables`
```sql
INSERT INTO variables VALUES:
  - bloquear_capacitacion_2025
  - bloquear_metas_colectivas_2025
  - permitir_devalidacion_rh
  - mostrar_avance_evaluacion_tipos
  - mensaje_datos_generales
```

---

## 🚀 PASOS A SEGUIR (EN ORDEN)

### 1️⃣ AHORA - Hacer backup
```bash
mysqldump -u root -p evaluacion_conanp > backup_16_enero_2026.sql
```
✅ Guarda este archivo en lugar seguro

### 2️⃣ INMEDIATO - Ejecutar SQL migration
```bash
mysql -u root -p evaluacion_conanp < migraciones_cambios_cliente_enero_2026.sql
```

### 3️⃣ VERIFICAR - Confirmar que las columnas existen
```sql
-- Abre phpMyAdmin o terminal MySQL
DESCRIBE calificaciones;
-- Deberías ver: finalizado_metas, fecha_finalizacion_metas, usuario_finalizacion_id, etc.

DESCRIBE metas;
-- Deberías ver: usuario_termino_captura, fecha_termino_usuario, metas_finalizadas, etc.
```

### 4️⃣ LISTO - El código PHP funcionará correctamente

---

## ❌ SI ALGO FALLA

### Error: "Syntax error near..."
- Abre el archivo SQL y verifica que no tenga caracteres raros
- Intenta desde phpMyAdmin en lugar de terminal

### Error: "Column already exists"
- La migración ya fue ejecutada
- Ignora el error, es harmless

### Error: "Access denied"
```sql
-- Habilitar LOAD DATA si es necesario
SET GLOBAL local_infile=1;
```

### Necesito revertir
```bash
# Restaurar desde backup
mysql -u root -p evaluacion_conanp < backup_16_enero_2026.sql
```

---

## ✅ CHECKLIST

- [ ] He hecho backup: `backup_16_enero_2026.sql`
- [ ] He ejecutado: `migraciones_cambios_cliente_enero_2026.sql`
- [ ] He verificado con DESCRIBE que existen las nuevas columnas
- [ ] El error `Unknown column 'estatus_colectivas'` ya no aparece
- [ ] El código PHP funciona correctamente

---

## 📝 RESUMEN

| Situación | Acción |
|-----------|--------|
| Error 1054 | → Ejecuta SQL migration |
| Código PHP espera columnas | → Primero migration, luego PHP |
| Tengo miedo de romper algo | → Haz backup ANTES |
| No sé cómo ejecutar SQL | → Usa phpMyAdmin, es GUI |
| Quiero revertir cambios | → Restaura desde backup |

---

**Una vez hayas ejecutado el SQL migration, el error desaparecerá.**

¿Necesitas ayuda para ejecutar el SQL? Dime qué método prefieres:
- A) Terminal MySQL
- B) phpMyAdmin (web)
- C) Otro
