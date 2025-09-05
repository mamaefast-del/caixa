<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
  header('Location: login.php');
  exit;
}

$mensagem = '';

// Processar ações de usuários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $usuario_id = intval($_POST['usuario_id']);
        
        switch ($_POST['action']) {
            case 'update_user':
                $saldo = floatval($_POST['saldo']);
                $percentual_ganho = $_POST['percentual_ganho'] !== '' ? floatval($_POST['percentual_ganho']) : null;
                $usar_global = isset($_POST['usar_global']) ? 1 : 0;
                $conta_demo = isset($_POST['conta_demo']) ? 1 : 0;
                $comissao = floatval($_POST['comissao'] ?? 0);
                
                if ($usar_global) {
                    $percentual_ganho = null;
                }
                
                $stmt = $pdo->prepare("UPDATE usuarios SET saldo = ?, percentual_ganho = ?, conta_demo = ?, comissao = ? WHERE id = ?");
                if ($stmt->execute([$saldo, $percentual_ganho, $conta_demo, $comissao, $usuario_id])) {
                    $mensagem = "Usuário atualizado com sucesso!";
                } else {
                    $mensagem = "Erro ao atualizar usuário.";
                }
                break;
                
            case 'delete_user':
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                if ($stmt->execute([$usuario_id])) {
                    $mensagem = "Usuário excluído com sucesso!";
                } else {
                    $mensagem = "Erro ao excluir usuário.";
                }
                break;
        }
    }
}

