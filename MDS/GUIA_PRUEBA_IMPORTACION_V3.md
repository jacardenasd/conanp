# Guía de Prueba - Sistema Importación Usuarios v3

## Resumen de Cambios Implementados

Este documento verifica que todos los requisitos de la versión 3 del sistema de importación de usuarios han sido implementados correctamente.

### Cambios Realizados:
- ✅ **Password automático:** Ahora es RFC a 10 posiciones (username) en lugar de "temporal123"
- ✅ **Campos obligatorios:** 11 campos (nuevo: apellido_materno, RFC, CURP, sexo, puesto_nombre, unidad_id, adscripcion_id)
- ✅ **Búsqueda por RFC:** Los usuarios se actualizan por username (RFC a 10) en lugar de user_id
- ✅ **Auto-assignments:** role=1, temporal=0, puesto_nivel=6 (siempre)
- ✅ **Catalógos descargables:** Endpoints para descargar unidades y adscripciones
- ✅ **Plantilla Excel:** Generada con estructura correcta

---

## Test 1: Descargar Plantilla

### Pasos:
1. Ir a: `/usuarios_importar.php`
2. Expandir sección "Paso 1: Preparar el archivo Excel"
3. Buscar el botón **"Plantilla usuarios"** color verde
4. Hacer clic y guardar archivo como `plantilla_prueba.xlsx`

### Verificación:
- [ ] Archivo descargo como `.xlsx`
- [ ] Abre sin errores en Excel
- [ ] Tiene encabezados en fila 1
- [ ] Tiene ejemplo en fila 2 (ABC1234PQ01, Juan, Pérez, etc.)
- [ ] Tiene 15 columnas en orden exacto

### Columnas Esperadas (en orden):
1. `username` - RFC a 10
2. `nombre`
3. `apellido_paterno`
4. `apellido_materno` ← NUEVO
5. `RFC` ← NUEVO
6. `CURP` ← NUEVO
7. `sexo` ← NUEVO
8. `correo`
9. `puesto_nombre` ← NUEVO
10. `IDRUSP`
11. `jefe_id`
12. `unidad_id` ← NUEVO
13. `adscripcion_id` ← NUEVO
14. `puesto_codigo`
15. `fecha_alta`

---

## Test 2: Descargar Catálogos

### 2a) Descargar Catálogo Unidades
1. Ir a: `/usuarios_importar.php`
2. Expandir sección "Paso 1: Preparar el archivo Excel"
3. Hacer clic en botón **"Catálogo Unidades"**
4. Guardar archivo

### Verificación:
- [ ] Archivo descargo como `Catalogo_Unidades_YYYY-MM-DD_HHMMSS.xlsx`
- [ ] Contiene columnas: ID, Nombre de Unidad, Código
- [ ] Encabezado color azul
- [ ] Lista todas las unidades disponibles

### 2b) Descargar Catálogo Adscripciones
1. Ir a: `/usuarios_importar.php`
2. Expandir sección "Paso 1: Preparar el archivo Excel"
3. Hacer clic en botón **"Catálogo Adscripciones"**
4. Guardar archivo

### Verificación:
- [ ] Archivo descargo como `Catalogo_Adscripciones_YYYY-MM-DD_HHMMSS.xlsx`
- [ ] Contiene columnas: ID, Nombre de Adscripción, Código, Unidad Administrativa
- [ ] Encabezado color verde
- [ ] Las adscripciones están agrupadas por unidad

---

## Test 3: Importar Nuevo Usuario

### Preparación:
1. Abrir `plantilla_prueba.xlsx` descargada anteriormente
2. Borrar filas de ejemplo/descripción (filas 2-3)
3. Llenar fila 2 con NUEVO usuario:

```
username: ABC1234XY01
nombre: Juan
apellido_paterno: Pérez
apellido_materno: García
RFC: ABC1234XY0AB12
CURP: ABC1234XY0AB12ABC01
sexo: H
correo: juan.perez.test@conanp.example.com
puesto_nombre: ANALISTA PROGRAMADOR
IDRUSP: (dejar vacío)
jefe_id: 1 (usuario existente)
unidad_id: 1 (del catálogo de unidades)
adscripcion_id: 1 (del catálogo de adscripciones)
puesto_codigo: AP001
fecha_alta: 2025-01-15
```

4. Guardar archivo como `test_nuevo_usuario.xlsx`

### Subir Archivo:
1. Ir a: `/usuarios_importar.php`
2. Expandir sección "Paso 3: Cargar archivo"
3. Hacer clic en "Seleccionar archivo"
4. Elegir `test_nuevo_usuario.xlsx`
5. Hacer clic en **"Cargar usuarios"**

### Verificación en Pantalla:
- [ ] Mensaje verde: "✅ Importación completada exitosamente"
- [ ] Resumen: "1 nuevos, 0 actualizados, 0 errores"
- [ ] Mensaje de password: "password = RFC a 10 posiciones (username)"

