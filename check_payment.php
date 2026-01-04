<?php
header('Content-Type: application/json');
require_once 'includes/db.php';

$transaction_id = $_GET['transaction_id'] ?? '';

if (empty($transaction_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Transaction ID required']);
    exit;
}

try {
    // Busca o status da assinatura no banco
    $stmt = $pdo->prepare("
        SELECT status, store_id 
        FROM subscriptions 
        WHERE gateway_transaction_id = ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$transaction_id]);
    $subscription = $stmt->fetch();

    if ($subscription) {
        echo json_encode([
            'status' => $subscription['status'] === 'active' ? 'completed' : 'pending',
            'store_id' => $subscription['store_id']
        ]);
    } else {
        echo json_encode(['status' => 'not_found']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}