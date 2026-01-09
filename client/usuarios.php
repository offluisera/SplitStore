<?php
/**
 * ============================================
 * GERENCIAMENTO DE USUÁRIOS DA LOJA
 * ============================================
 * client/usuarios.php
 */

session_start();
require_once '../includes/db.php';
require_once '../includes/auth_guard.php';

requireAccess(__FILE__);

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];

$message = "";
$messageType = "";

// Banir/Desbanir usuário
if (isset($_GET['toggle_ban']) && is_numeric($_GET['toggle_ban'])) {
    try {
        $user_id = (int)$_GET['toggle_ban'];
        
        // Busca status atual
        $stmt = $pdo->prepare("SELECT is_banned FROM store_users WHERE id = ? AND store_id = ?");
        $stmt->execute([$user_id, $store_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $new_status = $user['is_banned'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE store_users SET is_banned = ? WHERE id = ? AND store_id = ?");
            $stmt->execute([$new_status, $user_id, $store_id]);
            
            header('Location: usuarios.php?success=' . ($new_status ? 'banned' : 'unbanned'));
            exit;
        }
    } catch (Exception $e) {
        $message = "Erro: " . $e->getMessage();
        $messageType = "error";
    }
}

// Deletar usuário
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM store_users WHERE id = ? AND store_id = ?");
        $stmt->execute([$_GET['delete'], $store_id]);
        header('Location: usuarios.php?success=deleted');
        exit;
    } catch (Exception $e) {
        $message = "Erro ao deletar: " . $e->getMessage();
        $messageType = "error";
    }
}

// Atualizar rank
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_rank') {
    $user_id = (int)$_POST['user_id'];
    $rank = trim($_POST['rank']);
    $rank_color = trim($_POST['rank_color']);
    
    try {
        $stmt = $pdo->prepare("
            UPDATE store_users 
            SET rank = ?, rank_color = ? 
            WHERE id = ? AND store_id = ?
        ");
        $stmt->execute([$rank, $rank_color, $user_id, $store_id]);
        
        header('Location: usuarios.php?success=rank_updated');
        exit;
    } catch (Exception $e) {
        $message = "Erro ao atualizar rank: " . $e->getMessage();
        $messageType = "error";
    }
}

// Buscar usuários
try {
    $search = $_GET['search'] ?? '';
    $filter = $_GET['filter'] ?? 'all';
    
    $sql = "SELECT * FROM store_users WHERE store_id = ?";
    $params = [$store_id];
    
    if (!empty($search)) {
        $sql .= " AND (minecraft_nick LIKE ? OR email LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    if ($filter === 'banned') {
        $sql .= " AND is_banned = 1";
    } elseif ($filter === 'active') {
        $sql .= " AND is_banned = 0";
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas
    $stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_banned = 0 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN is_banned = 1 THEN 1 ELSE 0 END) as banned,
            SUM(total_purchases) as total_purchases,
            SUM(total_spent) as total_spent
        FROM store_users 
        WHERE store_id = ?
    ");
    $stats->execute([$store_id]);
    $stats = $stats->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Users Error: " . $e->getMessage());
    $users = [];
    $stats = ['total' => 0, 'active' => 0, 'banned' => 0, 'total_purchases' => 0, 'total_spent' => 0];
}

if (isset($_GET['success'])) {
    $messages = [
        'banned' => '✓ Usuário banido com sucesso!',
        'unbanned' => '✓ Usuário desbanido!',
        'deleted' => '✓ Usuário removido!',
        'rank_updated' => '✓ Rank atualizado!'
    ];
    $message = $messages[$_GET['success']] ?? '';
    $messageType = "success";
}

