<?php
/**
 * LOGOUT DO CLIENTE
 * Destroi a sessão e redireciona
 */

session_start();

// Limpa todas as variáveis de sessão
$_SESSION = array();

// Destroi o cookie de sessão se existir
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Destroi a sessão
session_destroy();

// Redireciona para o login
header('Location: login.php');
exit;
?>