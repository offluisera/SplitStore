<?php
require_once '../includes/db.php';

// Recebe o corpo da requisição (JSON) enviado pela MisticPay
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

/**
 * IMPORTANTE: Na produção, valide se a requisição partiu realmente da MisticPay
 * através do Client Secret ou IPs autorizados.
 */

if ($data && isset($data['external_id']) && $data['status'] === 'paid') {
    
    $external_id = $data['external_id']; // O ID da transação no seu sistema
    $mistic_id = $data['id']; // ID da transação na MisticPay

    try {
        // 1. Busca a transação pendente no banco
        $stmt = $pdo->prepare("SELECT id FROM transactions WHERE id = ? AND status = 'pending'");
        $stmt->execute([$external_id]);
        $transaction = $stmt->fetch();

        if ($transaction) {
            // 2. Atualiza para Completo, define data do pagamento e salva ID da Mistic
            $update = $pdo->prepare("
                UPDATE transactions 
                SET status = 'completed', 
                    paid_at = NOW(), 
                    external_id = ? 
                WHERE id = ?
            ");
            $update->execute([$mistic_id, $external_id]);

            // 3. Limpa o Cache do Redis para atualizar a Dashboard em tempo real
            if ($redis) {
                $redis->del('splitstore_core_metrics');
                $redis->del('admin_real_metrics');
            }

            // Responde 200 para a MisticPay não reenviar o post
            http_response_code(200);
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(404); // Transação não encontrada ou já paga
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Webhook Error: " . $e->getMessage());
    }
} else {
    http_response_code(400); // Dados inválidos
}