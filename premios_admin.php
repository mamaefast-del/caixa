<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
if (!isset($_SESSION['admin_id'])) {
    header("Location: login_admin.php");
    exit;
}
require 'db.php';

// Buscar configurações das raspadinhas
$stmt = $pdo->query("SELECT * FROM raspadinhas_config ORDER BY id");
$raspadinhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar formulário de atualização
$mensagem = '';
$activeTab = 'configuracoes'; // Default tab
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Capturar a aba ativa para manter após o submit
        if (isset($_POST['active_tab'])) {
            $activeTab = $_POST['active_tab'];
        }
        
        try {
            switch ($_POST['action']) {
                case 'update_config':
                    $id = $_POST['raspadinha_id'];
                    $nome = $_POST['nome'];
                    $valor = $_POST['valor'];
                    $chance_ganho = $_POST['chance_ganho'];
                    $ativa = isset($_POST['ativa']) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("UPDATE raspadinhas_config SET nome = ?, valor = ?, chance_ganho = ?, ativa = ? WHERE id = ?");
                    $stmt->execute([$nome, $valor, $chance_ganho, $ativa, $id]);
                    
                    $mensagem = "<div class='message success'><i class='fas fa-check-circle'></i> Configuração atualizada com sucesso!</div>";
                    break;
                    
                case 'add_premio':
                    $activeTab = 'premios'; // Manter na aba de prêmios
                    $raspadinha_id = $_POST['raspadinha_id'];
                    $nome_premio = $_POST['nome_premio'];
                    $valor_premio = $_POST['valor_premio'];
                    $chance_premio = floatval($_POST['chance_premio']); // Alterado para aceitar decimais
                    
                    // Processar upload da imagem
                    $imagem_premio = null;
                    if (isset($_FILES['imagem_premio']) && $_FILES['imagem_premio']['error'] === 0) {
                        $diretorio = __DIR__ . '/images/premios';
                        if (!is_dir($diretorio)) {
                            mkdir($diretorio, 0755, true);
                        }
                        
                        $extensao = strtolower(pathinfo($_FILES['imagem_premio']['name'], PATHINFO_EXTENSION));
                        $formatosPermitidos = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
                        
                        if (in_array($extensao, $formatosPermitidos)) {
                            $nomeArquivo = 'premio_' . $raspadinha_id . '_' . time() . '.' . $extensao;
                            $destino = $diretorio . '/' . $nomeArquivo;
                            
                            if (move_uploaded_file($_FILES['imagem_premio']['tmp_name'], $destino)) {
                                $imagem_premio = 'images/premios/' . $nomeArquivo;
                            }
                        }
                    }
                    
                    // Buscar prêmios existentes
                    $stmt = $pdo->prepare("SELECT premios_json FROM raspadinhas_config WHERE id = ?");
                    $stmt->execute([$raspadinha_id]);
                    $config = $stmt->fetch();
                    
                    $premios = json_decode($config['premios_json'], true) ?: [];
                    
                    // Adicionar novo prêmio
                    $premios[] = [
                        'nome' => $nome_premio,
                        'valor' => floatval($valor_premio),
                        'chance' => $chance_premio, // Mantém como float
                        'imagem' => $imagem_premio
                    ];
                    
                    // Atualizar no banco
                    $stmt = $pdo->prepare("UPDATE raspadinhas_config SET premios_json = ? WHERE id = ?");
                    $stmt->execute([json_encode($premios), $raspadinha_id]);
                    
                    $mensagem = "<div class='message success'><i class='fas fa-plus-circle'></i> Prêmio adicionado com sucesso!</div>";
                    break;
                    
                case 'edit_premio':
                    $activeTab = 'premios'; // Manter na aba de prêmios
                    $raspadinha_id = $_POST['raspadinha_id'];
                    $premio_index = $_POST['premio_index'];
                    $nome_premio = $_POST['nome_premio'];
                    $valor_premio = $_POST['valor_premio'];
                    $chance_premio = floatval($_POST['chance_premio']); // Alterado para aceitar decimais
                    
                    // Buscar prêmios existentes
                    $stmt = $pdo->prepare("SELECT premios_json FROM raspadinhas_config WHERE id = ?");
                    $stmt->execute([$raspadinha_id]);
                    $config = $stmt->fetch();
                    
                    $premios = json_decode($config['premios_json'], true) ?: [];
                    
                    if (isset($premios[$premio_index])) {
                        // Processar upload da nova imagem se fornecida
                        $imagem_premio = $premios[$premio_index]['imagem'] ?? null;
                        
                        if (isset($_FILES['imagem_premio']) && $_FILES['imagem_premio']['error'] === 0) {
                            $diretorio = __DIR__ . '/images/premios';
                            if (!is_dir($diretorio)) {
                                mkdir($diretorio, 0755, true);
                            }
                            
                            $extensao = strtolower(pathinfo($_FILES['imagem_premio']['name'], PATHINFO_EXTENSION));
                            $formatosPermitidos = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
                            
                            if (in_array($extensao, $formatosPermitidos)) {
                                // Remover imagem antiga se existir
                                if ($imagem_premio && file_exists(__DIR__ . '/' . $imagem_premio)) {
                                    unlink(__DIR__ . '/' . $imagem_premio);
                                }
                                
                                $nomeArquivo = 'premio_' . $raspadinha_id . '_' . time() . '.' . $extensao;
                                $destino = $diretorio . '/' . $nomeArquivo;
                                
                                if (move_uploaded_file($_FILES['imagem_premio']['tmp_name'], $destino)) {
                                    $imagem_premio = 'images/premios/' . $nomeArquivo;
                                }
                            }
                        }
                        
                        // Atualizar prêmio
                        $premios[$premio_index] = [
                            'nome' => $nome_premio,
                            'valor' => floatval($valor_premio),
                            'chance' => $chance_premio, // Mantém como float
                            'imagem' => $imagem_premio
                        ];
                        
                        // Atualizar no banco
                        $stmt = $pdo->prepare("UPDATE raspadinhas_config SET premios_json = ? WHERE id = ?");
                        $stmt->execute([json_encode($premios), $raspadinha_id]);
                        
                        $mensagem = "<div class='message success'><i class='fas fa-edit'></i> Prêmio editado com sucesso!</div>";
                    }
                    break;
                    
                case 'remove_premio':
                    $activeTab = 'premios'; // Manter na aba de prêmios
                    $raspadinha_id = $_POST['raspadinha_id'];
                    $premio_index = $_POST['premio_index'];
                    
                    // Buscar prêmios existentes
                    $stmt = $pdo->prepare("SELECT premios_json FROM raspadinhas_config WHERE id = ?");
                    $stmt->execute([$raspadinha_id]);
                    $config = $stmt->fetch();
                    
                    $premios = json_decode($config['premios_json'], true) ?: [];
                    
                    // Remover prêmio
                    if (isset($premios[$premio_index])) {
                        // Remover arquivo de imagem se existir
                        if (isset($premios[$premio_index]['imagem']) && file_exists(__DIR__ . '/' . $premios[$premio_index]['imagem'])) {
                            unlink(__DIR__ . '/' . $premios[$premio_index]['imagem']);
                        }
                        unset($premios[$premio_index]);
                        $premios = array_values($premios); // Reindexar array
                    }
                    
                    // Atualizar no banco
                    $stmt = $pdo->prepare("UPDATE raspadinhas_config SET premios_json = ? WHERE id = ?");
                    $stmt->execute([json_encode($premios), $raspadinha_id]);
                    
                    $mensagem = "<div class='message success'><i class='fas fa-trash'></i> Prêmio removido com sucesso!</div>";
                    break;
                    
                case 'update_immersion':
                    $activeTab = 'imersao'; // Manter na aba de imersão
                    $raspadinha_id = $_POST['raspadinha_id'];
                    $imersao_ativa = isset($_POST['imersao_ativa']) ? 1 : 0;
                    $valor_arrecadar = floatval($_POST['valor_arrecadar']);
                    $percentual_retorno = floatval($_POST['percentual_retorno']);
                    $modo_distribuicao = $_POST['modo_distribuicao'];
                    
                    // Buscar configuração atual
                    $stmt = $pdo->prepare("SELECT imersao_config FROM raspadinhas_config WHERE id = ?");
                    $stmt->execute([$raspadinha_id]);
                    $result = $stmt->fetch();
                    
                    $imersao_config = [
                        'ativa' => $imersao_ativa,
                        'valor_arrecadar' => $valor_arrecadar,
                        'valor_arrecadado' => $result ? (json_decode($result['imersao_config'], true)['valor_arrecadado'] ?? 0) : 0,
                        'percentual_retorno' => $percentual_retorno,
                        'modo_distribuicao' => $modo_distribuicao,
                        'status' => $result ? (json_decode($result['imersao_config'], true)['status'] ?? 'arrecadando') : 'arrecadando'
                    ];
                    
                    // Atualizar no banco
                    $stmt = $pdo->prepare("UPDATE raspadinhas_config SET imersao_config = ? WHERE id = ?");
                    $stmt->execute([json_encode($imersao_config), $raspadinha_id]);
                    
                    $mensagem = "<div class='message success'><i class='fas fa-cog'></i> Configuração de imersão atualizada com sucesso!</div>";
                    break;
                    
                case 'reset_immersion':
                    $activeTab = 'imersao'; // Manter na aba de imersão
                    $raspadinha_id = $_POST['raspadinha_id'];
                    
                    // Resetar imersão
                    $stmt = $pdo->prepare("SELECT imersao_config FROM raspadinhas_config WHERE id = ?");
                    $stmt->execute([$raspadinha_id]);
                    $result = $stmt->fetch();
                    
                    if ($result) {
                        $imersao_config = json_decode($result['imersao_config'], true) ?: [];
                        $imersao_config['valor_arrecadado'] = 0;
                        $imersao_config['status'] = 'arrecadando';
                        
                        $stmt = $pdo->prepare("UPDATE raspadinhas_config SET imersao_config = ? WHERE id = ?");
                        $stmt->execute([json_encode($imersao_config), $raspadinha_id]);
                    }
                    
                    $mensagem = "<div class='message success'><i class='fas fa-refresh'></i> Imersão resetada com sucesso!</div>";
                    break;
                    
                case 'force_distribute':
                    $activeTab = 'imersao'; // Manter na aba de imersão
                    $raspadinha_id = $_POST['raspadinha_id'];
                    
                    // Forçar distribuição
                    $stmt = $pdo->prepare("SELECT imersao_config FROM raspadinhas_config WHERE id = ?");
                    $stmt->execute([$raspadinha_id]);
                    $result = $stmt->fetch();
                    
                    if ($result) {
                        $imersao_config = json_decode($result['imersao_config'], true) ?: [];
                        $imersao_config['status'] = 'distribuindo';
                        
                        $stmt = $pdo->prepare("UPDATE raspadinhas_config SET imersao_config = ? WHERE id = ?");
                        $stmt->execute([json_encode($imersao_config), $raspadinha_id]);
                    }
                    
                    $mensagem = "<div class='message success'><i class='fas fa-play'></i> Distribuição de prêmios iniciada!</div>";
                    break;
            }
            
            // Recarregar dados
            $stmt = $pdo->query("SELECT * FROM raspadinhas_config ORDER BY id");
            $raspadinhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $mensagem = "<div class='message error'><i class='fas fa-exclamation-circle'></i> Erro: " . $e->getMessage() . "</div>";
        }
    }
}

