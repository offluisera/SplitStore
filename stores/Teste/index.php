<?php
/**
 * ============================================
 * SPLITSTORE - VERSÃO ESTÁVEL (DESIGN CORRIGIDO)
 * ============================================
 */

// Configurações iniciais
ini_set('display_errors', 0); // Desativar erros na tela para não sujar o design
error_reporting(E_ALL);
session_start();

// ==================================================================
// 1. CONEXÃO COM O BANCO
// ==================================================================
$db_found = false;
$possible_paths = [
    __DIR__ . '/../includes/db.php',       
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
    die("Erro Crítico: Banco de dados não conectado.");
}

// ==================================================================
// 2. IDENTIFICAÇÃO DA LOJA
// ==================================================================
$uri = $_SERVER['REQUEST_URI'];
$search_term = 'Teste'; // Padrão

// Tenta pegar da URL
if (preg_match('/^\/store\/([a-zA-Z0-9\-]+)/i', $uri, $matches)) {
    $search_term = $matches[1];
}

// ==================================================================
// 3. BUSCA DADOS
// ==================================================================
$store = null;
$products = [];

try {
    // Busca Loja
    $stmt = $pdo->prepare("SELECT * FROM stores WHERE store_name LIKE ? OR owner_name LIKE ? LIMIT 1");
    $stmt->execute([$search_term, $search_term]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($store) {
        // Busca Customização (Se não existir, usa array vazio para não dar erro)
        try {
            $stmt = $pdo->prepare("SELECT * FROM store_customization WHERE store_id = ?");
            $stmt->execute([$store['id']]);
            $custom = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($custom) $store = array_merge($store, $custom);
        } catch (Exception $e) {}

        // Busca Produtos
        try {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE store_id = ? AND status = 'active'");
            $stmt->execute([$store['id']]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    } else {
        // Fallback para loja teste se não achar nada
        $store = [
            'store_name' => 'Loja Não Encontrada',
            'store_title' => 'Erro 404',
            'store_description' => 'Verifique o nome da loja na URL.',
            'id' => 0
        ];
    }
} catch (Exception $e) {
    die("Erro SQL: " . $e->getMessage());
}

// ==================================================================
// 4. TRATAMENTO DE CORES (AQUI ESTAVA O BUG VISUAL)
// ==================================================================
// Força valores hexadecimais válidos se vier vazio do banco
$primaryColor = !empty($store['primary_color']) ? $store['primary_color'] : '#8b5cf6'; // Roxo (Padrão)
$secondaryColor = !empty($store['secondary_color']) ? $store['secondary_color'] : '#0f172a'; // Escuro (Padrão)
$logoUrl = $store['logo_url'] ?? '';

function formatMoney($val) { return 'R$ ' . number_format((float)$val, 2, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($store['store_name']) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?= $primaryColor ?>',   // Cor vinda do PHP
                        secondary: '<?= $secondaryColor ?>', // Cor vinda do PHP
                        dark: '#0f172a'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            background-color: <?= $secondaryColor ?>; /* Fallback CSS */
            color: white;
        }
        /* Efeito de vidro */
        .glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .glass-strong {
            background: rgba(20, 20, 20, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        /* Hover Cards */
        .product-card { transition: all 0.3s ease; }
        .product-card:hover { 
            transform: translateY(-5px); 
            border-color: <?= $primaryColor ?>;
            box-shadow: 0 10px 30px -10px <?= $primaryColor ?>40; 
        }
    </style>
</head>
<body class="bg-secondary min-h-screen flex flex-col overflow-x-hidden antialiased selection:bg-primary selection:text-white">

    <nav class="fixed top-0 w-full z-50 glass-strong h-20">
        <div class="max-w-7xl mx-auto px-6 h-full flex items-center justify-between">
            <div class="flex items-center gap-4">
                <?php if ($logoUrl): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" class="h-10 object-contain">
                <?php else: ?>
                    <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center font-black text-white text-lg shadow-lg shadow-primary/30">
                        <?= strtoupper(substr($store['store_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <span class="font-black text-xl uppercase tracking-tight text-white hidden md:block">
                    <?= htmlspecialchars($store['store_name']) ?>
                </span>
            </div>
            
            <button onclick="toggleCart()" class="relative p-3 glass rounded-xl hover:bg-white/10 transition group">
                <i data-lucide="shopping-cart" class="w-6 h-6 text-zinc-300 group-hover:text-white transition"></i>
                <span id="cartCountBadge" class="absolute -top-1 -right-1 bg-primary text-white text-[10px] font-black w-5 h-5 flex items-center justify-center rounded-full opacity-0 transition-opacity">0</span>
            </button>
        </div>
    </nav>

    <header class="relative pt-32 pb-16 text-center px-6">
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-3xl h-96 bg-primary/20 blur-[120px] rounded-full pointer-events-none -z-10"></div>

        <div class="relative z-10 max-w-4xl mx-auto">
            <span class="inline-block px-4 py-1.5 rounded-full glass text-xs font-bold uppercase tracking-widest mb-8 text-primary border border-primary/20 shadow-lg shadow-primary/10">
                Loja Oficial
            </span>
            
            <h1 class="text-5xl md:text-7xl font-black uppercase tracking-tighter mb-6 text-white leading-tight">
                <?= htmlspecialchars($store['store_title'] ?? $store['store_name']) ?>
            </h1>
            
            <p class="text-zinc-400 text-lg max-w-2xl mx-auto leading-relaxed">
                <?= htmlspecialchars($store['store_description'] ?? 'A melhor loja de itens para o seu gameplay.') ?>
            </p>
        </div>
    </header>

    <main class="flex-grow max-w-7xl mx-auto px-6 pb-24 w-full">
        <?php if (empty($products)): ?>
            <div class="flex flex-col items-center justify-center py-24 glass rounded-3xl text-center border-dashed border-2 border-white/10">
                <div class="w-20 h-20 bg-zinc-900 rounded-full flex items-center justify-center mb-6">
                    <i data-lucide="package-open" class="w-10 h-10 text-zinc-600"></i>
                </div>
                <h3 class="text-xl font-bold text-white mb-2">Sem produtos ainda</h3>
                <p class="text-zinc-500 max-w-md">Os produtos desta loja aparecerão aqui assim que forem cadastrados.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($products as $product): ?>
                <div class="product-card glass rounded-2xl p-4 flex flex-col h-full border border-white/5 relative group">
                    
                    <div class="aspect-square rounded-xl bg-black/40 mb-4 flex items-center justify-center overflow-hidden relative">
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" class="w-full h-full object-cover transition duration-700 group-hover:scale-110">
                        <?php else: ?>
                            <i data-lucide="box" class="w-12 h-12 text-zinc-700 group-hover:text-primary transition-colors"></i>
                        <?php endif; ?>
                        
                        <?php if (isset($product['stock']) && $product['stock'] == 0): ?>
                        <div class="absolute inset-0 bg-black/70 flex items-center justify-center backdrop-blur-[2px]">
                            <span class="text-xs font-black uppercase text-red-500 tracking-widest border border-red-500/30 px-3 py-1 rounded bg-red-500/10">Esgotado</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex-grow">
                        <h3 class="font-bold text-lg leading-tight uppercase text-white mb-2"><?= htmlspecialchars($product['name']) ?></h3>
                        <p class="text-xs text-zinc-500 line-clamp-2"><?= htmlspecialchars($product['description'] ?? '') ?></p>
                    </div>

                    <div class="mt-5 pt-4 border-t border-white/5 flex items-center justify-between">
                        <div>
                            <span class="block text-xs text-zinc-500 font-bold uppercase">Valor</span>
                            <span class="text-xl font-black text-primary"><?= formatMoney($product['price']) ?></span>
                        </div>
                        
                        <button onclick='addToCart(<?= json_encode($product) ?>)' 
                                class="bg-white text-black w-10 h-10 rounded-lg flex items-center justify-center hover:bg-primary hover:text-white transition-all shadow-lg hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                                <?= (isset($product['stock']) && $product['stock'] == 0) ? 'disabled' : '' ?>>
                            <i data-lucide="plus" class="w-5 h-5 font-bold"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="border-t border-white/5 bg-black/20 py-10 mt-auto">
        <div class="max-w-7xl mx-auto px-6 text-center md:text-left flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <p class="text-white font-bold text-sm">© <?= date('Y') ?> <?= htmlspecialchars($store['store_name']) ?></p>
                <p class="text-zinc-600 text-xs mt-1">Todos os direitos reservados.</p>
            </div>
            <div class="flex gap-4">
                <span class="text-zinc-600 text-xs font-bold uppercase">Powered by SplitStore</span>
            </div>
        </div>
    </footer>

    <div id="cartModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="toggleCart()"></div>
        
        <div class="absolute right-0 top-0 h-full w-full max-w-md bg-secondary border-l border-white/10 p-6 flex flex-col shadow-2xl transform transition-transform duration-300 translate-x-full" id="cartPanel">
            
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h2 class="text-2xl font-black uppercase text-white">Seu Carrinho</h2>
                    <p class="text-zinc-500 text-xs">Revise seus itens antes de finalizar</p>
                </div>
                <button onclick="toggleCart()" class="w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center text-white transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div id="cartItems" class="flex-1 overflow-y-auto space-y-3 pr-2 scrollbar-hide">
                </div>

            <div class="mt-6 pt-6 border-t border-white/10 bg-secondary">
                <div class="flex justify-between items-end mb-6">
                    <span class="text-zinc-400 font-bold uppercase text-xs pb-1">Total a pagar</span>
                    <span id="cartTotal" class="text-3xl font-black text-primary">R$ 0,00</span>
                </div>
                <button onclick="checkout()" class="w-full bg-primary text-white py-4 rounded-xl font-black uppercase text-sm hover:brightness-110 transition shadow-lg shadow-primary/25 flex items-center justify-center gap-2 group">
                    Ir para Pagamento
                    <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        // --- LÓGICA DO CARRINHO ---
        let cart = JSON.parse(localStorage.getItem('cart') || '[]');
        
        function updateCartDisplay() {
            const container = document.getElementById('cartItems');
            const badge = document.getElementById('cartCountBadge');
            const totalEl = document.getElementById('cartTotal');
            
            // Badge Count
            badge.innerText = cart.length;
            badge.style.opacity = cart.length > 0 ? '1' : '0';

            if(cart.length === 0) {
                container.innerHTML = `
                    <div class="h-full flex flex-col items-center justify-center text-center opacity-50">
                        <i data-lucide="shopping-basket" class="w-16 h-16 mb-4 text-zinc-600"></i>
                        <p class="text-zinc-400 font-bold">Seu carrinho está vazio</p>
                    </div>`;
                totalEl.innerText = 'R$ 0,00';
                lucide.createIcons();
                return;
            }

            let total = 0;
            container.innerHTML = cart.map((item, index) => {
                total += parseFloat(item.price);
                return `
                <div class="flex items-center gap-4 bg-white/5 p-3 rounded-xl border border-white/5 hover:border-white/10 transition group">
                    <div class="w-16 h-16 bg-black/30 rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden">
                        ${item.image_url ? `<img src="${item.image_url}" class="w-full h-full object-cover">` : `<i data-lucide="package" class="w-6 h-6 text-zinc-600"></i>`}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-sm text-white truncate">${item.name}</div>
                        <div class="text-xs text-primary font-bold mt-1">R$ ${parseFloat(item.price).toFixed(2).replace('.', ',')}</div>
                    </div>
                    <button onclick="removeFromCart(${index})" class="w-8 h-8 flex items-center justify-center rounded-lg text-zinc-500 hover:text-red-500 hover:bg-red-500/10 transition">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>`;
            }).join('');
            
            totalEl.innerText = 'R$ ' + total.toFixed(2).replace('.', ',');
            lucide.createIcons();
        }

        function addToCart(product) {
            cart.push(product);
            saveCart();
            updateCartDisplay();
            toggleCart(true);
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            saveCart();
            updateCartDisplay();
        }

        function saveCart() {
            localStorage.setItem('cart', JSON.stringify(cart));
        }

        function toggleCart(forceOpen = false) {
            const modal = document.getElementById('cartModal');
            const panel = document.getElementById('cartPanel');
            
            if (modal.classList.contains('hidden') || forceOpen) {
                modal.classList.remove('hidden');
                // Pequeno delay para animação CSS funcionar
                setTimeout(() => {
                    panel.classList.remove('translate-x-full');
                }, 10);
            } else {
                panel.classList.add('translate-x-full');
                setTimeout(() => {
                    modal.classList.add('hidden');
                }, 300);
            }
        }

        function checkout() {
            if(cart.length === 0) return alert('Seu carrinho está vazio!');
            alert('Integração de pagamento será configurada na próxima etapa!');
        }

        // Inicializa
        updateCartDisplay();
    </script>
</body>
</html>