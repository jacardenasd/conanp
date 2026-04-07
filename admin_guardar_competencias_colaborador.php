<?php 
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(2);

$periodo = $_SESSION['periodo'];

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['evaluacion'])) {
    $user_id = $_POST['colaborador']; 
    $nivel = $_POST['nivel'];
    $respuestas = $_POST['evaluacion'];
    $tipo = 'jefe';

    //ver si ya existen capturas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM competencias_evaluacion WHERE user_id = ? AND periodo = ?");
    $stmt->execute([$user_id, $periodo]);
    $cantidad_capturas = $stmt->fetchColumn();

    //ver si ya existen resultados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE user_id = ? AND periodo = ?");
    $stmt->execute([$user_id, $periodo]);
    $cantidad_calificaciones = $stmt->fetchColumn();
    
        
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
            $stmt = $pdo->prepare("SELECT id FROM competencias_evaluacion WHERE user_id = ? AND descripcion_id = ? AND periodo = ? AND tipo = ?");
            $stmt->execute([$user_id, $descripcion_id, $periodo, $tipo]);
            if ($stmt->fetchColumn()) {
                $upd = $pdo->prepare("UPDATE competencias_evaluacion SET evaluacion = ?, valor = ? 
                                      WHERE user_id = ? AND descripcion_id = ? AND periodo = ? AND tipo = ?");
                $upd->execute([$opcion, $peso, $user_id, $descripcion_id, $periodo, $tipo]);
                continue;
            }
        }

        $insert = $pdo->prepare("INSERT INTO competencias_evaluacion 
            (user_id, competencia_id, descripcion_id, evaluacion, valor, periodo, tipo) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insert->execute([$user_id, $competencia_id, $descripcion_id, $opcion, $valor_final, $periodo, $tipo]);
    }


   
    $sql = "SELECT 
        u.user_id,
        CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) AS nombre_completo,
        u.puesto_nivel,
        ROUND(SUM(avg_valor * peso) / SUM(peso), 2) AS calificacion_final
    FROM usuarios u
    JOIN (
        SELECT 
            ce.user_id,
            ce.competencia_id,
            AVG(ce.valor) AS avg_valor
        FROM competencias_evaluacion ce
        WHERE ce.periodo = :periodo AND ce.tipo = 'jefe' AND ce.valor > 0 -- ← aquí se omiten los que tienen valor 0
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
    
    // Cambio #6: Actualizar estatus gerenciales a 3 (evaluado por jefe) y guardar calificación
    if ($cantidad_calificaciones) {
        $stmt_updates = $pdo->prepare("UPDATE calificaciones SET gerenciales = ?, estatus_gerenciales = 3 WHERE user_id = ? AND periodo = ?");
        $stmt_updates->execute([$calificacion_final, $user_id, $periodo]);
    } else {
        $stmt_inserts = $pdo->prepare("INSERT INTO calificaciones (user_id, periodo, gerenciales, estatus_gerenciales) VALUES (?, ?, ?, 3)");
        $stmt_inserts->execute([$user_id, $periodo, $calificacion_final]);
    }

    // Cambio #6: Bloquear edición del usuario después de evaluación del jefe
    $stmt_bloqueo = $pdo->prepare("UPDATE competencias_evaluacion SET bloqueado_edicion = 1 WHERE user_id = ? AND periodo = ? AND tipo = 'auto'");
    $stmt_bloqueo->execute([$user_id, $periodo]);


   header("Location: admin_calificaciones.php?info=1");
    exit();
}
?>
