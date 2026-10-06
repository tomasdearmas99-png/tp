<?php
require __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(false, 'Método no permitido.');

$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $email === '' || $password === '')
    responder(false, 'Completá todos los campos.');
if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username))
    responder(false, 'El usuario debe tener 3 a 50 caracteres (letras, números o _).');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100)
    responder(false, 'El email no es válido.');
if (strlen($password) < 6)
    responder(false, 'La contraseña debe tener al menos 6 caracteres.');

$stmt = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE username = ? OR email = ?');
$stmt->execute([$username, $email]);
if ($stmt->fetch()) responder(false, 'Ese usuario o email ya está registrado.');

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO usuarios (username, email, password_hash) VALUES (?, ?, ?)');
$stmt->execute([$username, $email, $hash]);

session_regenerate_id(true);
$_SESSION['id_usuario'] = (int)$pdo->lastInsertId();
$_SESSION['username']   = $username;

responder(true, '¡Cuenta creada! Bienvenido, ' . $username . '.', ['username' => $username]);