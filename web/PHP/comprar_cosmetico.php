<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(0);
ini_set('display_errors', 0);

$host = "localhost";
$user = "root";           
$pass = "";               
$db   = "marvel"; 

$conexion = new mysqli($host, $user, $pass, $db);
if ($conexion->connect_error) {
    echo "Fallo de comunicación con la armería central.";
    exit;
}
$conexion->set_charset("utf8");

if (!isset($_SESSION['id_usuario'])) {
    echo "Protocolo denegado: Inicie sesión en el index.";
    exit;
}

if (!isset($_POST['id_item'])) {
    echo "Comando Stark corrupto.";
    exit;
}

$id_usuario = intval($_SESSION['id_usuario']);
$id_item = intval($_POST['id_item']);

// 1. Verificar si el usuario ya es dueño de esta personalización específica
$verificar_duplicado = $conexion->query("SELECT id_inventario FROM inventario_cosmeticos WHERE id_usuario = $id_usuario AND id_item = $id_item");
if ($verificar_duplicado && $verificar_duplicado->num_rows > 0) {
    echo "Señor, este cosmético ya se encuentra registrado en su inventario personal.";
    exit;
}

// 2. Extraer especificaciones técnicas de precio y nombre del producto
$consulta_item = $conexion->query("SELECT nombre, precio_monedas FROM tienda_cosmeticos WHERE id_item = $id_item");
if (!$consulta_item || $consulta_item->num_rows == 0) {
    echo "Artículo no identificado en los servidores Stark.";
    exit;
}
$item_data = $consulta_item->fetch_assoc();
$precio = intval($item_data['precio_monedas']);
$nombre_item = $item_data['nombre'];

// 3. Verificar solvencia de monedas_marvel
$consulta_user = $conexion->query("SELECT monedas_marvel FROM usuarios WHERE id_usuario = $id_usuario");
$user_data = $consulta_user->fetch_assoc();

if ($user_data['monedas_marvel'] < $precio) {
    echo "Fondos Stark insuficientes. Requiere " . $precio . " créditos.";
    exit;
}

// 4. TRANSACCIÓN ATÓMICA DE ADQUISICIÓN
$conexion->begin_transaction();

$restar_saldo = $conexion->query("UPDATE usuarios SET monedas_marvel = monedas_marvel - $precio WHERE id_usuario = $id_usuario");
$entregar_item = $conexion->query("INSERT INTO inventario_cosmeticos (id_usuario, id_item, equipado) VALUES ($id_usuario, $id_item, 0)");

if ($restar_saldo && $entregar_item) {
    $conexion->commit();
    echo "¡Adquisición autorizada! " . $nombre_item . " se ha transferido a tu inventario.";
} else {
    $conexion->rollback();
    echo "Fallo de encriptación al procesar el pago.";
}

$conexion->close();
?>
