<?php
/**
 * ============================================
 * API - VERIFICAÇÃO DE STATUS DO PAGAMENTO
 * ============================================
 * Endpoint chamado via AJAX para polling
 */

header('Content-Type: application/json');
require_once '../includes/db.php';

$transaction_id = $_GET['transaction_id'] ?? null;

if (!$transaction_id) {
    echo json_encode(['error' => 'Transaction ID required', 'status' => 'error']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT status, store_id, gateway_transaction_id
        FROM transactions 
        WHERE id = ?
    ");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['error' => 'Transaction not found', 'status' => 'not_found']);
        exit;
    }
    
    echo json_encode([
        'status' => $transaction['status'],
        'store_id' => $transaction['store_id'],
        'gateway_id' => $transaction['gateway_transaction_id']
    ]);
    
} catch (PDOException $e) {
    error_log("Check Payment Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error', 'status' => 'error']);
}
?>