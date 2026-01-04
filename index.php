<?php
ini_set('display_errors', 0);
if (file_exists('includes/db.php')) {
    require_once 'includes/db.php';
}

// Métricas Reais e Cache otimizado
$stats = [
    'lojas_ativas' => 0,
    'faturamento_total' => 0,
    'uptime' => 99.9,
    'total_clientes' => 0
];
$partners = [];
$feedbacks = [];

if (isset($pdo)) {
    try {
        $cacheKey = 'site_public_data_v3';
        
        if (isset($redis) && $redis->exists($cacheKey)) {
            $cachedData = json_decode($redis->get($cacheKey), true);
            $stats = $cachedData['stats'] ?? $stats;
            $partners = $cachedData['partners'] ?? [];
            $feedbacks = $cachedData['feedbacks'] ?? [];
        } else {
            // 1. MÉTRICAS REAIS DO BANCO
            
            // Total de lojas ativas
            $stmt = $pdo->query("SELECT COUNT(*) FROM stores WHERE status = 'active'");
            $stats['lojas_ativas'] = (int)$stmt->fetchColumn();
            
            // Total de clientes (todas as lojas cadastradas)
            $stmt = $pdo->query("SELECT COUNT(*) FROM stores");
            $stats['total_clientes'] = (int)$stmt->fetchColumn();
            
            // Faturamento total processado (transações completed)
            $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed'");
            $stats['faturamento_total'] = (float)$stmt->fetchColumn();
            
            // Uptime sempre 99.9% (pode ser dinâmico se tiver monitoramento)
            $stats['uptime'] = 99.9;
            
            // 2. PARCEIROS ATIVOS (logos das redes)
            $stmt = $pdo->query("
                SELECT nome, logo_url 
                FROM parceiros 
                WHERE status = 'active' 
                ORDER BY ordem ASC
            ");
            $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 3. FEEDBACKS APROVADOS
            $stmt = $pdo->query("
                SELECT 
                    author as nome, 
                    role as cargo, 
                    content as texto, 
                    rating as estrelas,
                    avatar_url
                FROM feedbacks 
                WHERE is_approved = 1 
                ORDER BY created_at DESC 
                LIMIT 6
            ");
            $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache de 5 minutos (300 segundos)
            if (isset($redis)) {
                $redis->setex($cacheKey, 300, json_encode([
                    'stats' => $stats,
                    'partners' => $partners,
                    'feedbacks' => $feedbacks
                ]));
            }
        }
    } catch (Exception $e) {
        error_log("Index Error: " . $e->getMessage());
        // Mantém valores default em caso de erro
    }
}

// Função auxiliar para formatar números
function formatNumber($num) {
    if ($num >= 1000000) {
        return number_format($num / 1000000, 1, ',', '.') . 'M';
    } elseif ($num >= 1000) {
        return number_format($num / 1000, 0, ',', '.') . 'K';
    }
    return number_format($num, 0, ',', '.');
}

// Função para formatar dinheiro
function formatMoney($amount) {
    if ($amount >= 1000000) {
        return 'R$ ' . number_format($amount / 1000000, 1, ',', '.') . 'M';
    } elseif ($amount >= 1000) {
        return 'R$ ' . number_format($amount / 1000, 0, ',', '.') . 'K';
    }
    return 'R$ ' . number_format($amount, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SplitStore | Sistema de Lojas Premium</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html { 
            scroll-behavior: smooth; 
            scroll-padding-top: 100px;
        }
        
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #000;
            color: #fff;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Partículas de fundo */
        #particles-js {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: 1;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        .content-wrapper {
            position: relative;
            z-index: 10;
        }

        /* Navbar Premium */
        .navbar {
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .navbar.scrolled {
            background: rgba(0, 0, 0, 0.95);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }

        /* Hero Section */
        .hero-title {
            font-size: clamp(2.5rem, 8vw, 6rem);
            line-height: 0.9;
            letter-spacing: -0.05em;
            font-weight: 900;
            background: linear-gradient(135deg, #fff 0%, #999 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-title-red {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Glass Card Premium */
        .glass-premium {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.02) 0%, rgba(255, 255, 255, 0.01) 100%);
            backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        /* Hover Effects */
        .hover-lift {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .hover-lift:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(220, 38, 38, 0.15);
        }

        /* Glow Effect */
        .glow-red {
            box-shadow: 0 0 40px rgba(220, 38, 38, 0.3);
        }

        .glow-red:hover {
            box-shadow: 0 0 60px rgba(220, 38, 38, 0.5);
        }

        /* Gradiente de Fundo */
        .gradient-radial {
            background: radial-gradient(circle at center, rgba(220, 38, 38, 0.1) 0%, transparent 70%);
        }

        /* Animações */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .float-animation {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes pulse-glow {
            0%, 100% { opacity: 0.8; }
            50% { opacity: 0.4; }
        }

        .pulse-glow {
            animation: pulse-glow 3s ease-in-out infinite;
        }

        /* FAQ Accordion */
        .faq-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
            opacity: 0;
        }

        .faq-item.active .faq-content {
            max-height: 300px;
            opacity: 1;
            padding-top: 1rem;
        }

        .faq-item.active .faq-icon {
            transform: rotate(180deg);
            color: #ef4444;
        }

        .faq-icon {
            transition: transform 0.3s ease, color 0.3s ease;
        }

        /* Scrollbar Custom */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #000;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #ef4444 0%, #991b1b 100%);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #ef4444;
        }

        /* Badge Premium */
        .badge-premium {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.2) 0%, rgba(127, 29, 29, 0.1) 100%);
            border: 1px solid rgba(220, 38, 38, 0.3);
            backdrop-filter: blur(10px);
        }

        /* Contador de Estrelas */
        .star-rating {
            display: flex;
            gap: 2px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
        }

        /* Loading State */
        .skeleton {
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.02) 0%, rgba(255, 255, 255, 0.05) 50%, rgba(255, 255, 255, 0.02) 100%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s ease-in-out infinite;
        }

        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>

    <div id="particles-js"></div>

    <div class="content-wrapper">
        
        <!-- NAVBAR PREMIUM -->
        <nav class="navbar fixed top-0 left-0 right-0 z-50">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="flex justify-between items-center h-20">
                    
                    <!-- Logo -->
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-red-600 to-red-900 rounded-xl flex items-center justify-center font-black shadow-lg shadow-red-900/40">
                            S
                        </div>
                        <span class="text-xl font-black tracking-tighter uppercase">
                            Split<span class="text-red-600">Store</span>
                        </span>
                    </div>

                    <!-- Menu Desktop -->
                    <div class="hidden md:flex items-center gap-10">
                        <a href="#recursos" class="text-zinc-400 hover:text-white text-xs font-bold uppercase tracking-wider transition-colors">
                            Recursos
                        </a>
                        <a href="#parceiros" class="text-zinc-400 hover:text-white text-xs font-bold uppercase tracking-wider transition-colors">
                            Parceiros
                        </a>
                        <a href="#planos" class="text-zinc-400 hover:text-white text-xs font-bold uppercase tracking-wider transition-colors">
                            Planos
                        </a>
                        <a href="#depoimentos" class="text-zinc-400 hover:text-white text-xs font-bold uppercase tracking-wider transition-colors">
                            Depoimentos
                        </a>
                        <a href="#faq" class="text-zinc-400 hover:text-white text-xs font-bold uppercase tracking-wider transition-colors">
                            FAQ
                        </a>
                    </div>

                    <!-- CTA Navbar -->
                    <div class="flex items-center gap-4">
                        <a href="admin/login.php" class="hidden md:block text-zinc-400 hover:text-white text-xs font-bold uppercase tracking-wider transition-colors">
                            Login
                        </a>
                        <a href="#planos" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2.5 rounded-xl font-black text-xs uppercase tracking-wider transition-all hover:scale-105 active:scale-95 glow-red">
                            Começar Agora
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- HERO SECTION PREMIUM -->
        <section class="relative min-h-screen flex items-center pt-20">
            <!-- Gradiente de fundo -->
            <div class="absolute inset-0 gradient-radial"></div>
            
            <div class="relative max-w-7xl mx-auto px-6 lg:px-8 py-24">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    
                    <!-- Conteúdo -->
                    <div data-aos="fade-right" data-aos-duration="1000">
                        
                        <!-- Badge -->
                        <div class="inline-flex items-center gap-2 badge-premium px-4 py-2 rounded-full mb-8">
                            <div class="w-2 h-2 bg-red-500 rounded-full pulse-glow"></div>
                            <span class="text-red-500 text-xs font-black uppercase tracking-wider">
                                Sistema V3.0 • Nova Geração
                            </span>
                        </div>

                        <!-- Título Principal -->
                        <h1 class="hero-title mb-6">
                            A Revolução<br>
                            das Lojas<br>
                            <span class="hero-title-red">Minecraft</span>
                        </h1>

                        <!-- Subtítulo -->
                        <p class="text-zinc-400 text-lg leading-relaxed mb-10 max-w-xl font-light">
                            Sistema completo de vendas com entrega automatizada, 
                            checkout premium e sincronização em tempo real. 
                            <span class="text-white font-semibold">Transforme seu servidor em uma máquina de vendas.</span>
                        </p>

                        <!-- CTAs -->
                        <div class="flex flex-col sm:flex-row gap-4 mb-12">
                            <a href="#planos" class="group bg-red-600 hover:bg-red-700 text-white px-8 py-4 rounded-2xl font-black text-sm uppercase tracking-wider glow-red transition-all hover:scale-105 active:scale-95 flex items-center justify-center gap-3">
                                Criar Minha Loja
                                <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                            </a>
                            <a href="#recursos" class="glass-premium hover:border-red-600/30 text-white px-8 py-4 rounded-2xl font-bold text-sm uppercase tracking-wider transition-all hover:scale-105 flex items-center justify-center gap-3">
                                <i data-lucide="play-circle" class="w-4 h-4"></i>
                                Ver Demonstração
                            </a>
                        </div>

                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-8">
                            <div>
                                <div class="text-3xl font-black text-white mb-1"><?= formatNumber($stats['lojas_ativas']) ?>+</div>
                                <div class="text-xs text-zinc-600 font-bold uppercase tracking-wider">Lojas Ativas</div>
                            </div>
                            <div>
                                <div class="text-3xl font-black text-white mb-1"><?= formatMoney($stats['faturamento_total']) ?>+</div>
                                <div class="text-xs text-zinc-600 font-bold uppercase tracking-wider">Processado</div>
                            </div>
                            <div>
                                <div class="text-3xl font-black text-white mb-1"><?= $stats['uptime'] ?>%</div>
                                <div class="text-xs text-zinc-600 font-bold uppercase tracking-wider">Uptime</div>
                            </div>
                        </div>
                    </div>

                    <!-- Visual/Preview -->
                    <div class="relative" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
                        <div class="absolute -inset-20 bg-red-600/20 blur-[120px] rounded-full pulse-glow"></div>
                        
                        <!-- Mockup -->
                        <div class="relative glass-premium rounded-3xl p-8 border-2 border-white/10 hover:border-red-600/30 transition-all float-animation">
                            <div class="aspect-video bg-gradient-to-br from-zinc-900 to-black rounded-2xl flex items-center justify-center border border-white/5">
                                <div class="text-center">
                                    <i data-lucide="shopping-cart" class="w-20 h-20 text-red-600/30 mx-auto mb-4"></i>
                                    <span class="text-zinc-700 font-black text-2xl uppercase tracking-wider">Interface Premium</span>
                                </div>
                            </div>
                            
                            <!-- Features Cards Flutuantes -->
                            <div class="absolute -left-4 top-1/4 glass-premium px-4 py-3 rounded-xl border border-red-600/20 shadow-xl">
                                <div class="flex items-center gap-3">
                                    <i data-lucide="zap" class="w-5 h-5 text-red-600"></i>
                                    <div>
                                        <div class="text-xs font-black text-white">Entrega Instantânea</div>
                                        <div class="text-[10px] text-zinc-600">Em < 3 segundos</div>
                                    </div>
                                </div>
                            </div>

                            <div class="absolute -right-4 bottom-1/4 glass-premium px-4 py-3 rounded-xl border border-red-600/20 shadow-xl">
                                <div class="flex items-center gap-3">
                                    <i data-lucide="shield-check" class="w-5 h-5 text-green-500"></i>
                                    <div>
                                        <div class="text-xs font-black text-white">100% Seguro</div>
                                        <div class="text-[10px] text-zinc-600">Anti-fraude</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- RECURSOS PREMIUM -->
        <section id="recursos" class="relative py-32">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                
                <!-- Header -->
                <div class="text-center mb-20" data-aos="fade-up">
                    <div class="inline-block text-red-600 text-xs font-black uppercase tracking-[0.3em] mb-4 bg-red-600/10 px-4 py-2 rounded-full">
                        Tecnologia de Ponta
                    </div>
                    <h2 class="text-5xl font-black uppercase tracking-tighter mb-4">
                        Recursos que <span class="text-red-600">Dominam</span>
                    </h2>
                    <p class="text-zinc-500 text-lg max-w-2xl mx-auto font-light">
                        Cada detalhe foi pensado para maximizar suas vendas e experiência dos jogadores
                    </p>
                </div>

                <!-- Grid de Recursos -->
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php
                    $recursos = [
                        [
                            'icon' => 'zap',
                            'title' => 'Sincronização Real-Time',
                            'desc' => 'Entrega automática via plugin com sincronização instantânea entre loja e servidor.',
                            'color' => 'red'
                        ],
                        [
                            'icon' => 'credit-card',
                            'title' => 'Checkout Otimizado',
                            'desc' => 'Processo de compra em 2 cliques com PIX, cartão e boleto integrados.',
                            'color' => 'blue'
                        ],
                        [
                            'icon' => 'bar-chart-3',
                            'title' => 'Analytics Avançado',
                            'desc' => 'Dashboard completo com métricas de vendas, conversão e comportamento.',
                            'color' => 'purple'
                        ],
                        [
                            'icon' => 'shield-check',
                            'title' => 'Anti-Fraude Nativo',
                            'desc' => 'Proteção avançada contra chargebacks e transações fraudulentas.',
                            'color' => 'green'
                        ],
                        [
                            'icon' => 'palette',
                            'title' => 'Design Customizável',
                            'desc' => 'Interface moderna totalmente personalizável com sua identidade visual.',
                            'color' => 'pink'
                        ],
                        [
                            'icon' => 'headset',
                            'title' => 'Suporte Especializado',
                            'desc' => 'Time de especialistas disponível para resolver qualquer problema.',
                            'color' => 'yellow'
                        ]
                    ];
                    
                    $colors = [
                        'red' => 'red-600',
                        'blue' => 'blue-600',
                        'purple' => 'purple-600',
                        'green' => 'green-600',
                        'pink' => 'pink-600',
                        'yellow' => 'yellow-600'
                    ];

                    foreach ($recursos as $i => $r):
                        $color = $colors[$r['color']];
                    ?>
                        <div class="group" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
                            <div class="glass-premium p-8 rounded-3xl hover-lift h-full border border-white/5 hover:border-<?= $color ?>/30">
                                
                                <!-- Icon -->
                                <div class="w-14 h-14 bg-<?= $color ?>/10 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                                    <i data-lucide="<?= $r['icon'] ?>" class="w-7 h-7 text-<?= $color ?>"></i>
                                </div>

                                <!-- Content -->
                                <h3 class="text-xl font-black uppercase mb-3 tracking-tight"><?= $r['title'] ?></h3>
                                <p class="text-zinc-500 leading-relaxed text-sm"><?= $r['desc'] ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- PARCEIROS -->
        <section id="parceiros" class="relative py-32 bg-zinc-950/50">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                
                <div class="text-center mb-20" data-aos="fade-up">
                    <div class="inline-block text-red-600 text-xs font-black uppercase tracking-[0.3em] mb-4 bg-red-600/10 px-4 py-2 rounded-full">
                        Confiança
                    </div>
                    <h2 class="text-5xl font-black uppercase tracking-tighter mb-4">
                        Redes que <span class="text-red-600">Confiam</span>
                    </h2>
                    <p class="text-zinc-500 text-lg">
                        Servidores parceiros que utilizam nossa tecnologia
                    </p>
                </div>

                <?php if (!empty($partners)): ?>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
                        <?php foreach ($partners as $i => $p): ?>
                            <div class="group" data-aos="zoom-in" data-aos-delay="<?= $i * 50 ?>">
                                <div class="glass-premium aspect-square rounded-2xl p-6 flex flex-col items-center justify-center grayscale opacity-40 hover:grayscale-0 hover:opacity-100 transition-all duration-500 hover-lift border border-white/5 hover:border-red-600/30">
                                    <img src="<?= htmlspecialchars($p['logo_url']) ?>" 
                                         alt="<?= htmlspecialchars($p['nome']) ?>" 
                                         class="w-20 h-20 object-contain mb-4"
                                         loading="lazy">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-600 group-hover:text-red-600 transition-colors text-center">
                                        <?= htmlspecialchars($p['nome']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- CTA Seja Parceiro -->
                    <div class="text-center mt-16" data-aos="fade-up">
                        <p class="text-zinc-600 text-sm mb-4">Sua rede também pode estar aqui</p>
                        <a href="#planos" class="text-red-600 font-black uppercase text-xs tracking-widest hover:text-red-500 transition-colors inline-flex items-center gap-2">
                            Seja um Parceiro
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Estado vazio -->
                    <div class="glass-premium rounded-3xl p-16 text-center border border-white/5">
                        <i data-lucide="users" class="w-16 h-16 text-zinc-800 mx-auto mb-6"></i>
                        <h3 class="text-xl font-black uppercase text-zinc-700 mb-3">Primeiros Parceiros em Breve</h3>
                        <p class="text-zinc-600 text-sm max-w-md mx-auto">
                            Estamos selecionando os primeiros servidores parceiros. 
                            <a href="#planos" class="text-red-600 hover:underline">Seja pioneiro!</a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- PLANOS PREMIUM -->
        <section id="planos" class="relative py-32">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                
                <div class="text-center mb-20" data-aos="fade-up">
                    <div class="inline-block text-red-600 text-xs font-black uppercase tracking-[0.3em] mb-4 bg-red-600/10 px-4 py-2 rounded-full">
                        Investimento
                    </div>
                    <h2 class="text-5xl font-black uppercase tracking-tighter mb-4">
                        Escolha seu <span class="text-red-600">Plano</span>
                    </h2>
                    <p class="text-zinc-500 text-lg max-w-2xl mx-auto">
                        Transparência total. Sem taxas ocultas. Cancele quando quiser.
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto">
                    <?php
                    $planos = [
                        [
                            'nome' => 'Starter',
                            'preco' => '14,99',
                            'desc' => 'Perfeito para começar',
                            'destaque' => false,
                            'features' => [
                                '1 Servidor Minecraft',
                                'Checkout Responsivo',
                                'Suporte via Ticket',
                                'Plugin de Entrega',
                                'Estatísticas Básicas'
                            ]
                        ],
                        [
                            'nome' => 'Enterprise',
                            'preco' => '25,99',
                            'desc' => 'Para redes sérias',
                            'destaque' => true,
                            'features' => [
                                '5 Servidores',
                                'Checkout Customizável',
                                'Suporte Prioritário 24/7',
                                'Analytics Avançado',
                                'Sem Taxas por Venda',
                                'API de Integração'
                            ]
                        ],
                        [
                            'nome' => 'Gerencial',
                            'preco' => '39,99',
                            'desc' => 'Soluções enterprise',
                            'destaque' => false,
                            'features' => [
                                'Servidores Ilimitados',
                                'Whitelabel Completo',
                                'Gerente de Contas',
                                'Backup em Tempo Real',
                                'Integrações Custom',
                                'Consultoria Mensal'
                            ]
                        ]
                    ];

                    foreach ($planos as $i => $p):
                    ?>
                        <div class="group relative" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
                            
                            <?php if ($p['destaque']): ?>
                                <!-- Badge Popular -->
                                <div class="absolute -top-4 left-1/2 -translate-x-1/2 z-20">
                                    <div class="bg-red-600 text-white text-[10px] font-black uppercase px-6 py-2 rounded-full shadow-lg shadow-red-600/50">
                                        Mais Popular
                                    </div>
                                </div>
                                
                                <!-- Glow Effect -->
                                <div class="absolute -inset-1 bg-red-600/20 rounded-[2rem] blur-2xl"></div>
                            <?php endif; ?>

                            <div class="relative glass-premium p-10 rounded-[2rem] border <?= $p['destaque'] ? 'border-red-600/50' : 'border-white/5' ?> h-full flex flex-col hover-lift">
                                
                                <!-- Header -->
                                <div class="mb-8">
                                    <h3 class="text-zinc-400 text-xs font-black uppercase tracking-[0.3em] mb-2">
                                        <?= $p['nome'] ?>
                                    </h3>
                                    <p class="text-zinc-600 text-sm mb-6"><?= $p['desc'] ?></p>
                                    
                                    <!-- Preço -->
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-zinc-500 text-xl">R$</span>
                                        <span class="text-6xl font-black tracking-tighter"><?= $p['preco'] ?></span>
                                        <span class="text-zinc-600 text-sm">/mês</span>
                                    </div>
                                </div>

                                <!-- Features -->
                                <ul class="space-y-4 mb-10 flex-1">
                                    <?php foreach ($p['features'] as $feature): ?>
                                        <li class="flex items-center gap-3 text-zinc-400 text-sm">
                                            <div class="w-5 h-5 bg-red-600/10 rounded-full flex items-center justify-center flex-shrink-0">
                                                <i data-lucide="check" class="w-3 h-3 text-red-600"></i>
                                            </div>
                                            <?= $feature ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>

                                <!-- CTA Button -->
                                <a href="checkout.php?plan=<?= strtolower($p['nome']) ?>" 
                                   class="block w-full py-4 rounded-xl font-black text-sm uppercase tracking-wider text-center transition-all <?= $p['destaque'] ? 'bg-red-600 text-white hover:bg-red-700 glow-red' : 'bg-white text-black hover:bg-zinc-200' ?>">
                                    Assinar Agora
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Info Adicional -->
                <div class="text-center mt-16" data-aos="fade-up">
                    <p class="text-zinc-600 text-sm mb-4">
                        Todos os planos incluem 7 dias de garantia. Cancele quando quiser.
                    </p>
                    <div class="flex items-center justify-center gap-8 text-xs text-zinc-700 font-bold uppercase tracking-wider">
                        <div class="flex items-center gap-2">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                            Pagamento Seguro
                        </div>
                        <div class="flex items-center gap-2">
                            <i data-lucide="zap" class="w-4 h-4"></i>
                            Ativação Instantânea
                        </div>
                        <div class="flex items-center gap-2">
                            <i data-lucide="headset" class="w-4 h-4"></i>
                            Suporte 24/7
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- DEPOIMENTOS -->
        <section id="depoimentos" class="relative py-32 bg-zinc-950/50">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                
                <div class="text-center mb-20" data-aos="fade-up">
                    <div class="inline-block text-red-600 text-xs font-black uppercase tracking-[0.3em] mb-4 bg-red-600/10 px-4 py-2 rounded-full">
                        Depoimentos
                    </div>
                    <h2 class="text-5xl font-black uppercase tracking-tighter mb-4">
                        Quem Usa, <span class="text-red-600">Aprova</span>
                    </h2>
                    <p class="text-zinc-500 text-lg max-w-2xl mx-auto">
                        Veja o que nossos clientes dizem sobre a transformação em seus servidores
                    </p>
                </div>

                <?php if (!empty($feedbacks)): ?>
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                        <?php foreach ($feedbacks as $i => $f): ?>
                            <div class="group" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
                                <div class="glass-premium p-8 rounded-3xl border border-white/5 hover:border-red-600/20 h-full flex flex-col hover-lift">
                                    
                                    <!-- Estrelas -->
                                    <div class="star-rating mb-6">
                                        <?php for ($s = 0; $s < (int)$f['estrelas']; $s++): ?>
                                            <i data-lucide="star" class="w-4 h-4 fill-red-600 text-red-600"></i>
                                        <?php endfor; ?>
                                        <?php for ($s = (int)$f['estrelas']; $s < 5; $s++): ?>
                                            <i data-lucide="star" class="w-4 h-4 text-zinc-800"></i>
                                        <?php endfor; ?>
                                    </div>

                                    <!-- Depoimento -->
                                    <blockquote class="text-zinc-400 leading-relaxed mb-8 flex-1 italic">
                                        "<?= htmlspecialchars($f['texto']) ?>"
                                    </blockquote>

                                    <!-- Autor -->
                                    <div class="flex items-center gap-4 pt-6 border-t border-white/5">
                                        <?php if (!empty($f['avatar_url'])): ?>
                                            <img src="<?= htmlspecialchars($f['avatar_url']) ?>" 
                                                 alt="<?= htmlspecialchars($f['nome']) ?>"
                                                 class="w-12 h-12 rounded-full object-cover border-2 border-red-600/20"
                                                 loading="lazy">
                                        <?php else: ?>
                                            <div class="w-12 h-12 bg-gradient-to-br from-red-600 to-red-900 rounded-full flex items-center justify-center font-black text-white border-2 border-red-600/20">
                                                <?= strtoupper(substr($f['nome'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div>
                                            <h4 class="font-black uppercase text-sm tracking-tight">
                                                <?= htmlspecialchars($f['nome']) ?>
                                            </h4>
                                            <p class="text-xs text-zinc-600 font-bold uppercase tracking-wider">
                                                <?= htmlspecialchars($f['cargo']) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Estado vazio -->
                    <div class="glass-premium rounded-3xl p-16 text-center border border-white/5">
                        <i data-lucide="message-square" class="w-16 h-16 text-zinc-800 mx-auto mb-6"></i>
                        <h3 class="text-xl font-black uppercase text-zinc-700 mb-3">Primeiros Depoimentos em Breve</h3>
                        <p class="text-zinc-600 text-sm max-w-md mx-auto">
                            Nossos primeiros clientes estão testando o sistema. 
                            Os depoimentos serão publicados em breve!
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- FAQ PREMIUM -->
        <section id="faq" class="relative py-32">
            <div class="max-w-4xl mx-auto px-6 lg:px-8">
                
                <div class="text-center mb-20" data-aos="fade-up">
                    <div class="inline-block text-red-600 text-xs font-black uppercase tracking-[0.3em] mb-4 bg-red-600/10 px-4 py-2 rounded-full">
                        Dúvidas Frequentes
                    </div>
                    <h2 class="text-5xl font-black uppercase tracking-tighter mb-4">
                        Perguntas <span class="text-red-600">& Respostas</span>
                    </h2>
                </div>

                <div class="space-y-4">
                    <?php
                    $faqs = [
                        [
                            'q' => 'Como funciona a entrega automatizada dos produtos?',
                            'a' => 'Assim que o pagamento é confirmado pela gateway (geralmente em segundos no PIX), nosso plugin sincroniza automaticamente com seu servidor e executa os comandos configurados. Todo o processo é instantâneo e não requer intervenção manual.'
                        ],
                        [
                            'q' => 'Quais meios de pagamento são aceitos?',
                            'a' => 'Aceitamos PIX (aprovação instantânea), Cartão de Crédito (parcelamento em até 12x) e Boleto Bancário através das principais gateways do mercado como Mercado Pago, PagSeguro e Stripe.'
                        ],
                        [
                            'q' => 'O sistema possui proteção contra fraudes e chargebacks?',
                            'a' => 'Sim! Incluímos sistema nativo de detecção de fraudes, análise de risco em tempo real, verificação de CPF e integração com ferramentas anti-fraude para minimizar chargebacks e proteger sua operação.'
                        ],
                        [
                            'q' => 'Posso personalizar o design da minha loja?',
                            'a' => 'Absolutamente! Nos planos Pro e Ultra você tem acesso total à customização de cores, logos, banners e até código CSS/JS personalizado para deixar a loja com a cara do seu servidor.'
                        ],
                        [
                            'q' => 'Como funciona o suporte técnico?',
                            'a' => 'Oferecemos suporte via ticket 24/7 no plano Starter, suporte prioritário com resposta em até 2h no plano Enterprise, e gerente de contas dedicado + WhatsApp direto no plano Gerencial.'
                        ],
                        [
                            'q' => 'Existe período de fidelidade ou multa de cancelamento?',
                            'a' => 'Não! Todos os nossos planos são mensais sem fidelidade. Você pode cancelar quando quiser sem qualquer multa ou taxa adicional. Acreditamos que você vai permanecer pela qualidade, não por obrigação.'
                        ]
                    ];

                    foreach ($faqs as $index => $faq):
                    ?>
                        <div class="faq-item glass-premium p-6 rounded-2xl border border-white/5 hover:border-red-600/30 cursor-pointer transition-all group" 
                             onclick="this.classList.toggle('active')"
                             data-aos="fade-up" 
                             data-aos-delay="<?= $index * 50 ?>">
                            
                            <div class="flex justify-between items-center gap-4">
                                <h3 class="text-base font-bold uppercase tracking-tight group-hover:text-red-600 transition-colors">
                                    <?= $faq['q'] ?>
                                </h3>
                                <i data-lucide="chevron-down" class="faq-icon w-5 h-5 text-zinc-500 flex-shrink-0"></i>
                            </div>
                            
                            <div class="faq-content">
                                <p class="text-zinc-500 leading-relaxed"><?= $faq['a'] ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- CTA Extra -->
                <div class="text-center mt-16" data-aos="fade-up">
                    <p class="text-zinc-600 mb-4">Ainda tem dúvidas?</p>
                    <a href="#" class="text-red-600 font-bold uppercase text-sm tracking-wider hover:text-red-500 transition-colors inline-flex items-center gap-2">
                        Falar com Especialista
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- CTA FINAL -->
        <section class="relative py-32">
            <div class="max-w-5xl mx-auto px-6 lg:px-8">
                <div class="glass-premium p-16 rounded-[3rem] border border-red-600/20 text-center relative overflow-hidden" data-aos="zoom-in">
                    
                    <!-- Background Pattern -->
                    <div class="absolute inset-0 opacity-5">
                        <div class="absolute inset-0" style="background-image: radial-gradient(circle, #ef4444 1px, transparent 1px); background-size: 40px 40px;"></div>
                    </div>

                    <!-- Content -->
                    <div class="relative z-10">
                        <h2 class="text-5xl font-black uppercase tracking-tighter mb-6">
                            Pronto para <span class="text-red-600">Decolar?</span>
                        </h2>
                        <p class="text-zinc-400 text-lg mb-10 max-w-2xl mx-auto">
                            Junte-se a centenas de servidores que já transformaram suas vendas com o SplitStore. 
                            Comece gratuitamente por 7 dias.
                        </p>
                        
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <a href="#planos" class="bg-red-600 hover:bg-red-700 text-white px-10 py-5 rounded-2xl font-black uppercase tracking-wider glow-red transition-all hover:scale-105">
                                Começar Agora - Grátis
                            </a>
                            <a href="#recursos" class="glass-premium border-white/10 hover:border-red-600/30 text-white px-10 py-5 rounded-2xl font-bold uppercase tracking-wider transition-all">
                                Ver Mais Recursos
                            </a>
                        </div>

                        <!-- Trust Badges -->
                        <div class="flex items-center justify-center gap-8 mt-12 text-xs text-zinc-600 font-bold uppercase tracking-wider">
                            <div class="flex items-center gap-2">
                                <i data-lucide="shield-check" class="w-4 h-4 text-green-500"></i>
                                SSL Seguro
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="clock" class="w-4 h-4 text-blue-500"></i>
                                Uptime <?= $stats['uptime'] ?>%
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="users" class="w-4 h-4 text-purple-500"></i>
                                <?= $stats['total_clientes'] ?>+ Clientes
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FOOTER PREMIUM -->
        <footer class="relative border-t border-white/5 bg-black py-16">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                
                <!-- Footer Grid -->
                <div class="grid md:grid-cols-2 lg:grid-cols-5 gap-12 mb-16">
                    
                    <!-- Brand -->
                    <div class="lg:col-span-2">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 bg-gradient-to-br from-red-600 to-red-900 rounded-xl flex items-center justify-center font-black shadow-lg">
                                S
                            </div>
                            <span class="text-xl font-black tracking-tighter uppercase">
                                Split<span class="text-red-600">Store</span>
                            </span>
                        </div>
                        <p class="text-zinc-500 leading-relaxed mb-6 max-w-sm">
                            A plataforma mais completa para vendas em servidores Minecraft. 
                            Tecnologia brasileira, suporte em português.
                        </p>
                        
                        <!-- Métricas em Tempo Real -->
                        <div class="glass-premium p-4 rounded-2xl mb-6 border border-white/5">
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <div class="text-lg font-black text-red-600"><?= $stats['lojas_ativas'] ?></div>
                                    <div class="text-[9px] text-zinc-700 font-bold uppercase tracking-wider">Lojas Ativas</div>
                                </div>
                                <div>
                                    <div class="text-lg font-black text-green-600"><?= formatMoney($stats['faturamento_total']) ?></div>
                                    <div class="text-[9px] text-zinc-700 font-bold uppercase tracking-wider">Processado</div>
                                </div>
                                <div>
                                    <div class="text-lg font-black text-blue-600"><?= $stats['uptime'] ?>%</div>
                                    <div class="text-[9px] text-zinc-700 font-bold uppercase tracking-wider">Uptime</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Social -->
                        <div class="flex gap-3">
                            <a href="#" class="w-10 h-10 glass-premium rounded-xl flex items-center justify-center hover:border-red-600/50 transition-all text-zinc-400 hover:text-red-600">
                                <i data-lucide="instagram" class="w-4 h-4"></i>
                            </a>
                            <a href="#" class="w-10 h-10 glass-premium rounded-xl flex items-center justify-center hover:border-red-600/50 transition-all text-zinc-400 hover:text-red-600">
                                <i data-lucide="twitter" class="w-4 h-4"></i>
                            </a>
                            <a href="#" class="w-10 h-10 glass-premium rounded-xl flex items-center justify-center hover:border-red-600/50 transition-all text-zinc-400 hover:text-red-600">
                                <i data-lucide="youtube" class="w-4 h-4"></i>
                            </a>
                            <a href="#" class="w-10 h-10 glass-premium rounded-xl flex items-center justify-center hover:border-red-600/50 transition-all text-zinc-400 hover:text-red-600">
                                <i data-lucide="message-circle" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Links -->
                    <div>
                        <h4 class="text-white text-xs font-black uppercase tracking-[0.3em] mb-6">Produto</h4>
                        <ul class="space-y-3 text-sm text-zinc-500">
                            <li><a href="#recursos" class="hover:text-white transition-colors">Recursos</a></li>
                            <li><a href="#planos" class="hover:text-white transition-colors">Planos</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Integrações</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Atualizações</a></li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="text-white text-xs font-black uppercase tracking-[0.3em] mb-6">Empresa</h4>
                        <ul class="space-y-3 text-sm text-zinc-500">
                            <li><a href="#" class="hover:text-white transition-colors">Sobre Nós</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Blog</a></li>
                            <li><a href="#parceiros" class="hover:text-white transition-colors">Parceiros</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Carreiras</a></li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="text-white text-xs font-black uppercase tracking-[0.3em] mb-6">Suporte</h4>
                        <ul class="space-y-3 text-sm text-zinc-500">
                            <li><a href="#" class="hover:text-white transition-colors">Documentação</a></li>
                            <li><a href="#faq" class="hover:text-white transition-colors">FAQ</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Status</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Contato</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Footer Bottom -->
                <div class="pt-8 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-6">
                    <p class="text-zinc-700 text-xs font-bold uppercase tracking-wider">
                        © 2026 SplitStore - Grupo Split. Todos os direitos reservados.
                    </p>
                    
                    <div class="flex gap-8 text-xs text-zinc-700 font-bold uppercase tracking-wider">
                        <a href="#" class="hover:text-zinc-500 transition-colors">Termos de Uso</a>
                        <a href="#" class="hover:text-zinc-500 transition-colors">Privacidade</a>
                        <a href="#" class="hover:text-zinc-500 transition-colors">Cookies</a>
                    </div>
                </div>
            </div>
        </footer>

    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Inicializa Lucide Icons
        lucide.createIcons();

        // Inicializa AOS (Animate on Scroll)
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100,
            easing: 'ease-out-cubic'
        });

        // Navbar Scroll Effect
        const navbar = document.querySelector('.navbar');
        let lastScroll = 0;

        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 100) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
            
            lastScroll = currentScroll;
        });

        // Particles.js Configuration
        particlesJS("particles-js", {
            particles: {
                number: {
                    value: 80,
                    density: {
                        enable: true,
                        value_area: 800
                    }
                },
                color: {
                    value: "#ef4444"
                },
                shape: {
                    type: "circle"
                },
                opacity: {
                    value: 0.15,
                    random: true,
                    anim: {
                        enable: true,
                        speed: 1,
                        opacity_min: 0.05,
                        sync: false
                    }
                },
                size: {
                    value: 3,
                    random: true,
                    anim: {
                        enable: true,
                        speed: 2,
                        size_min: 0.5,
                        sync: false
                    }
                },
                line_linked: {
                    enable: true,
                    distance: 150,
                    color: "#ef4444",
                    opacity: 0.08,
                    width: 1
                },
                move: {
                    enable: true,
                    speed: 1,
                    direction: "none",
                    random: true,
                    straight: false,
                    out_mode: "out",
                    bounce: false
                }
            },
            interactivity: {
                detect_on: "canvas",
                events: {
                    onhover: {
                        enable: true,
                        mode: "grab"
                    },
                    resize: true
                },
                modes: {
                    grab: {
                        distance: 140,
                        line_linked: {
                            opacity: 0.3
                        }
                    }
                }
            },
            retina_detect: true
        });

        // Smooth Scroll para links internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Re-inicializa Lucide após carregar conteúdo dinâmico
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
        });
    </script>
</body>
</html>