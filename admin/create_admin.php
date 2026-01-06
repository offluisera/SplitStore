<?php
session_start();
require_once '../includes/db.php';

// Proteção: apenas super_admin pode acessar
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_role'] !== 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';
    
    // Validações
    if (empty($username) || empty($email) || empty($password)) {
        $message = 'Todos os campos são obrigatórios';
        $messageType = 'error';
    } elseif (strlen($password) < 12) {
        $message = 'A senha deve ter no mínimo 12 caracteres';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'E-mail inválido';
        $messageType = 'error';
    } else {
        try {
            // Gera hash seguro
            $hash = password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 1
            ]);
            
            // Insere no banco
            $stmt = $pdo->prepare("
                INSERT INTO admins (username, password_hash, email, role) 
                VALUES (?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$username, $hash, $email, $role])) {
                $message = "Administrador '{$username}' criado com sucesso!";
                $messageType = 'success';
                
                // Log de segurança
                error_log("New admin created: {$username} by {$_SESSION['admin_username']}");
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = 'Username ou e-mail já existe';
            } else {
                $message = 'Erro ao criar administrador';
            }
            $messageType = 'error';
        }
    }
}

// Busca lista de admins
$admins = $pdo->query("SELECT id, username, email, role, last_login, created_at FROM admins ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Admins | SplitStore</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #050505; color: white; }
        .glass { background: rgba(255,255,255,0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body class="p-12">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-black mb-8">
            Gerenciar <span class="text-red-600">Administradores</span>
        </h1>

        <?php if($message): ?>
            <div class="glass border-<?= $messageType == 'success' ? 'green' : 'red' ?>-600/20 text-<?= $messageType == 'success' ? 'green' : 'red' ?>-500 p-4 rounded-2xl mb-6">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Formulário -->
        <div class="glass p-8 rounded-3xl mb-8">
            <h2 class="text-xl font-black mb-6">Criar Novo Admin</h2>
            <form method="POST" class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-xs text-zinc-600 uppercase font-bold block mb-2">Username</label>
                    <input type="text" name="username" required 
                           class="w-full bg-white/5 border border-white/10 p-3 rounded-xl outline-none focus:border-red-600">
                </div>
                <div>
                    <label class="text-xs text-zinc-600 uppercase font-bold block mb-2">E-mail</label>
                    <input type="email" name="email" required 
                           class="w-full bg-white/5 border border-white/10 p-3 rounded-xl outline-none focus:border-red-600">
                </div>
                <div>
                    <label class="text-xs text-zinc-600 uppercase font-bold block mb-2">Senha (mín. 12 caracteres)</label>
                    <input type="password" name="password" required minlength="12"
                           class="w-full bg-white/5 border border-white/10 p-3 rounded-xl outline-none focus:border-red-600">
                </div>
                <div>
                    <label class="text-xs text-zinc-600 uppercase font-bold block mb-2">Nível de Acesso</label>
                    <select name="role" class="w-full bg-zinc-900 border border-white/10 p-3 rounded-xl outline-none">
                        <option value="admin">Admin</option>
                        <option value="moderator">Moderador</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <button type="submit" class="bg-red-600 px-8 py-3 rounded-xl font-black uppercase text-xs hover:bg-red-700">
                        Criar Administrador
                    </button>
                </div>
            </form>
        </div>

        <!-- Lista de Admins -->
        <div class="glass rounded-3xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-white/5">
                    <tr class="text-xs font-black uppercase text-zinc-500">
                        <th class="p-4 text-left">Username</th>
                        <th class="p-4 text-left">Email</th>
                        <th class="p-4 text-left">Nível</th>
                        <th class="p-4 text-left">Último Login</th>
                        <th class="p-4 text-left">Criado em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($admins as $a): ?>
                    <tr class="border-b border-white/5">
                        <td class="p-4 font-bold"><?= htmlspecialchars($a['username']) ?></td>
                        <td class="p-4 text-sm text-zinc-500"><?= htmlspecialchars($a['email']) ?></td>
                        <td class="p-4">
                            <span class="px-3 py-1 bg-red-600/10 border border-red-600/20 rounded-lg text-xs font-black uppercase">
                                <?= $a['role'] ?>
                            </span>
                        </td>
                        <td class="p-4 text-sm text-zinc-500">
                            <?= $a['last_login'] ? date('d/m/Y H:i', strtotime($a['last_login'])) : 'Nunca' ?>
                        </td>
                        <td class="p-4 text-sm text-zinc-500">
                            <?= date('d/m/Y', strtotime($a['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-8">
            <a href="dashboard.php" class="text-zinc-600 hover:text-white text-sm font-bold uppercase">
                ← Voltar ao Dashboard
            </a>
        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>