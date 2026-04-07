# GUÍA DE IMPORTACIÓN DE USUARIOS DESDE CSV
## Sistema de Evaluación del Desempeño CONANP
### 15 de Enero de 2026

---

## 📋 RESUMEN DE OPCIONES

Tienes **3 formas** de importar los ~700 usuarios desde el archivo `usuarios.csv`:

| Opción | Método | Dificultad | Velocidad | Ideal Para |
|--------|--------|-----------|-----------|-----------|
| 1️⃣ | **LOAD DATA INFILE** (SQL directo) | 🔴 Alta | ⚡ Rápido | DBA, Terminal MySQL |
| 2️⃣ | **phpMyAdmin GUI** | 🟢 Baja | 🟡 Medio | Admin, Sin Terminal |
| 3️⃣ | **Asistente PHP** | 🟢 Baja | 🟡 Medio | Cualquiera, Web GUI |

---

## ✅ OPCIÓN 1: LOAD DATA INFILE (Recomendada para DBA)

### Archivo: `importar_usuarios_csv.sql`

**Ventajas:**
- ⚡ Más rápido (~5 segundos para 700 usuarios)
- 📊 Mejor para volúmenes grandes
- 🔧 Control total sobre el proceso

**Pasos:**

### Paso 1.1: Verificar permisos MySQL
```bash
mysql -u root -p
```

```sql
-- Habilitar LOAD DATA INFILE (si está deshabilitado)
SET GLOBAL local_infile=1;

-- Verificar
SHOW VARIABLES LIKE 'local_infile';
-- Debe mostrar: local_infile | ON
```

### Paso 1.2: Ejecutar importación

**En Windows (MAMP):**
```bash
cd C:\MAMP\htdocs\conanp
mysql -u root -p evaluacion_conanp < importar_usuarios_csv.sql
```

**En Linux:**
```bash
cd /var/www/html/conanp
mysql -u root -p evaluacion_conanp < importar_usuarios_csv.sql
```

### Paso 1.3: Verificar resultado
Deberías ver:
```
+--------------------------------------+-----------+
| verificacion                         | cantidad  |
+--------------------------------------+-----------+
| TOTAL USUARIOS IMPORTADOS            | 692       |
+--------------------------------------+-----------+
```

### Solución de problemas LOAD DATA:

❌ **Error: "Access denied for LOAD DATA"**
```sql
SET GLOBAL local_infile=1;
-- Luego reconectar a MySQL
```

❌ **Error: "File not found"**
- Verifica que `usuarios.csv` esté en `C:\MAMP\htdocs\conanp\`
- Usa ruta con doble barra: `C:\\MAMP\\htdocs\\conanp\\usuarios.csv`

❌ **Error: "Duplicate entry"**
```sql
-- Si ya existe datos, opción A:
TRUNCATE TABLE usuarios;  -- Borrar primero
-- O opción B:
-- El script usa "ON DUPLICATE KEY UPDATE", no hay error
```

---

## ✅ OPCIÓN 2: phpMyAdmin (Sin terminal)

**Ventajas:**
- 🖱️ GUI amigable
- 📱 Funciona en cualquier navegador
- ❌ Sin necesidad de terminal

**Pasos:**

### Paso 2.1: Acceder a phpMyAdmin
```
http://localhost/phpmyadmin
```

### Paso 2.2: Seleccionar tabla
- Click en: `evaluacion_conanp` (base de datos)
- Click en: `usuarios` (tabla)

### Paso 2.3: Click en "Importar"
- Menu superior: **Importar**
- Opción: "Seleccionar archivo"
- Buscar: `usuarios.csv`

### Paso 2.4: Configurar parámetros
- **Formato:** CSV
- **Delimitador de campos:** `,` (coma)
- **Delimitador de líneas:** LF (\n)
- **Encerrado en:** `"` (comillas)
- **Primera línea es encabezado:** ✅ Marcar

### Paso 2.5: Click "Continuar"
- Esperar a que procese
- Debería importar ~692 registros

**Captura esperada:**
```
Importación completada, 692 filas insertadas.
```

---

## ✅ OPCIÓN 3: Asistente PHP Web (Recomendado para no-técnicos)

