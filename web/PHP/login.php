<?php
require __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(false, 'Método no permitido.');

$id       = trim($_POST['identificador'] ?? ''); // usuario o email
$password = $_POST['password'] ?? '';

if ($id === '' || $password === '') responder(false, 'Completá todos los campos.');

$stmt = $pdo->prepare('SELECT id_usuario, username, password_hash FROM usuarios WHERE username = ? OR email = ?');
$stmt->execute([$id, $id]);
$u = $stmt->fetch();

if (!$u || !password_verify($password, $u['password_hash']))
    responder(false, 'Usuario o contraseña incorrectos.');

session_regenerate_id(true);
$_SESSION['id_usuario'] = (int)$u['id_usuario'];
$_SESSION['username']   = $u['username'];

responder(true, 'Sesión iniciada.', ['username' => $u['username']]);