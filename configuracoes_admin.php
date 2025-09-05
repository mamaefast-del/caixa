<?php
session_start();
$mostrar_popup = false;

if (isset($_SESSION['sucesso'])) {
  $mostrar_popup = true;
  unset($_SESSION['sucesso']);
}

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
  header('Location: login.php');
  exit;
}

require 'db.php';
$distribuicao = [];
if (file_exists('distribuicao.json')) {
  $distribuicao = json_decode(file_get_contents('distribuicao.json'), true);
}

$config = $pdo->query("SELECT * FROM configuracoes LIMIT 1")->fetch();

$stmt = $pdo->query("SELECT * FROM raspadinhas_config ORDER BY valor ASC");
$raspadinhas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Configurações da Plataforma</title>
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

    .desc {
      color: var(--text-muted);
      margin-bottom: 30px;
      font-weight: 500;
      font-size: 16px;
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

    .card h3 {
      color: var(--primary-gold);
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 20px;
    }

    /* Form */
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
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
      display: flex;
      align-items: center;
      gap: 6px;
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

    /* Raspadinha Cards */
    .raspadinha-card {
      background: var(--bg-panel);
      border: 1px solid var(--border-panel);
      border-radius: var(--radius);
      padding: 24px;
      margin-bottom: 24px;
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }

    .raspadinha-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--primary-gold), transparent);
      transition: var(--transition);
    }

    .raspadinha-card:hover {
      border-color: var(--primary-gold);
      box-shadow: var(--shadow-gold);
    }

    .raspadinha-card:hover::before {
      left: 100%;
    }

    .raspadinha-titulo {
      font-weight: 700;
      font-size: 18px;
      margin-bottom: 20px;
      color: var(--primary-gold);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .premios-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
      gap: 16px;
      margin-top: 20px;
    }

    .premio-item {
      background: var(--bg-dark);
      padding: 12px;
      border-radius: 8px;
      border: 1px solid var(--border-panel);
      transition: var(--transition);
    }

    .premio-item:hover {
      border-color: var(--primary-gold);
    }

    .premio-item label {
      font-size: 12px;
      color: var(--primary-gold);
      font-weight: 600;
      margin-bottom: 4px;
    }

    .premio-item input {
      width: 100%;
      padding: 8px;
      font-size: 12px;
    }

    /* Button */
    .btn {
      padding: 16px 32px;
      background: linear-gradient(135deg, var(--primary-gold), #f4c430);
      color: #000;
      font-weight: 700;
      border: none;
      border-radius: var(--radius);
      cursor: pointer;
      transition: var(--transition);
      font-size: 16px;
      box-shadow: 0 2px 8px rgba(251, 206, 0, 0.3);
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      margin-top: 20px;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(251, 206, 0, 0.4);
    }

    /* Success Popup */
    #overlay-sucesso {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(5px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
    }

    #popup-sucesso {
      background: var(--bg-panel);
      border: 2px solid var(--primary-gold);
      color: var(--primary-gold);
      padding: 30px 40px;
      border-radius: var(--radius);
      box-shadow: var(--shadow-gold);
      font-size: 20px;
      text-align: center;
      position: relative;
      max-width: 90%;
      user-select: none;
      animation: slideInDown 0.4s ease;
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

    #btn-fechar-popup {
      position: absolute;
      top: 10px;
      right: 15px;
      background: none;
      border: none;
      font-size: 28px;
      cursor: pointer;
      color: var(--primary-gold);
      font-weight: 700;
      line-height: 1;
      transition: var(--transition);
      width: 30px;
      height: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
    }

    #btn-fechar-popup:hover {
      background: rgba(251, 206, 0, 0.1);
      transform: scale(1.1);
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

      .premios-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .btn {
        width: 100%;
        justify-content: center;
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
      <a href="configuracoes_admin.php" class="nav-item active">
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
      <i class="fas fa-cog"></i>
      Configurações da Plataforma
    </h2>
    <p class="desc">Gerencie as configurações de taxas, bônus, afiliados e prêmios da plataforma.</p>

    <form action="salvar_configuracoes.php" method="POST">
      <!-- Configurações Gerais -->
      <div class="card">
        <h3>
          <i class="fas fa-sliders-h"></i>
          Configurações Gerais
        </h3>
        
        <div class="form-grid">
          <div class="form-group">
            <label for="min_deposito">
              <i class="fas fa-arrow-down"></i>
              Mínimo Depósito (R$)
            </label>
            <input type="number" id="min_deposito" name="min_deposito" step="0.01" value="<?= htmlspecialchars($config['min_deposito']) ?>" required>
          </div>
          
          <div class="form-group">
            <label for="max_deposito">
              <i class="fas fa-arrow-up"></i>
              Máximo Depósito (R$)
            </label>
            <input type="number" id="max_deposito" name="max_deposito" step="0.01" value="<?= htmlspecialchars($config['max_deposito']) ?>" required>
          </div>
          
          <div class="form-group">
            <label for="min_saque">
              <i class="fas fa-money-bill-wave"></i>
              Mínimo Saque (R$)
            </label>
            <input type="number" id="min_saque" name="min_saque" step="0.01" value="<?= htmlspecialchars($config['min_saque']) ?>" required>
          </div>
          
          <div class="form-group">
            <label for="max_saque">
              <i class="fas fa-money-bill-wave"></i>
              Máximo Saque (R$)
            </label>
            <input type="number" id="max_saque" name="max_saque" step="0.01" value="<?= htmlspecialchars($config['max_saque']) ?>" required>
          </div>
          
          <div class="form-group">
            <label for="bonus_deposito">
              <i class="fas fa-gift"></i>
              Bônus de Depósito (%)
            </label>
            <input type="number" id="bonus_deposito" name="bonus_deposito" step="0.01" value="<?= htmlspecialchars($config['bonus_deposito']) ?>">
          </div>
          
          <div class="form-group">
            <label for="rollover_multiplicador">
              <i class="fas fa-sync-alt"></i>
              Rollover (multiplicador)
            </label>
            <input type="number" id="rollover_multiplicador" name="rollover_multiplicador" step="0.1" value="<?= htmlspecialchars($config['rollover_multiplicador']) ?>" required>
          </div>
        </div>
      </div>

      <!-- Botão Salvar Configurações Gerais -->
      <button type="submit" class="btn">
        <i class="fas fa-save"></i>
        Salvar Configurações Gerais
      </button>

      <!-- Configurações de Afiliados -->
      <div class="card">
        <h3>
          <i class="fas fa-handshake"></i>
          Sistema de Afiliados
        </h3>
        
        <div class="form-grid">
          <div class="form-group">
            <label for="tipo_comissao">
              <i class="fas fa-chart-line"></i>
              Tipo de Comissão
            </label>
            <select id="tipo_comissao" name="tipo_comissao">
              <option value="revshare" <?= $config['tipo_comissao'] == 'revshare' ? 'selected' : '' ?>>RevShare</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="valor_comissao">
              <i class="fas fa-percentage"></i>
              Comissão Afiliados Nível 1 (%)
            </label>
            <input type="number" id="valor_comissao" name="valor_comissao" step="0.01" value="<?= htmlspecialchars($config['valor_comissao']) ?>" required>
          </div>
          
          <div class="form-group">
            <label for="valor_comissao_n2">
              <i class="fas fa-percentage"></i>
              Comissão Afiliados Nível 2 (%)
            </label>
            <input type="number" id="valor_comissao_n2" name="valor_comissao_n2" step="0.01" value="<?= htmlspecialchars($config['valor_comissao_n2']) ?>" required>
          </div>
          
          <div class="form-group">
            <label for="min_saque_comissao">
              <i class="fas fa-coins"></i>
              Mínimo Saque Comissão (R$)
            </label>
            <input type="number" id="min_saque_comissao" name="min_saque_comissao" step="0.01" value="<?= htmlspecialchars($config['min_saque_comissao']) ?>" required>
          </div>
          
          <div class="form-group">
            <label for="max_saque_comissao">
              <i class="fas fa-coins"></i>
              Máximo Saque Comissão (R$)
            </label>
            <input type="number" id="max_saque_comissao" name="max_saque_comissao" step="0.01" value="<?= htmlspecialchars($config['max_saque_comissao']) ?>" required>
          </div>
        </div>
      </div>

      <!-- Configurações das Raspadinhas -->
      <?php foreach ($raspadinhas as $r): ?>
        <div class="raspadinha-card">
          <h3 class="raspadinha-titulo">
            <i class="fas fa-ticket-alt"></i>
            <?= htmlspecialchars($r['nome']) ?> - R$ <?= number_format($r['valor'], 2, ',', '.') ?>
          </h3>

          <div class="form-group">
            <label for="chance_ganho_<?= $r['id'] ?>">
              <i class="fas fa-dice"></i>
              Chance de Ganho (%)
            </label>
            <input type="number" id="chance_ganho_<?= $r['id'] ?>" name="chance_ganho_<?= $r['id'] ?>" step="0.0001" value="<?= htmlspecialchars($r['chance_ganho']) ?>" required style="max-width: 200px;">
          </div>

          <?php
            $premios = json_decode($r['premios_json'], true);
          ?>
          <div style="margin-top: 20px;">
            <label style="font-size: 16px; color: var(--primary-gold); margin-bottom: 12px; display: block;">
              <i class="fas fa-trophy"></i>
              Distribuição de Prêmios - Chances (%)
            </label>
            <div class="premios-grid">
              <?php foreach ($premios as $valorPremio => $chancePremio): ?>
                <div class="premio-item">
                  <label for="premios_<?= $r['id'] ?>_<?= $valorPremio ?>">
                    R$ <?= htmlspecialchars($valorPremio) ?>
                  </label>
                  <input
                    type="number"
                    id="premios_<?= $r['id'] ?>_<?= $valorPremio ?>"
                    name="premios_<?= $r['id'] ?>_<?= $valorPremio ?>"
                    value="<?= htmlspecialchars($chancePremio) ?>"
                    step="0.0001" min="0" required
                  >
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <button type="submit" class="btn">
        <i class="fas fa-save"></i>
        Salvar Configurações
      </button>
      <button type="submit" class="btn">
        <i class="fas fa-save"></i>
        Salvar Configurações
      </button>
    </form>
  </div>

  <?php if ($mostrar_popup): ?>
    <div id="overlay-sucesso">
      <div id="popup-sucesso">
        <button id="btn-fechar-popup">&times;</button>
        <i class="fas fa-check-circle" style="font-size: 24px; margin-bottom: 12px;"></i>
        <br>
        <strong>Configurações salvas com sucesso!</strong>
      </div>
    </div>

    <script>
      document.getElementById('btn-fechar-popup').addEventListener('click', function () {
        document.getElementById('overlay-sucesso').style.display = 'none';
      });

      // Auto fechar após 3 segundos
      setTimeout(function() {
        const overlay = document.getElementById('overlay-sucesso');
        if (overlay) {
          overlay.style.display = 'none';
        }
      }, 3000);
    </script>
  <?php endif; ?>

</body>
</html>