<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
  header('Location: login.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $saldo = $_POST['saldo'];
    $percentual_ganho = $_POST['percentual_ganho'] ?? null;
    $usar_global = isset($_POST['usar_global']);
    $comissao = $_POST['comissao'] ?? 0;
    $conta_demo = isset($_POST['conta_demo']) ? 1 : 0;

    if ($usar_global) {
        $percentual_ganho = null;
    }

    $stmt = $pdo->prepare("UPDATE usuarios SET saldo = ?, percentual_ganho = ?, comissao = ?, conta_demo = ? WHERE id = ?");
    $stmt->execute([$saldo, $percentual_ganho, $comissao, $conta_demo, $id]);
}

header('Location: usuarios_admin.php');
exit;
?>