**Ventajas:**
- 🎨 Interfaz visual amigable
- 🔄 Diagnóstico automático
- ✅ Validación integrada
- 📊 Reportes paso a paso

### Paso 3.1: Acceder al asistente
```
http://localhost/conanp/importar_usuarios_asistente.php
```

✅ **Solo funciona si eres Super Admin (role = 3)**

### Paso 3.2: Ejecutar diagnóstico
- Click: **🔍 Diagnosticar CSV**
- Verifica:
  - Total de registros: 692
  - Usuarios activos: ~600
  - Usuarios inactivos: ~92 (vacantes)
  - Distribución por tipo: SPC, Primer Nivel, etc.

### Paso 3.3: Importar
- Click: **⬆️ Importar Usuarios**
- Confirmación: "¿Deseas importar los usuarios?"
- ✅ Clic
- Esperar ~30 segundos
- Resultado: ✅ Se importaron 692 registros

### Paso 3.4: Validar
- Click: **✅ Validar Base de Datos**
- Verifica:
  - Usuarios sin tipo_usuario: 0 ✅
  - Jefe_id inválidos: Mostrar cantidad
  - Distribución por tipo

---

## 📊 ESTRUCTURA DEL CSV

El archivo `usuarios.csv` contiene estas columnas:

```
user_id, role, nombre, apellido_paterno, apellido_materno, RFC, homoclave, 
CURP, IDRUSP, puesto_codigo, puesto_nivel, puesto_nombre, sexo, correo, 
nivel_estudios, fecha_alta, temporal, jefe_id, unidad_id, adscripcion_id, 
permite_metas_colectivas, tipo_usuario, estatus
```

**Ejemplo de fila:**
```csv
1,1,PEDRO CARLOS,ALVAREZ ICAZA,LONGORIA,AALP581103,7KA,AALP581103HDFLND07,003794696,16-F00-1-M2C029P-0003552-E-X-T,K22,COMISIONADO NACIONAL DE AREAS NATURALES PROTEGIDAS,H,p.alvarezicaza@conanp.gob.mx,Doctorado,01/10/2024,0,0,OFICINA DEL COMISIONADO NACIONAL,OFICINAS CENTRALES,NO,SPC,ACTIVO
```

**Campos importantes:**
- `user_id`: Identificador único
- `tipo_usuario`: SPC, Primer Nivel, Eventual, etc. ← **Crítico**
- `estatus`: ACTIVO o INACTIVO
- `jefe_id`: Usuario quien supervisa (puede ser 0)
- `nombre`: NULL para vacantes

---

## ⚙️ CONVERSIONES AUTOMÁTICAS

El sistema convierte automáticamente:

| Campo CSV | Conversión | Valor Final |
|-----------|-----------|-------------|
| tipo_usuario = "SPC" | → | 1 |
| tipo_usuario = "Primer Nivel" | → | 2 |
| tipo_usuario = Otro | → | 3 |
| estatus = "ACTIVO" | → | 1 |
| estatus = "INACTIVO" | → | 0 |
| fecha_alta = "01/10/2024" | → | "2024-10-01" |
| nombre = "NULL" | → | NULL (vacante) |
| jefe_id = 0 | → | NULL (sin jefe) |

---

## 🔐 SEGURIDAD POST-IMPORTACIÓN

### Usuarios nuevos reciben:
- ✅ Password temporal: `temporal123`
- ✅ Flag: `requiere_cambio_password = 1`
- ✅ En primer login: **DEBE cambiar password**

### Validación de integridad:
```sql
-- Ejecutar después de importar:

-- 1. Todos tienen tipo_usuario
SELECT COUNT(*) FROM usuarios WHERE tipo_usuario IS NULL;
-- Resultado esperado: 0

-- 2. Jefes válidos
SELECT COUNT(*) FROM usuarios u 
WHERE u.jefe_id > 0 AND NOT EXISTS (SELECT 1 FROM usuarios j WHERE j.user_id = u.jefe_id);
-- Resultado esperado: 0

-- 3. Distribución de usuarios activos
SELECT tipo_usuario, COUNT(*) FROM usuarios WHERE estatus = 1 GROUP BY tipo_usuario;
-- Resultado: Mostrar cantidad por tipo
```

