<?php
session_start();
require 'db.php';
date_default_timezone_set('America/Sao_Paulo');

$mensagem = '';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
  header('Location: login.php');
  exit;
}

// Filtro status (opcional)
$status_filter = $_GET['status'] ?? '';
$data_inicial = $_GET['data_inicial'] ?? '';
$data_final = $_GET['data_final'] ?? '';


// Paginação simples
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$limite = 20;
$offset = ($pagina - 1) * $limite;

// Query base
// Filtra para listagem e estatísticas
$where = [];
$params = [];

if ($status_filter && in_array($status_filter, ['pendente', 'aprovado', 'cancelado'])) {
  $where[] = "status = ?";
  $params[] = $status_filter;
}

if ($data_inicial) {
  $where[] = "DATE(criado_em) >= ?";
  $params[] = $data_inicial;
}

if ($data_final) {
  $where[] = "DATE(criado_em) <= ?";
  $params[] = $data_final;
}

$where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Paginação
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM transacoes_pix $where_sql");
$stmtTotal->execute($params);
$total = $stmtTotal->fetchColumn();

$totalPaginas = ceil($total / $limite);

$stmt = $pdo->prepare("SELECT * FROM transacoes_pix $where_sql ORDER BY criado_em DESC LIMIT $limite OFFSET $offset");
$stmt->execute($params);
$transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas
$stmtStats = $pdo->prepare("SELECT status, COUNT(*) as total FROM transacoes_pix $where_sql GROUP BY status");
$stmtStats->execute($params);

$stats = ['aprovado' => 0, 'pendente' => 0, 'cancelado' => 0];
$totalGeral = 0;

while ($row = $stmtStats->fetch(PDO::FETCH_ASSOC)) {
  $status = strtolower($row['status']);
  $stats[$status] = (int)$row['total'];
  $totalGeral += (int)$row['total'];
}



