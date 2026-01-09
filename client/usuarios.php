<?php
/**
 * ============================================
 * GERENCIAMENTO DE USUÁRIOS V2.0
 * ============================================
 * + Sistema de Staff/Equipe
 * + Ranks personalizados
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
// PROMOVER/DESPROMOVER STAFF
// ========================================
if (isset($_GET['toggle_staff']) && is_numeric($_GET['toggle_staff'])) {
    try {
        $user_id = (int)$_GET['toggle_staff'];
        
        $stmt = $pdo->prepare("SELECT is_staff FROM store_users WHERE id = ? AND store_id = ?");
        $stmt->execute([$user_id, $store_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $new_status = $user['is_staff'] ? 0 : 1;
            
            $pdo->beginTransaction();
            
            // Atualiza status de staff
            $stmt = $pdo->prepare("
                UPDATE store_users 
                SET is_staff = ?, 
                    staff_role = ?,
                    staff_description = ?
                WHERE id = ? AND store_id = ?
            ");
            
            $stmt->execute([
                $new_status,
                $new_status ? 'Staff' : null,
                $new_status ? 'Membro da equipe' : null,
                $user_id,
                $store_id
            ]);
            
            if ($new_status) {
                // Adiciona na tabela store_team
                $user_data = $pdo->prepare("SELECT * FROM store_users WHERE id = ?")->execute([$user_id]);
                $user_data = $pdo->query("SELECT * FROM store_users WHERE id = {$user_id}")->fetch();
                
                $stmt = $pdo->prepare("
                    INSERT INTO store_team 
                    (store_id, user_id, minecraft_nick, minecraft_uuid, role, role_color, skin_url, is_visible)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                
                $stmt->execute([
                    $store_id,
                    $user_id,
                    $user_data['minecraft_nick'],
                    $user_data['minecraft_uuid'],
                    'Staff',
                    '#8b5cf6',
                    $user_data['skin_url']
                ]);
            } else {
                // Remove da tabela store_team
                $pdo->prepare("DELETE FROM store_team WHERE user_id = ? AND store_id = ?")
                    ->execute([$user_id, $store_id]);
            }
            
            $pdo->commit();
            
            header('Location: usuarios.php?success=' . ($new_status ? 'promoted' : 'demoted'));
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Erro: " . $e->getMessage();
        $messageType = "error";
    }
}

// ========================================
// BANIR/DESBANIR
// ========================================
if (isset($_GET['toggle_ban']) && is_numeric($_GET['toggle_ban'])) {
    try {
        $user_id = (int)$_GET['toggle_ban'];
        
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

// ========================================
// DELETAR USUÁRIO
// ========================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $pdo->beginTransaction();
        
        $user_id = (int)$_GET['delete'];
        
        // Remove da equipe se for staff
        $pdo->prepare("DELETE FROM store_team WHERE user_id = ? AND store_id = ?")
            ->execute([$user_id, $store_id]);
        
        // Deleta usuário
        $pdo->prepare("DELETE FROM store_users WHERE id = ? AND store_id = ?")
            ->execute([$user_id, $store_id]);
        
        $pdo->commit();
        
        header('Location: usuarios.php?success=deleted');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Erro ao deletar: " . $e->getMessage();
        $messageType = "error";
    }
}

// ========================================
// ATUALIZAR RANK
// ========================================
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

// ========================================
// ATUALIZAR STAFF
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_staff') {
    $user_id = (int)$_POST['user_id'];
    $staff_role = trim($_POST['staff_role']);
    $staff_description = trim($_POST['staff_description']);
    $role_color = trim($_POST['role_color']);
    
    try {
        $pdo->beginTransaction();
        
        // Atualiza dados do usuário
        $stmt = $pdo->prepare("
            UPDATE store_users 
            SET staff_role = ?, staff_description = ?
            WHERE id = ? AND store_id = ?
        ");
        $stmt->execute([$staff_role, $staff_description, $user_id, $store_id]);
        
        // Atualiza na tabela de equipe
        $stmt = $pdo->prepare("
            UPDATE store_team 
            SET role = ?, role_color = ?, description = ?
            WHERE user_id = ? AND store_id = ?
        ");
        $stmt->execute([$staff_role, $role_color, $staff_description, $user_id, $store_id]);
        
        $pdo->commit();
        
        header('Location: usuarios.php?success=staff_updated');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Erro ao atualizar staff: " . $e->getMessage();
        $messageType = "error";
    }
}

// ========================================
// BUSCAR USUÁRIOS
// ========================================
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
    } elseif ($filter === 'staff') {
        $sql .= " AND is_staff = 1";
    }
    
    $sql .= " ORDER BY is_staff DESC, created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas
    $stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_banned = 0 THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN is_banned = 1 THEN 1 ELSE 0 END) as banned,
            SUM(CASE WHEN is_staff = 1 THEN 1 ELSE 0 END) as staff,
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
    $stats = ['total' => 0, 'active' => 0, 'banned' => 0, 'staff' => 0, 'total_purchases' => 0, 'total_spent' => 0];
}

if (isset($_GET['success'])) {
    $messages = [
        'promoted' => '✓ Usuário promovido a staff!',
        'demoted' => '✓ Usuário removido da staff!',
        'banned' => '✓ Usuário banido!',
        'unbanned' => '✓ Usuário desbanido!',
        'deleted' => '✓ Usuário removido!',
        'rank_updated' => '✓ Rank atualizado!',
        'staff_updated' => '✓ Dados da staff atualizados!'
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
                    Gerencie jogadores e equipe
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
                    <span class="text-[10px] text-purple-600 font-bold uppercase block mb-0.5">Staff</span>
                    <span class="text-xl font-black text-purple-500"><?= $stats['staff'] ?></span>
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
                    <a href="?filter=staff<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="flex-1 glass px-4 py-3 rounded-xl text-xs font-black uppercase text-center transition hover:bg-white/5 <?= $filter === 'staff' ? 'bg-purple-600/20 text-purple-500' : 'text-zinc-500' ?>">
                        Staff
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
                            
                            <?php if ($user['is_staff']): ?>
                                <div class="absolute -top-2 -right-2 w-7 h-7 bg-purple-600 rounded-full flex items-center justify-center border-2 border-black">
                                    <i data-lucide="shield-check" class="w-4 h-4 text-white"></i>
                                </div>
                            <?php endif; ?>
                            
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
                                
                                <?php if ($user['is_staff']): ?>
                                    <span class="bg-purple-500/10 text-purple-500 border border-purple-500/20 px-3 py-1 rounded-lg text-[10px] font-black uppercase">
                                        <i data-lucide="crown" class="w-3 h-3 inline"></i>
                                        <?= htmlspecialchars($user['staff_role'] ?? 'Staff') ?>
                                    </span>
                                <?php endif; ?>
                                
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

                            <?php if ($user['is_staff'] && !empty($user['staff_description'])): ?>
                                <div class="mt-3 text-xs text-zinc-500 italic">
                                    "<?= htmlspecialchars($user['staff_description']) ?>"
                                </div>
                            <?php endif; ?>

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
                            
                            <?php if ($user['is_staff']): ?>
                                <button onclick="openStaffModal(<?= htmlspecialchars(json_encode($user)) ?>)" 
                                        class="bg-purple-900/20 hover:bg-purple-900/30 text-purple-500 text-[10px] font-black uppercase px-4 py-2 rounded-xl transition whitespace-nowrap">
                                    Editar Staff
                                </button>
                            <?php endif; ?>
                            
                            <a href="?toggle_staff=<?= $user['id'] ?>" 
                               onclick="return confirm('<?= $user['is_staff'] ? 'Remover' : 'Promover' ?> usuário da equipe?')"
                               class="bg-purple-900/20 hover:bg-purple-900/30 text-purple-500 text-[10px] font-black uppercase px-4 py-2 rounded-xl transition text-center">
                                <?= $user['is_staff'] ? 'Despromover' : 'Promover Staff' ?>
                            </a>
                            
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
                <h3 class="text-xl font-black italic uppercase">Editar <span class="text-red-600">Rank</span></h3>
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
                                    onclick="selectRankColor('<?= $color ?>')"
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

    <!-- Modal: Editar Staff -->
    <div id="staffModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-md p-8 rounded-3xl border-purple-600/20">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-black italic uppercase">Editar <span class="text-purple-600">Staff</span></h3>
                <button onclick="closeStaffModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <form method="POST" id="staffForm" class="space-y-5">
                <input type="hidden" name="action" value="update_staff">
                <input type="hidden" name="user_id" id="staffUserId">

                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Cargo na Staff</label>
                    <input type="text" name="staff_role" id="staffRole" required
                           placeholder="Ex: Admin, Moderador, Helper..."
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-600 transition">
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Descrição</label>
                    <textarea name="staff_description" id="staffDescription" rows="3"
                              placeholder="Descreva o papel deste membro na equipe..."
                              class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-600 transition resize-none"></textarea>
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Cor do Cargo</label>
                    <div class="grid grid-cols-8 gap-2 mb-3">
                        <?php foreach ($colors as $color => $name): ?>
                            <button type="button" 
                                    onclick="selectStaffColor('<?= $color ?>')"
                                    class="w-10 h-10 rounded-lg border-2 border-white/10 hover:border-white/30 transition"
                                    style="background: <?= $color ?>;"
                                    title="<?= $name ?>"></button>
                        <?php endforeach; ?>
                    </div>
                    <input type="text" name="role_color" id="staffRoleColor" required
                           pattern="#[0-9A-Fa-f]{6}"
                           placeholder="#8b5cf6"
                           value="#8b5cf6"
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-600 transition">
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeStaffModal()" 
                            class="flex-1 bg-zinc-900 hover:bg-zinc-800 py-3 rounded-xl font-black uppercase text-xs transition">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-purple-600 hover:bg-purple-700 py-3 rounded-xl font-black uppercase text-xs tracking-widest transition shadow-lg shadow-purple-600/20">
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
        
        function selectRankColor(color) {
            document.getElementById('userRankColor').value = color;
        }
        
        function openStaffModal(user) {
            document.getElementById('staffUserId').value = user.id;
            document.getElementById('staffRole').value = user.staff_role || 'Staff';
            document.getElementById('staffDescription').value = user.staff_description || '';
            document.getElementById('staffRoleColor').value = user.rank_color || '#8b5cf6';
            document.getElementById('staffModal').classList.remove('hidden');
            lucide.createIcons();
        }
        
        function closeStaffModal() {
            document.getElementById('staffModal').classList.add('hidden');
        }
        
        function selectStaffColor(color) {
            document.getElementById('staffRoleColor').value = color;
        }
        
        document.getElementById('rankModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeRankModal();
        });
        
        document.getElementById('staffModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeStaffModal();
        });
    </script>
</body>
</html>