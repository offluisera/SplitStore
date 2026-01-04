<?php
session_start();
require_once '../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['admin_logged'])) {
    $store_id = $_POST['store_id'];
    $amount   = $_POST['amount'];
    $method   = $_POST['payment_method'];
    $status   = $_POST['status'];
    $paid_at  = ($status == 'completed') ? date('Y-m-d H:i:s') : null;

    try {
        $sql = "INSERT INTO transactions (store_id, amount, payment_method, status, paid_at) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$store_id, $amount, $method, $status, $paid_at]);

        // Limpa o cache das métricas para a Dashboard atualizar o faturamento
        if ($redis) {
            $redis->del('splitstore_core_metrics');
            $redis->del('admin_real_metrics');
        }

        header('Location: ../transactions.php?success=1');
    } catch (Exception $e) {
        header('Location: ../transactions.php?error=' . $e->getMessage());
    }
}