<?php
/**
 * SPLITSTORE - GERENCIAMENTO DE PRODUTOS
 * Versão corrigida e testada
 */

// Ativa display de erros apenas para debug (remover em produção)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Verifica conexão com banco
$db_path = '../includes/db.php';
if (!file_exists($db_path)) {
    die("Erro: Arquivo de conexão não encontrado em: " . $db_path);
}
require_once $db_path;

// Proteção de acesso
if (!isset($_SESSION['store_logged']) || $_SESSION['store_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$store_id = $_SESSION['store_id'] ?? 0;
$store_name = $_SESSION['store_name'] ?? 'Minha Loja';
$store_plan = $_SESSION['store_plan'] ?? 'basic';

if ($store_id === 0) {
    die("Erro: ID da loja não encontrado na sessão.");
}

$message = "";
$messageType = "";

// BUSCAR PRODUTOS (sempre executar antes de qualquer ação)
try {
    $stmt = $pdo->prepare("
        SELECT p.*,
               (SELECT COUNT(*) FROM transactions WHERE product_id = p.id AND status = 'completed') as sales_count
        FROM products p
        WHERE p.store_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$store_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
    $products = [];
}

// CRIAR PRODUTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $commands = trim($_POST['commands'] ?? '');
    $stock = isset($_POST['stock']) && $_POST['stock'] !== '' ? (int)$_POST['stock'] : null;
    $image_url = trim($_POST['image_url'] ?? '');
    
    if (!empty($name) && $price > 0) {
        try {
            $sql = "INSERT INTO products (store_id, name, description, price, commands, stock, image_url, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$store_id, $name, $description, $price, $commands, $stock, $image_url])) {
                header('Location: products.php?success=created');
                exit;
            }
        } catch (PDOException $e) {
            $message = "Erro ao criar produto: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "Nome e preço são obrigatórios.";
        $messageType = "error";
    }
}

// EDITAR PRODUTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $commands = trim($_POST['commands'] ?? '');
    $stock = isset($_POST['stock']) && $_POST['stock'] !== '' ? (int)$_POST['stock'] : null;
    $image_url = trim($_POST['image_url'] ?? '');
    
    if ($product_id > 0 && !empty($name) && $price > 0) {
        try {
            $sql = "UPDATE products 
                    SET name=?, description=?, price=?, commands=?, stock=?, image_url=? 
                    WHERE id=? AND store_id=?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$name, $description, $price, $commands, $stock, $image_url, $product_id, $store_id])) {
                header('Location: products.php?success=updated');
                exit;
            }
        } catch (PDOException $e) {
            $message = "Erro ao atualizar: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// DELETAR PRODUTO
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND store_id = ?");
        $stmt->execute([$_GET['delete'], $store_id]);
        header('Location: products.php?success=deleted');
        exit;
    } catch (PDOException $e) {
        $message = "Erro ao deletar: " . $e->getMessage();
        $messageType = "error";
    }
}

// TOGGLE STATUS
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $current = $_GET['current'] ?? 'active';
    $newStatus = ($current === 'active') ? 'inactive' : 'active';
    
    try {
        $stmt = $pdo->prepare("UPDATE products SET status = ? WHERE id = ? AND store_id = ?");
        $stmt->execute([$newStatus, $id, $store_id]);
        header('Location: products.php?success=status_updated');
        exit;
    } catch (PDOException $e) {
        $message = "Erro ao alterar status: " . $e->getMessage();
        $messageType = "error";
    }
}

// MENSAGENS DE SUCESSO
if (isset($_GET['success'])) {
    $messages = [
        'created' => 'Produto criado com sucesso!',
        'updated' => 'Produto atualizado!',
        'deleted' => 'Produto removido!',
        'status_updated' => 'Status atualizado!'
    ];
    $message = $messages[$_GET['success']] ?? '';
    $messageType = "success";
}

// PRODUTO EM EDIÇÃO
$editingProduct = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND store_id = ?");
        $stmt->execute([$_GET['edit'], $store_id]);
        $editingProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching product: " . $e->getMessage());
    }
}

