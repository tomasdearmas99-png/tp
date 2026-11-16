<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Forzar formato JSON plano estricto
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["status" => "error", "mensaje" => "Señor, debe iniciar sesión en la portada."]);
    exit;
}

$id_usuario = intval($_SESSION['id_usuario']);

// Liberamos el bloqueo del archivo de sesión inmediatamente para evitar colisiones
session_write_close();

$host = "localhost";
$user = "root";           
$pass = "";               
$db   = "marvel"; 

$conexion = new mysqli($host, $user, $pass, $db);
if ($conexion->connect_error) {
    echo json_encode(["status" => "error", "mensaje" => "Error de conexión con el Helicarrier."]);
    exit;
}
$conexion->set_charset("utf8");

$tipo_cofre = isset($_POST['tipo_cofre']) ? $_POST['tipo_cofre'] : 'comun';

$costos = [
    'comun' => 100,
    'epico' => 300,
    'legendario' => 700
];
$costo_final = isset($costos[$tipo_cofre]) ? $costos[$tipo_cofre] : 100;

// Validar saldo del usuario conectado
$consulta_user = $conexion->query("SELECT monedas_marvel FROM usuarios WHERE id_usuario = $id_usuario");
$user_data = $consulta_user->fetch_assoc();

if ($user_data['monedas_marvel'] < $costo_final) {
    echo json_encode(["status" => "error", "mensaje" => "Monedas Marvel insuficientes para abrir este cofre."]);
    exit;
}

// Ruleta Gacha Matemática limpia
$suerte = rand(1, 100);
$rareza_obtenida = "Comun";

if ($tipo_cofre === 'comun') {
    if ($suerte <= 80) $rareza_obtenida = "Comun";
    elseif ($suerte <= 98) $rareza_obtenida = "Epica";
    else $rareza_obtenida = "Legendaria";
} elseif ($tipo_cofre === 'epico') {
    if ($suerte <= 60) $rareza_obtenida = "Comun";
    elseif ($suerte <= 95) $rareza_obtenida = "Epica";
    else $rareza_obtenida = "Legendaria";
} elseif ($tipo_cofre === 'legendario') {
    if ($suerte <= 45) $rareza_obtenida = "Comun";
    elseif ($suerte <= 90) $rareza_obtenida = "Epica";
    else $rareza_obtenida = "Legendaria";
}

// Algoritmo de selección indexada veloz para MySQL
$count_query = $conexion->query("SELECT COUNT(*) AS total FROM cartas WHERE rareza = '$rareza_obtenida'");
$count_data = $count_query->fetch_assoc();
$total_cartas = intval($count_data['total']);

if ($total_cartas == 0) {
    echo json_encode(["status" => "error", "mensaje" => "Error cuántico: No hay cartas de rareza '$rareza_obtenida' registradas."]);
    exit;
}

$indice_aleatorio = rand(0, $total_cartas - 1);

$sql_carta = "SELECT id_carta, nombre_personaje, ataque, defensa, ruta_imagen_carta FROM cartas WHERE rareza = '$rareza_obtenida' LIMIT $indice_aleatorio, 1";
$res_carta = $conexion->query($sql_carta);

$carta = $res_carta->fetch_assoc();
$id_carta = intval($carta['id_carta']);
$nombre = $carta['nombre_personaje'];
$ataque = $carta['ataque'];
$defensa = $carta['defensa'];
$ruta_img = $carta['ruta_imagen_carta'];

// CORRECCIÓN PARA KNULL: Sincroniza la mayúscula si sale el ID de Knull de la ruleta
if ($id_carta === 209) {
    $ruta_img = '../../IM_CARTAS/knull.png';
}

// CORRECCIÓN PARA LOKI DIOS DE LAS HISTORIAS: Sincroniza la ruta exacta si sale de la ruleta
if ($id_carta === 211) {
    $ruta_img = '../../IM_CARTAS/loki_dios_historia.png';
}

// Procesar transacciones asíncronas seguras
$conexion->begin_transaction();
$update_saldo = $conexion->query("UPDATE usuarios SET monedas_marvel = monedas_marvel - $costo_final WHERE id_usuario = $id_usuario");

// CORRECCIÓN CLAVE: Usamos INSERT IGNORE para evitar caídas de red si te toca una carta repetida
$insert_inventario = $conexion->query("INSERT IGNORE INTO usuario_cartas (id_usuario, id_carta) VALUES ($id_usuario, $id_carta)");

if ($update_saldo && $insert_inventario) {
    $conexion->commit();
    echo json_encode([
        "status" => "success",
        "rareza" => $rareza_obtenida,
        "nombre" => $nombre,
        "ataque" => $ataque,
        "defensa" => $defensa,
        "ruta_imagen" => $ruta_img,
        "nuevo_saldo" => ($user_data['monedas_marvel'] - $costo_final)
    ]);
} else {
    $conexion->rollback();
    echo json_encode(["status" => "error", "mensaje" => "Fallo interno al registrar la recompensa."]);
}

$conexion->close();
?>