// Função para calcular estatísticas de imersão
function getImmersionStats($raspadinha) {
    $imersao = json_decode($raspadinha['imersao_config'] ?? '{}', true);
    
    $stats = [
        'ativa' => $imersao['ativa'] ?? false,
        'valor_arrecadar' => $imersao['valor_arrecadar'] ?? 0,
        'valor_arrecadado' => $imersao['valor_arrecadado'] ?? 0,
        'percentual_retorno' => $imersao['percentual_retorno'] ?? 70,
        'modo_distribuicao' => $imersao['modo_distribuicao'] ?? 'gradual',
        'status' => $imersao['status'] ?? 'arrecadando'
    ];
    
    $stats['progresso'] = $stats['valor_arrecadar'] > 0 ? 
        min(100, ($stats['valor_arrecadado'] / $stats['valor_arrecadar']) * 100) : 0;
    
    $stats['valor_distribuir'] = ($stats['valor_arrecadado'] * $stats['percentual_retorno']) / 100;
    
    return $stats;
}

// Função para formatar chance com decimais
function formatChance($chance) {
    if ($chance < 0.01) {
        return number_format($chance, 6);
    } elseif ($chance < 1) {
        return number_format($chance, 3);
    } else {
        return number_format($chance, 1);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gerenciar Prêmios - Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
      padding-top: 80px;
    }

    /* Header */
    .header {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 80px;
      background: #111318;
      border-bottom: 1px solid #1a1d24;
      padding: 0 32px;
      z-index: 100;
      backdrop-filter: blur(20px);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .header::before {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 2px;
      background: linear-gradient(180deg, #fbce00, #f4c430, #fbce00);
      opacity: 0.8;
    }

    .header .logo {
      color: #fbce00;
      font-size: 24px;
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }

    .header .logo::before {
      content: '⚡';
      font-size: 20px;
    }

    .nav-menu {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .nav-menu a {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 12px 16px;
      background: #0d1117;
      color: #ffffff;
      border: 1px solid #21262d;
      border-radius: 8px;
      text-decoration: none;
      transition: all 0.3s ease;
      font-weight: 500;
      font-size: 13px;
      position: relative;
      overflow: hidden;
      white-space: nowrap;
    }

    .nav-menu a::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(251, 206, 0, 0.1), transparent);
      transition: left 0.5s ease;
    }

    .nav-menu a:hover::before {
      left: 100%;
    }

    .nav-menu a:hover,
    .nav-menu a.active {
      background: linear-gradient(135deg, #fbce00, #f4c430);
      color: #000000;
      border-color: #fbce00;
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(251, 206, 0, 0.3);
    }

    .nav-menu a i {
      font-size: 14px;
      width: 20px;
      text-align: center;
    }

    .nav-menu a .nav-text {
      display: inline;
    }

    /* Content */
    .content {
      padding: 32px;
      min-height: 100vh;
    }

    h1 {
      color: #ffffff;
      font-size: 32px;
      font-weight: 800;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    h1::before {
      content: '🎁';
      font-size: 28px;
    }

    .subtitle {
      color: #8b949e;
      font-size: 16px;
      margin-bottom: 40px;
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
      transform: translateY(-4px);
      box-shadow: 0 8px 32px rgba(251, 206, 0, 0.2);
    }

    .card h3 {
      color: #fbce00;
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Forms */
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 16px;
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 16px;
    }

    .form-group label {
      color: #ffffff;
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 8px;
      display: block;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 12px 16px;
      background: #0d1117;
      border: 1px solid #21262d;
      border-radius: 8px;
      color: #ffffff;
      font-size: 14px;
      transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      border-color: #fbce00;
      outline: none;
      box-shadow: 0 0 0 3px rgba(251, 206, 0, 0.1);
    }

    .checkbox-group {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 8px;
    }

    .checkbox-group input[type="checkbox"] {
      width: auto;
      margin: 0;
    }

    /* Buttons */
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

    .btn-danger {
      background: linear-gradient(135deg, #ef4444, #dc2626);
      color: #ffffff;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    }

    .btn-small {
      padding: 8px 12px;
      font-size: 12px;
      margin-right: 4px;
    }

    .btn-success {
      background: linear-gradient(135deg, #22c55e, #16a34a);
      color: #ffffff;
      box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
    }

    .btn-success:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(34, 197, 94, 0.4);
    }

    /* Tables */
    .table-container {
      background: #0d1117;
      border: 1px solid #21262d;
      border-radius: 12px;
      overflow: hidden;
      margin-top: 16px;
    }

    .table {
      width: 100%;
      border-collapse: collapse;
    }

    .table th,
    .table td {
      padding: 12px 16px;
      text-align: left;
      border-bottom: 1px solid #21262d;
    }

    .table th {
      background: #111318;
      color: #fbce00;
      font-weight: 700;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .table td {
      color: #ffffff;
      font-size: 14px;
    }

    .table tr:hover {
      background: rgba(251, 206, 0, 0.05);
    }

    /* Image Preview */
    .image-preview {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 8px;
      border: 2px solid #fbce00;
    }

    .no-image {
      width: 60px;
      height: 60px;
      background: #21262d;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #8b949e;
      font-size: 12px;
    }

    /* Modal */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(5px);
    }

    .modal-content {
      background: #111318;
      margin: 5% auto;
      padding: 32px;
      border: 1px solid #1a1d24;
      border-radius: 16px;
      width: 90%;
      max-width: 600px;
      position: relative;
      animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
      from {
        opacity: 0;
        transform: translateY(-50px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      padding-bottom: 16px;
      border-bottom: 1px solid #1a1d24;
    }

    .modal-header h3 {
      color: #fbce00;
      font-size: 20px;
      font-weight: 700;
      margin: 0;
    }

    .close {
      color: #8b949e;
      font-size: 24px;
      font-weight: bold;
      cursor: pointer;
      transition: color 0.3s ease;
    }

    .close:hover {
      color: #fbce00;
    }

    .file-input-wrapper {
      position: relative;
      display: inline-block;
      width: 100%;
    }

    .file-input-wrapper input[type="file"] {
      position: absolute;
      opacity: 0;
      width: 100%;
      height: 100%;
      cursor: pointer;
    }

    .file-input-label {
      display: block;
      padding: 12px 16px;
      background: #0d1117;
      border: 1px solid #21262d;
      border-radius: 8px;
      color: #8b949e;
      cursor: pointer;
      transition: all 0.3s ease;
      text-align: center;
    }

    .file-input-label:hover {
      border-color: #fbce00;
      color: #fbce00;
    }

    .file-input-label i {
      margin-right: 8px;
    }

    /* Status badges */
    .status-badge {
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .status-ativa {
      background: rgba(34, 197, 94, 0.2);
      color: #22c55e;
    }

    .status-inativa {
      background: rgba(239, 68, 68, 0.2);
      color: #ef4444;
    }

    /* Messages */
    .message {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .message.success {
      background: rgba(34, 197, 94, 0.1);
      border: 1px solid rgba(34, 197, 94, 0.3);
      color: #22c55e;
    }

    .message.error {
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #ef4444;
    }

    /* Tabs */
    .tabs {
      display: flex;
      margin-bottom: 24px;
      background: #0d1117;
      border-radius: 12px;
      padding: 4px;
    }

    .tab {
      flex: 1;
      padding: 12px 16px;
      background: transparent;
      border: none;
      color: #8b949e;
      font-weight: 600;
      cursor: pointer;
      border-radius: 8px;
      transition: all 0.3s ease;
    }

    .tab.active {
      background: linear-gradient(135deg, #fbce00, #f4c430);
      color: #000;
    }

    .tab-content {
      display: none;
    }

    .tab-content.active {
      display: block;
      animation: fadeInUp 0.4s ease;
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

    /* Progress Bar */
    .progress-container {
      background: #0d1117;
      border-radius: 8px;
      padding: 4px;
      margin: 12px 0;
      border: 1px solid #21262d;
    }

    .progress-bar {
      height: 24px;
      background: linear-gradient(90deg, #fbce00, #f4c430);
      border-radius: 6px;
      transition: width 0.5s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 700;
      color: #000;
      position: relative;
      overflow: hidden;
    }

    .progress-bar::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
      animation: progressShine 2s infinite;
    }

    @keyframes progressShine {
      0% { left: -100%; }
      100% { left: 100%; }
    }

    .progress-text {
      position: absolute;
      width: 100%;
      text-align: center;
      font-size: 12px;
      font-weight: 700;
      color: #ffffff;
      z-index: 2;
    }

    /* Status Indicators */
    .status-indicator {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .status-arrecadando {
      background: rgba(59, 130, 246, 0.2);
      color: #3b82f6;
      border: 1px solid rgba(59, 130, 246, 0.3);
    }

    .status-distribuindo {
      background: rgba(251, 206, 0, 0.2);
      color: #fbce00;
      border: 1px solid rgba(251, 206, 0, 0.3);
    }

    .status-pausado {
      background: rgba(156, 163, 175, 0.2);
      color: #9ca3af;
      border: 1px solid rgba(156, 163, 175, 0.3);
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin: 20px 0;
    }

    .stat-card {
      background: #0d1117;
      border: 1px solid #21262d;
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      transition: all 0.3s ease;
    }

    .stat-card:hover {
      border-color: #fbce00;
      transform: translateY(-2px);
    }

    .stat-value {
      font-size: 24px;
      font-weight: 800;
      color: #fbce00;
      margin-bottom: 4px;
    }

    .stat-label {
      font-size: 12px;
      color: #8b949e;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Action Buttons */
    .action-buttons {
      display: flex;
      gap: 8px;
      margin-top: 16px;
      flex-wrap: wrap;
    }

    .btn-warning {
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: #ffffff;
      box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
    }

    .btn-warning:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
    }

    .btn-info {
      background: linear-gradient(135deg, #3b82f6, #2563eb);
      color: #ffffff;
      box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    }

    .btn-info:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }

    /* Input helpers */
    .input-helper {
      font-size: 12px;
      color: #8b949e;
      margin-top: 4px;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    /* Responsive */
    @media (max-width: 1024px) {
      .header {
        padding: 0 20px;
      }

      .nav-menu {
        gap: 4px;
      }

      .nav-menu a {
        padding: 10px 12px;
        font-size: 12px;
      }

      .nav-menu a .nav-text {
        display: none;
      }

      .content {
        padding: 20px;
      }

      .form-grid {
        grid-template-columns: 1fr;
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
  </style>
</head>

<body>
  <div class="header">
    <a href="painel_admin.php" class="logo">Admin Panel</a>
    <nav class="nav-menu">
      <a href="painel_admin.php">
        <i class="fas fa-chart-bar"></i>
        <span class="nav-text">Painel</span>
      </a>
      <a href="configuracoes_admin.php">
        <i class="fas fa-cog"></i>
        <span class="nav-text">Config</span>
      </a>
      <a href="usuarios_admin.php">
        <i class="fas fa-users"></i>
        <span class="nav-text">Usuários</span>
      </a>
      <a href="saques_admin.php">
        <i class="fas fa-money-bill-wave"></i>
        <span class="nav-text">Saques</span>
      </a>
      <a href="saques_comissao_admin.php">
        <i class="fas fa-percentage"></i>
        <span class="nav-text">Afiliado</span>
      </a>
      <a href="gateways_admin.php">
        <i class="fas fa-credit-card"></i>
        <span class="nav-text">Gateways</span>
      </a>
      <a href="pix_admin.php">
        <i class="fas fa-exchange-alt"></i>
        <span class="nav-text">PIX</span>
      </a>
      <a href="premios_admin.php" class="active">
        <i class="fas fa-gift"></i>
        <span class="nav-text">Prêmios</span>
      </a>
    </nav>
  </div>

  <div class="content">
    <h1>Gerenciar Prêmios</h1>
    <p class="subtitle">Configure os prêmios e chances de ganho das caixas</p>

    <?= $mensagem ?>

    <!-- Tabs -->
    <div class="tabs">
      <button class="tab <?= $activeTab === 'configuracoes' ? 'active' : '' ?>" onclick="openTab('configuracoes')">
        <i class="fas fa-cog"></i> Configurações
      </button>
      <button class="tab <?= $activeTab === 'premios' ? 'active' : '' ?>" onclick="openTab('premios')">
        <i class="fas fa-gift"></i> Gerenciar Prêmios
      </button>
      <button class="tab <?= $activeTab === 'imersao' ? 'active' : '' ?>" onclick="openTab('imersao')">
        <i class="fas fa-chart-line"></i> Sistema de Imersão
      </button>
    </div>

    <!-- Tab Configurações -->
    <div id="configuracoes" class="tab-content <?= $activeTab === 'configuracoes' ? 'active' : '' ?>">
      <?php foreach ($raspadinhas as $raspadinha): ?>
        <div class="card">
          <h3>
            <i class="fas fa-box"></i>
            <?= htmlspecialchars($raspadinha['nome']) ?>
            <span class="status-badge <?= $raspadinha['ativa'] ? 'status-ativa' : 'status-inativa' ?>">
              <?= $raspadinha['ativa'] ? 'Ativa' : 'Inativa' ?>
            </span>
          </h3>
          
          <form method="POST">
            <input type="hidden" name="action" value="update_config">
            <input type="hidden" name="raspadinha_id" value="<?= $raspadinha['id'] ?>">
            <input type="hidden" name="active_tab" value="configuracoes">
            
            <div class="form-grid">
              <div class="form-group">
                <label>Nome da Caixa</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($raspadinha['nome']) ?>" required>
              </div>
              
              <div class="form-group">
                <label>Valor da Caixa (R$)</label>
                <input type="number" step="0.01" name="valor" value="<?= $raspadinha['valor'] ?>" required>
              </div>
              
              <div class="form-group">
                <label>Chance de Ganho (%)</label>
                <input type="number" step="0.001" min="0" max="100" name="chance_ganho" value="<?= $raspadinha['chance_ganho'] ?>" required>
                <div class="input-helper">
                  <i class="fas fa-info-circle"></i>
                  Aceita valores decimais: ex. 0.001% para chances muito baixas
                </div>
              </div>
              
              <div class="form-group">
                <div class="checkbox-group">
                  <input type="checkbox" name="ativa" id="ativa_<?= $raspadinha['id'] ?>" <?= $raspadinha['ativa'] ? 'checked' : '' ?>>
                  <label for="ativa_<?= $raspadinha['id'] ?>">Caixa Ativa</label>
                </div>
              </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> Salvar Configurações
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Tab Prêmios -->
    <div id="premios" class="tab-content <?= $activeTab === 'premios' ? 'active' : '' ?>">
      <?php foreach ($raspadinhas as $raspadinha): ?>
        <div class="card">
          <h3>
            <i class="fas fa-gift"></i>
            Prêmios - <?= htmlspecialchars($raspadinha['nome']) ?>
          </h3>
          
          <!-- Adicionar Novo Prêmio -->
          <form method="POST" enctype="multipart/form-data" style="margin-bottom: 20px;">
            <input type="hidden" name="action" value="add_premio">
            <input type="hidden" name="raspadinha_id" value="<?= $raspadinha['id'] ?>">
            <input type="hidden" name="active_tab" value="premios">
            
            <div class="form-grid">
              <div class="form-group">
                <label>Nome do Prêmio</label>
                <input type="text" name="nome_premio" placeholder="Ex: iPhone 15, R$ 100, Não foi dessa vez" required>
              </div>
              
              <div class="form-group">
                <label>Valor do Prêmio (R$)</label>
                <input type="number" step="0.01" name="valor_premio" placeholder="0.00" required>
              </div>
              
              <div class="form-group">
                <label>Chance (%)</label>
                <input type="number" step="0.001" min="0" max="100" name="chance_premio" placeholder="10 ou 0.001" required>
                <div class="input-helper">
                  <i class="fas fa-percentage"></i>
                  Exemplos: 50% = 50, 0.1% = 0.1, 0.001% = 0.001
                </div>
              </div>
              
              <div class="form-group">
                <label>Imagem do Prêmio (Opcional)</label>
                <div class="file-input-wrapper">
                  <input type="file" name="imagem_premio" accept="image/*" id="imagem_premio_<?= $raspadinha['id'] ?>">
                  <label for="imagem_premio_<?= $raspadinha['id'] ?>" class="file-input-label">
                    <i class="fas fa-image"></i>
                    Selecionar Imagem
                  </label>
                </div>
              </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-plus"></i> Adicionar Prêmio
            </button>
          </form>
          
          <!-- Lista de Prêmios -->
          <?php 
          $premios = json_decode($raspadinha['premios_json'], true) ?: [];
          if (!empty($premios)): 
          ?>
            <div class="table-container">
              <table class="table">
                <thead>
                  <tr>
                    <th>Imagem</th>
                    <th>Nome do Prêmio</th>
                    <th>Valor (R$)</th>
                    <th>Chance (%)</th>
                    <th>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($premios as $index => $premio): ?>
                    <tr>
                      <td>
                        <?php if (isset($premio['imagem']) && $premio['imagem'] && file_exists(__DIR__ . '/' . $premio['imagem'])): ?>
                          <img src="<?= $premio['imagem'] ?>?v=<?= time() ?>" alt="<?= htmlspecialchars($premio['nome']) ?>" class="image-preview">
                        <?php else: ?>
                          <div class="no-image">
                            <i class="fas fa-image"></i>
                          </div>
                        <?php endif; ?>
                      </td>
                      <td><?= htmlspecialchars($premio['nome']) ?></td>
                      <td>R$ <?= number_format($premio['valor'], 2, ',', '.') ?></td>
                      <td><?= formatChance($premio['chance']) ?>%</td>
                      <td>
                        <button type="button" class="btn btn-success btn-small" onclick="openEditModal(<?= $raspadinha['id'] ?>, <?= $index ?>, '<?= htmlspecialchars($premio['nome'], ENT_QUOTES) ?>', <?= $premio['valor'] ?>, <?= $premio['chance'] ?>, '<?= $premio['imagem'] ?? '' ?>')">
                          <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" style="display: inline;">
                          <input type="hidden" name="action" value="remove_premio">
                          <input type="hidden" name="raspadinha_id" value="<?= $raspadinha['id'] ?>">
                          <input type="hidden" name="premio_index" value="<?= $index ?>">
                          <input type="hidden" name="active_tab" value="premios">
                          <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Tem certeza que deseja remover este prêmio?')">
                            <i class="fas fa-trash"></i>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p style="color: #8b949e; text-align: center; padding: 20px;">
              <i class="fas fa-gift" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>
              Nenhum prêmio configurado para esta caixa
            </p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Tab Imersão -->
    <div id="imersao" class="tab-content <?= $activeTab === 'imersao' ? 'active' : '' ?>">
      <?php foreach ($raspadinhas as $raspadinha): 
        $stats = getImmersionStats($raspadinha);
      ?>
        <div class="card">
          <h3>
            <i class="fas fa-chart-line"></i>
            Sistema de Imersão - <?= htmlspecialchars($raspadinha['nome']) ?>
            <span class="status-indicator status-<?= $stats['status'] ?>">
              <?php if ($stats['status'] === 'arrecadando'): ?>
                <i class="fas fa-arrow-down"></i> Arrecadando
              <?php elseif ($stats['status'] === 'distribuindo'): ?>
                <i class="fas fa-arrow-up"></i> Distribuindo
              <?php else: ?>
                <i class="fas fa-pause"></i> Pausado
              <?php endif; ?>
            </span>
          </h3>
          
          <!-- Configurações de Imersão -->
          <form method="POST" style="margin-bottom: 24px;">
            <input type="hidden" name="action" value="update_immersion">
            <input type="hidden" name="raspadinha_id" value="<?= $raspadinha['id'] ?>">
            <input type="hidden" name="active_tab" value="imersao">
            
            <div class="form-grid">
              <div class="form-group">
                <div class="checkbox-group">
                  <input type="checkbox" name="imersao_ativa" id="imersao_ativa_<?= $raspadinha['id'] ?>" <?= $stats['ativa'] ? 'checked' : '' ?>>
                  <label for="imersao_ativa_<?= $raspadinha['id'] ?>">Ativar Sistema de Imersão</label>
                </div>
              </div>
              
              <div class="form-group">
                <label>Valor para Arrecadar (R$)</label>
                <input type="number" step="0.01" name="valor_arrecadar" value="<?= $stats['valor_arrecadar'] ?>" placeholder="1000.00" required>
              </div>
              
              <div class="form-group">
                <label>Percentual de Retorno (%)</label>
                <input type="number" min="1" max="100" name="percentual_retorno" value="<?= $stats['percentual_retorno'] ?>" placeholder="70" required>
              </div>
              
              <div class="form-group">
                <label>Modo de Distribuição</label>
                <select name="modo_distribuicao" required>
                  <option value="gradual" <?= $stats['modo_distribuicao'] === 'gradual' ? 'selected' : '' ?>>Gradual (Distribuição lenta)</option>
                  <option value="rapido" <?= $stats['modo_distribuicao'] === 'rapido' ? 'selected' : '' ?>>Rápido (Distribuição acelerada)</option>
                  <option value="burst" <?= $stats['modo_distribuicao'] === 'burst' ? 'selected' : '' ?>>Burst (Tudo de uma vez)</option>
                </select>
              </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> Salvar Configurações
            </button>
          </form>
          
          <!-- Estatísticas -->
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-value">R$ <?= number_format($stats['valor_arrecadado'], 2, ',', '.') ?></div>
              <div class="stat-label">Arrecadado</div>
            </div>
            
            <div class="stat-card">
              <div class="stat-value">R$ <?= number_format($stats['valor_arrecadar'], 2, ',', '.') ?></div>
              <div class="stat-label">Meta</div>
            </div>
            
            <div class="stat-card">
              <div class="stat-value"><?= number_format($stats['progresso'], 1) ?>%</div>
              <div class="stat-label">Progresso</div>
            </div>
            
            <div class="stat-card">
              <div class="stat-value">R$ <?= number_format($stats['valor_distribuir'], 2, ',', '.') ?></div>
              <div class="stat-label">A Distribuir</div>
            </div>
          </div>
          
          <!-- Barra de Progresso -->
          <div class="progress-container">
            <div class="progress-text">
              <?= number_format($stats['progresso'], 1) ?>% - R$ <?= number_format($stats['valor_arrecadado'], 2, ',', '.') ?> / R$ <?= number_format($stats['valor_arrecadar'], 2, ',', '.') ?>
            </div>
            <div class="progress-bar" style="width: <?= $stats['progresso'] ?>%">
              <?php if ($stats['progresso'] > 20): ?>
                <?= number_format($stats['progresso'], 1) ?>%
              <?php endif; ?>
            </div>
          </div>
          
          <!-- Ações -->
          <div class="action-buttons">
            <?php if ($stats['status'] === 'arrecadando' && $stats['progresso'] >= 100): ?>
              <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="force_distribute">
                <input type="hidden" name="raspadinha_id" value="<?= $raspadinha['id'] ?>">
                <input type="hidden" name="active_tab" value="imersao">
                <button type="submit" class="btn btn-success">
                  <i class="fas fa-play"></i> Iniciar Distribuição
                </button>
              </form>
            <?php endif; ?>
            
            <form method="POST" style="display: inline;">
              <input type="hidden" name="action" value="reset_immersion">
              <input type="hidden" name="raspadinha_id" value="<?= $raspadinha['id'] ?>">
              <input type="hidden" name="active_tab" value="imersao">
              <button type="submit" class="btn btn-warning" onclick="return confirm('Tem certeza que deseja resetar a imersão? Isso zerará o valor arrecadado.')">
                <i class="fas fa-refresh"></i> Resetar Imersão
              </button>
            </form>
          </div>
          
          <!-- Explicação do Sistema -->
          <div style="margin-top: 24px; padding: 16px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px;">
            <h4 style="color: #3b82f6; margin-bottom: 8px; font-size: 14px;">
              <i class="fas fa-info-circle"></i> Como funciona o Sistema de Imersão:
            </h4>
            <ul style="color: #8b949e; font-size: 13px; line-height: 1.6; margin-left: 20px;">
              <li><strong>Fase 1 - Arrecadação:</strong> O sistema coleta apostas até atingir o valor definido</li>
              <li><strong>Fase 2 - Distribuição:</strong> Libera prêmios baseado no percentual de retorno configurado</li>
              <li><strong>Gradual:</strong> Distribui prêmios lentamente ao longo do tempo</li>
              <li><strong>Rápido:</strong> Distribui prêmios de forma acelerada</li>
              <li><strong>Burst:</strong> Libera todos os prêmios de uma só vez</li>
            </ul>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Modal de Edição -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-edit"></i> Editar Prêmio</h3>
        <span class="close" onclick="closeEditModal()">&times;</span>
      </div>
      
      <form method="POST" enctype="multipart/form-data" id="editForm">
        <input type="hidden" name="action" value="edit_premio">
        <input type="hidden" name="raspadinha_id" id="edit_raspadinha_id">
        <input type="hidden" name="premio_index" id="edit_premio_index">
        <input type="hidden" name="active_tab" value="premios">
        
        <div class="form-grid">
          <div class="form-group">
            <label>Nome do Prêmio</label>
            <input type="text" name="nome_premio" id="edit_nome_premio" required>
          </div>
          
          <div class="form-group">
            <label>Valor do Prêmio (R$)</label>
            <input type="number" step="0.01" name="valor_premio" id="edit_valor_premio" required>
          </div>
          
          <div class="form-group">
            <label>Chance (%)</label>
            <input type="number" step="0.001" min="0" max="100" name="chance_premio" id="edit_chance_premio" required>
            <div class="input-helper">
              <i class="fas fa-percentage"></i>
              Aceita valores decimais: ex. 0.001% para chances muito baixas
            </div>
          </div>
          
          <div class="form-group">
            <label>Nova Imagem (Opcional)</label>
            <div class="file-input-wrapper">
              <input type="file" name="imagem_premio" accept="image/*" id="edit_imagem_premio">
              <label for="edit_imagem_premio" class="file-input-label">
                <i class="fas fa-image"></i>
                Alterar Imagem
              </label>
            </div>
            <div id="current_image_preview" style="margin-top: 12px;"></div>
          </div>
        </div>
        
        <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
          <button type="button" class="btn" style="background: #21262d; color: #8b949e;" onclick="closeEditModal()">
            <i class="fas fa-times"></i> Cancelar
          </button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Salvar Alterações
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <script>
    // Variável para armazenar a aba ativa
    let currentActiveTab = '<?= $activeTab ?>';

    function openTab(tabName) {
      // Atualizar variável da aba ativa
      currentActiveTab = tabName;
      
      // Remove active class from all tabs and contents
      document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
      
      // Add active class to clicked tab and corresponding content
      event.target.classList.add('active');
      document.getElementById(tabName).classList.add('active');
    }

    function openEditModal(raspadinhaId, premioIndex, nome, valor, chance, imagem) {
      document.getElementById('edit_raspadinha_id').value = raspadinhaId;
      document.getElementById('edit_premio_index').value = premioIndex;
      document.getElementById('edit_nome_premio').value = nome;
      document.getElementById('edit_valor_premio').value = valor;
      document.getElementById('edit_chance_premio').value = chance;
      
      // Mostrar preview da imagem atual
      const previewDiv = document.getElementById('current_image_preview');
      if (imagem && imagem !== '') {
        previewDiv.innerHTML = `
          <div style="display: flex; align-items: center; gap: 8px; color: #8b949e; font-size: 12px;">
            <img src="${imagem}?v=${Date.now()}" alt="Imagem atual" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #21262d;">
            <span>Imagem atual</span>
          </div>
        `;
      } else {
        previewDiv.innerHTML = '<div style="color: #8b949e; font-size: 12px;"><i class="fas fa-info-circle"></i> Nenhuma imagem atual</div>';
      }
      
      document.getElementById('editModal').style.display = 'block';
    }

    function closeEditModal() {
      document.getElementById('editModal').style.display = 'none';
      document.getElementById('editForm').reset();
    }

    // Fechar modal ao clicar fora dele
    window.onclick = function(event) {
      const modal = document.getElementById('editModal');
      if (event.target === modal) {
        closeEditModal();
      }
    }

    // Auto-hide messages after 5 seconds
    setTimeout(() => {
      const messages = document.querySelectorAll('.message');
      messages.forEach(message => {
        message.style.opacity = '0';
        message.style.transform = 'translateY(-10px)';
        setTimeout(() => message.remove(), 300);
      });
    }, 5000);

    // Animação de entrada dos cards
    document.addEventListener('DOMContentLoaded', function() {
      const cards = document.querySelectorAll('.card');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
      });
    });

    // Preview de imagem ao selecionar arquivo
    document.querySelectorAll('input[type="file"]').forEach(input => {
      input.addEventListener('change', function() {
        const label = this.nextElementSibling;
        if (this.files && this.files[0]) {
          const fileName = this.files[0].name;
          label.innerHTML = `<i class="fas fa-check"></i> ${fileName}`;
          label.style.color = '#fbce00';
          label.style.borderColor = '#fbce00';
        } else {
          label.innerHTML = '<i class="fas fa-image"></i> Selecionar Imagem';
          label.style.color = '#8b949e';
          label.style.borderColor = '#21262d';
        }
      });
    });

    // Adicionar campo hidden para manter aba ativa em todos os formulários
    document.addEventListener('DOMContentLoaded', function() {
      // Adicionar listeners nos formulários para manter a aba ativa
      document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
          // Adicionar campo hidden se não existir
          if (!this.querySelector('input[name="active_tab"]')) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'active_tab';
            hiddenInput.value = currentActiveTab;
            this.appendChild(hiddenInput);
          } else {
            // Atualizar valor se já existe
            this.querySelector('input[name="active_tab"]').value = currentActiveTab;
          }
        });
      });
    });
  </script>
</body>
</html>