<?php
header('Content-Type: application/json');
session_start();
require 'db.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$valor = floatval($_POST['valor'] ?? 0);
$chave_pix = trim($_POST['chave_pix'] ?? '');

// Buscar limites de saque
$config = $pdo->query("SELECT min_saque, max_saque FROM configuracoes LIMIT 1")->fetch();
$min_saque = floatval($config['min_saque']);
$max_saque = floatval($config['max_saque']);

// Buscar comissão do usuário
$stmt = $pdo->prepare("SELECT comissao FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();
$comissao = floatval($usuario['comissao'] ?? 0);

if ($valor < $min_saque || $valor > $max_saque) {
    echo json_encode(['status' => 'erro', 'mensagem' => '❌ Valor fora dos limites permitidos.']);
    exit;
}

if ($valor > $comissao) {
    echo json_encode(['status' => 'erro', 'mensagem' => '❌ Comissão insuficiente.']);
    exit;
}

if (empty($chave_pix)) {
    echo json_encode(['status' => 'erro', 'mensagem' => '❌ Chave Pix inválida.']);
    exit;
}

// Buscar gateway EXPFY Pay ativo
$stmt = $pdo->prepare("SELECT client_id, client_secret, callback_url FROM gateways WHERE nome='expfypay' AND ativo=1 LIMIT 1");
$stmt->execute();
$gateway = $stmt->fetch();

if (!$gateway) {
    echo json_encode(['status' => 'erro', 'mensagem' => '❌ Gateway de saque não configurado.']);
    exit;
}

$publicKey = $gateway['client_id'];
$secretKey = $gateway['client_secret'];
$callbackUrl = str_replace('/webhook-pix.php', '/webhook_saque.php', $gateway['callback_url']);

function solicitarSaqueComissaoExpfyPay($publicKey, $secretKey, $valor, $chavePix, $callbackUrl) {
    $external_id = md5(uniqid("saque_comissao_user", true));
    
    // Detectar tipo de chave automaticamente
    $tipo_chave = 'EMAIL'; // padrão
    if (filter_var($chavePix, FILTER_VALIDATE_EMAIL)) {
        $tipo_chave = 'EMAIL';
    } elseif (preg_match('/^\d{11}$/', preg_replace('/\D/', '', $chavePix))) {
        $tipo_chave = 'CPF';
    } elseif (preg_match('/^\d{14}$/', preg_replace('/\D/', '', $chavePix))) {
        $tipo_chave = 'CNPJ';
    } elseif (preg_match('/^\d{10,11}$/', preg_replace('/\D/', '', $chavePix))) {
        $tipo_chave = 'PHONE';
    }
    
    $payload = [
        'amount' => floatval($valor),
        'pix_key' => $chavePix,
        'pix_key_type' => $tipo_chave,
        'description' => "Saque de comissão - ID: $external_id",
        'callback_url' => $callbackUrl
    ];

    $ch = curl_init('https://pro.expfypay.com/api/v1/withdrawls');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'X-Public-Key: ' . $publicKey,
            'X-Secret-Key: ' . $secretKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Log da resposta
    file_put_contents("log_saque_comissao_expfypay.txt", date('[Y-m-d H:i:s] ') . "HTTP $httpCode\nPayload: " . json_encode($payload) . "\nResposta: $response\nErro: $error\n\n", FILE_APPEND);

    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
        'external_id' => $external_id
    ];
}

// Desconta da comissão primeiro
$stmt = $pdo->prepare("UPDATE usuarios SET comissao = comissao - ? WHERE id = ?");
$stmt->execute([$valor, $usuario_id]);

// Registra o saque como pendente
$stmt = $pdo->prepare("INSERT INTO saques (usuario_id, valor, chave_pix, status, data, tipo) VALUES (?, ?, ?, 'pendente', NOW(), 'comissao')");
$stmt->execute([$usuario_id, $valor, $chave_pix]);

$saque_id = $pdo->lastInsertId();

// Solicita o saque via API
$resultado = solicitarSaqueComissaoExpfyPay($publicKey, $secretKey, $valor, $chave_pix, $callbackUrl);

if ($resultado['http_code'] === 200 || $resultado['http_code'] === 201) {
    $responseData = $resultado['response'];
    
    if (isset($responseData['transaction_id'])) {
        // Atualiza o saque com o ID da transação
        $stmt = $pdo->prepare("UPDATE saques SET transaction_id = ?, external_id = ? WHERE id = ?");
        $stmt->execute([$responseData['transaction_id'], $resultado['external_id'], $saque_id]);
        
        echo json_encode([
            'status' => 'sucesso',
            'mensagem' => '✅ Saque de comissão solicitado com sucesso! Aguardando processamento.'
        ]);
    } else {
        // Reverte o desconto da comissão se falhou
        $stmt = $pdo->prepare("UPDATE usuarios SET comissao = comissao + ? WHERE id = ?");
        $stmt->execute([$valor, $usuario_id]);
        
        // Remove o registro de saque
        $stmt = $pdo->prepare("DELETE FROM saques WHERE id = ?");
        $stmt->execute([$saque_id]);
        
        echo json_encode([
            'status' => 'erro',
            'mensagem' => '❌ Erro ao processar saque de comissão. Tente novamente.'
        ]);
    }
} else {
    // Reverte o desconto da comissão se falhou
    $stmt = $pdo->prepare("UPDATE usuarios SET comissao = comissao + ? WHERE id = ?");
    $stmt->execute([$valor, $usuario_id]);
    
    // Remove o registro de saque
    $stmt = $pdo->prepare("DELETE FROM saques WHERE id = ?");
    $stmt->execute([$saque_id]);
    
    echo json_encode([
        'status' => 'erro',
        'mensagem' => '❌ Erro ao solicitar saque de comissão. Tente novamente mais tarde.'
    ]);
}
?>
