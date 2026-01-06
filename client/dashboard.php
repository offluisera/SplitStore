<?php
/**
 * ============================================
 * SPLITSTORE - PAINEL DO CLIENTE (CORRIGIDO)
 * ============================================
 */

session_start();
require_once '../includes/db.php';

// Proteção de acesso
if (!isset($_SESSION['store_logged']) || $_SESSION['store_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];
$store_plan = $_SESSION['store_plan'];

// ========================================
// MÉTRICAS APENAS DA LOJA DO USUÁRIO
// ========================================

$metrics = [
    'vendas_mes' => 0,
    'pedidos_mes' => 0,
    'vendas_total' => 0,
    'pedidos_total' => 0,
    'ticket_medio' => 0
];
$recent_sales = [];

try {
    // 1. Vendas do mês APENAS desta loja
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as total,
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
    
    // 2. Total histórico APENAS desta loja
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as total,
            COUNT(*) as quantidade
        FROM transactions 
        WHERE store_id = ? 
        AND status = 'completed'
    ");
    $stmt->execute([$store_id]);
    $total = $stmt->fetch();
    
    $metrics['vendas_total'] = (float)$total['total'];
    $metrics['pedidos_total'] = (int)$total['quantidade'];
    
    // 3. Ticket médio
    $metrics['ticket_medio'] = $metrics['pedidos_total'] > 0 
        ? $metrics['vendas_total'] / $metrics['pedidos_total'] 
        : 0;
    
    // 4. Últimas 5 vendas APENAS desta loja
    $stmt = $pdo->prepare("
        SELECT *
        FROM transactions
        WHERE store_id = ?
        AND status = 'completed'
        ORDER BY paid_at DESC
        LIMIT 5
    ");
    $stmt->execute([$store_id]);
    $recent_sales = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
}

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
        
        .metric-card {
            transition: all 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-4px);
            border-color: rgba(220, 38, 38, 0.3);
        }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        
        <!-- Header -->
        <header class="flex justify-between items-center mb-16">
            <div>
                <h1 class="text-4xl font-black italic uppercase tracking-tighter">
                    Painel de <span class="text-red-600">Controle</span>
                </h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-[0.3em] mt-2">
                    Métricas da sua loja em tempo real
                </p>
            </div>
            
            <div class="flex gap-4">
                <button onclick="location.reload()" class="glass p-4 rounded-2xl hover:border-red-600/40 transition group">
                    <i data-lucide="refresh-cw" class="w-4 h-4 text-zinc-500 group-hover:text-red-600 group-hover:rotate-180 transition-all duration-500"></i>
                </button>
            </div>
        </header>

        <!-- Métricas Principais -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
            
            <!-- Vendas do Mês -->
            <div class="metric-card glass p-8 rounded-[2.5rem] border-white/5">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-green-600/10 rounded-2xl flex items-center justify-center">
                        <i data-lucide="trending-up" class="w-6 h-6 text-green-600"></i>
                    </div>
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
            <div class="metric-card glass p-8 rounded-[2.5rem] border-red-600/10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-red-600/10 rounded-2xl flex items-center justify-center">
                        <i data-lucide="receipt" class="w-6 h-6 text-red-600"></i>
                    </div>
                </div>
                <p class="text-red-600/50 text-[9px] font-black uppercase tracking-widest mb-2">Ticket Médio</p>
                <h3 class="text-4xl font-black italic text-red-500"><?= formatMoney($metrics['ticket_medio']) ?></h3>
                <p class="text-xs text-red-900 mt-2">Por pedido</p>
            </div>
        </div>

        <!-- Vendas Recentes -->
        <div class="glass rounded-[3rem] p-10">
            <div class="flex justify-between items-center mb-8">
                <h4 class="text-xs font-black uppercase tracking-[0.3em] italic">Vendas Recentes</h4>
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
                                        Venda #<?= $sale['id'] ?>
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

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
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

            <a href="servers.php" class="glass p-8 rounded-3xl hover:border-red-600/20 transition group">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-purple-600/10 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                        <i data-lucide="server" class="w-6 h-6 text-purple-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase">Conectar Servidor</h3>
                        <p class="text-[9px] text-zinc-600 font-bold">Configurar plugin</p>
                    </div>
                </div>
            </a>

            <a href="settings.php" class="glass p-8 rounded-3xl hover:border-red-600/20 transition group">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-red-600/10 rounded-xl flex items-center justify-center group-hover:scale-110 transition">
                        <i data-lucide="settings" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase">Configurações</h3>
                        <p class="text-[9px] text-zinc-600 font-bold">Gateway e API</p>
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