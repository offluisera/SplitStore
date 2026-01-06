<?php
/**
 * ============================================
 * SPLITSTORE - WEBHOOK MISTICPAY V3.0
 * ============================================
 * Processa notificações de pagamento
 * Ativa loja automaticamente após confirmação
 */

require_once '../includes/db.php';

// Log de requisições (apenas em desenvolvimento)
$logFile = __DIR__ . '/../logs/webhook_misticpay.log';
$requestLog = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
];

// Recebe o payload
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

$requestLog['payload'] = $data;

// ========================================
// VALIDAÇÃO DE SEGURANÇA
// ========================================

/**
 * IMPORTANTE: Em produção, validar assinatura ou token
 * Exemplo com MisticPay:
 * 
 * $signature = $_SERVER['HTTP_X_MISTIC_SIGNATURE'] ?? '';
 * $expectedSignature = hash_hmac('sha256', $payload, MISTIC_WEBHOOK_SECRET);
 * 
 * if (!hash_equals($expectedSignature, $signature)) {
 *     http_response_code(401);
 *     exit('Invalid signature');
 * }
 */

// Validar IP do webhook (opcional, mas recomendado)
$allowedIPs = [
    '177.54.144.0/20',  // Range de IPs da MisticPay (exemplo)
    '127.0.0.1',        // Localhost (apenas para testes)
];

function isIPAllowed($ip, $allowedRanges) {
    foreach ($allowedRanges as $range) {
        if (strpos($range, '/') !== false) {
            // CIDR notation
            list($subnet, $mask) = explode('/', $range);
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);
            $mask_long = ~((1 << (32 - $mask)) - 1);
            if (($ip_long & $mask_long) === ($subnet_long & $mask_long)) {
                return true;
            }
        } else {
            // IP exato
            if ($ip === $range) {
                return true;
            }
        }
    }
    return false;
}

// Descomente em produção:
// if (!isIPAllowed($_SERVER['REMOTE_ADDR'], $allowedIPs)) {
//     http_response_code(403);
//     exit('Forbidden');
// }

// ========================================
// VALIDAÇÃO DO PAYLOAD
// ========================================

