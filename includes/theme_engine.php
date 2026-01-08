<?php
/**
 * ============================================
 * SPLITSTORE - MOTOR DE TEMAS V2.0
 * ============================================
 * Sistema completo de renderização de temas
 * Coloque em: includes/theme_engine.php
 */

class ThemeEngine {
    private $pdo;
    private $theme_data;
    
    public function __construct($pdo, $store_id) {
        $this->pdo = $pdo;
        $this->loadTheme($store_id);
    }
    
    /**
     * Carrega tema do banco
     */
    private function loadTheme($store_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM store_customization WHERE store_id = ?");
            $stmt->execute([$store_id]);
            $theme = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($theme) {
                $this->theme_data = $theme;
            } else {
                $this->theme_data = $this->getDefaultTheme();
            }
        } catch (PDOException $e) {
            error_log("Theme Engine Error: " . $e->getMessage());
            $this->theme_data = $this->getDefaultTheme();
        }
    }
    
    /**
     * Retorna tema padrão
     */
    private function getDefaultTheme() {
        return [
            'template' => 'neon_gaming',
            'primary_color' => '#8b5cf6',
            'secondary_color' => '#0f172a',
            'accent_color' => '#ec4899',
            'background_pattern' => 'dots',
            'card_style' => 'glass',
            'button_style' => 'rounded',
            'font_family' => 'inter',
            'show_particles' => 1,
            'show_blur_effects' => 1,
            'dark_mode' => 1,
            'show_gradient_bg' => 1,
            'custom_css' => '',
            'custom_js' => ''
        ];
    }
    
    /**
     * Gera CSS completo do tema
     */
    public function generateCSS() {
        $template = $this->theme_data['template'];
        $primary = $this->theme_data['primary_color'];
        $secondary = $this->theme_data['secondary_color'];
        $accent = $this->theme_data['accent_color'];
        
        $css = "
        <style>
        :root {
            --primary: {$primary};
            --secondary: {$secondary};
            --accent: {$accent};
            --font-family: '{$this->getFontFamily()}', sans-serif;
        }
        
        body {
            font-family: var(--font-family);
            background: {$this->getBackgroundStyle()};
            color: #ffffff;
        }
        
        /* TEMA BASE: {$template} */
        {$this->getTemplateCSS($template)}
        
        /* PADRÃO DE FUNDO */
        {$this->getPatternCSS()}
        
        /* ESTILO DE CARDS */
        {$this->getCardCSS()}
        
        /* ESTILO DE BOTÕES */
        {$this->getButtonCSS()}
        
        /* EFEITOS ESPECIAIS */
        {$this->getEffectsCSS()}
        
        /* CSS PERSONALIZADO */
        {$this->theme_data['custom_css']}
        </style>
        ";
        
        return $css;
    }
    
    /**
     * CSS específico de cada template
     */
    private function getTemplateCSS($template) {
        $templates = [
            'neon_gaming' => "
                body { background: linear-gradient(135deg, #0a0a0a 0%, #1a0a2e 100%); }
                .product-card { 
                    border: 1px solid rgba(139, 92, 246, 0.2);
                    transition: all 0.3s ease;
                }
                .product-card:hover {
                    border-color: var(--primary);
                    box-shadow: 0 0 30px rgba(139, 92, 246, 0.4);
                    transform: translateY(-5px);
                }
                .btn-primary {
                    background: linear-gradient(135deg, var(--primary), var(--accent));
                    box-shadow: 0 0 20px rgba(139, 92, 246, 0.5);
                }
                h1, h2, h3 {
                    background: linear-gradient(135deg, var(--primary), var(--accent));
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    text-shadow: 0 0 30px rgba(139, 92, 246, 0.5);
                }
            ",
            
            'dark_premium' => "
                body { background: #0f172a; }
                .product-card {
                    background: rgba(255, 255, 255, 0.02);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255, 255, 255, 0.05);
                }
                .product-card:hover {
                    background: rgba(255, 255, 255, 0.05);
                    border-color: rgba(59, 130, 246, 0.3);
                }
                .btn-primary {
                    background: var(--primary);
                    box-shadow: 0 10px 30px -10px var(--primary);
                }
            ",
            
            'fire_rage' => "
                body { 
                    background: linear-gradient(135deg, #0a0a0a 0%, #1a0505 100%);
                    position: relative;
                }
                body::before {
                    content: '';
                    position: fixed;
                    inset: 0;
                    background: radial-gradient(circle at 50% 50%, rgba(239, 68, 68, 0.1) 0%, transparent 70%);
                    pointer-events: none;
                }
                .product-card {
                    background: linear-gradient(135deg, rgba(20, 5, 5, 0.8) 0%, rgba(10, 5, 5, 0.9) 100%);
                    border: 1px solid rgba(239, 68, 68, 0.3);
                }
                .product-card:hover {
                    border-color: var(--primary);
                    box-shadow: 0 0 40px rgba(239, 68, 68, 0.6);
                    animation: fire-pulse 0.5s ease;
                }
                @keyframes fire-pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.02); }
                }
                .btn-primary {
                    background: linear-gradient(135deg, #ef4444, #dc2626);
                    position: relative;
                    overflow: hidden;
                }
                .btn-primary::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.2), transparent);
                    animation: fire-shine 2s infinite;
                }
                @keyframes fire-shine {
                    0% { transform: translateX(-100%); }
                    100% { transform: translateX(100%); }
                }
            ",
            
            'nature_green' => "
                body { 
                    background: linear-gradient(135deg, #064e3b 0%, #022c22 100%);
                }
                .product-card {
                    background: rgba(16, 185, 129, 0.05);
                    border: 1px solid rgba(16, 185, 129, 0.2);
                }
                .product-card:hover {
                    background: rgba(16, 185, 129, 0.1);
                    border-color: var(--primary);
                    box-shadow: 0 0 30px rgba(16, 185, 129, 0.3);
                }
                .btn-primary {
                    background: linear-gradient(135deg, #10b981, #059669);
                }
            ",
            
            'ice_crystal' => "
                body { 
                    background: linear-gradient(135deg, #0c4a6e 0%, #082f49 100%);
                }
                .product-card {
                    background: rgba(6, 182, 212, 0.05);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(6, 182, 212, 0.2);
                }
                .product-card:hover {
                    border-color: var(--primary);
                    box-shadow: 0 0 40px rgba(6, 182, 212, 0.4);
                }
                .btn-primary {
                    background: linear-gradient(135deg, #06b6d4, #0891b2);
                    box-shadow: 0 0 30px rgba(6, 182, 212, 0.4);
                }
            ",
            
            'royal_gold' => "
                body { 
                    background: linear-gradient(135deg, #1c1917 0%, #0a0a0a 100%);
                }
                .product-card {
                    background: linear-gradient(135deg, rgba(30, 27, 23, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%);
                    border: 1px solid rgba(245, 158, 11, 0.2);
                }
                .product-card:hover {
                    border-color: var(--primary);
                    box-shadow: 0 10px 40px rgba(245, 158, 11, 0.3);
                }
                h1, h2, h3 {
                    background: linear-gradient(135deg, #f59e0b, #fbbf24);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                }
                .btn-primary {
                    background: linear-gradient(135deg, #f59e0b, #d97706);
                }
            ",
            
            'minimal_light' => "
                body { 
                    background: #ffffff;
                    color: #1f2937;
                }
                .product-card {
                    background: white;
                    border: 2px solid #e5e7eb;
                }
                .product-card:hover {
                    border-color: var(--primary);
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                }
                .btn-primary {
                    background: var(--primary);
                    color: white;
                }
            ",
            
            'cyberpunk' => "
                body { 
                    background: #000000;
                    position: relative;
                }
                body::before {
                    content: '';
                    position: fixed;
                    inset: 0;
                    background: 
                        linear-gradient(0deg, transparent 0%, rgba(252, 238, 9, 0.05) 50%, transparent 100%),
                        linear-gradient(90deg, rgba(0, 240, 255, 0.05) 0%, transparent 50%, rgba(252, 238, 9, 0.05) 100%);
                    pointer-events: none;
                }
                .product-card {
                    background: linear-gradient(135deg, rgba(10, 10, 10, 0.9) 0%, rgba(20, 20, 20, 0.8) 100%);
                    border: 2px solid transparent;
                    background-clip: padding-box;
                    position: relative;
                }
                .product-card::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    border: 2px solid transparent;
                    border-image: linear-gradient(135deg, #fcee09, #00f0ff) 1;
                    opacity: 0;
                    transition: opacity 0.3s;
                }
                .product-card:hover::before {
                    opacity: 1;
                }
                .btn-primary {
                    background: linear-gradient(135deg, #fcee09, #00f0ff);
                    color: #000;
                    font-weight: 900;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                }
            ",
            
            // TEMAS MINECRAFT
            'nether_realm' => "
                body { 
                    background: linear-gradient(135deg, #1a0505 0%, #0a0000 100%);
                    position: relative;
                }
                body::before {
                    content: '';
                    position: fixed;
                    inset: 0;
                    background: 
                        radial-gradient(circle at 20% 30%, rgba(220, 38, 38, 0.15) 0%, transparent 40%),
                        radial-gradient(circle at 80% 70%, rgba(249, 115, 22, 0.15) 0%, transparent 40%);
                    pointer-events: none;
                }
                .product-card {
                    background: linear-gradient(135deg, rgba(30, 10, 10, 0.8) 0%, rgba(20, 5, 5, 0.9) 100%);
                    border: 2px solid rgba(220, 38, 38, 0.3);
                    position: relative;
                    overflow: hidden;
                }
                .product-card::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    left: -50%;
                    width: 200%;
                    height: 200%;
                    background: linear-gradient(45deg, transparent 30%, rgba(249, 115, 22, 0.1) 50%, transparent 70%);
                    animation: nether-glow 3s linear infinite;
                }
                @keyframes nether-glow {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .product-card:hover {
                    border-color: #dc2626;
                    box-shadow: 
                        0 0 40px rgba(220, 38, 38, 0.6),
                        inset 0 0 40px rgba(249, 115, 22, 0.1);
                    transform: translateY(-8px);
                }
                .btn-primary {
                    background: linear-gradient(135deg, #dc2626, #f97316);
                    box-shadow: 0 10px 40px rgba(220, 38, 38, 0.5);
                    position: relative;
                }
                .btn-primary::after {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
                    animation: lava-flow 2s infinite;
                }
                @keyframes lava-flow {
                    0% { transform: translateX(-100%); }
                    100% { transform: translateX(100%); }
                }
                h1, h2, h3 {
                    background: linear-gradient(135deg, #dc2626, #f97316, #fbbf24);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    text-shadow: 0 0 40px rgba(220, 38, 38, 0.8);
                    filter: drop-shadow(0 0 10px rgba(249, 115, 22, 0.6));
                }
            ",
            
            'ender_kingdom' => "
                body { 
                    background: #000000;
                    position: relative;
                }
                body::before {
                    content: '';
                    position: fixed;
                    inset: 0;
                    background: 
                        radial-gradient(circle at 30% 40%, rgba(139, 92, 246, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 70% 60%, rgba(168, 85, 247, 0.15) 0%, transparent 50%);
                    pointer-events: none;
                }
                .product-card {
                    background: linear-gradient(135deg, rgba(20, 10, 30, 0.8) 0%, rgba(10, 5, 15, 0.9) 100%);
                    border: 2px solid rgba(139, 92, 246, 0.3);
                    position: relative;
                }
                .product-card::before {
                    content: '';
                    position: absolute;
                    inset: -2px;
                    background: linear-gradient(45deg, 
                        rgba(139, 92, 246, 0) 0%,
                        rgba(139, 92, 246, 0.5) 50%,
                        rgba(139, 92, 246, 0) 100%);
                    opacity: 0;
                    transition: opacity 0.5s;
                    animation: ender-pulse 3s ease-in-out infinite;
                }
                @keyframes ender-pulse {
                    0%, 100% { opacity: 0; }
                    50% { opacity: 0.3; }
                }
                .product-card:hover {
                    border-color: #8b5cf6;
                    box-shadow: 
                        0 0 50px rgba(139, 92, 246, 0.8),
                        inset 0 0 30px rgba(168, 85, 247, 0.2);
                    transform: translateY(-10px) scale(1.02);
                }
                .product-card:hover::before {
                    opacity: 1;
                }
                .btn-primary {
                    background: linear-gradient(135deg, #8b5cf6, #a855f7);
                    box-shadow: 0 10px 50px rgba(139, 92, 246, 0.6);
                    position: relative;
                    overflow: hidden;
                }
                .btn-primary::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
                    animation: ender-teleport 1.5s infinite;
                }
                @keyframes ender-teleport {
                    0% { transform: translateX(-100%) skewX(-20deg); }
                    100% { transform: translateX(100%) skewX(-20deg); }
                }
                h1, h2, h3 {
                    background: linear-gradient(135deg, #8b5cf6, #a855f7, #c084fc);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    filter: drop-shadow(0 0 20px rgba(139, 92, 246, 0.8));
                }
            ",
            
            'emerald_valley' => "
                body { 
                    background: linear-gradient(135deg, #064e3b 0%, #022c22 100%);
                    position: relative;
                }
                body::before {
                    content: '';
                    position: fixed;
                    inset: 0;
                    background: 
                        radial-gradient(circle at 40% 30%, rgba(16, 185, 129, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 60% 70%, rgba(52, 211, 153, 0.1) 0%, transparent 50%);
                    pointer-events: none;
                }
                .product-card {
                    background: linear-gradient(135deg, rgba(20, 60, 50, 0.6) 0%, rgba(10, 40, 30, 0.8) 100%);
                    border: 2px solid rgba(16, 185, 129, 0.3);
                    position: relative;
                }
                .product-card::after {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 2px;
                    background: linear-gradient(90deg, 
                        transparent 0%,
                        rgba(52, 211, 153, 0.8) 50%,
                        transparent 100%);
                    opacity: 0;
                    transition: opacity 0.3s;
                }
                .product-card:hover {
                    border-color: #10b981;
                    box-shadow: 
                        0 0 40px rgba(16, 185, 129, 0.5),
                        inset 0 0 20px rgba(52, 211, 153, 0.1);
                    transform: translateY(-6px);
                }
                .product-card:hover::after {
                    opacity: 1;
                }
                .btn-primary {
                    background: linear-gradient(135deg, #10b981, #34d399);
                    box-shadow: 0 10px 40px rgba(16, 185, 129, 0.4);
                    position: relative;
                }
                .btn-primary::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background: radial-gradient(circle at center, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
                    opacity: 0;
                    transition: opacity 0.3s;
                }
                .btn-primary:hover::before {
                    opacity: 1;
                    animation: emerald-shine 1s ease-out;
                }
                @keyframes emerald-shine {
                    0% { transform: scale(0); }
                    100% { transform: scale(2); opacity: 0; }
                }
                h1, h2, h3 {
                    background: linear-gradient(135deg, #10b981, #34d399, #6ee7b7);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    filter: drop-shadow(0 0 15px rgba(16, 185, 129, 0.6));
                }
            "
        ];
        
        return $templates[$template] ?? $templates['neon_gaming'];
    }
    
    /**
     * CSS para padrões de fundo
     */
    private function getPatternCSS() {
        $pattern = $this->theme_data['background_pattern'];
        
        $patterns = [
            'dots' => "
                body::after {
                    content: '';
                    position: fixed;
                    inset: 0;
                    background-image: radial-gradient(circle, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
                    background-size: 30px 30px;
                    pointer-events: none;
                    z-index: -1;
                }
            ",
            'grid' => "
                body::after {
                    content: '';
                    position: fixed;
                    inset: 0;
                    background-image: 
                        linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
                    background-size: 50px 50px;
                    pointer-events: none;
                    z-index: -1;
                }
            ",
            'waves' => "
                body::after {
                    content: '';
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    height: 300px;
                    background: 
                        radial-gradient(ellipse at center bottom, rgba(255, 255, 255, 0.03) 0%, transparent 70%);
                    pointer-events: none;
                    z-index: -1;
                }
            ",
            'hexagon' => "
                body::after {
                    content: '';
                    position: fixed;
                    inset: 0;
                    background-image: 
                        repeating-linear-gradient(60deg, rgba(255, 255, 255, 0.02) 0px, rgba(255, 255, 255, 0.02) 1px, transparent 1px, transparent 60px),
                        repeating-linear-gradient(-60deg, rgba(255, 255, 255, 0.02) 0px, rgba(255, 255, 255, 0.02) 1px, transparent 1px, transparent 60px);
                    pointer-events: none;
                    z-index: -1;
                }
            ",
            'circuit' => "
                body::after {
                    content: '';
                    position: fixed;
                    inset: 0;
                    background-image: 
                        linear-gradient(90deg, transparent 49%, rgba(255, 255, 255, 0.02) 49%, rgba(255, 255, 255, 0.02) 51%, transparent 51%),
                        linear-gradient(transparent 49%, rgba(255, 255, 255, 0.02) 49%, rgba(255, 255, 255, 0.02) 51%, transparent 51%);
                    background-size: 100px 100px;
                    pointer-events: none;
                    z-index: -1;
                }
            "
        ];
        
        return $patterns[$pattern] ?? '';
    }
    
    /**
     * CSS para estilo de cards
     */
    private function getCardCSS() {
        $style = $this->theme_data['card_style'];
        
        $styles = [
            'glass' => "
                .product-card {
                    background: rgba(255, 255, 255, 0.03);
                    backdrop-filter: blur(20px);
                    border-radius: 24px;
                }
            ",
            'solid' => "
                .product-card {
                    background: rgba(20, 20, 20, 0.95);
                    border-radius: 16px;
                }
            ",
            'bordered' => "
                .product-card {
                    background: transparent;
                    border: 2px solid rgba(255, 255, 255, 0.1);
                    border-radius: 20px;
                }
            ",
            'gradient' => "
                .product-card {
                    background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.01) 100%);
                    border-radius: 24px;
                }
            ",
            'neumorphic' => "
                .product-card {
                    background: #1a1a1a;
                    box-shadow: 
                        20px 20px 60px #0d0d0d,
                        -20px -20px 60px #272727;
                    border-radius: 20px;
                }
            "
        ];
        
        return $styles[$style] ?? $styles['glass'];
    }
    
    /**
     * CSS para estilo de botões
     */
    private function getButtonCSS() {
        $style = $this->theme_data['button_style'];
        
        $styles = [
            'rounded' => "
                .btn-primary {
                    border-radius: 16px;
                    padding: 16px 32px;
                    font-weight: 900;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
            ",
            'square' => "
                .btn-primary {
                    border-radius: 4px;
                    padding: 16px 32px;
                    font-weight: 900;
                }
            ",
            'pill' => "
                .btn-primary {
                    border-radius: 999px;
                    padding: 16px 40px;
                    font-weight: 900;
                }
            ",
            'sharp' => "
                .btn-primary {
                    border-radius: 0;
                    padding: 16px 32px;
                    font-weight: 900;
                    clip-path: polygon(8px 0, 100% 0, 100% calc(100% - 8px), calc(100% - 8px) 100%, 0 100%, 0 8px);
                }
            "
        ];
        
        return $styles[$style] ?? $styles['rounded'];
    }
    
    /**
     * CSS para efeitos especiais
     */
    private function getEffectsCSS() {
        $css = '';
        
        if ($this->theme_data['show_blur_effects']) {
            $css .= "
                .glass-effect {
                    backdrop-filter: blur(20px);
                }
            ";
        }
        
        if ($this->theme_data['show_gradient_bg']) {
            $css .= "
                body {
                    background-attachment: fixed;
                }
            ";
        }
        
        return $css;
    }
    
    /**
     * Gera JavaScript do tema
     */
    public function generateJS() {
        $js = "<script>\n";
        
        // Partículas
        if ($this->theme_data['show_particles']) {
            $js .= $this->getParticlesJS();
        }
        
        // JS Personalizado
        if (!empty($this->theme_data['custom_js'])) {
            $js .= $this->theme_data['custom_js'] . "\n";
        }
        
        $js .= "</script>";
        
        return $js;
    }
    
    /**
     * JavaScript para partículas
     */
    private function getParticlesJS() {
        return "
        // Sistema de Partículas Simples
        (function() {
            const canvas = document.createElement('canvas');
            canvas.style.position = 'fixed';
            canvas.style.top = '0';
            canvas.style.left = '0';
            canvas.style.width = '100%';
            canvas.style.height = '100%';
            canvas.style.pointerEvents = 'none';
            canvas.style.zIndex = '1';
            canvas.style.opacity = '0.3';
            document.body.appendChild(canvas);
            
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            
            const particles = [];
            const particleCount = 50;
            
            for (let i = 0; i < particleCount; i++) {
                particles.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    vx: (Math.random() - 0.5) * 0.5,
                    vy: (Math.random() - 0.5) * 0.5,
                    size: Math.random() * 2 + 1
                });
            }
            
            function animate() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--primary');
                
                particles.forEach(p => {
                    p.x += p.vx;
                    p.y += p.vy;
                    
                    if (p.x < 0 || p.x > canvas.width) p.vx *= -1;
                    if (p.y < 0 || p.y > canvas.height) p.vy *= -1;
                    
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                    ctx.fill();
                });
                
                requestAnimationFrame(animate);
            }
            
            animate();
            
            window.addEventListener('resize', () => {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            });
        })();
        ";
    }
    
    /**
     * Retorna fonte do tema
     */
    private function getFontFamily() {
        $fonts = [
            'inter' => 'Inter',
            'poppins' => 'Poppins',
            'roboto' => 'Roboto',
            'montserrat' => 'Montserrat',
            'orbitron' => 'Orbitron',
            'rajdhani' => 'Rajdhani'
        ];
        
        return $fonts[$this->theme_data['font_family']] ?? 'Inter';
    }
    
    /**
     * Retorna estilo de background
     */
    private function getBackgroundStyle() {
        if ($this->theme_data['dark_mode']) {
            return $this->theme_data['secondary_color'];
        }
        return '#ffffff';
    }
    
    /**
     * Retorna link de fonte do Google Fonts
     */
    public function getFontLink() {
        $font = $this->getFontFamily();
        return "https://fonts.googleapis.com/css2?family={$font}:wght@400;600;700;900&display=swap";
    }
    
    /**
     * Renderiza head completo
     */
    public function renderHead() {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        echo '<link href="' . $this->getFontLink() . '" rel="stylesheet">';
        echo $this->generateCSS();
    }
    
    /**
     * Renderiza scripts
     */
    public function renderScripts() {
        echo $this->generateJS();
    }
}