<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Minutos sin entrar a la web antes de cerrar la sesión
define('TIEMPO_SESION', 5 * 60);

if (!empty($_SESSION['id_usuario'])) {
    $ultima = $_SESSION['ultima_actividad'] ?? time();
    if (time() - $ultima > TIEMPO_SESION) {
        $_SESSION = []; // sesión vencida: se cierra
    } else {
        $_SESSION['ultima_actividad'] = time(); // sigue activa: reinicia el contador
    }
}

$host = 'localhost';
$db   = 'marvel';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo conectar a la base de datos.']);
    exit;
}

function responder($ok, $mensaje, $extra = []) {
    echo json_encode(array_merge(['ok' => $ok, 'mensaje' => $mensaje], $extra));
    exit;
}

// Si se abre este archivo directo en el navegador, sirve como prueba
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    responder(true, 'Conexión correcta a la base marvel.');
}