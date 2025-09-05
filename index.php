<?php
$duracao = 60 * 60 * 24 * 30;
session_set_cookie_params(['lifetime'=>$duracao,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.gc_maxlifetime',$duracao);
session_start();
require 'db.php';
$usuarioLogado=false;$usuario=null;
if(isset($_SESSION['usuario_id'])){
  $usuarioLogado=true;
  $stmt=$pdo->prepare("SELECT nome, saldo FROM usuarios WHERE id=?");
  $stmt->execute([$_SESSION['usuario_id']]);
  $usuario=$stmt->fetch();
}
$jsonFile=__DIR__.'/imagens_menu.json';
$imagens=file_exists($jsonFile)?json_decode(file_get_contents($jsonFile),true):[];
$banner1=$imagens['banner1']??'banner.webp';
$dadosJson=file_exists('imagens_menu.json')?json_decode(file_get_contents('imagens_menu.json'),true):[];
$logo=$dadosJson['logo']??'logo.png';
if($usuarioLogado){
  $stmt=$pdo->prepare("SELECT nome, saldo FROM usuarios WHERE id=?");
  $stmt->execute([$_SESSION['usuario_id']]);
  $usuario=$stmt->fetch();
}

// Ganhadores fake para credibilidade
$ganhadores = [
  ['nome' => 'Carlos M.', 'produto' => 'iPhone 15 Pro', 'imagem' => 'https://images.pexels.com/photos/607812/pexels-photo-607812.jpeg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 8.500', 'tempo' => '2 min atrás'],
  ['nome' => 'Ana P.', 'produto' => 'MacBook Air', 'imagem' => 'https://images.pexels.com/photos/18105/pexels-photo.jpg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 12.200', 'tempo' => '5 min atrás'],
  ['nome' => 'João S.', 'produto' => 'PlayStation 5', 'imagem' => 'https://images.pexels.com/photos/9072316/pexels-photo-9072316.jpeg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 4.500', 'tempo' => '8 min atrás'],
  ['nome' => 'Maria L.', 'produto' => 'Samsung Galaxy', 'imagem' => 'https://images.pexels.com/photos/1092644/pexels-photo-1092644.jpeg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 3.800', 'tempo' => '12 min atrás'],
  ['nome' => 'Pedro R.', 'produto' => 'AirPods Pro', 'imagem' => 'https://images.pexels.com/photos/3780681/pexels-photo-3780681.jpeg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 2.100', 'tempo' => '15 min atrás'],
  ['nome' => 'Lucia F.', 'produto' => 'Nintendo Switch', 'imagem' => 'https://images.pexels.com/photos/1298601/pexels-photo-1298601.jpeg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 2.800', 'tempo' => '18 min atrás']
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Início - Caixas</title>
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
/* Reset e base */
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

.user-actions {
  display: flex;
  align-items: center;
  gap: 12px;
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

.btn {
  padding: 10px 16px;
  border-radius: 8px;
  border: none;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
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

.btn-secondary {
  background: #1a1d24;
  color: #ffffff;
  border: 1px solid #2a2d34;
}

.btn-secondary:hover {
  background: #2a2d34;
}

/* Container principal */
.main-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

/* Banner Principal */
.hero-banner {
  margin: 24px auto;
  position: relative;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.hero-banner img {
  width: 100%;
  height: 280px;
  object-fit: cover;
  display: block;
}

.hero-banner::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(45deg, rgba(251, 206, 0, 0.1), transparent);
  pointer-events: none;
}

/* Últimos Ganhadores */
.winners-section {
  margin: 40px auto;
  background: #111318;
  border-radius: 16px;
  padding: 24px;
  border: 1px solid #1a1d24;
}

.winners-header {
  text-align: center;
  margin-bottom: 24px;
}

.winners-header h2 {
  color: #fbce00;
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.winners-header p {
  color: #8b949e;
  font-size: 14px;
}

.winners-grid {
  display: flex;
  overflow-x: auto;
  gap: 16px;
  padding: 8px 0;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.winners-grid::-webkit-scrollbar {
  display: none;
}

.winner-card {
  background: #0d1117;
  border: 1px solid #21262d;
  border-radius: 12px;
  padding: 16px;
  min-width: 200px;
  flex-shrink: 0;
  position: relative;
  overflow: hidden;
  text-align: center;
  transition: all 0.3s ease;
}

.winner-card::before {
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

.winner-card:hover {
  border-color: #fbce00;
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(251, 206, 0, 0.2);
}

.winner-product-image {
  width: 80px;
  height: 80px;
  object-fit: cover;
  border-radius: 12px;
  margin: 0 auto 12px;
  display: block;
  border: 2px solid #fbce00;
}

.winner-details h4 {
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 4px;
  color: #ffffff;
}

.winner-details .product-name {
  font-size: 12px;
  color: #8b949e;
  margin-bottom: 8px;
}

.winner-details small {
  color: #8b949e;
  font-size: 10px;
}

.winner-prize {
  color: #fbce00;
  font-weight: 700;
  font-size: 16px;
  margin-top: 8px;
}

/* Seção de Pacotes */
.packages-section {
  margin: 40px auto;
}

.section-header {
  margin-bottom: 24px;
}

.section-header h2 {
  color: #ffffff;
  font-size: 28px;
  font-weight: 800;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.section-header p {
  color: #8b949e;
  font-size: 16px;
}

.packages-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
}

.package-card {
  background: #111318;
  border-radius: 16px;
  overflow: hidden;
  transition: all 0.3s ease;
  position: relative;
  border: 2px solid transparent;
  background-clip: padding-box;
}

.package-card::before {
  content: '';
  position: absolute;
  inset: 0;
  padding: 2px;
  background: linear-gradient(135deg, #fbce00, #f4c430, #fbce00);
  border-radius: 16px;
  mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  mask-composite: xor;
  -webkit-mask-composite: xor;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.package-card:hover::before {
  opacity: 1;
}

.package-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(251, 206, 0, 0.2);
}

.package-image {
  position: relative;
  height: 200px;
  overflow: hidden;

  display: flex;               /* ativa flexbox */
  justify-content: center;     /* centraliza horizontal */
  align-items: center;         /* centraliza vertical */
}

.package-image img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain; /* mantém proporção sem cortar */
}


.package-card:hover .package-image img {
  transform: scale(1.05);
}

.hot-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  background: linear-gradient(135deg, #ff4444, #ff6600);
  color: white;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  gap: 4px;
  box-shadow: 0 2px 8px rgba(255, 68, 68, 0.4);
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

.package-price {
  position: absolute;
  top: 12px;
  right: 12px;
  background: rgba(0, 0, 0, 0.8);
  color: #fbce00;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 800;
  border: 1px solid #fbce00;
  backdrop-filter: blur(10px);
}

.package-info {
  padding: 20px;
  text-align: center;
}

.package-title {
  font-weight: 700;
  color: #ffffff;
  font-size: 16px;
  margin-bottom: 4px;
}

.package-subtitle {
  color: #8b949e;
  font-size: 12px;
}

/* Como funciona */
.how-it-works {
  margin: 60px auto;
  background: #111318;
  border-radius: 16px;
  padding: 40px 24px;
  border: 1px solid #1a1d24;
}

.how-it-works h2 {
  color: #fbce00;
  font-size: 14px;
  font-weight: 600;
  text-align: center;
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.how-it-works h3 {
  color: #ffffff;
  font-size: 32px;
  font-weight: 800;
  text-align: center;
  margin-bottom: 12px;
}

.how-it-works > p {
  text-align: center;
  color: #8b949e;
  font-size: 16px;
  margin-bottom: 32px;
}

.steps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 24px;
}

.step-card {
  background: #0d1117;
  border: 1px solid #21262d;
  border-radius: 12px;
  padding: 24px;
  text-align: center;
  transition: all 0.3s ease;
  position: relative;
}

.step-card:hover {
  border-color: #fbce00;
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(251, 206, 0, 0.1);
}

.step-number {
  background: linear-gradient(135deg, #fbce00, #f4c430);
  color: #000;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 18px;
  margin: 0 auto 16px;
}

.step-card h4 {
  color: #ffffff;
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 8px;
}

.step-card p {
  color: #8b949e;
  font-size: 14px;
  line-height: 1.6;
  margin-bottom: 16px;
}

.step-image {
  border-radius: 8px;
  overflow: hidden;
  background: #0a0b0f;
}

.step-image img {
  width: 100%;
  height: auto;
  display: block;
}

/* Footer */
.footer-info {
  background: #111318;
  text-align: center;
  padding: 40px 20px;
  margin-top: 60px;
  border-top: 1px solid #1a1d24;
}

.footer-info .logo img {
  height: 36px;
  margin-bottom: 16px;
  filter: brightness(1.1);
}

.footer-info p {
  color: #8b949e;
  margin: 8px 0;
  font-size: 14px;
}

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

/* Modal */
.modal {
  display: none;
  position: fixed;
  z-index: 9999;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  backdrop-filter: blur(8px);
  justify-content: center;
  align-items: center;
  animation: fadeIn 0.3s ease;
}

.modal.show {
  display: flex;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.modal-content {
  background: #111318;
  border: 1px solid #1a1d24;
  border-radius: 16px;
  width: 100%;
  max-width: 420px;
  padding: 32px 24px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
  position: relative;
}

.modal .close {
  position: absolute;
  top: 16px;
  right: 20px;
  font-size: 24px;
  color: #8b949e;
  cursor: pointer;
  transition: color 0.2s ease;
}

.modal .close:hover {
  color: #fbce00;
}

.tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 24px;
  background: #0d1117;
  padding: 4px;
  border-radius: 12px;
}

.tabs button {
  flex: 1;
  padding: 12px;
  border: none;
  background: transparent;
  color: #8b949e;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-weight: 600;
  font-size: 14px;
}

.tabs button.active,
.tabs button:hover {
  background: #fbce00;
  color: #000;
}

.input-group {
  position: relative;
  margin-bottom: 16px;
}

.input-group .icon {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: #8b949e;
  font-size: 16px;
  pointer-events: none;
}

.input-group input {
  width: 100%;
  padding: 14px 16px 14px 48px;
  border-radius: 8px;
  border: 1px solid #21262d;
  background: #0d1117;
  color: #ffffff;
  font-size: 14px;
  transition: border-color 0.2s ease;
}

.input-group input:focus {
  border-color: #fbce00;
  outline: none;
  box-shadow: 0 0 0 3px rgba(251, 206, 0, 0.1);
}

.input-group input::placeholder {
  color: #8b949e;
}

.btn-full {
  width: 100%;
  padding: 14px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 700;
  margin-top: 8px;
}

#auth-msg {
  margin-top: 16px;
  color: #ff6b6b;
  text-align: center;
  font-weight: 600;
  font-size: 14px;
}

.confirm-modal {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 10000;
  background: rgba(0, 0, 0, 0.8);
  backdrop-filter: blur(8px);
  justify-content: center;
  align-items: center;
}

.confirm-modal.show {
  display: flex;
}

.confirm-box {
  width: min(92vw, 480px);
  background: #111318;
  border: 1px solid #1a1d24;
  border-radius: 16px;
  padding: 32px 24px;
  color: #ffffff;
  text-align: center;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
}

.confirm-box h4 {
  margin: 0 0 12px;
  color: #ffffff;
  font-size: 20px;
  font-weight: 700;
}

.confirm-box p {
  margin: 0 0 24px;
  color: #8b949e;
  line-height: 1.6;
}

.confirm-actions {
  display: flex;
  gap: 12px;
  flex-direction: column;
}

@media (min-width: 520px) {
  .confirm-actions {
    flex-direction: row;
  }
}

.btn-ghost {
  background: #0d1117;
  border: 1px solid #21262d;
  color: #8b949e;
}

.btn-ghost:hover {
  background: #1a1d24;
  color: #ffffff;
}

/* Responsivo */
@media (max-width: 768px) {
  .main-container {
    padding: 0 16px;
  }
  
  .hero-banner {
    margin: 16px auto 32px;
  }
  
  .hero-banner img {
    height: 200px;
  }
  
  .packages-grid {
    grid-template-columns: 1fr;
  }
  
  .steps-grid {
    grid-template-columns: 1fr;
  }
  
  .header-content {
    padding: 0 4px;
  }
  
  .user-actions {
    gap: 8px;
  }
  
  .btn {
    padding: 8px 12px;
    font-size: 13px;
  }
  
  .winner-card {
    min-width: 180px;
  }
  
  .winner-product-image {
    width: 60px;
    height: 60px;
  }
}

/* Animações de entrada */
.package-card {
  animation: slideInUp 0.6s ease forwards;
  opacity: 0;
}

.package-card:nth-child(1) { animation-delay: 0.1s; }
.package-card:nth-child(2) { animation-delay: 0.2s; }
.package-card:nth-child(3) { animation-delay: 0.3s; }
.package-card:nth-child(4) { animation-delay: 0.4s; }
.package-card:nth-child(5) { animation-delay: 0.5s; }

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

.winner-card {
  animation: slideInLeft 0.6s ease forwards;
  opacity: 0;
}

.winner-card:nth-child(1) { animation-delay: 0.1s; }
.winner-card:nth-child(2) { animation-delay: 0.2s; }
.winner-card:nth-child(3) { animation-delay: 0.3s; }
.winner-card:nth-child(4) { animation-delay: 0.4s; }
.winner-card:nth-child(5) { animation-delay: 0.5s; }
.winner-card:nth-child(6) { animation-delay: 0.6s; }

@keyframes slideInLeft {
  from {
    opacity: 0;
    transform: translateX(-20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}
  </style>
</head>
<body>
<script>
const urlParams=new URLSearchParams(window.location.search);
const codigoConvite=urlParams.get('codigo');
if(codigoConvite){localStorage.setItem('codigo_convite',codigoConvite);}
</script>

<!-- Header -->
<div class="header">
  <div class="header-content">
    <div class="logo">
      <img src="images/<?= $logo ?>?v=<?= time() ?>" alt="Logo">
    </div>
    <div class="user-actions">
      <?php if($usuarioLogado):?>
        <span class="saldo">R$ <?= number_format($usuario['saldo'],2,',','.') ?></span>
        <button class="btn btn-primary btn-depositar" onclick='window.location.href="deposito.php"'>
          <i class="fas fa-plus"></i> Recarregar
        </button>
      <?php else:?>
        <button class="btn btn-secondary" id="btn-open-login">
          <i class="fas fa-sign-in-alt"></i> Entrar
        </button>
        <button class="btn btn-primary" id="btn-open-register">
          <i class="fas fa-user-plus"></i> Cadastre-se
        </button>
      <?php endif;?>
    </div>
  </div>
</div>

<div class="main-container">
  <!-- Banner Principal -->
  <div class="hero-banner">
    <a href="/menu">
      <img src="images/<?= $banner1 ?>?v=<?= filemtime("images/$banner1") ?>" alt="Banner Principal">
    </a>
  </div>

  <!-- Últimos Ganhadores -->
  <div class="winners-section">
    <div class="winners-header">
      <h2><i class="fas fa-trophy"></i> Últimos Ganhadores</h2>
      <p>Veja quem acabou de ganhar prêmios incríveis!</p>
    </div>
    <div class="winners-grid">
      <?php foreach($ganhadores as $index => $ganhador): ?>
        <div class="winner-card">
          <img src="<?= $ganhador['imagem'] ?>" alt="<?= $ganhador['produto'] ?>" class="winner-product-image">
          <div class="winner-details">
            <h4><?= $ganhador['nome'] ?></h4>
            <div class="product-name"><?= $ganhador['produto'] ?></div>
            <small><?= $ganhador['tempo'] ?></small>
          </div>
          <div class="winner-prize"><?= $ganhador['valor'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Pacotes Premium -->
  <div class="packages-section">
    <div class="section-header">
      <h2><i class="fas fa-gift"></i> CAIXAS PREMIOS</h2>
      <p>Escolha sua caixa e tenha a chance de ganhar prêmios incríveis</p>
    </div>
    <div class="packages-grid">
      <?php
        $imagensMenu=file_exists('imagens_menu.json')?json_decode(file_get_contents('imagens_menu.json'),true):[];
        $raspadinhas=[
          ["id"=>1,"imagem"=>isset($imagensMenu['menu1'])?"images/".$imagensMenu['menu1']."?v=".filemtime("images/".$imagensMenu['menu1']):"images/menu1.png","valor"=>"R$ 1,00","premio"=>"R$ 2.000,00","hot"=>true],
          ["id"=>2,"imagem"=>isset($imagensMenu['menu2'])?"images/".$imagensMenu['menu2']."?v=".filemtime("images/".$imagensMenu['menu2']):"images/menu2.png","valor"=>"R$ 3,00","premio"=>"R$ 1.200,00","hot"=>false],
          ["id"=>3,"imagem"=>isset($imagensMenu['menu3'])?"images/".$imagensMenu['menu3']."?v=".filemtime("images/".$imagensMenu['menu3']):"images/menu3.png","valor"=>"R$ 15,00","premio"=>"R$ 5.000,00","hot"=>true],
          ["id"=>4,"imagem"=>isset($imagensMenu['menu4'])?"images/".$imagensMenu['menu4']."?v=".filemtime("images/".$imagensMenu['menu4']):"images/menu4.png","valor"=>"R$ 25,00","premio"=>"R$ 8.000,00","hot"=>true],
          ["id"=>5,"imagem"=>isset($imagensMenu['menu5'])?"images/".$imagensMenu['menu5']."?v=".filemtime("images/".$imagensMenu['menu4']):"images/menu5.png","valor"=>"R$ 50,00","premio"=>"R$ 180.000,00","hot"=>true],

        ];
        foreach($raspadinhas as $r){
          echo '
          <div class="package-card">
            <a href="roleta.php?id='.$r['id'].'" style="text-decoration:none;color:inherit;">
              <div class="package-image">
                '.($r['hot'] ? '<div class="hot-badge"><i class="fas fa-fire"></i> HOT</div>' : '').'
                <img src="'.$r['imagem'].'" alt="Pacote">
                <span class="package-price">'.$r['valor'].'</span>
              </div>
              <div class="package-info">
                <div class="package-title">PRÊMIOS ATÉ '.$r['premio'].'</div>
                <div class="package-subtitle">'.($r['nome']??'Pacote Premium').'</div>
              </div>
            </a>
          </div>';
        }
      ?>
    </div>
  </div>

  <!-- Como Funciona -->
  <div class="how-it-works">
    <h2>Como funciona</h2>
    <h3>É MUITO SIMPLES</h3>
    <p>Abrir um pacote é bem fácil! Veja o passo a passo para começar agora</p>
    <div class="steps-grid">
      <div class="step-card">
        <div class="step-number">1</div>
        <h4><i class="fas fa-wallet"></i> Deposite</h4>
        <p>Clique no botão amarelo no canto superior do site e escolha a quantia ideal para fazer seu depósito.</p>
        <div class="step-image"><img src="images/h1.webp" alt="Deposite"></div>
      </div>
      <div class="step-card">
        <div class="step-number">2</div>
        <h4><i class="fas fa-gift"></i> Escolha um Pacote</h4>
        <p>Encontre o pacote ou uma raspadinha perfeita para você e clique em abrir.</p>
        <div class="step-image"><img src="images/h2.webp" alt="Escolha um pacote"></div>
      </div>
      <div class="step-card">
        <div class="step-number">3</div>
        <h4><i class="fas fa-mouse-pointer"></i> Clique em abrir</h4>
        <p>Após escolher sua premiação desejada, clique em abrir.</p>
        <div class="step-image"><img src="images/h3.webp" alt="Clique em abrir"></div>
      </div>
      <div class="step-card">
        <div class="step-number">4</div>
        <h4><i class="fas fa-trophy"></i> Aproveite!</h4>
        <p>Parabéns! Agora você pode retirar o valor no PIX ou enviar o produto para sua casa.</p>
        <div class="step-image"><img src="images/h4.webp" alt="Aproveite!"></div>
      </div>
    </div>
  </div>
</div>

<!-- Footer Info -->
<div class="footer-info">
  <div class="logo">
    <img src="images/<?= $logo ?>?v=<?= time() ?>" alt="Logo">
  </div>
  <p>A maior e melhor plataforma de premiações do Brasil</p>
  <p>© 2025 Show de prêmios! Todos os direitos reservados.</p>
</div>

<!-- Bottom Navigation -->
<div class="bottom-nav">
  <a href="index" class="active">
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
  <a href="afiliado">
    <i class="fas fa-users"></i>
    <span>Afiliados</span>
  </a>
  <a href="perfil">
    <i class="fas fa-user"></i>
    <span>Perfil</span>
  </a>
</div>

<?php if(!$usuarioLogado):?>
<!-- Modal de Autenticação -->
<div id="modal-auth" class="modal">
  <div class="modal-content">
    <span class="close" onclick="fecharModal()">&times;</span>
    <div class="tabs">
      <button id="tab-login" class="active" onclick="mostrarTab('login')">Entrar</button>
      <button id="tab-register" onclick="mostrarTab('register')">Criar conta</button>
    </div>
    <div id="form-login" class="tab-content">
      <form id="loginForm">
        <div class="input-group">
          <span class="icon"><i class="fa fa-envelope"></i></span>
          <input type="text" name="telefone" placeholder="E-mail ou Telefone" required>
        </div>
        <div class="input-group">
          <span class="icon"><i class="fa fa-lock"></i></span>
          <input type="password" name="senha" placeholder="Senha" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full">
          <i class="fas fa-sign-in-alt"></i> Entrar na minha conta
        </button>
      </form>
    </div>
    <div id="form-register" class="tab-content" style="display:none;">
      <form id="registerForm">
        <div class="input-group">
          <span class="icon"><i class="fa fa-user"></i></span>
          <input type="text" name="nome" placeholder="Nome completo" required>
        </div>
        <div class="input-group">
          <span class="icon"><i class="fa fa-phone"></i></span>
          <input type="text" name="telefone" placeholder="(00) 00000-0000" required>
        </div>
        <div class="input-group">
          <span class="icon"><i class="fa fa-envelope"></i></span>
          <input type="email" name="email" placeholder="E-mail" required>
        </div>
        <div class="input-group">
          <span class="icon"><i class="fa fa-lock"></i></span>
          <input type="password" name="senha" placeholder="Senha" required>
        </div>
        <div class="input-group">
          <span class="icon"><i class="fa fa-gift"></i></span>
          <input type="text" name="codigo_convite" placeholder="Código de convite (opcional)" value="<?= htmlspecialchars($_GET['code'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary btn-full">
          Continuar <i class="fas fa-arrow-right"></i>
        </button>
      </form>
    </div>
    <div id="auth-msg"></div>
  </div>
</div>

<!-- Modal de Confirmação -->
<div id="confirm-cancel" class="confirm-modal">
  <div class="confirm-box">
    <h4>Tem certeza que deseja cancelar seu registro?</h4>
    <p>Cadastre-se agora e tenha a chance de ganhar bônus e rodadas grátis!</p>
    <div class="confirm-actions">
      <button id="btn-retomar-cadastro" class="btn btn-primary">
        <i class="fas fa-arrow-right"></i> Continuar
      </button>
      <button id="btn-cancelar-cadastro" class="btn btn-ghost">
        <i class="fas fa-times"></i> Sim, quero cancelar
      </button>
    </div>
  </div>
</div>
<?php endif;?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
$(function(){
  $('#loginForm input[name="telefone"], #registerForm input[name="telefone"]').mask('(00) 00000-0000');
});

const usuarioLogado=<?= $usuarioLogado?'true':'false' ?>;

document.querySelectorAll('a[data-requer-login]').forEach(l=>{
  l.addEventListener('click',function(e){
    if(!usuarioLogado){
      e.preventDefault();
      openAuth('login');
    }
  })
});

document.querySelectorAll('.btn-depositar').forEach(b=>{
  b.addEventListener('click',function(e){
    if(!usuarioLogado){
      e.preventDefault();
      openAuth('login');
    }
  })
});

const btnOpenLogin=document.getElementById('btn-open-login');
const btnOpenRegister=document.getElementById('btn-open-register');
if(btnOpenLogin){btnOpenLogin.addEventListener('click',()=>openAuth('login'));} 
if(btnOpenRegister){btnOpenRegister.addEventListener('click',()=>openAuth('register'));} 

let cadastroEmAndamento=false;

function openAuth(tab){
  mostrarTab(tab);
  document.getElementById('modal-auth').classList.add('show');
}

function fecharModal(){
  const regTab=document.getElementById('form-register');
  const isRegisterVisible=regTab&&regTab.style.display!=='none';
  if(isRegisterVisible&&cadastroEmAndamento){
    const c=document.getElementById('confirm-cancel');
    if(c){c.classList.add('show');}
    return;
  }
  const modal=document.getElementById('modal-auth');
  if(modal){modal.classList.remove('show');}
}

function mostrarTab(tab){
  const isLogin=(tab==='login');
  document.getElementById('form-login').style.display=isLogin?'block':'none';
  document.getElementById('form-register').style.display=isLogin?'none':'block';
  const bLogin=document.getElementById('tab-login'),bReg=document.getElementById('tab-register');
  if(bLogin&&bReg){
    bLogin.classList.toggle('active',isLogin);
    bReg.classList.toggle('active',!isLogin);
  }
  const msg=document.getElementById('auth-msg');
  if(msg)msg.innerText='';
  if(!isLogin){
    const campoCodigo=document.querySelector('#registerForm input[name="codigo_convite"]');
    const valorSalvo=localStorage.getItem('codigo_convite');
    if(campoCodigo&&valorSalvo){campoCodigo.value=valorSalvo;}
  }
}

document.addEventListener('DOMContentLoaded',function(){
  const regForm=document.getElementById('registerForm');
  if(regForm){
    regForm.addEventListener('input',()=>{cadastroEmAndamento=true;});
    regForm.addEventListener('submit',()=>{cadastroEmAndamento=false;});
  }
  const b1=document.getElementById('btn-retomar-cadastro');
  const b2=document.getElementById('btn-cancelar-cadastro');
  if(b1){
    b1.addEventListener('click',()=>{
      document.getElementById('confirm-cancel').classList.remove('show');
    });
  }
  if(b2){
    b2.addEventListener('click',()=>{
      cadastroEmAndamento=false;
      document.getElementById('confirm-cancel').classList.remove('show');
      const m=document.getElementById('modal-auth');
      if(m){m.classList.remove('show');}
    });
  }
});

// Simulação de novos ganhadores em tempo real
function atualizarGanhadores() {
  const nomes = ['Ricardo M.', 'Fernanda S.', 'Gabriel L.', 'Patricia R.', 'Bruno C.', 'Camila F.'];
  const produtos = ['iPhone 15 Pro', 'MacBook Air', 'PlayStation 5', 'Samsung Galaxy', 'AirPods Pro', 'Nintendo Switch'];
  const valores = ['R$ 5.200', 'R$ 8.900', 'R$ 15.600', 'R$ 3.400', 'R$ 22.100', 'R$ 11.800'];
  
  const ganhadorItems = document.querySelectorAll('.winner-card');
  if (ganhadorItems.length > 0) {
    const randomIndex = Math.floor(Math.random() * ganhadorItems.length);
    const item = ganhadorItems[randomIndex];
    
    const nome = nomes[Math.floor(Math.random() * nomes.length)];
    const produto = produtos[Math.floor(Math.random() * produtos.length)];
    const valor = valores[Math.floor(Math.random() * valores.length)];
    
    // Atualizar o conteúdo
    const nomeElement = item.querySelector('.winner-details h4');
    const produtoElement = item.querySelector('.winner-details .product-name');
    const valorElement = item.querySelector('.winner-prize');
    const tempoElement = item.querySelector('.winner-details small');
    
    if (nomeElement && produtoElement && valorElement && tempoElement) {
      nomeElement.textContent = nome;
      produtoElement.textContent = produto;
      valorElement.textContent = valor;
      tempoElement.textContent = 'Agora mesmo';
      
      // Destacar o item atualizado
      item.style.borderColor = '#fbce00';
      item.style.boxShadow = '0 4px 16px rgba(251, 206, 0, 0.3)';
      
      setTimeout(() => {
        item.style.borderColor = '#21262d';
        item.style.boxShadow = 'none';
      }, 3000);
    }
  }
}

// Atualizar ganhadores a cada 8-15 segundos
setInterval(atualizarGanhadores, Math.random() * 7000 + 8000);

// Scroll horizontal suave para os ganhadores
const winnersGrid = document.querySelector('.winners-grid');
if (winnersGrid) {
  let isScrolling = false;
  
  function autoScroll() {
    if (!isScrolling) {
      winnersGrid.scrollBy({
        left: 220,
        behavior: 'smooth'
      });
      
      // Reset scroll quando chegar ao final
      if (winnersGrid.scrollLeft >= winnersGrid.scrollWidth - winnersGrid.clientWidth - 50) {
        setTimeout(() => {
          winnersGrid.scrollTo({
            left: 0,
            behavior: 'smooth'
          });
        }, 2000);
      }
    }
  }
  
  // Auto scroll a cada 4 segundos
  setInterval(autoScroll, 4000);
  
  // Pausar auto scroll quando usuário interage
  winnersGrid.addEventListener('mouseenter', () => isScrolling = true);
  winnersGrid.addEventListener('mouseleave', () => isScrolling = false);
  winnersGrid.addEventListener('touchstart', () => isScrolling = true);
  winnersGrid.addEventListener('touchend', () => setTimeout(() => isScrolling = false, 3000));
}
</script>

<script>
$(function(){
  $('#loginForm').submit(function(e){
    e.preventDefault();
    $.ajax({
      url:'login_ajax.php',
      type:'POST',
      data:$(this).serialize(),
      success:function(r){
        if(r.trim()==='success'){
          location.reload();
        }else{
          $('#auth-msg').text(r);
        }
      },
      error:function(){
        $('#auth-msg').text('Erro ao processar requisição. Tente novamente.');
      }
    });
  });
  
  $('#registerForm').submit(function(e){
    e.preventDefault();
    $.ajax({
      url:'register_ajax.php',
      method:'POST',
      data:$(this).serialize(),
      success:function(r){
        if(r.trim()==='success'){
          window.location.href='deposito';
        }else{
          $('#auth-msg').text(r);
        }
      },
      error:function(){
        $('#auth-msg').text('Erro ao processar requisição. Tente novamente.');
      }
    });
  });
});
</script>
</body>
</html>