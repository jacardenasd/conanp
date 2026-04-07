<?php
require 'config/db.php';

$ruta_log_accesos = __DIR__ . '/includes/log_accesos.php';
if (is_file($ruta_log_accesos)) {
	require_once $ruta_log_accesos;
}

if (!function_exists('registrar_log_acceso')) {
	function registrar_log_acceso(PDO $pdo, $evento, $userId = null, $username = null, $resultado = 'OK', $detalle = null)
	{
		$descripcion = trim(($detalle ?? 'Evento de acceso') . ($username ? ' | usuario: ' . $username : ''));
		try {
			$stmtAuditoria = $pdo->prepare("INSERT INTO auditorias (user_id, tabla, accion, descripcion, fecha, usuario_id)
				VALUES (?, 'log_accesos_usuarios', ?, ?, NOW(), ?)");
			$stmtAuditoria->execute([
				$userId,
				strtoupper((string)$evento),
				substr($descripcion, 0, 255),
				$userId
			]);
		} catch (Throwable $e) {
			return;
		}
	}
}

session_start();

if (isset($_SESSION['user_id']) || isset($_SESSION['username'])) {
	registrar_log_acceso(
		$pdo,
		'logout',
		$_SESSION['user_id'] ?? null,
		$_SESSION['username'] ?? null,
		'OK',
		'Cierre de sesión'
	);
}

session_destroy();
header("Location: index.php");
exit();
?>