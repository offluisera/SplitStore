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

// ========================================
// FILTROS
// ========================================
$search = $_GET['search'] ?? '';
$rank_filter = $_GET['rank'] ?? '';
$order_by = $_GET['order_by'] ?? 'recent'; // recent, oldest, most_spent, most_active

// ========================================
// BANIR/DESBANIR USUÁRIO
// ========================================
if (isset($_GET['toggle_ban']) && is_numeric($_GET['toggle_ban'])) {
    $user_id = (int)$_GET['toggle_ban'];
    
    try {
        $stmt = $pdo->prepare("SELECT is_banned FROM store_users WHERE id = ? AND store_id = ?");
        $stmt->execute([$user_id, $store_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $new_status = $user['is_banned'] ? 0 : 1;
            $pdo->prepare("UPDATE store_users SET is_banned = ? WHERE id = ?")->execute([$new_status, $user_id]);
            
            header('Location: usuarios.php?success=' . ($new_status ? 'user_banned' : 'user_unbanned'));
            exit;
        }
    } catch (Exception $e) {
        $message = "Erro: " . $e->getMessage();
        $messageType = "error";
    }
}

// ========================================
// ATUALIZAR RANK
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_rank') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $rank = trim($_POST['rank'] ?? 'Membro');
    $rank_color = trim($_POST['rank_color'] ?? '#9CA3AF');
    
    try {
        $stmt = $pdo->prepare("UPDATE store_users SET rank = ?, rank_color = ? WHERE id = ? AND store_id = ?");
        if ($stmt->execute([$rank, $rank_color, $user_id, $store_id])) {
            header('Location: usuarios.php?success=rank_updated');
            exit;
        }
    } catch (Exception $e) {
        $message = "Erro: " . $e->getMessage();
        $messageType = "error";
    }
}

// ========================================
// BUSCAR USUÁRIOS
// ========================================
$sql = "SELECT 
            su.*,
            COUNT(DISTINCT nc.id) as total_comments,
            COUNT(DISTINCT p.id) as total_purchases_count,
            COALESCE(SUM(CASE WHEN p.status = 'delivered' THEN p.amount ELSE 0 END), 0) as total_spent_sum
        FROM store_users su
        LEFT JOIN news_comments nc ON su.id = nc.user_id AND nc.is_deleted = 0
        LEFT JOIN purchases p ON su.minecraft_uuid = p.player_uuid AND p.store_id = su.store_id
        WHERE su.store_id = ?";

$params = [$store_id];

if (!empty($search)) {
    $sql .= " AND (su.minecraft_nick LIKE ? OR su.email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($rank_filter)) {
    $sql .= " AND su.rank = ?";
    $params[] = $rank_filter;
}

$sql .= " GROUP BY su.id";

switch ($order_by) {
    case 'oldest':
        $sql .= " ORDER BY su.created_at ASC";
        break;
    case 'most_spent':
        $sql .= " ORDER BY total_spent_sum DESC";
        break;
    case 'most_active':
        $sql .= " ORDER BY total_comments DESC";
        break;
    case 'recent':
    default:
        $sql .= " ORDER BY su.created_at DESC";
        break;
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Estatísticas
    $total_users = count($users);
    $banned_users = count(array_filter($users, fn($u) => $u['is_banned']));
    $total_revenue_from_users = array_sum(array_column($users, 'total_spent_sum'));
    
    // Buscar ranks únicos
    $stmt = $pdo->prepare("SELECT DISTINCT rank FROM store_users WHERE store_id = ?");
    $stmt->execute([$store_id]);
    $ranks = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    error_log("Users Error: " . $e->getMessage());
    $users = [];
    $ranks = [];
}

if (isset($_GET['success'])) {
    $messages = [
        'user_banned' => 'Usuário banido com sucesso!',
        'user_unbanned' => 'Ban removido com sucesso!',
        'rank_updated' => 'Rank atualizado!'
    ];
    $message = $messages[$_GET['success']] ?? '';
    $messageType = "success";
}

