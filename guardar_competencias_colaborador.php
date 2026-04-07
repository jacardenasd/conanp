<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

if (!function_exists('obtener_estatus_prerequisitos_colaborador')) {
    function obtener_estatus_prerequisitos_colaborador($pdo, $colaborador_id, $periodo) {
        $stmt = $pdo->prepare("SELECT estatus_metas, estatus_gerenciales FROM calificaciones WHERE user_id = ? AND periodo = ?");
        $stmt->execute([$colaborador_id, $periodo]);
        $estatus = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $estatus_metas = (int)($estatus['estatus_metas'] ?? 0);
        $estatus_gerenciales = (int)($estatus['estatus_gerenciales'] ?? 0);

        $stmt = $pdo->prepare("SELECT COUNT(*) AS total_metas,
                                      SUM(CASE WHEN resultado IS NOT NULL AND resultado <> '' AND resultado <> 0 THEN 1 ELSE 0 END) AS metas_con_propuesta
                               FROM metas
                               WHERE user_id = ? AND periodo = ?");
        $stmt->execute([$colaborador_id, $periodo]);
        $metas = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total_metas = (int)($metas['total_metas'] ?? 0);
        $metas_con_propuesta = (int)($metas['metas_con_propuesta'] ?? 0);
        $metas_listas = ($total_metas > 0 && $metas_con_propuesta >= $total_metas);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM competencias_evaluacion WHERE user_id = ? AND periodo = ? AND tipo = 'auto'");
        $stmt->execute([$colaborador_id, $periodo]);
        $autoeval_registros = (int)($stmt->fetchColumn() ?? 0);
        $gerenciales_listas = ($autoeval_registros > 0);

        $estatus_metas_efectivo = max($estatus_metas, $metas_listas ? 2 : 0);
        $estatus_gerenciales_efectivo = max($estatus_gerenciales, $gerenciales_listas ? 2 : 0);

        return [
            'estatus_metas' => $estatus_metas,
            'estatus_gerenciales' => $estatus_gerenciales,
            'estatus_metas_efectivo' => $estatus_metas_efectivo,
            'estatus_gerenciales_efectivo' => $estatus_gerenciales_efectivo,
            'metas_listas' => $metas_listas,
            'gerenciales_listas' => $gerenciales_listas,
            'puede_evaluar_jefe' => ($estatus_metas_efectivo >= 2 && $estatus_gerenciales_efectivo >= 2),
        ];
    }
}

$periodo = $_SESSION['periodo'];

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['evaluacion'])) {
    try {
        $user_id = (int)($_POST['colaborador'] ?? 0);
        $nivel = (int)($_POST['nivel'] ?? 0);
        $respuestas = $_POST['evaluacion'];
        $tipo = 'jefe';

        if ($user_id <= 0 || $nivel <= 0 || !is_array($respuestas) || count($respuestas) === 0) {
            header("Location: mis_colaboradores.php?info=8");
            exit();
        }

        $jefe_id = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE user_id = ? AND jefe_id = ?");
        $stmt->execute([$user_id, $jefe_id]);
        if ((int)$stmt->fetchColumn() <= 0) {
            header("Location: mis_colaboradores.php?info=8");
            exit();
        }

        $prerequisitos = obtener_estatus_prerequisitos_colaborador($pdo, $user_id, (int)$periodo);
        $estatus_metas = (int)$prerequisitos['estatus_metas_efectivo'];
        $estatus_gerenciales = (int)$prerequisitos['estatus_gerenciales_efectivo'];

        if ($estatus_gerenciales < 2) {
            header("Location: mis_colaboradores.php?info=11");
            exit();
        }

        $stmt = $pdo->prepare("SELECT estatus_gerenciales FROM calificaciones WHERE user_id = ? AND periodo = ?");
        $stmt->execute([$user_id, $periodo]);
        $estatus_gerenciales = (int)($stmt->fetchColumn() ?? 0);
        if ($estatus_gerenciales >= 3) {
            header("Location: evaluar_competencias_colaborador.php?colaborador=$user_id&info=6");
            exit();
        }

        //ver si ya existen capturas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM competencias_evaluacion WHERE user_id = ? AND periodo = ?");
        $stmt->execute([$user_id, $periodo]);
        $cantidad_capturas = (int)$stmt->fetchColumn();

        //ver si ya existen resultados
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE user_id = ? AND periodo = ?");
        $stmt->execute([$user_id, $periodo]);
        $cantidad_calificaciones = (int)$stmt->fetchColumn();
        
            
        // Obtener valores de competencias por nivel
        $stmt = $pdo->prepare("SELECT id, competencia_id FROM competencias_descripcion WHERE nivel = ?");
        $stmt->execute([$nivel]);
        $valor_por_comportamiento = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $valor_por_comportamiento[$row['id']] = ['competencia_id' => $row['competencia_id']];
        }

        foreach ($respuestas as $descripcion_id => $opcion) {
            $competencia_id = $valor_por_comportamiento[$descripcion_id]['competencia_id'] ?? 0;
            if ($competencia_id <= 0) {
                continue;
            }

            $peso = match (trim($opcion)) {
                'Muy Característico' => 100,
                'Característico' => 80,
                'Poco Característico' => 50,
                'No es Característico' => 20,
                'No Aplica' => 0,
                default => 0
            };

            $valor_final = $peso;

            if ($cantidad_capturas) {
                // Eliminar registro existente para este comportamiento (jefe)
                $delete = $pdo->prepare("
                    DELETE FROM competencias_evaluacion 
                    WHERE user_id = ? AND descripcion_id = ? AND periodo = ? AND tipo = ?
                ");
                $delete->execute([$user_id, $descripcion_id, $periodo, $tipo]);
            }

            // Insertar el nuevo registro
            $insert = $pdo->prepare("INSERT INTO competencias_evaluacion 
                (user_id, competencia_id, descripcion_id, evaluacion, valor, periodo, tipo) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([$user_id, $competencia_id, $descripcion_id, $opcion, $valor_final, $periodo, $tipo]);
        }


   
    $sql = "SELECT 
        u.user_id,
        CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS nombre_completo,
        u.puesto_nivel,
        ROUND(SUM(avg_valor * peso) / NULLIF(SUM(peso), 0), 2) AS calificacion_final
    FROM usuarios u
    JOIN (
        SELECT 
            ce.user_id,
            ce.competencia_id,
            AVG(ce.valor) AS avg_valor
        FROM competencias_evaluacion ce
        WHERE ce.periodo = :periodo AND ce.tipo = 'jefe' AND ce.valor > 0
        GROUP BY ce.user_id, ce.competencia_id
    ) AS eval ON eval.user_id = u.user_id
    JOIN (
        SELECT 
            nivel,
            competencia_id,
            CASE competencia_id
                WHEN 1 THEN vision
                WHEN 2 THEN liderazgo
                WHEN 3 THEN orientacion
                WHEN 4 THEN negociacion
                WHEN 5 THEN trabajo
            END AS peso
        FROM (
            SELECT 
                nivel, 
                1 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
            UNION ALL
            SELECT 
                nivel, 
                2 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
            UNION ALL
            SELECT 
                nivel, 
                3 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
            UNION ALL
            SELECT 
                nivel, 
                4 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
            UNION ALL
            SELECT 
                nivel, 
                5 AS competencia_id, vision, liderazgo, orientacion, negociacion, trabajo FROM competencias_pesos
        ) AS pesos_ext
    ) AS pesos ON u.puesto_nivel = pesos.nivel AND eval.competencia_id = pesos.competencia_id
    WHERE u.user_id = :user_id
    GROUP BY u.user_id, u.nombre, u.apellido_paterno, u.apellido_materno, u.puesto_nivel
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id, 'periodo' => $periodo]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $calificacion_final = $resultado['calificacion_final'] ?? null;
    if ($calificacion_final === null) {
        $stmt_fallback = $pdo->prepare("SELECT ROUND(AVG(valor), 2) FROM competencias_evaluacion WHERE user_id = ? AND periodo = ? AND tipo = 'jefe' AND valor > 0");
        $stmt_fallback->execute([$user_id, $periodo]);
        $calificacion_final = $stmt_fallback->fetchColumn();
    }
    $calificacion_final = $calificacion_final ?? 0;
    
    // Cambio #D3, D4, D6: Guardar calificación del jefe con estatus=3 (finalizado)
    if ($cantidad_calificaciones) {
        $stmt_updates = $pdo->prepare("UPDATE calificaciones SET gerenciales = ?, estatus_gerenciales = 3 WHERE user_id = ? AND periodo = ?");
        $stmt_updates->execute([$calificacion_final, $user_id, $periodo]);
    } else {
        $stmt_inserts = $pdo->prepare("INSERT INTO calificaciones (user_id, periodo, gerenciales, estatus_gerenciales) VALUES (?, ?, ?, 3)");
        $stmt_inserts->execute([$user_id, $periodo, $calificacion_final]);
    }

    $stmt_bloqueo = $pdo->prepare("UPDATE competencias_evaluacion SET bloqueado_edicion = 1 WHERE user_id = ? AND periodo = ? AND tipo = 'auto'");
    $stmt_bloqueo->execute([$user_id, $periodo]);

   header("Location: mis_colaboradores.php?info=1");
    exit();
    
    } catch (PDOException $e) {
        // Cambio Febrero 2026 - E3: Manejo controlado de errores al guardar evaluación del jefe
        error_log("Error guardando evaluación gerencial del jefe: " . $e->getMessage());
        header("Location: mis_colaboradores.php?info=error&msg=" . urlencode("Error al guardar la evaluación. Por favor intente nuevamente."));
        exit();
    } catch (Exception $e) {
        error_log("Error inesperado en evaluación del jefe: " . $e->getMessage());
        header("Location: mis_colaboradores.php?info=error&msg=" . urlencode("Error inesperado. Contacte al administrador."));
        exit();
    }
}
?>
