<?php
/**
 * ============================================
 * SISTEMA DE AUTENTICAÇÃO - LOJA
 * ============================================
 * stores/Teste/auth.php
 */

session_start();
require_once '../../includes/db.php';

$store_slug = basename(dirname(__FILE__));

// Busca loja
try {
    $stmt = $pdo->prepare("SELECT * FROM stores WHERE store_slug = ? AND status = 'active'");
    $stmt->execute([$store_slug]);
    $store = $stmt->fetch();
    
    if (!$store) {
        die("Loja não encontrada.");
    }
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

$message = "";
$messageType = "";
$action = $_GET['action'] ?? 'login';

// ========================================
// REGISTRO DE USUÁRIO
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    
    $minecraft_nick = trim($_POST['minecraft_nick'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validações
    if (empty($minecraft_nick) || empty($email) || empty($password)) {
        $message = "Preencha todos os campos obrigatórios.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email inválido.";
        $messageType = "error";
    } elseif (strlen($password) < 6) {
        $message = "A senha deve ter no mínimo 6 caracteres.";
        $messageType = "error";
    } elseif ($password !== $password_confirm) {
        $message = "As senhas não coincidem.";
        $messageType = "error";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $minecraft_nick)) {
        $message = "Nick inválido. Use apenas letras, números e _ (3-16 caracteres).";
        $messageType = "error";
    } else {
        try {
            // Verifica se nick existe no Minecraft via API
            $uuid = verifyMinecraftNick($minecraft_nick);
            
            if (!$uuid) {
                $message = "Nick '$minecraft_nick' não encontrado no Minecraft. Certifique-se de usar um nick válido.";
                $messageType = "error";
            } else {
                // Verifica se já existe
                $check = $pdo->prepare("SELECT id FROM store_users WHERE store_id = ? AND (minecraft_nick = ? OR email = ?)");
                $check->execute([$store['id'], $minecraft_nick, $email]);
                
                if ($check->fetch()) {
                    $message = "Este nick ou email já está cadastrado nesta loja.";
                    $messageType = "error";
                } else {
                    // Busca skin
                    $skin_url = "https://mc-heads.net/avatar/{$uuid}/100";
                    
                    // Registra usuário
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO store_users 
                        (store_id, minecraft_nick, minecraft_uuid, email, password_hash, skin_url, rank, rank_color) 
                        VALUES (?, ?, ?, ?, ?, ?, 'Membro', '#9CA3AF')
                    ");
                    
                    if ($stmt->execute([$store['id'], $minecraft_nick, $uuid, $email, $password_hash, $skin_url])) {
                        $message = "Conta criada com sucesso! Faça login para continuar.";
                        $messageType = "success";
                        $action = 'login';
                    }
                }
            }
        } catch (PDOException $e) {
            $message = "Erro ao criar conta: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// ========================================
// LOGIN
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $message = "Preencha email e senha.";
        $messageType = "error";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM store_users 
                WHERE store_id = ? AND email = ? AND is_banned = 0
            ");
            $stmt->execute([$store['id'], $email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Atualiza último login
                $pdo->prepare("UPDATE store_users SET last_login = NOW() WHERE id = ?")
                    ->execute([$user['id']]);
                
                // Cria sessão
                $_SESSION['store_user_logged'] = true;
                $_SESSION['store_user_id'] = $user['id'];
                $_SESSION['store_user_nick'] = $user['minecraft_nick'];
                $_SESSION['store_user_rank'] = $user['rank'];
                $_SESSION['store_user_skin'] = $user['skin_url'];
                $_SESSION['store_slug'] = $store_slug;
                
                // Token de sessão (se marcar "lembrar-me")
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    $pdo->prepare("
                        INSERT INTO store_user_sessions (user_id, session_token, ip_address, user_agent, expires_at)
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([
                        $user['id'],
                        $token,
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        $_SERVER['HTTP_USER_AGENT'] ?? null,
                        $expires
                    ]);
                    
                    setcookie('store_session_' . $store_slug, $token, strtotime('+30 days'), '/', '', false, true);
                }
                
                header('Location: index.php');
                exit;
            } else {
                $message = "Email ou senha incorretos.";
                $messageType = "error";
            }
        } catch (PDOException $e) {
            $message = "Erro ao fazer login: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// ========================================
// LOGOUT
// ========================================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    setcookie('store_session_' . $store_slug, '', time() - 3600, '/');
    header('Location: index.php');
    exit;
}

// ========================================
// FUNÇÃO: VERIFICAR NICK NO MINECRAFT
// ========================================
function verifyMinecraftNick($nick) {
    try {
        $url = "https://api.mojang.com/users/profiles/minecraft/{$nick}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['id'] ?? null;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Minecraft API Error: " . $e->getMessage());
        return null;
    }
}

$primaryColor = $store['primary_color'] ?? '#dc2626';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $action === 'register' ? 'Criar Conta' : 'Login' ?> | <?= htmlspecialchars($store['store_name']) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?= $primaryColor ?>'
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        body { 
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%); 
            font-family: 'Inter', sans-serif;
        }
        
        .glass { 
            background: rgba(255, 255, 255, 0.02); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255, 255, 255, 0.05); 
        }
        
        .gradient-text {
            background: linear-gradient(135deg, <?= $primaryColor ?> 0%, #ef4444 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">

    <div class="w-full max-w-md">
        
        <!-- Logo/Header -->
        <div class="text-center mb-8">
            <a href="index.php" class="inline-flex items-center gap-3 mb-6 group">
                <?php if (!empty($store['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($store['logo_url']) ?>" class="h-12 object-contain">
                <?php else: ?>
                    <div class="w-14 h-14 bg-primary rounded-xl flex items-center justify-center font-black shadow-lg">
                        <?= strtoupper(substr($store['store_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="text-left">
                    <div class="font-black text-xl uppercase tracking-tight text-white">
                        <?= htmlspecialchars($store['store_name']) ?>
                    </div>
                    <div class="text-[9px] text-zinc-600 font-bold uppercase tracking-widest">
                        Loja Oficial
                    </div>
                </div>
            </a>
            
            <h1 class="text-3xl font-black uppercase tracking-tight mb-2">
                <?= $action === 'register' ? 'Criar' : 'Entrar na' ?> <span class="gradient-text">Conta</span>
            </h1>
            <p class="text-zinc-500 text-sm">
                <?= $action === 'register' ? 'Registre-se para comentar e muito mais' : 'Acesse sua conta da loja' ?>
            </p>
        </div>

        <!-- Mensagens -->
        <?php if($message): ?>
            <div class="glass border-<?= $messageType === 'success' ? 'green' : 'red' ?>-600/30 text-<?= $messageType === 'success' ? 'green' : 'red' ?>-500 p-4 rounded-2xl mb-6 text-sm font-bold flex items-center gap-3">
                <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="glass rounded-2xl p-1.5 flex gap-1.5 mb-6">
            <a href="?action=login" class="flex-1 py-3 rounded-xl text-xs font-black uppercase tracking-wider text-center transition <?= $action === 'login' ? 'bg-primary text-white' : 'text-zinc-500 hover:text-white' ?>">
                Login
            </a>
            <a href="?action=register" class="flex-1 py-3 rounded-xl text-xs font-black uppercase tracking-wider text-center transition <?= $action === 'register' ? 'bg-primary text-white' : 'text-zinc-500 hover:text-white' ?>">
                Registrar
            </a>
        </div>

        <!-- Form: Login -->
        <?php if ($action === 'login'): ?>
        <form method="POST" class="glass rounded-3xl p-8">
            <input type="hidden" name="action" value="login">
            
            <div class="space-y-5">
                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-2 block">Email</label>
                    <input type="email" name="email" required 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-primary transition"
                           placeholder="seu@email.com">
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-2 block">Senha</label>
                    <input type="password" name="password" required 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-primary transition"
                           placeholder="••••••••">
                </div>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="remember" class="w-5 h-5 rounded accent-primary cursor-pointer">
                    <span class="text-sm text-zinc-400">Lembrar-me por 30 dias</span>
                </label>

                <button type="submit" class="w-full bg-primary hover:brightness-110 text-white py-4 rounded-xl font-black uppercase text-sm tracking-widest transition shadow-lg">
                    Entrar
                </button>
            </div>
        </form>
        <?php endif; ?>

        <!-- Form: Registro -->
        <?php if ($action === 'register'): ?>
        <form method="POST" class="glass rounded-3xl p-8">
            <input type="hidden" name="action" value="register">
            
            <div class="space-y-5">
                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-2 block">Nick do Minecraft *</label>
                    <input type="text" name="minecraft_nick" required 
                           pattern="[a-zA-Z0-9_]{3,16}"
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-primary transition"
                           placeholder="SeuNick123">
                    <p class="text-[10px] text-zinc-600 mt-2">Será verificado se existe no Minecraft</p>
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-2 block">Email *</label>
                    <input type="email" name="email" required 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-primary transition"
                           placeholder="seu@email.com">
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-2 block">Senha *</label>
                    <input type="password" name="password" required minlength="6"
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-primary transition"
                           placeholder="••••••••">
                    <p class="text-[10px] text-zinc-600 mt-2">Mínimo 6 caracteres</p>
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-2 block">Confirmar Senha *</label>
                    <input type="password" name="password_confirm" required minlength="6"
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-primary transition"
                           placeholder="••••••••">
                </div>

                <div class="glass p-4 rounded-xl border border-blue-600/20 bg-blue-600/5">
                    <div class="flex items-start gap-3">
                        <i data-lucide="shield-check" class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <p class="text-xs font-bold text-blue-500 mb-1">Verificação de Nick</p>
                            <p class="text-[10px] text-zinc-400 leading-relaxed">
                                Seu nick será verificado na API do Minecraft. Certifique-se de usar um nick válido e registrado.
                            </p>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full bg-primary hover:brightness-110 text-white py-4 rounded-xl font-black uppercase text-sm tracking-widest transition shadow-lg">
                    Criar Conta
                </button>
            </div>
        </form>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center mt-6">
            <a href="index.php" class="text-xs text-zinc-500 hover:text-white transition font-bold uppercase tracking-wider">
                ← Voltar para a loja
            </a>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>