<?php
/**
 * ============================================
 * SPLITSTORE - LOJA FRONTEND V2.0 COMPLETA
 * ============================================
 * stores/[nome-da-loja]/index.php
 * 
 * Recursos:
 * - Header com menu e busca
 * - Sistema de categorias
 * - Footer completo
 * - Widgets Discord/Twitter
 * - Design moderno e responsivo
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

// Conexão com banco
$db_found = false;
$possible_paths = [
    __DIR__ . '/../../includes/db.php',
    $_SERVER['DOCUMENT_ROOT'] . '/includes/db.php'
];

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_found = true;
        break;
    }
}

if (!$db_found || !isset($pdo)) {
    die("Erro: Banco de dados não conectado.");
}

// Identifica a loja
$store_slug = basename(dirname(__FILE__));

try {
    // Busca loja
    $stmt = $pdo->prepare("
        SELECT s.*, sc.* 
        FROM stores s
        LEFT JOIN store_customization sc ON s.id = sc.store_id
        WHERE s.store_slug = ? AND s.status = 'active'
    ");
    $stmt->execute([$store_slug]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$store) {
        die("Loja não encontrada ou inativa.");
    }
    
    // Busca categorias
    $stmt = $pdo->prepare("
        SELECT * FROM categories 
        WHERE store_id = ? AND status = 'active'
        ORDER BY order_position ASC, name ASC
    ");
    $stmt->execute([$store['id']]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtros
    $search = $_GET['search'] ?? '';
    $category_filter = $_GET['category'] ?? '';
    
    // Busca produtos
    $sql = "SELECT * FROM products WHERE store_id = ? AND status = 'active'";
    $params = [$store['id']];
    
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    if (!empty($category_filter)) {
        $sql .= " AND category_id = ?";
        $params[] = $category_filter;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

$primaryColor = $store['primary_color'] ?? '#dc2626';
$secondaryColor = $store['secondary_color'] ?? '#0f172a';
$accentColor = $store['accent_color'] ?? '#ef4444';

function formatMoney($val) { 
    return 'R$ ' . number_format((float)$val, 2, ',', '.'); 
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($store['store_name']) ?> - Loja Oficial</title>
    
    <meta name="description" content="<?= htmlspecialchars($store['store_description'] ?? 'Loja oficial') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($store['store_name']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($store['store_description'] ?? '') ?>">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?= $primaryColor ?>',
                        secondary: '<?= $secondaryColor ?>',
                        accent: '<?= $accentColor ?>'
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
            background: rgba(255, 255, 255, 0.03); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255, 255, 255, 0.05); 
        }
        
        .glass-strong { 
            background: rgba(0, 0, 0, 0.6); 
            backdrop-filter: blur(30px); 
            border-bottom: 1px solid rgba(255, 255, 255, 0.1); 
        }
        
        .product-card { 
            transition: all 0.3s ease; 
        }
        
        .product-card:hover { 
            transform: translateY(-8px); 
            border-color: <?= $primaryColor ?>;
            box-shadow: 0 20px 60px -20px <?= $primaryColor ?>60; 
        }
        
        .category-chip {
            transition: all 0.2s ease;
        }
        
        .category-chip:hover {
            background: <?= $primaryColor ?>;
            color: white;
        }
        
        .category-chip.active {
            background: <?= $primaryColor ?>;
            color: white;
        }
        
        /* Scrollbar personalizada */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.02); }
        ::-webkit-scrollbar-thumb { background: <?= $primaryColor ?>; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: <?= $accentColor ?>; }
    </style>