function formatMoney($val) {
    return 'R$ ' . number_format($val, 2, ',', '.');
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
        .user-card:hover { transform: translateY(-2px); border-color: rgba(220, 38, 38, 0.3); }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        
        <!-- Header -->
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">
                    Usuários <span class="text-red-600">Cadastrados</span>
                </h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">
                    Gerencie os usuários da sua loja
                </p>
            </div>
            
            <div class="flex gap-3">
                <button onclick="exportUsers()" class="glass px-6 py-3 rounded-2xl hover:border-red-600/40 transition flex items-center gap-2">
                    <i data-lucide="download" class="w-4 h-4 text-zinc-500"></i>
                    <span class="text-xs font-black uppercase">Exportar</span>
                </button>
            </div>
        </header>

        <?php if($message): ?>
            <div class="glass border-<?= $messageType === 'success' ? 'green' : 'red' ?>-600/20 text-<?= $messageType === 'success' ? 'green' : 'red' ?>-500 p-5 rounded-2xl mb-8 flex items-center gap-3">
                <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <span class="font-bold"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-blue-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="users" class="w-5 h-5 text-blue-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Total Usuários</p>
                <h3 class="text-3xl font-black"><?= $total_users ?></h3>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-green-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="dollar-sign" class="w-5 h-5 text-green-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Receita Total</p>
                <h3 class="text-3xl font-black text-green-500"><?= formatMoney($total_revenue_from_users) ?></h3>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-purple-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="shield" class="w-5 h-5 text-purple-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Ranks Diferentes</p>
                <h3 class="text-3xl font-black text-purple-500"><?= count($ranks) ?></h3>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-red-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="user-x" class="w-5 h-5 text-red-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Usuários Banidos</p>
                <h3 class="text-3xl font-black text-red-500"><?= $banned_users ?></h3>
            </div>
        </div>

        <!-- Filtros -->
        <div class="glass p-6 rounded-3xl mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                
                <!-- Busca -->
                <div class="md:col-span-2">
                    <div class="relative">
                        <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-600"></i>
                        <input type="text" name="search" placeholder="Buscar por nick ou email..." 
                               value="<?= htmlspecialchars($search) ?>"
                               class="w-full bg-white/5 border border-white/10 pl-12 pr-4 py-3 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                </div>

                <!-- Rank -->
                <div>
                    <select name="rank" class="w-full bg-zinc-900 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-600 transition">
                        <option value="">Todos Ranks</option>
                        <?php foreach ($ranks as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>" <?= $rank_filter === $r ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Ordenação -->
                <div>
                    <select name="order_by" class="w-full bg-zinc-900 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-600 transition">
                        <option value="recent" <?= $order_by === 'recent' ? 'selected' : '' ?>>Mais Recentes</option>
                        <option value="oldest" <?= $order_by === 'oldest' ? 'selected' : '' ?>>Mais Antigos</option>
                        <option value="most_spent" <?= $order_by === 'most_spent' ? 'selected' : '' ?>>Maior Gasto</option>
                        <option value="most_active" <?= $order_by === 'most_active' ? 'selected' : '' ?>>Mais Ativos</option>
                    </select>
                </div>

                <div class="md:col-span-4 flex gap-3">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 px-8 py-3 rounded-xl font-black text-xs uppercase transition">
                        Aplicar Filtros
                    </button>
                    <a href="usuarios.php" class="glass px-8 py-3 rounded-xl font-black text-xs uppercase text-zinc-500 hover:text-white transition">
                        Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- Lista de Usuários -->
        <div class="grid gap-4">
            <?php if (empty($users)): ?>
                <div class="glass rounded-3xl p-24 text-center opacity-30">
                    <i data-lucide="users" class="w-16 h-16 mx-auto mb-4 text-zinc-700"></i>
                    <p class="text-xs font-bold uppercase tracking-widest text-zinc-700">
                        Nenhum usuário cadastrado
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                <div class="user-card glass p-6 rounded-2xl border border-white/5">
                    <div class="flex items-start justify-between gap-4">
                        
                        <!-- Info Principal -->
                        <div class="flex items-start gap-4 flex-1 min-w-0">
                            <!-- Skin -->
                            <img src="<?= htmlspecialchars($user['skin_url']) ?>" class="w-16 h-16 rounded-xl flex-shrink-0">
                            
                            <!-- Dados -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="text-lg font-black truncate"><?= htmlspecialchars($user['minecraft_nick']) ?></h3>
                                    <span class="px-3 py-1 rounded-lg text-[10px] font-black uppercase" 
                                          style="background: <?= $user['rank_color'] ?>15; color: <?= $user['rank_color'] ?>; border: 1px solid <?= $user['rank_color'] ?>30;">
                                        <?= htmlspecialchars($user['rank']) ?>
                                    </span>
                                    <?php if ($user['is_banned']): ?>
                                        <span class="bg-red-500/10 text-red-500 border border-red-500/20 px-2 py-1 rounded text-[9px] font-black uppercase">
                                            Banido
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="text-sm text-zinc-500 mb-3 truncate"><?= htmlspecialchars($user['email']) ?></p>
                                
                                <div class="flex flex-wrap items-center gap-4 text-xs">
                                    <div class="flex items-center gap-2 text-zinc-500">
                                        <i data-lucide="shopping-bag" class="w-4 h-4 text-green-500"></i>
                                        <span><?= $user['total_purchases_count'] ?> compras</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-zinc-500">
                                        <i data-lucide="dollar-sign" class="w-4 h-4 text-green-500"></i>
                                        <span><?= formatMoney($user['total_spent_sum']) ?> gasto</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-zinc-500">
                                        <i data-lucide="message-circle" class="w-4 h-4 text-blue-500"></i>
                                        <span><?= $user['total_comments'] ?> comentários</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-zinc-500">
                                        <i data-lucide="calendar" class="w-4 h-4"></i>
                                        <span>Desde <?= date('d/m/Y', strtotime($user['created_at'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ações -->
                        <div class="flex flex-col gap-2">
                            <button onclick="openRankModal(<?= htmlspecialchars(json_encode($user)) ?>)" 
                                    class="bg-blue-900/20 hover:bg-blue-900/30 text-blue-500 text-[10px] font-black uppercase px-4 py-2 rounded-xl transition whitespace-nowrap">
                                Editar Rank
                            </button>
                            
                            <a href="?toggle_ban=<?= $user['id'] ?>" 
                               onclick="return confirm('<?= $user['is_banned'] ? 'Desbanir' : 'Banir' ?> este usuário?')"
                               class="<?= $user['is_banned'] ? 'bg-green-900/20 hover:bg-green-900/30 text-green-500' : 'bg-red-900/20 hover:bg-red-900/30 text-red-500' ?> text-[10px] font-black uppercase px-4 py-2 rounded-xl text-center transition whitespace-nowrap">
                                <?= $user['is_banned'] ? 'Desbanir' : 'Banir' ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

    <!-- Modal: Editar Rank -->
    <div id="rankModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-md p-10 rounded-[3rem] border-blue-600/20">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black italic uppercase">
                    Editar <span class="text-blue-600">Rank</span>
                </h3>
                <button onclick="closeRankModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <form method="POST" id="rankForm" class="space-y-6">
                <input type="hidden" name="action" value="update_rank">
                <input type="hidden" name="user_id" id="rankUserId">

                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Nome do Rank</label>
                    <input type="text" name="rank" id="rankName" required
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-blue-600 transition">
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Cor do Rank</label>
                    <div class="flex gap-3">
                        <input type="color" name="rank_color" id="rankColor" required
                               class="w-20 h-12 rounded-xl cursor-pointer">
                        <input type="text" id="rankColorText" readonly
                               class="flex-1 bg-white/5 border border-white/10 p-4 rounded-xl text-sm font-mono outline-none">
                    </div>
                </div>

                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeRankModal()" 
                            class="flex-1 bg-zinc-900 hover:bg-zinc-800 py-4 rounded-xl font-black uppercase text-xs transition">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-blue-600 hover:bg-blue-700 py-4 rounded-xl font-black uppercase text-xs transition">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function openRankModal(user) {
            document.getElementById('rankUserId').value = user.id;
            document.getElementById('rankName').value = user.rank;
            document.getElementById('rankColor').value = user.rank_color;
            document.getElementById('rankColorText').value = user.rank_color;
            document.getElementById('rankModal').classList.remove('hidden');
            lucide.createIcons();
        }
        
        function closeRankModal() {
            document.getElementById('rankModal').classList.add('hidden');
        }
        
        document.getElementById('rankColor')?.addEventListener('input', (e) => {
            document.getElementById('rankColorText').value = e.target.value;
        });
        
        function exportUsers() {
            alert('Funcionalidade de exportação será implementada');
        }
    </script>
</body>
</html>