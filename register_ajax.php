<?php
session_start();
require 'db.php';

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';
$telefone = trim($_POST['telefone'] ?? '');
$codigo_convite = trim($_POST['codigo_convite'] ?? '');

if (empty($nome) || empty($email) || empty($senha) || empty($telefone)) {
    echo "Por favor, preencha todos os campos.";
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->rowCount() > 0) {
    echo "E-mail já cadastrado.";
    exit;
}

$indicador_id = null;
if (!empty($codigo_convite)) {
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE codigo_convite = ?");
    $stmt->execute([$codigo_convite]);
    $indicador = $stmt->fetch();
    if ($indicador) {
        $indicador_id = $indicador['id'];
    }
}

$hash = password_hash($senha, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, telefone, saldo, indicado_por) VALUES (?, ?, ?, ?, 0.00, ?)");
$stmt->execute([$nome, $email, $hash, $telefone, $indicador_id]);

$usuario_id = $pdo->lastInsertId();

function gerarCodigoConvite($pdo, $length = 8) {
    do {
        $codigo = strtoupper(substr(bin2hex(random_bytes(16)), 0, $length));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE codigo_convite = ?");
        $stmt->execute([$codigo]);
    } while ($stmt->fetchColumn() > 0);
    return $codigo;
}

$codigo_convite = gerarCodigoConvite($pdo);
$stmt = $pdo->prepare("UPDATE usuarios SET codigo_convite = ? WHERE id = ?");
$stmt->execute([$codigo_convite, $usuario_id]);

$_SESSION['usuario_id'] = $usuario_id;
$_SESSION['usuario_nome'] = $nome;

echo "success";
