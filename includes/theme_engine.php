<?php
/**
 * ============================================
 * SPLITSTORE - MOTOR DE TEMAS V3.0 COMPLETO
 * ============================================
 * Sistema completo com TODOS os temas Minecraft
 * Arquivo: includes/theme_engine.php
 */

class ThemeEngine {
    private $pdo;
    private $theme_data;
    
    public function __construct($pdo, $store_id) {
        $this->pdo = $pdo;
        $this->loadTheme($store_id);
    }
    
    private function loadTheme($store_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM store_customization WHERE store_id = ?");
            $stmt->execute([$store_id]);
            $theme = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->theme_data = $theme ?: $this->getDefaultTheme();
        } catch (PDOException $e) {
            error_log("Theme Engine Error: " . $e->getMessage());
            $this->theme_data = $this->getDefaultTheme();
        }
    }
    
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
     * Gera CSS COMPLETO do tema
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
            color: #ffffff;
        }
        
        /* =========================================
           ANIMAÇÕES GLOBAIS MINECRAFT
           ========================================= */
        
        /* Nether - Lava Bubble */
        @keyframes lava-bubble {
            0%, 100% { transform: translateY(0px) scale(1); }
            50% { transform: translateY(-10px) scale(1.05); }
        }
        
        @keyframes fire-glow {
            0%, 100% { 
                box-shadow: 0 0 40px rgba(220, 38, 38, 0.6), 0 0 80px rgba(249, 115, 22, 0.3);
            }
            50% { 
                box-shadow: 0 0 60px rgba(220, 38, 38, 0.8), 0 0 100px rgba(249, 115, 22, 0.5);
            }
        }
        
        /* Ender - Partículas */
        @keyframes ender-particle {
            0% { transform: translateY(0) scale(1); opacity: 1; }
            100% { transform: translateY(-30px) scale(0); opacity: 0; }
        }
        
        @keyframes dimensional-shift {
            0%, 100% { filter: hue-rotate(0deg); }
            50% { filter: hue-rotate(20deg); }
        }
        
        @keyframes ender-glow {
            0%, 100% {
                box-shadow: 0 0 20px rgba(139, 92, 246, 0.5), inset 0 0 20px rgba(168, 85, 247, 0.2);
            }
            50% {
                box-shadow: 0 0 40px rgba(139, 92, 246, 0.8), inset 0 0 30px rgba(168, 85, 247, 0.4);
            }
        }
        
        @keyframes ender-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        @keyframes ender-teleport {
            0% { transform: translateX(-100%) skewX(-20deg); }
            100% { transform: translateX(100%) skewX(-20deg); }
        }
        
        /* Emerald - Esmeralda */
        @keyframes emerald-shine {
            0% { background-position: -200% center; }
            100% { background-position: 200% center; }
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
        
        /* =========================================
           TEMA ESPECÍFICO: {$template}
           ========================================= */
        {$this->getTemplateCSS($template)}
        
        /* PADRÃO DE FUNDO */
        {$this->getPatternCSS()}
        
        /* ESTILO DE CARDS */
        {$this->getCardCSS()}
        
        /* ESTILO DE BOTÕES */
        {$this->getButtonCSS()}
        
        /* EFEITOS ESPECIAIS */
        {$this->getEffectsCSS()}
        
        /* CSS PERSONALIZADO DO USUÁRIO */
        {$this->theme_data['custom_css']}
        
        /* UTILITÁRIOS GLOBAIS */
        html { scroll-behavior: smooth; }
        
        .product-card, .btn-primary, a {
            transition: all 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .product-card { animation: none !important; }
            body::before, body::after { display: none; }
        }
        
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        </style>
        ";
        
        return $css;
    }
    
    /**
     * CSS COMPLETO de cada template
     */
    private function getTemplateCSS($template) {
        $templates = [
            // ========== TEMAS MINECRAFT ==========
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
                    z-index: 1;
                }
                
                .product-card {
                    animation: lava-bubble 4s ease-in-out infinite;
                    background: linear-gradient(135deg, rgba(30, 10, 10, 0.8) 0%, rgba(20, 5, 5, 0.9) 100%) !important;
                    border: 2px solid rgba(220, 38, 38, 0.3) !important;
                }
                
                .product-card:hover {
                    animation: fire-glow 1s ease-in-out infinite;
                    border-color: #dc2626 !important;
                }
                
                button.bg-white {
                    background: linear-gradient(135deg, #dc2626, #f97316) !important;
                    color: white !important;
                    box-shadow: 0 10px 40px rgba(220, 38, 38, 0.5);
                    position: relative;
                    overflow: hidden;
                }
                
                button.bg-white::before {
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
                
                button.bg-white:hover::before {
                    width: 300px;
                    height: 300px;
                }
                
                h1, h2, h3 {
                    background: linear-gradient(135deg, #dc2626, #f97316, #fbbf24) !important;
                    -webkit-background-clip: text !important;
                    -webkit-text-fill-color: transparent !important;
                    filter: drop-shadow(0 0 10px rgba(249, 115, 22, 0.6));
                }
            ",
            
            'ender_kingdom' => "
                body { 
                    background: #000000 !important;
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
                    z-index: 1;
                }
                
                .product-card {
                    background: linear-gradient(135deg, rgba(20, 10, 30, 0.8) 0%, rgba(10, 5, 15, 0.9) 100%) !important;
                    border: 2px solid rgba(139, 92, 246, 0.3) !important;
                    animation: ender-float 4s ease-in-out infinite;
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
                
                .product-card:hover {
                    border-color: #8b5cf6 !important;
                    box-shadow: 
                        0 0 50px rgba(139, 92, 246, 0.8),
                        inset 0 0 30px rgba(168, 85, 247, 0.2) !important;
                    transform: translateY(-10px) scale(1.02) !important;
                }
                
                button.bg-white {
                    background: linear-gradient(135deg, #8b5cf6, #a855f7) !important;
                    color: white !important;
                    box-shadow: 0 10px 50px rgba(139, 92, 246, 0.6);
                    position: relative;
                    overflow: hidden;
                }
                
                button.bg-white::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
                    animation: ender-teleport 1.5s infinite;
                }
                
                button.bg-white:hover {
                    animation: ender-glow 1.5s ease-in-out infinite;
                }
                
                h1, h2, h3 {
                    background: linear-gradient(135deg, #8b5cf6, #a855f7, #c084fc) !important;
                    -webkit-background-clip: text !important;
                    -webkit-text-fill-color: transparent !important;
                    filter: drop-shadow(0 0 20px rgba(139, 92, 246, 0.8));
                }
            ",
            
            'emerald_valley' => "
                body { 
                    background: linear-gradient(135deg, #064e3b 0%, #022c22 100%) !important;
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
                    z-index: 1;
                }
                
                .product-card {
                    background: linear-gradient(135deg, rgba(20, 60, 50, 0.6) 0%, rgba(10, 40, 30, 0.8) 100%) !important;
                    border: 2px solid rgba(16, 185, 129, 0.3) !important;
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
                    background: linear-gradient(90deg, transparent, rgba(52, 211, 153, 0.8), transparent);
                    transition: left 0.5s;
                }
                
                .product-card:hover::before {
                    left: 100%;
                }
                
                .product-card:hover {
                    animation: village-pulse 2s ease-in-out infinite;
                    border-color: #10b981 !important;
                    box-shadow: 
                        0 0 40px rgba(16, 185, 129, 0.5),
                        inset 0 0 20px rgba(52, 211, 153, 0.1) !important;
                }
                
                button.bg-white {
                    background: linear-gradient(135deg, #10b981 0%, #34d399 25%, #6ee7b7 50%, #34d399 75%, #10b981 100%) !important;
                    background-size: 200% 100%;
                    color: white !important;
                    box-shadow: 0 10px 40px rgba(16, 185, 129, 0.4);
                }
                
                button.bg-white:hover {
                    animation: emerald-shine 2s linear infinite;
                }
                
                h1, h2, h3 {
                    background: linear-gradient(135deg, #10b981, #34d399, #6ee7b7) !important;
                    -webkit-background-clip: text !important;
                    -webkit-text-fill-color: transparent !important;
                    filter: drop-shadow(0 0 15px rgba(16, 185, 129, 0.6));
                }
            ",
            
            // ========== TEMAS MODERNOS ==========
            'neon_gaming' => "
                body { background: linear-gradient(135deg, #0a0a0a 0%, #1a0a2e 100%) !important; }
                .product-card { 
                    border: 1px solid rgba(139, 92, 246, 0.2) !important;
                }
                .product-card:hover {
                    border-color: var(--primary) !important;
                    box-shadow: 0 0 30px rgba(139, 92, 246, 0.4) !important;
                }
                h1, h2, h3 {
                    background: linear-gradient(135deg, var(--primary), var(--accent)) !important;
                    -webkit-background-clip: text !important;
                    -webkit-text-fill-color: transparent !important;
                }
            ",
            
            'dark_premium' => "
                body { background: #0f172a !important; }
                .product-card {
                    background: rgba(255, 255, 255, 0.02) !important;
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255, 255, 255, 0.05) !important;
                }
                .product-card:hover {
                    background: rgba(255, 255, 255, 0.05) !important;
                    border-color: rgba(59, 130, 246, 0.3) !important;
                }
            ",
            
            'fire_rage' => "
                body { 
                    background: linear-gradient(135deg, #0a0a0a 0%, #1a0505 100%) !important;
                    position: relative;
                }
                body::before {
                    content: '';
                    position: fixed;
                    inset: 0;
                    background: radial-gradient(circle at 50% 50%, rgba(239, 68, 68, 0.1) 0%, transparent 70%);
                    pointer-events: none;
                    z-index: 1;
                }
                .product-card {
                    background: linear-gradient(135deg, rgba(20, 5, 5, 0.8) 0%, rgba(10, 5, 5, 0.9) 100%) !important;
                    border: 1px solid rgba(239, 68, 68, 0.3) !important;
                }
                .product-card:hover {
                    border-color: var(--primary) !important;
                    box-shadow: 0 0 40px rgba(239, 68, 68, 0.6) !important;
                }
            ",
            
            'nature_green' => "
                body { background: linear-gradient(135deg, #064e3b 0%, #022c22 100%) !important; }
                .product-card {
                    background: rgba(16, 185, 129, 0.05) !important;
                    border: 1px solid rgba(16, 185, 129, 0.2) !important;
                }
                .product-card:hover {
                    background: rgba(16, 185, 129, 0.1) !important;
                    border-color: var(--primary) !important;
                    box-shadow: 0 0 30px rgba(16, 185, 129, 0.3) !important;
                }
            ",
            
            'ice_crystal' => "
                body { background: linear-gradient(135deg, #0c4a6e 0%, #082f49 100%) !important; }
                .product-card {
                    background: rgba(6, 182, 212, 0.05) !important;
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(6, 182, 212, 0.2) !important;
                }
                .product-card:hover {
                    border-color: var(--primary) !important;
                    box-shadow: 0 0 40px rgba(6, 182, 212, 0.4) !important;
                }
            ",
            
            'royal_gold' => "
                body { background: linear-gradient(135deg, #1c1917 0%, #0a0a0a 100%) !important; }
                .product-card {
                    background: linear-gradient(135deg, rgba(30, 27, 23, 0.8) 0%, rgba(10, 10, 10, 0.9) 100%) !important;
                    border: 1px solid rgba(245, 158, 11, 0.2) !important;
                }
                .product-card:hover {
                    border-color: var(--primary) !important;
                    box-shadow: 0 10px 40px rgba(245, 158, 11, 0.3) !important;
                }
                h1, h2, h3 {
                    background: linear-gradient(135deg, #f59e0b, #fbbf24) !important;
                    -webkit-background-clip: text !important;
                    -webkit-text-fill-color: transparent !important;
                }
            ",
            
            'minimal_light' => "
                body { background: #ffffff !important; color: #1f2937 !important; }
                .product-card {
                    background: white !important;
                    border: 2px solid #e5e7eb !important;
                }
                .product-card:hover {
                    border-color: var(--primary) !important;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1) !important;
                }
            ",
            
            'cyberpunk' => "
                body { background: #000000 !important; position: relative; }
                body::before {
                    content: '';
                    position: fixed;
                    inset: 0;
                    background: 
                        linear-gradient(0deg, transparent 0%, rgba(252, 238, 9, 0.05) 50%, transparent 100%),
                        linear-gradient(90deg, rgba(0, 240, 255, 0.05) 0%, transparent 50%, rgba(252, 238, 9, 0.05) 100%);
                    pointer-events: none;
                    z-index: 1;
                }
                .product-card {
                    background: linear-gradient(135deg, rgba(10, 10, 10, 0.9) 0%, rgba(20, 20, 20, 0.8) 100%) !important;
                    border: 2px solid transparent !important;
                }
                .product-card:hover {
                    border-image: linear-gradient(135deg, #fcee09, #00f0ff) 1 !important;
                }
                button.bg-white {
                    background: linear-gradient(135deg, #fcee09, #00f0ff) !important;
                    color: #000 !important;
                    font-weight: 900;
                }
            "
        ];
        
        return $templates[$template] ?? $templates['neon_gaming'];
    }
    
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
                    z-index: 2;
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
                    z-index: 2;
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
                    z-index: 2;
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
                    z-index: 2;
                }
            "
        ];
        
        return $patterns[$pattern] ?? '';
    }
    
    private function getCardCSS() {
        return "";
    }
    
    private function getButtonCSS() {
        return "";
    }
    
    private function getEffectsCSS() {
        return "";
    }
    
    public function generateJS() {
        $js = "<script>\n";
        
        if ($this->theme_data['show_particles']) {
            $js .= $this->getParticlesJS();
        }
        
        if (!empty($this->theme_data['custom_js'])) {
            $js .= $this->theme_data['custom_js'] . "\n";
        }
        
        $js .= "</script>";
        
        return $js;
    }
    
    private function getParticlesJS() {
        return "
        (function() {
            const canvas = document.createElement('canvas');
            canvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:3;opacity:0.3';
            document.body.appendChild(canvas);
            
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            
            const particles = [];
            for (let i = 0; i < 50; i++) {
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
    
    public function getFontLink() {
        $font = $this->getFontFamily();
        return "https://fonts.googleapis.com/css2?family={$font}:wght@400;600;700;900&display=swap";
    }
    
    public function renderHead() {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
        echo '<link href="' . $this->getFontLink() . '" rel="stylesheet">';
        echo $this->generateCSS();
    }
    
    public function renderScripts() {
        echo $this->generateJS();
    }
}
?>