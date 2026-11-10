<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Forzar respuesta de texto plano limpia para el alert de Javascript
header('Content-Type: text/plain; charset=utf-8');

// Desactivar reportes crudos para mantener la respuesta limpia
error_reporting(0);
ini_set('display_errors', 0);

$host = "localhost";
$user = "root";           
$pass = "";               
$db   = "marvel"; 

$conexion = new mysqli($host, $user, $pass, $db);
if ($conexion->connect_error) {
    echo "Error crítico: No se pudo conectar con la armería central Stark.";
    exit;
}
$conexion->set_charset("utf8");

if (!isset($_SESSION['id_usuario'])) {
    echo "Señor, debe iniciar sesión en la portada para poder comprar.";
    exit;
}

if (!isset($_POST['id_item'])) {
    echo "Error: No se recibió el ID del cosmético en la petición POST.";
    exit;
}

$id_usuario = intval($_SESSION['id_usuario']);
$id_item = intval($_POST['id_item']);

// 1. CORRECCIÓN CLAVE: Usamos COUNT(*) para evitar el error de columna 'id_inventario' desconocida
$verificar = $conexion->query("SELECT COUNT(*) AS total FROM inventario_cosmeticos WHERE id_usuario = $id_usuario AND id_item = $id_item");
$verificar_data = $verificar->fetch_assoc();

if (intval($verificar_data['total']) > 0) {
    echo "Señor, esta personalización ya se encuentra registrada en su inventario.";
    exit;
}

// 2. Obtener datos del catálogo
$consulta_item = $conexion->query("SELECT nombre, precio_monedas FROM tienda_cosmeticos WHERE id_item = $id_item");
if (!$consulta_item || $consulta_item->num_rows == 0) {
    echo "Error: El artículo seleccionado no existe en el catálogo global.";
    exit;
}
$item_data = $consulta_item->fetch_assoc();
$precio = intval($item_data['precio_monedas']);
$nombre_item = $item_data['nombre'];

// 3. Verificar tu saldo real en la columna monedas_marvel
$consulta_user = $conexion->query("SELECT monedas_marvel FROM usuarios WHERE id_usuario = $id_usuario");
$user_data = $consulta_user->fetch_assoc();

if ($user_data['monedas_marvel'] < $precio) {
    echo "Fondos Stark insuficientes. Necesitas " . $precio . " créditos para adquirir: " . $nombre_item;
    exit;
}

// 4. Procesar la compra
$conexion->begin_transaction();
$update_saldo = $conexion->query("UPDATE usuarios SET monedas_marvel = monedas_marvel - $precio WHERE id_usuario = $id_usuario");
$insert_item = $conexion->query("INSERT INTO inventario_cosmeticos (id_usuario, id_item, equipado) VALUES ($id_usuario, $id_item, 0)");

if ($update_saldo && $insert_item) {
    $conexion->commit();
    echo "¡Adquisición autorizada! '" . $nombre_item . "' se ha transferido a tu inventario.";
} else {
    $conexion->rollback();
    echo "Error interno al procesar el pago en las tablas.";
}

$conexion->close();
?>
