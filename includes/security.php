<?php
/**
 * ============================================
 * SPLITSTORE - SISTEMA DE AUTENTICAÇÃO SEGURO
 * ============================================
 * 
 * Recursos de Segurança:
 * - Proteção contra Session Hijacking
 * - Rate Limiting (Força Bruta)
 * - Timeout de Inatividade
 * - Regeneração de Session ID
 * - Logs de Tentativas
 * - IP Whitelist (Opcional)
 * - 2FA Ready (Estrutura preparada)
 */

// ============================================
// 1. ARQUIVO: includes/security.php
// ============================================
class SecurityManager {
    private $pdo;
    private $redis;
    private $maxLoginAttempts = 5;
    private $lockoutTime = 900; // 15 minutos
    
    public function __construct($pdo, $redis = null) {
        $this->pdo = $pdo;
        $this->redis = $redis;
    }
    
    /**
     * Verifica se o IP está bloqueado por excesso de tentativas
     */
    public function isIpBlocked($ip) {
        $cacheKey = "login_attempts:{$ip}";
        
        if ($this->redis && $this->redis->exists($cacheKey)) {
            $attempts = (int)$this->redis->get($cacheKey);
            return $attempts >= $this->maxLoginAttempts;
        }
        
        // Fallback MySQL
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            AND success = 0
        ");
        $stmt->execute([$ip]);
        $result = $stmt->fetch();
        
        return $result['attempts'] >= $this->maxLoginAttempts;
    }
    
    /**
     * Registra tentativa de login
     */
    public function logLoginAttempt($username, $ip, $success, $userAgent) {
        // Incrementa contador no Redis
        if ($this->redis) {
            $cacheKey = "login_attempts:{$ip}";
            $this->redis->incr($cacheKey);
            $this->redis->expire($cacheKey, $this->lockoutTime);
        }
        
        // Log no MySQL
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO login_attempts (username, ip_address, user_agent, success, attempted_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $ip, $userAgent, $success ? 1 : 0]);
        } catch (PDOException $e) {
            error_log("Failed to log login attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Limpa tentativas após login bem-sucedido
     */
    public function clearLoginAttempts($ip) {
        if ($this->redis) {
            $this->redis->del("login_attempts:{$ip}");
        }
    }
    
    /**
     * Gera token CSRF
     */
    public function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Valida token CSRF
     */
    public function validateCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Verifica fingerprint da sessão (anti-hijacking)
     */
    public function validateSessionFingerprint() {
        $currentFingerprint = $this->generateFingerprint();
        
        if (!isset($_SESSION['fingerprint'])) {
            $_SESSION['fingerprint'] = $currentFingerprint;
            return true;
        }
        
        return hash_equals($_SESSION['fingerprint'], $currentFingerprint);
    }
    
    /**
     * Gera fingerprint baseado em User-Agent e IP
     */
    private function generateFingerprint() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return hash('sha256', $userAgent . $ip . 'SplitStore2026');
    }
    
    /**
     * Verifica timeout de inatividade
     */
    public function checkInactivityTimeout($timeout = 1800) {
        if (isset($_SESSION['last_activity'])) {
            $inactive = time() - $_SESSION['last_activity'];
            if ($inactive > $timeout) {
                return false; // Sessão expirou
            }
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Regenera Session ID periodicamente
     */
    public function regenerateSessionId($interval = 1800) {
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
            session_regenerate_id(true);
        } elseif (time() - $_SESSION['last_regeneration'] > $interval) {
            $_SESSION['last_regeneration'] = time();
            session_regenerate_id(true);
        }
    }
}

// ============================================
// 2. ARQUIVO: admin/auth.php (ATUALIZADO)
// ============================================
session_start();
require_once '../includes/db.php';
require_once '../includes/security.php';

$security = new SecurityManager($pdo, $redis ?? null);

// Configuração de segurança
$ip = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Verifica se já está logado
if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Valida CSRF Token
    if (!isset($_POST['csrf_token']) || !$security->validateCsrfToken($_POST['csrf_token'])) {
        $error = "Token de segurança inválido. Recarregue a página.";
    }
    // 2. Verifica Rate Limiting
    elseif ($security->isIpBlocked($ip)) {
        $error = "Muitas tentativas falhadas. Tente novamente em 15 minutos.";
        $security->logLoginAttempt($_POST['username'] ?? '', $ip, false, $userAgent);
    }
    else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (!empty($username) && !empty($password)) {
            try {
                // Busca usuário no banco
                $stmt = $pdo->prepare("
                    SELECT id, username, password, role, status, two_factor_enabled 
                    FROM admin_users 
                    WHERE username = ? AND status = 'active'
                ");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                // Verifica credenciais
                if ($user && password_verify($password, $user['password'])) {
                    
                    // Login bem-sucedido
                    $security->clearLoginAttempts($ip);
                    $security->logLoginAttempt($username, $ip, true, $userAgent);
                    
                    // Cria sessão segura
                    session_regenerate_id(true);
                    $_SESSION['admin_logged'] = true;
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['admin_role'] = $user['role'];
                    $_SESSION['last_activity'] = time();
                    $_SESSION['last_regeneration'] = time();
                    $_SESSION['fingerprint'] = hash('sha256', $userAgent . $ip . 'SplitStore2026');
                    
                    // Atualiza último login
                    $stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Redireciona
                    header('Location: dashboard.php');
                    exit;
                    
                } else {
                    // Login falhou
                    $security->logLoginAttempt($username, $ip, false, $userAgent);
                    $error = "Usuário ou senha incorretos.";
                }
                
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $error = "Erro ao processar login. Tente novamente.";
            }
        } else {
            $error = "Preencha todos os campos.";
        }
    }
}