function formatMoney($val) {
    return 'R$ ' . number_format((float)$val, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Usuários | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #000; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .user-card { transition: all 0.3s ease; }
        .user-card:hover { transform: translateY(-4px); border-color: rgba(220, 38, 38, 0.3); }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        
        <!-- Header -->
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">
                    Usuários da <span class="text-red-600">Loja</span>
                </h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">
                    Gerencie os clientes cadastrados
                </p>
            </div>
            
            <div class="flex items-center gap-3">
                <div class="glass px-4 py-2 rounded-xl">
                    <span class="text-[10px] text-zinc-600 font-bold uppercase block mb-0.5">Total</span>
                    <span class="text-xl font-black"><?= $stats['total'] ?></span>
                </div>
                <div class="glass px-4 py-2 rounded-xl">
                    <span class="text-[10px] text-green-600 font-bold uppercase block mb-0.5">Ativos</span>
                    <span class="text-xl font-black text-green-500"><?= $stats['active'] ?></span>
                </div>
                <div class="glass px-4 py-2 rounded-xl">
                    <span class="text-[10px] text-red-600 font-bold uppercase block mb-0.5">Banidos</span>
                    <span class="text-xl font-black text-red-500"><?= $stats['banned'] ?></span>
                </div>
            </div>
        </header>

        <?php if($message): ?>
            <div class="glass border-<?= $messageType === 'success' ? 'green' : 'red' ?>-600/20 text-<?= $messageType === 'success' ? 'green' : 'red' ?>-500 p-5 rounded-2xl mb-8 flex items-center gap-3">
                <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <span class="font-bold"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Filtros e Busca -->
        <div class="glass rounded-2xl p-6 mb-8">
            <div class="grid md:grid-cols-2 gap-4">
                <!-- Busca -->
                <form method="GET" class="relative">
                    <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-500"></i>
                    <input type="text" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="Buscar por nick ou email..."
                           class="w-full bg-white/5 border border-white/10 pl-12 pr-4 py-3 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    <?php if (!empty($_GET['filter'])): ?>
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($_GET['filter']) ?>">
                    <?php endif; ?>
                </form>

                <!-- Filtro -->
                <div class="flex gap-2">
                    <a href="?" class="flex-1 glass px-4 py-3 rounded-xl text-xs font-black uppercase text-center transition hover:bg-white/5 <?= empty($filter) || $filter === 'all' ? 'bg-red-600/20 text-red-500' : 'text-zinc-500' ?>">
                        Todos
                    </a>
                    <a href="?filter=active<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="flex-1 glass px-4 py-3 rounded-xl text-xs font-black uppercase text-center transition hover:bg-white/5 <?= $filter === 'active' ? 'bg-green-600/20 text-green-500' : 'text-zinc-500' ?>">
                        Ativos
                    </a>
                    <a href="?filter=banned<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="flex-1 glass px-4 py-3 rounded-xl text-xs font-black uppercase text-center transition hover:bg-white/5 <?= $filter === 'banned' ? 'bg-red-600/20 text-red-500' : 'text-zinc-500' ?>">
                        Banidos
                    </a>
                </div>
            </div>
        </div>

        <!-- Lista de Usuários -->
        <?php if (empty($users)): ?>
            <div class="glass rounded-3xl p-24 text-center opacity-30">
                <i data-lucide="users" class="w-16 h-16 mx-auto mb-4 text-zinc-700"></i>
                <p class="text-xs font-bold uppercase tracking-widest text-zinc-700">
                    <?= !empty($search) ? 'Nenhum usuário encontrado' : 'Nenhum usuário cadastrado ainda' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="grid gap-4">
                <?php foreach ($users as $user): ?>
                <div class="user-card glass rounded-2xl p-6 border border-white/5">
                    <div class="flex items-center gap-6">
                        
                        <!-- Avatar -->
                        <div class="relative">
                            <img src="<?= htmlspecialchars($user['skin_url']) ?>" 
                                 class="w-16 h-16 rounded-xl <?= $user['is_banned'] ? 'opacity-30 grayscale' : '' ?>">
                            <?php if ($user['is_banned']): ?>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <i data-lucide="ban" class="w-8 h-8 text-red-500"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Info Principal -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-lg font-black uppercase">
                                    <?= htmlspecialchars($user['minecraft_nick']) ?>
                                </h3>
                                <span class="px-3 py-1 rounded-lg text-[10px] font-black uppercase border"
                                      style="background: <?= $user['rank_color'] ?>15; color: <?= $user['rank_color'] ?>; border-color: <?= $user['rank_color'] ?>30;">
                                    <?= htmlspecialchars($user['rank']) ?>
                                </span>
                                <?php if ($user['is_banned']): ?>
                                    <span class="bg-red-500/10 text-red-500 border border-red-500/20 px-3 py-1 rounded-lg text-[10px] font-black uppercase">
                                        Banido
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="grid grid-cols-4 gap-6 text-xs">
                                <div>
                                    <span class="text-zinc-600 font-bold uppercase block mb-1">Email</span>
                                    <span class="text-zinc-400"><?= htmlspecialchars($user['email']) ?></span>
                                </div>
                                <div>
                                    <span class="text-zinc-600 font-bold uppercase block mb-1">UUID</span>
                                    <span class="text-zinc-400 font-mono"><?= htmlspecialchars(substr($user['minecraft_uuid'], 0, 8)) ?>...</span>
                                </div>
                                <div>
                                    <span class="text-zinc-600 font-bold uppercase block mb-1">Compras</span>
                                    <span class="text-zinc-400"><?= $user['total_purchases'] ?> pedidos</span>
                                </div>
                                <div>
                                    <span class="text-zinc-600 font-bold uppercase block mb-1">Total Gasto</span>
                                    <span class="text-green-500 font-bold"><?= formatMoney($user['total_spent']) ?></span>
                                </div>
                            </div>

                            <div class="mt-3 text-[10px] text-zinc-700 flex items-center gap-4">
                                <span>Cadastro: <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></span>
                                <?php if ($user['last_login']): ?>
                                    <span>• Último login: <?= date('d/m/Y H:i', strtotime($user['last_login'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Ações -->
                        <div class="flex flex-col gap-2">
                            <button onclick="openRankModal(<?= htmlspecialchars(json_encode($user)) ?>)" 
                                    class="bg-blue-900/20 hover:bg-blue-900/30 text-blue-500 text-[10px] font-black uppercase px-4 py-2 rounded-xl transition whitespace-nowrap">
                                Editar Rank
                            </button>
                            <a href="?toggle_ban=<?= $user['id'] ?>" 
                               onclick="return confirm('Tem certeza?')"
                               class="bg-yellow-900/20 hover:bg-yellow-900/30 text-yellow-500 text-[10px] font-black uppercase px-4 py-2 rounded-xl transition text-center">
                                <?= $user['is_banned'] ? 'Desbanir' : 'Banir' ?>
                            </a>
                            <a href="?delete=<?= $user['id'] ?>" 
                               onclick="return confirm('Deletar usuário permanentemente?')"
                               class="bg-red-900/20 hover:bg-red-900/30 text-red-500 text-[10px] font-black uppercase px-4 py-2 rounded-xl transition text-center">
                                Deletar
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- Modal: Editar Rank -->
    <div id="rankModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-md p-8 rounded-3xl border-red-600/20">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-black italic uppercase">
                    Editar <span class="text-red-600">Rank</span>
                </h3>
                <button onclick="closeRankModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <form method="POST" id="rankForm" class="space-y-5">
                <input type="hidden" name="action" value="update_rank">
                <input type="hidden" name="user_id" id="userId">

                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Nick</label>
                    <input type="text" id="userNick" disabled
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm">
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Nome do Rank</label>
                    <input type="text" name="rank" id="userRank" required
                           placeholder="Ex: VIP, Membro, Admin..."
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Cor do Rank</label>
                    <div class="grid grid-cols-8 gap-2 mb-3">
                        <?php
                        $colors = [
                            '#ef4444' => 'Vermelho',
                            '#f97316' => 'Laranja', 
                            '#eab308' => 'Amarelo',
                            '#22c55e' => 'Verde',
                            '#3b82f6' => 'Azul',
                            '#8b5cf6' => 'Roxo',
                            '#ec4899' => 'Rosa',
                            '#9CA3AF' => 'Cinza'
                        ];
                        foreach ($colors as $color => $name): ?>
                            <button type="button" 
                                    onclick="selectColor('<?= $color ?>')"
                                    class="w-10 h-10 rounded-lg border-2 border-white/10 hover:border-white/30 transition"
                                    style="background: <?= $color ?>;"
                                    title="<?= $name ?>"></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="text" name="rank_color" id="userRankColor" required
                           pattern="#[0-9A-Fa-f]{6}"
                           placeholder="#ef4444"
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeRankModal()" 
                            class="flex-1 bg-zinc-900 hover:bg-zinc-800 py-3 rounded-xl font-black uppercase text-xs transition">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-red-600 hover:bg-red-700 py-3 rounded-xl font-black uppercase text-xs tracking-widest transition shadow-lg shadow-red-600/20">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function openRankModal(user) {
            document.getElementById('userId').value = user.id;
            document.getElementById('userNick').value = user.minecraft_nick;
            document.getElementById('userRank').value = user.rank;
            document.getElementById('userRankColor').value = user.rank_color;
            document.getElementById('rankModal').classList.remove('hidden');
            lucide.createIcons();
        }
        
        function closeRankModal() {
            document.getElementById('rankModal').classList.add('hidden');
        }
        
        function selectColor(color) {
            document.getElementById('userRankColor').value = color;
        }
        
        document.getElementById('rankModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeRankModal();
        });
    </script>
</body>
</html>