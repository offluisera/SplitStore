<?php
/**
 * ============================================
 * SPLITSTORE - CUSTOMIZAÇÃO + MENU EDITOR
 * ============================================
 */

session_start();
require_once '../includes/db.php';
require_once '../includes/auth_guard.php';

requireAccess(__FILE__);

if (!isset($_SESSION['store_logged'])) {
    header('Location: login.php');
    exit;
}

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];

$message = "";
$messageType = "";

// ========================================
// GERENCIAR MENU
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    switch ($_POST['action']) {
        
        // ADICIONAR ITEM DE MENU
        case 'add_menu_item':
            $label = trim($_POST['label'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $icon = trim($_POST['icon'] ?? '');
            
            if (empty($label) || empty($url)) {
                $message = "Preencha label e URL.";
                $messageType = "error";
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO store_menu (store_id, label, url, icon, order_position, is_system)
                        SELECT ?, ?, ?, ?, COALESCE(MAX(order_position), 0) + 1, 0
                        FROM store_menu WHERE store_id = ?
                    ");
                    $stmt->execute([$store_id, $label, $url, $icon, $store_id]);
                    $message = "Item adicionado ao menu!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $message = "Erro: " . $e->getMessage();
                    $messageType = "error";
                }
            }
            break;
        
        // ATUALIZAR ORDEM DO MENU
        case 'update_menu_order':
            $order = json_decode($_POST['order'] ?? '[]', true);
            
            if (is_array($order)) {
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("UPDATE store_menu SET order_position = ? WHERE id = ? AND store_id = ?");
                    
                    foreach ($order as $position => $id) {
                        $stmt->execute([$position, $id, $store_id]);
                    }
                    
                    $pdo->commit();
                    $message = "Ordem atualizada!";
                    $messageType = "success";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = "Erro: " . $e->getMessage();
                    $messageType = "error";
                }
            }
            break;
        
        // TOGGLE ITEM
        case 'toggle_menu_item':
            $id = (int)($_POST['id'] ?? 0);
            
            try {
                $stmt = $pdo->prepare("UPDATE store_menu SET is_enabled = NOT is_enabled WHERE id = ? AND store_id = ?");
                $stmt->execute([$id, $store_id]);
                exit('OK');
            } catch (PDOException $e) {
                exit('ERROR');
            }
            break;
        
        // DELETAR ITEM
        case 'delete_menu_item':
            $id = (int)($_POST['id'] ?? 0);
            
            try {
                $stmt = $pdo->prepare("DELETE FROM store_menu WHERE id = ? AND store_id = ? AND is_system = 0");
                $stmt->execute([$id, $store_id]);
                
                if ($stmt->rowCount() > 0) {
                    $message = "Item removido!";
                    $messageType = "success";
                } else {
                    $message = "Item não pode ser removido (sistema).";
                    $messageType = "error";
                }
            } catch (PDOException $e) {
                $message = "Erro: " . $e->getMessage();
                $messageType = "error";
            }
            break;
        
        // SALVAR DESIGN
        case 'save_design':
            $data = [
                'template' => $_POST['template'] ?? 'neon_gaming',
                'primary_color' => $_POST['primary_color'] ?? '#8b5cf6',
                'secondary_color' => $_POST['secondary_color'] ?? '#0f172a',
                'accent_color' => $_POST['accent_color'] ?? '#ec4899',
                'logo_url' => trim($_POST['logo_url'] ?? ''),
                'favicon_url' => trim($_POST['favicon_url'] ?? ''),
                'banner_url' => trim($_POST['banner_url'] ?? ''),
                'background_pattern' => $_POST['background_pattern'] ?? 'dots',
                'animation_style' => $_POST['animation_style'] ?? 'smooth',
                'card_style' => $_POST['card_style'] ?? 'glass',
                'button_style' => $_POST['button_style'] ?? 'rounded',
                'font_family' => $_POST['font_family'] ?? 'inter',
                'store_title' => trim($_POST['store_title'] ?? ''),
                'store_description' => trim($_POST['store_description'] ?? ''),
                'store_tagline' => trim($_POST['store_tagline'] ?? ''),
                'show_particles' => isset($_POST['show_particles']) ? 1 : 0,
                'show_blur_effects' => isset($_POST['show_blur_effects']) ? 1 : 0,
                'dark_mode' => isset($_POST['dark_mode']) ? 1 : 0,
                'show_gradient_bg' => isset($_POST['show_gradient_bg']) ? 1 : 0,
                'custom_css' => $_POST['custom_css'] ?? '',
                'custom_js' => $_POST['custom_js'] ?? ''
            ];
            
            try {
                $check = $pdo->prepare("SELECT id FROM store_customization WHERE store_id = ?");
                $check->execute([$store_id]);
                
                if ($check->fetch()) {
                    $fields = array_keys($data);
                    $sql = "UPDATE store_customization SET " . 
                           implode(', ', array_map(fn($f) => "$f = ?", $fields)) . 
                           " WHERE store_id = ?";
                    $values = array_merge(array_values($data), [$store_id]);
                } else {
                    $fields = array_keys($data);
                    $sql = "INSERT INTO store_customization (store_id, " . implode(', ', $fields) . ") 
                            VALUES (?" . str_repeat(', ?', count($fields)) . ")";
                    $values = array_merge([$store_id], array_values($data));
                }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                
                $message = "✨ Customizações salvas com sucesso!";
                $messageType = "success";
                
            } catch (PDOException $e) {
                $message = "Erro ao salvar: " . $e->getMessage();
                $messageType = "error";
            }
            break;
    }
}

