<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

try {
    $cor_primaria = $input['primaria'] ?? '#fbce00';
    $cor_secundaria = $input['secundaria'] ?? '#f4c430';
    $cor_azul = $input['azul'] ?? '#007fdb';
    $cor_verde = $input['verde'] ?? '#00e880';
    $cor_fundo = $input['fundo'] ?? '#0a0b0f';
    $cor_painel = $input['painel'] ?? '#111318';
    
    // Validar formato hexadecimal
    $cores = [$cor_primaria, $cor_secundaria, $cor_azul, $cor_verde, $cor_fundo, $cor_painel];
    foreach ($cores as $cor) {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $cor)) {
            throw new Exception('Formato de cor inválido: ' . $cor);
        }
    }
    
    // Verificar se registro existe
    $stmt = $pdo->query("SELECT COUNT(*) FROM cores_site");
    $exists = $stmt->fetchColumn() > 0;
    
    if ($exists) {
        $stmt = $pdo->prepare("UPDATE cores_site SET cor_primaria = ?, cor_secundaria = ?, cor_azul = ?, cor_verde = ?, cor_fundo = ?, cor_painel = ? WHERE id = 1");
        $stmt->execute([$cor_primaria, $cor_secundaria, $cor_azul, $cor_verde, $cor_fundo, $cor_painel]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO cores_site (cor_primaria, cor_secundaria, cor_azul, cor_verde, cor_fundo, cor_painel) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cor_primaria, $cor_secundaria, $cor_azul, $cor_verde, $cor_fundo, $cor_painel]);
    }
    
    // Gerar CSS dinâmico
    gerarCSSPersonalizado($cor_primaria, $cor_secundaria, $cor_azul, $cor_verde, $cor_fundo, $cor_painel);
    
    echo json_encode(['success' => true, 'message' => 'Cores atualizadas com sucesso!']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function gerarCSSPersonalizado($primaria, $secundaria, $azul, $verde, $fundo, $painel) {
    // Converter hex para RGB
    function hexToRgb($hex) {
        $hex = ltrim($hex, '#');
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
    
    $primaryRgb = hexToRgb($primaria);
    $primaryRgbString = $primaryRgb['r'] . ', ' . $primaryRgb['g'] . ', ' . $primaryRgb['b'];
    
    $css = "/* Cores personalizadas - Gerado automaticamente em " . date('Y-m-d H:i:s') . " */
:root {
    --primary-gold: {$primaria};
    --secondary-gold: {$secundaria};
    --primary-blue: {$azul};
    --primary-green: {$verde};
    --bg-dark: {$fundo};
    --bg-panel: {$painel};
    --primary-gold-rgb: {$primaryRgbString};
}

/* Aplicação das cores personalizadas */
.btn-primary, 
.btn-depositar, 
.saldo, 
.generate-btn,
.btn-jogar,
.btn-copiar,
.quick-amount:hover,
.btn-continuar,
.login-button,
.btn-full {
    background: linear-gradient(135deg, var(--primary-gold), var(--secondary-gold)) !important;
    color: #000 !important;
}

.footer a.active, 
.tab.active, 
i.active,
.bottom-nav a.active,
.bottom-nav a:hover {
    color: var(--primary-blue) !important;
}

.footer a.deposito-btn,
.footer a.deposit-btn,
.bottom-nav .deposit-btn {
    background: var(--primary-blue) !important;
    color: #fff !important;
}

.btn-verde, 
.status-aprovado,
.btn-success {
    background: var(--primary-green) !important;
    color: #000 !important;
}

body {
    background: var(--bg-dark) !important;
}

.header, 
.card, 
.container,
.deposit-form,
.history-section,
.winners-section,
.packages-section .package-card,
.how-it-works,
.game-container,
.modal-content,
.login-container {
    background: var(--bg-panel) !important;
}

.text-primary,
.title,
.welcome-title,
.stat-value,
.transaction-value,
.winner-prize,
.package-price,
.step-number,
.modal-content h2,
.form-title h1,
.section-header h2,
.winners-header h2 {
    color: var(--primary-gold) !important;
}

.border-primary,
.package-card::before,
.card::before {
    border-color: var(--primary-gold) !important;
}

/* Gradientes e sombras */
.btn-primary:hover,
.btn-depositar:hover,
.generate-btn:hover,
.btn-jogar:hover,
.login-button:hover {
    box-shadow: 0 6px 20px rgba(var(--primary-gold-rgb), 0.4) !important;
}

/* Inputs e formulários */
.form-input:focus,
.input-group input:focus,
.search-input:focus,
.select-input:focus,
.input-box:focus {
    border-color: var(--primary-gold) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-gold-rgb), 0.1) !important;
}

/* Status e badges */
.status-pendente {
    color: var(--primary-gold) !important;
    background: rgba(var(--primary-gold-rgb), 0.15) !important;
}

/* Efeitos hover */
.card:hover,
.stat-card:hover,
.package-card:hover,
.winner-card:hover,
.transaction-item:hover {
    border-color: var(--primary-gold) !important;
    box-shadow: 0 8px 24px rgba(var(--primary-gold-rgb), 0.1) !important;
}

/* Elementos específicos */
.highlight,
.highlight2 {
    color: var(--primary-gold) !important;
}

.valor-label,
.codigo-afiliado {
    color: var(--primary-gold) !important;
    background: rgba(var(--primary-gold-rgb), 0.15) !important;
}

/* Navegação */
.nav-item.active,
.logo {
    color: var(--primary-gold) !important;
}

/* Modais e overlays */
.modal-overlay {
    backdrop-filter: blur(8px);
}

/* Animações e efeitos */
@keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}

.card::before,
.package-card::before {
    background: linear-gradient(90deg, transparent, var(--primary-gold), transparent) !important;
}
";
    
    file_put_contents(__DIR__ . '/css/cores-dinamicas.css', $css);
}
?>