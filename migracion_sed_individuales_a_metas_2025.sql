-- Migración: sed_individuales (2025) -> metas
-- Objetivo: pasar metas individuales históricas al esquema actual.
-- Idempotente: evita duplicados por (user_id, periodo, indicador, unidad, ponderacion).

SET @periodo_objetivo := 2025;

DROP TEMPORARY TABLE IF EXISTS tmp_sed_metas_2025;
CREATE TEMPORARY TABLE tmp_sed_metas_2025 AS
SELECT
    s.IDMi_ind,
    TRIM(s.rfc) AS rfc,
    1 AS meta_num,
    TRIM(s.mi_m1) AS indicador,
    TRIM(s.mi_m1_um) AS unidad_codigo,
    TRIM(s.mi_m1_ponderacion) AS ponderacion,
    s.mi_m1_sobre AS sobresaliente,
    s.mi_m1_satis AS satisfactorio,
    s.mi_m1_no_satis AS no_satisfactorio,
    s.mi_m1_no_aprob AS no_aprobatorio,
    s.mi_m1_deficiente AS deficiente,
    TRIM(s.mi_m1_resultado) AS resultado
FROM sed_individuales s
WHERE s.anio = CAST(@periodo_objetivo AS CHAR)

UNION ALL
SELECT
    s.IDMi_ind,
    TRIM(s.rfc) AS rfc,
    2 AS meta_num,
    TRIM(s.mi_m2) AS indicador,
    TRIM(s.mi_m2_um) AS unidad_codigo,
    TRIM(s.mi_m2_ponderacion) AS ponderacion,
    s.mi_m2_sobre AS sobresaliente,
    s.mi_m2_satis AS satisfactorio,
    s.mi_m2_no_satis AS no_satisfactorio,
    s.mi_m2_no_aprob AS no_aprobatorio,
    s.mi_m2_deficiente AS deficiente,
    TRIM(s.mi_m2_resultado) AS resultado
FROM sed_individuales s
WHERE s.anio = CAST(@periodo_objetivo AS CHAR)

UNION ALL
SELECT
    s.IDMi_ind,
    TRIM(s.rfc) AS rfc,
    3 AS meta_num,
    TRIM(s.mi_m3) AS indicador,
    TRIM(s.mi_m3_um) AS unidad_codigo,
    TRIM(s.mi_m3_ponderacion) AS ponderacion,
    s.mi_m3_sobre AS sobresaliente,
    s.mi_m3_satis AS satisfactorio,
    s.mi_m3_no_satis AS no_satisfactorio,
    s.mi_m3_no_aprob AS no_aprobatorio,
    s.mi_m3_deficiente AS deficiente,
    TRIM(s.mi_m3_resultado) AS resultado
FROM sed_individuales s
WHERE s.anio = CAST(@periodo_objetivo AS CHAR)

UNION ALL
SELECT
    s.IDMi_ind,
    TRIM(s.rfc) AS rfc,
    4 AS meta_num,
    TRIM(s.mi_m4) AS indicador,
    TRIM(s.mi_m4_um) AS unidad_codigo,
    TRIM(s.mi_m4_ponderacion) AS ponderacion,
    s.mi_m4_sobre AS sobresaliente,
    s.mi_m4_satis AS satisfactorio,
    s.mi_m4_no_satis AS no_satisfactorio,
    s.mi_m4_no_aprob AS no_aprobatorio,
    s.mi_m4_deficiente AS deficiente,
    TRIM(s.mi_m4_resultado) AS resultado
FROM sed_individuales s
WHERE s.anio = CAST(@periodo_objetivo AS CHAR)

UNION ALL
SELECT
    s.IDMi_ind,
    TRIM(s.rfc) AS rfc,
    5 AS meta_num,
    TRIM(s.mi_m5) AS indicador,
    TRIM(s.mi_m5_um) AS unidad_codigo,
    TRIM(s.mi_m5_ponderacion) AS ponderacion,
    s.mi_m5_sobre AS sobresaliente,
    s.mi_m5_satis AS satisfactorio,
    s.mi_m5_no_satis AS no_satisfactorio,
    s.mi_m5_no_aprob AS no_aprobatorio,
    s.mi_m5_deficiente AS deficiente,
    TRIM(s.mi_m5_resultado) AS resultado
FROM sed_individuales s
WHERE s.anio = CAST(@periodo_objetivo AS CHAR);