// Buscar itens do menu
$menu_items = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM store_menu WHERE store_id = ? ORDER BY order_position ASC");
    $stmt->execute([$store_id]);
    $menu_items = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Menu fetch error: " . $e->getMessage());
}

// Buscar customização
$stmt = $pdo->prepare("SELECT * FROM store_customization WHERE store_id = ?");
$stmt->execute([$store_id]);
$custom = $stmt->fetch(PDO::FETCH_ASSOC);

$defaults = [
    'template' => 'neon_gaming',
    'primary_color' => '#8b5cf6',
    'secondary_color' => '#0f172a',
    'accent_color' => '#ec4899',
    'background_pattern' => 'dots',
    'animation_style' => 'smooth',
    'card_style' => 'glass',
    'button_style' => 'rounded',
    'font_family' => 'inter',
    'logo_url' => '',
    'favicon_url' => '',
    'banner_url' => '',
    'store_title' => $store_name,
    'store_description' => 'Loja premium de itens Minecraft',
    'store_tagline' => 'Os melhores items do servidor',
    'show_particles' => 1,
    'show_blur_effects' => 1,
    'dark_mode' => 1,
    'show_gradient_bg' => 1,
    'custom_css' => '',
    'custom_js' => ''
];

$c = $custom ? array_merge($defaults, $custom) : $defaults;