if (!$data || !isset($data['external_id']) || !isset($data['status'])) {
    $requestLog['error'] = 'Invalid payload structure';
    file_put_contents($logFile, json_encode($requestLog) . PHP_EOL, FILE_APPEND);
    
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$external_id = $data['external_id'];        // ID da transação no nosso sistema
$gateway_transaction_id = $data['id'] ?? '';  // ID na MisticPay
$status = $data['status'];                   // paid, cancelled, expired
$amount = $data['amount'] ?? 0;

$requestLog['transaction_id'] = $external_id;
$requestLog['gateway_status'] = $status;

// ========================================
// PROCESSAR PAGAMENTO
// ========================================

try {
    $pdo->beginTransaction();
    
    // 1. Busca a transação
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.store_id,
            t.amount,
            t.status as current_status,
            s.store_name,
            s.email,
            s.plan
        FROM transactions t
        JOIN stores s ON t.store_id = s.id
        WHERE t.id = ?
    ");
    $stmt->execute([$external_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        $requestLog['error'] = 'Transaction not found';
        file_put_contents($logFile, json_encode($requestLog) . PHP_EOL, FILE_APPEND);
        
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Transaction not found']);
        exit;
    }
    
    // Prevenir processamento duplicado
    if ($transaction['current_status'] === 'completed') {
        $requestLog['info'] = 'Transaction already processed';
        file_put_contents($logFile, json_encode($requestLog) . PHP_EOL, FILE_APPEND);
        
        $pdo->commit();
        http_response_code(200);
        echo json_encode(['status' => 'already_processed']);
        exit;
    }
    
    // 2. Processar baseado no status
    if ($status === 'paid' || $status === 'approved' || $status === 'completed') {
        
        // ===== PAGAMENTO APROVADO =====
        
        // Atualiza transação
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET 
                status = 'completed',
                gateway_transaction_id = ?,
                paid_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$gateway_transaction_id, $external_id]);
        
        // Ativa a loja
        $stmt = $pdo->prepare("
            UPDATE stores 
            SET 
                status = 'active',
                activated_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$transaction['store_id']]);
        
        // Cria assinatura
        $nextBillingDate = date('Y-m-d', strtotime('+30 days'));
        
        $stmt = $pdo->prepare("
            INSERT INTO subscriptions (
                store_id,
                plan,
                status,
                amount,
                billing_cycle,
                next_billing_date,
                gateway_subscription_id,
                created_at
            ) VALUES (?, ?, 'active', ?, 'monthly', ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                status = 'active',
                amount = VALUES(amount),
                next_billing_date = VALUES(next_billing_date),
                updated_at = NOW()
        ");
        $stmt->execute([
            $transaction['store_id'],
            $transaction['plan'],
            $transaction['amount'],
            $nextBillingDate,
            $gateway_transaction_id
        ]);
        
        // Log de ativação
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (
                store_id,
                action,
                description,
                created_at
            ) VALUES (?, 'store_activated', ?, NOW())
        ");
        $stmt->execute([
            $transaction['store_id'],
            "Loja ativada após pagamento de R$ " . number_format($transaction['amount'], 2, ',', '.')
        ]);
        
        // Limpa cache (se usar Redis)
        if (isset($redis)) {
            $redis->del('splitstore_core_metrics');
            $redis->del('admin_real_metrics');
            $redis->del('store_' . $transaction['store_id'] . '_data');
        }
        
        // TODO: Enviar e-mail de boas-vindas
        // sendWelcomeEmail($transaction['email'], $transaction['store_name']);
        
        $requestLog['action'] = 'payment_approved';
        $requestLog['store_activated'] = $transaction['store_id'];
        
        $pdo->commit();
        
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'action' => 'store_activated',
            'store_id' => $transaction['store_id']
        ]);
        
    } elseif ($status === 'cancelled' || $status === 'failed') {
        
        // ===== PAGAMENTO CANCELADO/FALHOU =====
        
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET 
                status = 'failed',
                gateway_transaction_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$gateway_transaction_id, $external_id]);
        
        // Atualiza status da loja para 'cancelled'
        $stmt = $pdo->prepare("
            UPDATE stores 
            SET status = 'cancelled', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$transaction['store_id']]);
        
        $requestLog['action'] = 'payment_failed';
        
        $pdo->commit();
        
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'action' => 'payment_failed'
        ]);
        
    } elseif ($status === 'expired') {
        
        // ===== PAGAMENTO EXPIRADO =====
        
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET 
                status = 'expired',
                gateway_transaction_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$gateway_transaction_id, $external_id]);
        
        $requestLog['action'] = 'payment_expired';
        
        $pdo->commit();
        
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'action' => 'payment_expired'
        ]);
        
    } else {
        
        // Status desconhecido
        $requestLog['warning'] = 'Unknown status: ' . $status;
        
        $pdo->commit();
        
        http_response_code(200);
        echo json_encode([
            'status' => 'received',
            'action' => 'unknown_status'
        ]);
    }
    
} catch (PDOException $e) {
    $pdo->rollBack();
    
    $requestLog['error'] = 'Database error: ' . $e->getMessage();
    file_put_contents($logFile, json_encode($requestLog) . PHP_EOL, FILE_APPEND);
    
    error_log("Webhook Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

// Salva log
file_put_contents($logFile, json_encode($requestLog) . PHP_EOL, FILE_APPEND);

// ========================================
// FUNÇÕES AUXILIARES
// ========================================

/**
 * Envia e-mail de boas-vindas (implementar)
 */
function sendWelcomeEmail($email, $storeName) {
    // TODO: Implementar envio de e-mail
    // Pode usar PHPMailer, SendGrid, Mailgun, etc.
    
    error_log("TODO: Enviar e-mail de boas-vindas para: " . $email);
    
    return true;
}
?>