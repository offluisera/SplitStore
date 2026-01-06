?>
<?php
/**
 * admin/auth.php
 * Sistema de Autenticação Seguro com Argon2ID
 * 
 * Features:
 * - Hash seguro de senhas com Argon2ID
 * - Proteção contra timing attacks
 * - Regeneração de ID de sessão
 * - Rate limiting (bloqueio após tentativas falhas)
 * - Log de segurança
 * - Rehashing automático de senhas antigas
 */

session_start();
require_once '../includes/db.php';

// Configurações de segurança
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos em segundos
define('DELAY_FAILED_LOGIN', 200000); // 200ms em microsegundos

/**
 * Função auxiliar para registrar tentativa de login
 */
function logLoginAttempt($pdo, $username, $success) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (username, ip_address, user_agent, success) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $username,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $success ? 1 : 0
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log login attempt: " . $e->getMessage());
    }
}

/**
 * Função auxiliar para limpar tentativas antigas (executar periodicamente)
 */
function cleanOldAttempts($pdo) {
    try {
        $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    } catch (PDOException $e) {
        error_log("Failed to clean old attempts: " . $e->getMessage());
    }
}

// Processa apenas requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Validação inicial dos campos
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    usleep(DELAY_FAILED_LOGIN); // Previne timing attacks
    header('Location: login.php?error=empty_fields');
    exit;
}

try {
    // 1. Busca o admin no banco
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    // Se não encontrou, adiciona delay e redireciona
    if (!$admin) {
        usleep(DELAY_FAILED_LOGIN); // Previne enumeração de usuários
        logLoginAttempt($pdo, $username, false);
        header('Location: login.php?error=invalid');
        exit;
    }
    
    // 2. Verifica se a conta está bloqueada
    if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
        $remainingTime = ceil((strtotime($admin['locked_until']) - time()) / 60);
        error_log("Login blocked: {$username} from IP: {$_SERVER['REMOTE_ADDR']} - {$remainingTime} min remaining");
        header("Location: login.php?error=locked&time={$remainingTime}");
        exit;
    }
    
    // 3. Verifica a senha
    if (password_verify($password, $admin['password_hash'])) {
        
        // 3.1 Login bem-sucedido! Atualiza o hash se necessário
        if (password_needs_rehash($admin['password_hash'], PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 1
        ])) {
            $new_hash = password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 1
            ]);
            
            $updateHash = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
            $updateHash->execute([$new_hash, $admin['id']]);
            
            error_log("Password hash updated for user: {$username}");
        }
        
        // 3.2 Regenera o ID da sessão (previne session fixation)
        session_regenerate_id(true);
        
        // 3.3 Define variáveis de sessão
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // 3.4 Atualiza último login e limpa tentativas falhas
        $updateLogin = $pdo->prepare("
            UPDATE admins 
            SET last_login = NOW(), 
                login_attempts = 0, 
                locked_until = NULL 
            WHERE id = ?
        ");
        $updateLogin->execute([$admin['id']]);
        
        // 3.5 Registra login bem-sucedido
        logLoginAttempt($pdo, $username, true);
        error_log("Successful login: {$username} from IP: {$_SERVER['REMOTE_ADDR']}");
        
        // 3.6 Limpa cache do Redis se existir
        if (isset($redis) && $redis) {
            try {
                $redis->del('admin_session_' . $admin['id']);
            } catch (Exception $e) {
                error_log("Redis error: " . $e->getMessage());
            }
        }
        
        // 3.7 Redireciona para o dashboard
        header('Location: dashboard.php');
        exit;
        
    } else {
        // 4. Senha incorreta - incrementa tentativas
        $attempts = $admin['login_attempts'] + 1;
        $locked_until = null;
        
        // 4.1 Bloqueia após atingir o limite
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $locked_until = date('Y-m-d H:i:s', time() + LOCKOUT_TIME);
            error_log("Account locked: {$username} from IP: {$_SERVER['REMOTE_ADDR']} - Too many attempts");
        }
        
        // 4.2 Atualiza contador de tentativas
        $updateAttempts = $pdo->prepare("
            UPDATE admins 
            SET login_attempts = ?, 
                locked_until = ? 
            WHERE id = ?
        ");
        $updateAttempts->execute([$attempts, $locked_until, $admin['id']]);
        
        // 4.3 Registra tentativa falha
        logLoginAttempt($pdo, $username, false);
        error_log("Failed login attempt #{$attempts}: {$username} from IP: {$_SERVER['REMOTE_ADDR']}");
        
        // 4.4 Delay para prevenir brute force
        usleep(DELAY_FAILED_LOGIN);
        
        // 4.5 Redireciona com mensagem apropriada
        if ($locked_until) {
            header('Location: login.php?error=locked&time=' . ceil(LOCKOUT_TIME / 60));
        } else {
            $remaining = MAX_LOGIN_ATTEMPTS - $attempts;
            header("Location: login.php?error=invalid&remaining={$remaining}");
        }
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    usleep(DELAY_FAILED_LOGIN);
    header('Location: login.php?error=system');
    exit;
}

// Limpeza periódica (1% de chance a cada login)
if (rand(1, 100) === 1) {
    cleanOldAttempts($pdo);
}
?>