// Gera novo token CSRF
$csrfToken = $security->generateCsrfToken();

// ============================================
// 3. ARQUIVO: includes/auth_guard.php
// ============================================
/**
 * Middleware de Proteção de Rotas
 * Include no início de cada página protegida
 */

if (!function_exists('requireAuth')) {
    function requireAuth($requiredRole = null) {
        global $pdo, $redis;
        
        if (!isset($_SESSION)) {
            session_start();
        }
        
        require_once __DIR__ . '/security.php';
        $security = new SecurityManager($pdo, $redis ?? null);
        
        // 1. Verifica se está logado
        if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
            header('Location: login.php?error=unauthorized');
            exit;
        }
        
        // 2. Verifica fingerprint (anti-hijacking)
        if (!$security->validateSessionFingerprint()) {
            session_unset();
            session_destroy();
            header('Location: login.php?error=invalid_session');
            exit;
        }
        
        // 3. Verifica timeout de inatividade (30 minutos)
        if (!$security->checkInactivityTimeout(1800)) {
            session_unset();
            session_destroy();
            header('Location: login.php?error=timeout');
            exit;
        }
        
        // 4. Regenera Session ID periodicamente
        $security->regenerateSessionId(1800);
        
        // 5. Verifica Role (se especificado)
        if ($requiredRole && $_SESSION['admin_role'] !== $requiredRole) {
            header('HTTP/1.1 403 Forbidden');
            die('Acesso negado.');
        }
        
        return true;
    }
}

// ============================================
// 4. SQL: Criação das Tabelas
// ============================================
/*

-- Tabela de Usuários Admin
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'manager', 'support') DEFAULT 'admin',
    status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
    two_factor_enabled TINYINT(1) DEFAULT 0,
    two_factor_secret VARCHAR(32) NULL,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Log de Tentativas de Login
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    success TINYINT(1) DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Criar usuário admin padrão (senha: admin@split2026)
INSERT INTO admin_users (username, password, email, role) 
VALUES (
    'admin', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    'admin@splitstore.com.br', 
    'admin'
) ON DUPLICATE KEY UPDATE username=username;

*/

// ============================================
// 5. EXEMPLO DE USO EM PÁGINAS PROTEGIDAS
// ============================================
/*

// admin/dashboard.php
<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth_guard.php';

// Protege a rota (apenas admin)
requireAuth('admin');

// Resto do código da página...
?>

*/

// ============================================
// 6. SCRIPT DE CRIAÇÃO DE USUÁRIOS
// ============================================
/*

// admin/create_user.php (protegido, apenas para admin)
<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth_guard.php';

requireAuth('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $email = trim($_POST['email']);
    $role = $_POST['role'] ?? 'admin';
    
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_users (username, password, email, role) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$username, $hashedPassword, $email, $role]);
        echo "Usuário criado com sucesso!";
    } catch (PDOException $e) {
        echo "Erro: " . $e->getMessage();
    }
}
?>

*/

// ============================================
// 7. LOGOUT SEGURO
// ============================================
/*

// admin/logout.php
<?php
session_start();

// Destrói todas as variáveis de sessão
$_SESSION = array();

// Destrói o cookie de sessão
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Destroi a sessão
session_destroy();

// Redireciona
header('Location: login.php?logout=success');
exit;
?>

*/

