<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    // Exemplo de verificação (Substitua pela lógica de DB no futuro)
    // Usuário: admin | Senha: admin@split2026
    if ($user === 'admin' && $pass === 'admin@split2026') {
        $_SESSION['admin_logged'] = true;
        $_SESSION['user_name'] = 'Administrador';
        header('Location: dashboard.php');
        exit;
    } else {
        header('Location: login.php?error=invalid');
        exit;
    }
}