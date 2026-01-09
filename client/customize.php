<?php
/**
 * ============================================
 * SPLITSTORE - CUSTOMIZAÇÃO AVANÇADA V4.0
 * ============================================
 * + Todos os Temas Mantidos
 * + Menu Drag & Drop
 * + Editor de Wiki/Regras
 * + Cores Vermelho/Branco
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
// SALVAR DESIGN
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_design') {
    
    $data = [
        'template' => $_POST['template'] ?? 'neon_gaming',
        'primary_color' => $_POST['primary_color'] ?? '#dc2626',
        'secondary_color' => $_POST['secondary_color'] ?? '#0f172a',
        'accent_color' => $_POST['accent_color'] ?? '#f87171',
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
}

// ========================================
// SALVAR MENU
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_menu') {
    
    try {
        $pdo->beginTransaction();
        
        // Deletar itens antigos
        $pdo->prepare("DELETE FROM store_menu WHERE store_id = ?")->execute([$store_id]);
        
        // Inserir novos itens
        $items = $_POST['menu_items'] ?? [];
        
        foreach ($items as $index => $item) {
            if (empty($item['label']) || empty($item['url'])) continue;
            
            $stmt = $pdo->prepare("
                INSERT INTO store_menu 
                (store_id, label, url, icon, order_position, is_enabled, is_external)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $store_id,
                trim($item['label']),
                trim($item['url']),
                trim($item['icon'] ?? ''),
                $index,
                isset($item['enabled']) ? 1 : 0,
                isset($item['external']) ? 1 : 0
            ]);
        }
        
        $pdo->commit();
        $message = "✓ Menu atualizado com sucesso!";
        $messageType = "success";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Erro ao salvar menu: " . $e->getMessage();
        $messageType = "error";
    }
}

// ========================================
// SALVAR PÁGINA (WIKI/REGRAS)
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_page') {
    
    $slug = $_POST['slug'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    
    if (empty($slug) || empty($title)) {
        $message = "Preencha título e conteúdo.";
        $messageType = "error";
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM store_pages WHERE store_id = ? AND slug = ?");
            $check->execute([$store_id, $slug]);
            
            if ($check->fetch()) {
                $stmt = $pdo->prepare("
                    UPDATE store_pages 
                    SET title = ?, content = ?, is_published = ?
                    WHERE store_id = ? AND slug = ?
                ");
                $stmt->execute([$title, $content, $is_published, $store_id, $slug]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO store_pages (store_id, slug, title, content, is_published)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$store_id, $slug, $title, $content, $is_published]);
            }
            
            $message = "✓ Página salva com sucesso!";
            $messageType = "success";
            
        } catch (PDOException $e) {
            $message = "Erro ao salvar: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Buscar dados atuais
$stmt = $pdo->prepare("SELECT * FROM store_customization WHERE store_id = ?");
$stmt->execute([$store_id]);
$custom = $stmt->fetch(PDO::FETCH_ASSOC);

$defaults = [
    'template' => 'neon_gaming',
    'primary_color' => '#dc2626',
    'secondary_color' => '#0f172a',
    'accent_color' => '#f87171',
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

// Buscar menu items
$stmt = $pdo->prepare("SELECT * FROM store_menu WHERE store_id = ? ORDER BY order_position ASC");
$stmt->execute([$store_id]);
$menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar páginas
$pages = [];
foreach (['wiki', 'regras'] as $slug) {
    $stmt = $pdo->prepare("SELECT * FROM store_pages WHERE store_id = ? AND slug = ?");
    $stmt->execute([$store_id, $slug]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    $pages[$slug] = $page ?: [
        'slug' => $slug,
        'title' => ucfirst($slug),
        'content' => '',
        'is_published' => 1
    ];
}

// ===== TEMPLATES COMPLETOS =====
$templates = [
    // ===== TEMAS MINECRAFT =====
    'nether_realm' => [
        'name' => '🔥 Nether Realm',
        'description' => 'Tema inspirado no Nether - Lava, fogo e terror ardente',
        'preview_bg' => 'linear-gradient(135deg, #dc2626 0%, #7f1d1d 100%)',
        'colors' => ['primary' => '#dc2626', 'accent' => '#f97316'],
        'features' => ['Efeitos de lava', 'Animações de fogo', 'Brilho vulcânico'],
        'category' => 'minecraft',
        'preview_icon' => '🌋'
    ],
    'ender_kingdom' => [
        'name' => '👁️ Ender Kingdom',
        'description' => 'Tema do End - Mistério, teletransporte e olhos de ender',
        'preview_bg' => 'linear-gradient(135deg, #8b5cf6 0%, #3b0764 100%)',
        'colors' => ['primary' => '#8b5cf6', 'accent' => '#a855f7'],
        'features' => ['Efeitos de teletransporte', 'Partículas roxas', 'Mistério dimensional'],
        'category' => 'minecraft',
        'preview_icon' => '🌌'
    ],
    'emerald_valley' => [
        'name' => '💎 Emerald Valley',
        'description' => 'Tema de vilas e trading - Esmeraldas brilhantes e prosperidade',
        'preview_bg' => 'linear-gradient(135deg, #10b981 0%, #064e3b 100%)',
        'colors' => ['primary' => '#10b981', 'accent' => '#34d399'],
        'features' => ['Brilho de esmeralda', 'Animações de trading', 'Atmosfera próspera'],
        'category' => 'minecraft',
        'preview_icon' => '🏘️'
    ],
    
    // ===== TEMAS MODERNOS =====
    'neon_gaming' => [
        'name' => 'Neon Gaming',
        'description' => 'Estilo cyberpunk com neons vibrantes e animações futuristas',
        'preview_bg' => 'linear-gradient(135deg, #dc2626 0%, #991b1b 100%)',
        'colors' => ['primary' => '#dc2626', 'accent' => '#f87171'],
        'features' => ['Partículas animadas', 'Gradientes neon', 'Animações suaves'],
        'category' => 'modern'
    ],
    'dark_premium' => [
        'name' => 'Dark Premium',
        'description' => 'Design minimalista escuro com toques de elegância',
        'preview_bg' => 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)',
        'colors' => ['primary' => '#dc2626', 'accent' => '#f87171'],
        'features' => ['Glassmorphism', 'Sombras suaves', 'Transições elegantes'],
        'category' => 'modern'
    ],
    'fire_rage' => [
        'name' => 'Fire Rage',
        'description' => 'Tema agressivo com cores quentes e energia',
        'preview_bg' => 'linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%)',
        'colors' => ['primary' => '#ef4444', 'accent' => '#f97316'],
        'features' => ['Animações intensas', 'Cores vibrantes', 'Efeitos de fogo'],
        'category' => 'modern'
    ],
    'nature_green' => [
        'name' => 'Nature Green',
        'description' => 'Tema natural com tons verdes e orgânicos',
        'preview_bg' => 'linear-gradient(135deg, #059669 0%, #047857 100%)',
        'colors' => ['primary' => '#10b981', 'accent' => '#34d399'],
        'features' => ['Paleta natural', 'Animações suaves', 'Design orgânico'],
        'category' => 'modern'
    ],
    'ice_crystal' => [
        'name' => 'Ice Crystal',
        'description' => 'Tema gelado com tons de azul e branco',
        'preview_bg' => 'linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%)',
        'colors' => ['primary' => '#06b6d4', 'accent' => '#22d3ee'],
        'features' => ['Efeitos de cristal', 'Brilhos sutis', 'Tons frios'],
        'category' => 'modern'
    ],
    'royal_gold' => [
        'name' => 'Royal Gold',
        'description' => 'Luxuoso com dourado e preto elegante',
        'preview_bg' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        'colors' => ['primary' => '#f59e0b', 'accent' => '#fbbf24'],
        'features' => ['Detalhes dourados', 'Luxo premium', 'Elegância real'],
        'category' => 'modern'
    ],
    'minimal_light' => [
        'name' => 'Minimal Light',
        'description' => 'Design limpo e minimalista com fundo claro',
        'preview_bg' => 'linear-gradient(135deg, #ffffff 0%, #f3f4f6 100%)',
        'colors' => ['primary' => '#dc2626', 'accent' => '#f87171'],
        'features' => ['Minimalista', 'Fundo claro', 'Elegante'],
        'category' => 'modern'
    ],
    'cyberpunk' => [
        'name' => 'Cyberpunk 2077',
        'description' => 'Inspirado no universo cyberpunk futurístico',
        'preview_bg' => 'linear-gradient(135deg, #fcee09 0%, #00f0ff 100%)',
        'colors' => ['primary' => '#fcee09', 'accent' => '#00f0ff'],
        'features' => ['Neons amarelos', 'Efeitos ciano', 'Futurista'],
        'category' => 'modern'
    ]
];

$bgPatterns = [
    'dots' => 'Pontos',
    'grid' => 'Grade',
    'waves' => 'Ondas',
    'hexagon' => 'Hexágonos',
    'circuit' => 'Circuito',
    'none' => 'Sem padrão'
];

$cardStyles = [
    'glass' => 'Glassmorphism',
    'solid' => 'Sólido',
    'bordered' => 'Com borda',
    'gradient' => 'Gradiente',
    'neumorphic' => 'Neumórfico'
];

$buttonStyles = [
    'rounded' => 'Arredondado',
    'square' => 'Quadrado',
    'pill' => 'Pílula',
    'sharp' => 'Pontiagudo'
];

$fonts = [
    'inter' => 'Inter (Moderna)',
    'poppins' => 'Poppins (Geométrica)',
    'roboto' => 'Roboto (Limpa)',
    'montserrat' => 'Montserrat (Elegante)',
    'orbitron' => 'Orbitron (Futurista)',
    'rajdhani' => 'Rajdhani (Gaming)'
];

$available_icons = [
    'home', 'shopping-bag', 'newspaper', 'book-open', 'shield-check', 'users',
    'trophy', 'sparkles', 'zap', 'star', 'heart', 'gift', 'crown', 'sword',
    'shield', 'map', 'compass', 'flag', 'diamond', 'gem'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Customização Avançada | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&family=Poppins:wght@400;600;700;900&family=Orbitron:wght@400;700;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #000; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-strong { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(40px); border: 1px solid rgba(255, 255, 255, 0.1); }
        
        input[type="color"] {
            width: 70px;
            height: 70px;
            border: none;
            border-radius: 16px;
            cursor: pointer;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
        }
        
        input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
        input[type="color"]::-webkit-color-swatch { border: 3px solid rgba(255, 255, 255, 0.1); border-radius: 16px; }
        
        .template-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .template-card:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 20px 60px -20px rgba(220, 38, 38, 0.5); 
        }
        
        .template-card.selected { 
            border-color: #dc2626; 
            box-shadow: 0 0 0 2px #dc2626; 
        }
        
        .tab-button {
            position: relative;
            transition: all 0.3s;
        }
        
        .tab-button.active {
            color: #dc2626;
        }
        
        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: #dc2626;
            border-radius: 999px;
        }
        
        .menu-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.3s;
            cursor: move;
        }
        
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(220, 38, 38, 0.3);
        }
        
        .sortable-ghost {
            opacity: 0.4;
            background: rgba(220, 38, 38, 0.1);
        }
        
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.02); }
        ::-webkit-scrollbar-thumb { background: rgba(220, 38, 38, 0.5); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(220, 38, 38, 0.7); }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-8 overflow-y-auto">
        
        <header class="mb-12">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-black uppercase tracking-tighter mb-2">
                        Customização <span class="text-red-600">Avançada</span>
                    </h1>
                    <p class="text-zinc-500 text-sm">
                        Transforme sua loja com temas profissionais e personalizações ilimitadas
                    </p>
                </div>
                
                <div class="flex gap-3">
                    <a href="/stores/<?= urlencode($store_name) ?>" target="_blank" 
                       class="glass px-6 py-3 rounded-2xl hover:border-red-500/30 transition flex items-center gap-2">
                        <i data-lucide="external-link" class="w-4 h-4"></i>
                        <span class="text-sm font-bold">Preview</span>
                    </a>
                    <button onclick="document.querySelector('form[name=designForm]')?.requestSubmit()" 
                            class="bg-gradient-to-r from-red-600 to-red-700 px-8 py-3 rounded-2xl font-bold hover:brightness-110 transition shadow-lg shadow-red-500/20">
                        <i data-lucide="save" class="w-4 h-4 inline mr-2"></i>
                        Salvar Tudo
                    </button>
                </div>
            </div>
        </header>

        <?php if($message): ?>
            <div class="glass-strong border-<?= $messageType == 'success' ? 'green' : 'red' ?>-500/30 text-<?= $messageType == 'success' ? 'green' : 'red' ?>-400 p-5 rounded-2xl mb-8 flex items-center gap-3 animate-in slide-in-from-top">
                <i data-lucide="<?= $messageType == 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <span class="font-bold"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- TABS NAVIGATION -->
        <div class="glass-strong rounded-2xl p-2 flex gap-2 overflow-x-auto mb-8">
            <button type="button" onclick="switchTab('templates')" class="tab-button active px-6 py-3 rounded-xl font-bold text-sm transition whitespace-nowrap">
                <i data-lucide="palette" class="w-4 h-4 inline mr-2"></i>Temas
            </button>
            <button type="button" onclick="switchTab('menu')" class="tab-button px-6 py-3 rounded-xl font-bold text-sm text-zinc-500 transition whitespace-nowrap">
                <i data-lucide="menu" class="w-4 h-4 inline mr-2"></i>Menu
            </button>
            <button type="button" onclick="switchTab('pages')" class="tab-button px-6 py-3 rounded-xl font-bold text-sm text-zinc-500 transition whitespace-nowrap">
                <i data-lucide="file-text" class="w-4 h-4 inline mr-2"></i>Páginas
            </button>
            <button type="button" onclick="switchTab('colors')" class="tab-button px-6 py-3 rounded-xl font-bold text-sm text-zinc-500 transition whitespace-nowrap">
                <i data-lucide="droplet" class="w-4 h-4 inline mr-2"></i>Cores
            </button>
            <button type="button" onclick="switchTab('design')" class="tab-button px-6 py-3 rounded-xl font-bold text-sm text-zinc-500 transition whitespace-nowrap">
                <i data-lucide="layout" class="w-4 h-4 inline mr-2"></i>Design
            </button>
            <button type="button" onclick="switchTab('content')" class="tab-button px-6 py-3 rounded-xl font-bold text-sm text-zinc-500 transition whitespace-nowrap">
                <i data-lucide="type" class="w-4 h-4 inline mr-2"></i>Conteúdo
            </button>
            <button type="button" onclick="switchTab('advanced')" class="tab-button px-6 py-3 rounded-xl font-bold text-sm text-zinc-500 transition whitespace-nowrap">
                <i data-lucide="code" class="w-4 h-4 inline mr-2"></i>Avançado
            </button>
        </div>

        <!-- TAB: TEMPLATES -->
        <div id="tab-templates" class="tab-content">
            <form method="POST" name="designForm">
                <input type="hidden" name="action" value="save_design">
                
                <div class="glass-strong rounded-3xl p-10 mb-8">
                    <div class="mb-8">
                        <h2 class="text-2xl font-black uppercase mb-2">Escolha seu Tema</h2>
                        <p class="text-zinc-500">Selecione um tema profissional como base para sua loja</p>
                    </div>

                    <!-- TEMAS MINECRAFT -->
                    <div class="mb-12">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 bg-gradient-to-br from-green-600 to-emerald-600 rounded-xl flex items-center justify-center">
                                <span class="text-xl">⛏️</span>
                            </div>
                            <div>
                                <h3 class="text-xl font-black uppercase">Temas Minecraft</h3>
                                <p class="text-xs text-zinc-600">Inspirados nas dimensões do jogo</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php 
                            $minecraftThemes = array_filter($templates, fn($t) => ($t['category'] ?? '') === 'minecraft');
                            foreach($minecraftThemes as $key => $template): 
                            ?>
                            <label class="cursor-pointer group">
                                <input type="radio" name="template" value="<?= $key ?>" 
                                       <?= $c['template'] === $key ? 'checked' : '' ?>
                                       class="hidden template-input">
                                <div class="template-card glass p-6 rounded-2xl border-2 border-white/5 hover:scale-105 transition-all duration-300 <?= $c['template'] === $key ? 'selected' : '' ?>">
                                    <div class="aspect-video rounded-xl mb-5 overflow-hidden relative" 
                                         style="background: <?= $template['preview_bg'] ?>">
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <span class="text-6xl opacity-30"><?= $template['preview_icon'] ?? '⭐' ?></span>
                                        </div>
                                        <div class="absolute top-3 right-3 bg-black/50 backdrop-blur-sm px-3 py-1 rounded-lg">
                                            <span class="text-[9px] font-black uppercase text-green-400">Minecraft</span>
                                        </div>
                                    </div>
                                    
                                    <h3 class="text-lg font-black uppercase mb-2"><?= $template['name'] ?></h3>
                                    <p class="text-xs text-zinc-500 leading-relaxed mb-4"><?= $template['description'] ?></p>
                                    
                                    <div class="space-y-2 pt-4 border-t border-white/5">
                                        <?php foreach($template['features'] as $feature): ?>
                                        <div class="flex items-center gap-2 text-[10px] text-zinc-600">
                                            <i data-lucide="zap" class="w-3 h-3 text-green-500"></i>
                                            <span><?= $feature ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="mt-4 pt-4 border-t border-white/5 opacity-0 group-has-[:checked]:opacity-100 transition-opacity">
                                        <div class="flex items-center gap-2 text-green-500 text-sm font-bold">
                                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                                            <span>Tema Ativo</span>
                                        </div>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- TEMAS MODERNOS -->
                    <div>
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 bg-gradient-to-br from-red-600 to-red-700 rounded-xl flex items-center justify-center">
                                <i data-lucide="palette" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-black uppercase">Temas Modernos</h3>
                                <p class="text-xs text-zinc-600">Designs contemporâneos e elegantes</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php 
                            $modernThemes = array_filter($templates, fn($t) => ($t['category'] ?? 'modern') === 'modern');
                            foreach($modernThemes as $key => $template): 
                            ?>
                            <label class="cursor-pointer group">
                                <input type="radio" name="template" value="<?= $key ?>" 
                                       <?= $c['template'] === $key ? 'checked' : '' ?>
                                       class="hidden template-input">
                                <div class="template-card glass p-6 rounded-2xl border border-white/5 <?= $c['template'] === $key ? 'selected' : '' ?>">
                                    <div class="aspect-video rounded-xl mb-5 overflow-hidden relative" 
                                         style="background: <?= $template['preview_bg'] ?>">
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <i data-lucide="sparkles" class="w-12 h-12 text-white/30"></i>
                                        </div>
                                    </div>
                                    
                                    <h3 class="text-lg font-black uppercase mb-2"><?= $template['name'] ?></h3>
                                    <p class="text-xs text-zinc-500 leading-relaxed mb-4"><?= $template['description'] ?></p>
                                    
                                    <div class="space-y-2 pt-4 border-t border-white/5">
                                        <?php foreach($template['features'] as $feature): ?>
                                        <div class="flex items-center gap-2 text-[10px] text-zinc-600">
                                            <i data-lucide="check" class="w-3 h-3 text-red-500"></i>
                                            <span><?= $feature ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="mt-4 pt-4 border-t border-white/5 opacity-0 group-has-[:checked]:opacity-100 transition-opacity">
                                        <div class="flex items-center gap-2 text-red-500 text-sm font-bold">
                                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                                            <span>Tema Ativo</span>
                                        </div>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="bg-gradient-to-r from-red-600 to-red-700 px-8 py-3 rounded-2xl font-bold hover:brightness-110 transition shadow-lg">
                    <i data-lucide="save" class="w-4 h-4 inline mr-2"></i>
                    Salvar Tema
                </button>
            </form>
        </div>

        <!-- TAB: MENU -->
        <div id="tab-menu" class="tab-content hidden">
            <div class="glass-strong rounded-3xl p-10 mb-8">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h2 class="text-2xl font-black uppercase mb-2">Gerenciar Menu</h2>
                        <p class="text-zinc-500">Arraste e solte para reordenar os itens do menu</p>
                    </div>
                    <button type="button" onclick="addMenuItem()" class="bg-red-600 hover:bg-red-700 px-6 py-3 rounded-xl font-bold text-sm transition">
                        <i data-lucide="plus" class="w-4 h-4 inline mr-2"></i>
                        Adicionar Item
                    </button>
                </div>

                <form method="POST" id="menuForm">
                    <input type="hidden" name="action" value="save_menu">
                    
                    <div id="menuItems" class="space-y-3 mb-6">
                        <?php foreach ($menu_items as $index => $item): ?>
                        <div class="menu-item" data-id="<?= $index ?>">
                            <div class="grid grid-cols-12 gap-4 items-center">
                                <div class="col-span-1 flex items-center justify-center text-zinc-600 cursor-move drag-handle">
                                    <i data-lucide="grip-vertical" class="w-5 h-5"></i>
                                </div>
                                
                                <div class="col-span-3">
                                    <input type="text" name="menu_items[<?= $index ?>][label]" 
                                           value="<?= htmlspecialchars($item['label']) ?>" required
                                           placeholder="Nome" 
                                           class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-500 transition">
                                </div>
                                
                                <div class="col-span-4">
                                    <input type="text" name="menu_items[<?= $index ?>][url]" 
                                           value="<?= htmlspecialchars($item['url']) ?>" required
                                           placeholder="URL (ex: loja.php)" 
                                           class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-500 transition">
                                </div>
                                
                                <div class="col-span-2">
                                    <select name="menu_items[<?= $index ?>][icon]" 
                                            class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-500 transition">
                                        <option value="">Sem ícone</option>
                                        <?php foreach ($available_icons as $icon): ?>
                                        <option value="<?= $icon ?>" <?= $item['icon'] === $icon ? 'selected' : '' ?>>
                                            <?= ucfirst($icon) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-span-1 flex items-center justify-center">
                                    <label class="flex items-center cursor-pointer" title="Ativo">
                                        <input type="checkbox" name="menu_items[<?= $index ?>][enabled]" 
                                               <?= $item['is_enabled'] ? 'checked' : '' ?>
                                               class="w-5 h-5 rounded accent-red-600">
                                    </label>
                                </div>
                                
                                <div class="col-span-1 flex items-center justify-center">
                                    <button type="button" onclick="removeMenuItem(this)" 
                                            class="text-red-500 hover:text-red-400 transition">
                                        <i data-lucide="trash-2" class="w-5 h-5"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="bg-red-600 hover:bg-red-700 px-8 py-3 rounded-xl font-black uppercase transition">
                            <i data-lucide="save" class="w-4 h-4 inline mr-2"></i>
                            Salvar Menu
                        </button>
                        <button type="button" onclick="resetMenu()" class="glass px-6 py-3 rounded-xl hover:bg-white/5 transition">
                            <i data-lucide="rotate-ccw" class="w-4 h-4 inline mr-2"></i>
                            Resetar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- TAB: PÁGINAS -->
        <div id="tab-pages" class="tab-content hidden">
            <div class="grid md:grid-cols-2 gap-6">
                
                <!-- WIKI -->
                <div class="glass-strong rounded-3xl p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                            <i data-lucide="book-open" class="w-6 h-6 text-blue-500"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-black uppercase">Wiki</h3>
                            <p class="text-xs text-zinc-600">Guia de como jogar</p>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="save_page">
                        <input type="hidden" name="slug" value="wiki">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-bold text-zinc-500 mb-2 block">Título</label>
                                <input type="text" name="title" value="<?= htmlspecialchars($pages['wiki']['title']) ?>" required
                                       class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-blue-500 transition">
                            </div>

                            <div>
                                <label class="text-xs font-bold text-zinc-500 mb-2 block">Conteúdo (Markdown)</label>
                                <textarea name="content" rows="12" required
                                          class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-blue-500 transition resize-none font-mono"><?= htmlspecialchars($pages['wiki']['content']) ?></textarea>
                            </div>

                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_published" <?= $pages['wiki']['is_published'] ? 'checked' : '' ?>
                                       class="w-5 h-5 rounded accent-blue-600">
                                <span class="text-sm font-bold">Página publicada</span>
                            </label>

                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 py-3 rounded-xl font-black uppercase text-sm transition">
                                <i data-lucide="save" class="w-4 h-4 inline mr-2"></i>
                                Salvar Wiki
                            </button>
                        </div>
                    </form>
                </div>

                <!-- REGRAS -->
                <div class="glass-strong rounded-3xl p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-12 h-12 bg-red-500/10 rounded-xl flex items-center justify-center">
                            <i data-lucide="shield-check" class="w-6 h-6 text-red-500"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-black uppercase">Regras</h3>
                            <p class="text-xs text-zinc-600">Regras do servidor</p>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="save_page">
                        <input type="hidden" name="slug" value="regras">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-bold text-zinc-500 mb-2 block">Título</label>
                                <input type="text" name="title" value="<?= htmlspecialchars($pages['regras']['title']) ?>" required
                                       class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-500 transition">
                            </div>

                            <div>
                                <label class="text-xs font-bold text-zinc-500 mb-2 block">Conteúdo (Markdown)</label>
                                <textarea name="content" rows="12" required
                                          class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-500 transition resize-none font-mono"><?= htmlspecialchars($pages['regras']['content']) ?></textarea>
                            </div>

                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_published" <?= $pages['regras']['is_published'] ? 'checked' : '' ?>
                                       class="w-5 h-5 rounded accent-red-600">
                                <span class="text-sm font-bold">Página publicada</span>
                            </label>

                            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 py-3 rounded-xl font-black uppercase text-sm transition">
                                <i data-lucide="save" class="w-4 h-4 inline mr-2"></i>
                                Salvar Regras
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB: CORES -->
        <div id="tab-colors" class="tab-content hidden">
            <div class="glass-strong rounded-3xl p-10">
                <div class="mb-8">
                    <h2 class="text-2xl font-black uppercase mb-2">Paleta de Cores</h2>
                    <p class="text-zinc-500">Defina as cores que representam sua marca</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="glass p-6 rounded-2xl">
                        <label class="text-[10px] font-black uppercase text-zinc-600 tracking-widest block mb-4">
                            Cor Primária
                        </label>
                        <div class="flex items-center gap-4">
                            <input type="color" name="primary_color" value="<?= $c['primary_color'] ?>" form="designForm"
                                   onchange="updatePreview('primary', this.value)">
                            <div class="flex-1">
                                <input type="text" value="<?= $c['primary_color'] ?>" 
                                       class="w-full bg-black/30 border border-white/10 p-3 rounded-xl text-sm font-mono uppercase outline-none focus:border-red-500 transition"
                                       readonly>
                                <p class="text-[9px] text-zinc-600 mt-2">Botões, links e destaques</p>
                            </div>
                        </div>
                    </div>

                    <div class="glass p-6 rounded-2xl">
                        <label class="text-[10px] font-black uppercase text-zinc-600 tracking-widest block mb-4">
                            Cor Secundária
                        </label>
                        <div class="flex items-center gap-4">
                            <input type="color" name="secondary_color" value="<?= $c['secondary_color'] ?>" form="designForm"
                                   onchange="updatePreview('secondary', this.value)">
                            <div class="flex-1">
                                <input type="text" value="<?= $c['secondary_color'] ?>" 
                                       class="w-full bg-black/30 border border-white/10 p-3 rounded-xl text-sm font-mono uppercase outline-none focus:border-red-500 transition"
                                       readonly>
                                <p class="text-[9px] text-zinc-600 mt-2">Fundos e backgrounds</p>
                            </div>
                        </div>
                    </div>

                    <div class="glass p-6 rounded-2xl">
                        <label class="text-[10px] font-black uppercase text-zinc-600 tracking-widest block mb-4">
                            Cor de Destaque
                        </label>
                        <div class="flex items-center gap-4">
                            <input type="color" name="accent_color" value="<?= $c['accent_color'] ?>" form="designForm"
                                   onchange="updatePreview('accent', this.value)">
                            <div class="flex-1">
                                <input type="text" value="<?= $c['accent_color'] ?>" 
                                       class="w-full bg-black/30 border border-white/10 p-3 rounded-xl text-sm font-mono uppercase outline-none focus:border-red-500 transition"
                                       readonly>
                                <p class="text-[9px] text-zinc-600 mt-2">Promoções e badges</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="glass p-8 rounded-2xl">
                    <p class="text-[10px] font-black uppercase text-zinc-600 mb-4 tracking-widest">Pré-visualização</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <button type="button" id="preview-primary" 
                                style="background: <?= $c['primary_color'] ?>;"
                                class="py-5 rounded-xl font-black uppercase text-sm text-white shadow-lg">
                            Botão Primário
                        </button>
                        <div id="preview-secondary" 
                             style="background: <?= $c['secondary_color'] ?>;"
                             class="p-5 rounded-xl flex items-center justify-center border border-white/5">
                            <span class="text-sm font-bold">Fundo Card</span>
                        </div>
                        <div id="preview-accent" 
                             style="background: <?= $c['accent_color'] ?>;"
                             class="p-5 rounded-xl flex items-center justify-center">
                            <span class="text-sm font-black uppercase">50% OFF</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: DESIGN -->
        <div id="tab-design" class="tab-content hidden">
            <div class="space-y-6">
                
                <div class="glass-strong rounded-3xl p-10">
                    <h3 class="text-xl font-black uppercase mb-6">Estilo Visual</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="text-xs font-bold text-zinc-400 mb-3 block">Padrão de Fundo</label>
                            <select name="background_pattern" form="designForm" class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-500 transition">
                                <?php foreach($bgPatterns as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $c['background_pattern'] === $key ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-zinc-400 mb-3 block">Estilo de Card</label>
                            <select name="card_style" form="designForm" class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-500 transition">
                                <?php foreach($cardStyles as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $c['card_style'] === $key ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-zinc-400 mb-3 block">Estilo de Botão</label>
                            <select name="button_style" form="designForm" class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-500 transition">
                                <?php foreach($buttonStyles as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $c['button_style'] === $key ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-zinc-400 mb-3 block">Fonte Principal</label>
                            <select name="font_family" form="designForm" class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-500 transition">
                                <?php foreach($fonts as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $c['font_family'] === $key ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="glass-strong rounded-3xl p-10">
                    <h3 class="text-xl font-black uppercase mb-6">Efeitos Especiais</h3>
                    
                    <div class="space-y-4">
                        <label class="glass p-6 rounded-2xl flex items-center justify-between cursor-pointer hover:border-red-500/30 transition">
                            <div>
                                <div class="font-bold mb-1">Partículas Animadas</div>
                                <div class="text-xs text-zinc-500">Efeito de partículas flutuantes no fundo</div>
                            </div>
                            <input type="checkbox" name="show_particles" form="designForm" <?= $c['show_particles'] ? 'checked' : '' ?>
                                   class="w-5 h-5 rounded accent-red-600 cursor-pointer">
                        </label>

                        <label class="glass p-6 rounded-2xl flex items-center justify-between cursor-pointer hover:border-red-500/30 transition">
                            <div>
                                <div class="font-bold mb-1">Efeitos de Blur</div>
                                <div class="text-xs text-zinc-500">Glassmorphism e backdrop blur</div>
                            </div>
                            <input type="checkbox" name="show_blur_effects" form="designForm" <?= $c['show_blur_effects'] ? 'checked' : '' ?>
                                   class="w-5 h-5 rounded accent-red-600 cursor-pointer">
                        </label>

                        <label class="glass p-6 rounded-2xl flex items-center justify-between cursor-pointer hover:border-red-500/30 transition">
                            <div>
                                <div class="font-bold mb-1">Modo Escuro</div>
                                <div class="text-xs text-zinc-500">Ativar tema dark por padrão</div>
                            </div>
                            <input type="checkbox" name="dark_mode" form="designForm" <?= $c['dark_mode'] ? 'checked' : '' ?>
                                   class="w-5 h-5 rounded accent-red-600 cursor-pointer">
                        </label>

                        <label class="glass p-6 rounded-2xl flex items-center justify-between cursor-pointer hover:border-red-500/30 transition">
                            <div>
                                <div class="font-bold mb-1">Gradiente de Fundo</div>
                                <div class="text-xs text-zinc-500">Background com gradiente animado</div>
                            </div>
                            <input type="checkbox" name="show_gradient_bg" form="designForm" <?= $c['show_gradient_bg'] ? 'checked' : '' ?>
                                   class="w-5 h-5 rounded accent-red-600 cursor-pointer">
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: CONTEÚDO -->
        <div id="tab-content" class="tab-content hidden">
            <div class="space-y-6">
                
                <div class="glass-strong rounded-3xl p-10">
                    <h3 class="text-xl font-black uppercase mb-6">Textos da Loja</h3>
                    
                    <div class="space-y-6">
                        <div>
                            <label class="text-xs font-bold text-zinc-400 mb-3 block">Título da Loja</label>
                            <input type="text" name="store_title" form="designForm" value="<?= htmlspecialchars($c['store_title']) ?>"
                                   placeholder="<?= htmlspecialchars($store_name) ?> - Loja Premium"
                                   class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-500 transition">
                            <p class="text-[9px] text-zinc-600 mt-2">Aparece no topo da página e no SEO</p>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-zinc-400 mb-3 block">Descrição</label>
                            <textarea name="store_description" form="designForm" rows="3"
                                      placeholder="A melhor loja de itens para o seu gameplay..."
                                      class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-500 transition resize-none"><?= htmlspecialchars($c['store_description']) ?></textarea>
                            <p class="text-[9px] text-zinc-600 mt-2">Descrição que aparece no header da loja</p>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-zinc-400 mb-3 block">Slogan/Tagline</label>
                            <input type="text" name="store_tagline" form="designForm" value="<?= htmlspecialchars($c['store_tagline']) ?>"
                                   placeholder="Os melhores items do servidor"
                                   class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-500 transition">
                            <p class="text-[9px] text-zinc-600 mt-2">Frase de efeito curta</p>
                        </div>
                    </div>
                </div>

                <div class="glass-strong rounded-3xl p-10">
                    <h3 class="text-xl font-black uppercase mb-6">Imagens e Mídia</h3>
                    
                    <div class="space-y-6">
                        <div>
                            <label class="text-xs font-bold text-zinc-400 mb-3 block">Logo URL</label>
                            <div class="flex gap-3">
                                <input type="url" name="logo_url" form="designForm" value="<?= htmlspecialchars($c['logo_url']) ?>"
                                       placeholder="https://i.imgur.com/logo.png"
                                       class="flex-1 bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-500 transition">
                                <?php if($c['logo_url']): ?>
                                <div class="w-16 h-16 bg-black/30 rounded-xl flex items-center justify-center overflow-hidden">
                                    <img src="<?= htmlspecialchars($c['logo_url']) ?>" class="max-w-full max-h-full object-contain">
                                </div>
                                <?php endif; ?>
                            </div>
                            <p class="text-[9px] text-zinc-600 mt-2">Recomendado: PNG transparente, 200x200px</p>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-zinc-400 mb-3 block">Favicon URL</label>
                            <input type="url" name="favicon_url" form="designForm" value="<?= htmlspecialchars($c['favicon_url']) ?>"
                                   placeholder="https://i.imgur.com/favicon.png"
                                   class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-500 transition">
                            <p class="text-[9px] text-zinc-600 mt-2">Ícone da aba do navegador, 32x32px</p>
                        </div>

                        <div>
                            <label class="text-xs font-bold text-zinc-400 mb-3 block">Banner Principal URL</label>
                            <input type="url" name="banner_url" form="designForm" value="<?= htmlspecialchars($c['banner_url']) ?>"
                                   placeholder="https://i.imgur.com/banner.jpg"
                                   class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-500 transition">
                            <p class="text-[9px] text-zinc-600 mt-2">Banner do topo, recomendado 1920x400px</p>
                            
                            <?php if($c['banner_url']): ?>
                            <div class="mt-4 aspect-[16/3] bg-black/30 rounded-2xl overflow-hidden">
                                <img src="<?= htmlspecialchars($c['banner_url']) ?>" class="w-full h-full object-cover">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: AVANÇADO -->
        <div id="tab-advanced" class="tab-content hidden">
            <div class="space-y-6">
                
                <div class="glass-strong rounded-3xl p-10">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-xl font-black uppercase">CSS Personalizado</h3>
                            <p class="text-zinc-500 text-sm mt-1">Adicione seus próprios estilos CSS</p>
                        </div>
                        <span class="px-3 py-1 bg-yellow-500/10 text-yellow-500 text-[10px] font-black uppercase rounded-lg border border-yellow-500/20">
                            Avançado
                        </span>
                    </div>
                    
                    <textarea name="custom_css" form="designForm" rows="12"
                              placeholder="/* Seu CSS aqui */&#10;.minha-classe {&#10;    color: #dc2626;&#10;    font-weight: bold;&#10;}"
                              class="w-full bg-black/40 border border-white/10 p-4 rounded-xl text-xs font-mono outline-none focus:border-red-500 transition resize-none"><?= htmlspecialchars($c['custom_css']) ?></textarea>
                    
                    <div class="mt-4 flex items-start gap-3 p-4 bg-blue-500/5 border border-blue-500/20 rounded-xl">
                        <i data-lucide="info" class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5"></i>
                        <div class="text-xs text-blue-400">
                            <p class="font-bold mb-1">Dicas:</p>
                            <ul class="list-disc list-inside space-y-1 text-blue-400/80">
                                <li>Use seletores específicos para evitar conflitos</li>
                                <li>Teste sempre antes de publicar</li>
                                <li>Variáveis CSS disponíveis: --primary, --secondary, --accent</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="glass-strong rounded-3xl p-10">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-xl font-black uppercase">JavaScript Personalizado</h3>
                            <p class="text-zinc-500 text-sm mt-1">Adicione interações customizadas</p>
                        </div>
                        <span class="px-3 py-1 bg-red-500/10 text-red-500 text-[10px] font-black uppercase rounded-lg border border-red-500/20">
                            Perigo
                        </span>
                    </div>
                    
                    <textarea name="custom_js" form="designForm" rows="12"
                              placeholder="// Seu JavaScript aqui&#10;console.log('Loja customizada!');"
                              class="w-full bg-black/40 border border-white/10 p-4 rounded-xl text-xs font-mono outline-none focus:border-red-500 transition resize-none"><?= htmlspecialchars($c['custom_js']) ?></textarea>
                    
                    <div class="mt-4 flex items-start gap-3 p-4 bg-red-500/5 border border-red-500/20 rounded-xl">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5"></i>
                        <div class="text-xs text-red-400">
                            <p class="font-bold mb-1">Atenção:</p>
                            <ul class="list-disc list-inside space-y-1 text-red-400/80">
                                <li>Código JavaScript pode quebrar a loja se mal escrito</li>
                                <li>Não adicione scripts maliciosos ou invasivos</li>
                                <li>Evite conflitos com as funções nativas da loja</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();
        
        // ===== TAB SWITCHING =====
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
                btn.classList.add('text-zinc-500');
            });
            
            document.getElementById('tab-' + tabName).classList.remove('hidden');
            
            event.target.closest('.tab-button').classList.add('active');
            event.target.closest('.tab-button').classList.remove('text-zinc-500');
            
            lucide.createIcons();
        }
        
        // ===== COLOR PREVIEW =====
        function updatePreview(type, value) {
            const preview = document.getElementById('preview-' + type);
            if (preview) {
                preview.style.background = value;
            }
            
            const input = document.querySelector(`input[name="${type}_color"]`);
            if (input && input.nextElementSibling) {
                const textInput = input.nextElementSibling.querySelector('input[type="text"]');
                if (textInput) {
                    textInput.value = value;
                }
            }
        }
        
        // ===== MENU DRAG & DROP =====
        let sortable = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            const menuContainer = document.getElementById('menuItems');
            
            if (menuContainer) {
                sortable = Sortable.create(menuContainer, {
                    handle: '.drag-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    onEnd: function() {
                        updateMenuIndexes();
                    }
                });
            }
        });
        
        function updateMenuIndexes() {
            const items = document.querySelectorAll('#menuItems .menu-item');
            items.forEach((item, index) => {
                const inputs = item.querySelectorAll('input, select');
                inputs.forEach(input => {
                    const name = input.getAttribute('name');
                    if (name) {
                        const newName = name.replace(/\[\d+\]/, `[${index}]`);
                        input.setAttribute('name', newName);
                    }
                });
            });
        }
        
        let menuItemCount = <?= count($menu_items) ?>;
        
        function addMenuItem() {
            const container = document.getElementById('menuItems');
            const index = menuItemCount++;
            
            const html = `
                <div class="menu-item" data-id="${index}">
                    <div class="grid grid-cols-12 gap-4 items-center">
                        <div class="col-span-1 flex items-center justify-center text-zinc-600 cursor-move drag-handle">
                            <i data-lucide="grip-vertical" class="w-5 h-5"></i>
                        </div>
                        
                        <div class="col-span-3">
                            <input type="text" name="menu_items[${index}][label]" required
                                   placeholder="Nome" 
                                   class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-500 transition">
                        </div>
                        
                        <div class="col-span-4">
                            <input type="text" name="menu_items[${index}][url]" required
                                   placeholder="URL (ex: loja.php)" 
                                   class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-500 transition">
                        </div>
                        
                        <div class="col-span-2">
                            <select name="menu_items[${index}][icon]" 
                                    class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-500 transition">
                                <option value="">Sem ícone</option>
                                <?php foreach ($available_icons as $icon): ?>
                                <option value="<?= $icon ?>"><?= ucfirst($icon) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-span-1 flex items-center justify-center">
                            <label class="flex items-center cursor-pointer" title="Ativo">
                                <input type="checkbox" name="menu_items[${index}][enabled]" checked
                                       class="w-5 h-5 rounded accent-red-600">
                            </label>
                        </div>
                        
                        <div class="col-span-1 flex items-center justify-center">
                            <button type="button" onclick="removeMenuItem(this)" 
                                    class="text-red-500 hover:text-red-400 transition">
                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', html);
            lucide.createIcons();
        }
        
        function removeMenuItem(btn) {
            if (confirm('Remover este item do menu?')) {
                btn.closest('.menu-item').remove();
                updateMenuIndexes();
            }
        }
        
        function resetMenu() {
            if (confirm('Resetar o menu para o padrão?')) {
                location.reload();
            }
        }
        
        // ===== FORM CHANGE TRACKING =====
        let formChanged = false;
        
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('input', () => {
                formChanged = true;
            });
        });
        
        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'Você tem alterações não salvas. Deseja realmente sair?';
            }
        });
        
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', () => {
                formChanged = false;
            });
        });
        
        // ===== TEMPLATE SELECTION =====
        document.querySelectorAll('.template-input').forEach(input => {
            input.addEventListener('change', function() {
                document.querySelectorAll('.template-card').forEach(card => {
                    card.classList.remove('selected');
                });
                
                if (this.checked) {
                    this.closest('.template-card').classList.add('selected');
                }
            });
        });
    </script>
</body>
</html>