// Conta produtos ativos
$activeCount = count(array_filter($products, fn($p) => $p['status'] === 'active'));
$totalSales = array_sum(array_column($products, 'sales_count'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050505; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">Gestão de <span class="text-red-600">Produtos</span></h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">Crie e gerencie seus itens</p>
            </div>
            <button onclick="openModal()" class="bg-red-600 px-8 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-red-700 transition shadow-lg shadow-red-600/20 flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Novo Produto
            </button>
        </header>

        <?php if($message): ?>
            <div class="glass border-<?= $messageType === 'success' ? 'green' : 'red' ?>-600/20 text-<?= $messageType === 'success' ? 'green' : 'red' ?>-500 p-4 rounded-2xl mb-8 text-xs font-bold flex items-center gap-3">
                <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="glass p-6 rounded-2xl">
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Total de Produtos</p>
                <h3 class="text-3xl font-black"><?= count($products) ?></h3>
            </div>
            <div class="glass p-6 rounded-2xl">
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Ativos</p>
                <h3 class="text-3xl font-black text-green-500"><?= $activeCount ?></h3>
            </div>
            <div class="glass p-6 rounded-2xl">
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Total Vendido</p>
                <h3 class="text-3xl font-black text-red-500"><?= $totalSales ?></h3>
            </div>
        </div>

        <!-- Grid de Produtos -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if(empty($products)): ?>
                <div class="col-span-full glass rounded-3xl p-24 flex flex-col items-center justify-center opacity-30">
                    <i data-lucide="inbox" class="w-16 h-16 mb-6"></i>
                    <p class="font-bold uppercase text-xs tracking-widest">Nenhum produto cadastrado</p>
                </div>
            <?php else: ?>
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
                                <h3 class="font-black text-sm uppercase italic line-clamp-1"><?= htmlspecialchars($product['name']) ?></h3>
                                <span class="w-2 h-2 rounded-full flex-shrink-0 ml-2 mt-1 <?= $product['status'] === 'active' ? 'bg-green-500' : 'bg-zinc-700' ?>"></span>
                            </div>

                            <p class="text-zinc-500 text-xs mt-3 line-clamp-2 leading-relaxed">
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
                            <a href="?toggle=<?= $product['id'] ?>&current=<?= $product['status'] ?>" 
                               class="bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white text-[10px] font-black uppercase py-2 rounded-xl text-center transition">
                                <?= $product['status'] === 'active' ? 'Ocultar' : 'Ativar' ?>
                            </a>
                            <a href="?delete=<?= $product['id'] ?>" 
                               onclick="return confirm('Tem certeza que deseja deletar este produto?')"
                               class="bg-red-900/20 hover:bg-red-900/30 text-red-500 text-[10px] font-black uppercase py-2 rounded-xl text-center transition">
                                Deletar
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Criar/Editar -->
    <div id="modalProduct" class="<?= $editingProduct ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-2xl p-10 rounded-[3rem] border-red-600/20 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black italic uppercase">
                    <?= $editingProduct ? 'Editar' : 'Novo' ?> <span class="text-red-600">Produto</span>
                </h3>
                <button onclick="closeModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="<?= $editingProduct ? 'edit' : 'create' ?>">
                <?php if($editingProduct): ?>
                    <input type="hidden" name="product_id" value="<?= $editingProduct['id'] ?>">
                <?php endif; ?>
                
                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Nome do Produto</label>
                    <input type="text" name="name" placeholder="VIP Diamante" required 
                           value="<?= $editingProduct ? htmlspecialchars($editingProduct['name']) : '' ?>"
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Descrição</label>
                    <textarea name="description" rows="3" placeholder="Descrição do produto..."
                              class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition resize-none"><?= $editingProduct ? htmlspecialchars($editingProduct['description']) : '' ?></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Preço (R$)</label>
                        <input type="number" name="price" step="0.01" min="0" placeholder="14.99" required 
                               value="<?= $editingProduct ? $editingProduct['price'] : '' ?>"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Estoque (vazio = ilimitado)</label>
                        <input type="number" name="stock" min="0" placeholder="Ilimitado" 
                               value="<?= $editingProduct && $editingProduct['stock'] !== null ? $editingProduct['stock'] : '' ?>"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Comandos (um por linha)</label>
                    <textarea name="commands" rows="4" placeholder="give {player} diamond 64&#10;lp user {player} parent set vip" 
                              class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition resize-none font-mono"><?= $editingProduct ? htmlspecialchars($editingProduct['commands']) : '' ?></textarea>
                    <p class="text-[8px] text-zinc-700 ml-2">Use {player} para o nome do jogador</p>
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">URL da Imagem</label>
                    <input type="url" name="image_url" placeholder="https://i.imgur.com/..." 
                           value="<?= $editingProduct ? htmlspecialchars($editingProduct['image_url']) : '' ?>"
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                </div>

                <div class="flex gap-4 pt-6">
                    <button type="button" onclick="closeModal()" 
                            class="flex-1 py-4 font-black uppercase text-xs text-zinc-500 hover:text-white transition">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-red-600 py-4 rounded-xl font-black uppercase text-xs tracking-widest hover:bg-red-700 transition flex items-center justify-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <?= $editingProduct ? 'Salvar' : 'Criar Produto' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function openModal() {
            document.getElementById('modalProduct').classList.remove('hidden');
            lucide.createIcons();
        }
        
        function closeModal() {
            document.getElementById('modalProduct').classList.add('hidden');
            if (window.location.search.includes('edit=')) {
                window.location.href = 'products.php';
            }
        }
        
        <?php if($editingProduct): ?>
            openModal();
        <?php endif; ?>
    </script>
</body>
</html>