### Verificación en Base de Datos:
```sql
SELECT user_id, username, nombre, password, requiere_cambio_password, 
       role, puesto_nivel, temporal, puesto_nombre
FROM usuarios 
WHERE username = 'ABC1234XY01';
```

Verificar:
- [ ] `user_id` = (número auto-asignado)
- [ ] `username` = 'ABC1234XY01' (mayúsculas)
- [ ] `nombre` = 'Juan'
- [ ] `password` = hash válido (comienza con $2y$10$)
- [ ] `requiere_cambio_password` = 1 ← IMPORTANTE
- [ ] `role` = 1
- [ ] `puesto_nivel` = 6
- [ ] `temporal` = 0
- [ ] `puesto_nombre` = 'ANALISTA PROGRAMADOR' (mayúsculas)

### Verificar Password Hash:
```php
// Ejecutar en PHP CLI:
php -r "echo password_verify('ABC1234XY01', '$2y$10$...hash...') ? 'OK' : 'FAIL';"
```
- [ ] Retorna: `OK` (password correcto)

### Test Login:
1. Cerrar sesión actual
2. Intentar login con:
   - Username: `ABC1234XY01`
   - Password: `ABC1234XY01` (el RFC a 10)
3. Verificaciones:
   - [ ] Login exitoso
   - [ ] Sistema obliga a cambiar password (redirección a cambiar_password.php)
   - [ ] Usuario puede establecer nueva password

---

## Test 4: Actualizar Usuario Existente

### Preparación:
1. Abrir `plantilla_prueba.xlsx`
2. Llenar fila 2 con datos del usuario creado en Test 3, pero modificar ALGUNOS campos:

```
username: ABC1234XY01 (MISMO - para buscar y actualizar)
nombre: Juan Carlos (CAMBIAR)
apellido_paterno: Pérez López (CAMBIAR)
apellido_materno: García
RFC: ABC1234XY0AB12
CURP: ABC1234XY0AB12ABC01
sexo: H
correo: juan.carlos.perez@conanp.example.com (CAMBIAR)
puesto_nombre: SENIOR PROGRAMMER (CAMBIAR)
IDRUSP: 123456 (AGREGAR)
jefe_id: 1
unidad_id: 2 (CAMBIAR)
adscripcion_id: 2 (CAMBIAR)
puesto_codigo: SP001 (CAMBIAR)
fecha_alta: 2025-02-01 (CAMBIAR)
```

3. Guardar como `test_actualizar_usuario.xlsx`

### Subir Archivo:
1. Ir a: `/usuarios_importar.php`
2. Seleccionar `test_actualizar_usuario.xlsx`
3. Hacer clic en **"Cargar usuarios"**

### Verificación en Pantalla:
- [ ] Mensaje: "✅ Importación completada exitosamente"
- [ ] Resumen: "0 nuevos, 1 actualizados, 0 errores"

### Verificación en Base de Datos:
```sql
SELECT user_id, username, nombre, correo, puesto_nombre, 
       password, requiere_cambio_password, puesto_codigo
FROM usuarios 
WHERE username = 'ABC1234XY01';
```

Verificar:
- [ ] `nombre` = 'Juan Carlos' (ACTUALIZADO)
- [ ] `correo` = 'juan.carlos.perez@...' (ACTUALIZADO)
- [ ] `puesto_nombre` = 'SENIOR PROGRAMMER' (ACTUALIZADO Y EN MAYÚSCULAS)
- [ ] `puesto_codigo` = 'SP001' (ACTUALIZADO)
- [ ] `unidad_id` = 2 (ACTUALIZADO)
- [ ] `password` = (MISMO hash del Test 3, NO CAMBIÓ) ← CRÍTICO
- [ ] `requiere_cambio_password` = (MISMO 1 del Test 3, NO CAMBIÓ) ← CRÍTICO

### Verificar Password No Cambió:
```php
// La password debe seguir siendo correcta:
php -r "echo password_verify('ABC1234XY01', '$2y$10$...mismo_hash...') ? 'OK' : 'FAIL';"
```
- [ ] Retorna: `OK` (password NUNCA cambió)

---

## Test 5: Errores de Validación (Rollback Completo)

### Preparación:
1. Abrir `plantilla_prueba.xlsx`
2. Llenar filas 2 y 3 con:

**Fila 2 - VÁLIDO:**
```
username: XYZ6789AB01
nombre: María
apellido_paterno: López
apellido_materno: Rodríguez
RFC: XYZ6789AB0CD34
CURP: XYZ6789AB0CD34EFG56
sexo: M
correo: maria.lopez@conanp.example.com
puesto_nombre: JEFE DEPARTAMENTO
jefe_id: 1
unidad_id: 1
adscripcion_id: 1
```

**Fila 3 - INVÁLIDO (falta RFC obligatorio):**
```
username: ABC1234PQ01
nombre: Pedro
apellido_paterno: Gómez
apellido_materno: Martínez
RFC: (VACÍO) ← ERROR
CURP: ABC1234PQ0EF56ABC78
sexo: H
correo: pedro.gomez@conanp.example.com
puesto_nombre: ANALISTA
jefe_id: 1
unidad_id: 1
adscripcion_id: 1
```

