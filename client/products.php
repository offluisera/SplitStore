<?php
/**
 * ============================================
 * PRODUCTS.PHP - VERSÃO CORRIGIDA ✅
 * ============================================
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/db.php';
require_once '../includes/auth_guard.php';

requireAccess(__FILE__);

$store_id = $_SESSION['store_id'] ?? 0;
$store_name = $_SESSION['store_name'] ?? 'Minha Loja';

if ($store_id === 0) {
    die("❌ ERRO: ID da loja não encontrado na sessão.");
}

$message = "";
$messageType = "";

// ========================================
// DEBUG MODE (remover em produção)
// ========================================
$debug = isset($_GET['debug']);

if ($debug) {
    echo "<pre style='background:#000;color:#0f0;padding:20px;'>";
    echo "🔍 DEBUG MODE\n";
    echo "Store ID: {$store_id}\n";
    echo "Store Name: {$store_name}\n";
    echo "</pre>";
}

// ========================================
// CRIAR CATEGORIA
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_category') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? strtolower(str_replace(' ', '-', $name)));
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'box');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $order_position = (int)($_POST['order_position'] ?? 0);
    
    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO categories (store_id, parent_id, name, slug, description, icon, order_position, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            if ($stmt->execute([$store_id, $parent_id, $name, $slug, $description, $icon, $order_position])) {
                header('Location: products.php?success=category_created');
                exit;
            }
        } catch (PDOException $e) {
            $message = "❌ Erro ao criar categoria: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// ========================================
// CRIAR PRODUTO ✅ CORRIGIDO
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_product') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $commands = trim($_POST['commands'] ?? '');
    $stock = isset($_POST['stock']) && $_POST['stock'] !== '' ? (int)$_POST['stock'] : null;
    $image_url = trim($_POST['image_url'] ?? '');
    
    // ✅ VALIDAÇÃO
    if (empty($name)) {
        $message = "❌ Nome do produto é obrigatório.";
        $messageType = "error";
    } elseif ($price <= 0) {
        $message = "❌ Preço deve ser maior que zero.";
        $messageType = "error";
    } else {
        try {
            $sql = "INSERT INTO products (store_id, category_id, name, description, price, commands, stock, image_url, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$store_id, $category_id, $name, $description, $price, $commands, $stock, $image_url])) {
                $product_id = $pdo->lastInsertId();
                
                // ✅ LOG DE SUCESSO
                error_log("✅ Produto criado: ID={$product_id}, Nome={$name}, Store={$store_id}");
                
                header('Location: products.php?success=product_created&id=' . $product_id);
                exit;
            } else {
                $message = "❌ Falha ao executar INSERT.";
                $messageType = "error";
            }
        } catch (PDOException $e) {
            $message = "❌ Erro SQL: " . $e->getMessage();
            $messageType = "error";
            error_log("❌ SQL Error: " . $e->getMessage());
        }
    }
}

// ========================================
// EDITAR PRODUTO
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $commands = trim($_POST['commands'] ?? '');
    $stock = isset($_POST['stock']) && $_POST['stock'] !== '' ? (int)$_POST['stock'] : null;
    $image_url = trim($_POST['image_url'] ?? '');
    
    if ($product_id > 0 && !empty($name) && $price > 0) {
        try {
            $sql = "UPDATE products 
                    SET name=?, description=?, price=?, category_id=?, commands=?, stock=?, image_url=?, updated_at=NOW() 
                    WHERE id=? AND store_id=?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$name, $description, $price, $category_id, $commands, $stock, $image_url, $product_id, $store_id])) {
                header('Location: products.php?success=product_updated');
                exit;
            }
        } catch (PDOException $e) {
            $message = "❌ Erro: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// ========================================
// DELETAR PRODUTO
// ========================================
if (isset($_GET['delete_product']) && is_numeric($_GET['delete_product'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND store_id = ?");
        $stmt->execute([$_GET['delete_product'], $store_id]);
        header('Location: products.php?success=product_deleted');
        exit;
    } catch (PDOException $e) {
        $message = "❌ Erro: " . $e->getMessage();
        $messageType = "error";
    }
}

// ========================================
// DELETAR CATEGORIA
// ========================================
if (isset($_GET['delete_category']) && is_numeric($_GET['delete_category'])) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt->execute([$_GET['delete_category']]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            header('Location: products.php?error=category_has_products');
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND store_id = ?");
        $stmt->execute([$_GET['delete_category'], $store_id]);
        header('Location: products.php?success=category_deleted');
        exit;
    } catch (PDOException $e) {
        $message = "❌ Erro: " . $e->getMessage();
        $messageType = "error";
    }
}

// ========================================
// BUSCAR DADOS ✅ CORRIGIDO
// ========================================

// Categorias
try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count,
               p.name as parent_name
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        WHERE c.store_id = ?
        ORDER BY c.order_position ASC, c.name ASC
    ");
    $stmt->execute([$store_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($debug) {
        echo "<pre style='background:#000;color:#0f0;padding:20px;'>";
        echo "📂 Categorias encontradas: " . count($categories) . "\n";
        print_r($categories);
        echo "</pre>";
    }
} catch (PDOException $e) {
    error_log("❌ Error fetching categories: " . $e->getMessage());
    $categories = [];
}

// Produtos ✅ QUERY CORRIGIDA
try {
    $stmt = $pdo->prepare("
        SELECT p.*,
               c.name as category_name,
               (SELECT COUNT(*) FROM transactions WHERE product_id = p.id AND status = 'completed') as sales_count
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.store_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$store_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($debug) {
        echo "<pre style='background:#000;color:#0f0;padding:20px;'>";
        echo "📦 Produtos encontrados: " . count($products) . "\n";
        print_r($products);
        echo "</pre>";
    }
} catch (PDOException $e) {
    error_log("❌ Error fetching products: " . $e->getMessage());
    $products = [];
}

// Mensagens de Sucesso
if (isset($_GET['success'])) {
    $messages = [
        'product_created' => '✅ Produto criado com sucesso!',
        'product_updated' => '✅ Produto atualizado!',
        'product_deleted' => '✅ Produto removido!',
        'category_created' => '✅ Categoria criada!',
        'category_deleted' => '✅ Categoria removida!'
    ];
    $message = $messages[$_GET['success']] ?? '';
    $messageType = "success";
    
    if (isset($_GET['id'])) {
        $message .= " (ID: " . $_GET['id'] . ")";
    }
}

if (isset($_GET['error'])) {
    $errors = [
        'category_has_products' => '❌ Não é possível deletar uma categoria com produtos!'
    ];
    $message = $errors[$_GET['error']] ?? '❌ Erro desconhecido';
    $messageType = "error";
}

// Produto em Edição
$editingProduct = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND store_id = ?");
        $stmt->execute([$_GET['edit'], $store_id]);
        $editingProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("❌ Error fetching product: " . $e->getMessage());
    }
}

// Estatísticas
$activeProducts = count(array_filter($products, fn($p) => $p['status'] === 'active'));
$totalSales = array_sum(array_column($products, 'sales_count'));
$totalCategories = count($categories);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Produtos | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #000; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .tab-button.active { background: rgba(220, 38, 38, 0.1); color: #dc2626; border-color: rgba(220, 38, 38, 0.3); }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        
        <!-- Header -->
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">
                    Gestão de <span class="text-red-600">Produtos</span>
                </h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">
                    Gerencie seus produtos e categorias
                </p>
            </div>
            
            <div class="flex gap-3">
                <button onclick="openCategoryModal()" class="glass px-6 py-3 rounded-2xl hover:border-blue-600/40 transition flex items-center gap-2 group">
                    <i data-lucide="folder-plus" class="w-4 h-4 text-blue-600 group-hover:scale-110 transition"></i>
                    <span class="text-xs font-black uppercase tracking-wider">Nova Categoria</span>
                </button>
                
                <button onclick="openProductModal()" class="bg-red-600 px-8 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-red-700 transition shadow-lg shadow-red-600/20 flex items-center gap-2">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Novo Produto
                </button>
            </div>
        </header>

        <?php if($message): ?>
            <div class="glass border-<?= $messageType === 'success' ? 'green' : 'red' ?>-600/20 text-<?= $messageType === 'success' ? 'green' : 'red' ?>-500 p-4 rounded-2xl mb-8 text-xs font-bold flex items-center gap-3 animate-in">
                <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Debug Badge -->
        <?php if ($debug): ?>
        <div class="glass border-yellow-600/20 bg-yellow-600/5 p-4 rounded-2xl mb-8 text-xs font-bold flex items-center gap-3">
            <i data-lucide="bug" class="w-5 h-5 text-yellow-500"></i>
            <span class="text-yellow-500">Modo DEBUG ativado</span>
            <a href="products.php" class="ml-auto text-yellow-600 hover:underline">Desativar</a>
        </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="glass p-6 rounded-2xl">
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Total Produtos</p>
                <h3 class="text-3xl font-black"><?= count($products) ?></h3>
            </div>
            <div class="glass p-6 rounded-2xl">
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Ativos</p>
                <h3 class="text-3xl font-black text-green-500"><?= $activeProducts ?></h3>
            </div>
            <div class="glass p-6 rounded-2xl">
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Categorias</p>
                <h3 class="text-3xl font-black text-blue-500"><?= $totalCategories ?></h3>
            </div>
            <div class="glass p-6 rounded-2xl">
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Total Vendido</p>
                <h3 class="text-3xl font-black text-red-500"><?= $totalSales ?></h3>
            </div>
        </div>

        <!-- Tabs -->
        <div class="glass rounded-2xl p-2 flex gap-2 mb-8 overflow-x-auto">
            <button onclick="switchTab('products')" class="tab-button flex-1 px-6 py-3 rounded-xl font-bold text-sm transition whitespace-nowrap active">
                <i data-lucide="package" class="w-4 h-4 inline mr-2"></i>Produtos
            </button>
            <button onclick="switchTab('categories')" class="tab-button flex-1 px-6 py-3 rounded-xl font-bold text-sm text-zinc-500 transition whitespace-nowrap">
                <i data-lucide="folder" class="w-4 h-4 inline mr-2"></i>Categorias
            </button>
        </div>

        <!-- Tab: Produtos -->
        <div id="tab-products" class="tab-content">
            <?php if(empty($products)): ?>
                <div class="glass rounded-3xl p-24 flex flex-col items-center justify-center opacity-30">
                    <i data-lucide="inbox" class="w-16 h-16 mb-6"></i>
                    <p class="font-bold uppercase text-xs tracking-widest mb-2">Nenhum produto cadastrado</p>
                    <a href="?debug=1" class="text-xs text-blue-500 hover:underline">Ativar modo DEBUG</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach($products as $product): ?>
                        <div class="glass p-6 rounded-3xl border border-white/5 hover:border-red-600/20 transition-all flex flex-col">
                            
                            <!-- Imagem -->
                            <div class="aspect-video bg-black/40 rounded-2xl mb-4 flex items-center justify-center overflow-hidden">
                                <?php if (!empty($product['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>"
                                         class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i data-lucide="package" class="w-12 h-12 text-zinc-800"></i>
                                <?php endif; ?>
                            </div>

                            <!-- Info -->
                            <div class="flex-1">
                                <div class="flex items-start justify-between mb-2">
                                    <h3 class="font-black text-sm uppercase italic line-clamp-1 flex-1">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </h3>
                                    <span class="w-2 h-2 rounded-full flex-shrink-0 ml-2 mt-1 <?= $product['status'] === 'active' ? 'bg-green-500' : 'bg-zinc-700' ?>"></span>
                                </div>

                                <?php if (!empty($product['category_name'])): ?>
                                    <span class="inline-block px-2 py-1 rounded bg-blue-500/10 text-blue-500 text-[9px] font-bold uppercase mb-2">
                                        <?= htmlspecialchars($product['category_name']) ?>
                                    </span>
                                <?php endif; ?>

                                <p class="text-zinc-500 text-xs mt-2 line-clamp-2 leading-relaxed">
                                    <?= htmlspecialchars($product['description']) ?>
                                </p>

                                <div class="mt-4 pt-4 border-t border-white/5">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-2xl font-black text-white">R$ <?= number_format($product['price'], 2, ',', '.') ?></p>
                                            <p class="text-[9px] text-zinc-600 font-bold uppercase"><?= $product['sales_count'] ?> vendas</p>
                                        </div>
                                        <?php if ($product['stock'] !== null): ?>
                                            <div class="text-right">
                                                <p class="text-xs font-black"><?= $product['stock'] ?></p>
                                                <p class="text-[9px] text-zinc-600">estoque</p>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-[9px] font-black uppercase text-green-600">Ilimitado</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Ações -->
                            <div class="grid grid-cols-3 gap-2 mt-4">
                                <a href="?edit=<?= $product['id'] ?>" 
                                   class="bg-blue-900/20 hover:bg-blue-900/30 text-blue-500 text-[10px] font-black uppercase py-2 rounded-xl text-center transition">
                                    Editar
                                </a>
                                <button onclick="duplicateProduct(<?= $product['id'] ?>)" 
                                        class="bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white text-[10px] font-black uppercase py-2 rounded-xl transition">
                                    Duplicar
                                </button>
                                <a href="?delete_product=<?= $product['id'] ?>" 
                                   onclick="return confirm('Tem certeza que deseja deletar este produto?')"
                                   class="bg-red-900/20 hover:bg-red-900/30 text-red-500 text-[10px] font-black uppercase py-2 rounded-xl text-center transition">
                                    Deletar
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Categorias -->
        <div id="tab-categories" class="tab-content hidden">
            <?php if(empty($categories)): ?>
                <div class="glass rounded-3xl p-24 flex flex-col items-center justify-center opacity-30">
                    <i data-lucide="folder-open" class="w-16 h-16 mb-6"></i>
                    <p class="font-bold uppercase text-xs tracking-widest">Nenhuma categoria criada</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach($categories as $cat): ?>
                        <div class="glass p-6 rounded-2xl border border-white/5 hover:border-blue-600/20 transition">
                            <div class="flex items-start justify-between mb-4">
                                <div class="w-12 h-12 bg-blue-600/10 rounded-xl flex items-center justify-center">
                                    <i data-lucide="<?= htmlspecialchars($cat['icon'] ?? 'folder') ?>" class="w-6 h-6 text-blue-600"></i>
                                </div>
                                <span class="px-3 py-1 rounded-lg text-[9px] font-black uppercase <?= $cat['status'] === 'active' ? 'bg-green-500/10 text-green-500' : 'bg-zinc-700/50 text-zinc-500' ?>">
                                    <?= $cat['status'] ?>
                                </span>
                            </div>
                            
                            <h3 class="font-black text-lg uppercase mb-2">
                                <?= htmlspecialchars($cat['name']) ?>
                            </h3>
                            
                            <?php if (!empty($cat['parent_name'])): ?>
                                <p class="text-xs text-zinc-500 mb-2">
                                    Subcategoria de: <span class="text-blue-500"><?= htmlspecialchars($cat['parent_name']) ?></span>
                                </p>
                            <?php endif; ?>
                            
                            <p class="text-xs text-zinc-600 line-clamp-2 mb-4">
                                <?= htmlspecialchars($cat['description'] ?? 'Sem descrição') ?>
                            </p>
                            
                            <div class="flex items-center justify-between pt-4 border-t border-white/5">
                                <span class="text-xs text-zinc-500">
                                    <?= $cat['product_count'] ?> produto(s)
                                </span>
                                <button onclick="confirm('Tem certeza?') && (window.location.href='?delete_category=<?= $cat['id'] ?>')" 
                                        class="text-red-500 hover:text-red-400 text-xs font-bold">
                                    Deletar
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Modal: Criar/Editar Produto -->
    <div id="productModal" class="<?= $editingProduct ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-3xl p-10 rounded-[3rem] border-red-600/20 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black italic uppercase">
                    <?= $editingProduct ? 'Editar' : 'Novo' ?> <span class="text-red-600">Produto</span>
                </h3>
                <button onclick="closeProductModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <form action="" method="POST" class="space-y-6">
                <input type="hidden" name="action" value="<?= $editingProduct ? 'edit_product' : 'create_product' ?>">
                <?php if($editingProduct): ?>
                    <input type="hidden" name="product_id" value="<?= $editingProduct['id'] ?>">
                <?php endif; ?>
                
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Nome do Produto *</label>
                        <input type="text" name="name" placeholder="VIP Diamante" required 
                               value="<?= $editingProduct ? htmlspecialchars($editingProduct['name']) : '' ?>"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>

                    <div>
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Categoria</label>
                        <select name="category_id" class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                            <option value="">Sem categoria</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($editingProduct && $editingProduct['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                    <?php if(!empty($cat['parent_name'])): ?>(<?= htmlspecialchars($cat['parent_name']) ?>)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Descrição</label>
                    <textarea name="description" rows="3" placeholder="Descrição detalhada do produto..."
                              class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition resize-none"><?= $editingProduct ? htmlspecialchars($editingProduct['description']) : '' ?></textarea>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Preço (R$) *</label>
                        <input type="number" name="price" step="0.01" min="0.01" placeholder="14.99" required 
                               value="<?= $editingProduct ? $editingProduct['price'] : '' ?>"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                    <div>
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Estoque</label>
                        <input type="number" name="stock" min="0" placeholder="Ilimitado" 
                               value="<?= $editingProduct && $editingProduct['stock'] !== null ? $editingProduct['stock'] : '' ?>"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                        <p class="text-[8px] text-zinc-700 mt-2 ml-2">Deixe vazio para estoque ilimitado</p>
                    </div>
                </div>

                <div>
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">URL da Imagem</label>
                    <input type="url" name="image_url" placeholder="https://i.imgur.com/..." 
                           value="<?= $editingProduct ? htmlspecialchars($editingProduct['image_url']) : '' ?>"
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                </div>

                <div>
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Comandos (um por linha)</label>
                    <textarea name="commands" rows="6" placeholder="give {player} diamond 64&#10;lp user {player} parent set vip&#10;tell {player} Você recebeu VIP Diamante!" 
                              class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition resize-none font-mono"><?= $editingProduct ? htmlspecialchars($editingProduct['commands']) : '' ?></textarea>
                    <p class="text-[8px] text-zinc-700 mt-2 ml-2">Use {player} para o nome do jogador | {uuid} para UUID</p>
                </div>

                <div class="flex gap-4 pt-6">
                    <button type="button" onclick="closeProductModal()" 
                            class="flex-1 py-4 font-black uppercase text-xs text-zinc-500 hover:text-white transition">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-red-600 py-4 rounded-xl font-black uppercase text-xs tracking-widest hover:bg-red-700 transition flex items-center justify-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <?= $editingProduct ? 'Salvar Alterações' : 'Criar Produto' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Criar Categoria -->
    <div id="categoryModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-2xl p-10 rounded-[3rem] border-blue-600/20">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black italic uppercase">
                    Nova <span class="text-blue-600">Categoria</span>
                </h3>
                <button onclick="closeCategoryModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <form action="" method="POST" class="space-y-6">
                <input type="hidden" name="action" value="create_category">
                
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Nome *</label>
                        <input type="text" name="name" placeholder="VIPs" required 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-blue-600 transition">
                    </div>

                    <div>
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Slug</label>
                        <input type="text" name="slug" placeholder="vips" 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-blue-600 transition">
                        <p class="text-[8px] text-zinc-700 mt-2 ml-2">Deixe vazio para gerar automaticamente</p>
                    </div>
                </div>

                <div>
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Descrição</label>
                    <textarea name="description" rows="3" placeholder="Descrição da categoria..."
                              class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-blue-600 transition resize-none"></textarea>
                </div>

                <div class="grid md:grid-cols-3 gap-6">
                    <div>
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Categoria Pai</label>
                        <select name="parent_id" class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-blue-600 transition">
                            <option value="">Categoria principal</option>
                            <?php 
                            $parentCategories = array_filter($categories, fn($c) => $c['parent_id'] === null);
                            foreach($parentCategories as $parent): 
                            ?>
                                <option value="<?= $parent['id'] ?>"><?= htmlspecialchars($parent['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Ícone (Lucide)</label>
                        <input type="text" name="icon" placeholder="box" value="box"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-blue-600 transition">
                    </div>

                    <div>
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Ordem</label>
                        <input type="number" name="order_position" min="0" value="0"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-blue-600 transition">
                    </div>
                </div>

                <div class="flex gap-4 pt-6">
                    <button type="button" onclick="closeCategoryModal()" 
                            class="flex-1 py-4 font-black uppercase text-xs text-zinc-500 hover:text-white transition">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-blue-600 py-4 rounded-xl font-black uppercase text-xs tracking-widest hover:bg-blue-700 transition flex items-center justify-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Criar Categoria
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.add('hidden'));
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
                btn.classList.add('text-zinc-500');
            });
            
            document.getElementById('tab-' + tabName).classList.remove('hidden');
            event.target.classList.add('active');
            event.target.classList.remove('text-zinc-500');
        }
        
        function openProductModal() {
            document.getElementById('productModal').classList.remove('hidden');
            lucide.createIcons();
        }
        
        function closeProductModal() {
            document.getElementById('productModal').classList.add('hidden');
            if (window.location.search.includes('edit=')) {
                window.location.href = 'products.php';
            }
        }
        
        function openCategoryModal() {
            document.getElementById('categoryModal').classList.remove('hidden');
            lucide.createIcons();
        }
        
        function closeCategoryModal() {
            document.getElementById('categoryModal').classList.add('hidden');
        }
        
        function duplicateProduct(id) {
            if (confirm('Criar uma cópia deste produto?')) {
                alert('Funcionalidade em desenvolvimento');
            }
        }
        
        <?php if($editingProduct): ?>
            openProductModal();
        <?php endif; ?>
    </script>
</body>
</html>