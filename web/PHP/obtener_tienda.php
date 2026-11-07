<?php
// Indicar al navegador que la respuesta es JSON limpio
header('Content-Type: application/json; charset=utf-8');
session_start();

// Configuración directa de la conexión a la base de datos
$host = "localhost";
$user = "root";           
$pass = "";               
$db   = "marvel"; 

$conexion = new mysqli($host, $user, $pass, $db);

$saldo = 0;
$id_usuario = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;

// Consultar saldo usando tu columna real 'monedas_marvel'
if ($id_usuario > 0 && !$conexion->connect_error) {
    $conexion->set_charset("utf8");
    $consulta_saldo = $conexion->query("SELECT monedas_marvel FROM usuarios WHERE id_usuario = $id_usuario");
    if ($consulta_saldo && $consulta_saldo->num_rows > 0) {
        $res_saldo = $consulta_saldo->fetch_assoc();
        $saldo = intval($res_saldo['monedas_marvel']); // Cambiado a tu columna exacta de la BD
    }
}

// Inicializar el array de productos de forma correcta y limpia
$productos = array();

// Si la conexión a la base de datos funciona, traemos los artículos reales
if (!$conexion->connect_error) {
    $conexion->set_charset("utf8");
    $resultado = $conexion->query("SELECT id_item, nombre, precio_monedas, categoria FROM tienda_cosmeticos");
    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $productos[] = $fila; // Agrega correctamente cada fila al array
        }
    }
}

// Enviar la respuesta unificada final hacia JavaScript
echo json_encode([
    "saldo_stark" => $saldo,
    "productos" => $productos
]);

if (!$conexion->connect_error) {
    $conexion->close();
}
?>
