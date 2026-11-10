<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

$host = "localhost";
$user = "root";           
$pass = "";               
$db   = "marvel"; 

$conexion = new mysqli($host, $user, $pass, $db);
if ($conexion->connect_error) {
    echo json_encode(["error" => "Fallo de conexión al Helicarrier."]);
    exit;
}
$conexion->set_charset("utf8");

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["error" => "Señor, debe iniciar sesión para sincronizar la tienda."]);
    exit;
}

$id_usuario = intval($_SESSION['id_usuario']);

// 1. Obtener balance del usuario
$consulta_user = $conexion->query("SELECT monedas_marvel FROM usuarios WHERE id_usuario = $id_usuario");
$user_data = $consulta_user->fetch_assoc();
$saldo = isset($user_data['monedas_marvel']) ? $user_data['monedas_marvel'] : 0;

// 2. Traer el catálogo global usando solo las columnas seguras de fábrica
$consulta_items = $conexion->query("SELECT id_item, nombre, categoria, precio_monedas FROM tienda_cosmeticos ORDER BY id_item ASC");

$productos = [];
if ($consulta_items) {
    while ($row = $consulta_items->fetch_assoc()) {
        $productos[] = [
            "id_item" => intval($row['id_item']),
            "nombre" => $row['nombre'],
            "categoria" => $row['categoria'],
            "precio_monedas" => intval($row['precio_monedas'])
        ];
    }
}

echo json_encode([
    "saldo_stark" => $saldo,
    "productos" => $productos
]);

$conexion->close();
?>
