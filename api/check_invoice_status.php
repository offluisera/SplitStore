<?php
/**
 * ============================================
 * API - VERIFICAR STATUS DA FATURA
 * ============================================
 * api/check_invoice_status.php
 * 
 * Endpoint para verificação em tempo real do status de pagamento
 */

header('Content-Type: application/json');
require_once '../includes/db.php';

$invoice_id = $_GET['invoice_id'] ?? null;

if (!$invoice_id) {
    echo json_encode(['error' => 'Invoice ID required', 'status' => 'error']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            i.status,
            i.store_id,
            i.gateway_transaction_id,
            s.first_payment_confirmed
        FROM invoices i
        JOIN stores s ON i.store_id = s.store_id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        echo json_encode(['error' => 'Invoice not found', 'status' => 'not_found']);
        exit;
    }
    
    echo json_encode([
        'status' => $invoice['status'],
        'store_id' => $invoice['store_id'],
        'gateway_id' => $invoice['gateway_transaction_id'],
        'first_payment_confirmed' => (bool)$invoice['first_payment_confirmed']
    ]);
    
} catch (PDOException $e) {
    error_log("Check Invoice Status Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error', 'status' => 'error']);
}