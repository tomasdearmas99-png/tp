<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Forzar que la respuesta del servidor sea interpretada como JSON limpio siempre
header('Content-Type: application/json; charset=utf-8');

// Desactivar impresión de advertencias que deformen la respuesta
error_reporting(0);
ini_set('display_errors', 0);

$host = "localhost";
$user = "root";           
$pass = "";               
$db   = "marvel"; 

$conexion = new mysqli($host, $user, $pass, $db);
if ($conexion->connect_error) {
    echo json_encode(["status" => "error", "mensaje" => "Error de conexión con la armería Stark."]);
    exit;
}
$conexion->set_charset("utf8");

// Validar inicio de sesión único
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["status" => "error", "mensaje" => "Señor, debe iniciar sesión en la portada para abrir un cofre."]);
    exit;
}

$id_usuario = intval($_SESSION['id_usuario']);

// Leer el tipo de cofre enviado por el botón de la interfaz
$tipo_cofre = isset($_POST['tipo_cofre']) ? $_POST['tipo_cofre'] : 'comun';

// Costos calibrados de los suministros
$costos = [
    'comun' => 100,
    'epico' => 300,
    'legendario' => 700
];
$costo_final = isset($costos[$tipo_cofre]) ? $costos[$tipo_cofre] : 100;

// 1. Obtener balance de monedas_marvel del usuario conectado
$consulta_user = $conexion->query("SELECT monedas_marvel FROM usuarios WHERE id_usuario = $id_usuario");
if (!$consulta_user) {
    echo json_encode(["status" => "error", "mensaje" => "Error: No se encontró el registro de monedas."]);
    exit;
}
$user_data = $consulta_user->fetch_assoc();

if ($user_data['monedas_marvel'] < $costo_final) {
    echo json_encode(["status" => "error", "mensaje" => "Monedas Marvel insuficientes para abrir este cofre."]);
    exit;
}

// 2. SISTEMA GACHA: Ruleta con tus nuevas probabilidades solicitadas
$suerte = rand(1, 100);
$rareza_obtenida = "Comun";

if ($tipo_cofre === 'comun') {
    // RECLUTA: 80% Común | 18% Épica | 2% Legendaria
    if ($suerte <= 80) $rareza_obtenida = "Comun";
    elseif ($suerte <= 98) $rareza_obtenida = "Epica";
    else $rareza_obtenida = "Legendaria";
    
} elseif ($tipo_cofre === 'epico') {
    // VENGADOR NUEVO: 60% Común | 35% Épica | 5% Legendaria
    if ($suerte <= 60) $rareza_obtenida = "Comun";
    elseif ($suerte <= 95) $rareza_obtenida = "Epica"; // 60 + 35 = 95
    else $rareza_obtenida = "Legendaria";
    
} elseif ($tipo_cofre === 'legendario') {
    // NEXO CÓSMICO NUEVO: 45% Común | 45% Épica | 10% Legendaria
    if ($suerte <= 45) $rareza_obtenida = "Comun";
    elseif ($suerte <= 90) $rareza_obtenida = "Epica"; // 45 + 45 = 90
    else $rareza_obtenida = "Legendaria";
}

// 3. Extraer una carta aleatoria que coincida exactamente con la rareza obtenida
$sql_carta = "SELECT id_carta, nombre_personaje, ataque, defensa, ruta_imagen_carta FROM cartas WHERE rareza = '$rareza_obtenida' ORDER BY RAND() LIMIT 1";
$res_carta = $conexion->query($sql_carta);

if (!$res_carta || $res_carta->num_rows == 0) {
    echo json_encode(["status" => "error", "mensaje" => "Bóveda vacía para la rareza: " . $rareza_obtenida]);
    exit;
}

$carta = $res_carta->fetch_assoc();
$id_carta = intval($carta['id_carta']);
$nombre = $carta['nombre_personaje'];
$ataque = $carta['ataque'];
$defensa = $carta['defensa'];
$ruta_img = $carta['ruta_imagen_carta'];

// 4. PROCESAMIENTO ATÓMICO: Descontar fondos y asignar carta al usuario único
$conexion->begin_transaction();

$update_saldo = $conexion->query("UPDATE usuarios SET monedas_marvel = monedas_marvel - $costo_final WHERE id_usuario = $id_usuario");
$insert_inventario = $conexion->query("INSERT INTO usuario_cartas (id_usuario, id_carta) VALUES ($id_usuario, $id_carta)");

if ($update_saldo && $insert_inventario) {
    $conexion->commit();
    
    // Retornamos la respuesta limpia hacia el JavaScript de la tienda
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
    echo json_encode(["status" => "error", "mensaje" => "Error de sincronización en las tablas del inventario."]);
}

$conexion->close();
?>
