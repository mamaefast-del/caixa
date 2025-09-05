<?php
session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

require 'db.php';

// Criar tabela de afiliados se não existir
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS afiliados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        codigo_afiliado VARCHAR(20) UNIQUE NOT NULL,
        total_indicados INT DEFAULT 0,
        total_comissao DECIMAL(10,2) DEFAULT 0.00,
        comissao_pendente DECIMAL(10,2) DEFAULT 0.00,
        data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ativo BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS comissoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        afiliado_id INT NOT NULL,
        usuario_indicado_id INT NOT NULL,
        valor_comissao DECIMAL(10,2) NOT NULL,
        tipo ENUM('deposito', 'jogada') DEFAULT 'deposito',
        status ENUM('pendente', 'pago') DEFAULT 'pendente',
        data_comissao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (afiliado_id) REFERENCES afiliados(id),
        FOREIGN KEY (usuario_indicado_id) REFERENCES usuarios(id)
    )");
} catch (PDOException $e) {
    // Tabelas já existem ou erro na criação
}

// Buscar afiliados
try {
    $stmt = $pdo->query("
        SELECT a.*, u.nome, u.email,
               COUNT(DISTINCT ui.id) as indicados_ativos,
               SUM(c.valor_comissao) as total_ganho
        FROM afiliados a
        LEFT JOIN usuarios u ON a.usuario_id = u.id
        LEFT JOIN usuarios ui ON ui.codigo_indicacao = a.codigo_afiliado
        LEFT JOIN comissoes c ON a.id = c.afiliado_id AND c.status = 'pago'
        GROUP BY a.id
        ORDER BY a.total_comissao DESC
    ");
    $afiliados = $stmt->fetchAll();
} catch (PDOException $e) {
    $afiliados = [];
}

// Estatísticas gerais
try {
    $total_afiliados = $pdo->query("SELECT COUNT(*) FROM afiliados WHERE ativo = 1")->fetchColumn();
    $total_indicados = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE codigo_indicacao IS NOT NULL AND codigo_indicacao != ''")->fetchColumn();
    $total_comissoes = $pdo->query("SELECT SUM(valor_comissao) FROM comissoes WHERE status = 'pago'")->fetchColumn() ?: 0;
    $comissoes_pendentes = $pdo->query("SELECT SUM(valor_comissao) FROM comissoes WHERE status = 'pendente'")->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $total_afiliados = $total_indicados = $total_comissoes = $comissoes_pendentes = 0;
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'criar_afiliado':
                $usuario_id = intval($_POST['usuario_id']);
                $codigo = strtoupper(substr(md5(uniqid()), 0, 8));
                
                $stmt = $pdo->prepare("INSERT INTO afiliados (usuario_id, codigo_afiliado) VALUES (?, ?)");
                $stmt->execute([$usuario_id, $codigo]);
                $_SESSION['success'] = 'Afiliado criado com sucesso!';
                break;
                
            case 'pagar_comissoes':
                $afiliado_id = intval($_POST['afiliado_id']);
                
                // Buscar comissões pendentes
                $stmt = $pdo->prepare("SELECT SUM(valor_comissao) FROM comissoes WHERE afiliado_id = ? AND status = 'pendente'");
                $stmt->execute([$afiliado_id]);
                $valor_pendente = $stmt->fetchColumn() ?: 0;
                
                if ($valor_pendente > 0) {
                    // Marcar comissões como pagas
                    $stmt = $pdo->prepare("UPDATE comissoes SET status = 'pago' WHERE afiliado_id = ? AND status = 'pendente'");
                    $stmt->execute([$afiliado_id]);
                    
                    // Atualizar totais do afiliado
                    $stmt = $pdo->prepare("UPDATE afiliados SET total_comissao = total_comissao + ?, comissao_pendente = 0 WHERE id = ?");
                    $stmt->execute([$valor_pendente, $afiliado_id]);
                    
                    // Adicionar saldo ao usuário afiliado
                    $stmt = $pdo->prepare("SELECT usuario_id FROM afiliados WHERE id = ?");
                    $stmt->execute([$afiliado_id]);
                    $usuario_id = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
                    $stmt->execute([$valor_pendente, $usuario_id]);
                    
                    $_SESSION['success'] = "Comissões pagas! R$ " . number_format($valor_pendente, 2, ',', '.') . " creditado.";
                }
                break;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao processar ação: ' . $e->getMessage();
    }
    
    header('Location: admin_afiliados.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Afiliados - Admin</title>
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

        .btn-primary { background: var(--primary-green); color: #000; }
        .btn-primary { 
            background: linear-gradient(135deg, var(--primary-gold), var(--primary-green)); 
            color: #000; 
            box-shadow: 0 4px 16px rgba(251, 206, 0, 0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(251, 206, 0, 0.4);
        }
        .btn-secondary { background: var(--bg-card); color: var(--text-light); border: 1px solid var(--border-color); }
        .btn-success { background: var(--success-color); color: white; }
        .btn-danger { background: var(--error-color); color: white; }
        .btn-warning { background: var(--warning-color); color: white; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--bg-panel);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .stat-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary-green);
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 14px;
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

        .codigo-afiliado {
            background: var(--bg-card);
            padding: 4px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-weight: 700;
            color: var(--primary-green);
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

        .info-card {
            background: var(--bg-panel);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .info-card h3 {
            color: var(--primary-green);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-card p {
            color: var(--text-muted);
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <i class="fas fa-user-friends"></i>
            Sistema de Afiliados
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

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($total_afiliados) ?></div>
                <div class="stat-label">Afiliados Ativos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($total_indicados) ?></div>
                <div class="stat-label">Total Indicados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ <?= number_format($total_comissoes, 2, ',', '.') ?></div>
                <div class="stat-label">Comissões Pagas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">R$ <?= number_format($comissoes_pendentes, 2, ',', '.') ?></div>
                <div class="stat-label">Comissões Pendentes</div>
            </div>
        </div>

        <div class="info-card">
            <h3>
                <i class="fas fa-info-circle"></i>
                Como Funciona o Sistema de Afiliados
            </h3>
            <p>
                O sistema "Indique e Ganhe" permite que usuários ganhem comissões indicando novos jogadores. 
                Cada afiliado recebe um código único que pode ser compartilhado. Quando um novo usuário se cadastra 
                usando este código e faz depósitos ou joga, o afiliado recebe uma comissão configurável.
            </p>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Afiliado</th>
                        <th>Código</th>
                        <th>Indicados</th>
                        <th>Total Ganho</th>
                        <th>Pendente</th>
                        <th>Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($afiliados as $afiliado): ?>
                        <tr>
                            <td style="color: var(--text-light); font-weight: 600;">
                                <?= htmlspecialchars($afiliado['nome'] ?? 'Usuário #' . $afiliado['usuario_id']) ?>
                                <div style="font-size: 12px; color: var(--text-muted);">
                                    <?= htmlspecialchars($afiliado['email'] ?? '') ?>
                                </div>
                            </td>
                            <td>
                                <span class="codigo-afiliado"><?= $afiliado['codigo_afiliado'] ?></span>
                            </td>
                            <td style="color: var(--primary-green); font-weight: 700;">
                                <?= $afiliado['indicados_ativos'] ?? 0 ?>
                            </td>
                            <td style="color: var(--success-color); font-weight: 700;">
                                R$ <?= number_format($afiliado['total_ganho'] ?? 0, 2, ',', '.') ?>
                            </td>
                            <td style="color: var(--warning-color); font-weight: 700;">
                                R$ <?= number_format($afiliado['comissao_pendente'], 2, ',', '.') ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($afiliado['data_cadastro'])) ?></td>
                            <td>
                                <?php if ($afiliado['comissao_pendente'] > 0): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="pagar_comissoes">
                                        <input type="hidden" name="afiliado_id" value="<?= $afiliado['id'] ?>">
                                        <button type="submit" 
                                                class="btn btn-success btn-sm" 
                                                onclick="return confirm('Pagar comissões pendentes?')">
                                            <i class="fas fa-dollar-sign"></i>
                                            Pagar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 12px;">
                                        Sem pendências
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (empty($afiliados)): ?>
            <div class="info-card">
                <h3>
                    <i class="fas fa-users"></i>
                    Nenhum Afiliado Cadastrado
                </h3>
                <p>
                    Ainda não há afiliados no sistema. Os usuários podem se tornar afiliados através da área de perfil 
                    ou você pode criar manualmente através do sistema de usuários.
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>