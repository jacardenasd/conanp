<?php
require 'config/db.php';
require 'includes/session.php';
require 'includes/variables.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
checkLogin(2);

$version = obtener_variable('version');
$nombre_sistema = obtener_variable('nombre_sistema');
$ano = obtener_variable('ano_elaboracion');
$contacto = obtener_variable('correo_contacto');
$empresa = obtener_variable('empresa');


if (!isset($_POST['user_id'])) {
    echo "ID de usuario no proporcionado.";
    exit;
}

$user_id = $_POST['user_id'];

// Validar duplicados en username, RFC y CURP excluyendo el usuario actual
$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE (username = ? OR RFC = ? OR CURP = ?) AND user_id != ?");
$stmt->execute([$_POST['username'], $_POST['RFC'], $_POST['CURP'], $user_id]);
if ($stmt->fetchColumn() > 0) {
    echo "Error: Ya existe un usuario con el mismo username, RFC o CURP.";
    header("Location: admin_usuarios.php?error=repetido");
    exit;
}

// Obtener usuario actual para conservar la foto si no se reemplaza
$usuario_actual = $pdo->prepare("SELECT foto FROM usuarios WHERE user_id = ?");
$usuario_actual->execute([$user_id]);
$usuario = $usuario_actual->fetch();
$foto = $usuario['foto'];

// Procesar nueva foto si se sube
if (!empty($_FILES['foto']['name'])) {
    $foto = uniqid() . "_" . basename($_FILES['foto']['name']);
    move_uploaded_file($_FILES['foto']['tmp_name'], "fotos/" . $foto);
}

// Procesar contraseña (solo si se proporciona)
$password = null;
if (!empty($_POST['password'])) {
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
}

// Ejecutar UPDATE
$query = "UPDATE usuarios SET
    username = :username,
    nombre = :nombre,
    apellido_paterno = :apellido_paterno,
    apellido_materno = :apellido_materno,
    estatus = :estatus,
    RFC = :RFC,
    homoclave = :homoclave,
    puesto_nombre = :puesto_nombre,
    puesto_codigo = :puesto_codigo,
    puesto_nivel = :puesto_nivel,
    fecha_alta = :fecha_alta,
    CURP = :CURP,
    IDRUSP = :IDRUSP,
    sexo = :sexo,
    correo = :correo,
    tipo_usuario = :tipo_usuario,
    nivel_estudios = :nivel_estudios,
    temporal = :temporal,
    jefe_id = :jefe_id,
    unidad_id = :unidad_id,
    adscripcion_id = :adscripcion_id,
    permite_metas_colectivas = :permite_metas_colectivas,
    requiere_cambio_password = :requiere_cambio_password,
    role = :role,
    foto = :foto";

if ($password !== null) {
    $query .= ", password = :password";
}

$query .= " WHERE user_id = :user_id";

$stmt = $pdo->prepare($query);
$params = [
    ':username' => $_POST['username'],
    ':nombre' => $_POST['nombre'],
    ':apellido_paterno' => $_POST['apellido_paterno'],
    ':apellido_materno' => $_POST['apellido_materno'],
    ':estatus' => $_POST['estatus'],
    ':RFC' => $_POST['RFC'],
    ':homoclave' => $_POST['homoclave'],
    ':puesto_nombre' => $_POST['puesto_nombre'],
    ':puesto_codigo' => $_POST['puesto_codigo'],
    ':puesto_nivel' => $_POST['puesto_nivel'],
    ':fecha_alta' => $_POST['fecha_alta'],
    ':CURP' => $_POST['CURP'],
    ':IDRUSP' => $_POST['IDRUSP'],
    ':sexo' => $_POST['sexo'],
    ':correo' => $_POST['correo'],
    ':tipo_usuario' => $_POST['tipo_usuario'],
    ':nivel_estudios' => $_POST['nivel_estudios'],
    ':temporal' => $_POST['temporal'],
    ':jefe_id' => $_POST['jefe_id'] ?: null,
    ':unidad_id' => $_POST['unidad_id'],
    ':adscripcion_id' => $_POST['adscripcion_id'],
    ':permite_metas_colectivas' => $_POST['permite_metas_colectivas'],
    ':requiere_cambio_password' => $_POST['requiere_cambio_password'],
    ':role' => $_POST['role'],
    ':foto' => $foto,
    ':user_id' => $user_id
];

if ($password !== null) {
    $params[':password'] = $password;
}

$stmt->execute($params);

header("Location: usuarios_editar.php?user_id=$user_id&info=1");
exit;
?>
