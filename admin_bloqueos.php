<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

if (!function_exists('asegurar_tabla_estatus_captura')) {
    function asegurar_tabla_estatus_captura(PDO $pdo): void {
        static $tabla_asegurada = false;
        if ($tabla_asegurada) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS estatus_captura_periodo (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            periodo INT NOT NULL,
            modulo VARCHAR(120) NOT NULL,
            bloqueado TINYINT(1) NOT NULL DEFAULT 0,
            actualizado_por INT NULL,
            fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_periodo_modulo (periodo, modulo),
            KEY idx_periodo (periodo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

        $pdo->exec($sql);
        $tabla_asegurada = true;
    }
}

if (!function_exists('guardar_bloqueo_por_periodo')) {
    function guardar_bloqueo_por_periodo($base_variable, $periodo, $bloqueado, $usuario_id = null): void {
        global $pdo;
        asegurar_tabla_estatus_captura($pdo);

        $periodo = (int)$periodo;
        $bloqueado = ((int)$bloqueado === 1) ? 1 : 0;
        $usuario_id = ($usuario_id !== null) ? (int)$usuario_id : null;

        $stmt = $pdo->prepare("INSERT INTO estatus_captura_periodo (periodo, modulo, bloqueado, actualizado_por)
                               VALUES (?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE
                                   bloqueado = VALUES(bloqueado),
                                   actualizado_por = VALUES(actualizado_por),
                                   fecha_actualizacion = CURRENT_TIMESTAMP");
        $stmt->execute([$periodo, $base_variable, $bloqueado, $usuario_id]);

        $nombre_variable = $base_variable . '_' . $periodo;
        $stmtLegacy = $pdo->prepare("INSERT INTO variables (nombre, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        $stmtLegacy->execute([$nombre_variable, (string)$bloqueado]);
    }
}

if (!function_exists('obtener_bloqueo_por_periodo')) {
    function obtener_bloqueo_por_periodo($base_variable, $periodo, $default = '0') {
        global $pdo;

        $periodo = (int)$periodo;
        asegurar_tabla_estatus_captura($pdo);

        $stmt = $pdo->prepare("SELECT bloqueado FROM estatus_captura_periodo WHERE periodo = ? AND modulo = ? LIMIT 1");
        $stmt->execute([$periodo, $base_variable]);
        $valor_tabla = $stmt->fetchColumn();

        if ($valor_tabla !== false && $valor_tabla !== null && $valor_tabla !== '') {
            return ((int)$valor_tabla === 1) ? '1' : '0';
        }

        $nombre_variable = $base_variable . '_' . $periodo;
        $valor = obtener_variable($nombre_variable);
        if ($valor === null || $valor === '') {
            $valor = $default;
        }

        return (string)$valor;
    }
}

if (!function_exists('configuracion_estatus_captura')) {
    function configuracion_estatus_captura() {
        return [
            [
                'base' => 'bloquear_capacitacion',
                'titulo' => 'Captura de Capacitacion',
                'descripcion' => 'Permite o bloquea la captura/edicion de cursos para el periodo seleccionado.',
                'default' => '0',
            ],
            [
                'base' => 'bloquear_metas_individuales',
                'titulo' => 'Captura de Metas Individuales',
                'descripcion' => 'Permite o bloquea alta/edicion/eliminacion de metas individuales del periodo.',
                'default' => '0',
            ],
            [
                'base' => 'bloquear_metas_colectivas',
                'titulo' => 'Captura de Metas Colectivas',
                'descripcion' => 'Permite o bloquea alta/edicion/eliminacion de metas colectivas del periodo.',
                'default' => '0',
            ],
            [
                'base' => 'bloquear_cedula_firmada',
                'titulo' => 'Descarga/Carga de Cedula Firmada',
                'descripcion' => 'Controla la disponibilidad del proceso de cedula firmada para el periodo.',
                'default' => '0',
            ],
        ];
    }
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(3);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');

$configBloqueos = configuracion_estatus_captura();

// Compatibilidad adicional para el flujo histórico de PDF de metas firmadas.
$configBloqueos[] = [
    'base' => 'bloquear_pdf_metas_individuales',
    'titulo' => 'Carga de PDF de Metas Individuales Firmadas',
    'descripcion' => 'Permite o bloquea la carga del PDF firmado en Mis metas individuales para el periodo.',
    'default' => '0',
];

asegurar_tabla_estatus_captura($pdo);

$stmt = $pdo->query("SELECT anio FROM periodos ORDER BY anio DESC");
$periodosDisponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
$periodoSeleccionado = isset($_GET['periodo']) ? (int)$_GET['periodo'] : (int)($_SESSION['periodo'] ?? date('Y'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_bloqueos'])) {
    $periodoSeleccionado = (int)($_POST['periodo_bloqueo'] ?? $periodoSeleccionado);
}

if (!in_array($periodoSeleccionado, array_map('intval', $periodosDisponibles), true)) {
    $periodoSeleccionado = (int)($_SESSION['periodo'] ?? date('Y'));
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_bloqueos'])) {
    $modulosProcesados = [];

    foreach ($configBloqueos as $bloqueo) {
        $base = $bloqueo['base'];
        $valor = isset($_POST[$base]) ? 1 : 0;
        guardar_bloqueo_por_periodo($base, $periodoSeleccionado, $valor, (int)($_SESSION['user_id'] ?? 0));
        $modulosProcesados[] = $base;
    }

    $placeholders = implode(',', array_fill(0, count($modulosProcesados), '?'));
    $params = array_merge([(int)$periodoSeleccionado], $modulosProcesados);
    $stmtVerificacion = $pdo->prepare("SELECT COUNT(*) FROM estatus_captura_periodo WHERE periodo = ? AND modulo IN ($placeholders)");
    $stmtVerificacion->execute($params);
    $totalGuardados = (int)$stmtVerificacion->fetchColumn();

    if ($totalGuardados === count($modulosProcesados)) {
        $msg = 'Bloqueos actualizados correctamente para el periodo ' . $periodoSeleccionado . '.';
    } else {
        $msg = 'Se intentó guardar, pero no se pudieron verificar todos los registros en estatus_captura_periodo para el periodo ' . $periodoSeleccionado . '.';
    }
}

$valoresActuales = [];
foreach ($configBloqueos as $bloqueo) {
    $base = $bloqueo['base'];
    $valorGuardado = obtener_bloqueo_por_periodo($base, $periodoSeleccionado, $bloqueo['default']);
    $valoresActuales[$base] = (string)$valorGuardado;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($nombre_sistema); ?></title>

    <link href="assets/fonts/inter/inter.css" rel="stylesheet" type="text/css">
    <link href="assets/icons/phosphor/styles.min.css" rel="stylesheet" type="text/css">
    <link href="assets/css/ltr/all.min.css" id="stylesheet" rel="stylesheet" type="text/css">

    <script src="assets/demo/demo_configurator.js"></script>
    <script src="assets/js/bootstrap/bootstrap.bundle.min.js"></script>
    <link href="assets/icons/icomoon/styles.min.css" rel="stylesheet" type="text/css">

    <script src="assets/js/jquery/jquery.min.js"></script>
    <script src="assets/js/app.js"></script>
</head>
<body>

<?php require_once('assets/main_navbar.php'); ?>

<div class="page-content">

<?php require_once('assets/main_navigation.php'); ?>

    <div class="content-wrapper">
        <div class="content-inner">

            <div class="page-header page-header-light shadow">
                <div class="page-header-content d-lg-flex border-top">
                    <div class="d-flex">
                        <div class="breadcrumb py-2">
                            <a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
                            <a href="#" class="breadcrumb-item">Administracion</a>
                            <span class="breadcrumb-item active">Bloqueos de Captura</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Bloqueos administrativos por periodo</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-3 text-muted">Usa este apartado para activar o desactivar bloqueos de carga/captura por periodo.</p>

                        <?php if ($msg !== ''): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
                        <?php endif; ?>

                        <form method="get" class="row g-3 mb-3">
                            <div class="col-md-4 col-sm-6">
                                <label class="form-label fw-semibold" for="periodo_bloqueo">Periodo a configurar</label>
                                <select class="form-select" id="periodo_bloqueo" name="periodo" onchange="this.form.submit()">
                                    <?php foreach ($periodosDisponibles as $anio): ?>
                                        <option value="<?php echo (int)$anio; ?>" <?php echo ((int)$anio === (int)$periodoSeleccionado) ? 'selected' : ''; ?>>
                                            <?php echo (int)$anio; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>

                        <form method="post">

                            <div class="row g-3">
                                <?php foreach ($configBloqueos as $bloqueo): ?>
                                    <?php
                                        $nombreCampo = $bloqueo['base'];
                                        $activado = ($valoresActuales[$nombreCampo] === '1');
                                    ?>
                                    <div class="col-12">
                                        <div class="border rounded p-3">
                                            <div class="form-check form-switch mb-2">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    id="<?php echo htmlspecialchars($nombreCampo . '_' . $periodoSeleccionado); ?>"
                                                    name="<?php echo htmlspecialchars($nombreCampo); ?>"
                                                    value="1"
                                                    <?php echo $activado ? 'checked' : ''; ?>
                                                >
                                                <label class="form-check-label fw-semibold" for="<?php echo htmlspecialchars($nombreCampo . '_' . $periodoSeleccionado); ?>">
                                                    <?php echo htmlspecialchars($bloqueo['titulo'] . ' ' . $periodoSeleccionado); ?>
                                                </label>
                                            </div>
                                            <small class="text-muted"><?php echo htmlspecialchars($bloqueo['descripcion']); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <input type="hidden" name="periodo_bloqueo" value="<?php echo (int)$periodoSeleccionado; ?>">

                            <div class="mt-3">
                                <button type="submit" name="guardar_bloqueos" value="1" class="btn btn-primary">Guardar bloqueos</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php require_once('assets/footer.php'); ?>
        </div>
    </div>
</div>

</body>
</html>