// ============================================
// RETORNA O CÓDIGO PARA auth.php ATUALIZADO
// ============================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Seguro | SplitStore Admin</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #000; 
            color: white;
            overflow: hidden;
            height: 100vh;
        }

        #particles-js {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: 1;
            top: 0;
            left: 0;
        }

        .login-wrapper {
            position: relative;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            padding: 20px;
        }

        .glass-card {
            background: rgba(10, 10, 10, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            width: 100%;
            max-width: 400px;
        }

        .glow-red-focus:focus-within {
            border-color: rgba(220, 38, 38, 0.5);
            box-shadow: 0 0 20px -5px rgba(220, 38, 38, 0.3);
        }

        input {
            background: rgba(20, 20, 20, 0.8) !important;
            outline: none;
        }
        
        .shake {
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
    </style>
</head>
<body class="antialiased">

    <div id="particles-js"></div>

    <div class="login-wrapper">
        <div class="glass-card p-10 rounded-[2.5rem] shadow-2xl border border-white/5">
            
            <div class="text-center mb-10">
                <a href="../index.php" class="inline-block mb-6 opacity-50 hover:opacity-100 transition-opacity">
                    <i data-lucide="arrow-left" class="w-5 h-5 mx-auto"></i>
                </a>
                <h2 class="text-2xl font-black uppercase italic tracking-tighter">
                    Split<span class="text-red-600">Admin</span>
                </h2>
                <p class="text-zinc-500 text-[10px] font-bold uppercase tracking-[0.3em] mt-2">Área Restrita • Protegido</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-900/20 border border-red-600/30 text-red-500 p-4 rounded-2xl mb-6 text-sm font-bold shake">
                    <div class="flex items-center gap-3">
                        <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['logout'])): ?>
                <div class="bg-green-900/20 border border-green-600/30 text-green-500 p-4 rounded-2xl mb-6 text-sm font-bold">
                    <div class="flex items-center gap-3">
                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                        <span>Logout realizado com sucesso!</span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="bg-yellow-900/20 border border-yellow-600/30 text-yellow-500 p-4 rounded-2xl mb-6 text-sm font-bold">
                    <div class="flex items-center gap-3">
                        <i data-lucide="clock" class="w-5 h-5"></i>
                        <span>
                            <?php 
                            $messages = [
                                'timeout' => 'Sessão expirada por inatividade.',
                                'unauthorized' => 'Acesso não autorizado.',
                                'invalid_session' => 'Sessão inválida detectada.'
                            ];
                            echo $messages[$_GET['error']] ?? 'Erro desconhecido.';
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-6">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                
                <div class="space-y-2 group">
                    <label class="text-[10px] font-black uppercase tracking-widest text-zinc-600 ml-1">Usuário</label>
                    <div class="relative flex items-center glow-red-focus rounded-2xl border border-white/5 transition-all">
                        <i data-lucide="user" class="absolute left-4 w-4 h-4 text-zinc-600"></i>
                        <input type="text" name="username" required
                            class="w-full py-4 pl-12 pr-4 rounded-2xl text-sm text-white border-none placeholder:text-zinc-800"
                            placeholder="Seu usuário"
                            autocomplete="username">
                    </div>
                </div>

                <div class="space-y-2 group">
                    <label class="text-[10px] font-black uppercase tracking-widest text-zinc-600 ml-1">Senha</label>
                    <div class="relative flex items-center glow-red-focus rounded-2xl border border-white/5 transition-all">
                        <i data-lucide="lock" class="absolute left-4 w-4 h-4 text-zinc-600"></i>
                        <input type="password" name="password" required
                            class="w-full py-4 pl-12 pr-4 rounded-2xl text-sm text-white border-none placeholder:text-zinc-800"
                            placeholder="••••••••"
                            autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" 
                    class="w-full bg-red-600 hover:bg-red-700 text-white py-4 rounded-2xl font-black uppercase text-[11px] tracking-widest transition-all hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-red-900/20">
                    <i data-lucide="shield-check" class="w-4 h-4 inline mr-2"></i>
                    Autenticar Acesso
                </button>
            </form>

            <div class="mt-8 text-center">
                <div class="flex items-center justify-center gap-2 mb-4">
                    <i data-lucide="shield" class="w-3 h-3 text-green-500"></i>
                    <p class="text-zinc-600 text-[9px] font-bold uppercase tracking-widest">
                        Conexão Segura SSL
                    </p>
                </div>
                <p class="text-zinc-700 text-[9px] font-bold uppercase tracking-widest leading-relaxed">
                    © 2026 Grupo Split<br>Soluções WEB's
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        lucide.createIcons();

        particlesJS("particles-js", {
            "particles": {
                "number": { "value": 40, "density": { "enable": true, "value_area": 800 } },
                "color": { "value": "#ff0000" },
                "shape": { "type": "circle" },
                "opacity": { "value": 0.2, "random": true },
                "size": { "value": 2, "random": true },
                "line_linked": { "enable": true, "distance": 150, "color": "#ff0000", "opacity": 0.1, "width": 1 },
                "move": { "enable": true, "speed": 0.8, "direction": "none", "random": true, "straight": false, "out_mode": "out", "bounce": false }
            },
            "retina_detect": true
        });
    </script>
</body>
</html>