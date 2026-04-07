<?php
require 'config/db.php';
require 'includes/session.php';

// SECCIÓN BLOQUEADA: La capacitación no está disponible actualmente
//header('Location: index.php');
//exit();
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(2);
asegurar_columna_capacitacion_contabiliza($pdo);

$user_id_remitente = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'validar_cursos') {
        if (isset($_POST['seleccionados']) && is_array($_POST['seleccionados'])) {
            foreach ($_POST['seleccionados'] as $id) {
                $id = intval($id);

                // Validar el curso
                $stmt = $pdo->prepare("UPDATE capacitacion SET validado = 1, contabiliza_horas = 1 WHERE id = ?");
                $stmt->execute([$id]);

                // Recuperar el usuario_id del curso
                $stmtUser = $pdo->prepare("SELECT nombre_curso, user_id FROM capacitacion WHERE id = ?");
                $stmtUser->execute([$id]);
                $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $el_curso = $user['nombre_curso'];
                    $usuario_id = $user['user_id'];
                    $mensaje = "Tu constancia del curso de capacitación ".$el_curso." fue aceptada y sumará para la acreditación de las 40 h de capacitación anual";
                    $asunto = "Validación de Curso.";
                    $fecha = date('Y-m-d H:i:s');
                    $leido = 0;
                
                    $stmtMensaje = $pdo->prepare("INSERT INTO mensajes (remitente_id, destinatario_id, asunto, mensaje, fecha, leido) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtMensaje->execute([$user_id_remitente, $usuario_id, $asunto, $mensaje, $fecha, $leido]);
                

                }
            }
        }
    }

    header("Location: admin_capacitacion.php?info=6");
    exit;
?>

