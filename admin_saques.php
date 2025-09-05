<?php
session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

require 'db.php';

// Criar tabela de saques se não existir
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS saques (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        chave_pix VARCHAR(255) NOT NULL,
        tipo_chave ENUM('cpf', 'email', 'telefone', 'aleatoria') DEFAULT 'cpf',
        status ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente',
        data_solicitacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_processamento TIMESTAMP NULL,
        observacoes TEXT,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    )");
} catch (PDOException $e) {
    // Tabela já existe ou erro na criação
}

// Buscar saques
$status_filter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = '';
$params = [];

if ($status_filter) {
    $where = "WHERE s.status = ?";
    $params = [$status_filter];
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM saques s $where");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("
        SELECT s.*, u.nome, u.email 
        FROM saques s 
        LEFT JOIN usuarios u ON s.usuario_id = u.id 
        $where 
        ORDER BY s.data_solicitacao DESC 
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $saques = $stmt->fetchAll();
} catch (PDOException $e) {
    $saques = [];
    $total = 0;
}

$total_pages = ceil($total / $limit);

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $saque_id = intval($_POST['saque_id'] ?? 0);
    
    try {
        switch ($action) {
            case 'aprovar':
                $stmt = $pdo->prepare("UPDATE saques SET status = 'aprovado', data_processamento = NOW() WHERE id = ?");
                $stmt->execute([$saque_id]);
                $_SESSION['success'] = 'Saque aprovado!';
                break;
                
            case 'rejeitar':
                $observacoes = $_POST['observacoes'] ?? '';
                
                // Buscar dados do saque para devolver o valor
                $stmt = $pdo->prepare("SELECT * FROM saques WHERE id = ?");
                $stmt->execute([$saque_id]);
                $saque = $stmt->fetch();
                
                if ($saque && $saque['status'] === 'pendente') {
                    // Rejeitar saque
                    $stmt = $pdo->prepare("UPDATE saques SET status = 'rejeitado', data_processamento = NOW(), observacoes = ? WHERE id = ?");
                    $stmt->execute([$observacoes, $saque_id]);
                    
                    // Devolver saldo ao usuário
                    $stmt = $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
                    $stmt->execute([$saque['valor'], $saque['usuario_id']]);
                    
                    $_SESSION['success'] = 'Saque rejeitado e valor devolvido ao usuário!';
                }
                break;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao processar ação: ' . $e->getMessage();
    }
    
    header('Location: admin_saques.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saques PIX - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0d1117;
            --bg-panel: #161b22;
            --primary-gold: #fbce00;
            --text-light: #ffffff;
            --text-muted: #8b949e;
            --radius: 12px;
            --transition: 0.3s ease;
            --border-panel: #21262d;
            --shadow-gold: 0 0 20px rgba(251, 206, 0, 0.3);
            --success-color: #22c55e;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
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
            box-shadow: 0 4px 16px rgba(251, 206, 0, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(251, 206, 0, 0.4);
        }
        .btn-secondary { 
            background: var(--bg-panel); 
            color: var(--text-light); 
            border: 1px solid var(--border-panel); 
        }
        .btn-success { background: var(--success-color); color: white; }
        .btn-danger { background: var(--error-color); color: white; }
        .btn-warning { background: var(--warning-color); color: white; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px;
        }

        h2 {
            font-size: 28px;
            color: var(--primary-gold);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

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

        .filters {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .select-input {
            padding: 12px 16px;
            background: var(--bg-dark);
            border: 1px solid var(--border-panel);
            border-radius: var(--radius);
            color: var(--text-light);
            transition: var(--transition);
        }

        .select-input:focus {
            outline: none;
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 3px rgba(251, 206, 0, 0.1);
        }

        .table-container {
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
            background: transparent;
        }

        .table th {
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

        .table th:first-child {
            border-radius: var(--radius) 0 0 var(--radius);
        }

        .table th:last-child {
            border-radius: 0 var(--radius) var(--radius) 0;
        }

        .table td {
            padding: 14px;
            color: var(--text-muted);
            vertical-align: middle;
            border: 1px solid var(--border-panel);
            border-top: none;
            font-size: 13px;
            background: var(--bg-panel);
        }

        .table td:first-child {
            border-radius: var(--radius) 0 0 var(--radius);
            border-left: 1px solid var(--border-panel);
        }

        .table td:last-child {
            border-radius: 0 var(--radius) var(--radius) 0;
            border-right: 1px solid var(--border-panel);
        }

        .table tr:hover {
            background: rgba(251, 206, 0, 0.05);
            transform: translateY(-1px);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .status-pendente { 
            background: rgba(251, 206, 0, 0.15); 
            color: var(--primary-gold); 
        }
        .status-aprovado { 
            background: rgba(34, 197, 94, 0.15); 
            color: #22c55e; 
        }
        .status-rejeitado { 
            background: rgba(239, 68, 68, 0.15); 
            color: #ef4444; 
        }

        .pix-info {
            background: var(--bg-dark);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            margin-top: 4px;
            border: 1px solid var(--border-panel);
        }

        /* Messages */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideInDown 0.4s ease;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error-color);
            color: var(--error-color);
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

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.show { display: flex; }

        .modal-content {
            background: var(--bg-panel);
            padding: 24px;
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
            margin: -24px -24px 20px -24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-light);
        }

        .form-input {
            width: 100%;
            padding: 12px;
            background: var(--bg-dark);
            border: 1px solid var(--border-panel);
            border-radius: var(--radius);
            color: var(--text-light);
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 3px rgba(251, 206, 0, 0.1);
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

            .container {
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .table {
                font-size: 11px;
            }

            .btn {
                padding: 6px 10px;
                font-size: 12px;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
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
            <a href="usuarios_admin.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span class="nav-text">Usuários</span>
            </a>
            <a href="premios_admin.php" class="nav-item">
                <i class="fas fa-gift"></i>
                <span class="nav-text">Prêmios</span>
            </a>
            <a href="admin_saques.php" class="nav-item active">
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

    <div class="container">
        <h2>
            <i class="fas fa-money-bill-wave"></i>
            Gerenciar Saques PIX
        </h2>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $_SESSION['success'] ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $_SESSION['error'] ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="stats-grid">
            <?php
            try {
                $pendentes = $pdo->query("SELECT COUNT(*) FROM saques WHERE status = 'pendente'")->fetchColumn();
                $aprovados = $pdo->query("SELECT COUNT(*) FROM saques WHERE status = 'aprovado'")->fetchColumn();
                $rejeitados = $pdo->query("SELECT COUNT(*) FROM saques WHERE status = 'rejeitado'")->fetchColumn();
                $total_valor = $pdo->query("SELECT SUM(valor) FROM saques WHERE status = 'aprovado'")->fetchColumn() ?: 0;
            } catch (PDOException $e) {
                $pendentes = $aprovados = $rejeitados = $total_valor = 0;
            }
            ?>
            <div class="stat-card">
                <div class="stat-value" style="color: #f59e0b;"><?= $pendentes ?></div>
                <div class="stat-label">Pendentes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #22c55e;"><?= $aprovados ?></div>
                <div class="stat-label">Aprovados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #ef4444;"><?= $rejeitados ?></div>
                <div class="stat-label">Rejeitados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ <?= number_format($total_valor, 2, ',', '.') ?></div>
                <div class="stat-label">Total Pago</div>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 20px; color: var(--primary-gold); display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-filter"></i> Filtros
            </h3>
            
            <div class="filters">
                <form method="GET" style="display: flex; gap: 16px; align-items: center;">
                    <select name="status" class="select-input">
                        <option value="">Todos os Status</option>
                        <option value="pendente" <?= $status_filter === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                        <option value="aprovado" <?= $status_filter === 'aprovado' ? 'selected' : '' ?>>Aprovados</option>
                        <option value="rejeitado" <?= $status_filter === 'rejeitado' ? 'selected' : '' ?>>Rejeitados</option>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        Filtrar
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 20px; color: var(--primary-gold); display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-table"></i> Lista de Saques PIX
            </h3>
            
            <?php if (empty($saques)): ?>
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                    <p>Nenhum saque registrado.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuário</th>
                                <th>Valor</th>
                                <th>Chave PIX</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($saques as $saque): ?>
                                <tr>
                                    <td><?= $saque['id'] ?></td>
                                    <td style="color: var(--text-light); font-weight: 600;">
                                        <?= htmlspecialchars($saque['nome'] ?? 'Usuário #' . $saque['usuario_id']) ?>
                                    </td>
                                    <td style="color: var(--primary-gold); font-weight: 700;">
                                        R$ <?= number_format($saque['valor'], 2, ',', '.') ?>
                                    </td>
                                    <td>
                                        <div style="color: var(--text-light);">
                                            <?= htmlspecialchars($saque['chave_pix']) ?>
                                        </div>
                                        <div class="pix-info">
                                            Tipo: <?= ucfirst($saque['tipo_chave']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                            $status = $saque['status'];
                                            $icon = $status === 'aprovado' ? 'check-circle' : ($status === 'pendente' ? 'clock' : 'times-circle');
                                        ?>
                                        <span class="status-badge status-<?= $status ?>">
                                            <i class="fas fa-<?= $icon ?>"></i>
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($saque['data_solicitacao'])) ?></td>
                                    <td>
                                        <?php if ($saque['status'] === 'pendente'): ?>
                                            <div style="display: flex; gap: 4px;">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="aprovar">
                                                    <input type="hidden" name="saque_id" value="<?= $saque['id'] ?>">
                                                    <button type="submit" 
                                                            class="btn btn-success btn-sm" 
                                                            onclick="return confirm('Aprovar saque? Confirme que o PIX foi enviado.')">
                                                        <i class="fas fa-check"></i>
                                                        Aprovar Saque
                                                    </button>
                                                </form>
                                                
                                                <button type="button" 
                                                        class="btn btn-danger btn-sm" 
                                                        onclick="rejectSaque(<?= $saque['id'] ?>)">
                                                    <i class="fas fa-times"></i>
                                                    Recusar
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style: italic;">
                                                <?= $saque['status'] === 'aprovado' ? 'Pago' : 'Rejeitado' ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Rejeitar Saque -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-times"></i>
                    Recusar Saque
                </h3>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="rejeitar">
                <input type="hidden" name="saque_id" id="rejectSaqueId">
                
                <div class="form-group">
                    <label class="form-label">Motivo da Recusa</label>
                    <textarea name="observacoes" 
                              class="form-input" 
                              rows="4" 
                              placeholder="Descreva o motivo da recusa..."
                              required></textarea>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i>
                        Recusar e Devolver Saldo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function rejectSaque(saqueId) {
            document.getElementById('rejectSaqueId').value = saqueId;
            document.getElementById('rejectModal').classList.add('show');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('show');
        }

        // Fechar modal ao clicar fora
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRejectModal();
            }
        });
    </script>
</body>
</html>
                        <tr>
                            <td><?= $saque['id'] ?></td>
                            <td style="color: var(--text-light); font-weight: 600;">
                                <?= htmlspecialchars($saque['nome'] ?? 'Usuário #' . $saque['usuario_id']) ?>
                            </td>
                            <td style="color: var(--primary-green); font-weight: 700;">
                                R$ <?= number_format($saque['valor'], 2, ',', '.') ?>
                            </td>
                            <td>
                                <div style="color: var(--text-light);">
                                    <?= htmlspecialchars($saque['chave_pix']) ?>
                                </div>
                                <div class="pix-info">
                                    Tipo: <?= ucfirst($saque['tipo_chave']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $saque['status'] ?>">
                                    <?= ucfirst($saque['status']) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($saque['data_solicitacao'])) ?></td>
                            <td>
                                <?php if ($saque['status'] === 'pendente'): ?>
                                    <div style="display: flex; gap: 4px;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="aprovar">
                                            <input type="hidden" name="saque_id" value="<?= $saque['id'] ?>">
                                            <button type="submit" 
                                                    class="btn btn-success btn-sm" 
                                                    onclick="return confirm('Aprovar saque? Confirme que o PIX foi enviado.')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        
                                        <button type="button" 
                                                class="btn btn-danger btn-sm" 
                                                onclick="rejectSaque(<?= $saque['id'] ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 12px;">
                                        <?= $saque['status'] === 'aprovado' ? 'Pago' : 'Rejeitado' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Rejeitar Saque -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px; color: var(--text-light);">
                <i class="fas fa-times"></i>
                Rejeitar Saque
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="rejeitar">
                <input type="hidden" name="saque_id" id="rejectSaqueId">
                
                <div class="form-group">
                    <label class="form-label">Motivo da Rejeição</label>
                    <textarea name="observacoes" 
                              class="form-input" 
                              rows="4" 
                              placeholder="Descreva o motivo da rejeição..."
                              required></textarea>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i>
                        Rejeitar e Devolver Saldo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function rejectSaque(saqueId) {
            document.getElementById('rejectSaqueId').value = saqueId;
            document.getElementById('rejectModal').classList.add('show');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('show');
        }

        // Fechar modal ao clicar fora
        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRejectModal();
            }
        });
    </script>
</body>
</html>