<?php
/**
 * ============================================
 * SPLITSTORE - APLICADOR DE TEMA PADRÃO V3.0
 * ============================================
 * Aplica tema padrão profissional para novas lojas
 * Arquivo: client/apply_default_theme.php
 */

require_once '../includes/db.php';
require_once '../includes/auth_guard.php';

requireAccess(__FILE__);

// TEMA PADRÃO NEON GAMING (Profissional e Moderno)
$defaultTheme = [
    'template' => 'neon_gaming',
    'primary_color' => '#8b5cf6',
    'secondary_color' => '#0f172a',
    'accent_color' => '#ec4899',
    'logo_url' => '',
    'favicon_url' => '',
    'banner_url' => '',
    'background_pattern' => 'dots',
    'animation_style' => 'smooth',
    'card_style' => 'glass',
    'button_style' => 'rounded',
    'font_family' => 'inter',
    'store_title' => '',
    'store_description' => 'Loja premium de itens Minecraft com os melhores VIPs, kits e vantagens do servidor',
    'store_tagline' => 'Transforme sua experiência no servidor',
    'show_particles' => 1,
    'show_blur_effects' => 1,
    'dark_mode' => 1,
    'show_gradient_bg' => 1,
    'custom_css' => '',
    'custom_js' => ''
];

/**
 * Aplica tema padrão para uma loja
 */
function applyDefaultTheme($pdo, $store_id, $store_name) {
    global $defaultTheme;
    
    try {
        // Verifica se já existe customização
        $check = $pdo->prepare("SELECT id FROM store_customization WHERE store_id = ?");
        $check->execute([$store_id]);
        
        if ($check->fetch()) {
            return false; // Já tem customização, não sobrescreve
        }
        
        // Aplica tema padrão
        $theme = $defaultTheme;
        $theme['store_title'] = $store_name . ' - Loja VIP';
        
        $fields = array_keys($theme);
        $sql = "INSERT INTO store_customization (store_id, " . implode(', ', $fields) . ") 
                VALUES (?" . str_repeat(', ?', count($fields)) . ")";
        $values = array_merge([$store_id], array_values($theme));
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao aplicar tema padrão: " . $e->getMessage());
        return false;
    }
}

/**
 * TEMPLATES PRÉ-DEFINIDOS
 */
