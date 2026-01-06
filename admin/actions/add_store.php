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
    $owner = $_POST['owner_name'];
    $store = $_POST['store_name'];
    $email = $_POST['email'];
    $plan  = $_POST['plan_type'];

    try {
        $stmt = $pdo->prepare("INSERT INTO stores (owner_name, store_name, email, plan_type, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->execute([$owner, $store, $email, $plan]);

        // Limpa o cache do Redis para a Dashboard mostrar o novo cliente instantaneamente
        if ($redis) {
            $redis->del('splitstore_core_metrics');
        }

        header('Location: ../stores.php?success=store_created');
    } catch (Exception $e) {
        header('Location: ../stores.php?error=email_exists');
    }
}