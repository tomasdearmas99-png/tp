<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Configuración directa de la conexión
$host = "localhost";
$user = "root";           
$pass = "";               
$db   = "marvel"; 

$conexion = new mysqli($host, $user, $pass, $db);

if ($conexion->connect_error) {
    echo json_encode(["status" => "error", "mensaje" => "Error de conexión al Helicarrier."]);
    exit;
}
$conexion->set_charset("utf8");

// Validar que el usuario esté logueado
if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(["status" => "error", "mensaje" => "Debes iniciar sesión para reclamar tu recompensa."]);
    exit;
}

$id_usuario = intval($_SESSION['id_usuario']);
$recompensa_monedas = 150; // Cantidad de monedas diarias de regalo

// 1. Consultar cuándo fue el último reclamo del usuario actual
$consulta = $conexion->prepare("SELECT ultimo_reclamo FROM usuarios WHERE id_usuario = ?");
$consulta->bind_param("i", $id_usuario);
$consulta->execute();
$resultado = $consulta->get_result()->fetch_assoc();
$consulta->close();

$ultimo_reclamo = $resultado['ultimo_reclamo'];
$puede_reclamar = false;

if ($ultimo_reclamo === NULL) {
    // Es la primera vez en su vida que va a reclamar
    $puede_reclamar = true;
} else {
    // Calculamos la diferencia de tiempo entre el momento actual y el último reclamo
    $fecha_actual = new DateTime();
    $fecha_ultimo = new DateTime($ultimo_reclamo);
    $diferencia = $fecha_actual->diff($fecha_ultimo);
    
    // Si pasó más de 0 días (es decir, 1 o más) o la diferencia en horas es igual o mayor a 24
    if ($diferencia->days >= 1 || ($diferencia->h + ($diferencia->days * 24)) >= 24) {
        $puede_reclamar = true;
    }
}

// 2. Procesar el reclamo o rebotar la petición
if ($puede_reclamar) {
    $conexion->begin_transaction();

    // Sumamos las monedas y actualizamos la estampa de tiempo al segundo actual (NOW())
    $actualizar = $conexion->prepare("UPDATE usuarios SET monedas_marvel = monedas_marvel + ?, ultimo_reclamo = NOW() WHERE id_usuario = ?");
    $actualizar->bind_param("ii", $recompensa_monedas, $id_usuario);

    if ($actualizar->execute()) {
        $conexion->commit();
        // Actualizamos también la variable de sesión para que se refleje en la web de inmediato
        if (isset($_SESSION['monedas_marvel'])) {
            $_SESSION['monedas_marvel'] += $recompensa_monedas;
        }
        echo json_encode([
            "status" => "success",
            "mensaje" => "¡Excelente, Agente! Has recolectado tus 150 Monedas Marvel diarias de Industrias Stark."
        ]);
    } else {
        $conexion->rollback();
        echo json_encode(["status" => "error", "mensaje" => "Fallo crítico en los servidores de Hydra al procesar el premio."]);
    }
    $actualizar->close();
} else {
    // Calcular cuánto tiempo le falta para cumplir las 24 horas
    $tiempo_restante = 86400 - (time() - strtotime($ultimo_reclamo));
    $horas_falta = floor($tiempo_restante / 3600);
    $minutos_falta = floor(($tiempo_restante % 3600) / 60);

    echo json_encode([
        "status" => "bloqueado",
        "mensaje" => "S.H.I.E.L.D. detectó un reclamo reciente. Vuelve en un lapso de $horas_falta horas y $minutos_falta minutos."
    ]);
}

$conexion->close();
?>
