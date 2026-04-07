<?php 
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin();

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');
$user_id = $_SESSION['user_id']; 

//validar que tengo todo completo
$datosUsuario = verificarDatosUsuario($pdo, $user_id);
$puesto_id = $_SESSION['puesto_id'];
$periodo = $_SESSION['periodo'];

// Consultar el estatus de ese periodo
$stmt = $pdo->prepare("SELECT estatus FROM periodos WHERE anio = ?");
$stmt->execute([$periodo]);
$estatus_periodo = $stmt->fetchColumn();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['evaluacion'])) {
    try {
        $user_id = $_SESSION['user_id']; 

        //validar que tengo todo completo
        $datosUsuario = verificarDatosUsuario($pdo, $user_id);
        $periodo = $_SESSION['periodo'];
        $nivel = $_POST['nivel'];
        $respuestas = $_POST['evaluacion'];
        $actualiza = isset($_POST['actualiza']);
        $tipo = 'auto';

        $stmt = $pdo->prepare("SELECT estatus_gerenciales FROM calificaciones WHERE user_id = ? AND periodo = ?");
        $stmt->execute([$user_id, $periodo]);
        $estatus_gerenciales = (int)($stmt->fetchColumn() ?? 0);
        if ($estatus_gerenciales >= 2) {
            header("Location: mi_autoevaluacion.php?info=6");
            exit();
        }

        // Obtener valores de competencias por nivel
        $stmt = $pdo->prepare("SELECT cd.id, cd.competencia_id, cv.valor 
                               FROM competencias_descripcion cd
                               JOIN competencias_valores cv ON cd.competencia_id = cv.competencia_id AND cd.nivel = cv.nivel
                               WHERE cd.nivel = ?");
        $stmt->execute([$nivel]);
        $valor_por_comportamiento = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $valor_por_comportamiento[$row['id']] = ['competencia_id' => $row['competencia_id'], 'valor' => $row['valor']];
        }

        foreach ($respuestas as $descripcion_id => $opcion) {
            $valor_base = $valor_por_comportamiento[$descripcion_id]['valor'] ?? 0;
            $competencia_id = $valor_por_comportamiento[$descripcion_id]['competencia_id'] ?? 0;

            $peso = match (trim($opcion)) {
                'Muy Característico' => 100,
                'Característico' => 80,
                'Poco Característico' => 50,
                'No es Característico' => 20,
                'No Aplica' => 0,
                default => 0
            };

            $valor_final = $peso;

            if ($actualiza) {
                // Primero eliminar registros existentes para este comportamiento
                $delete = $pdo->prepare("
                    DELETE FROM competencias_evaluacion 
                    WHERE user_id = ? AND descripcion_id = ? AND periodo = ? AND tipo = ?
                ");
                $delete->execute([$user_id, $descripcion_id, $periodo, $tipo]);
                
                // Luego insertar el nuevo valor
                $insert = $pdo->prepare("
                    INSERT INTO competencias_evaluacion 
                    (user_id, competencia_id, descripcion_id, evaluacion, valor, periodo, tipo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([$user_id, $competencia_id, $descripcion_id, $opcion, $valor_final, $periodo, $tipo]);
            } else {
                $insert = $pdo->prepare("INSERT INTO competencias_evaluacion 
                    (user_id, competencia_id, descripcion_id, evaluacion, valor, periodo, tipo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insert->execute([$user_id, $competencia_id, $descripcion_id, $opcion, $valor_final, $periodo, $tipo]);
            }
        }

        // Cambio #6, D1, D6: Calcular y guardar la calificación gerencial en tabla calificaciones
        // Obtener nivel del puesto del usuario
        $stmt_nivel = $pdo->prepare("SELECT puesto_nivel FROM usuarios WHERE user_id = ?");
        $stmt_nivel->execute([$user_id]);
        $nivel_puesto = $stmt_nivel->fetchColumn();

        // Calcular promedio ponderado de competencias
        $sql_calculo = "
            SELECT ROUND(SUM(avg_valor * peso) / SUM(peso), 2) AS calificacion_final
            FROM (
                SELECT 
                    eval.competencia_id,
                    eval.avg_valor,
                    CASE 
                        WHEN eval.competencia_id = 1 THEN cp.vision
                        WHEN eval.competencia_id = 2 THEN cp.liderazgo
                        WHEN eval.competencia_id = 3 THEN cp.orientacion
                        WHEN eval.competencia_id = 4 THEN cp.negociacion
                        WHEN eval.competencia_id = 5 THEN cp.trabajo
                    END as peso
                FROM (
                    SELECT competencia_id, AVG(valor) AS avg_valor
                    FROM competencias_evaluacion
                    WHERE user_id = ? AND periodo = ? AND tipo = 'auto' AND valor > 0
                    GROUP BY competencia_id
                ) AS eval
                JOIN usuarios u ON u.user_id = ?
                JOIN competencias_pesos cp ON u.puesto_nivel = cp.nivel
            ) AS calc
            WHERE peso > 0
        ";
        
        $stmt_calculo = $pdo->prepare($sql_calculo);
        $stmt_calculo->execute([$user_id, $periodo, $user_id]);
        $calificacion_gerencial = $stmt_calculo->fetchColumn() ?? 0;

        // Actualizar tabla calificaciones con estatus=2 (autoevaluado, pendiente de jefe)
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE user_id = ? AND periodo = ?");
        $stmt_check->execute([$user_id, $periodo]);
        
        if ($stmt_check->fetchColumn() > 0) {
            $stmt_update = $pdo->prepare("UPDATE calificaciones SET gerenciales = ?, estatus_gerenciales = 2 WHERE user_id = ? AND periodo = ?");
            $stmt_update->execute([$calificacion_gerencial, $user_id, $periodo]);
        } else {
            $stmt_insert = $pdo->prepare("INSERT INTO calificaciones (user_id, periodo, gerenciales, estatus_gerenciales) VALUES (?, ?, ?, 2)");
            $stmt_insert->execute([$user_id, $periodo, $calificacion_gerencial]);
        }

        header("Location: mi_autoevaluacion.php?info=2");
        exit();
        
    } catch (PDOException $e) {
        // Cambio Febrero 2026 - E1: Manejo controlado de errores en autoevaluación
        error_log("Error guardando autoevaluación gerencial: " . $e->getMessage());
        header("Location: mi_autoevaluacion.php?info=error&msg=" . urlencode("Error al guardar la autoevaluación. Por favor intente nuevamente."));
        exit();
    } catch (Exception $e) {
        error_log("Error inesperado en autoevaluación: " . $e->getMessage());
        header("Location: mi_autoevaluacion.php?info=error&msg=" . urlencode("Error inesperado. Contacte al administrador."));
        exit();
    }
}
?>
