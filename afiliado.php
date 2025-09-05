<?php
session_start();
require 'db.php';
if(!isset($_SESSION['usuario_id'])){header('Location:index.php');exit;}
$stmt=$pdo->prepare("SELECT nome,saldo,codigo_convite,comissao FROM usuarios WHERE id=?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario=$stmt->fetch();
$codigo_convite=$usuario['codigo_convite'];$comissao=$usuario['comissao'];
$stmt=$pdo->query("SELECT valor_comissao,valor_comissao_n2 FROM configuracoes LIMIT 1");
$config=$stmt->fetch(PDO::FETCH_ASSOC);
$percentual_comissao=floatval($config['valor_comissao']??0);
$percentual_comissao_n2=floatval($config['valor_comissao_n2']??0);

$link_convite="https://linkplataforma.online/?ref=".$codigo_convite;

$stmt=$pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE indicado_por=?");
$stmt->execute([$_SESSION['usuario_id']]);
$total_indicados=$stmt->fetchColumn();

$stmt=$pdo->prepare("SELECT COUNT(*) FROM usuarios u2 JOIN usuarios u1 ON u2.indicado_por=u1.id WHERE u1.indicado_por=?");
$stmt->execute([$_SESSION['usuario_id']]);
$total_indicados_n2=$stmt->fetchColumn();

$stmt=$pdo->prepare("SELECT u.id,u.nome,u.telefone,COALESCE(SUM(tp.valor),0) total_depositado,MIN(tp.criado_em) data_primeiro_deposito FROM usuarios u LEFT JOIN transacoes_pix tp ON tp.usuario_id=u.id AND tp.status='aprovado' WHERE u.indicado_por=? GROUP BY u.id");
$stmt->execute([$_SESSION['usuario_id']]);
$indicados=$stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt=$pdo->prepare("SELECT u2.id,u2.nome,u2.telefone,COALESCE(SUM(tp.valor),0) total_depositado,MIN(tp.criado_em) data_primeiro_deposito FROM usuarios u1 JOIN usuarios u2 ON u2.indicado_por=u1.id LEFT JOIN transacoes_pix tp ON tp.usuario_id=u2.id AND tp.status='aprovado' WHERE u1.indicado_por=? GROUP BY u2.id");
$stmt->execute([$_SESSION['usuario_id']]);
$indicados_n2=$stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt=$pdo->prepare("SELECT c.valor,c.criado_em,u.nome nome_indicado FROM comissoes c JOIN usuarios u ON c.indicado_id=u.id WHERE c.usuario_id=? ORDER BY c.criado_em DESC");
$stmt->execute([$_SESSION['usuario_id']]);
$historicoComissoes=$stmt->fetchAll(PDO::FETCH_ASSOC);

// Carrega logo
$dadosJson = file_exists('imagens_menu.json') ? json_decode(file_get_contents('imagens_menu.json'), true) : [];
$logo = $dadosJson['logo'] ?? 'logo.png';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
  <title>Programa de Afiliados</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="css/cores-personalizadas.css">
  <style>
    /* Reset e Base */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #0a0b0f;
      color: #ffffff;
      min-height: 100vh;
      line-height: 1.5;
      padding-bottom: 80px;
    }

    /* Header */
    .header {
      background: #111318;
      border-bottom: 1px solid #1a1d24;
      padding: 16px 20px;
      position: sticky;
      top: 0;
      z-index: 100;
      backdrop-filter: blur(20px);
    }

    .header-content {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .logo img {
      height: 40px;
      filter: brightness(1.1);
    }

    .saldo {
      background: linear-gradient(135deg, #fbce00, #f4c430);
      color: #000;
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 700;
      font-size: 14px;
      box-shadow: 0 2px 8px rgba(251, 206, 0, 0.3);
    }

    /* Container */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    /* Tabs */
    .tabs {
      display: flex;
      background: #111318;
      border-bottom: 1px solid #1a1d24;
      position: sticky;
      top: 72px;
      z-index: 50;
      backdrop-filter: blur(20px);
    }

    .tab {
      flex: 1;
      text-align: center;
      padding: 16px 0;
      cursor: pointer;
      font-weight: 700;
      color: #8b949e;
      transition: all 0.3s ease;
      position: relative;
      background: transparent;
      border: none;
      font-size: 14px;
    }

    .tab.active {
      color: #fbce00;
    }

    .tab.active::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(135deg, #fbce00, #f4c430);
      border-radius: 2px 2px 0 0;
    }

    .tab:hover:not(.active) {
      color: #ffffff;
      background: rgba(251, 206, 0, 0.1);
    }

    /* Tab Content */
    .tab-content {
      display: none;
      padding: 24px 0;
      animation: fadeInUp 0.4s ease;
    }

    .tab-content.active {
      display: block;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Cards */
    .card {
      background: #111318;
      border: 1px solid #1a1d24;
      border-radius: 16px;
      padding: 24px;
      margin-bottom: 24px;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .card::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 2px;
      background: linear-gradient(90deg, transparent, #fbce00, transparent);
      animation: shimmer 3s infinite;
    }

    @keyframes shimmer {
      0% { left: -100%; }
      100% { left: 100%; }
    }

    .card:hover {
      border-color: #fbce00;
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(251, 206, 0, 0.1);
    }

    .card h3 {
      color: #fbce00;
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Stats */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }

    .stat-item {
      background: #0d1117;
      border: 1px solid #21262d;
      border-radius: 12px;
      padding: 16px;
      text-align: center;
      transition: all 0.3s ease;
    }

    .stat-item:hover {
      border-color: #fbce00;
      transform: translateY(-2px);
    }

    .stat-value {
      color: #fbce00;
      font-size: 24px;
      font-weight: 800;
      margin-bottom: 4px;
    }

    .stat-label {
      color: #8b949e;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Link de Afiliado */
    .affiliate-link {
      display: flex;
      gap: 12px;
      align-items: center;
      margin: 16px 0;
    }

    .affiliate-link input {
      flex: 1;
      padding: 12px 16px;
      border-radius: 8px;
      background: #0d1117;
      border: 1px solid #21262d;
      color: #ffffff;
      font-size: 14px;
      transition: border-color 0.3s ease;
    }

    .affiliate-link input:focus {
      border-color: #fbce00;
      outline: none;
      box-shadow: 0 0 0 3px rgba(251, 206, 0, 0.1);
    }

    .btn {
      padding: 12px 20px;
      border-radius: 8px;
      border: none;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      font-size: 14px;
    }

    .btn-primary {
      background: linear-gradient(135deg, #fbce00, #f4c430);
      color: #000;
      box-shadow: 0 2px 8px rgba(251, 206, 0, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(251, 206, 0, 0.4);
    }

    .btn-full {
      width: 100%;
      margin-top: 16px;
    }

    /* Lista de Indicados */
    .indicados-list {
      background: #0d1117;
      border: 1px solid #21262d;
      border-radius: 12px;
      overflow: hidden;
    }

    .indicado-item {
      padding: 16px 20px;
      border-bottom: 1px solid #21262d;
      transition: background-color 0.3s ease;
    }

    .indicado-item:last-child {
      border-bottom: none;
    }

    .indicado-item:hover {
      background: rgba(251, 206, 0, 0.05);
    }

    .indicado-nome {
      font-weight: 600;
      color: #ffffff;
      margin-bottom: 4px;
    }

    .indicado-info {
      font-size: 13px;
      color: #8b949e;
      margin-bottom: 2px;
    }

    .indicado-deposito {
      color: #fbce00;
      font-weight: 600;
    }

    /* Section Headers */
    .section-header {
      text-align: center;
      margin: 32px 0 24px;
    }

    .section-header h2 {
      color: #fbce00;
      font-size: 24px;
      font-weight: 800;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .section-header p {
      color: #8b949e;
      font-size: 14px;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #8b949e;
    }

    .empty-state i {
      font-size: 48px;
      color: #21262d;
      margin-bottom: 16px;
    }

    /* Bottom Navigation */
    .bottom-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: #111318;
      border-top: 1px solid #1a1d24;
      display: flex;
      justify-content: space-around;
      padding: 12px 0;
      z-index: 1000;
      backdrop-filter: blur(20px);
    }

    .bottom-nav a {
      color: #8b949e;
      text-decoration: none;
      text-align: center;
      padding: 8px 12px;
      border-radius: 8px;
      transition: all 0.2s ease;
      font-size: 12px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
    }

    .bottom-nav a:hover,
    .bottom-nav a.active {
      color: #fbce00;
      background: rgba(251, 206, 0, 0.1);
    }

    .bottom-nav .deposit-btn {
      background: linear-gradient(135deg, #fbce00, #f4c430);
      color: #000 !important;
      font-weight: 700;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(251, 206, 0, 0.3);
    }

    .bottom-nav .deposit-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(251, 206, 0, 0.4);
    }

    .bottom-nav i {
      font-size: 16px;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .container {
        padding: 0 16px;
      }

      .header-content {
        padding: 0 4px;
      }

      .affiliate-link {
        flex-direction: column;
        gap: 8px;
      }

      .affiliate-link input {
        width: 100%;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
      }

      .stat-value {
        font-size: 20px;
      }

      .section-header h2 {
        font-size: 20px;
      }
    }

    /* Loading Animation */
    .card {
      animation: slideInUp 0.6s ease forwards;
      opacity: 0;
    }

    .card:nth-child(1) { animation-delay: 0.1s; }
    .card:nth-child(2) { animation-delay: 0.2s; }
    .card:nth-child(3) { animation-delay: 0.3s; }

    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Highlight Values */
    .highlight {
      color: #fbce00;
      font-weight: 700;
    }

    .highlight2 {
      color: #f4c430;
      font-weight: 700;
    }
  </style>
</head>

<body>
  <!-- Header -->
  <div class="header">
    <div class="header-content">
      <div class="logo">
        <img src="images/<?= $logo ?>?v=<?= time() ?>" alt="Logo">
      </div>
      <div class="saldo">
        R$ <?= number_format($comissao, 2, ',', '.') ?>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab active" onclick="openTab('convite')">
      <i class="fas fa-share-alt"></i> Meu Link
    </button>
    <button class="tab" onclick="openTab('dados')">
      <i class="fas fa-users"></i> Indicados
    </button>
  </div>

  <div class="container">
    <!-- Tab Convite -->
    <div id="convite" class="tab-content active">
      <!-- Stats Overview -->
      <div class="stats-grid">
        <div class="stat-item">
          <div class="stat-value">R$ <?= number_format($comissao, 2, ',', '.') ?></div>
          <div class="stat-label">Saldo Disponível</div>
        </div>
        <div class="stat-item">
          <div class="stat-value"><?= number_format($percentual_comissao, 1) ?>%</div>
          <div class="stat-label">Comissão Nível 1</div>
        </div>
        <div class="stat-item">
          <div class="stat-value"><?= number_format($percentual_comissao_n2, 1) ?>%</div>
          <div class="stat-label">Comissão Nível 2</div>
        </div>
        <div class="stat-item">
          <div class="stat-value"><?= intval($total_indicados + $total_indicados_n2) ?></div>
          <div class="stat-label">Total Indicados</div>
        </div>
      </div>

      <!-- Saque de Comissão -->
      <div class="card">
        <h3><i class="fas fa-wallet"></i> Saque de Comissão</h3>
        <p style="margin-bottom: 16px; color: #8b949e;">
          Retire suas comissões acumuladas diretamente para sua conta PIX
        </p>
        <button class="btn btn-primary btn-full" onclick="location.href='sacar_comissao.php'">
          <i class="fas fa-money-bill-wave"></i>
          Sacar R$ <?= number_format($comissao, 2, ',', '.') ?>
        </button>
      </div>

      <!-- Link de Afiliado -->
      <div class="card">
        <h3><i class="fas fa-link"></i> Seu Link de Afiliado</h3>
        <p style="margin-bottom: 16px; color: #8b949e;">
          Compartilhe este link e ganhe comissão por cada pessoa que se cadastrar
        </p>
        
        <div class="affiliate-link">
          <input 
            id="linkAfiliado" 
            type="text" 
            value="https://linkplataforma.online/index/?code=<?= htmlspecialchars($codigo_convite) ?>" 
            readonly
          >
          <button class="btn btn-primary" onclick="copiarLink()">
            <i class="fas fa-copy"></i> Copiar
          </button>
        </div>

        <div class="stats-grid" style="margin-top: 20px;">
          <div class="stat-item">
            <div class="stat-value"><?= intval($total_indicados) ?></div>
            <div class="stat-label">Nível 1</div>
          </div>
          <div class="stat-item">
            <div class="stat-value"><?= intval($total_indicados_n2) ?></div>
            <div class="stat-label">Nível 2</div>
          </div>
          <div class="stat-item" style="grid-column: 1 / -1;">
            <div class="stat-value"><?= htmlspecialchars($codigo_convite) ?></div>
            <div class="stat-label">Seu Código</div>
          </div>
        </div>
      </div>

      <!-- Como Funciona -->
      <div class="card">
        <h3><i class="fas fa-question-circle"></i> Como Funciona</h3>
        <div style="display: grid; gap: 16px; margin-top: 16px;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <div style="background: linear-gradient(135deg, #fbce00, #f4c430); color: #000; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px;">1</div>
            <div>
              <div style="font-weight: 600; color: #ffffff;">Compartilhe seu link</div>
              <div style="font-size: 13px; color: #8b949e;">Envie para amigos e redes sociais</div>
            </div>
          </div>
          <div style="display: flex; align-items: center; gap: 12px;">
            <div style="background: linear-gradient(135deg, #fbce00, #f4c430); color: #000; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px;">2</div>
            <div>
              <div style="font-weight: 600; color: #ffffff;">Pessoa se cadastra</div>
              <div style="font-size: 13px; color: #8b949e;">Usando seu código de convite</div>
            </div>
          </div>
          <div style="display: flex; align-items: center; gap: 12px;">
            <div style="background: linear-gradient(135deg, #fbce00, #f4c430); color: #000; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px;">3</div>
            <div>
              <div style="font-weight: 600; color: #ffffff;">Ganhe comissão</div>
              <div style="font-size: 13px; color: #8b949e;">A cada depósito do seu indicado</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab Dados -->
    <div id="dados" class="tab-content">
      <?php if(!empty($indicados)): ?>
        <div class="section-header">
          <h2><i class="fas fa-users"></i> Indicados Nível 1</h2>
          <p><?= count($indicados) ?> pessoa(s) indicada(s) diretamente</p>
        </div>
        
        <div class="card">
          <div class="indicados-list">
            <?php foreach($indicados as $ind): ?>
              <div class="indicado-item">
                <div class="indicado-nome"><?= htmlspecialchars($ind['nome']) ?></div>
                <div class="indicado-info">📱 <?= htmlspecialchars($ind['telefone']) ?></div>
                <div class="indicado-info">💰 Depósitos: <span class="indicado-deposito">R$ <?= number_format($ind['total_depositado'], 2, ',', '.') ?></span></div>
                <div class="indicado-info">📅 Primeiro depósito: <?= $ind['data_primeiro_deposito'] ? date('d/m/Y H:i', strtotime($ind['data_primeiro_deposito'])) : 'Não realizado' ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if(!empty($indicados_n2)): ?>
        <div class="section-header">
          <h2><i class="fas fa-sitemap"></i> Indicados Nível 2</h2>
          <p><?= count($indicados_n2) ?> pessoa(s) indicada(s) pelos seus indicados</p>
        </div>
        
        <div class="card">
          <div class="indicados-list">
            <?php foreach($indicados_n2 as $ind2): ?>
              <div class="indicado-item">
                <div class="indicado-nome"><?= htmlspecialchars($ind2['nome']) ?></div>
                <div class="indicado-info">📱 <?= htmlspecialchars($ind2['telefone']) ?></div>
                <div class="indicado-info">💰 Depósitos: <span class="indicado-deposito">R$ <?= number_format($ind2['total_depositado'], 2, ',', '.') ?></span></div>
                <div class="indicado-info">📅 Primeiro depósito: <?= $ind2['data_primeiro_deposito'] ? date('d/m/Y H:i', strtotime($ind2['data_primeiro_deposito'])) : 'Não realizado' ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if(empty($indicados) && empty($indicados_n2)): ?>
        <div class="empty-state">
          <i class="fas fa-user-friends"></i>
          <h3 style="color: #8b949e; margin-bottom: 8px;">Nenhum indicado ainda</h3>
          <p>Compartilhe seu link de afiliado para começar a ganhar comissões!</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Bottom Navigation -->
  <div class="bottom-nav">
    <a href="index">
      <i class="fas fa-home"></i>
      <span>Início</span>
    </a>
    <a href="menu">
      <i class="fas fa-box"></i>
      <span>Pacotes</span>
    </a>
    <a href="deposito" class="deposit-btn">
      <i class="fas fa-credit-card"></i>
      <span>Depositar</span>
    </a>
    <a href="afiliado" class="active">
      <i class="fas fa-users"></i>
      <span>Afiliados</span>
    </a>
    <a href="perfil">
      <i class="fas fa-user"></i>
      <span>Perfil</span>
    </a>
  </div>

  <script>
    function openTab(tabName) {
      // Remove active class from all tabs and contents
      document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
      
      // Add active class to clicked tab and corresponding content
      event.target.classList.add('active');
      document.getElementById(tabName).classList.add('active');
    }

    function copiarLink() {
      const input = document.getElementById("linkAfiliado");
      input.select();
      input.setSelectionRange(0, 99999);
      
      try {
        document.execCommand("copy");
        
        // Visual feedback
        const btn = event.target.closest('.btn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
        btn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
        
        setTimeout(() => {
          btn.innerHTML = originalText;
          btn.style.background = 'linear-gradient(135deg, #fbce00, #f4c430)';
        }, 2000);
        
      } catch (err) {
        alert("Link copiado para a área de transferência!");
      }
    }

    // Animação de entrada dos cards
    document.addEventListener('DOMContentLoaded', function() {
      const cards = document.querySelectorAll('.card');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
      });
    });
  </script>
</body>
</html>