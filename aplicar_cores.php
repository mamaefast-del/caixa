<?php
require 'db.php';

function aplicarCoresPersonalizadas() {
    global $pdo;
    
    try {
        // Buscar cores do banco
        $stmt = $pdo->query("SELECT * FROM cores_site ORDER BY id DESC LIMIT 1");
        $cores = $stmt->fetch();
        
        if (!$cores) {
            return; // Usar cores padrão
        }
        
        // Converter hex para RGB para usar em rgba()
        function hexToRgb($hex) {
            $hex = ltrim($hex, '#');
            return [
                'r' => hexdec(substr($hex, 0, 2)),
                'g' => hexdec(substr($hex, 2, 2)),
                'b' => hexdec(substr($hex, 4, 2))
            ];
        }
        
        $primaryRgb = hexToRgb($cores['cor_primaria']);
        $primaryRgbString = $primaryRgb['r'] . ', ' . $primaryRgb['g'] . ', ' . $primaryRgb['b'];
        
        // Gerar CSS dinâmico
        $css = "/* Cores personalizadas - Gerado automaticamente em " . date('Y-m-d H:i:s') . " */
:root {
    --primary-gold: {$cores['cor_primaria']};
    --secondary-gold: {$cores['cor_secundaria']};
    --primary-blue: {$cores['cor_azul']};
    --primary-green: {$cores['cor_verde']};
    --bg-dark: {$cores['cor_fundo']};
    --bg-panel: {$cores['cor_painel']};
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
        
        // Salvar CSS
        file_put_contents(__DIR__ . '/css/cores-dinamicas.css', $css);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Erro ao aplicar cores: " . $e->getMessage());
        return false;
    }
}

// Aplicar cores automaticamente quando o arquivo for incluído
aplicarCoresPersonalizadas();
?>