// Calcular porcentagem de aprovação
$percentualAprovado = $totalGeral > 0 ? round(($stats['aprovado'] / $totalGeral) * 100, 2) : 0;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Transações PIX</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <style>
    :root {
      --bg-dark: #0d1117;
      --bg-panel: #161b22;
      --primary-gold: #fbce00;
      --text-light: #f0f6fc;
      --text-muted: #8b949e;
      --radius: 12px;
      --transition: 0.3s ease;
      --border-panel: #21262d;
      --shadow-gold: 0 0 20px rgba(251, 206, 0, 0.3);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #0d1117 0%, #161b22 100%);
      color: var(--text-light);
      min-height: 100vh;
      padding-top: 80px;
    }

    /* Header */
    .header {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 80px;
      background: rgba(13, 17, 23, 0.95);
      backdrop-filter: blur(10px);
      border-bottom: 2px solid var(--primary-gold);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 24px;
      z-index: 1000;
      box-shadow: var(--shadow-gold);
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 20px;
      font-weight: 700;
      color: var(--primary-gold);
      text-decoration: none;
      transition: var(--transition);
    }

    .logo:hover {
      transform: scale(1.05);
      text-shadow: 0 0 10px var(--primary-gold);
    }

    .logo i {
      font-size: 24px;
    }

    .nav-menu {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .nav-item {
      padding: 10px 16px;
      background: var(--bg-panel);
      border: 1px solid var(--border-panel);
      border-radius: var(--radius);
      text-decoration: none;
      color: var(--text-light);
      font-weight: 500;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 8px;
      position: relative;
      overflow: hidden;
    }

    .nav-item:hover {
      background: linear-gradient(135deg, var(--primary-gold), #f4c430);
      color: #000;
      transform: translateY(-2px);
      box-shadow: var(--shadow-gold);
    }

    .nav-item.active {
      background: linear-gradient(135deg, var(--primary-gold), #f4c430);
      color: #000;
      box-shadow: var(--shadow-gold);
    }

    .nav-text {
      display: inline;
    }

    .content {
      padding: 40px;
      max-width: 1400px;
      margin: 0 auto;
    }

    h2 {
      font-size: 28px;
      color: var(--primary-gold);
      margin-bottom: 30px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    /* Messages */
    .message {
      padding: 16px 20px;
      border-radius: var(--radius);
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 500;
      animation: slideInDown 0.4s ease;
    }

    .message.success {
      background: rgba(34, 197, 94, 0.15);
      border: 1px solid rgba(34, 197, 94, 0.3);
      color: #22c55e;
    }

    .message.error {
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #ef4444;
    }

    @keyframes slideInDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Cards */
    .card {
      background: var(--bg-panel);
      border: 1px solid var(--border-panel);
      border-radius: var(--radius);
      padding: 24px;
      margin-bottom: 24px;
      transition: var(--transition);
    }

    .card:hover {
      border-color: var(--primary-gold);
      box-shadow: var(--shadow-gold);
    }

    /* Forms */
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 16px;
      margin-bottom: 20px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    label {
      font-weight: 600;
      color: var(--text-light);
      font-size: 14px;
    }

    input, select {
      padding: 12px 16px;
      background: var(--bg-dark);
      border: 1px solid var(--border-panel);
      border-radius: var(--radius);
      color: var(--text-light);
      font-size: 14px;
      transition: var(--transition);
    }

    input:focus, select:focus {
      outline: none;
      border-color: var(--primary-gold);
      box-shadow: 0 0 0 3px rgba(251, 206, 0, 0.1);
    }

    input:hover, select:hover {
      border-color: var(--primary-gold);
    }

    /* Buttons */
    .btn {
      padding: 12px 20px;
      border: none;
      border-radius: var(--radius);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      font-size: 14px;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary-gold), #f4c430);
      color: #000;
      box-shadow: 0 2px 8px rgba(251, 206, 0, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(251, 206, 0, 0.4);
    }

    /* Tables */
    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0 8px;
      background: transparent;
    }

    thead tr th {
      padding: 14px;
      color: var(--primary-gold);
      font-weight: 700;
      text-align: left;
      background: var(--bg-panel);
      border: 1px solid var(--border-panel);
      user-select: none;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    thead tr th:first-child {
      border-radius: var(--radius) 0 0 var(--radius);
    }

    thead tr th:last-child {
      border-radius: 0 var(--radius) var(--radius) 0;
    }

    tbody tr {
      background: var(--bg-panel);
      transition: var(--transition);
    }

    tbody tr:hover {
      background: rgba(251, 206, 0, 0.05);
      transform: translateY(-1px);
    }

    tbody tr td {
      padding: 14px;
      color: var(--text-light);
      vertical-align: middle;
      border: 1px solid var(--border-panel);
      border-top: none;
      font-size: 13px;
    }

    tbody tr td:first-child {
      border-radius: var(--radius) 0 0 var(--radius);
      border-left: 1px solid var(--border-panel);
    }

    tbody tr td:last-child {
      border-radius: 0 var(--radius) var(--radius) 0;
      border-right: 1px solid var(--border-panel);
    }

    /* Status Colors */
    .status-aprovado {
      color: #22c55e;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .status-pendente {
      color: #f59e0b;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .status-cancelado {
      color: #ef4444;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    /* Stats Cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: var(--bg-panel);
      border: 1px solid var(--border-panel);
      border-radius: var(--radius);
      padding: 20px;
      text-align: center;
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--primary-gold), transparent);
      transition: var(--transition);
    }

    .stat-card:hover {
      border-color: var(--primary-gold);
      transform: translateY(-2px);
      box-shadow: var(--shadow-gold);
    }

    .stat-card:hover::before {
      left: 100%;
    }

    .stat-value {
      font-size: 24px;
      font-weight: 800;
      color: var(--primary-gold);
      margin-bottom: 4px;
    }

    .stat-label {
      font-size: 12px;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Pagination */
    .pagination {
      margin-top: 25px;
      text-align: center;
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 12px;
    }

    .pagination a {
      color: var(--primary-gold);
      text-decoration: none;
      padding: 8px 16px;
      border: 1px solid var(--border-panel);
      border-radius: var(--radius);
      background: var(--bg-panel);
      font-weight: 600;
      transition: var(--transition);
    }

    .pagination a:hover {
      background: var(--primary-gold);
      color: #000;
      transform: translateY(-1px);
    }

    .pagination span {
      color: var(--text-muted);
      font-weight: 500;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .header {
        padding: 0 16px;
      }

      .nav-text {
        display: none;
      }

      .nav-item {
        padding: 10px;
      }

      .content {
        padding: 20px;
      }

      .form-grid {
        grid-template-columns: 1fr;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      table {
        font-size: 12px;
      }

      .pagination {
        flex-wrap: wrap;
      }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <div class="header">
    <a href="painel_admin.php" class="logo">
      <i class="fas fa-crown"></i>
      <span>Admin Panel</span>
    </a>
    
    <nav class="nav-menu">
      <a href="painel_admin.php" class="nav-item">
        <i class="fas fa-tachometer-alt"></i>
        <span class="nav-text">Dashboard</span>
      </a>
      <a href="configuracoes_admin.php" class="nav-item">
        <i class="fas fa-cog"></i>
        <span class="nav-text">Configurações</span>
      </a>
      <a href="cores_admin.php" class="nav-item">
        <i class="fas fa-palette"></i>
        <span class="nav-text">Cores</span>
      </a>
      <a href="usuarios_admin.php" class="nav-item">
        <i class="fas fa-users"></i>
        <span class="nav-text">Usuários</span>
      </a>
      <a href="premios_admin.php" class="nav-item">
        <i class="fas fa-gift"></i>
        <span class="nav-text">Prêmios</span>
      </a>
      <a href="saques_admin.php" class="nav-item">
        <i class="fas fa-money-bill-wave"></i>
        <span class="nav-text">Saques</span>
      </a>
      <a href="saques_comissao_admin.php" class="nav-item">
        <i class="fas fa-percentage"></i>
        <span class="nav-text">Comissões</span>
      </a>
      <a href="gateways_admin.php" class="nav-item">
        <i class="fas fa-credit-card"></i>
        <span class="nav-text">Gateways</span>
      </a>
      <a href="pix_admin.php" class="nav-item <?= ($action !== 'dev_mode') ? 'active' : '' ?>">
        <i class="fas fa-exchange-alt"></i>
        <span class="nav-text">Transações</span>
      </a>
      <a href="?action=dev_mode" class="nav-item <?= ($action === 'dev_mode') ? 'active' : '' ?>" style="<?= ($action === 'dev_mode') ? '' : 'background: linear-gradient(135deg, #8b5cf6, #a855f7);' ?>">
        <i class="fas fa-code"></i>
        <span class="nav-text">Desenvolvedor</span>
      </a>
    </nav>
  </div>

  <div class="content">
    <h2>
      <i class="fas fa-exchange-alt"></i>
      Transações PIX
    </h2>

    <?php if ($mensagem): ?>
      <div class="message success"><?= $mensagem ?></div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card">
      <h3 style="margin-bottom: 20px; color: var(--primary-gold); display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-filter"></i> Filtros
      </h3>
      
      <div class="form-grid">
        <form method="GET" action="">
          <div class="form-group">
            <label for="status">Status:</label>
            <select name="status" id="status" onchange="this.form.submit()">
              <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>Todos</option>
              <option value="pendente" <?= $status_filter === 'pendente' ? 'selected' : '' ?>>Pendente</option>
              <option value="aprovado" <?= $status_filter === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
              <option value="cancelado" <?= $status_filter === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
            </select>
          </div>
        </form>

        <form method="GET" action="">
          <div class="form-group">
            <label for="data_inicial">Data Inicial:</label>
            <input type="date" name="data_inicial" id="data_inicial" value="<?= htmlspecialchars($_GET['data_inicial'] ?? '') ?>" onchange="this.form.submit()">
          </div>
        </form>

        <form method="GET" action="">
          <div class="form-group">
            <label for="data_final">Data Final:</label>
            <input type="date" name="data_final" id="data_final" value="<?= htmlspecialchars($_GET['data_final'] ?? '') ?>" onchange="this.form.submit()">
          </div>
        </form>
      </div>
    </div>

    <!-- Estatísticas -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value"><?= $totalGeral ?></div>
        <div class="stat-label">Total de Transações</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-value" style="color: #22c55e;"><?= $stats['aprovado'] ?></div>
        <div class="stat-label">Aprovadas</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-value" style="color: #f59e0b;"><?= $stats['pendente'] ?></div>
        <div class="stat-label">Pendentes</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-value" style="color: #ef4444;"><?= $stats['cancelado'] ?></div>
        <div class="stat-label">Canceladas</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-value" style="color: <?= $percentualAprovado >= 80 ? '#22c55e' : ($percentualAprovado >= 50 ? '#f59e0b' : '#ef4444') ?>"><?= $percentualAprovado ?>%</div>
        <div class="stat-label">Taxa de Aprovação</div>
      </div>
    </div>

    <!-- Debug Webhook -->
    <div class="card">
      <h3 style="margin-bottom: 20px; color: var(--primary-gold); display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-bug"></i> Debug Webhook
      </h3>
      
      <div style="display: flex; gap: 12px; margin-bottom: 20px;">
        <a href="?action=view_logs" class="btn btn-primary">
          <i class="fas fa-file-alt"></i> Ver Logs do Webhook
        </a>
        <a href="?action=test_webhook" class="btn btn-primary">
          <i class="fas fa-play"></i> Testar Webhook
        </a>
        <a href="?action=check_pending" class="btn btn-primary">
          <i class="fas fa-clock"></i> Verificar Pendentes
        </a>
        <a href="?action=check_user_balance" class="btn btn-primary">
          <i class="fas fa-user-check"></i> Verificar Saldo Usuário
        </a>
        <a href="debug_webhook.php" class="btn btn-primary">
          <i class="fas fa-cogs"></i> Debug Completo
        </a>
      </div>

      <?php
      $action = $_GET['action'] ?? '';
      
      if ($action === 'view_logs') {
        echo '<div style="background: #0d1117; padding: 20px; border-radius: 8px; max-height: 400px; overflow-y: auto;">';
        echo '<h4 style="color: var(--primary-gold); margin-bottom: 15px;">Últimos Logs do Webhook:</h4>';
        
        if (file_exists('log_webhook_expfypay.txt')) {
          $logs = file_get_contents('log_webhook_expfypay.txt');
          $lines = explode("\n", $logs);
          $recentLines = array_slice($lines, -50); // Últimas 50 linhas
          
          echo '<pre style="color: #f0f6fc; font-size: 12px; line-height: 1.4;">';
          foreach ($recentLines as $line) {
            if (trim($line)) {
              if (strpos($line, 'ERRO') !== false) {
                echo '<span style="color: #ef4444;">' . htmlspecialchars($line) . '</span>' . "\n";
              } elseif (strpos($line, 'SUCESSO') !== false) {
                echo '<span style="color: #22c55e;">' . htmlspecialchars($line) . '</span>' . "\n";
              } elseif (strpos($line, 'AVISO') !== false) {
                echo '<span style="color: #f59e0b;">' . htmlspecialchars($line) . '</span>' . "\n";
              } else {
                echo htmlspecialchars($line) . "\n";
              }
            }
          }
          echo '</pre>';
        } else {
          echo '<p style="color: var(--text-muted);">Nenhum log encontrado.</p>';
        }
        echo '</div>';
      }
      
      if ($action === 'check_pending') {
        echo '<div style="background: #0d1117; padding: 20px; border-radius: 8px;">';
        echo '<h4 style="color: var(--primary-gold); margin-bottom: 15px;">Transações Pendentes:</h4>';
        
        $stmtPending = $pdo->query("SELECT * FROM transacoes_pix WHERE LOWER(status) IN ('pendente', 'pending', 'aguardando') ORDER BY criado_em DESC LIMIT 10");
        $pending = $stmtPending->fetchAll();
        
        if (count($pending) > 0) {
          echo '<table style="width: 100%; font-size: 12px;">';
          echo '<tr style="background: var(--bg-panel);">';
          echo '<th style="padding: 8px; border: 1px solid var(--border-panel);">ID</th>';
          echo '<th style="padding: 8px; border: 1px solid var(--border-panel);">Usuário</th>';
          echo '<th style="padding: 8px; border: 1px solid var(--border-panel);">Valor</th>';
          echo '<th style="padding: 8px; border: 1px solid var(--border-panel);">External ID</th>';
          echo '<th style="padding: 8px; border: 1px solid var(--border-panel);">Criado em</th>';
          echo '</tr>';
          
          foreach ($pending as $p) {
            echo '<tr>';
            echo '<td style="padding: 8px; border: 1px solid var(--border-panel);">' . $p['id'] . '</td>';
            echo '<td style="padding: 8px; border: 1px solid var(--border-panel);">' . $p['usuario_id'] . '</td>';
            echo '<td style="padding: 8px; border: 1px solid var(--border-panel);">R$ ' . number_format($p['valor'], 2, ',', '.') . '</td>';
            echo '<td style="padding: 8px; border: 1px solid var(--border-panel); font-family: monospace;">' . $p['external_id'] . '</td>';
            echo '<td style="padding: 8px; border: 1px solid var(--border-panel);">' . date('d/m/Y H:i:s', strtotime($p['criado_em'])) . '</td>';
            echo '</tr>';
          }
          echo '</table>';
        } else {
          echo '<p style="color: #22c55e;">✓ Nenhuma transação pendente encontrada.</p>';
        }
        echo '</div>';
      }
      
      if ($action === 'test_webhook') {
        echo '<div style="background: #0d1117; padding: 20px; border-radius: 8px;">';
        echo '<h4 style="color: var(--primary-gold); margin-bottom: 15px;">Teste de Webhook:</h4>';
        echo '<p style="color: var(--text-muted); margin-bottom: 15px;">Use este formulário para testar o webhook com dados simulados:</p>';
        
        // Mostrar mensagem se houver
        if (isset($_GET['msg']) && isset($_GET['type'])) {
          $msg = htmlspecialchars($_GET['msg']);
          $type = $_GET['type'];
          $color = $type === 'success' ? '#22c55e' : '#ef4444';
          echo '<div style="background: rgba(' . ($type === 'success' ? '34, 197, 94' : '239, 68, 68') . ', 0.15); border: 1px solid rgba(' . ($type === 'success' ? '34, 197, 94' : '239, 68, 68') . ', 0.3); color: ' . $color . '; padding: 12px; border-radius: 8px; margin-bottom: 15px;">';
          echo '<i class="fas fa-' . ($type === 'success' ? 'check-circle' : 'exclamation-circle') . '"></i> ' . $msg;
          echo '</div>';
        }
        
        echo '<form method="POST" action="test_webhook.php" style="display: grid; gap: 12px; max-width: 400px;">';
        echo '<div class="form-group">';
        echo '<label>Transaction ID:</label>';
        echo '<input type="text" name="transaction_id" value="TEST_' . time() . '" required>';
        echo '</div>';
        echo '<div class="form-group">';
        echo '<label>External ID:</label>';
        echo '<input type="text" name="external_id" value="EXT_' . time() . '" required>';
        echo '</div>';
        echo '<div class="form-group">';
        echo '<label>Valor:</label>';
        echo '<input type="number" name="amount" value="10.00" step="0.01" required>';
        echo '</div>';
        echo '<button type="submit" class="btn btn-primary">';
        echo '<i class="fas fa-paper-plane"></i> Enviar Teste';
        echo '</button>';
        echo '</form>';
        echo '</div>';
      }
      
      if ($action === 'check_user_balance') {
        echo '<div style="background: #0d1117; padding: 20px; border-radius: 8px;">';
        echo '<h4 style="color: var(--primary-gold); margin-bottom: 15px;">Verificar Saldo de Usuário:</h4>';
        
        if ($_POST['user_id'] ?? false) {
          $user_id = intval($_POST['user_id']);
          
          $stmtUser = $pdo->prepare("SELECT id, nome, email, saldo, comissao FROM usuarios WHERE id = ?");
          $stmtUser->execute([$user_id]);
          $user = $stmtUser->fetch();
          
          if ($user) {
            echo '<div style="background: var(--bg-panel); padding: 15px; border-radius: 8px; margin-bottom: 15px;">';
            echo '<h5 style="color: var(--primary-gold); margin-bottom: 10px;">Dados do Usuário:</h5>';
            echo '<p><strong>ID:</strong> ' . $user['id'] . '</p>';
            echo '<p><strong>Nome:</strong> ' . htmlspecialchars($user['nome']) . '</p>';
            echo '<p><strong>Email:</strong> ' . htmlspecialchars($user['email']) . '</p>';
            echo '<p><strong>Saldo:</strong> <span style="color: #22c55e; font-weight: bold;">R$ ' . number_format($user['saldo'], 2, ',', '.') . '</span></p>';
            echo '<p><strong>Comissão:</strong> R$ ' . number_format($user['comissao'], 2, ',', '.') . '</p>';
            echo '</div>';
            
            // Últimas transações do usuário
            $stmtTrans = $pdo->prepare("SELECT * FROM transacoes_pix WHERE usuario_id = ? ORDER BY criado_em DESC LIMIT 5");
            $stmtTrans->execute([$user_id]);
            $transacoes = $stmtTrans->fetchAll();
            
            if (count($transacoes) > 0) {
              echo '<h5 style="color: var(--primary-gold); margin-bottom: 10px;">Últimas 5 Transações:</h5>';
              echo '<table style="width: 100%; font-size: 12px;">';
              echo '<tr style="background: var(--bg-panel);">';
              echo '<th style="padding: 8px; border: 1px solid var(--border-panel);">ID</th>';
              echo '<th style="padding: 8px; border: 1px solid var(--border-panel);">Valor</th>';
              echo '<th style="padding: 8px; border: 1px solid var(--border-panel);">Status</th>';
              echo '<th style="padding: 8px; border: 1px solid var(--border-panel);">Criado em</th>';
              echo '</tr>';
              
              foreach ($transacoes as $t) {
                $statusColor = $t['status'] === 'aprovado' ? '#22c55e' : ($t['status'] === 'pendente' ? '#f59e0b' : '#ef4444');
                echo '<tr>';
                echo '<td style="padding: 8px; border: 1px solid var(--border-panel);">' . $t['id'] . '</td>';
                echo '<td style="padding: 8px; border: 1px solid var(--border-panel);">R$ ' . number_format($t['valor'], 2, ',', '.') . '</td>';
                echo '<td style="padding: 8px; border: 1px solid var(--border-panel); color: ' . $statusColor . ';">' . ucfirst($t['status']) . '</td>';
                echo '<td style="padding: 8px; border: 1px solid var(--border-panel);">' . date('d/m/Y H:i:s', strtotime($t['criado_em'])) . '</td>';
                echo '</tr>';
              }
              echo '</table>';
            }
          } else {
            echo '<p style="color: #ef4444;">Usuário não encontrado.</p>';
          }
        }
        
        echo '<form method="POST" style="display: flex; gap: 12px; align-items: end; margin-top: 15px;">';
        echo '<div class="form-group" style="margin: 0;">';
        echo '<label>ID do Usuário:</label>';
        echo '<input type="number" name="user_id" placeholder="Digite o ID do usuário" required>';
        echo '</div>';
        echo '<button type="submit" class="btn btn-primary">';
        echo '<i class="fas fa-search"></i> Verificar';
        echo '</button>';
        echo '</form>';
        echo '</div>';
      }
      
      if ($action === 'dev_mode') {
        // Verificar senha do desenvolvedor
        $dev_password = $_POST['dev_password'] ?? '';
        $dev_mode_active = false;
        
        if ($dev_password) {
          // Buscar senha no banco (só o dono do código saberá)
          $stmtDev = $pdo->prepare("SELECT valor FROM dev_config WHERE chave = 'dev_password' LIMIT 1");
          $stmtDev->execute();
          $devConfig = $stmtDev->fetch();
          
          if ($devConfig && password_verify($dev_password, $devConfig['valor'])) {
            $dev_mode_active = true;
          } else {
            echo '<div class="message error">';
            echo '<i class="fas fa-exclamation-triangle"></i> Senha incorreta!';
            echo '</div>';
          }
        }
        
        if (!$dev_mode_active) {
          // Formulário de senha
          echo '<div style="background: #0d1117; padding: 40px; border-radius: 8px; text-align: center;">';
          echo '<h3 style="color: var(--primary-gold); margin-bottom: 20px;">';
          echo '<i class="fas fa-lock"></i> Modo Desenvolvedor';
          echo '</h3>';
          echo '<p style="color: var(--text-muted); margin-bottom: 30px;">';
          echo 'Digite a senha de desenvolvedor para acessar as configurações avançadas.';
          echo '</p>';
          
          echo '<form method="POST" style="max-width: 300px; margin: 0 auto;">';
          echo '<div class="form-group">';
          echo '<label>Senha de Desenvolvedor:</label>';
          echo '<input type="password" name="dev_password" placeholder="Digite a senha" required>';
          echo '</div>';
          echo '<button type="submit" class="btn btn-primary" style="width: 100%;">';
          echo '<i class="fas fa-unlock"></i> Acessar';
          echo '</button>';
          echo '</form>';
          echo '</div>';
        } else {
          // Modo desenvolvedor ativo
          echo '<div style="background: #0d1117; padding: 40px; border-radius: 8px;">';
          echo '<h3 style="color: #8b5cf6; margin-bottom: 20px;">';
          echo '<i class="fas fa-code"></i> Modo Desenvolvedor - ATIVO';
          echo '</h3>';
          
          // Formulário para adicionar splits
          if ($_POST['action'] === 'add_split') {
            $email = trim($_POST['email'] ?? '');
            $percentage = floatval($_POST['percentage'] ?? 0);
            
            if ($email && $percentage > 0 && $percentage <= 100) {
              // Adicionar split na configuração
              $splitData = [
                'email' => $email,
                'percentage' => $percentage
              ];
              
                             // Buscar splits existentes
               $stmtSplits = $pdo->prepare("SELECT valor FROM dev_config WHERE chave = 'splits_config' LIMIT 1");
               $stmtSplits->execute();
               $splitsConfig = $stmtSplits->fetch();
              
              $splits = [];
              if ($splitsConfig) {
                $splits = json_decode($splitsConfig['valor'], true) ?: [];
              }
              
              $splits[] = $splitData;
              
                             // Salvar no banco
               if ($splitsConfig) {
                 $stmtUpdate = $pdo->prepare("UPDATE dev_config SET valor = ? WHERE chave = 'splits_config'");
                 $stmtUpdate->execute([json_encode($splits)]);
               } else {
                 $stmtInsert = $pdo->prepare("INSERT INTO dev_config (chave, valor) VALUES ('splits_config', ?)");
                 $stmtInsert->execute([json_encode($splits)]);
               }
              
              echo '<div class="message success">';
              echo '<i class="fas fa-check-circle"></i> Split adicionado com sucesso!';
              echo '</div>';
            } else {
              echo '<div class="message error">';
              echo '<i class="fas fa-exclamation-triangle"></i> Dados inválidos!';
              echo '</div>';
            }
          }
          
                     // Listar splits existentes
           $stmtSplits = $pdo->prepare("SELECT valor FROM dev_config WHERE chave = 'splits_config' LIMIT 1");
           $stmtSplits->execute();
           $splitsConfig = $stmtSplits->fetch();
          
          if ($splitsConfig) {
            $splits = json_decode($splitsConfig['valor'], true) ?: [];
            if (!empty($splits)) {
              echo '<div style="background: var(--bg-panel); padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
              echo '<h4 style="color: var(--primary-gold); margin-bottom: 15px;">Splits Configurados:</h4>';
              echo '<table style="width: 100%; font-size: 12px;">';
              echo '<tr style="background: var(--bg-dark);">';
              echo '<th style="padding: 8px; border: 1px solid var(--border-panel);">Email</th>';
              echo '<th style="padding: 8px; border: 1px solid var(--border-panel);">Percentual</th>';
              echo '</tr>';
              
              foreach ($splits as $split) {
                echo '<tr>';
                echo '<td style="padding: 8px; border: 1px solid var(--border-panel);">' . htmlspecialchars($split['email']) . '</td>';
                echo '<td style="padding: 8px; border: 1px solid var(--border-panel);">' . $split['percentage'] . '%</td>';
                echo '</tr>';
              }
              echo '</table>';
              echo '</div>';
            }
          }
          
          // Formulário para adicionar novo split
          echo '<form method="POST" style="display: grid; gap: 16px; max-width: 400px;">';
          echo '<input type="hidden" name="action" value="add_split">';
          
          echo '<div class="form-group">';
          echo '<label>Email do Parceiro:</label>';
          echo '<input type="email" name="email" placeholder="parceiro@exemplo.com" required>';
          echo '</div>';
          
          echo '<div class="form-group">';
          echo '<label>Percentual (%):</label>';
          echo '<input type="number" name="percentage" step="0.1" min="0.1" max="100" placeholder="10.5" required>';
          echo '</div>';
          
          echo '<button type="submit" class="btn btn-primary">';
          echo '<i class="fas fa-plus"></i> Adicionar Split';
          echo '</button>';
          echo '</form>';
          
          echo '</div>';
        }
      }
      ?>
    </div>

    <!-- Tabela de Transações -->
    <div class="card">
      <h3 style="margin-bottom: 20px; color: var(--primary-gold); display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-table"></i> Lista de Transações
      </h3>
      
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Usuário</th>
          <th>Telefone</th>
          <th>Valor (R$)</th>
          <th>External ID</th>
          <th>Status</th>
          <th>Criado em</th>
          <th>Transaction ID</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($transacoes) === 0): ?>
          <tr><td colspan="8" style="text-align:center; color: var(--text-muted);">Nenhuma transação encontrada.</td></tr>
        <?php else: ?>
          <?php foreach ($transacoes as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t['id']) ?></td>
              <td><?= htmlspecialchars($t['usuario_id']) ?></td>
              <td><?= htmlspecialchars($t['telefone']) ?></td>
              <td><?= number_format($t['valor'], 2, ',', '.') ?></td>
              <td><?= htmlspecialchars($t['external_id']) ?></td>
              <?php
                $status = htmlspecialchars($t['status']);
                $classeStatus = 'status-' . strtolower($status);
                $iconStatus = $status === 'aprovado' ? 'check-circle' : ($status === 'pendente' ? 'clock' : 'times-circle');
              ?>
              <td class="<?= $classeStatus ?>" style="text-transform: capitalize;">
                <i class="fas fa-<?= $iconStatus ?>"></i>
                <?= $status ?>
              </td>

              <?php
                $dt = new DateTime($t['criado_em'], new DateTimeZone('UTC'));
                $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
              ?>
              <td><?= $dt->format('d/m/Y H:i:s') ?></td>

              <td><?= htmlspecialchars($t['transaction_id']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    </div>

    <!-- Paginação -->
    <div class="pagination">
      <?php
      $queryString = http_build_query([
        'status' => $status_filter,
        'data_inicial' => $data_inicial,
        'data_final' => $data_final
      ]);
      ?>

      <?php if ($pagina > 1): ?>
        <a href="?<?= $queryString ?>&pagina=<?= $pagina - 1 ?>">
          <i class="fas fa-chevron-left"></i> Anterior
        </a>
      <?php endif; ?>

      <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>

      <?php if ($pagina < $totalPaginas): ?>
        <a href="?<?= $queryString ?>&pagina=<?= $pagina + 1 ?>">
          Próximo <i class="fas fa-chevron-right"></i>
        </a>
      <?php endif; ?>

    </div>
  </div>
</body>
</html>