</head>
<body class="bg-secondary min-h-screen">

    <!-- ============================================
         HEADER COMPLETO
         ============================================ -->
    <header class="fixed top-0 w-full z-50 glass-strong">
        <div class="max-w-7xl mx-auto px-6">
            <!-- Top Bar -->
            <div class="flex items-center justify-between h-20">
                
                <!-- Logo -->
                <a href="/" class="flex items-center gap-3 group">
                    <?php if (!empty($store['logo_url'])): ?>
                        <img src="<?= htmlspecialchars($store['logo_url']) ?>" class="h-10 object-contain">
                    <?php else: ?>
                        <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center font-black shadow-lg shadow-primary/30">
                            <?= strtoupper(substr($store['store_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="hidden md:block">
                        <div class="font-black text-lg uppercase tracking-tight">
                            <?= htmlspecialchars($store['store_name']) ?>
                        </div>
                        <div class="text-[9px] text-zinc-600 font-bold uppercase tracking-widest">
                            Loja Oficial
                        </div>
                    </div>
                </a>

                <!-- Desktop Menu -->
                <nav class="hidden lg:flex items-center gap-8">
                    <a href="#produtos" class="text-sm font-bold uppercase tracking-wider text-zinc-400 hover:text-white transition">
                        Produtos
                    </a>
                    <a href="#categorias" class="text-sm font-bold uppercase tracking-wider text-zinc-400 hover:text-white transition">
                        Categorias
                    </a>
                    <a href="#sobre" class="text-sm font-bold uppercase tracking-wider text-zinc-400 hover:text-white transition">
                        Sobre
                    </a>
                    <a href="#contato" class="text-sm font-bold uppercase tracking-wider text-zinc-400 hover:text-white transition">
                        Contato
                    </a>
                </nav>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <!-- Busca -->
                    <button onclick="toggleSearch()" class="w-10 h-10 glass rounded-xl flex items-center justify-center hover:bg-white/10 transition">
                        <i data-lucide="search" class="w-5 h-5"></i>
                    </button>
                    
                    <!-- Carrinho -->
                    <button onclick="toggleCart()" class="relative w-10 h-10 glass rounded-xl flex items-center justify-center hover:bg-white/10 transition">
                        <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                        <span id="cartBadge" class="absolute -top-1 -right-1 bg-primary text-white text-[10px] font-black w-5 h-5 flex items-center justify-center rounded-full opacity-0">0</span>
                    </button>
                    
                    <!-- Menu Mobile -->
                    <button onclick="toggleMobileMenu()" class="lg:hidden w-10 h-10 glass rounded-xl flex items-center justify-center">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <!-- Search Bar (Hidden by default) -->
            <div id="searchBar" class="hidden pb-4 animate-in slide-in-from-top">
                <form method="GET" class="relative">
                    <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-zinc-500"></i>
                    <input type="text" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="Buscar produtos..."
                           class="w-full bg-white/5 border border-white/10 pl-12 pr-4 py-3 rounded-xl text-sm outline-none focus:border-primary transition">
                </form>
            </div>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="fixed inset-0 z-40 hidden lg:hidden">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="toggleMobileMenu()"></div>
        <div class="absolute right-0 top-0 h-full w-80 bg-secondary border-l border-white/10 p-6">
            <nav class="flex flex-col gap-4 mt-20">
                <a href="#produtos" class="text-sm font-bold uppercase tracking-wider text-zinc-400 hover:text-white transition py-3 border-b border-white/5">
                    Produtos
                </a>
                <a href="#categorias" class="text-sm font-bold uppercase tracking-wider text-zinc-400 hover:text-white transition py-3 border-b border-white/5">
                    Categorias
                </a>
                <a href="#sobre" class="text-sm font-bold uppercase tracking-wider text-zinc-400 hover:text-white transition py-3 border-b border-white/5">
                    Sobre
                </a>
                <a href="#contato" class="text-sm font-bold uppercase tracking-wider text-zinc-400 hover:text-white transition py-3 border-b border-white/5">
                    Contato
                </a>
            </nav>
        </div>
    </div>

    <!-- ============================================
         HERO SECTION
         ============================================ -->
    <section class="pt-32 pb-16 px-6">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-16">
                <span class="inline-block px-4 py-1.5 rounded-full glass text-xs font-black uppercase tracking-widest mb-6 text-primary border border-primary/20">
                    Loja Oficial
                </span>
                <h1 class="text-5xl md:text-7xl font-black uppercase tracking-tighter mb-6 leading-tight">
                    <?= htmlspecialchars($store['store_title'] ?? $store['store_name']) ?>
                </h1>
                <p class="text-zinc-400 text-lg max-w-2xl mx-auto leading-relaxed">
                    <?= htmlspecialchars($store['store_description'] ?? 'Bem-vindo à nossa loja!') ?>
                </p>
            </div>

            <!-- Filtro de Categorias -->
            <?php if (!empty($categories)): ?>
            <div id="categorias" class="mb-12">
                <div class="flex items-center gap-3 overflow-x-auto pb-4 scrollbar-hide">
                    <a href="?" class="category-chip glass px-6 py-3 rounded-full text-xs font-black uppercase tracking-wider whitespace-nowrap <?= empty($category_filter) ? 'active' : '' ?>">
                        Todos
                    </a>
                    <?php foreach ($categories as $cat): ?>
                    <a href="?category=<?= $cat['id'] ?>" class="category-chip glass px-6 py-3 rounded-full text-xs font-black uppercase tracking-wider whitespace-nowrap flex items-center gap-2 <?= $category_filter == $cat['id'] ? 'active' : '' ?>">
                        <?php if (!empty($cat['icon'])): ?>
                        <i data-lucide="<?= htmlspecialchars($cat['icon']) ?>" class="w-4 h-4"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============================================
         PRODUTOS
         ============================================ -->
    <section id="produtos" class="px-6 pb-24">
        <div class="max-w-7xl mx-auto">
            
            <?php if (!empty($search)): ?>
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <p class="text-sm text-zinc-500">Resultados para:</p>
                    <h2 class="text-2xl font-black">"<?= htmlspecialchars($search) ?>"</h2>
                    <p class="text-xs text-zinc-600 mt-1"><?= count($products) ?> produto(s) encontrado(s)</p>
                </div>
                <a href="?" class="text-xs font-bold uppercase text-primary hover:underline">
                    Limpar Busca
                </a>
            </div>
            <?php endif; ?>

            <?php if (empty($products)): ?>
            <div class="glass rounded-3xl p-24 text-center border-dashed border-2 border-white/5">
                <div class="w-20 h-20 bg-zinc-900 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="package-open" class="w-10 h-10 text-zinc-600"></i>
                </div>
                <h3 class="text-xl font-bold mb-2">Nenhum produto encontrado</h3>
                <p class="text-zinc-500">
                    <?php if (!empty($search)): ?>
                        Tente buscar por outro termo
                    <?php else: ?>
                        Os produtos aparecerão aqui em breve
                    <?php endif; ?>
                </p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($products as $product): ?>
                <div class="product-card glass rounded-2xl p-5 flex flex-col border border-white/5 group">
                    
                    <!-- Imagem -->
                    <div class="relative aspect-square rounded-xl bg-black/40 mb-4 overflow-hidden">
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                 class="w-full h-full object-cover transition duration-700 group-hover:scale-110">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <i data-lucide="box" class="w-12 h-12 text-zinc-700"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Badge Estoque -->
                        <?php if (isset($product['stock']) && $product['stock'] !== null): ?>
                        <div class="absolute top-3 right-3 px-3 py-1 rounded-lg text-[10px] font-black uppercase <?= $product['stock'] > 0 ? 'bg-green-500/20 text-green-500 border border-green-500/30' : 'bg-red-500/20 text-red-500 border border-red-500/30' ?>">
                            <?= $product['stock'] > 0 ? $product['stock'] . ' unidades' : 'Esgotado' ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="flex-1">
                        <h3 class="font-black text-base uppercase tracking-tight mb-2 line-clamp-1">
                            <?= htmlspecialchars($product['name']) ?>
                        </h3>
                        <p class="text-xs text-zinc-500 line-clamp-2 leading-relaxed">
                            <?= htmlspecialchars($product['description'] ?? '') ?>
                        </p>
                    </div>

                    <!-- Footer -->
                    <div class="mt-5 pt-4 border-t border-white/5 flex items-center justify-between">
                        <div>
                            <span class="block text-[9px] text-zinc-600 font-bold uppercase tracking-wider mb-1">Valor</span>
                            <span class="text-2xl font-black text-primary"><?= formatMoney($product['price']) ?></span>
                        </div>
                        
                        <button onclick='addToCart(<?= json_encode($product) ?>)' 
                                class="bg-primary text-white w-12 h-12 rounded-xl flex items-center justify-center hover:brightness-110 transition shadow-lg shadow-primary/25 hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                                <?= (isset($product['stock']) && $product['stock'] == 0) ? 'disabled' : '' ?>>
                            <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============================================
         WIDGETS SECTION
         ============================================ -->
    <section class="px-6 pb-24">
        <div class="max-w-7xl mx-auto">
            <div class="grid md:grid-cols-2 gap-8">
                
                <!-- Widget Discord -->
                <div class="glass rounded-3xl p-8 border border-white/5">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 bg-[#5865F2]/20 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-[#5865F2]" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.892a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-black uppercase">Discord</h3>
                            <p class="text-xs text-zinc-500">Junte-se à comunidade</p>
                        </div>
                    </div>
                    <p class="text-sm text-zinc-400 mb-6 leading-relaxed">
                        Entre no nosso servidor Discord e fique por dentro de novidades, promoções e eventos!
                    </p>
                    <a href="https://discord.gg/seuservidor" target="_blank" 
                       class="inline-flex items-center gap-2 bg-[#5865F2] hover:bg-[#4752C4] text-white px-6 py-3 rounded-xl font-bold text-sm transition">
                        <i data-lucide="message-circle" class="w-4 h-4"></i>
                        Entrar no Discord
                    </a>
                </div>

                <!-- Widget Twitter -->
                <div class="glass rounded-3xl p-8 border border-white/5">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 bg-[#1DA1F2]/20 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-[#1DA1F2]" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-black uppercase">Twitter</h3>
                            <p class="text-xs text-zinc-500">Siga-nos nas redes</p>
                        </div>
                    </div>
                    <p class="text-sm text-zinc-400 mb-6 leading-relaxed">
                        Acompanhe nossas atualizações, promoções e novidades em tempo real no Twitter!
                    </p>
                    <a href="https://twitter.com/seuservidor" target="_blank" 
                       class="inline-flex items-center gap-2 bg-[#1DA1F2] hover:bg-[#1A8CD8] text-white px-6 py-3 rounded-xl font-bold text-sm transition">
                        <i data-lucide="twitter" class="w-4 h-4"></i>
                        Seguir no Twitter
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================
         FOOTER COMPLETO
         ============================================ -->
    <footer class="border-t border-white/5 bg-black/20">
        <div class="max-w-7xl mx-auto px-6 py-16">
            <div class="grid md:grid-cols-4 gap-12 mb-12">
                
                <!-- Coluna 1: Logo e Descrição -->
                <div class="md:col-span-2">
                    <div class="flex items-center gap-3 mb-6">
                        <?php if (!empty($store['logo_url'])): ?>
                            <img src="<?= htmlspecialchars($store['logo_url']) ?>" class="h-10 object-contain">
                        <?php else: ?>
                            <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center font-black">
                                <?= strtoupper(substr($store['store_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="font-black text-xl uppercase tracking-tight">
                            <?= htmlspecialchars($store['store_name']) ?>
                        </div>
                    </div>
                    <p class="text-sm text-zinc-500 leading-relaxed max-w-md mb-6">
                        <?= htmlspecialchars($store['store_description'] ?? 'A melhor loja de itens para o seu servidor Minecraft.') ?>
                    </p>
                    
                    <!-- Redes Sociais -->
                    <div class="flex items-center gap-3">
                        <a href="#" class="w-10 h-10 glass rounded-xl flex items-center justify-center hover:bg-primary hover:text-white transition">
                            <i data-lucide="twitter" class="w-4 h-4"></i>
                        </a>
                        <a href="#" class="w-10 h-10 glass rounded-xl flex items-center justify-center hover:bg-[#5865F2] hover:text-white transition">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.892a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z"/>
                            </svg>
                        </a>
                        <a href="#" class="w-10 h-10 glass rounded-xl flex items-center justify-center hover:bg-[#E4405F] hover:text-white transition">
                            <i data-lucide="instagram" class="w-4 h-4"></i>
                        </a>
                    </div>
                </div>

                <!-- Coluna 2: Links Rápidos -->
                <div>
                    <h4 class="text-sm font-black uppercase tracking-wider mb-4">Links Rápidos</h4>
                    <ul class="space-y-3">
                        <li><a href="#produtos" class="text-sm text-zinc-500 hover:text-white transition">Produtos</a></li>
                        <li><a href="#categorias" class="text-sm text-zinc-500 hover:text-white transition">Categorias</a></li>
                        <li><a href="#sobre" class="text-sm text-zinc-500 hover:text-white transition">Sobre Nós</a></li>
                        <li><a href="#contato" class="text-sm text-zinc-500 hover:text-white transition">Contato</a></li>
                    </ul>
                </div>

                <!-- Coluna 3: Suporte -->
                <div>
                    <h4 class="text-sm font-black uppercase tracking-wider mb-4">Suporte</h4>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-sm text-zinc-500 hover:text-white transition">FAQ</a></li>
                        <li><a href="#" class="text-sm text-zinc-500 hover:text-white transition">Termos de Uso</a></li>
                        <li><a href="#" class="text-sm text-zinc-500 hover:text-white transition">Política de Privacidade</a></li>
                        <li><a href="#" class="text-sm text-zinc-500 hover:text-white transition">Devolução</a></li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="pt-8 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-sm text-zinc-600">
                    © <?= date('Y') ?> <?= htmlspecialchars($store['store_name']) ?>. Todos os direitos reservados.
                </p>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-zinc-700">Powered by</span>
                    <span class="text-xs font-black uppercase text-primary">SplitStore</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- ============================================
         MODAL CARRINHO
         ============================================ -->
    <div id="cartModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="toggleCart()"></div>
        <div class="absolute right-0 top-0 h-full w-full max-w-md bg-secondary border-l border-white/10 p-6 flex flex-col shadow-2xl">
            
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h2 class="text-2xl font-black uppercase">Carrinho</h2>
                    <p class="text-xs text-zinc-500">Revise seus itens</p>
                </div>
                <button onclick="toggleCart()" class="w-10 h-10 glass rounded-xl flex items-center justify-center hover:bg-white/10 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div id="cartItems" class="flex-1 overflow-y-auto pr-2"></div>

            <div class="mt-6 pt-6 border-t border-white/10">
                <div class="flex justify-between items-end mb-6">
                    <span class="text-zinc-500 font-bold uppercase text-xs">Total</span>
                    <span id="cartTotal" class="text-3xl font-black text-primary">R$ 0,00</span>
                </div>
                <button onclick="checkout()" class="w-full bg-primary hover:brightness-110 text-white py-4 rounded-xl font-black uppercase text-sm transition shadow-lg shadow-primary/30 flex items-center justify-center gap-2">
                    Finalizar Compra
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        let cart = JSON.parse(localStorage.getItem('cart_<?= $store_slug ?>') || '[]');
        
        function toggleSearch() {
            const searchBar = document.getElementById('searchBar');
            searchBar.classList.toggle('hidden');
            if (!searchBar.classList.contains('hidden')) {
                searchBar.querySelector('input').focus();
            }
        }
        
        function toggleMobileMenu() {
            document.getElementById('mobileMenu').classList.toggle('hidden');
        }
        
        function updateCart() {
            const badge = document.getElementById('cartBadge');
            const items = document.getElementById('cartItems');
            const total = document.getElementById('cartTotal');
            
            badge.innerText = cart.length;
            badge.style.opacity = cart.length > 0 ? '1' : '0';
            
            if (cart.length === 0) {
                items.innerHTML = '<div class="h-full flex flex-col items-center justify-center text-center opacity-50"><i data-lucide="shopping-basket" class="w-16 h-16 mb-4 text-zinc-700"></i><p class="text-zinc-500">Carrinho vazio</p></div>';
                total.innerText = 'R$ 0,00';
                lucide.createIcons();
                return;
            }
            
            let sum = 0;
            items.innerHTML = cart.map((item, i) => {
                sum += parseFloat(item.price);
                return `<div class="flex gap-4 bg-white/5 p-3 rounded-xl border border-white/5 mb-3">
                    <div class="w-16 h-16 bg-black/30 rounded-lg flex-shrink-0 overflow-hidden">
                        ${item.image_url ? `<img src="${item.image_url}" class="w-full h-full object-cover">` : '<i data-lucide="package" class="w-6 h-6 text-zinc-600"></i>'}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm truncate">${item.name}</p>
                        <p class="text-xs text-primary font-bold">R$ ${parseFloat(item.price).toFixed(2).replace('.', ',')}</p>
                    </div>
                    <button onclick="removeFromCart(${i})" class="w-8 h-8 flex items-center justify-center rounded-lg text-zinc-500 hover:text-red-500 hover:bg-red-500/10">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>`;
            }).join('');
            
            total.innerText = 'R$ ' + sum.toFixed(2).replace('.', ',');
            lucide.createIcons();
        }
        
        function addToCart(product) {
            cart.push(product);
            localStorage.setItem('cart_<?= $store_slug ?>', JSON.stringify(cart));
            updateCart();
            toggleCart();
        }
        
        function removeFromCart(index) {
            cart.splice(index, 1);
            localStorage.setItem('cart_<?= $store_slug ?>', JSON.stringify(cart));
            updateCart();
        }
        
        function toggleCart() {
            document.getElementById('cartModal').classList.toggle('hidden');
        }
        
        function checkout() {
            if (cart.length === 0) return alert('Carrinho vazio!');
            alert('Sistema de checkout será implementado em breve!');
        }
        
        updateCart();
    </script>
</body>
</html>