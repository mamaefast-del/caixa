<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int) $_GET['id'];
    $tabelas = [
        'rollover',
        'transacoes_pix',
        'saques',
    ];

    foreach ($tabelas as $tabela) {
        $stmt = $pdo->prepare("DELETE FROM $tabela WHERE usuario_id = ?");
        $stmt->execute([$id]);
    }
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: usuarios_admin.php');
exit;
