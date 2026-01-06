<?php
session_start();
require_once '../../includes/db.php';

session_start();

// 1. Verifica se está logado
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// 2. Timeout de sessão (30 minutos de inatividade)
$timeout = 1800; // 30 minutos
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=timeout');
    exit;
}
$_SESSION['last_activity'] = time();

// 3. Proteção contra session hijacking (opcional, mas recomendado)
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
}

if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=invalid_session');
    exit;
}

// 4. Regenera ID da sessão periodicamente (a cada 30 minutos)
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}
?>

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