$premadeTemplates = [
    // ===== TEMAS MINECRAFT =====
    'nether_realm' => [
        'name' => 'Nether Realm',
        'template' => 'nether_realm',
        'primary_color' => '#dc2626',
        'secondary_color' => '#1a0505',
        'accent_color' => '#f97316',
        'background_pattern' => 'dots',
        'card_style' => 'glass',
        'button_style' => 'rounded',
        'font_family' => 'rajdhani',
        'show_particles' => 1,
        'show_blur_effects' => 1,
        'dark_mode' => 1,
        'show_gradient_bg' => 1
    ],
    
    'ender_kingdom' => [
        'name' => 'Ender Kingdom',
        'template' => 'ender_kingdom',
        'primary_color' => '#8b5cf6',
        'secondary_color' => '#000000',
        'accent_color' => '#a855f7',
        'background_pattern' => 'hexagon',
        'card_style' => 'glass',
        'button_style' => 'rounded',
        'font_family' => 'inter',
        'show_particles' => 1,
        'show_blur_effects' => 1,
        'dark_mode' => 1,
        'show_gradient_bg' => 1
    ],
    
    'emerald_valley' => [
        'name' => 'Emerald Valley',
        'template' => 'emerald_valley',
        'primary_color' => '#10b981',
        'secondary_color' => '#064e3b',
        'accent_color' => '#34d399',
        'background_pattern' => 'grid',
        'card_style' => 'glass',
        'button_style' => 'rounded',
        'font_family' => 'poppins',
        'show_particles' => 1,
        'show_blur_effects' => 1,
        'dark_mode' => 1,
        'show_gradient_bg' => 1
    ],
    
    // ===== TEMAS MODERNOS =====
    'neon_gaming' => [
        'name' => 'Neon Gaming',
        'template' => 'neon_gaming',
        'primary_color' => '#8b5cf6',
        'secondary_color' => '#0f172a',
        'accent_color' => '#ec4899',
        'background_pattern' => 'circuit',
        'card_style' => 'glass',
        'button_style' => 'rounded',
        'font_family' => 'inter',
        'show_particles' => 1,
        'show_blur_effects' => 1,
        'dark_mode' => 1,
        'show_gradient_bg' => 1
    ],
    
    'dark_premium' => [
        'name' => 'Dark Premium',
        'template' => 'dark_premium',
        'primary_color' => '#3b82f6',
        'secondary_color' => '#0f172a',
        'accent_color' => '#06b6d4',
        'background_pattern' => 'grid',
        'card_style' => 'glass',
        'button_style' => 'rounded',
        'font_family' => 'inter',
        'show_particles' => 0,
        'show_blur_effects' => 1,
        'dark_mode' => 1,
        'show_gradient_bg' => 0
    ],
    
    'fire_rage' => [
        'name' => 'Fire Rage',
        'template' => 'fire_rage',
        'primary_color' => '#ef4444',
        'secondary_color' => '#0a0a0a',
        'accent_color' => '#f97316',
        'background_pattern' => 'waves',
        'card_style' => 'gradient',
        'button_style' => 'sharp',
        'font_family' => 'rajdhani',
        'show_particles' => 1,
        'show_blur_effects' => 0,
        'dark_mode' => 1,
        'show_gradient_bg' => 1
    ],
    
    'nature_green' => [
        'name' => 'Nature Green',
        'template' => 'nature_green',
        'primary_color' => '#10b981',
        'secondary_color' => '#064e3b',
        'accent_color' => '#34d399',
        'background_pattern' => 'dots',
        'card_style' => 'solid',
        'button_style' => 'rounded',
        'font_family' => 'poppins',
        'show_particles' => 1,
        'show_blur_effects' => 1,
        'dark_mode' => 1,
        'show_gradient_bg' => 1
    ],
    
    'ice_crystal' => [
        'name' => 'Ice Crystal',
        'template' => 'ice_crystal',
        'primary_color' => '#06b6d4',
        'secondary_color' => '#0c4a6e',
        'accent_color' => '#22d3ee',
        'background_pattern' => 'hexagon',
        'card_style' => 'glass',
        'button_style' => 'pill',
        'font_family' => 'inter',
        'show_particles' => 1,
        'show_blur_effects' => 1,
        'dark_mode' => 1,
        'show_gradient_bg' => 1
    ],
    
    'royal_gold' => [
        'name' => 'Royal Gold',
        'template' => 'royal_gold',
        'primary_color' => '#f59e0b',
        'secondary_color' => '#1c1917',
        'accent_color' => '#fbbf24',
        'background_pattern' => 'grid',
        'card_style' => 'bordered',
        'button_style' => 'sharp',
        'font_family' => 'montserrat',
        'show_particles' => 0,
        'show_blur_effects' => 1,
        'dark_mode' => 1,
        'show_gradient_bg' => 0
    ],
    
    'minimal_light' => [
        'name' => 'Minimal Light',
        'template' => 'minimal_light',
        'primary_color' => '#6366f1',
        'secondary_color' => '#ffffff',
        'accent_color' => '#8b5cf6',
        'background_pattern' => 'none',
        'card_style' => 'bordered',
        'button_style' => 'rounded',
        'font_family' => 'inter',
        'show_particles' => 0,
        'show_blur_effects' => 0,
        'dark_mode' => 0,
        'show_gradient_bg' => 0
    ],
    
    'cyberpunk' => [
        'name' => 'Cyberpunk 2077',
        'template' => 'cyberpunk',
        'primary_color' => '#fcee09',
        'secondary_color' => '#000000',
        'accent_color' => '#00f0ff',
        'background_pattern' => 'circuit',
        'card_style' => 'gradient',
        'button_style' => 'sharp',
        'font_family' => 'orbitron',
        'show_particles' => 1,
        'show_blur_effects' => 0,
        'dark_mode' => 1,
        'show_gradient_bg' => 1
    ]
];

/**
 * Aplica template pré-definido
 */
function applyPremadeTemplate($pdo, $store_id, $store_name, $templateKey) {
    global $premadeTemplates, $defaultTheme;
    
    if (!isset($premadeTemplates[$templateKey])) {
        return false;
    }
    
    try {
        $template = array_merge($defaultTheme, $premadeTemplates[$templateKey]);
        $template['store_title'] = $store_name . ' - Loja VIP';
        $template['store_description'] = 'Loja premium de itens Minecraft com os melhores VIPs, kits e vantagens do servidor';
        $template['store_tagline'] = 'Transforme sua experiência no servidor';
        
        // Verifica se já existe
        $check = $pdo->prepare("SELECT id FROM store_customization WHERE store_id = ?");
        $check->execute([$store_id]);
        
        if ($check->fetch()) {
            // UPDATE
            $fields = array_keys($template);
            $sql = "UPDATE store_customization SET " . 
                   implode(', ', array_map(fn($f) => "$f = ?", $fields)) . 
                   " WHERE store_id = ?";
            $values = array_merge(array_values($template), [$store_id]);
        } else {
            // INSERT
            $fields = array_keys($template);
            $sql = "INSERT INTO store_customization (store_id, " . implode(', ', $fields) . ") 
                    VALUES (?" . str_repeat(', ?', count($fields)) . ")";
            $values = array_merge([$store_id], array_values($template));
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao aplicar template: " . $e->getMessage());
        return false;
    }
}

// Se for chamado via POST (API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_template'])) {
    header('Content-Type: application/json');
    
    $store_id = $_POST['store_id'] ?? null;
    $template_key = $_POST['template_key'] ?? 'neon_gaming';
    
    if (!$store_id) {
        echo json_encode(['success' => false, 'message' => 'Store ID não fornecido']);
        exit;
    }
    
    // Busca nome da loja
    $stmt = $pdo->prepare("SELECT store_name FROM stores WHERE id = ?");
    $stmt->execute([$store_id]);
    $store = $stmt->fetch();
    
    if (!$store) {
        echo json_encode(['success' => false, 'message' => 'Loja não encontrada']);
        exit;
    }
    
    $success = applyPremadeTemplate($pdo, $store_id, $store['store_name'], $template_key);
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Template aplicado com sucesso!' : 'Erro ao aplicar template'
    ]);
    exit;
}

