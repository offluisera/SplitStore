<?php
/**
 * ============================================
 * SPLITSTORE - HOME V3.0 ULTRA
 * ============================================
 * Design moderno Dark/Red com produtos e notícias
 */

session_start();
require_once '../../includes/db.php';

$store_slug = basename(dirname(__FILE__));

try {
    // Busca loja + customização
    $stmt = $pdo->prepare("
        SELECT s.*, sc.* 
        FROM stores s
        LEFT JOIN store_customization sc ON s.id = sc.store_id
        WHERE s.store_slug = ? AND s.status = 'active'
    ");
    $stmt->execute([$store_slug]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$store) die("Loja não encontrada.");
    
    // Busca menu customizado
    $stmt = $pdo->prepare("
        SELECT * FROM store_menu 
        WHERE store_id = ? AND is_enabled = 1
        ORDER BY order_position ASC
    ");
    $stmt->execute([$store['id']]);
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // PRODUTO MAIS VENDIDO
    $top_product = null;
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE store_id = ? AND status = 'active' AND total_sold > 0
        ORDER BY total_sold DESC
        LIMIT 1
    ");
    $stmt->execute([$store['id']]);
    $top_product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ÚLTIMO PRODUTO LANÇADO
    $new_product = null;
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE store_id = ? AND status = 'active'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$store['id']]);
    $new_product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 5 ÚLTIMAS NOTÍCIAS
    $news = [];
    $stmt = $pdo->prepare("
        SELECT * FROM news 
        WHERE store_id = ? AND status = 'published'
        ORDER BY COALESCE(published_at, created_at) DESC
        LIMIT 5
    ");
    $stmt->execute([$store['id']]);
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas do servidor
    $stats = ['online_players' => 0, 'max_players' => 0, 'total_players' => 0];
    try {
        $stmt = $pdo->prepare("
            SELECT online_players, max_players 
            FROM server_status 
            WHERE store_id = ? 
            ORDER BY last_update DESC 
            LIMIT 1
        ");
        $stmt->execute([$store['id']]);
        $server_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($server_stats) {
            $stats['online_players'] = $server_stats['online_players'];
            $stats['max_players'] = $server_stats['max_players'];
        }
        
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT player_uuid) as total
            FROM player_sessions
            WHERE store_id = ?
        ");
        $stmt->execute([$store['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_players'] = $result['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Stats Error: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

$primaryColor = $store['primary_color'] ?? '#dc2626';
$secondaryColor = $store['secondary_color'] ?? '#0f172a';

function formatMoney($val) { 
    return 'R$ ' . number_format((float)$val, 2, ',', '.'); 
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 3600) return floor($diff / 60) . 'min atrás';
    if ($diff < 86400) return floor($diff / 3600) . 'h atrás';
    if ($diff < 604800) return floor($diff / 86400) . 'd atrás';
    return date('d/m/Y', $time);
}

$is_logged = isset($_SESSION['store_user_logged']) && $_SESSION['store_user_logged'] === true;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($store['store_title'] ?? $store['store_name']) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?= $primaryColor ?>',
                        secondary: '<?= $secondaryColor ?>'
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        body { 
            background: <?= $secondaryColor ?>; 
            color: white;
            font-family: 'Inter', sans-serif;
        }
        
        .glass { 
            background: rgba(255, 255, 255, 0.02); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255, 255, 255, 0.05); 
        }
        
        .glass-strong { 
            background: rgba(0, 0, 0, 0.6); 
            backdrop-filter: blur(30px); 
            border-bottom: 1px solid rgba(255, 255, 255, 0.1); 
        }
        
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-hover:hover {
            transform: translateY(-8px);
            border-color: <?= $primaryColor ?>;
            box-shadow: 0 20px 60px -20px <?= $primaryColor ?>80;
        }
        
        .gradient-text {
            background: linear-gradient(135deg, <?= $primaryColor ?> 0%, #ef4444 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
    </style>
</head>
<body>

    <!-- HEADER FIXO -->
    <header class="fixed top-0 w-full z-50 glass-strong">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-center justify-between h-20">
                
                <!-- Logo -->
                <a href="index.php" class="flex items-center gap-3 group">
                    <?php if (!empty($store['logo_url'])): ?>
                        <img src="<?= htmlspecialchars($store['logo_url']) ?>" class="h-10 object-contain group-hover:scale-110 transition">
                    <?php else: ?>
                        <div class="w-12 h-12 bg-gradient-to-br from-primary to-red-600 rounded-xl flex items-center justify-center font-black shadow-lg shadow-primary/30 group-hover:scale-110 transition">
                            <?= strtoupper(substr($store['store_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="hidden md:block">
                        <div class="font-black text-lg uppercase tracking-tight group-hover:text-primary transition">
                            <?= htmlspecialchars($store['store_name']) ?>
                        </div>
                        <div class="text-[9px] text-zinc-600 font-bold uppercase tracking-widest">
                            Servidor Minecraft
                        </div>
                    </div>
                </a>

                <!-- Menu Desktop -->
                <nav class="hidden lg:flex items-center gap-6">
                    <?php foreach ($menu_items as $item): ?>
                    <a href="<?= htmlspecialchars($item['url']) ?>" 
                       class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-zinc-400 hover:text-white transition group">
                        <?php if ($item['icon']): ?>
                        <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="w-4 h-4 group-hover:text-primary transition"></i>
                        <?php endif; ?>
                        <span class="group-hover:text-primary transition"><?= htmlspecialchars($item['label']) ?></span>
                    </a>
                    <?php endforeach; ?>
                </nav>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <?php if ($is_logged): ?>
                        <a href="auth.php?action=logout" class="flex items-center gap-2 glass px-4 py-2 rounded-xl hover:bg-white/10 transition">
                            <img src="<?= htmlspecialchars($_SESSION['store_user_skin']) ?>" class="w-6 h-6 rounded-lg">
                            <span class="hidden md:block text-xs font-bold"><?= htmlspecialchars($_SESSION['store_user_nick']) ?></span>
                        </a>
                    <?php else: ?>
                        <a href="auth.php" class="bg-gradient-to-r from-primary to-red-600 hover:brightness-110 px-6 py-2 rounded-xl text-xs font-black uppercase transition shadow-lg shadow-primary/30">
                            Login
                        </a>
                    <?php endif; ?>
                    
                    <button onclick="toggleMobileMenu()" class="lg:hidden w-10 h-10 glass rounded-xl flex items-center justify-center hover:bg-white/10 transition">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="fixed inset-0 z-40 hidden lg:hidden">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="toggleMobileMenu()"></div>
        <div class="absolute right-0 top-0 h-full w-80 bg-secondary border-l border-white/10 p-6">
            <div class="flex items-center justify-between mb-8">
                <h3 class="font-black uppercase text-lg">Menu</h3>
                <button onclick="toggleMobileMenu()" class="w-10 h-10 glass rounded-xl flex items-center justify-center">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <nav class="flex flex-col gap-4">
                <?php foreach ($menu_items as $item): ?>
                <a href="<?= htmlspecialchars($item['url']) ?>" class="flex items-center gap-3 text-sm font-bold uppercase tracking-wider text-zinc-400 hover:text-white transition py-3 border-b border-white/5">
                    <?php if ($item['icon']): ?>
                    <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="w-4 h-4"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($item['label']) ?>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>

    <!-- HERO SECTION -->
    <section class="relative pt-32 pb-20 px-6 overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background-image: radial-gradient(circle, rgba(220, 38, 38, 0.3) 1px, transparent 1px); background-size: 30px 30px;"></div>
        </div>
        
        <!-- Floating Elements -->
        <div class="absolute top-1/4 right-1/4 w-64 h-64 bg-primary/10 rounded-full blur-3xl float-animation"></div>
        <div class="absolute bottom-1/4 left-1/4 w-96 h-96 bg-red-600/10 rounded-full blur-3xl float-animation" style="animation-delay: 2s;"></div>
        
        <div class="max-w-7xl mx-auto relative">
            <div class="text-center mb-12">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full glass border border-primary/20 mb-6 hover:bg-white/5 transition">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    <span class="text-xs font-black uppercase tracking-widest text-green-500">
                        <?= $stats['online_players'] ?> Jogadores Online
                    </span>
                </div>
                
                <h1 class="text-5xl md:text-7xl font-black uppercase tracking-tighter mb-6 leading-tight">
                    Bem-vindo ao <span class="gradient-text"><?= htmlspecialchars($store['store_name']) ?></span>
                </h1>
                
                <p class="text-zinc-400 text-lg max-w-2xl mx-auto leading-relaxed mb-8">
                    <?= htmlspecialchars($store['store_description'] ?? 'O melhor servidor Minecraft do Brasil com economia, ranks e muito mais!') ?>
                </p>
                
                <div class="flex flex-wrap items-center justify-center gap-4">
                    <a href="loja.php" class="bg-gradient-to-r from-primary to-red-600 hover:brightness-110 px-8 py-4 rounded-xl font-black uppercase text-sm transition shadow-lg shadow-primary/30 flex items-center gap-2">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                        Visitar Loja
                    </a>
                    <a href="wiki.php" class="glass hover:bg-white/5 px-8 py-4 rounded-xl font-black uppercase text-sm transition flex items-center gap-2 border border-white/10">
                        <i data-lucide="book-open" class="w-4 h-4"></i>
                        Como Jogar
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto">
                <div class="glass rounded-2xl p-6 text-center hover:bg-white/5 transition card-hover">
                    <div class="w-12 h-12 bg-green-500/10 rounded-xl flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="users" class="w-6 h-6 text-green-500"></i>
                    </div>
                    <p class="text-3xl font-black mb-1"><?= $stats['online_players'] ?></p>
                    <p class="text-xs text-zinc-600 font-bold uppercase">Online Agora</p>
                </div>
                
                <div class="glass rounded-2xl p-6 text-center hover:bg-white/5 transition card-hover">
                    <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="server" class="w-6 h-6 text-blue-500"></i>
                    </div>
                    <p class="text-3xl font-black mb-1"><?= $stats['max_players'] ?></p>
                    <p class="text-xs text-zinc-600 font-bold uppercase">Slots Totais</p>
                </div>
                
                <div class="glass rounded-2xl p-6 text-center hover:bg-white/5 transition card-hover">
                    <div class="w-12 h-12 bg-purple-500/10 rounded-xl flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="trophy" class="w-6 h-6 text-purple-500"></i>
                    </div>
                    <p class="text-3xl font-black mb-1"><?= number_format($stats['total_players']) ?></p>
                    <p class="text-xs text-zinc-600 font-bold uppercase">Jogadores Únicos</p>
                </div>
                
                <div class="glass rounded-2xl p-6 text-center hover:bg-white/5 transition card-hover">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="zap" class="w-6 h-6 text-primary"></i>
                    </div>
                    <p class="text-3xl font-black mb-1">24/7</p>
                    <p class="text-xs text-zinc-600 font-bold uppercase">Uptime</p>
                </div>
            </div>
        </div>
    </section>

    <!-- PRODUTOS EM DESTAQUE -->
    <?php if ($top_product || $new_product): ?>
    <section class="px-6 pb-16">
        <div class="max-w-7xl mx-auto">
            
            <div class="text-center mb-12">
                <h2 class="text-4xl font-black uppercase tracking-tight mb-3">
                    Produtos em <span class="gradient-text">Destaque</span>
                </h2>
                <p class="text-zinc-500">Confira os produtos mais populares e lançamentos</p>
            </div>
            
            <div class="grid md:grid-cols-2 gap-6">
                
                <!-- Produto Mais Vendido -->
                <?php if ($top_product): ?>
                <div class="card-hover glass rounded-3xl p-8 border border-white/5 relative overflow-hidden group">
                    <div class="absolute top-4 right-4 px-4 py-2 rounded-full bg-gradient-to-r from-yellow-500 to-orange-500 text-white text-xs font-black uppercase shadow-lg">
                        🔥 Mais Vendido
                    </div>
                    
                    <div class="flex flex-col gap-6">
                        <div class="aspect-square bg-black/40 rounded-2xl overflow-hidden">
                            <?php if (!empty($top_product['image_url'])): ?>
                                <img src="<?= htmlspecialchars($top_product['image_url']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i data-lucide="package" class="w-20 h-20 text-zinc-700"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <h3 class="text-2xl font-black uppercase mb-2 group-hover:text-primary transition">
                                <?= htmlspecialchars($top_product['name']) ?>
                            </h3>
                            <p class="text-sm text-zinc-500 mb-4 line-clamp-2">
                                <?= htmlspecialchars($top_product['description'] ?? '') ?>
                            </p>
                            
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-3xl font-black gradient-text">
                                    <?= formatMoney($top_product['price']) ?>
                                </span>
                                <div class="text-xs text-zinc-600">
                                    <i data-lucide="shopping-cart" class="w-3 h-3 inline"></i>
                                    <?= $top_product['total_sold'] ?> vendas
                                </div>
                            </div>
                            
                            <a href="loja.php" class="block bg-gradient-to-r from-primary to-red-600 hover:brightness-110 py-4 rounded-xl font-black uppercase text-sm text-center transition shadow-lg">
                                Comprar Agora
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Último Lançamento -->
                <?php if ($new_product): ?>
                <div class="card-hover glass rounded-3xl p-8 border border-white/5 relative overflow-hidden group">
                    <div class="absolute top-4 right-4 px-4 py-2 rounded-full bg-gradient-to-r from-green-500 to-emerald-500 text-white text-xs font-black uppercase shadow-lg">
                        ✨ Novo
                    </div>
                    
                    <div class="flex flex-col gap-6">
                        <div class="aspect-square bg-black/40 rounded-2xl overflow-hidden">
                            <?php if (!empty($new_product['image_url'])): ?>
                                <img src="<?= htmlspecialchars($new_product['image_url']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i data-lucide="sparkles" class="w-20 h-20 text-zinc-700"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <h3 class="text-2xl font-black uppercase mb-2 group-hover:text-primary transition">
                                <?= htmlspecialchars($new_product['name']) ?>
                            </h3>
                            <p class="text-sm text-zinc-500 mb-4 line-clamp-2">
                                <?= htmlspecialchars($new_product['description'] ?? '') ?>
                            </p>
                            
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-3xl font-black gradient-text">
                                    <?= formatMoney($new_product['price']) ?>
                                </span>
                                <div class="text-xs text-zinc-600">
                                    <i data-lucide="clock" class="w-3 h-3 inline"></i>
                                    Lançado <?= timeAgo($new_product['created_at']) ?>
                                </div>
                            </div>
                            
                            <a href="loja.php" class="block bg-gradient-to-r from-primary to-red-600 hover:brightness-110 py-4 rounded-xl font-black uppercase text-sm text-center transition shadow-lg">
                                Ver na Loja
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ÚLTIMAS NOTÍCIAS -->
    <?php if (!empty($news)): ?>
    <section class="px-6 pb-24">
        <div class="max-w-7xl mx-auto">
            
            <div class="flex items-center justify-between mb-12">
                <div>
                    <h2 class="text-4xl font-black uppercase tracking-tight mb-3">
                        Últimas <span class="gradient-text">Notícias</span>
                    </h2>
                    <p class="text-zinc-500">Fique por dentro das novidades do servidor</p>
                </div>
                <a href="noticias.php" class="hidden md:flex items-center gap-2 text-sm font-bold uppercase text-primary hover:underline">
                    Ver Todas
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <?php foreach (array_slice($news, 0, 3) as $article): ?>
                <article class="card-hover glass rounded-2xl overflow-hidden border border-white/5 group">
                    <div class="aspect-video bg-zinc-900 overflow-hidden">
                        <?php if (!empty($article['image_url'])): ?>
                            <img src="<?= htmlspecialchars($article['image_url']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <i data-lucide="newspaper" class="w-16 h-16 text-zinc-700"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-6">
                        <div class="flex items-center gap-2 text-xs text-zinc-600 mb-3">
                            <i data-lucide="clock" class="w-3 h-3"></i>
                            <?= timeAgo($article['published_at'] ?? $article['created_at']) ?>
                        </div>
                        
                        <h3 class="text-lg font-black uppercase tracking-tight mb-3 line-clamp-2 group-hover:text-primary transition">
                            <?= htmlspecialchars($article['title']) ?>
                        </h3>
                        
                        <p class="text-sm text-zinc-500 line-clamp-3 mb-4 leading-relaxed">
                            <?= htmlspecialchars(substr($article['content'], 0, 120)) ?>...
                        </p>
                        
                        <a href="noticia.php?id=<?= $article['id'] ?>" class="inline-flex items-center gap-2 text-xs font-black uppercase text-primary hover:underline">
                            Ler Mais 
                            <i data-lucide="arrow-right" class="w-3 h-3"></i>
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-8 md:hidden">
                <a href="noticias.php" class="inline-flex items-center gap-2 text-sm font-bold uppercase text-primary hover:underline">
                    Ver Todas as Notícias
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="border-t border-white/5 bg-black/20">
        <div class="max-w-7xl mx-auto px-6 py-12">
            <div class="grid md:grid-cols-3 gap-8 mb-8">
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <?php if (!empty($store['logo_url'])): ?>
                            <img src="<?= htmlspecialchars($store['logo_url']) ?>" class="h-10 object-contain">
                        <?php else: ?>
                            <div class="w-12 h-12 bg-gradient-to-br from-primary to-red-600 rounded-xl flex items-center justify-center font-black shadow-lg">
                                <?= strtoupper(substr($store['store_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="font-black text-lg uppercase tracking-tight">
                                <?= htmlspecialchars($store['store_name']) ?>
                            </div>
                            <div class="text-[9px] text-zinc-600 font-bold uppercase tracking-widest">
                                Servidor Minecraft
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-zinc-600 leading-relaxed">
                        <?= htmlspecialchars($store['store_tagline'] ?? 'O melhor servidor de Minecraft') ?>
                    </p>
                </div>
                
                <div>
                    <h4 class="font-black uppercase text-sm mb-4">Links Rápidos</h4>
                    <div class="space-y-2">
                        <?php foreach (array_slice($menu_items, 0, 6) as $item): ?>
                        <a href="<?= htmlspecialchars($item['url']) ?>" class="block text-sm text-zinc-600 hover:text-white transition">
                            <?= htmlspecialchars($item['label']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-black uppercase text-sm mb-4">Servidor</h4>
                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-sm text-zinc-600">
                            <i data-lucide="server" class="w-4 h-4"></i>
                            <span>IP: play.<?= strtolower($store['store_name']) ?>.com.br</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-zinc-600">
                            <i data-lucide="users" class="w-4 h-4"></i>
                            <span><?= $stats['online_players'] ?>/<?= $stats['max_players'] ?> online</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-white/5 pt-8 text-center">
                <p class="text-sm text-zinc-600 mb-2">
                    © <?= date('Y') ?> <?= htmlspecialchars($store['store_name']) ?>. Todos os direitos reservados.
                </p>
                <div class="flex items-center justify-center gap-2">
                    <span class="text-xs text-zinc-700">Powered by</span>
                    <span class="text-xs font-black uppercase gradient-text">SplitStore</span>
                </div>
            </div>
        </div>
    </footer>

    <script>
        lucide.createIcons();
        
        function toggleMobileMenu() {
            document.getElementById('mobileMenu').classList.toggle('hidden');
            lucide.createIcons();
        }
        
        // Fechar menu ao clicar fora
        document.getElementById('mobileMenu')?.addEventListener('click', (e) => {
            if (e.target.classList.contains('bg-black/80')) {
                toggleMobileMenu();
            }
        });
    </script>
</body>
</html>