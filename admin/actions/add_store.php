<?php
session_start();
require_once '../../includes/db.php';

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