/**
 * CSS PERSONALIZADOS POR TEMA
 */
$customCSS = [
    'neon_gaming' => "
/* Neon Gaming - Efeitos especiais */
@keyframes neon-pulse {
    0%, 100% { text-shadow: 0 0 10px var(--primary), 0 0 20px var(--primary); }
    50% { text-shadow: 0 0 20px var(--primary), 0 0 40px var(--primary); }
}

.product-card:hover {
    box-shadow: 0 0 30px rgba(139, 92, 246, 0.4);
}

.btn-primary {
    box-shadow: 0 0 20px rgba(139, 92, 246, 0.5);
}
",
    
    'fire_rage' => "
/* Fire Rage - Efeitos de fogo */
@keyframes fire-flicker {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

.product-card:hover {
    border-color: #ef4444;
    animation: fire-flicker 0.5s ease-in-out;
}
",
    
    'royal_gold' => "
/* Royal Gold - Efeitos luxuosos */
.product-card {
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.product-card:hover {
    border-color: #f59e0b;
    box-shadow: 0 10px 40px rgba(245, 158, 11, 0.3);
}

h1, h2, h3 {
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
",

    // ===== CSS TEMAS MINECRAFT =====
    'nether_realm' => "
/* Nether Realm - Efeitos de Lava e Fogo */
@keyframes lava-bubble {
    0%, 100% { transform: translateY(0px) scale(1); }
    50% { transform: translateY(-10px) scale(1.05); }
}

@keyframes fire-glow {
    0%, 100% { 
        box-shadow: 0 0 40px rgba(220, 38, 38, 0.6),
                    0 0 80px rgba(249, 115, 22, 0.3);
    }
    50% { 
        box-shadow: 0 0 60px rgba(220, 38, 38, 0.8),
                    0 0 100px rgba(249, 115, 22, 0.5);
    }
}

.product-card {
    animation: lava-bubble 4s ease-in-out infinite;
}

.product-card:hover {
    animation: fire-glow 1s ease-in-out infinite;
}

.btn-primary {
    position: relative;
    overflow: hidden;
}

.btn-primary::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-primary:hover::before {
    width: 300px;
    height: 300px;
}
",

    'ender_kingdom' => "
/* Ender Kingdom - Efeitos de Teletransporte */
@keyframes ender-particle {
    0% {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
    100% {
        transform: translateY(-30px) scale(0);
        opacity: 0;
    }
}

@keyframes dimensional-shift {
    0%, 100% {
        filter: hue-rotate(0deg);
    }
    50% {
        filter: hue-rotate(20deg);
    }
}

.product-card {
    position: relative;
}

.product-card::after {
    content: '';
    position: absolute;
    inset: -20px;
    background: radial-gradient(circle, rgba(139, 92, 246, 0.3) 0%, transparent 70%);
    opacity: 0;
    transition: opacity 0.5s;
    pointer-events: none;
    z-index: -1;
}

.product-card:hover::after {
    opacity: 1;
    animation: dimensional-shift 2s ease-in-out infinite;
}

@keyframes ender-glow {
    0%, 100% {
        box-shadow: 0 0 20px rgba(139, 92, 246, 0.5),
                    inset 0 0 20px rgba(168, 85, 247, 0.2);
    }
    50% {
        box-shadow: 0 0 40px rgba(139, 92, 246, 0.8),
                    inset 0 0 30px rgba(168, 85, 247, 0.4);
    }
}

.btn-primary:hover {
    animation: ender-glow 1.5s ease-in-out infinite;
}
",

    'emerald_valley' => "
/* Emerald Valley - Efeitos de Esmeralda */
@keyframes emerald-shine {
    0% {
        background-position: -200% center;
    }
    100% {
        background-position: 200% center;
    }
}

@keyframes village-pulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
    }
    50% {
        transform: scale(1.02);
        box-shadow: 0 0 40px rgba(16, 185, 129, 0.6);
    }
}

.product-card {
    position: relative;
    overflow: hidden;
}

.product-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, 
        transparent, 
        rgba(52, 211, 153, 0.8), 
        transparent);
    transition: left 0.5s;
}

.product-card:hover::before {
    left: 100%;
}

.product-card:hover {
    animation: village-pulse 2s ease-in-out infinite;
}

.btn-primary {
    background: linear-gradient(135deg, 
        #10b981 0%, 
        #34d399 25%, 
        #6ee7b7 50%, 
        #34d399 75%, 
        #10b981 100%);
    background-size: 200% 100%;
}

.btn-primary:hover {
    animation: emerald-shine 2s linear infinite;
}
"
];
?>