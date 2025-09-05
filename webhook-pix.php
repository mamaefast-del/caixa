<?php
require 'db.php';
header('Content-Type: application/json; charset=utf-8');

// ============ Sistema de Log Melhorado ============
function logWebhook($message, $level = 'INFO') {
    $timestamp = date('[Y-m-d H:i:s]');
    $logEntry = "$timestamp [$level] $message" . PHP_EOL;
    file_put_contents("log_webhook_expfypay.txt", $logEntry, FILE_APPEND);
}

logWebhook("=== INÍCIO DO WEBHOOK ===");
logWebhook("Método: " . $_SERVER['REQUEST_METHOD']);
logWebhook("User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A'));
logWebhook("IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A'));

// Headers completos
$headers = getallheaders();
foreach($headers as $key => $value) {
    if (strtolower($key) !== 'x-signature') {
        logWebhook("Header $key: $value");
    }
}

// Leia o corpo cru
$raw = file_get_contents('php://input');
logWebhook("Payload recebido: " . $raw);
logWebhook("Tamanho do payload: " . strlen($raw) . " bytes");

// ============ Validação de Método ============
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logWebhook("ERRO: Método inválido - esperado POST, recebido " . $_SERVER['REQUEST_METHOD'], 'ERROR');
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// ============ Validação de Assinatura ============
$signatureRecebida = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

if (empty($signatureRecebida)) {
    logWebhook("ERRO: Header X-Signature não encontrado", 'ERROR');
    http_response_code(401);
    echo json_encode(['error' => 'Assinatura não fornecida']);
    exit;
}

logWebhook("Assinatura recebida: " . substr($signatureRecebida, 0, 10) . "...");

// Buscar configuração do gateway
try {
    $stmt = $pdo->prepare("SELECT client_secret FROM gateways WHERE LOWER(nome) = 'expfypay' AND ativo = 1 LIMIT 1");
    $stmt->execute();
    $gateway = $stmt->fetch();

    if (!$gateway) {
        logWebhook("ERRO: Gateway EXPFY Pay não encontrado ou inativo", 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Gateway não configurado']);
        exit;
    }

    $secretKey = $gateway['client_secret'];
    logWebhook("Secret key encontrada (primeiros 8 chars): " . substr($secretKey, 0, 8) . "...");

} catch (Exception $e) {
    logWebhook("ERRO: Falha ao buscar gateway - " . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
    exit;
}

// Calcular assinatura esperada
$assinaturaEsperada = hash_hmac('sha256', $raw, $secretKey);
logWebhook("Assinatura esperada: " . substr($assinaturaEsperada, 0, 10) . "...");

if (!hash_equals($assinaturaEsperada, $signatureRecebida)) {
    logWebhook("ERRO: Assinatura inválida - Esperada: " . substr($assinaturaEsperada, 0, 10) . "... vs Recebida: " . substr($signatureRecebida, 0, 10) . "...", 'ERROR');
    http_response_code(401);
    echo json_encode(['error' => 'Assinatura inválida']);
    exit;
}

logWebhook("✓ Assinatura válida");

// ============ Validação de Payload ============
$data = json_decode($raw, true);

if (!$data || json_last_error() !== JSON_ERROR_NONE) {
    logWebhook("ERRO: Payload JSON inválido - " . json_last_error_msg(), 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Payload JSON inválido']);
    exit;
}

logWebhook("✓ Payload JSON válido");

// Verificar campos obrigatórios - mais flexível
$camposObrigatorios = ['transaction_id', 'external_id', 'status'];
$camposFaltando = [];

foreach($camposObrigatorios as $campo) {
    if (!isset($data[$campo]) || empty($data[$campo])) {
        $camposFaltando[] = $campo;
    }
}

if (!empty($camposFaltando)) {
    logWebhook("ERRO: Campos obrigatórios ausentes: " . implode(', ', $camposFaltando), 'ERROR');
    logWebhook("Payload recebido: " . json_encode($data), 'ERROR');
    http_response_code(400);
    echo json_encode(['error' => 'Campos obrigatórios ausentes: ' . implode(', ', $camposFaltando)]);
    exit;
}

// Extrair dados
$transaction_id = trim($data['transaction_id']);
$external_id = trim($data['external_id']);
$status = strtolower(trim($data['status'])); // Normalizar case
$amount = floatval($data['amount'] ?? 0);
$paid_at = $data['paid_at'] ?? date('Y-m-d H:i:s');

logWebhook("Dados extraídos - Transaction: $transaction_id, External: $external_id, Status: $status, Amount: $amount");

// ============ Verificação de Duplicata ============
try {
    $stmtDup = $pdo->prepare("SELECT COUNT(*) FROM webhook_logs WHERE transaction_id = ? AND status = 'processed'");
    $stmtDup->execute([$transaction_id]);
    
    if ($stmtDup->fetchColumn() > 0) {
        logWebhook("AVISO: Webhook duplicado para transaction_id: $transaction_id", 'WARNING');
        echo json_encode(['status' => 'already_processed', 'message' => 'Transação já processada']);
        exit;
    }
} catch (Exception $e) {
    logWebhook("ERRO: Falha ao verificar duplicata - " . $e->getMessage(), 'ERROR');
}

// ============ Processar Evento ============
if (in_array($status, ['completed', 'confirmed', 'approved'])) {
    
    try {
        // Iniciar transação
        $pdo->beginTransaction();
        
        // Busca transação pendente - melhorar busca
        $stmt = $pdo->prepare("
            SELECT * FROM transacoes_pix 
            WHERE external_id = ? 
            AND LOWER(status) IN ('pendente', 'pending', 'aguardando') 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute([$external_id]);
        $transacao = $stmt->fetch();

        if (!$transacao) {
            // Tentar buscar por transaction_id
            $stmt = $pdo->prepare("
                SELECT * FROM transacoes_pix 
                WHERE transaction_id = ? 
                AND LOWER(status) IN ('pendente', 'pending', 'aguardando') 
                LIMIT 1
            ");
            $stmt->execute([$transaction_id]);
            $transacao = $stmt->fetch();
        }

        if ($transacao) {
            logWebhook("✓ Transação encontrada: ID {$transacao['id']}, Usuário: {$transacao['usuario_id']}");

            // Verificar se valores conferem
            $valorTransacao = floatval($transacao['valor']);
            if (abs($valorTransacao - $amount) > 0.01) {
                logWebhook("AVISO: Divergência de valores - Esperado: $valorTransacao, Recebido: $amount", 'WARNING');
            }

            // Atualizar transação
            $stmtUpdate = $pdo->prepare("
                UPDATE transacoes_pix 
                SET status = 'aprovado', 
                    valor = ?, 
                    transaction_id = ?
                WHERE id = ?
            ");
            $stmtUpdate->execute([$amount, $transaction_id, $transacao['id']]);

            logWebhook("✓ Transação atualizada para aprovado");

            // Buscar configurações
            $config = $pdo->query("SELECT * FROM configuracoes LIMIT 1")->fetch();
            if (!$config) {
                throw new Exception("Configurações não encontradas");
            }

            $bonusPercent = floatval($config['bonus_deposito'] ?? 0) / 100;
            $percentualComissaoN1 = floatval($config['valor_comissao'] ?? 0) / 100;
            $percentualComissaoN2 = floatval($config['valor_comissao_n2'] ?? 0) / 100;
            $rolloverMultiplicador = floatval($config['rollover_multiplicador'] ?? 2);

            $bonusValor = $amount * $bonusPercent;
            $valorFinal = $amount + $bonusValor;

            logWebhook("Cálculos - Bônus: $bonusValor, Valor final: $valorFinal");

            // Verificar saldo atual do usuário
            $stmtSaldoAtual = $pdo->prepare("SELECT saldo FROM usuarios WHERE id = ?");
            $stmtSaldoAtual->execute([$transacao['usuario_id']]);
            $saldoAnterior = $stmtSaldoAtual->fetchColumn();
            
            logWebhook("Saldo anterior do usuário {$transacao['usuario_id']}: R$ " . number_format($saldoAnterior, 2, ',', '.'));

            // Atualizar saldo do usuário
            $stmtSaldo = $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
            $result = $stmtSaldo->execute([$valorFinal, $transacao['usuario_id']]);
            
            if (!$result) {
                throw new Exception("Falha ao atualizar saldo do usuário");
            }

            // Verificar saldo após atualização
            $stmtSaldoNovo = $pdo->prepare("SELECT saldo FROM usuarios WHERE id = ?");
            $stmtSaldoNovo->execute([$transacao['usuario_id']]);
            $saldoNovo = $stmtSaldoNovo->fetchColumn();
            
            logWebhook("✓ Saldo atualizado para usuário {$transacao['usuario_id']} - Anterior: R$ " . number_format($saldoAnterior, 2, ',', '.') . " → Novo: R$ " . number_format($saldoNovo, 2, ',', '.') . " (+R$ " . number_format($valorFinal, 2, ',', '.') . ")");

            // Sistema de rollover melhorado
            if ($amount > 0) {
                // Verificar se o usuário existe antes de criar rollover
                $stmtVerificarUsuario = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE id = ?");
                $stmtVerificarUsuario->execute([$transacao['usuario_id']]);
                
                if ($stmtVerificarUsuario->fetchColumn() > 0) {
                    $stmtRollover = $pdo->prepare("
                        SELECT COUNT(*) FROM rollover 
                        WHERE usuario_id = ? 
                        AND valor_deposito = ? 
                        AND finalizado = 0
                    ");
                    $stmtRollover->execute([$transacao['usuario_id'], $amount]);
                    
                    if ($stmtRollover->fetchColumn() == 0) {
                        $valorRollover = $amount * $rolloverMultiplicador;
                        $stmtInsertRollover = $pdo->prepare("
                            INSERT INTO rollover (usuario_id, valor_deposito, valor_necessario, valor_acumulado, finalizado, criado_em) 
                            VALUES (?, ?, ?, 0, 0, NOW())
                        ");
                        $stmtInsertRollover->execute([$transacao['usuario_id'], $amount, $valorRollover]);
                        logWebhook("✓ Rollover criado - Valor necessário: $valorRollover");
                    }
                } else {
                    logWebhook("AVISO: Usuário {$transacao['usuario_id']} não existe - pulando criação de rollover", 'WARNING');
                }
            }

            // Sistema de comissões melhorado
            $stmtIndicador = $pdo->prepare("SELECT indicado_por FROM usuarios WHERE id = ?");
            $stmtIndicador->execute([$transacao['usuario_id']]);
            $indicadorNivel1 = $stmtIndicador->fetchColumn();

            if ($indicadorNivel1 && $percentualComissaoN1 > 0) {
                $valorComissaoN1 = $amount * $percentualComissaoN1;
                
                // Verificar se comissão já foi paga
                $stmtVerifComissao = $pdo->prepare("
                    SELECT COUNT(*) FROM comissoes 
                    WHERE transacao_id = ? AND usuario_id = ? AND nivel = 1
                ");
                $stmtVerifComissao->execute([$transacao['id'], $indicadorNivel1]);
                
                if ($stmtVerifComissao->fetchColumn() == 0) {
                    $stmtComissao1 = $pdo->prepare("UPDATE usuarios SET comissao = comissao + ? WHERE id = ?");
                    $stmtComissao1->execute([$valorComissaoN1, $indicadorNivel1]);

                    $stmtLogComissao1 = $pdo->prepare("
                        INSERT INTO comissoes (usuario_id, indicado_id, transacao_id, valor, nivel, criado_em) 
                        VALUES (?, ?, ?, ?, 1, NOW())
                    ");
                    $stmtLogComissao1->execute([$indicadorNivel1, $transacao['usuario_id'], $transacao['id'], $valorComissaoN1]);
                    
                    logWebhook("✓ Comissão N1 paga - Usuário: $indicadorNivel1, Valor: $valorComissaoN1");
                }

                // Comissão Nível 2
                $stmtIndicador2 = $pdo->prepare("SELECT indicado_por FROM usuarios WHERE id = ?");
                $stmtIndicador2->execute([$indicadorNivel1]);
                $indicadorNivel2 = $stmtIndicador2->fetchColumn();

                if ($indicadorNivel2 && $percentualComissaoN2 > 0) {
                    $valorComissaoN2 = $amount * $percentualComissaoN2;
                    
                    $stmtVerifComissao2 = $pdo->prepare("
                        SELECT COUNT(*) FROM comissoes 
                        WHERE transacao_id = ? AND usuario_id = ? AND nivel = 2
                    ");
                    $stmtVerifComissao2->execute([$transacao['id'], $indicadorNivel2]);
                    
                    if ($stmtVerifComissao2->fetchColumn() == 0) {
                        $stmtComissao2 = $pdo->prepare("UPDATE usuarios SET comissao = comissao + ? WHERE id = ?");
                        $stmtComissao2->execute([$valorComissaoN2, $indicadorNivel2]);

                        $stmtLogComissao2 = $pdo->prepare("
                            INSERT INTO comissoes (usuario_id, indicado_id, transacao_id, valor, nivel, criado_em) 
                            VALUES (?, ?, ?, ?, 2, NOW())
                        ");
                        $stmtLogComissao2->execute([$indicadorNivel2, $indicadorNivel1, $transacao['id'], $valorComissaoN2]);
                        
                        logWebhook("✓ Comissão N2 paga - Usuário: $indicadorNivel2, Valor: $valorComissaoN2");
                    }
                }
            }

            // ============ SISTEMA DE SPLITS ============
            if (isset($data['splits']) && is_array($data['splits']) && !empty($data['splits'])) {
                logWebhook("Processando splits para transação ID {$transacao['id']}");
                
                foreach ($data['splits'] as $split) {
                    if (isset($split['email']) && isset($split['percentage']) && $split['percentage'] > 0) {
                        $email = trim($split['email']);
                        $percentage = floatval($split['percentage']);
                        $valorSplit = $amount * ($percentage / 100);
                        
                        logWebhook("Split: $email - $percentage% = R$ " . number_format($valorSplit, 2, ',', '.'));
                        
                        // Buscar usuário por email
                        $stmtUsuario = $pdo->prepare("SELECT id, nome, saldo FROM usuarios WHERE email = ? LIMIT 1");
                        $stmtUsuario->execute([$email]);
                        $usuarioSplit = $stmtUsuario->fetch();
                        
                        if ($usuarioSplit) {
                            // Atualizar saldo do parceiro
                            $stmtSaldoSplit = $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
                            $stmtSaldoSplit->execute([$valorSplit, $usuarioSplit['id']]);
                            
                            // Registrar o split
                            $stmtSplitLog = $pdo->prepare("
                                INSERT INTO splits_log (transacao_id, usuario_id, email, percentage, valor, criado_em) 
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ");
                            $stmtSplitLog->execute([$transacao['id'], $usuarioSplit['id'], $email, $percentage, $valorSplit]);
                            
                            logWebhook("✓ Split processado: Usuário {$usuarioSplit['id']} recebeu R$ " . number_format($valorSplit, 2, ',', '.'));
                        } else {
                            logWebhook("AVISO: Usuário não encontrado para split: $email", 'WARNING');
                        }
                    }
                }
            }

            // ============ SISTEMA DE SPLITS ============
            if (isset($data['splits']) && is_array($data['splits']) && !empty($data['splits'])) {
                logWebhook("Processando splits para transação ID {$transacao['id']}");
                
                foreach ($data['splits'] as $split) {
                    if (isset($split['email']) && isset($split['percentage']) && $split['percentage'] > 0) {
                        $email = trim($split['email']);
                        $percentage = floatval($split['percentage']);
                        $valorSplit = $amount * ($percentage / 100);
                        
                        logWebhook("Split: $email - $percentage% = R$ " . number_format($valorSplit, 2, ',', '.'));
                        
                        // Buscar usuário por email
                        $stmtUsuario = $pdo->prepare("SELECT id, nome, saldo FROM usuarios WHERE email = ? LIMIT 1");
                        $stmtUsuario->execute([$email]);
                        $usuarioSplit = $stmtUsuario->fetch();
                        
                        if ($usuarioSplit) {
                            // Atualizar saldo do parceiro
                            $stmtSaldoSplit = $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
                            $stmtSaldoSplit->execute([$valorSplit, $usuarioSplit['id']]);
                            
                            // Registrar o split
                            $stmtSplitLog = $pdo->prepare("
                                INSERT INTO splits_log (transacao_id, usuario_id, email, percentage, valor, criado_em) 
                                VALUES (?, ?, ?, ?, ?, NOW())
                            ");
                            $stmtSplitLog->execute([$transacao['id'], $usuarioSplit['id'], $email, $percentage, $valorSplit]);
                            
                            logWebhook("✓ Split processado: Usuário {$usuarioSplit['id']} recebeu R$ " . number_format($valorSplit, 2, ',', '.'));
                        } else {
                            logWebhook("AVISO: Usuário não encontrado para split: $email", 'WARNING');
                        }
                    }
                }
            } else {
                // Verificar se há splits configurados no sistema
                $stmtSplitsConfig = $pdo->prepare("SELECT valor FROM dev_config WHERE chave = 'splits_config' LIMIT 1");
                $stmtSplitsConfig->execute();
                $splitsConfig = $stmtSplitsConfig->fetch();
                
                if ($splitsConfig) {
                    $splits = json_decode($splitsConfig['valor'], true) ?: [];
                    if (!empty($splits)) {
                        logWebhook("Processando splits configurados no sistema para transação ID {$transacao['id']}");
                        
                        foreach ($splits as $split) {
                            if (isset($split['email']) && isset($split['percentage']) && $split['percentage'] > 0) {
                                $email = trim($split['email']);
                                $percentage = floatval($split['percentage']);
                                $valorSplit = $amount * ($percentage / 100);
                                
                                logWebhook("Split configurado: $email - $percentage% = R$ " . number_format($valorSplit, 2, ',', '.'));
                                
                                // Buscar usuário por email
                                $stmtUsuario = $pdo->prepare("SELECT id, nome, saldo FROM usuarios WHERE email = ? LIMIT 1");
                                $stmtUsuario->execute([$email]);
                                $usuarioSplit = $stmtUsuario->fetch();
                                
                                if ($usuarioSplit) {
                                    // Atualizar saldo do parceiro
                                    $stmtSaldoSplit = $pdo->prepare("UPDATE usuarios SET saldo = saldo + ? WHERE id = ?");
                                    $stmtSaldoSplit->execute([$valorSplit, $usuarioSplit['id']]);
                                    
                                    // Registrar o split
                                    $stmtSplitLog = $pdo->prepare("
                                        INSERT INTO splits_log (transacao_id, usuario_id, email, percentage, valor, criado_em) 
                                        VALUES (?, ?, ?, ?, ?, NOW())
                                    ");
                                    $stmtSplitLog->execute([$transacao['id'], $usuarioSplit['id'], $email, $percentage, $valorSplit]);
                                    
                                    logWebhook("✓ Split configurado processado: Usuário {$usuarioSplit['id']} recebeu R$ " . number_format($valorSplit, 2, ',', '.'));
                                } else {
                                    logWebhook("AVISO: Usuário não encontrado para split configurado: $email", 'WARNING');
                                }
                            }
                        }
                    }
                }
            }

            // Log de webhook processado
            try {
                $stmtWebhookLog = $pdo->prepare("
                    INSERT INTO webhook_logs (transaction_id, external_id, payload, status, processed_at) 
                    VALUES (?, ?, ?, 'processed', NOW())
                ");
                $stmtWebhookLog->execute([$transaction_id, $external_id, $raw]);
            } catch (Exception $e) {
                logWebhook("AVISO: Falha ao salvar log do webhook - " . $e->getMessage(), 'WARNING');
            }

            // Commit da transação
            $pdo->commit();
            
            logWebhook("✓ SUCESSO: Pagamento processado completamente - Usuário: {$transacao['usuario_id']}, Valor original: $amount, Valor final: $valorFinal");
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'Pagamento processado com sucesso',
                'transaction_id' => $transaction_id,
                'external_id' => $external_id,
                'amount_processed' => $valorFinal
            ]);

        } else {
            logWebhook("AVISO: Transação não encontrada - External ID: $external_id, Transaction ID: $transaction_id", 'WARNING');
            
            // Log para transação não encontrada
            try {
                $stmtNotFound = $pdo->prepare("
                    INSERT INTO webhook_logs (transaction_id, external_id, payload, status, processed_at) 
                    VALUES (?, ?, ?, 'transaction_not_found', NOW())
                ");
                $stmtNotFound->execute([$transaction_id, $external_id, $raw]);
            } catch (Exception $e) {
                logWebhook("Erro ao salvar log de transação não encontrada", 'ERROR');
            }
            
            http_response_code(404);
            echo json_encode([
                'status' => 'not_found', 
                'message' => 'Transação não encontrada ou já processada',
                'external_id' => $external_id
            ]);
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        logWebhook("ERRO CRÍTICO: " . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Erro interno do servidor']);
    }

} else {
    logWebhook("INFO: Status '$status' não processável - aguardando confirmação");
    
    try {
        $stmtPending = $pdo->prepare("
            INSERT INTO webhook_logs (transaction_id, external_id, payload, status, processed_at) 
            VALUES (?, ?, ?, 'status_pending', NOW())
        ");
        $stmtPending->execute([$transaction_id, $external_id, $raw]);
    } catch (Exception $e) {
        logWebhook("Erro ao salvar log de status pendente", 'WARNING');
    }
    
    echo json_encode([
        'status' => 'pending', 
        'message' => "Status '$status' recebido - aguardando confirmação"
    ]);
}

logWebhook("=== FIM DO WEBHOOK ===");
?>