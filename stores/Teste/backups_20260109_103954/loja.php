<?php
/**
 * ============================================
 * SPLITSTORE - LOJA DE PRODUTOS
 * ============================================
 */

session_start();
require_once '../../includes/db.php';

$store_slug = basename(dirname(__FILE__));
$category_filter = isset($_GET['categoria']) ? (int)$_GET['categoria'] : null;
$search = isset($_GET['busca']) ? trim($_GET['busca']) : '';

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
    
    if (!$store) die("Loja não encontrada.");
    
    // Menu
    $stmt = $pdo->prepare("
        SELECT * FROM store_menu 
        WHERE store_id = ? AND is_enabled = 1
        ORDER BY order_position ASC
    ");
    $stmt->execute([$store['id']]);
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Categorias
    $stmt = $pdo->prepare("
        SELECT c.*, COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
        WHERE c.store_id = ? AND c.status = 'active'
        GROUP BY c.id
        ORDER BY c.order_position ASC
    ");
    $stmt->execute([$store['id']]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Produtos
    $sql = "
        SELECT p.*, c.name as category_name, c.icon as category_icon
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.store_id = ? AND p.status = 'active'
    ";
    
    $params = [$store['id']];
    
    if ($category_filter) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_filter;
    }
    
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY p.total_sold DESC, p.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

$primaryColor = $store['primary_color'] ?? '#dc2626';

function formatMoney($val) { 
    return 'R$ ' . number_format((float)$val, 2, ',', '.'); 
}

$is_logged = isset($_SESSION['store_user_logged']) && $_SESSION['store_user_logged'] === true;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loja | <?= htmlspecialchars($store['store_name']) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?= $primaryColor ?>'
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        body { 
            background: #0f0f0f; 
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
        }
        
        .product-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .product-card:hover {
            transform: translateY(-12px);
            border-color: <?= $primaryColor ?>;
            box-shadow: 0 25px 70px -20px <?= $primaryColor ?>80;
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <header class="sticky top-0 z-50 glass-strong border-b border-white/10">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-center justify-between h-20">
                <a href="index.php" class="flex items-center gap-3 hover:opacity-80 transition">
                    <?php if (!empty($store['logo_url'])): ?>
                        <img src="<?= htmlspecialchars($store['logo_url']) ?>" class="h-10 object-contain">
                    <?php else: ?>
                        <div class="w-12 h-12 bg-gradient-to-br from-primary to-red-600 rounded-xl flex items-center justify-center font-black shadow-lg">
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
                
                <nav class="hidden lg:flex items-center gap-6">
                    <?php foreach ($menu_items as $item): ?>
                    <a href="<?= htmlspecialchars($item['url']) ?>" 
                       class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-zinc-400 hover:text-white transition <?= basename($_SERVER['PHP_SELF']) == basename($item['url']) ? 'text-primary' : '' ?>">
                        <?php if ($item['icon']): ?>
                        <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="w-4 h-4"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($item['label']) ?>
                    </a>
                    <?php endforeach; ?>
                </nav>
                
                <div class="flex items-center gap-3">
                    <?php if ($is_logged): ?>
                        <a href="auth.php?action=logout" class="flex items-center gap-2 glass px-4 py-2 rounded-xl hover:bg-white/10 transition">
                            <img src="<?= htmlspecialchars($_SESSION['store_user_skin']) ?>" class="w-6 h-6 rounded-lg">
                            <span class="hidden md:block text-xs font-bold"><?= htmlspecialchars($_SESSION['store_user_nick']) ?></span>
                        </a>
                    <?php else: ?>
                        <a href="auth.php" class="bg-gradient-to-r from-primary to-red-600 hover:brightness-110 px-6 py-2 rounded-xl text-xs font-black uppercase transition shadow-lg">
                            Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-12">
        
        <!-- Header da Loja -->
        <div class="mb-12">
            <div class="flex items-center gap-3 mb-6">
                <a href="index.php" class="text-zinc-600 hover:text-white transition">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-4xl font-black uppercase tracking-tighter">
                    <i data-lucide="shopping-bag" class="w-8 h-8 inline text-primary"></i>
                    Loja de <span class="text-primary">Produtos</span>
                </h1>
            </div>
            <p class="text-zinc-500 text-lg">
                Adquira VIPs, kits, itens especiais e muito mais para melhorar sua experiência no servidor
            </p>
        </div>

        <!-- Busca e Filtros -->
        <div class="glass rounded-2xl p-6 mb-8 border border-white/10">
            <div class="grid md:grid-cols-2 gap-4">
                
                <!-- Busca -->
                <form method="GET" class="relative">
                    <input type="text" name="busca" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Buscar produtos..."
                           class="w-full bg-black/30 border border-white/10 pl-12 pr-4 py-4 rounded-xl text-sm outline-none focus:border-primary transition">
                    <i data-lucide="search" class="w-5 h-5 text-zinc-600 absolute left-4 top-1/2 -translate-y-1/2"></i>
                    
                    <?php if ($search): ?>
                    <a href="loja.php" class="absolute right-4 top-1/2 -translate-y-1/2 text-zinc-600 hover:text-white transition">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </a>
                    <?php endif; ?>
                </form>

                <!-- Filtro de Categorias -->
                <div class="flex gap-2 overflow-x-auto">
                    <a href="loja.php" class="px-4 py-2 rounded-xl text-xs font-black uppercase whitespace-nowrap transition <?= !$category_filter ? 'bg-primary text-white' : 'bg-white/5 text-zinc-400 hover:bg-white/10' ?>">
                        <i data-lucide="grid-3x3" class="w-4 h-4 inline mr-1"></i>
                        Todas
                    </a>
                    
                    <?php foreach ($categories as $cat): ?>
                    <a href="?categoria=<?= $cat['id'] ?>" 
                       class="px-4 py-2 rounded-xl text-xs font-black uppercase whitespace-nowrap transition <?= $category_filter == $cat['id'] ? 'bg-primary text-white' : 'bg-white/5 text-zinc-400 hover:bg-white/10' ?>">
                        <?php if ($cat['icon']): ?>
                        <i data-lucide="<?= htmlspecialchars($cat['icon']) ?>" class="w-4 h-4 inline mr-1"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($cat['name']) ?>
                        <span class="opacity-60">(<?= $cat['product_count'] ?>)</span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if ($search || $category_filter): ?>
            <div class="mt-4 pt-4 border-t border-white/5 flex items-center justify-between text-sm">
                <div class="text-zinc-500">
                    Mostrando <?= count($products) ?> produto(s)
                    <?php if ($search): ?>
                        para "<strong class="text-white"><?= htmlspecialchars($search) ?></strong>"
                    <?php endif; ?>
                </div>
                <a href="loja.php" class="text-primary hover:underline font-bold uppercase">
                    Limpar Filtros
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Grid de Produtos -->
        <?php if (empty($products)): ?>
            <div class="glass rounded-3xl p-20 text-center border border-white/5">
                <div class="w-20 h-20 bg-zinc-900 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="package-x" class="w-10 h-10 text-zinc-700"></i>
                </div>
                <h3 class="text-2xl font-black uppercase mb-3 text-zinc-600">Nenhum Produto Encontrado</h3>
                <p class="text-zinc-500 mb-6">
                    <?= $search ? "Tente buscar com outros termos" : "Nenhum produto disponível nesta categoria" ?>
                </p>
                <a href="loja.php" class="inline-block bg-primary hover:brightness-110 px-8 py-3 rounded-xl font-black uppercase text-sm transition">
                    Ver Todos os Produtos
                </a>
            </div>
        <?php else: ?>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($products as $product): ?>
                <div class="product-card glass rounded-2xl overflow-hidden border border-white/5 flex flex-col">
                    
                    <!-- Imagem -->
                    <div class="aspect-square bg-zinc-900 overflow-hidden relative group">
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                 class="w-full h-full object-cover group-hover:scale-110 transition duration-500"
                                 alt="<?= htmlspecialchars($product['name']) ?>">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <i data-lucide="package" class="w-20 h-20 text-zinc-800"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Badges -->
                        <div class="absolute top-3 left-3 flex flex-col gap-2">
                            <?php if ($product['total_sold'] > 0): ?>
                            <span class="px-3 py-1 rounded-full bg-yellow-500/90 text-yellow-900 text-xs font-black uppercase backdrop-blur-sm">
                                🔥 <?= $product['total_sold'] ?> vendas
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($product['category_name']): ?>
                            <span class="px-3 py-1 rounded-full bg-black/70 text-white text-xs font-bold uppercase backdrop-blur-sm">
                                <?php if ($product['category_icon']): ?>
                                <i data-lucide="<?= htmlspecialchars($product['category_icon']) ?>" class="w-3 h-3 inline"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars($product['category_name']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isset($product['stock']) && $product['stock'] !== null && $product['stock'] < 10 && $product['stock'] > 0): ?>
                        <div class="absolute bottom-3 right-3">
                            <span class="px-3 py-1 rounded-full bg-red-500/90 text-white text-xs font-black uppercase backdrop-blur-sm">
                                <i data-lucide="alert-circle" class="w-3 h-3 inline"></i>
                                Últimas <?= $product['stock'] ?> unidades
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Conteúdo -->
                    <div class="p-5 flex flex-col flex-1">
                        <h3 class="text-lg font-black uppercase tracking-tight mb-2 line-clamp-1">
                            <?= htmlspecialchars($product['name']) ?>
                        </h3>
                        
                        <p class="text-xs text-zinc-500 line-clamp-2 mb-4 leading-relaxed flex-1">
                            <?= htmlspecialchars($product['description'] ?? 'Sem descrição') ?>
                        </p>
                        
                        <div class="pt-4 border-t border-white/5">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-2xl font-black text-primary">
                                    <?= formatMoney($product['price']) ?>
                                </span>
                                
                                <?php if (isset($product['stock']) && $product['stock'] !== null): ?>
                                <div class="text-xs text-zinc-600">
                                    <i data-lucide="package" class="w-3 h-3 inline"></i>
                                    <?= $product['stock'] > 0 ? "Estoque: {$product['stock']}" : "Esgotado" ?>
                                </div>
                                <?php else: ?>
                                <div class="text-xs text-green-500">
                                    <i data-lucide="infinity" class="w-3 h-3 inline"></i>
                                    Ilimitado
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!isset($product['stock']) || $product['stock'] === null || $product['stock'] > 0): ?>
                            <button onclick="buyProduct(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>', <?= $product['price'] ?>)"
                                    class="w-full bg-gradient-to-r from-primary to-red-600 hover:brightness-110 py-3 rounded-xl font-black uppercase text-xs transition shadow-lg">
                                <i data-lucide="shopping-cart" class="w-4 h-4 inline mr-1"></i>
                                Comprar Agora
                            </button>
                            <?php else: ?>
                            <button disabled class="w-full bg-zinc-800 text-zinc-600 py-3 rounded-xl font-black uppercase text-xs cursor-not-allowed">
                                <i data-lucide="x-circle" class="w-4 h-4 inline mr-1"></i>
                                Esgotado
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- Footer Simplificado -->
    <footer class="border-t border-white/5 mt-16">
        <div class="max-w-7xl mx-auto px-6 py-8 text-center">
            <p class="text-sm text-zinc-600">
                © <?= date('Y') ?> <?= htmlspecialchars($store['store_name']) ?>
            </p>
            <div class="flex items-center justify-center gap-2 mt-2">
                <span class="text-xs text-zinc-700">Powered by</span>
                <span class="text-xs font-black uppercase text-primary">SplitStore</span>
            </div>
        </div>
    </footer>

    <script>
        lucide.createIcons();
        
        function buyProduct(id, name, price) {
            <?php if ($is_logged): ?>
                // Aqui você implementaria o checkout real
                alert(`Produto: ${name}\nPreço: R$ ${price.toFixed(2).replace('.', ',')}\n\nCheckout em desenvolvimento...`);
            <?php else: ?>
                if (confirm('Você precisa estar logado para comprar. Fazer login agora?')) {
                    window.location.href = 'auth.php';
                }
            <?php endif; ?>
        }
    </script>
</body>
</html>