$busca = trim($_GET['busca'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = '';
$params = [];

if ($busca !== '') {
    $where = "WHERE email LIKE ? OR telefone LIKE ? OR nome LIKE ?";
    $params = ["%$busca%", "%$busca%", "%$busca%"];
}

// Contar total de usuários
$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios $where");
$stmt->execute($params);
$total = $stmt->fetchColumn();

$total_pages = ceil($total / $limit);

// Buscar usuários com paginação
$stmt = $pdo->prepare("SELECT * FROM usuarios $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas gerais
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_usuarios,
        COUNT(CASE WHEN conta_demo = 1 THEN 1 END) as contas_demo,
        SUM(saldo) as saldo_total,
        AVG(saldo) as saldo_medio
    FROM usuarios
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Gerenciar Usuários</title>
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

    /* Search Form */
    .search-form {
      display: flex;
      gap: 16px;
      align-items: center;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }

    .search-input {
      flex: 1;
      min-width: 300px;
      padding: 12px 16px;
      background: var(--bg-dark);
      border: 1px solid var(--border-panel);
      border-radius: var(--radius);
      color: var(--text-light);
      font-size: 14px;
      transition: var(--transition);
    }

    .search-input:focus {
      outline: none;
      border-color: var(--primary-gold);
      box-shadow: 0 0 0 3px rgba(251, 206, 0, 0.1);
    }

    .search-input::placeholder {
      color: var(--text-muted);
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

    /* Form Elements */
    .form-input {
      padding: 8px 12px;
      background: var(--bg-dark);
      border: 1px solid var(--border-panel);
      border-radius: 6px;
      color: var(--text-light);
      font-size: 12px;
      transition: var(--transition);
      width: 80px;
    }

    .form-input:focus {
      outline: none;
      border-color: var(--primary-gold);
      box-shadow: 0 0 0 2px rgba(251, 206, 0, 0.1);
    }

    .form-input.wide {
      width: 120px;
    }

    .checkbox-group {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .checkbox-item {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
    }

    .checkbox-item input[type="checkbox"] {
      accent-color: var(--primary-gold);
      cursor: pointer;
    }

    .checkbox-item label {
      color: var(--text-muted);
      cursor: pointer;
      user-select: none;
    }

    /* Buttons */
    .btn {
      padding: 8px 16px;
      border: none;
      border-radius: var(--radius);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      font-size: 12px;
      margin: 2px;
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

    .btn-success {
      background: linear-gradient(135deg, #22c55e, #16a34a);
      color: white;
    }

    .btn-danger {
      background: linear-gradient(135deg, #ef4444, #dc2626);
      color: white;
    }

    /* Status badges */
    .status-demo {
      background: rgba(251, 206, 0, 0.15);
      color: var(--primary-gold);
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 10px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .status-normal {
      background: rgba(34, 197, 94, 0.15);
      color: #22c55e;
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 10px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
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

    /* Modal */
    .modal {
      display: none;
      position: fixed;
      z-index: 2000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(5px);
    }

    .modal-content {
      background: var(--bg-panel);
      margin: 10% auto;
      padding: 0;
      border: 2px solid var(--primary-gold);
      border-radius: var(--radius);
      width: 90%;
      max-width: 500px;
      box-shadow: var(--shadow-gold);
    }

    .modal-header {
      background: linear-gradient(135deg, var(--primary-gold), #f4c430);
      color: #000;
      padding: 20px;
      border-radius: var(--radius) var(--radius) 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .modal-header h3 {
      margin: 0;
      font-size: 18px;
      font-weight: 700;
    }

    .close {
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: #000;
      padding: 0;
      width: 30px;
      height: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: var(--transition);
    }

    .close:hover {
      background: rgba(0, 0, 0, 0.1);
    }

    .modal-body {
      padding: 24px;
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

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .search-form {
        flex-direction: column;
        align-items: stretch;
      }

      .search-input {
        min-width: auto;
      }

      table {
        font-size: 11px;
      }

      .form-input {
        width: 60px;
        font-size: 11px;
      }

      .btn {
        padding: 6px 10px;
        font-size: 11px;
      }

      .checkbox-item {
        font-size: 10px;
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
      <a href="usuarios_admin.php" class="nav-item active">
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
      <a href="pix_admin.php" class="nav-item">
        <i class="fas fa-exchange-alt"></i>
        <span class="nav-text">Transações</span>
      </a>
      <a href="afiliados_admin.php" class="nav-item">
        <i class="fas fa-handshake"></i>
        <span class="nav-text">Afiliados</span>
      </a>
    </nav>
  </div>

  <div class="content">
    <h2>
      <i class="fas fa-users"></i>
      Gerenciar Usuários
    </h2>

    <?php if ($mensagem): ?>
      <div class="message success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($mensagem) ?>
      </div>
    <?php endif; ?>

    <!-- Estatísticas -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value"><?= $stats['total_usuarios'] ?></div>
        <div class="stat-label">Total de Usuários</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-value" style="color: #f59e0b;"><?= $stats['contas_demo'] ?></div>
        <div class="stat-label">Contas Demo</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-value">R$ <?= number_format($stats['saldo_total'], 2, ',', '.') ?></div>
        <div class="stat-label">Saldo Total</div>
      </div>
      
      <div class="stat-card">
        <div class="stat-value">R$ <?= number_format($stats['saldo_medio'], 2, ',', '.') ?></div>
        <div class="stat-label">Saldo Médio</div>
      </div>
    </div>

    <!-- Busca -->
    <div class="card">
      <h3 style="margin-bottom: 20px; color: var(--primary-gold); display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-search"></i> Buscar Usuários
      </h3>
      
      <form method="GET" class="search-form">
        <input
          type="text"
          name="busca"
          class="search-input"
          placeholder="Buscar por email, telefone ou nome..."
          value="<?= htmlspecialchars($busca) ?>"
          autocomplete="off"
        />
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-search"></i>
          Buscar
        </button>
        <?php if ($busca): ?>
          <a href="usuarios_admin.php" class="btn btn-success">
            <i class="fas fa-times"></i>
            Limpar
          </a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Tabela de Usuários -->
    <div class="card">
      <h3 style="margin-bottom: 20px; color: var(--primary-gold); display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-table"></i> Lista de Usuários
        <?php if ($busca): ?>
          <span style="font-size: 14px; color: var(--text-muted);">(<?= $total ?> resultados para "<?= htmlspecialchars($busca) ?>")</span>
        <?php endif; ?>
      </h3>
      
      <?php if (empty($usuarios)): ?>
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
          <i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
          <p><?= $busca ? 'Nenhum usuário encontrado para esta busca.' : 'Nenhum usuário cadastrado.' ?></p>
        </div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Email</th>
              <th>Telefone</th>
              <th>Saldo</th>
              <th>Depósitos</th>
              <th>Saques</th>
              <th>% Ganho</th>
              <th>Indicações</th>
              <th>Comissão</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($usuarios as $u): ?>
              <?php
                // Buscar estatísticas do usuário
                $indicacoes = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE indicado_por = ?");
                $indicacoes->execute([$u['id']]);
                $indicados = $indicacoes->fetchColumn();

                $depositos = $pdo->prepare("SELECT SUM(valor) FROM transacoes_pix WHERE usuario_id = ? AND status = 'aprovado'");
                $depositos->execute([$u['id']]);
                $total_depositos = $depositos->fetchColumn() ?? 0;

                $saques = $pdo->prepare("SELECT SUM(valor) FROM saques WHERE usuario_id = ? AND status = 'aprovado'");
                $saques->execute([$u['id']]);
                $total_saques = $saques->fetchColumn() ?? 0;
              ?>
              <tr>
                <form method="POST">
                  <input type="hidden" name="action" value="update_user">
                  <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                  
                  <td><?= $u['id'] ?></td>
                  <td>
                    <strong><?= htmlspecialchars($u['email']) ?></strong>
                    <?php if ($u['nome']): ?>
                      <br><small style="color: var(--text-muted);"><?= htmlspecialchars($u['nome']) ?></small>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($u['telefone']) ?></td>
                  <td>
                    <input type="number" step="0.01" name="saldo" value="<?= $u['saldo'] ?>" class="form-input">
                  </td>
                  <td>R$ <?= number_format($total_depositos, 2, ',', '.') ?></td>
                  <td>R$ <?= number_format($total_saques, 2, ',', '.') ?></td>
                  <td>
                    <input
                      type="number"
                      step="0.1"
                      name="percentual_ganho"
                      value="<?= is_null($u['percentual_ganho']) ? '' : $u['percentual_ganho'] ?>"
                      class="form-input"
                      placeholder="Global"
                    />
                    <div class="checkbox-group">
                      <div class="checkbox-item">
                        <input type="checkbox" name="usar_global" value="1" <?= is_null($u['percentual_ganho']) ? 'checked' : '' ?> id="global_<?= $u['id'] ?>">
                        <label for="global_<?= $u['id'] ?>">Usar padrão</label>
                      </div>
                    </div>
                  </td>
                  <td><?= $indicados ?></td>
                  <td>
                    <input type="number" step="0.01" name="comissao" value="<?= $u['comissao'] ?? 0 ?>" class="form-input">
                  </td>
                  <td>
                    <div class="checkbox-group">
                      <div class="checkbox-item">
                        <input type="checkbox" name="conta_demo" value="1" <?= ($u['conta_demo'] ?? 0) ? 'checked' : '' ?> id="demo_<?= $u['id'] ?>">
                        <label for="demo_<?= $u['id'] ?>">Conta Demo</label>
                      </div>
                    </div>
                    <?php if ($u['conta_demo'] ?? 0): ?>
                      <span class="status-demo">
                        <i class="fas fa-star"></i>
                        Demo
                      </span>
                    <?php else: ?>
                      <span class="status-normal">
                        <i class="fas fa-user"></i>
                        Normal
                      </span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button type="submit" class="btn btn-success">
                      <i class="fas fa-save"></i>
                      Salvar
                    </button>
                </form>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars($u['email']) ?>')">
                      <i class="fas fa-trash"></i>
                      Excluir
                    </button>
                  </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
          <div class="pagination">
            <?php
            $query_params = ['busca' => $busca];
            $query_string = http_build_query(array_filter($query_params));
            ?>

            <?php if ($page > 1): ?>
              <a href="?<?= $query_string ?>&page=<?= $page - 1 ?>">
                <i class="fas fa-chevron-left"></i> Anterior
              </a>
            <?php endif; ?>

            <span>Página <?= $page ?> de <?= $total_pages ?></span>

            <?php if ($page < $total_pages): ?>
              <a href="?<?= $query_string ?>&page=<?= $page + 1 ?>">
                Próximo <i class="fas fa-chevron-right"></i>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Modal de Confirmação de Exclusão -->
  <div id="deleteModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-exclamation-triangle"></i> Confirmar Exclusão</h3>
        <button class="close" onclick="closeDeleteModal()">&times;</button>
      </div>
      <div class="modal-body">
        <p style="margin-bottom: 20px; color: var(--text-light);">
          Tem certeza que deseja excluir o usuário <strong id="userEmail"></strong>?
        </p>
        <p style="margin-bottom: 20px; color: #ef4444; font-size: 14px;">
          <i class="fas fa-warning"></i>
          Esta ação não pode ser desfeita!
        </p>
        
        <form method="POST" id="deleteForm">
          <input type="hidden" name="action" value="delete_user">
          <input type="hidden" name="usuario_id" id="deleteUserId">
          
          <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <button type="button" class="btn btn-success" onclick="closeDeleteModal()">
              <i class="fas fa-times"></i>
              Cancelar
            </button>
            <button type="submit" class="btn btn-danger">
              <i class="fas fa-trash"></i>
              Excluir Usuário
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    function confirmDelete(userId, userEmail) {
      document.getElementById('deleteUserId').value = userId;
      document.getElementById('userEmail').textContent = userEmail;
      document.getElementById('deleteModal').style.display = 'block';
    }

    function closeDeleteModal() {
      document.getElementById('deleteModal').style.display = 'none';
    }

    // Fechar modal ao clicar fora
    window.onclick = function(event) {
      const modal = document.getElementById('deleteModal');
      if (event.target === modal) {
        closeDeleteModal();
      }
    }

    // Gerenciar checkbox "usar padrão"
    document.addEventListener('DOMContentLoaded', function() {
      const globalCheckboxes = document.querySelectorAll('input[name="usar_global"]');
      
      globalCheckboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const percentualInput = row.querySelector('input[name="percentual_ganho"]');
        
        checkbox.addEventListener('change', function() {
          if (this.checked) {
            percentualInput.value = '';
            percentualInput.disabled = true;
            percentualInput.style.opacity = '0.5';
          } else {
            percentualInput.disabled = false;
            percentualInput.style.opacity = '1';
            percentualInput.focus();
          }
        });
        
        // Aplicar estado inicial
        if (checkbox.checked) {
          percentualInput.disabled = true;
          percentualInput.style.opacity = '0.5';
        }
      });
    });
  </script>
</body>
</html>