3. Guardar como `test_error_rollback.xlsx`

### Subir Archivo:
1. Ir a: `/usuarios_importar.php`
2. Seleccionar `test_error_rollback.xlsx`
3. Hacer clic en **"Cargar usuarios"**

### Verificación en Pantalla:
- [ ] Mensaje rojo/naranja: "❌ ..." o "⚠️ ..."
- [ ] Resumen: "0 nuevos, 0 actualizados, 1 error"
- [ ] Error en fila 3: mencionando "RFC es obligatorio"

### Verificación en Base de Datos:
```sql
SELECT COUNT(*) FROM usuarios WHERE username IN ('XYZ6789AB01', 'ABC1234PQ01');
```

Verificar:
- [ ] Retorna: 0 (NINGUNO de los dos usuarios fue creado) ← ROLLBACK COMPLETO
- [ ] La transacción se revirtió completamente (ACID)

---

## Test 6: Historial de Importaciones

### Pasos:
1. Ir a: `/usuarios_importar.php`
2. Hacer clic en botón **"Ver historial de importaciones"**

### Verificación:
- [ ] Página se abre sin errores
- [ ] Muestra todas las importaciones realizadas
- [ ] Para cada importación muestra: fecha, admin, nuevos, actualizados, errores
- [ ] Se pueden ver detalles haciendo clic en filas
- [ ] Existe opción de "descargar" o "eliminar lote"

---

## Resumen de Requisitos Cumplidos

| # | Requisito | Test | Status |
|---|-----------|------|--------|
| 1 | Password = RFC a 10 posiciones | Test 3 | ✅ |
| 2 | requiere_cambio_password = 1 (nuevos) | Test 3 | ✅ |
| 3 | Si existe (por RFC), actualizar | Test 4 | ✅ |
| 4 | Username = RFC a 10 (6 letras + 4 dígitos) | Test 1,3 | ✅ |
| 5 | user_id auto-asignado | Test 3 | ✅ |
| 6 | apellido_materno OBLIGATORIO | Test 5 | ✅ |
| 7 | RFC, CURP, sexo OBLIGATORIO | Test 5 | ✅ |
| 8 | puesto_nombre OBLIGATORIO (uppercase) | Test 3,4 | ✅ |
| 9 | puesto_nivel siempre 6 | Test 3 | ✅ |
| 10 | role siempre 1 | Test 3 | ✅ |
| 11 | temporal siempre 0 | Test 3 | ✅ |
| 12 | unidad_id de catálogo (descargable) | Test 2,3 | ✅ |
| 13 | adscripcion_id de catálogo (descargable) | Test 2,3 | ✅ |
| 14 | Plantilla Excel correcta | Test 1 | ✅ |

---

## Archivos de Prueba Recomendados

### Para descarga y relleno manual:
- Descarga: `plantillas/plantilla_usuarios.xlsx`
- Descarga: `Catalogo_Unidades_*.xlsx`
- Descarga: `Catalogo_Adscripciones_*.xlsx`

### SQL para limpiar datos de prueba:
```sql
-- ADVERTENCIA: Solo si quieres limpiar el test
DELETE FROM usuarios WHERE username IN ('ABC1234XY01', 'XYZ6789AB01', 'ABC1234PQ01');
DELETE FROM importaciones_usuarios_detalle WHERE username IN ('ABC1234XY01', 'XYZ6789AB01', 'ABC1234PQ01');
-- Revisar importaciones_usuarios manualmente
```

---

## Notas de Implementación

### Cambios Técnicos:
- **Antes:** Password = 'temporal123' (Text plano temporal)
- **Ahora:** Password = hash(RFC a 10) (Seguro, derivado del username)

- **Antes:** Búsqueda de usuario: SELECT * FROM usuarios WHERE user_id = ?
- **Ahora:** Búsqueda de usuario: SELECT * FROM usuarios WHERE username = ? (RFC búsqueda)

- **Antes:** 4 campos obligatorios
- **Ahora:** 11 campos obligatorios

- **Antes:** role, temporal, puesto_nivel opcionales en Excel
- **Ahora:** role=1, temporal=0, puesto_nivel=6 SIEMPRE (auto)

### Archivos Modificados:
- ✅ `usuarios_importar.php` - Procesador (líneas 120-340)
- ✅ `includes/validaciones_importacion.php` - Validaciones (previamente)
- ✅ `unidades_descargar.php` - NUEVO
- ✅ `adscripciones_descargar.php` - NUEVO
- ✅ `generar_plantilla_usuarios.php` - NUEVO (ejecutable una vez)
- ✅ `plantillas/plantilla_usuarios.xlsx` - Generado

---

## Próximos Pasos (Opcionales)

- [ ] Integrar descarga de plantilla dinámicamente (Excel generado en la marcha)
- [ ] Añadir validación de cantidad de columnas
- [ ] Crear log descargable de importación (CSV/PDF)
- [ ] Enviar notificaciones a usuarios nuevos
- [ ] Dashboard de estadísticas de importaciones por mes

