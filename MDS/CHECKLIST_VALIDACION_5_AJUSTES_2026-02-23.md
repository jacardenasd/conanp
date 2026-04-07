# Checklist de validación - 5 ajustes (23-Feb-2026)

## Preparación
- Iniciar sesión con 3 perfiles de prueba:
  1. Admin RH
  2. Jefe inmediato
  3. Candidato (evaluado)
- Usar un periodo de prueba (ej. 2029) y registrar datos controlados.

---

## 1) Admin valida y ya no aparece 404
**Pantalla:** `admin_aportaciones_destacadas.php` y `admin_actividades_extraordinarias.php`

### Pasos
1. Filtrar por colaborador y periodo.
2. Dar clic en **Validar** en un registro pendiente.

### Resultado esperado
- No aparece error 404.
- Regresa a la misma pantalla con `info=1`.
- El registro queda con `validado_rh = 1`.

### SQL de apoyo
```sql
SELECT id, validado_rh
FROM aportaciones_destacadas
WHERE user_id = :user_id AND periodo = :periodo
ORDER BY id DESC
LIMIT 5;
```

```sql
SELECT id, validado_rh
FROM actividades_extraordinarias
WHERE user_id = :user_id AND periodo = :periodo
ORDER BY id DESC
LIMIT 5;
```

---

## 2) Jefe termina sin validar y NO suma puntos
**Pantalla:** `cols_aportaciones_destacadas.php` y `cols_actividades_extraordinarias.php`

### Pasos
1. Como jefe, abrir colaborador con registros capturados pero sin validar.
2. No validar ninguno.
3. Dar clic en **Terminar**.

### Resultado esperado
- `calificaciones.aportaciones_destacadas = 0` cuando no hubo validaciones.
- `calificaciones.actividades_extraordinarias = 0` cuando no hubo validaciones.
- No se suman puntos por simples capturas.

### SQL de apoyo
```sql
SELECT periodo, aportaciones_destacadas, estatus_aportaciones_destacadas,
       actividades_extraordinarias, estatus_actividades_extraordinarias
FROM calificaciones
WHERE user_id = :user_id AND periodo = :periodo;
```

---

## 3) Cédula bloqueada si Admin no validó/descartó aportaciones/actividades
**Pantalla:** `mi_evaluacion.php` y descarga en `reporte_cedula_resultados.php`

### Pasos
1. Como candidato, tener al menos 1 aportación o actividad capturada.
2. Dejar el registro pendiente de RH (ni validado ni descartado por RH).
3. Intentar descargar cédula.

### Resultado esperado
- Botón de descarga bloqueado en Mi evaluación (o mensaje de requisito pendiente).
- Si se intenta URL directa de descarga, debe mostrar pantalla de requisitos pendientes.

### SQL de apoyo
```sql
SELECT id, validado_rh, COALESCE(rechazado_por_rh,0) AS rechazado_por_rh
FROM aportaciones_destacadas
WHERE user_id = :user_id AND periodo = :periodo;
```

```sql
SELECT id, validado_rh, COALESCE(rechazado_por_rh,0) AS rechazado_por_rh
FROM actividades_extraordinarias
WHERE user_id = :user_id AND periodo = :periodo;
```

---

## 4) Cédula bloqueada si metas colectivas no evaluadas (sin “Falso”)
**Pantalla:** `mi_evaluacion.php` y `reporte_cedula_resultados.php`

### Pasos
1. Configurar unidad con metas colectivas en el periodo.
2. Dejar `calificaciones_colectivas.estatus < 2`.
3. Intentar descargar cédula.

### Resultado esperado
- Descarga bloqueada por requisito de metas colectivas.
- Ya no aparece valor booleano/falso como calificación en cédula.

### SQL de apoyo
```sql
SELECT unidad_id, periodo, estatus, resultado
FROM calificaciones_colectivas
WHERE unidad_id = :unidad_id AND periodo = :periodo;
```

---

## 5) Metas colectivas 2029: captura y evaluación consistentes
**Pantallas:** `mis_metas_colectivas.php`, `metas_colectivas.php`, `cierre_periodo_colectivas.php`

### Pasos
1. Definir sesión en periodo 2029.
2. Capturar varias metas (más de una) con ponderación total 100.
3. Cerrar captura.
4. En evaluación, registrar resultados y cerrar evaluación.

### Resultado esperado
- No se cierra prematuramente tras una sola meta.
- Evaluación permitida en el mismo periodo activo (2029).
- Cálculo de colectivas guardado en periodo correcto.

### SQL de apoyo
```sql
SELECT unidad_id, periodo, COUNT(*) AS total_metas, SUM(ponderacion) AS suma_pond
FROM metas_colectivas
WHERE unidad_id = :unidad_id AND periodo = 2029
GROUP BY unidad_id, periodo;
```

```sql
SELECT unidad_id, periodo, estatus, resultado
FROM calificaciones_colectivas
WHERE unidad_id = :unidad_id AND periodo = 2029;
```

---

## Criterio final de aceptación
- Los 5 casos pasan sin errores de navegación ni sumatorias incorrectas.
- No hay descarga de cédula cuando existan pendientes de RH o colectivas sin evaluar.
- El periodo de metas colectivas se mantiene consistente en captura/evaluación/cierre.
