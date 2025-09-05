<?php
session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

require 'db.php';

// Buscar usuários
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = '';
$params = [];

if ($search) {
    $where = "WHERE nome LIKE ? OR email LIKE ? OR telefone LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios $where");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios $where ORDER BY data_cadastro DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    $usuarios = [];
    $total = 0;
}

$total_pages = ceil($total / $limit);

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);
    
    try {
        switch ($action) {
            case 'block':
                $stmt = $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['success'] = 'Usuário bloqueado com sucesso!';
                break;
                
            case 'unblock':
                $stmt = $pdo->prepare("UPDATE usuarios SET ativo = 1 WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['success'] = 'Usuário desbloqueado com sucesso!';
                break;
                
            case 'update_saldo':
                $novo_saldo = floatval($_POST['novo_saldo'] ?? 0);
                $stmt = $pdo->prepare("UPDATE usuarios SET saldo = ? WHERE id = ?");
                $stmt->execute([$novo_saldo, $user_id]);
                $_SESSION['success'] = 'Saldo atualizado com sucesso!';
                break;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao processar ação: ' . $e->getMessage();
    }
    
    header('Location: admin_usuarios.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0a0b0f;
            --bg-panel: #111318;
            --bg-card: #1a1d24;
            --primary-gold: #fbce00;
            --primary-green: #00d4aa;
            --text-light: #ffffff;
            --text-muted: #8b949e;
            --border-color: #21262d;
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
            background: var(--bg-dark);
            color: var(--text-light);
            min-height: 100vh;
        }

        .header {
            background: var(--bg-panel);
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            background: linear-gradient(135deg, var(--primary-gold), var(--primary-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-gold), var(--primary-green));
            color: #000;
            box-shadow: 0 4px 16px rgba(251, 206, 0, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(251, 206, 0, 0.4);
        }

        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-light);
            border: 1px solid var(--border-color);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-danger {
            background: var(--error-color);
            color: white;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .filters {
            background: var(--bg-panel);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 300px;
            padding: 12px 16px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-light);
        }

        .table-container {
            background: var(--bg-panel);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .table th {
            background: var(--bg-card);
            font-weight: 600;
            color: var(--text-light);
        }

        .table td {
            color: var(--text-muted);
        }

        .table tr:hover {
            background: rgba(0, 212, 170, 0.05);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: var(--success-color);
            color: white;
        }

        .status-blocked {
            background: var(--error-color);
            color: white;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }

        .pagination a {
            padding: 8px 12px;
            background: var(--bg-panel);
            color: var(--text-light);
            text-decoration: none;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .pagination a.active {
            background: var(--primary-green);
            color: #000;
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--bg-panel);
            padding: 24px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
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
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-light);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <i class="fas fa-users"></i>
            Gerenciar Usuários
        </h1>
        <a href="configuracoes_admin.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Voltar ao Painel
        </a>
    </div>

    <div class="container">
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

        <div class="filters">
            <form method="GET" style="display: flex; gap: 16px; align-items: center; flex: 1;">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="🔍 Buscar por nome, email ou telefone..." 
                       value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Buscar
                </button>
            </form>
            <div style="color: var(--text-muted);">
                Total: <?= number_format($total) ?> usuários
            </div>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Telefone</th>
                        <th>Saldo</th>
                        <th>Status</th>
                        <th>Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td style="color: var(--text-light); font-weight: 600;">
                                <?= htmlspecialchars($user['nome']) ?>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['telefone'] ?? '-') ?></td>
                            <td style="color: var(--primary-green); font-weight: 700;">
                                R$ <?= number_format($user['saldo'], 2, ',', '.') ?>
                            </td>
                            <td>
                                <?php if ($user['ativo']): ?>
                                    <span class="status-badge status-active">Ativo</span>
                                <?php else: ?>
                                    <span class="status-badge status-blocked">Bloqueado</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($user['data_cadastro'])) ?></td>
                            <td>
                                <div style="display: flex; gap: 4px;">
                                    <button type="button" 
                                            class="btn btn-primary btn-sm" 
                                            onclick="editSaldo(<?= $user['id'] ?>, <?= $user['saldo'] ?>)">
                                        <i class="fas fa-dollar-sign"></i>
                                    </button>
                                    
                                    <?php if ($user['ativo']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="block">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" 
                                                    class="btn btn-warning btn-sm" 
                                                    onclick="return confirm('Bloquear usuário?')">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="unblock">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" 
                                                    class="btn btn-success btn-sm" 
                                                    onclick="return confirm('Desbloquear usuário?')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" 
                       class="<?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Editar Saldo -->
    <div id="editSaldoModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px; color: var(--text-light);">
                <i class="fas fa-dollar-sign"></i>
                Editar Saldo
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_saldo">
                <input type="hidden" name="user_id" id="editUserId">
                
                <div class="form-group">
                    <label class="form-label">Novo Saldo (R$)</label>
                    <input type="number" 
                           name="novo_saldo" 
                           id="editSaldoInput" 
                           class="form-input" 
                           step="0.01" 
                           required>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditSaldoModal()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editSaldo(userId, currentSaldo) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editSaldoInput').value = currentSaldo;
            document.getElementById('editSaldoModal').classList.add('show');
        }

        function closeEditSaldoModal() {
            document.getElementById('editSaldoModal').classList.remove('show');
        }

        // Fechar modal ao clicar fora
        document.getElementById('editSaldoModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditSaldoModal();
            }
        });
    </script>
</body>
</html>