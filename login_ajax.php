<?php
session_start();
require 'db.php';

$telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
$senha = $_POST['senha'] ?? '';

if (empty($telefone) || empty($senha)) {
    echo "Preencha todos os campos.";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', '') = ?");
$stmt->execute([$telefone]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($senha, $usuario['senha'])) {
    echo "Telefone ou senha incorretos.";
} else {
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    echo "success";
}
