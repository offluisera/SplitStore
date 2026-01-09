<?php
/**
 * ============================================
 * SPLITSTORE - DASHBOARD CORRIGIDO
 * ============================================
 * Com tratamento de erros e fallbacks
 */

// HABILITAR ERROS PARA DEBUG
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Log de debug
error_log("=== DASHBOARD DEBUG ===");
error_log("Session data: " . print_r($_SESSION, true));

require_once '../includes/db.php';
require_once '../includes/auth_guard.php';

// Protege a página
requireLogin();

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'] ?? 'Sua Loja';
$store_plan = $_SESSION['store_plan'] ?? 'basic';

error_log("✅ Store ID: $store_id");
error_log("✅ Store Name: $store_name");

// ========================================
// BUSCAR MÉTRICAS COM TRATAMENTO DE ERROS
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
    error_log("🔍 Buscando métricas para store_id: $store_id");
    
    // 1. Vendas do mês
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
    $sales = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sales) {
        $metrics['vendas_mes'] = (float)$sales['total'];
        $metrics['pedidos_mes'] = (int)$sales['quantidade'];
        error_log("💰 Vendas do mês: " . $metrics['vendas_mes']);
    }
    
    // 2. Total histórico
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as total,
            COUNT(*) as quantidade
        FROM transactions 
        WHERE store_id = ? 
        AND status = 'completed'
    ");
    $stmt->execute([$store_id]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($total) {
        $metrics['vendas_total'] = (float)$total['total'];
        $metrics['pedidos_total'] = (int)$total['quantidade'];
        error_log("💵 Vendas total: " . $metrics['vendas_total']);
    }
    
    // 3. Ticket médio
    $metrics['ticket_medio'] = $metrics['pedidos_total'] > 0 
        ? $metrics['vendas_total'] / $metrics['pedidos_total'] 
        : 0;
    
    // 4. Últimas vendas
    $stmt = $pdo->prepare("
        SELECT *
        FROM transactions
        WHERE store_id = ?
        AND status = 'completed'
        ORDER BY paid_at DESC
        LIMIT 5
    ");
    $stmt->execute([$store_id]);
    $recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("📊 Total de vendas recentes: " . count($recent_sales));
    
} catch (PDOException $e) {
    error_log("❌ Erro ao buscar métricas: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    // Não redireciona - apenas mostra valores zerados
}

function formatMoney($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function formatNumber($value) {
    return number_format((int)$value, 0, ',', '.');
}

error_log("✅ Dashboard carregado com sucesso");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?= htmlspecialchars($store_name) ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #050505; 
            color: white; 
            scrollbar-width: none; 
        }
        
        ::-webkit-scrollbar { 
            display: none; 
        }
        
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
        
        /* Animação de loading */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="flex min-h-screen">

    <!-- SIDEBAR -->
    <?php 
    $sidebar_path = __DIR__ . '/components/sidebar.php';
    if (file_exists($sidebar_path)) {
        include $sidebar_path;
    } else {
        error_log("⚠️ Sidebar não encontrada em: $sidebar_path");
        // Sidebar de fallback
        echo '<aside class="w-72 bg-black border-r border-white/5 p-8">
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-black italic">
                        <span class="text-white">Split</span><span class="text-red-600">Store</span>
                    </h2>
                </div>
                <nav class="space-y-2">
                    <a href="dashboard.php" class="flex items-center gap-3 p-4 rounded-xl bg-red-600 text-white">
                        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                        <span class="font-bold">Dashboard</span>
                    </a>
                    <a href="produtos.php" class="flex items-center gap-3 p-4 rounded-xl hover:bg-white/5">
                        <i data-lucide="package" class="w-5 h-5"></i>
                        <span class="font-bold">Produtos</span>
                    </a>
                    <a href="logout.php" class="flex items-center gap-3 p-4 rounded-xl hover:bg-red-600/20 text-red-500 mt-auto">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                        <span class="font-bold">Sair</span>
                    </a>
                </nav>
              </aside>';
    }
    ?>

    <!-- CONTEÚDO PRINCIPAL -->
    <main class="flex-1 p-12 fade-in">
        
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
                <button onclick="location.reload()" 
                    class="glass p-4 rounded-2xl hover:border-red-600/40 transition group"
                    title="Atualizar dados">
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
                    <p class="text-[10px] text-zinc-800 mt-2">
                        Suas vendas aparecerão aqui
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

        <!-- Debug Info (remover em produção) -->
        <?php if (isset($_GET['debug'])): ?>
        <div class="glass rounded-2xl p-6 mt-8 border border-yellow-600/20">
            <h4 class="text-yellow-500 font-bold mb-4">🔍 DEBUG INFO</h4>
            <pre class="text-xs text-zinc-400 overflow-auto"><?php
                echo "Store ID: $store_id\n";
                echo "Store Name: $store_name\n";
                echo "Store Plan: $store_plan\n";
                echo "\nMétricas:\n";
                print_r($metrics);
                echo "\nSessão:\n";
                print_r($_SESSION);
            ?></pre>
        </div>
        <?php endif; ?>

    </main>

    <script>
        // Inicializar Lucide Icons
        lucide.createIcons();
        
        // Log de carregamento
        console.log('✅ Dashboard carregado');
        console.log('Store ID:', <?= json_encode($store_id) ?>);
        console.log('Store Name:', <?= json_encode($store_name) ?>);
        
        // Verificar se os scripts carregaram
        if (typeof lucide === 'undefined') {
            console.error('❌ Lucide não carregou');
        }
        
        if (typeof tailwind === 'undefined') {
            console.error('❌ Tailwind não carregou');
        }
    </script>
</body>
</html>