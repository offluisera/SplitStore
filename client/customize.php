<?php
/**
 * ============================================
 * SPLITSTORE - CUSTOMIZAÇÃO AVANÇADA V2.0
 * ============================================
 * Sistema completo de temas e personalização
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

// SALVAR CUSTOMIZAÇÕES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_design') {
    
    $data = [
        'template' => $_POST['template'] ?? 'neon',
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
            // UPDATE
            $fields = array_keys($data);
            $sql = "UPDATE store_customization SET " . 
                   implode(', ', array_map(fn($f) => "$f = ?", $fields)) . 
                   " WHERE store_id = ?";
            $values = array_merge(array_values($data), [$store_id]);
        } else {
            // INSERT
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

// BUSCAR CUSTOMIZAÇÕES ATUAIS
$stmt = $pdo->prepare("SELECT * FROM store_customization WHERE store_id = ?");
$stmt->execute([$store_id]);
$custom = $stmt->fetch(PDO::FETCH_ASSOC);

// Valores padrão
$defaults = [
    'template' => 'neon',
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

// TEMPLATES DISPONÍVEIS
$templates = [
    'neon' => [
        'name' => 'Neon Gaming',
        'description' => 'Estilo cyberpunk com neons vibrantes e animações futuristas',
        'preview_bg' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'colors' => ['primary' => '#8b5cf6', 'accent' => '#ec4899'],
        'features' => ['Partículas animadas', 'Gradientes neon', 'Animações suaves']
    ],
    'dark_premium' => [
        'name' => 'Dark Premium',
        'description' => 'Design minimalista escuro com toques de elegância',
        'preview_bg' => 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)',
        'colors' => ['primary' => '#3b82f6', 'accent' => '#06b6d4'],
        'features' => ['Glassmorphism', 'Sombras suaves', 'Transições elegantes']
    ],
    'fire_rage' => [
        'name' => 'Fire Rage',
        'description' => 'Tema agressivo com cores quentes e energia',
        'preview_bg' => 'linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%)',
        'colors' => ['primary' => '#ef4444', 'accent' => '#f97316'],
        'features' => ['Animações intensas', 'Cores vibrantes', 'Efeitos de fogo']
    ],
    'nature_green' => [
        'name' => 'Nature Green',
        'description' => 'Tema natural com tons verdes e orgânicos',
        'preview_bg' => 'linear-gradient(135deg, #059669 0%, #047857 100%)',
        'colors' => ['primary' => '#10b981', 'accent' => '#34d399'],
        'features' => ['Paleta natural', 'Animações suaves', 'Design orgânico']
    ],
    'ice_crystal' => [
        'name' => 'Ice Crystal',
        'description' => 'Tema gelado com tons de azul e branco',
        'preview_bg' => 'linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%)',
        'colors' => ['primary' => '#06b6d4', 'accent' => '#22d3ee'],
        'features' => ['Efeitos de cristal', 'Brilhos sutis', 'Tons frios']
    ],
    'royal_gold' => [
        'name' => 'Royal Gold',
        'description' => 'Luxuoso com dourado e preto elegante',
        'preview_bg' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        'colors' => ['primary' => '#f59e0b', 'accent' => '#fbbf24'],
        'features' => ['Detalhes dourados', 'Luxo premium', 'Elegância real']
    ]
];

// PADRÕES DE FUNDO
$bgPatterns = [
    'dots' => 'Pontos',
    'grid' => 'Grade',
    'waves' => 'Ondas',
    'hexagon' => 'Hexágonos',
    'circuit' => 'Circuito',
    'none' => 'Sem padrão'
];

// ESTILOS DE CARD
$cardStyles = [
    'glass' => 'Glassmorphism',
    'solid' => 'Sólido',
    'bordered' => 'Com borda',
    'gradient' => 'Gradiente',
    'neumorphic' => 'Neumórfico'
];

// ESTILOS DE BOTÃO
$buttonStyles = [
    'rounded' => 'Arredondado',
    'square' => 'Quadrado',
    'pill' => 'Pílula',
    'sharp' => 'Pontiagudo'
];

// FONTES
$fonts = [
    'inter' => 'Inter (Moderna)',
    'poppins' => 'Poppins (Geométrica)',
    'roboto' => 'Roboto (Limpa)',
    'montserrat' => 'Montserrat (Elegante)',
    'orbitron' => 'Orbitron (Futurista)',
    'rajdhani' => 'Rajdhani (Gaming)'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Customização Avançada | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&family=Poppins:wght@400;600;700;900&family=Orbitron:wght@400;700;900&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #000; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-strong { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(40px); border: 1px solid rgba(255, 255, 255, 0.1); }
        
        /* Color Picker Premium */
        input[type="color"] {
            width: 70px;
            height: 70px;
            border: none;
            border-radius: 16px;
            cursor: pointer;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.5);
        }
        
        input[type="color"]::-webkit-color-swatch-wrapper { padding: 0; }
        input[type="color"]::-webkit-swatch { border: 3px solid rgba(255, 255, 255, 0.1); border-radius: 16px; }
        
        /* Template Cards */
        .template-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .template-card:hover { transform: translateY(-8px); box-shadow: 0 20px 60px -20px rgba(139, 92, 246, 0.5); }
        .template-card.selected { border-color: #8b5cf6; box-shadow: 0 0 0 2px #8b5cf6; }
        
        .template-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 0%, rgba(139, 92, 246, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .template-card:hover::before { opacity: 1; }
        
        /* Tabs */
        .tab-button {
            position: relative;
            transition: all 0.3s;
        }
        
        .tab-button.active {
            color: #8b5cf6;
        }
        
        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: #8b5cf6;
            border-radius: 999px;
        }
        
        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .float-animation { animation: float 3s ease-in-out infinite; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.02); }
        ::-webkit-scrollbar-thumb { background: rgba(139, 92, 246, 0.5); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(139, 92, 246, 0.7); }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-8 overflow-y-auto">
        
        <!-- Header -->
        <header class="mb-12">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-black uppercase tracking-tighter mb-2">
                        Customização <span class="text-purple-500">Avançada</span>
                    </h1>
                    <p class="text-zinc-500 text-sm">
                        Transforme sua loja com temas profissionais e personalizações ilimitadas
                    </p>
                </div>
                
                <div class="flex gap-3">
                    <a href="/stores/<?= urlencode($store_name) ?>" target="_blank" 
                       class="glass px-6 py-3 rounded-2xl hover:border-purple-500/30 transition flex items-center gap-2">
                        <i data-lucide="external-link" class="w-4 h-4"></i>
                        <span class="text-sm font-bold">Preview</span>
                    </a>
                    <button onclick="document.querySelector('form').requestSubmit()" 
                            class="bg-gradient-to-r from-purple-600 to-pink-600 px-8 py-3 rounded-2xl font-bold hover:brightness-110 transition shadow-lg shadow-purple-500/20">
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

        <form method="POST" class="space-y-8">
            <input type="hidden" name="action" value="save_design">
            
            <!-- TABS NAVIGATION -->
            <div class="glass-strong rounded-2xl p-2 flex gap-2 overflow-x-auto">
                <button type="button" onclick="switchTab('templates')" class="tab-button active px-6 py-3 rounded-xl font-bold text-sm transition whitespace-nowrap">
                    <i data-lucide="palette" class="w-4 h-4 inline mr-2"></i>Temas
                </button>
                <button type="button" onclick="switchTab('colors')" class="tab-button px-6 py-3 rounded-xl font-bold text-sm text-zinc-500 transition whitespace-nowrap">
                    <i data-lucide="droplet" class="w-4 h-4 inline mr-2"></i>Cores
                </button>
                <button type="button" onclick="switchTab('design')" class="tab-button px-6 py-3 rounded-xl font-bold text-sm text-zinc-500 transition whitespace-nowrap">
                    <i data-lucide="layout" class="w-4 h-4 inline mr-2"></i>Design
                </button>
                <button type="button" onclick="switchTab('content')" class="tab-button px-6 py-3 rounded-xl font-bold text-sm text-zinc-500 transition whitespace-nowrap">
                    <i data-lucide="file-text" class="w-4 h-4 inline mr-2"></i>Conteúdo
                </button>
                <button type="button" onclick="switchTab('advanced')" class="tab-button px-6 py-3 rounded-xl font-bold text-sm text-zinc-500 transition whitespace-nowrap">
                    <i data-lucide="code" class="w-4 h-4 inline mr-2"></i>Avançado
                </button>
            </div>

            <!-- TAB: TEMPLATES -->
            <div id="tab-templates" class="tab-content">
                <div class="glass-strong rounded-3xl p-10">
                    <div class="mb-8">
                        <h2 class="text-2xl font-black uppercase mb-2">Escolha seu Tema</h2>
                        <p class="text-zinc-500">Selecione um tema profissional como base para sua loja</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach($templates as $key => $template): ?>
                        <label class="cursor-pointer group">
                            <input type="radio" name="template" value="<?= $key ?>" 
                                   <?= $c['template'] === $key ? 'checked' : '' ?>
                                   class="hidden template-input">
                            <div class="template-card glass p-6 rounded-2xl border border-white/5">
                                <!-- Preview -->
                                <div class="aspect-video rounded-xl mb-5 overflow-hidden relative" 
                                     style="background: <?= $template['preview_bg'] ?>">
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <i data-lucide="sparkles" class="w-12 h-12 text-white/30"></i>
                                    </div>
                                </div>
                                
                                <!-- Info -->
                                <h3 class="text-lg font-black uppercase mb-2"><?= $template['name'] ?></h3>
                                <p class="text-xs text-zinc-500 leading-relaxed mb-4"><?= $template['description'] ?></p>
                                
                                <!-- Features -->
                                <div class="space-y-2 pt-4 border-t border-white/5">
                                    <?php foreach($template['features'] as $feature): ?>
                                    <div class="flex items-center gap-2 text-[10px] text-zinc-600">
                                        <i data-lucide="check" class="w-3 h-3 text-purple-500"></i>
                                        <span><?= $feature ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Badge Selected -->
                                <div class="mt-4 pt-4 border-t border-white/5 opacity-0 group-has-[:checked]:opacity-100 transition-opacity">
                                    <div class="flex items-center gap-2 text-purple-500 text-sm font-bold">
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

            <!-- TAB: CORES -->
            <div id="tab-colors" class="tab-content hidden">
                <div class="glass-strong rounded-3xl p-10">
                    <div class="mb-8">
                        <h2 class="text-2xl font-black uppercase mb-2">Paleta de Cores</h2>
                        <p class="text-zinc-500">Defina as cores que representam sua marca</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <!-- Cor Primária -->
                        <div class="glass p-6 rounded-2xl">
                            <label class="text-[10px] font-black uppercase text-zinc-600 tracking-widest block mb-4">
                                Cor Primária
                            </label>
                            <div class="flex items-center gap-4">
                                <input type="color" name="primary_color" value="<?= $c['primary_color'] ?>" 
                                       onchange="updatePreview('primary', this.value)">
                                <div class="flex-1">
                                    <input type="text" value="<?= $c['primary_color'] ?>" 
                                           class="w-full bg-black/30 border border-white/10 p-3 rounded-xl text-sm font-mono uppercase outline-none focus:border-purple-500 transition"
                                           readonly>
                                    <p class="text-[9px] text-zinc-600 mt-2">Botões, links e destaques</p>
                                </div>
                            </div>
                        </div>

                        <!-- Cor Secundária -->
                        <div class="glass p-6 rounded-2xl">
                            <label class="text-[10px] font-black uppercase text-zinc-600 tracking-widest block mb-4">
                                Cor Secundária
                            </label>
                            <div class="flex items-center gap-4">
                                <input type="color" name="secondary_color" value="<?= $c['secondary_color'] ?>" 
                                       onchange="updatePreview('secondary', this.value)">
                                <div class="flex-1">
                                    <input type="text" value="<?= $c['secondary_color'] ?>" 
                                           class="w-full bg-black/30 border border-white/10 p-3 rounded-xl text-sm font-mono uppercase outline-none focus:border-purple-500 transition"
                                           readonly>
                                    <p class="text-[9px] text-zinc-600 mt-2">Fundos e backgrounds</p>
                                </div>
                            </div>
                        </div>

                        <!-- Cor de Destaque -->
                        <div class="glass p-6 rounded-2xl">
                            <label class="text-[10px] font-black uppercase text-zinc-600 tracking-widest block mb-4">
                                Cor de Destaque
                            </label>
                            <div class="flex items-center gap-4">
                                <input type="color" name="accent_color" value="<?= $c['accent_color'] ?>" 
                                       onchange="updatePreview('accent', this.value)">
                                <div class="flex-1">
                                    <input type="text" value="<?= $c['accent_color'] ?>" 
                                           class="w-full bg-black/30 border border-white/10 p-3 rounded-xl text-sm font-mono uppercase outline-none focus:border-purple-500 transition"
                                           readonly>
                                    <p class="text-[9px] text-zinc-600 mt-2">Promoções e badges</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Preview ao Vivo -->
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
                    
                    <!-- Estilo Visual -->
                    <div class="glass-strong rounded-3xl p-10">
                        <h3 class="text-xl font-black uppercase mb-6">Estilo Visual</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Padrão de Fundo -->
                            <div>
                                <label class="text-xs font-bold text-zinc-400 mb-3 block">Padrão de Fundo</label>
                                <select name="background_pattern" class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-500 transition">
                                    <?php foreach($bgPatterns as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $c['background_pattern'] === $key ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Estilo de Card -->
                            <div>
                                <label class="text-xs font-bold text-zinc-400 mb-3 block">Estilo de Card</label>
                                <select name="card_style" class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-500 transition">
                                    <?php foreach($cardStyles as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $c['card_style'] === $key ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Estilo de Botão -->
                            <div>
                                <label class="text-xs font-bold text-zinc-400 mb-3 block">Estilo de Botão</label>
                                <select name="button_style" class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-500 transition">
                                    <?php foreach($buttonStyles as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $c['button_style'] === $key ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Fonte -->
                            <div>
                                <label class="text-xs font-bold text-zinc-400 mb-3 block">Fonte Principal</label>
                                <select name="font_family" class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-500 transition">
                                    <?php foreach($fonts as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $c['font_family'] === $key ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Efeitos Visuais -->
                    <div class="glass-strong rounded-3xl p-10">
                        <h3 class="text-xl font-black uppercase mb-6">Efeitos Especiais</h3>
                        
                        <label class="glass p-6 rounded-2xl flex items-center justify-between cursor-pointer hover:border-purple-500/30 transition">
                                <div>
                                    <div class="font-bold mb-1">Partículas Animadas</div>
                                    <div class="text-xs text-zinc-500">Efeito de partículas flutuantes no fundo</div>
                                </div>
                                <input type="checkbox" name="show_particles" <?= $c['show_particles'] ? 'checked' : '' ?>
                                       class="w-5 h-5 rounded accent-purple-600 cursor-pointer">
                            </label>

                            <label class="glass p-6 rounded-2xl flex items-center justify-between cursor-pointer hover:border-purple-500/30 transition">
                                <div>
                                    <div class="font-bold mb-1">Efeitos de Blur</div>
                                    <div class="text-xs text-zinc-500">Glassmorphism e backdrop blur</div>
                                </div>
                                <input type="checkbox" name="show_blur_effects" <?= $c['show_blur_effects'] ? 'checked' : '' ?>
                                       class="w-5 h-5 rounded accent-purple-600 cursor-pointer">
                            </label>

                            <label class="glass p-6 rounded-2xl flex items-center justify-between cursor-pointer hover:border-purple-500/30 transition">
                                <div>
                                    <div class="font-bold mb-1">Modo Escuro</div>
                                    <div class="text-xs text-zinc-500">Ativar tema dark por padrÃ£o</div>
                                </div>
                                <input type="checkbox" name="dark_mode" <?= $c['dark_mode'] ? 'checked' : '' ?>
                                       class="w-5 h-5 rounded accent-purple-600 cursor-pointer">
                            </label>

                            <label class="glass p-6 rounded-2xl flex items-center justify-between cursor-pointer hover:border-purple-500/30 transition">
                                <div>
                                    <div class="font-bold mb-1">Gradiente de Fundo</div>
                                    <div class="text-xs text-zinc-500">Background com gradiente animado</div>
                                </div>
                                <input type="checkbox" name="show_gradient_bg" <?= $c['show_gradient_bg'] ? 'checked' : '' ?>
                                       class="w-5 h-5 rounded accent-purple-600 cursor-pointer">
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: CONTEÚDO -->
            <div id="tab-content" class="tab-content hidden">
                <div class="space-y-6">
                    
                    <!-- Textos da Loja -->
                    <div class="glass-strong rounded-3xl p-10">
                        <h3 class="text-xl font-black uppercase mb-6">Textos da Loja</h3>
                        
                        <div class="space-y-6">
                            <div>
                                <label class="text-xs font-bold text-zinc-400 mb-3 block">Título da Loja</label>
                                <input type="text" name="store_title" value="<?= htmlspecialchars($c['store_title']) ?>"
                                       placeholder="<?= htmlspecialchars($store_name) ?> - Loja Premium"
                                       class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-500 transition">
                                <p class="text-[9px] text-zinc-600 mt-2">Aparece no topo da página e no SEO</p>
                            </div>

                            <div>
                                <label class="text-xs font-bold text-zinc-400 mb-3 block">Descrição</label>
                                <textarea name="store_description" rows="3"
                                          placeholder="A melhor loja de itens para o seu gameplay..."
                                          class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-500 transition resize-none"><?= htmlspecialchars($c['store_description']) ?></textarea>
                                <p class="text-[9px] text-zinc-600 mt-2">Descrição que aparece no header da loja</p>
                            </div>

                            <div>
                                <label class="text-xs font-bold text-zinc-400 mb-3 block">Slogan/Tagline</label>
                                <input type="text" name="store_tagline" value="<?= htmlspecialchars($c['store_tagline']) ?>"
                                       placeholder="Os melhores items do servidor"
                                       class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-500 transition">
                                <p class="text-[9px] text-zinc-600 mt-2">Frase de efeito curta</p>
                            </div>
                        </div>
                    </div>

                    <!-- Imagens -->
                    <div class="glass-strong rounded-3xl p-10">
                        <h3 class="text-xl font-black uppercase mb-6">Imagens e Mídia</h3>
                        
                        <div class="space-y-6">
                            <div>
                                <label class="text-xs font-bold text-zinc-400 mb-3 block">Logo URL</label>
                                <div class="flex gap-3">
                                    <input type="url" name="logo_url" value="<?= htmlspecialchars($c['logo_url']) ?>"
                                           placeholder="https://i.imgur.com/logo.png"
                                           class="flex-1 bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-500 transition">
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
                                <input type="url" name="favicon_url" value="<?= htmlspecialchars($c['favicon_url']) ?>"
                                       placeholder="https://i.imgur.com/favicon.png"
                                       class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-500 transition">
                                <p class="text-[9px] text-zinc-600 mt-2">Ícone da aba do navegador, 32x32px</p>
                            </div>

                            <div>
                                <label class="text-xs font-bold text-zinc-400 mb-3 block">Banner Principal URL</label>
                                <input type="url" name="banner_url" value="<?= htmlspecialchars($c['banner_url']) ?>"
                                       placeholder="https://i.imgur.com/banner.jpg"
                                       class="w-full bg-black/30 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-purple-500 transition">
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
                    
                    <!-- CSS Personalizado -->
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
                        
                        <textarea name="custom_css" rows="12"
                                  placeholder="/* Seu CSS aqui */&#10;.minha-classe {&#10;    color: #8b5cf6;&#10;    font-weight: bold;&#10;}"
                                  class="w-full bg-black/40 border border-white/10 p-4 rounded-xl text-xs font-mono outline-none focus:border-purple-500 transition resize-none"><?= htmlspecialchars($c['custom_css']) ?></textarea>
                        
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

                    <!-- JavaScript Personalizado -->
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
                        
                        <textarea name="custom_js" rows="12"
                                  placeholder="// Seu JavaScript aqui&#10;console.log('Loja customizada!');"
                                  class="w-full bg-black/40 border border-white/10 p-4 rounded-xl text-xs font-mono outline-none focus:border-purple-500 transition resize-none"><?= htmlspecialchars($c['custom_js']) ?></textarea>
                        
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

                    <!-- Reset -->
                    <div class="glass-strong rounded-3xl p-10 border-2 border-red-500/20">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-red-500/10 rounded-xl flex items-center justify-center flex-shrink-0">
                                <i data-lucide="trash-2" class="w-6 h-6 text-red-500"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-black uppercase mb-2">Zona de Perigo</h3>
                                <p class="text-zinc-500 text-sm mb-4">Ações irreversíveis que afetam toda a personalização</p>
                                <button type="button" onclick="resetCustomization()"
                                        class="px-6 py-3 bg-red-500/10 border border-red-500/30 text-red-500 rounded-xl font-bold text-sm hover:bg-red-500/20 transition">
                                    Restaurar Configurações Padrão
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </form>

    </main>

    <script>
        lucide.createIcons();
        
        // Tab Switching
        function switchTab(tabName) {
            // Esconde todas as tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Remove active de todos os botÃµes
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
                btn.classList.add('text-zinc-500');
            });
            
            // Mostra tab selecionada
            document.getElementById('tab-' + tabName).classList.remove('hidden');
            
            // Ativa botÃ£o
            event.target.closest('.tab-button').classList.add('active');
            event.target.closest('.tab-button').classList.remove('text-zinc-500');
        }
        
        // Preview de Cores ao Vivo
        function updatePreview(type, value) {
            const preview = document.getElementById('preview-' + type);
            if (preview) {
                preview.style.background = value;
            }
            
            // Atualiza campo de texto hex
            const input = document.querySelector(`input[name="${type}_color"]`);
            if (input && input.nextElementSibling) {
                input.nextElementSibling.querySelector('input[type="text"]').value = value;
            }
        }
        
        // Sincroniza color picker com input text
        document.querySelectorAll('input[type="color"]').forEach(colorInput => {
            const textInput = colorInput.nextElementSibling?.querySelector('input[type="text"]');
            if (textInput) {
                textInput.addEventListener('input', (e) => {
                    const value = e.target.value;
                    if (/^#[0-9A-F]{6}$/i.test(value)) {
                        colorInput.value = value;
                        const type = colorInput.name.replace('_color', '');
                        updatePreview(type, value);
                    }
                });
            }
        });
        
        // Reset Customização
        function resetCustomization() {
            if (confirm('⚠️ ATENÇÃO! Isso irá restaurar TODAS as configurações para o padrão. Esta ação não pode ser desfeita. Deseja continuar?')) {
                // Reseta template
                document.querySelector('input[name="template"][value="neon"]').checked = true;
                
                // Reseta cores
                document.querySelector('input[name="primary_color"]').value = '#8b5cf6';
                document.querySelector('input[name="secondary_color"]').value = '#0f172a';
                document.querySelector('input[name="accent_color"]').value = '#ec4899';
                
                // Reseta campos de texto
                document.querySelector('input[name="logo_url"]').value = '';
                document.querySelector('input[name="favicon_url"]').value = '';
                document.querySelector('input[name="banner_url"]').value = '';
                document.querySelector('input[name="store_title"]').value = '<?= addslashes($store_name) ?>';
                document.querySelector('textarea[name="store_description"]').value = 'Loja premium de itens Minecraft';
                document.querySelector('input[name="store_tagline"]').value = 'Os melhores items do servidor';
                
                // Reseta selects
                document.querySelector('select[name="background_pattern"]').value = 'dots';
                document.querySelector('select[name="card_style"]').value = 'glass';
                document.querySelector('select[name="button_style"]').value = 'rounded';
                document.querySelector('select[name="font_family"]').value = 'inter';
                
                // Reseta checkboxes
                document.querySelector('input[name="show_particles"]').checked = true;
                document.querySelector('input[name="show_blur_effects"]').checked = true;
                document.querySelector('input[name="dark_mode"]').checked = true;
                document.querySelector('input[name="show_gradient_bg"]').checked = true;
                
                // Reseta CSS/JS
                document.querySelector('textarea[name="custom_css"]').value = '';
                document.querySelector('textarea[name="custom_js"]').value = '';
                
                // Atualiza previews
                updatePreview('primary', '#8b5cf6');
                updatePreview('secondary', '#0f172a');
                updatePreview('accent', '#ec4899');
                
                alert('✅ Configurações restauradas! Clique em "Salvar Tudo" para aplicar.');
            }
        }
        
        // Auto-save warning
        let formChanged = false;
        document.querySelector('form').addEventListener('input', () => {
            formChanged = true;
        });
        
        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'Você tem alterações não salvas. Deseja realmente sair?';
            }
        });
        
        document.querySelector('form').addEventListener('submit', () => {
            formChanged = false;
        });
    </script>
</body>
</html>