<?php
/**
 * ============================================
 * SPLITSTORE - PAINEL DO CLIENTE
 * ============================================
 * Dashboard completa para gerenciamento da loja
 */

session_start();
require_once '../includes/db.php';

// Proteção de acesso
if (!isset($_SESSION['store_logged'])) {
    header('Location: login.php');
    exit;
}

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];
$store_plan = $_SESSION['store_plan'];

// ========================================
// MÉTRICAS DA LOJA (CACHE DE 2 MINUTOS)
// ========================================

$metrics = [];
$recent_sales = [];
$top_products = [];
$cacheKey = "store_metrics_{$store_id}";

if ($redis && $redis->exists($cacheKey)) {
    $cached = json_decode($redis->get($cacheKey), true);
    $metrics = $cached['metrics'];
    $recent_sales = $cached['recent_sales'];
    $top_products = $cached['top_products'];
} else {
    try {
        // Vendas do mês atual
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total,
                   COUNT(*) as quantidade
            FROM transactions 
            WHERE store_id = ? 
            AND status = 'completed'
            AND MONTH(paid_at) = MONTH(CURRENT_DATE())
            AND YEAR(paid_at) = YEAR(CURRENT_DATE())
        ");
        $stmt->execute([$store_id]);
        $sales = $stmt->fetch();
        
        $metrics['vendas_mes'] = (float)$sales['total'];
        $metrics['pedidos_mes'] = (int)$sales['quantidade'];
        
        // Total histórico
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total,
                   COUNT(*) as quantidade
            FROM transactions 
            WHERE store_id = ? 
            AND status = 'completed'
        ");
        $stmt->execute([$store_id]);
        $total = $stmt->fetch();
        
        $metrics['vendas_total'] = (float)$total['total'];
        $metrics['pedidos_total'] = (int)$total['quantidade'];
        
        // Ticket médio
        $metrics['ticket_medio'] = $metrics['pedidos_total'] > 0 
            ? $metrics['vendas_total'] / $metrics['pedidos_total'] 
            : 0;
        
        // Taxa de conversão (simulada - será calculada com analytics)
        $metrics['conversao'] = 3.2;
        
        // Produtos cadastrados
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE store_id = ?");
        $stmt->execute([$store_id]);
        $metrics['total_produtos'] = (int)$stmt->fetchColumn();
        
        // Clientes únicos
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT customer_email) 
            FROM transactions 
            WHERE store_id = ? 
            AND status = 'completed'
        ");
        $stmt->execute([$store_id]);
        $metrics['clientes_unicos'] = (int)$stmt->fetchColumn();
        
        // Últimas 5 vendas
        $stmt = $pdo->prepare("
            SELECT 
                t.*,
                COALESCE(p.name, 'Produto não identificado') as product_name
            FROM transactions t
            LEFT JOIN products p ON t.product_id = p.id
            WHERE t.store_id = ?
            AND t.status = 'completed'
            ORDER BY t.paid_at DESC
            LIMIT 5
        ");
        $stmt->execute([$store_id]);
        $recent_sales = $stmt->fetchAll();
        
        // Top 5 produtos mais vendidos
        $stmt = $pdo->prepare("
            SELECT 
                p.name,
                p.price,
                COUNT(t.id) as vendas,
                SUM(t.amount) as receita
            FROM transactions t
            INNER JOIN products p ON t.product_id = p.id
            WHERE t.store_id = ?
            AND t.status = 'completed'
            GROUP BY p.id
            ORDER BY vendas DESC
            LIMIT 5
        ");
        $stmt->execute([$store_id]);
        $top_products = $stmt->fetchAll();
        
        // Cache por 2 minutos
        if ($redis) {
            $redis->setex($cacheKey, 120, json_encode([
                'metrics' => $metrics,
                'recent_sales' => $recent_sales,
                'top_products' => $top_products
            ]));
        }
        
    } catch (PDOException $e) {
        error_log("Store Dashboard Error: " . $e->getMessage());
        $metrics = [
            'vendas_mes' => 0,
            'pedidos_mes' => 0,
            'vendas_total' => 0,
            'pedidos_total' => 0,
            'ticket_medio' => 0,
            'conversao' => 0,
            'total_produtos' => 0,
            'clientes_unicos' => 0
        ];
    }
}

// Buscar credenciais da loja
$stmt = $pdo->prepare("SELECT client_secret, api_key, store_slug FROM stores WHERE id = ?");
$stmt->execute([$store_id]);
$credentials = $stmt->fetch();

// Funções auxiliares
function formatMoney($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function formatNumber($value) {
    return number_format($value, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?= htmlspecialchars($store_name) ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #050505; 
            color: white; 
            scrollbar-width: none; 
        }
        ::-webkit-scrollbar { display: none; }
        
        .glass { 
            background: rgba(255, 255, 255, 0.02); 
            backdrop-filter: blur(12px); 
            border: 1px solid rgba(255, 255, 255, 0.05); 
        }
        
        .sidebar-item:hover { 
            background: rgba(220, 38, 38, 0.05); 
            color: #dc2626; 
        }
        
        .metric-card {
            transition: all 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-4px);
            border-color: rgba(220, 38, 38, 0.3);
        }
        
        .pulse-dot {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }
    </style>
</head>
<body class="flex min-h-screen">

    <!-- SIDEBAR -->
    <aside class="w-72 border-r border-white/5 bg-black flex flex-col sticky top-0 h-screen">
        <!-- Logo/Loja -->
        <div class="p-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-12 h-12 bg-gradient-to-br from-red-600 to-red-900 rounded-2xl flex items-center justify-center font-black shadow-lg shadow-red-900/40">
                    <?= strtoupper(substr($store_name, 0, 1)) ?>
                </div>
                <div>
                    <h2 class="text-sm font-black uppercase italic tracking-tight"><?= htmlspecialchars($store_name) ?></h2>
                    <span class="text-[9px] font-bold uppercase tracking-widest text-red-500"><?= $store_plan ?></span>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="glass rounded-xl p-4 border-white/5">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[9px] font-black uppercase text-zinc-600">Este Mês</span>
                    <span class="text-xs font-black text-green-500">+12%</span>
                </div>
                <p class="text-xl font-black italic"><?= formatMoney($metrics['vendas_mes']) ?></p>
            </div>
        </div>

        <!-- Menu -->
        <nav class="flex-1 px-6 space-y-2 overflow-y-auto">
            <a href="dashboard.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest bg-red-600/10 text-red-600 border border-red-600/20">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Overview
            </a>
            
            <a href="products.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest text-zinc-500 sidebar-item">
                <i data-lucide="package" class="w-4 h-4"></i> Produtos
            </a>
            
            <a href="orders.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest text-zinc-500 sidebar-item">
                <i data-lucide="shopping-cart" class="w-4 h-4"></i> Pedidos
            </a>
            
            <a href="customers.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest text-zinc-500 sidebar-item">
                <i data-lucide="users" class="w-4 h-4"></i> Clientes
            </a>
            
            <div class="h-px bg-white/5 my-4"></div>
            
            <a href="customize.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest text-zinc-500 sidebar-item">
                <i data-lucide="palette" class="w-4 h-4"></i> Customização
            </a>
            
            <a href="servers.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest text-zinc-500 sidebar-item">
                <i data-lucide="server" class="w-4 h-4"></i> Servidores
            </a>
            
            <a href="integrations.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest text-zinc-500 sidebar-item">
                <i data-lucide="plug" class="w-4 h-4"></i> Integrações
            </a>
            
            <a href="settings.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest text-zinc-500 sidebar-item">
                <i data-lucide="settings" class="w-4 h-4"></i> Configurações
            </a>
        </nav>

        <!-- Footer -->
        <div class="p-6 border-t border-white/5">
            <a href="logout.php" class="flex items-center gap-2 text-zinc-600 hover:text-white transition text-[9px] font-black uppercase tracking-[0.2em]">
                <i data-lucide="log-out" class="w-3 h-3"></i> Sair
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-12">
        
        <!-- Header -->
        <header class="flex justify-between items-center mb-16">
            <div>
                <h1 class="text-4xl font-black italic uppercase tracking-tighter">
                    Bem-vindo de <span class="text-red-600">Volta</span>
                </h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-[0.3em] mt-2 italic flex items-center gap-2">
                    <span class="w-2 h-2 bg-green-500 rounded-full pulse-dot"></span>
                    Loja Online • Sincronizada
                </p>
            </div>
            
            <div class="flex gap-4">
                <button onclick="location.reload()" class="glass p-4 rounded-2xl hover:border-red-600/40 transition group">
                    <i data-lucide="refresh-cw" class="w-4 h-4 text-zinc-500 group-hover:text-red-600 group-hover:rotate-180 transition-all duration-500"></i>
                </button>
                
                <a href="<?= htmlspecialchars($credentials['store_slug']) ?>.splitstore.com.br" target="_blank" class="glass px-6 py-4 rounded-2xl hover:border-red-600/40 transition flex items-center gap-3 group">
                    <i data-lucide="external-link" class="w-4 h-4 text-zinc-500 group-hover:text-red-600 transition"></i>
                    <span class="text-xs font-black uppercase tracking-wider text-zinc-500 group-hover:text-white transition">Ver Loja</span>
                </a>
            </div>
        </header>

        <!-- Métricas Principais -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            
            <!-- Vendas do Mês -->
            <div class="metric-card glass p-8 rounded-[2.5rem] border-white/5">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-green-600/10 rounded-2xl flex items-center justify-center">
                        <i data-lucide="trending-up" class="w-6 h-6 text-green-600"></i>
                    </div>
                    <span class="text-xs font-black uppercase tracking-wider text-green-600/50">+12%</span>
                </div>
                <p class="text-zinc-600 text-[9px] font-black uppercase tracking-widest mb-2">Vendas do Mês</p>
                <h3 class="text-4xl font-black italic"><?= formatMoney($metrics['vendas_mes']) ?></h3>
                <p class="text-xs text-zinc-700 mt-2"><?= formatNumber($metrics['pedidos_mes']) ?> pedidos</p>
            </div>

            <!-- Total Histórico -->
            <div class="metric-card glass p-8 rounded-[2.5rem] border-white/5">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-600/10 rounded-2xl flex items-center justify-center">
                        <i data-lucide="dollar-sign" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[9px] font-black uppercase tracking-widest mb-2">Total Processado</p>
                <h3 class="text-4xl font-black italic"><?= formatMoney($metrics['vendas_total']) ?></h3>
                <p class="text-xs text-zinc-700 mt-2"><?= formatNumber($metrics['pedidos_total']) ?> vendas</p>
            </div>

            <!-- Ticket Médio -->
            <div class="metric-card glass p-8 rounded-[2.5rem] border-white/5">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-purple-600/10 rounded-2xl flex items-center justify-center">
                        <i data-lucide="receipt" class="w-6 h-6 text-purple-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[9px] font-black uppercase tracking-widest mb-2">Ticket Médio</p>
                <h3 class="text-4xl font-black italic"><?= formatMoney($metrics['ticket_medio']) ?></h3>
                <p class="text-xs text-zinc-700 mt-2">Por pedido</p>
            </div>

            <!-- Clientes -->
            <div class="metric-card glass p-8 rounded-[2.5rem] border-red-600/10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-red-600/10 rounded-2xl flex items-center justify-center">
                        <i data-lucide="users" class="w-6 h-6 text-red-600"></i>
                    </div>
                </div>
                <p class="text-red-600/50 text-[9px] font-black uppercase tracking-widest mb-2">Clientes Únicos</p>
                <h3 class="text-4xl font-black italic text-red-500"><?= formatNumber($metrics['clientes_unicos']) ?></h3>
                <p class="text-xs text-red-900 mt-2">Compradores</p>
            </div>
        </div>

        <!-- Grid Principal -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            
            <!-- Vendas Recentes (2/3) -->
            <div class="lg:col-span-2 glass rounded-[3rem] p-10">
                <div class="flex justify-between items-center mb-8">
                    <h4 class="text-xs font-black uppercase tracking-[0.3em] italic">Vendas Recentes</h4>
                    <a href="orders.php" class="text-[10px] font-black uppercase text-zinc-600 hover:text-red-600 transition tracking-wider">
                        Ver Todas →
                    </a>
                </div>
                
                <?php if (!empty($recent_sales)): ?>
                    <div class="space-y-4">
                        <?php foreach($recent_sales as $sale): ?>
                            <div class="flex items-center justify-between p-5 rounded-[1.5rem] bg-white/[0.02] border border-white/5 hover:border-red-600/20 transition">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-full bg-green-500/10 flex items-center justify-center">
                                        <i data-lucide="check" class="w-4 h-4 text-green-500"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase text-white">
                                            <?= htmlspecialchars($sale['product_name']) ?>
                                        </p>
                                        <p class="text-[9px] text-zinc-600 font-bold">
                                            <?= date('d/m/Y H:i', strtotime($sale['paid_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                                <span class="text-sm font-black italic text-green-500">
                                    + <?= formatMoney($sale['amount']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-16 opacity-30">
                        <i data-lucide="inbox" class="w-16 h-16 mx-auto mb-4"></i>
                        <p class="text-xs font-bold uppercase tracking-widest text-zinc-700">
                            Nenhuma venda ainda
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top Produtos (1/3) -->
            <div class="glass rounded-[3rem] p-10">
                <h4 class="text-xs font-black uppercase tracking-[0.3em] italic text-center mb-10">
                    Top Produtos
                </h4>
                
                <?php if (!empty($top_products)): ?>
                    <div class="space-y-6">
                        <?php foreach($top_products as $i => $product): ?>
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[9px] font-black uppercase text-zinc-500">
                                            #<?= $i + 1 ?> <?= htmlspecialchars($product['name']) ?>
                                        </span>
                                    </div>
                                    <span class="text-[10px] font-black text-red-600">
                                        <?= $product['vendas'] ?>x
                                    </span>
                                </div>
                                <div class="overflow-hidden h-2 text-xs flex rounded-full bg-zinc-900">
                                    <div style="width:<?= min(100, ($product['vendas'] / $top_products[0]['vendas']) * 100) ?>%" 
                                         class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-red-600">
                                    </div>
                                </div>
                                <p class="text-[9px] text-zinc-700 mt-1">
                                    <?= formatMoney($product['receita']) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 opacity-30">
                        <i data-lucide="package" class="w-12 h-12 mx-auto mb-4"></i>
                        <p class="text-xs font-bold uppercase text-zinc-700">
                            Sem dados ainda
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <a href="products.php?action=new" class="glass p-8 rounded-3xl hover:border-red-600/20 transition group">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-red-600/10 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                        <i data-lucide="plus" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase">Adicionar Produto</h3>
                        <p class="text-[9px] text-zinc-600 font-bold">Criar novo item</p>
                    </div>
                </div>
            </a>

            <a href="customize.php" class="glass p-8 rounded-3xl hover:border-red-600/20 transition group">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-blue-600/10 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                        <i data-lucide="palette" class="w-6 h-6 text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase">Personalizar Loja</h3>
                        <p class="text-[9px] text-zinc-600 font-bold">Cores e design</p>
                    </div>
                </div>
            </a>

            <a href="settings.php?tab=credentials" class="glass p-8 rounded-3xl hover:border-red-600/20 transition group">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-purple-600/10 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                        <i data-lucide="key" class="w-6 h-6 text-purple-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase">Credenciais API</h3>
                        <p class="text-[9px] text-zinc-600 font-bold">Plugin & Integrações</p>
                    </div>
                </div>
            </a>
        </div>

    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>