-- Filtrar filas vacías o inválidas
DELETE FROM tmp_sed_metas_2025
WHERE indicador IS NULL
   OR indicador = ''
   OR ponderacion IS NULL
   OR ponderacion = ''
   OR CAST(ponderacion AS UNSIGNED) = 0;

INSERT INTO metas (
    user_id,
    periodo,
    indicador,
    unidad,
    ponderacion,
    sobresaliente,
    satisfactorio,
    no_satisfactorio,
    no_aprobatorio,
    deficiente,
    estatus,
    resultado,
    resultado_final,
    estatus_carga
)
SELECT
    COALESCE(u_user.user_id, u_rfc.user_id) AS user_id,
    @periodo_objetivo AS periodo,
    t.indicador,
    COALESCE(um.nombre, t.unidad_codigo, 'cantidad') AS unidad,
    CAST(t.ponderacion AS UNSIGNED) AS ponderacion,
    t.sobresaliente,
    t.satisfactorio,
    t.no_satisfactorio,
    t.no_aprobatorio,
    t.deficiente,
    'no evaluado' AS estatus,
    CASE
        WHEN t.resultado REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN CAST(ROUND(CAST(t.resultado AS DECIMAL(10,2)), 0) AS UNSIGNED)
        ELSE 0
    END AS resultado,
    CASE
        WHEN t.resultado REGEXP '^[0-9]+(\\.[0-9]+)?$' THEN CAST(ROUND(CAST(t.resultado AS DECIMAL(10,2)), 0) AS UNSIGNED)
        ELSE 0
    END AS resultado_final,
    1 AS estatus_carga
FROM tmp_sed_metas_2025 t
LEFT JOIN usuarios u_user
    ON UPPER(u_user.username) = UPPER(t.rfc)
LEFT JOIN usuarios u_rfc
    ON u_user.user_id IS NULL
   AND UPPER(u_rfc.RFC) = UPPER(t.rfc)
LEFT JOIN unidades_medida um
    ON LPAD(CAST(um.id AS CHAR), 2, '0') = LPAD(t.unidad_codigo, 2, '0')
WHERE COALESCE(u_user.user_id, u_rfc.user_id) IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM metas m
      WHERE m.user_id = COALESCE(u_user.user_id, u_rfc.user_id)
        AND m.periodo = @periodo_objetivo
        AND m.indicador = t.indicador
        AND (
            m.unidad = COALESCE(um.nombre, t.unidad_codigo, 'cantidad')
            OR LPAD(TRIM(m.unidad), 2, '0') = LPAD(t.unidad_codigo, 2, '0')
        )
        AND m.ponderacion = CAST(t.ponderacion AS UNSIGNED)
  );

-- Ajuste post-carga: normaliza unidad cuando quedó como código numérico (1..10)
UPDATE metas m
INNER JOIN unidades_medida um
        ON LPAD(CAST(um.id AS CHAR), 2, '0') = LPAD(TRIM(m.unidad), 2, '0')
SET m.unidad = um.nombre
WHERE m.periodo = @periodo_objetivo
    AND m.estatus_carga = 1
    AND TRIM(m.unidad) REGEXP '^[0-9]+$';

-- ========================
-- Validaciones sugeridas
-- ========================

-- Total de metas en staging para 2025 (después de filtros)
SELECT COUNT(*) AS total_staging_2025
FROM tmp_sed_metas_2025;

-- RFC sin usuario destino en tabla usuarios
SELECT DISTINCT t.rfc
FROM tmp_sed_metas_2025 t
LEFT JOIN usuarios u_user ON UPPER(u_user.username) = UPPER(t.rfc)
LEFT JOIN usuarios u_rfc ON UPPER(u_rfc.RFC) = UPPER(t.rfc)
WHERE u_user.user_id IS NULL
  AND u_rfc.user_id IS NULL
ORDER BY t.rfc;

-- Conteo final cargado en metas para 2025
SELECT COUNT(*) AS total_metas_2025
FROM metas
WHERE periodo = @periodo_objetivo;

-- Muestra de metas migradas
SELECT m.id, m.user_id, u.username, m.periodo, m.ponderacion, m.unidad,
       LEFT(m.indicador, 120) AS indicador_preview
FROM metas m
INNER JOIN usuarios u ON u.user_id = m.user_id
WHERE m.periodo = @periodo_objetivo
ORDER BY m.user_id, m.id
LIMIT 100;
