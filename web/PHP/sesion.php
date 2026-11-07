<?php
require __DIR__ . '/conexion.php';

if (empty($_SESSION['id_usuario'])) {
    echo json_encode(['logueado' => false]);
    exit;
}

$stmt = $pdo->prepare('SELECT username, monedas_marvel FROM usuarios WHERE id_usuario = ?');
$stmt->execute([$_SESSION['id_usuario']]);
$u = $stmt->fetch();

if (!$u) {
    session_destroy();
    echo json_encode(['logueado' => false]);
    exit;
}

echo json_encode(['logueado' => true, 'username' => $u['username'], 'monedas' => (int)$u['monedas_marvel']]);