$lucide_icons = [
    'home', 'shopping-bag', 'newspaper', 'shield-check', 'users', 'book-open',
    'star', 'heart', 'zap', 'trophy', 'gift', 'settings', 'info',
    'mail', 'phone', 'map-pin', 'globe', 'gamepad-2', 'sword',
    'shield', 'crown', 'gem', 'coins', 'ticket', 'package'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Customização | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; background: #000; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-strong { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(40px); border: 1px solid rgba(255, 255, 255, 0.1); }
        
        .menu-item {
            cursor: move;
            transition: all 0.2s;
        }
        
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .menu-item.sortable-ghost {
            opacity: 0.4;
        }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-8 overflow-y-auto">
        
        <header class="mb-12">
            <h1 class="text-4xl font-black uppercase tracking-tighter mb-2">
                Customização <span class="text-purple-500">Avançada</span>
            </h1>
            <p class="text-zinc-500 text-sm">
                Personalize sua loja e menu de navegação
            </p>
        </header>

        <?php if($message): ?>
        <div class="glass-strong border-<?= $messageType == 'success' ? 'green' : 'red' ?>-500/30 text-<?= $messageType == 'success' ? 'green' : 'red' ?>-400 p-5 rounded-2xl mb-8 flex items-center gap-3">
            <i data-lucide="<?= $messageType == 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
            <span class="font-bold"><?= htmlspecialchars($message) ?></span>
        </div>
        <?php endif; ?>

        <!-- TABS -->
        <div class="glass-strong rounded-2xl p-2 flex gap-2 overflow-x-auto mb-8">
            <button type="button" onclick="switchTab('menu')" class="tab-button active px-6 py-3 rounded-xl font-bold text-sm transition whitespace-nowrap">
                <i data-lucide="menu" class="w-4 h-4 inline mr-2"></i>Menu
            </button>
            <button type="button" onclick="switchTab('design')" class="tab-button px-6 py-3 rounded-xl font-bold text-sm text-zinc-500 transition whitespace-nowrap">
                <i data-lucide="palette" class="w-4 h-4 inline mr-2"></i>Visual
            </button>
            <button type="button" onclick="switchTab('content')" class="tab-button px-6 py-3 rounded-xl font-bold text-sm text-zinc-500 transition whitespace-nowrap">
                <i data-lucide="file-text" class="w-4 h-4 inline mr-2"></i>Conteúdo
            </button>
        </div>

        <!-- TAB: MENU EDITOR -->
        <div id="tab-menu" class="tab-content">
            <div class="glass-strong rounded-3xl p-10">
                
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="text-2xl font-black uppercase mb-2">Editor de Menu</h2>
                        <p class="text-zinc-500">Arraste para reordenar os itens</p>
                    </div>
                    
                    <button onclick="showAddMenuModal()" class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-3 rounded-xl font-bold hover:brightness-110 transition">
                        <i data-lucide="plus" class="w-4 h-4 inline mr-2"></i>
                        Novo Item
                    </button>
                </div>

                <!-- Lista de Menus (Sortable) -->
                <div id="menu-list" class="space-y-3">
                    <?php foreach ($menu_items as $item): ?>
                    <div class="menu-item glass rounded-xl p-5 flex items-center gap-4" data-id="<?= $item['id'] ?>">
                        <div class="cursor-move">
                            <i data-lucide="grip-vertical" class="w-5 h-5 text-zinc-600"></i>
                        </div>
                        
                        <div class="flex-1 flex items-center gap-4">
                            <?php if ($item['icon']): ?>
                            <div class="w-10 h-10 bg-purple-500/10 rounded-lg flex items-center justify-center">
                                <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="w-5 h-5 text-purple-500"></i>
                            </div>
                            <?php endif; ?>
                            
                            <div>
                                <div class="font-bold"><?= htmlspecialchars($item['label']) ?></div>
                                <div class="text-xs text-zinc-600"><?= htmlspecialchars($item['url']) ?></div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <?php if ($item['is_system']): ?>
                            <span class="px-3 py-1 bg-blue-500/10 text-blue-500 text-xs font-bold uppercase rounded-lg">
                                Sistema
                            </span>
                            <?php endif; ?>
                            
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" 
                                       <?= $item['is_enabled'] ? 'checked' : '' ?>
                                       onchange="toggleMenuItem(<?= $item['id'] ?>)"
                                       class="sr-only peer">
                                <div class="w-11 h-6 bg-zinc-700 peer-focus:ring-2 peer-focus:ring-purple-500 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                            </label>
                            
                            <?php if (!$item['is_system']): ?>
                            <button onclick="deleteMenuItem(<?= $item['id'] ?>)" class="p-2 hover:bg-red-500/10 rounded-lg transition text-red-500">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- TAB: DESIGN (código existente resumido) -->
        <div id="tab-design" class="tab-content hidden">
            <form method="POST">
                <input type="hidden" name="action" value="save_design">
                
                <div class="glass-strong rounded-3xl p-10 mb-6">
                    <h3 class="text-xl font-black uppercase mb-6">Paleta de Cores</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="glass p-6 rounded-2xl">
                            <label class="text-xs font-bold text-zinc-400 mb-3 block">Cor Primária</label>
                            <input type="color" name="primary_color" value="<?= $c['primary_color'] ?>" class="w-full h-16 rounded-xl cursor-pointer">
                        </div>
                        
                        <div class="glass p-6 rounded-2xl">
                            <label class="text-xs font-bold text-zinc-400 mb-3 block">Cor Secundária</label>
                            <input type="color" name="secondary_color" value="<?= $c['secondary_color'] ?>" class="w-full h-16 rounded-xl cursor-pointer">
                        </div>
                        
                        <div class="glass p-6 rounded-2xl">
                            <label class="text-xs font-bold text-zinc-400 mb-3 block">Cor de Destaque</label>
                            <input type="color" name="accent_color" value="<?= $c['accent_color'] ?>" class="w-full h-16 rounded-xl cursor-pointer">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="bg-gradient-to-r from-purple-600 to-pink-600 px-8 py-4 rounded-2xl font-black uppercase text-sm tracking-widest transition shadow-lg">
                    Salvar Customizações
                </button>
            </form>
        </div>

        <!-- TAB: CONTENT (código existente resumido) -->
        <div id="tab-content" class="tab-content hidden">
            <form method="POST">
                <input type="hidden" name="action" value="save_design">
                
                <div class="glass-strong rounded-3xl p-10 mb-6">
                    <h3 class="text-xl font-black uppercase mb-6">Textos da Loja</h3>
                    
                    <div class="space-y-6">
                        <div>
                            <label class="text-xs font-bold text-zinc-400 mb-3 block">Título da Loja</label>
                            <input type="text" name="store_title" value="<?= htmlspecialchars($c['store_title']) ?>"
                                   class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-500 transition">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="bg-gradient-to-r from-purple-600 to-pink-600 px-8 py-4 rounded-2xl font-black uppercase text-sm tracking-widest transition shadow-lg">
                    Salvar Customizações
                </button>
            </form>
        </div>

    </main>

    <!-- Modal: Adicionar Item de Menu -->
    <div id="addMenuModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="glass-strong max-w-lg w-full p-8 rounded-3xl">
            <h3 class="text-2xl font-black uppercase mb-6">Novo Item de Menu</h3>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_menu_item">
                
                <div class="space-y-5 mb-6">
                    <div>
                        <label class="text-xs font-bold text-zinc-400 mb-2 block">Label *</label>
                        <input type="text" name="label" required
                               placeholder="Ex: Discord"
                               class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-500 transition">
                    </div>
                    
                    <div>
                        <label class="text-xs font-bold text-zinc-400 mb-2 block">URL *</label>
                        <input type="text" name="url" required
                               placeholder="Ex: https://discord.gg/seu-servidor ou página.php"
                               class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-500 transition">
                    </div>
                    
                    <div>
                        <label class="text-xs font-bold text-zinc-400 mb-2 block">Ícone (Lucide)</label>
                        <select name="icon" class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-500 transition">
                            <option value="">Sem ícone</option>
                            <?php foreach ($lucide_icons as $icon): ?>
                            <option value="<?= $icon ?>"><?= ucfirst(str_replace('-', ' ', $icon)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <button type="button" onclick="closeAddMenuModal()" class="flex-1 bg-zinc-900 hover:bg-zinc-800 text-white py-3 rounded-xl font-bold transition">
                        Cancelar
                    </button>
                    <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-xl font-bold transition">
                        Adicionar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        // Tab switching
        function switchTab(name) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.add('hidden'));
            document.querySelectorAll('.tab-button').forEach(b => {
                b.classList.remove('active');
                b.classList.add('text-zinc-500');
            });
            
            document.getElementById('tab-' + name).classList.remove('hidden');
            event.target.closest('.tab-button').classList.add('active');
            event.target.closest('.tab-button').classList.remove('text-zinc-500');
        }
        
        // Sortable menu
        const menuList = document.getElementById('menu-list');
        if (menuList) {
            const sortable = new Sortable(menuList, {
                animation: 150,
                handle: '.cursor-move',
                ghostClass: 'sortable-ghost',
                onEnd: function() {
                    const order = Array.from(menuList.children).map((item, index) => 
                        parseInt(item.dataset.id)
                    );
                    
                    fetch('', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=update_menu_order&order=' + encodeURIComponent(JSON.stringify(order))
                    });
                }
            });
        }
        
        // Toggle menu item
        function toggleMenuItem(id) {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=toggle_menu_item&id=' + id
            });
        }
        
        // Delete menu item
        function deleteMenuItem(id) {
            if (confirm('Deseja remover este item do menu?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_menu_item">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Modal functions
        function showAddMenuModal() {
            document.getElementById('addMenuModal').classList.remove('hidden');
            lucide.createIcons();
        }
        
        function closeAddMenuModal() {
            document.getElementById('addMenuModal').classList.add('hidden');
        }
        
        document.getElementById('addMenuModal')?.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeAddMenuModal();
        });
    </script>
</body>
</html>