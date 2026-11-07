<?php
session_start();

// Configuración directa de la conexión
$host = "localhost";
$user = "root";           
$pass = "";               
$db   = "marvel"; 

$conexion = new mysqli($host, $user, $pass, $db);

if ($conexion->connect_error) {
    echo "Error de conexión a los servidores de Stark.";
    exit;
}
$conexion->set_charset("utf8");

if (!isset($_SESSION['id_usuario'])) {
    echo "Error: Debes iniciar sesión en la plataforma para adquirir cosméticos.";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_usuario = intval($_SESSION['id_usuario']);
    $id_item = isset($_POST['id_item']) ? intval($_POST['id_item']) : 0;

    if ($id_item === 0) {
        echo "Artículo inválido.";
        exit;
    }

    // 1. Obtener precio y datos del cosmético
    $consulta_item = $conexion->prepare("SELECT precio_monedas, nombre FROM tienda_cosmeticos WHERE id_item = ?");
    $consulta_item->bind_param("i", $id_item);
    $consulta_item->execute();
    $res_item = $consulta_item->get_result();

    if ($res_item->num_rows === 0) {
        echo "El artículo seleccionado no existe en Industrias Stark.";
        $consulta_item->close();
        exit;
    }
    
    $item = $res_item->fetch_assoc();
    $precio = intval($item['precio_monedas']);
    $nombre_item = $item['nombre'];
    $consulta_item->close();

    // 2. Verificar el saldo usando tu columna real 'monedas_marvel'
    $consulta_user = $conexion->prepare("SELECT monedas_marvel FROM usuarios WHERE id_usuario = ?");
    $consulta_user->bind_param("i", $id_usuario);
    $consulta_user->execute();
    $user = $consulta_user->get_result()->fetch_assoc();
    $consulta_user->close();

    if ($user['monedas_marvel'] < $precio) {
        echo "Monedas Marvel insuficientes para adquirir: " . $nombre_item;
        exit;
    }

    // 3. Verificar si ya tiene el cosmético comprado en su inventario
    $consulta_inv = $conexion->prepare("SELECT id_item FROM inventario_cosmeticos WHERE id_usuario = ? AND id_item = ?");
    $consulta_inv->bind_param("ii", $id_usuario, $id_item);
    $consulta_inv->execute();
    $res_inv = $consulta_inv->get_result();
    $consulta_inv->close();

    if ($res_inv->num_rows > 0) {
        echo "Ya has adquirido este cosmético anteriormente. Revisa tu perfil.";
        exit;
    }

    // 4. Procesar la compra (Restar monedas_marvel e insertar en inventario)
    $conexion->begin_transaction();

    // Cambiado a tu columna exacta 'monedas_marvel'
    $restar = $conexion->prepare("UPDATE usuarios SET monedas_marvel = monedas_marvel - ? WHERE id_usuario = ?");
    $restar->bind_param("ii", $precio, $id_usuario);
    
    $insertar = $conexion->prepare("INSERT INTO inventario_cosmeticos (id_usuario, id_item, equipado) VALUES (?, ?, 0)");
    $insertar->bind_param("ii", $id_usuario, $id_item);

    if ($restar->execute() && $insertar->execute()) {
        $conexion->commit();
        echo "¡Adquisición exitosa! Has obtenido: " . $nombre_item;
    } else {
        $conexion->rollback();
        echo "Error en los sistemas al procesar la transferencia.";
    }

    $restar->close();
    $insertar->close();
    $conexion->close();
}
?>