---

## 🚀 PASOS RECOMENDADOS (Orden completo)

### Fase 1: Preparación (1 hora)
1. ✅ Backup de BD actual
   ```bash
   mysqldump -u root -p evaluacion_conanp > backup_15_ene_2026.sql
   ```

2. ✅ Ejecutar SQL migration (`migraciones_cambios_cliente_enero_2026.sql`)
   ```bash
   mysql -u root -p evaluacion_conanp < migraciones_cambios_cliente_enero_2026.sql
   ```

3. ✅ Verificar nuevas columnas en usuarios
   ```sql
   DESCRIBE usuarios;
   -- Buscar: tipo_usuario
   ```

### Fase 2: Importar usuarios (30 minutos)
- **Elegir una opción:**
  - 🏃 DBA con terminal → Opción 1 (LOAD DATA)
  - 🐢 Admin con phpMyAdmin → Opción 2
  - 🎨 Cualquiera → Opción 3 (Asistente PHP)

### Fase 3: Validación (30 minutos)
1. ✅ Ejecutar checklist de datos (CHECKLIST_CARGA_DATOS_INICIAL.md)
2. ✅ Validar jerarquía de jefes
3. ✅ Verificar periodo_actual = 2026
4. ✅ Insertar variables de configuración

### Fase 4: Testing (1 hora)
1. ✅ Login como usuario SPC → Ver "Avance de Evaluación"
2. ✅ Login como usuario Primer Nivel → Ver "Avance"
3. ✅ Login como usuario Eventual → NO ver "Avance"
4. ✅ Editar perfil (usuario normal) → RFC bloqueado
5. ✅ Intentar agregar Capacitación 2025 → Bloqueado

---

## 🆘 SOPORTE

### Si algo sale mal:

**Q: "Error: Duplicate entry for 'user_id'"**  
A: Los usuarios ya existen. Opciones:
```sql
-- A) Actualizar existentes (recomendado)
-- Script ya maneja con ON DUPLICATE KEY UPDATE

-- B) Borrar primero (¡CUIDADO!)
TRUNCATE TABLE usuarios;
-- Luego re-importar
```

**Q: "File not found /tmp/usuarios.csv"**  
A: Revisar ruta en el script SQL:
```sql
-- Cambiar línea:
LOAD DATA INFILE 'C:\\MAMP\\htdocs\\conanp\\usuarios.csv'
-- Por la ruta correcta en tu sistema
```

**Q: "Local infile is disabled"**  
A: Habilitar en MySQL:
```sql
SET GLOBAL local_infile=1;
```

**Q: "Importó pero tipo_usuario está vacío"**  
A: Aplicar conversión:
```sql
UPDATE usuarios SET tipo_usuario = 1 WHERE tipo_usuario IS NULL;
```

**Q: ¿Cómo veo si importó correctamente?**  
A: En phpMyAdmin o terminal:
```sql
SELECT COUNT(*), estatus FROM usuarios GROUP BY estatus;
SELECT COUNT(*), tipo_usuario FROM usuarios GROUP BY tipo_usuario;
```

---

## 📁 ARCHIVOS GENERADOS

| Archivo | Tipo | Uso |
|---------|------|-----|
| `importar_usuarios_csv.sql` | SQL | LOAD DATA INFILE (terminal) |
| `importar_usuarios_inserts.sql` | SQL | INSERT statements (backup) |
| `importar_usuarios_asistente.php` | PHP | Web GUI (recomendado) |
| `usuarios.csv` | CSV | Datos fuente |

---

## ✨ PRÓXIMOS PASOS (Después de importar)

1. Ejecutar `migraciones_cambios_cliente_enero_2026.sql` (si aún no)
2. Completar `CHECKLIST_CARGA_DATOS_INICIAL.md`
3. Configurar variables de período
4. Pruebas funcionales (ver arriba)
5. Comunicar a usuarios cambios del sistema

---

**¿Preguntas?** Contactar: tecnologia@conanp.gob.mx

**Última actualización:** 15 de Enero de 2026
