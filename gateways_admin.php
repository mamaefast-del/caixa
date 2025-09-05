<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
  header('Location: login.php');
  exit;
}

// Processa envio do formulário para atualizar gateways
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Atualiza credenciais e ativo
    $ativo = $_POST['ativo'] ?? '';
    $gateways = $_POST['gateways'] ?? [];

    foreach ($gateways as $id => $dados) {
        $client_id = trim($dados['client_id'] ?? '');
        $client_secret = trim($dados['client_secret'] ?? '');
        $callback_base = trim($dados['callback_base'] ?? '');
        $callback_url = rtrim($callback_base, '/') . '/webhook-pix.php';
        $is_ativo = ($ativo == $id) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE gateways SET client_id = ?, client_secret = ?, callback_url = ?, ativo = ? WHERE id = ?");
        $stmt->execute([$client_id, $client_secret, $callback_url, $is_ativo, $id]);

    }

    header('Location: gateways_admin.php?msg=Salvo com sucesso!');
    exit;
}

// Busca gateways
$gateways = $pdo->query("SELECT * FROM gateways ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$mensagem = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Configuração de Gateways</title>
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

    .gateways-section {
      width: 48%;
      margin-right: 2%;
    }

    .ip-section {
      width: 48%;
      margin-left: 2%;
    }

    .two-column-layout {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 30px;
      margin: 30px 0;
    }

    form {
      max-width: 100%;
      margin: 0;
    }

    input[type="text"], input[type="password"] {
      width: 100%;
      padding: 12px 16px;
      font-size: 15px;
      border: 1px solid var(--border-panel);
      border-radius: var(--radius);
      background-color: var(--bg-dark);
      color: var(--text-light);
      transition: all 0.3s ease;
      min-height: 45px;
    }

    input[type="text"]:focus, input[type="password"]:focus {
      outline: none;
      border-color: var(--primary-gold);
      box-shadow: 0 0 0 3px rgba(251, 206, 0, 0.1);
      transform: translateY(-2px);
    }

    input[type="text"]:hover, input[type="password"]:hover {
      border-color: var(--primary-gold);
    }

    input[type="radio"] {
      cursor: pointer;
      width: 22px;
      height: 22px;
      vertical-align: middle;
      accent-color: var(--primary-gold);
    }

    label {
      font-size: 14px;
      color: var(--text-light);
      user-select: none;
      cursor: pointer;
      font-weight: 600;
    }

    .btn {
      padding: 16px 32px;
      background: linear-gradient(135deg, var(--primary-gold), #f4c430);
      color: #000;
      font-weight: 700;
      border: none;
      border-radius: var(--radius);
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 30px;
      font-size: 16px;
      box-shadow: 0 2px 8px rgba(251, 206, 0, 0.3);
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(251, 206, 0, 0.4);
    }

    .gateway-name {
      font-weight: 700;
      font-size: 16px;
      color: var(--primary-gold);
      text-transform: uppercase;
      letter-spacing: 1px;
      padding: 8px 16px;
      background: rgba(251, 206, 0, 0.1);
      border-radius: 8px;
      border: 1px solid rgba(251, 206, 0, 0.3);
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

    /* Estilos do Painel de Credenciais EXPFY Pay */
    .credentials-panel {
        background: var(--bg-panel);
        border: 2px solid var(--primary-gold);
        border-radius: 15px;
        padding: 15px;
        box-shadow: var(--shadow-gold);
      }

    .credentials-header {
         display: flex;
         align-items: center;
         margin-bottom: 12px;
         padding-bottom: 12px;
         border-bottom: 2px solid rgba(251, 206, 0, 0.2);
       }

    .wallet-icon {
         font-size: 16px;
         margin-right: 8px;
       }

       .credentials-header h3 {
         color: var(--primary-gold);
         font-size: 16px;
         font-weight: 700;
         margin: 0;
       }

    .status-banner {
         background: linear-gradient(135deg, var(--primary-gold), #f4c430);
         color: #000;
         padding: 8px 12px;
         border-radius: 6px;
         margin-bottom: 12px;
         display: flex;
         align-items: center;
         font-weight: 600;
         font-size: 13px;
       }

    .check-icon {
         font-size: 12px;
         margin-right: 6px;
       }

    .info-box {
         background: rgba(255, 193, 7, 0.1);
         border: 1px solid rgba(255, 193, 7, 0.3);
         border-radius: 6px;
         padding: 12px;
         margin-bottom: 12px;
         color: #ffc107;
       }

    .security-section {
       margin-bottom: 12px;
       line-height: 1.5;
     }

    .api-section {
        line-height: 1.5;
      }

    .shield-icon {
       margin-right: 6px;
     }

    .info-box code {
       background: rgba(0, 0, 0, 0.3);
       padding: 3px 6px;
       border-radius: 3px;
       font-family: 'Courier New', monospace;
       color: var(--primary-gold);
       font-size: 11px;
     }

    .credentials-form {
        margin-top: 12px;
      }

      .input-group {
        margin-bottom: 12px;
      }

    .input-group label {
         display: flex;
         align-items: center;
         margin-bottom: 5px;
         color: var(--primary-gold);
         font-weight: 600;
         font-size: 11px;
       }

    .key-icon, .lock-icon, .link-icon, .radio-icon {
         margin-right: 5px;
         font-size: 11px;
       }

    .credentials-form input[type="text"],
       .credentials-form input[type="password"] {
         width: 100%;
         padding: 8px 12px;
         font-size: 11px;
         border: 1px solid var(--border-panel);
         border-radius: 6px;
         background-color: var(--bg-dark);
         color: var(--text-light);
         transition: all 0.3s ease;
         min-height: 35px;
       }

    .credentials-form input[type="text"]:focus,
    .credentials-form input[type="password"]:focus {
      outline: none;
      border-color: var(--primary-gold);
      box-shadow: 0 0 0 3px rgba(251, 206, 0, 0.1);
      transform: translateY(-2px);
    }

    .password-input-container {
      position: relative;
      display: flex;
      align-items: center;
    }

    .eye-toggle {
         position: absolute;
         right: 12px;
         background: none;
         border: none;
         color: var(--primary-gold);
         font-size: 14px;
         cursor: pointer;
         padding: 4px;
         border-radius: 5px;
         transition: all 0.3s ease;
       }

    .eye-toggle:hover {
      background: rgba(251, 206, 0, 0.1);
      transform: scale(1.1);
    }

    .radio-container {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        background: var(--bg-dark);
        border: 1px solid var(--border-panel);
        border-radius: 6px;
        transition: all 0.3s ease;
      }

    .radio-container:hover {
      border-color: var(--primary-gold);
    }

    .radio-container input[type="radio"] {
       width: 18px;
       height: 18px;
       margin-right: 10px;
       accent-color: var(--primary-gold);
     }

    .radio-label {
       color: var(--text-light);
       font-weight: 500;
       cursor: pointer;
       margin: 0;
       font-size: 12px;
     }

    .save-btn {
         width: 100%;
         padding: 12px 24px;
         background: linear-gradient(135deg, var(--primary-gold), #f4c430);
         color: #000;
         font-weight: 700;
         border: none;
         border-radius: 8px;
         cursor: pointer;
         transition: all 0.3s ease;
         font-size: 13px;
         box-shadow: 0 2px 8px rgba(251, 206, 0, 0.3);
         display: flex;
         align-items: center;
         justify-content: center;
         gap: 6px;
       }

    .save-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 12px rgba(251, 206, 0, 0.4);
    }

    .save-icon {
         font-size: 14px;
       }

    /* Estilos do Painel de IP sempre visível */
     .ip-panel {
       margin: 0;
       background: var(--bg-panel);
       border: 2px solid var(--primary-gold);
       border-radius: 15px;
       padding: 0;
       width: 100%;
       max-width: 100%;
       box-shadow: var(--shadow-gold);
     }

    .ip-panel-content {
       background: var(--bg-panel);
       border-radius: 15px;
       padding: 0;
       width: 100%;
       overflow-y: auto;
       margin: 0 auto;
     }

    .ip-panel-header {
       background: linear-gradient(135deg, var(--primary-gold), #f4c430);
       color: white;
       padding: 15px;
       border-radius: 15px 15px 0 0;
       display: flex;
       justify-content: center;
       align-items: center;
     }

     .ip-panel-header h3 {
       margin: 0;
       font-size: 18px;
       font-weight: 600;
     }

    .ip-info-section {
       padding: 20px;
       background: var(--bg-dark);
       border-bottom: 1px solid rgba(251, 206, 0, 0.3);
       margin: 0;
     }

    .ip-item {
       display: flex;
       align-items: center;
       margin-bottom: 15px;
       padding: 12px;
       background: var(--bg-panel);
       border-radius: 8px;
       border: 1px solid rgba(251, 206, 0, 0.2);
       box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
     }

    .ip-label {
      font-weight: 600;
      color: var(--primary-gold);
      min-width: 60px;
      margin-right: 15px;
    }

    .ip-value {
       flex: 1;
       font-family: 'Courier New', monospace;
       font-size: 14px;
       color: #e0e0e0;
       background: rgba(0, 0, 0, 0.3);
       padding: 10px 14px;
       border-radius: 6px;
       border: 1px solid rgba(251, 206, 0, 0.3);
       margin: 0 12px;
     }

    .copy-btn {
       background: var(--primary-gold);
       color: #000;
       border: none;
       border-radius: 6px;
       padding: 10px 14px;
       margin-left: 12px;
       cursor: pointer;
       transition: all 0.3s;
       font-size: 16px;
       min-width: 45px;
     }

    .copy-btn:hover {
      background: #f4c430;
      transform: scale(1.05);
    }

    .ip-note {
       margin-top: 20px;
       padding: 15px;
       background: var(--bg-panel);
       border-radius: 8px;
       border-left: 4px solid var(--primary-gold);
       color: var(--text-light);
       font-size: 14px;
       line-height: 1.6;
       box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
     }

    .info-icon {
      margin-right: 8px;
      color: var(--primary-gold);
    }

    .security-warning {
       padding: 20px;
       background: var(--bg-dark);
     }

    .warning-header {
       display: flex;
       align-items: center;
       margin-bottom: 15px;
       padding: 12px;
       background: linear-gradient(135deg, #ff6b6b, #ee5a52);
       border-radius: 8px;
       color: white;
     }

     .warning-icon {
       font-size: 20px;
       margin-right: 10px;
     }

     .warning-header h4 {
       margin: 0;
       font-size: 16px;
       font-weight: 600;
     }

    .warning-content {
      color: #e0e0e0;
      line-height: 1.6;
    }

    .warning-content p {
       margin-bottom: 15px;
       padding: 12px;
       background: rgba(255, 107, 107, 0.1);
       border-radius: 8px;
       border-left: 4px solid #ff6b6b;
       font-size: 14px;
       line-height: 1.6;
     }

    .recommendation {
       margin-top: 20px;
       padding: 15px;
       background: var(--bg-panel);
       border-radius: 8px;
       border: 1px solid rgba(251, 206, 0, 0.3);
       box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
     }

    .arrow-icon {
      color: var(--primary-gold);
      margin-right: 8px;
    }

    .recommendation strong {
      color: var(--primary-gold);
    }

    .recommendation ul {
      margin: 15px 0 0 25px;
      color: var(--text-light);
    }

    .recommendation li {
       margin-bottom: 10px;
       font-size: 14px;
       line-height: 1.5;
     }

    .ip-panel-container {
      width: 100%;
      display: block;
      margin: 0;
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

      .two-column-layout {
        flex-direction: column;
        gap: 20px;
      }

      .gateways-section,
      .ip-section {
        width: 100%;
        margin: 0;
      }

      .credentials-panel {
         padding: 20px;
       }

      .credentials-header h3 {
         font-size: 20px;
       }

      .status-banner {
         padding: 12px 16px;
         font-size: 14px;
       }

      .info-box {
         padding: 15px;
         font-size: 14px;
       }

      .credentials-form input[type="text"],
      .credentials-form input[type="password"] {
         padding: 12px 15px;
         min-height: 45px;
       }

      .save-btn {
         padding: 15px 25px;
         font-size: 15px;
       }

      /* Painel responsivo */
      .ip-panel {
        width: 98%;
        margin: 25px auto;
        max-width: 98%;
      }

      .ip-panel-header {
        padding: 15px;
      }

      .ip-panel-header h3 {
        font-size: 18px;
      }

      .ip-info-section,
      .security-warning {
        padding: 20px;
      }

      .ip-item {
        flex-direction: column;
        align-items: flex-start;
      }

      .ip-label {
        margin-bottom: 8px;
        min-width: auto;
      }

      .copy-btn {
        margin-left: 0;
        margin-top: 8px;
        width: 100%;
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
      <a href="gateways_admin.php" class="nav-item active">
        <i class="fas fa-credit-card"></i>
        <span class="nav-text">Gateways</span>
      </a>
      <a href="pix_admin.php" class="nav-item">
        <i class="fas fa-exchange-alt"></i>
        <span class="nav-text">Transações</span>
      </a>
    </nav>
  </div>

  <div class="content">
    <h2>
      <i class="fas fa-credit-card"></i>
      Configuração de Gateways de Pagamento
    </h2>

    <?php if ($mensagem): ?>
      <div class="message success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($mensagem) ?>
      </div>
    <?php endif; ?>

    <div class="two-column-layout">
      <!-- Coluna Esquerda: Configuração de Gateways -->
        <div class="gateways-section">
          <div class="credentials-panel">
            <div class="credentials-header">
              <span class="wallet-icon">💳</span>
              <h3>Credenciais EXPFY Pay</h3>
            </div>

            <!-- Status de Configuração -->
            <div class="status-banner">
              <span class="check-icon">✅</span>
              <span>API EXPFY Pay Configurada</span>
            </div>

            <!-- Caixa de Informações -->
            <div class="info-box">
               <div class="security-section">
                 <span class="shield-icon">🛡️</span>
                 <strong>Segurança:</strong> Suas credenciais são criptografadas e protegidas. Nunca compartilhe essas informações com terceiros não autorizados.
               </div>
               <div class="api-section">
                 <strong>URL Base da API:</strong> <code>https://pro.expfypay.com/api/v1/</code>
               </div>
            </div>

            <!-- Formulário de Credenciais -->
            <form method="POST" action="gateways_admin.php" class="credentials-form">
              <div class="input-group">
                <label>
                  <span class="key-icon">🔑</span>
                  Public Key (X-Public-Key)
                </label>
                <input 
                  type="text" 
                  name="gateways[<?= $gateways[0]['id'] ?? 1 ?>][client_id]" 
                  value="<?= htmlspecialchars($gateways[0]['client_id'] ?? '') ?>" 
                  placeholder="pk"
                  required
                />
              </div>

              <div class="input-group">
                <label>
                  <span class="lock-icon">🔒</span>
                  Secret Key (X-Secret-Key)
                </label>
                <div class="password-input-container">
                  <input 
                    type="password" 
                    name="gateways[<?= $gateways[0]['id'] ?? 1 ?>][client_secret]" 
                    value="<?= htmlspecialchars($gateways[0]['client_secret'] ?? '') ?>" 
                    placeholder="Secret Key"
                    required
                    id="secret-key-input"
                  />
                  <button type="button" class="eye-toggle" onclick="togglePassword()">
                    👁️
                  </button>
                </div>
              </div>

              <div class="input-group">
                <label>
                  <span class="link-icon">🔗</span>
                  Callback URL
                </label>
                <input 
                  type="text" 
                  name="gateways[<?= $gateways[0]['id'] ?? 1 ?>][callback_base]" 
                  value="<?= htmlspecialchars(str_replace('/webhook-pix.php', '', $gateways[0]['callback_url'] ?? '')) ?>" 
                  placeholder="https://voy-cloverpg.fun"
                  required
                />
              </div>

              <div class="input-group">
                <label>
                  <span class="radio-icon">📻</span>
                  Gateway Ativo
                </label>
                <div class="radio-container">
                  <input 
                    type="radio" 
                    name="ativo" 
                    value="<?= $gateways[0]['id'] ?? 1 ?>" 
                    <?= ($gateways[0]['ativo'] ?? false) ? 'checked' : '' ?> 
                    title="Ativar este gateway"
                    id="gateway-active"
                  />
                  <label for="gateway-active" class="radio-label">Ativar EXPFY Pay</label>
                </div>
              </div>

              <button class="save-btn" type="submit">
                <span class="save-icon"><i class="fas fa-save"></i></span>
                Salvar Credenciais EXPFY Pay
              </button>
            </form>
          </div>
        </div>

      <!-- Coluna Direita: Informações de IP -->
      <div class="ip-section">
         <div class="ip-panel-container">
           <div class="ip-panel">
             <div class="ip-panel-content">
               <div class="ip-panel-header">
                 <h3><i class="fas fa-globe"></i> Informações de IP do Site</h3>
               </div>
               
               <div class="ip-info-section">
                 <div class="ip-item">
                   <span class="ip-label">IPv4:</span>
                   <span class="ip-value" id="ipv4">Carregando...</span>
                   <button class="copy-btn" onclick="copyToClipboard('ipv4')" title="Copiar IPv4">
                     <i class="fas fa-copy"></i>
                   </button>
                 </div>
                 
                 <div class="ip-item">
                   <span class="ip-label">IPv6:</span>
                   <span class="ip-value" id="ipv6">Carregando...</span>
                   <button class="copy-btn" onclick="copyToClipboard('ipv6')" title="Copiar IPv6">
                     <i class="fas fa-copy"></i>
                   </button>
                 </div>
                 
                 <div class="ip-note">
                   <span class="info-icon"><i class="fas fa-info-circle"></i></span>
                   Estes são os IPs públicos do seu servidor. Use-os para configurar as permissões no gateway de pagamento.
                 </div>
               </div>

               <div class="security-warning">
                 <div class="warning-header">
                   <span class="warning-icon"><i class="fas fa-exclamation-triangle"></i></span>
                   <h4>AVISO IMPORTANTE DE SEGURANÇA</h4>
                 </div>
                 
                 <div class="warning-content">
                   <p>Este código está exposto publicamente e, portanto, não garante 100% de segurança.</p>
                   <p>Ao configurar IPs de saque neste ambiente, você estará exposto a riscos de acesso não autorizado e possíveis perdas financeiras.</p>
                   
                   <div class="recommendation">
                     <span class="arrow-icon"><i class="fas fa-arrow-right"></i></span>
                     <strong>Recomendação:</strong>
                     <ul>
                       <li>Não utilize este IP em produção para operações de saque</li>
                       <li>Utilize apenas em ambiente de testes ou sandbox</li>
                       <li>Caso opte por utilizá-lo em produção, esteja plenamente ciente de que os riscos são de sua inteira responsabilidade</li>
                     </ul>
                   </div>
                 </div>
               </div>
             </div>
           </div>
         </div>
       </div>
    </div>
  </div>

  <script>
    // Função para obter IPs
    async function getIPs() {
      try {
        const response = await fetch('https://api.ipify.org?format=json');
        const data = await response.json();
        document.getElementById('ipv4').textContent = data.ip;
        
        // Para IPv6, tentamos uma API diferente
        try {
          const response6 = await fetch('https://api64.ipify.org?format=json');
          const data6 = await response6.json();
          document.getElementById('ipv6').textContent = data6.ip;
        } catch (e) {
          document.getElementById('ipv6').textContent = 'Não disponível';
        }
      } catch (error) {
        document.getElementById('ipv4').textContent = 'Erro ao carregar';
        document.getElementById('ipv6').textContent = 'Erro ao carregar';
      }
    }

    // Função para copiar IP para clipboard
    function copyToClipboard(elementId) {
      const text = document.getElementById(elementId).textContent;
      navigator.clipboard.writeText(text).then(() => {
        // Feedback visual
        const btn = event.target;
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => {
          btn.innerHTML = originalHTML;
        }, 1000);
      });
    }

    // Função para mostrar/ocultar senha
     function togglePassword() {
       const input = document.getElementById('secret-key-input');
       const button = event.target;
       
       if (input.type === 'password') {
         input.type = 'text';
         button.innerHTML = '<i class="fas fa-eye-slash"></i>';
         button.title = 'Ocultar senha';
       } else {
         input.type = 'password';
         button.innerHTML = '<i class="fas fa-eye"></i>';
         button.title = 'Mostrar senha';
       }
     }

    // Carrega IPs quando a página carrega
     window.onload = function() {
       getIPs();
     }
  </script>
</body>
</html>
