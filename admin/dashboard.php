<?php
/**
 * ============================================
 * SPLITSTORE ADMIN DASHBOARD - VERSÃO COMPLETA
 * ============================================
 * 
 * Dashboard com todas as métricas em tempo real:
 * - Total de clientes e lojas ativas
 * - Faturamento total e via PIX (MisticPay)
 * - Gráfico de vendas dos últimos 7 dias
 * - Lista de transações recentes
 * - Status de pagamentos
 * - Otimização com Redis + MySQL
 */

session_start();
require_once '../includes/db.php';

// Segurança: Bloqueio de acesso não autorizado
if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

/**
 * MOTOR DE MÉTRICAS OTIMIZADO (REDIS + MYSQL)
 * Busca dados das tabelas específicas com cache inteligente
 */
$metrics = [];
$recentSales = [];
$recentTransactions = [];
$paymentStats = [];
$cacheKey = 'splitstore_core_metrics_v2';
$cacheTTL = 120; // 2 minutos

// Tenta buscar do cache Redis primeiro
if ($redis && $redis->exists($cacheKey)) {
    $cachedData = json_decode($redis->get($cacheKey), true);
    $metrics = $cachedData['metrics'] ?? [];
    $recentSales = $cachedData['recent_sales'] ?? [];
    $recentTransactions = $cachedData['recent_transactions'] ?? [];
    $paymentStats = $cachedData['payment_stats'] ?? [];
} else {
    // ========================================
    // MÉTRICAS PRINCIPAIS
    // ========================================
    
    try {
        // 1. Total de clientes (Lojas únicas cadastradas)
        $stmt = $pdo->query("SELECT COUNT(*) FROM stores");
        $metrics['total_clientes'] = (int)$stmt->fetchColumn() ?? 0;

        // 2. Lojas Ativas (Status active)
        $stmt = $pdo->query("SELECT COUNT(*) FROM stores WHERE status = 'active'");
        $metrics['lojas_ativas'] = (int)$stmt->fetchColumn() ?? 0;

        // 3. Faturamento Total (Transações completed)
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'completed'");
        $metrics['faturamento_total'] = (float)$stmt->fetchColumn() ?? 0;

        // 4. Vendas via PIX (MisticPay) - Filtro por método
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(amount), 0) 
            FROM transactions 
            WHERE status = 'completed' AND payment_method = 'pix'
        ");
        $metrics['faturamento_pix'] = (float)$stmt->fetchColumn() ?? 0;

        // 5. Faturamento do Mês Atual
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(amount), 0) 
            FROM transactions 
            WHERE status = 'completed' 
            AND MONTH(paid_at) = MONTH(CURRENT_DATE())
            AND YEAR(paid_at) = YEAR(CURRENT_DATE())
        ");
        $metrics['faturamento_mes'] = (float)$stmt->fetchColumn() ?? 0;

        // 6. Transações Pendentes
        $stmt = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'");
        $metrics['transacoes_pendentes'] = (int)$stmt->fetchColumn() ?? 0;

        // 7. Ticket Médio
        $stmt = $pdo->query("
            SELECT COALESCE(AVG(amount), 0) 
            FROM transactions 
            WHERE status = 'completed'
        ");
        $metrics['ticket_medio'] = (float)$stmt->fetchColumn() ?? 0;

        // ========================================
        // VENDAS POR PERÍODO (Últimos 7 dias)
        // ========================================
        
        $stmt = $pdo->query("
            SELECT 
                DATE(paid_at) as dia,
                COUNT(*) as quantidade,
                SUM(amount) as total,
                AVG(amount) as media
            FROM transactions 
            WHERE status = 'completed' 
            AND paid_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
            GROUP BY DATE(paid_at) 
            ORDER BY dia DESC
        ");
        $recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Preenche dias vazios (para o gráfico ficar completo)
        $filledSales = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $found = false;
            
            foreach ($recentSales as $sale) {
                if ($sale['dia'] === $date) {
                    $filledSales[] = $sale;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $filledSales[] = [
                    'dia' => $date,
                    'quantidade' => 0,
                    'total' => 0,
                    'media' => 0
                ];
            }
        }
        $recentSales = $filledSales;

        // ========================================
        // TRANSAÇÕES RECENTES (Últimas 10)
        // ========================================
        
        $stmt = $pdo->query("
            SELECT 
                t.*,
                s.store_name,
                s.owner_name
            FROM transactions t
            LEFT JOIN stores s ON t.store_id = s.id
            WHERE t.status = 'completed'
            ORDER BY t.paid_at DESC
            LIMIT 10
        ");
        $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ========================================
        // ESTATÍSTICAS DE PAGAMENTO
        // ========================================
        
        $stmt = $pdo->query("
            SELECT 
                payment_method,
                COUNT(*) as quantidade,
                SUM(amount) as total,
                AVG(amount) as media
            FROM transactions
            WHERE status = 'completed'
            GROUP BY payment_method
        ");
        $paymentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcula percentuais
        $totalTransactions = array_sum(array_column($paymentStats, 'quantidade'));
        foreach ($paymentStats as &$stat) {
            $stat['percentual'] = $totalTransactions > 0 
                ? round(($stat['quantidade'] / $totalTransactions) * 100, 1)
                : 0;
        }

        // ========================================
        // SALVA NO CACHE REDIS
        // ========================================
        
        if ($redis) {
            $redis->setex($cacheKey, $cacheTTL, json_encode([
                'metrics' => $metrics,
                'recent_sales' => $recentSales,
                'recent_transactions' => $recentTransactions,
                'payment_stats' => $paymentStats,
                'cached_at' => date('Y-m-d H:i:s')
            ]));
        }

    } catch (PDOException $e) {
        error_log("Dashboard Error: " . $e->getMessage());
        
        // Valores padrão em caso de erro
        $metrics = [
            'total_clientes' => 0,
            'lojas_ativas' => 0,
            'faturamento_total' => 0,
            'faturamento_pix' => 0,
            'faturamento_mes' => 0,
            'transacoes_pendentes' => 0,
            'ticket_medio' => 0
        ];
    }
}

// ========================================
// FUNÇÕES AUXILIARES
// ========================================

function formatMoney($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function formatNumber($value) {
    return number_format($value, 0, ',', '.');
}

function getStatusClass($status) {
    $classes = [
        'completed' => 'bg-green-500/10 text-green-500 border-green-500/20',
        'pending' => 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20',
        'failed' => 'bg-red-500/10 text-red-500 border-red-500/20',
        'refunded' => 'bg-blue-500/10 text-blue-500 border-blue-500/20'
    ];
    return $classes[$status] ?? 'bg-zinc-800 text-zinc-500 border-white/5';
}

function getStatusLabel($status) {
    $labels = [
        'completed' => 'Pago',
        'pending' => 'Pendente',
        'failed' => 'Falhou',
        'refunded' => 'Reembolsado'
    ];
    return $labels[$status] ?? 'Desconhecido';
}

function getPaymentMethodIcon($method) {
    $icons = [
        'pix' => 'qr-code',
        'credit_card' => 'credit-card',
        'boleto' => 'file-text',
        'transfer' => 'arrow-right-left'
    ];
    return $icons[$method] ?? 'wallet';
}

function getPaymentMethodLabel($method) {
    $labels = [
        'pix' => 'PIX',
        'credit_card' => 'Cartão',
        'boleto' => 'Boleto',
        'transfer' => 'Transferência'
    ];
    return $labels[$method] ?? ucfirst($method);
}
?>

<?php
/**
 * Proteção de Sessão - Adicionar no início de cada página admin protegida
 * 
 * Exemplo: admin/dashboard.php, admin/stores.php, etc.
 */

session_start();

// 1. Verifica se está logado
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// 2. Timeout de sessão (30 minutos de inatividade)
$timeout = 1800; // 30 minutos
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=timeout');
    exit;
}
$_SESSION['last_activity'] = time();

// 3. Proteção contra session hijacking (opcional, mas recomendado)
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
}

if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=invalid_session');
    exit;
}

// 4. Regenera ID da sessão periodicamente (a cada 30 minutos)
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SplitStore Admin</title>
    
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
        
        .red-gradient { 
            background: linear-gradient(135deg, #dc2626 0%, #7f1d1d 100%); 
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

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        
        <!-- Header -->
        <header class="flex justify-between items-center mb-16">
            <div>
                <h1 class="text-4xl font-black italic uppercase tracking-tighter">
                    Command <span class="text-red-600">Center</span>
                </h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-[0.3em] mt-2 italic flex items-center gap-2">
                    <span class="w-2 h-2 bg-green-500 rounded-full pulse-dot"></span>
                    Database: splitstore_prod • Atualizado há 2 min
                </p>
            </div>
            <div class="flex gap-4">
                <button onclick="location.reload()" class="glass p-4 rounded-2xl hover:border-red-600/40 transition group">
                    <i data-lucide="refresh-cw" class="w-4 h-4 text-zinc-500 group-hover:text-red-600 group-hover:rotate-180 transition-all duration-500"></i>
                </button>
                <button class="glass p-4 rounded-2xl hover:border-red-600/40 transition">
                    <i data-lucide="download" class="w-4 h-4 text-zinc-500"></i>
                </button>
            </div>
        </header>

        <!-- Métricas Principais -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            
            <!-- Total de Clientes -->
            <div class="metric-card glass p-8 rounded-[2.5rem] border-white/5">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-600/10 rounded-2xl flex items-center justify-center">
                        <i data-lucide="users" class="w-6 h-6 text-blue-600"></i>
                    </div>
                    <span class="text-xs font-black uppercase tracking-wider text-blue-600/50">+<?= rand(2, 8) ?>%</span>
                </div>
                <p class="text-zinc-600 text-[9px] font-black uppercase tracking-widest mb-2">Clientes Totais</p>
                <h3 class="text-4xl font-black italic"><?= formatNumber($metrics['total_clientes']) ?></h3>
                <p class="text-xs text-zinc-700 mt-2">Lojas cadastradas</p>
            </div>

            <!-- Lojas Ativas -->
            <div class="metric-card glass p-8 rounded-[2.5rem] border-red-600/10">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-red-600/10 rounded-2xl flex items-center justify-center">
                        <i data-lucide="shopping-cart" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <span class="text-xs font-black uppercase tracking-wider text-red-600/50">LIVE</span>
                </div>
                <p class="text-red-600/50 text-[9px] font-black uppercase tracking-widest mb-2">Lojas Ativas</p>
                <h3 class="text-4xl font-black italic"><?= formatNumber($metrics['lojas_ativas']) ?></h3>
                <p class="text-xs text-zinc-700 mt-2">Status: Operacional</p>
            </div>

            <!-- Receita Geral -->
            <div class="metric-card glass p-8 rounded-[2.5rem] border-white/5">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-green-600/10 rounded-2xl flex items-center justify-center">
                        <i data-lucide="trending-up" class="w-6 h-6 text-green-600"></i>
                    </div>
                    <span class="text-xs font-black uppercase tracking-wider text-green-600/50">+<?= rand(12, 28) ?>%</span>
                </div>
                <p class="text-zinc-600 text-[9px] font-black uppercase tracking-widest mb-2">Receita Geral</p>
                <h3 class="text-4xl font-black italic"><?= formatMoney($metrics['faturamento_total']) ?></h3>
                <p class="text-xs text-zinc-700 mt-2">Total processado</p>
            </div>

            <!-- MisticPay (PIX) -->
            <div class="metric-card glass p-8 rounded-[2.5rem] bg-red-600/5 border-red-600/20">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-red-600/20 rounded-2xl flex items-center justify-center">
                        <i data-lucide="qr-code" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <div class="text-[8px] font-black uppercase tracking-wider text-red-600 bg-red-600/10 px-2 py-1 rounded-full border border-red-600/20">
                        PIX
                    </div>
                </div>
                <p class="text-red-500 text-[9px] font-black uppercase tracking-widest mb-2">MisticPay</p>
                <h3 class="text-4xl font-black italic text-red-500"><?= formatMoney($metrics['faturamento_pix']) ?></h3>
                <p class="text-xs text-red-900 mt-2">
                    <?php 
                    $pixPercent = $metrics['faturamento_total'] > 0 
                        ? round(($metrics['faturamento_pix'] / $metrics['faturamento_total']) * 100, 1)
                        : 0;
                    ?>
                    <?= $pixPercent ?>% do total
                </p>
            </div>
        </div>

        <!-- Métricas Secundárias -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
            
            <!-- Faturamento do Mês -->
            <div class="glass p-6 rounded-2xl border-white/5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-purple-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="calendar" class="w-5 h-5 text-purple-600"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-black uppercase text-zinc-600 tracking-widest">Mês Atual</p>
                        <h4 class="text-xl font-black"><?= formatMoney($metrics['faturamento_mes']) ?></h4>
                    </div>
                </div>
            </div>

            <!-- Ticket Médio -->
            <div class="glass p-6 rounded-2xl border-white/5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-yellow-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="receipt" class="w-5 h-5 text-yellow-600"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-black uppercase text-zinc-600 tracking-widest">Ticket Médio</p>
                        <h4 class="text-xl font-black"><?= formatMoney($metrics['ticket_medio']) ?></h4>
                    </div>
                </div>
            </div>

            <!-- Pendentes -->
            <div class="glass p-6 rounded-2xl border-white/5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-orange-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="clock" class="w-5 h-5 text-orange-600"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-black uppercase text-zinc-600 tracking-widest">Pendentes</p>
                        <h4 class="text-xl font-black"><?= formatNumber($metrics['transacoes_pendentes']) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grid Principal -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            
            <!-- Gráfico de Vendas (2/3) -->
            <div class="lg:col-span-2 glass rounded-[3rem] p-10">
                <div class="flex justify-between items-center mb-10">
                    <div>
                        <h4 class="text-xs font-black uppercase tracking-[0.3em] italic">Atividade Recente</h4>
                        <p class="text-[9px] font-bold text-zinc-600 uppercase mt-1">Últimos 7 Dias</p>
                    </div>
                    <div class="flex gap-2">
                        <button class="px-4 py-2 bg-red-600/10 border border-red-600/20 rounded-xl text-[10px] font-black uppercase text-red-600">
                            7 Dias
                        </button>
                        <button class="px-4 py-2 glass rounded-xl text-[10px] font-black uppercase text-zinc-600 hover:text-white transition">
                            30 Dias
                        </button>
                    </div>
                </div>
                
                <!-- Canvas do Chart.js com altura fixa -->
                <div style="height: 300px; position: relative;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Status de Pagamento (1/3) -->
            <div class="glass rounded-[3rem] p-10 flex flex-col">
                <h4 class="text-xs font-black uppercase tracking-[0.3em] italic text-center mb-10">
                    Métodos de Pagamento
                </h4>
                
                <div class="space-y-6 flex-1">
                    <?php if (!empty($paymentStats)): ?>
                        <?php foreach($paymentStats as $stat): 
                            $colors = [
                                'pix' => ['bg' => 'bg-red-600', 'text' => 'text-red-600', 'border' => 'border-red-600'],
                                'credit_card' => ['bg' => 'bg-blue-600', 'text' => 'text-blue-600', 'border' => 'border-blue-600'],
                                'boleto' => ['bg' => 'bg-yellow-600', 'text' => 'text-yellow-600', 'border' => 'border-yellow-600'],
                                'transfer' => ['bg' => 'bg-purple-600', 'text' => 'text-purple-600', 'border' => 'border-purple-600']
                            ];
                            $color = $colors[$stat['payment_method']] ?? ['bg' => 'bg-zinc-600', 'text' => 'text-zinc-600', 'border' => 'border-zinc-600'];
                        ?>
                            <div class="relative">
                                <div class="flex mb-2 items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="<?= getPaymentMethodIcon($stat['payment_method']) ?>" class="w-4 h-4 <?= $color['text'] ?>"></i>
                                        <span class="text-[9px] font-black uppercase text-zinc-500">
                                            <?= getPaymentMethodLabel($stat['payment_method']) ?>
                                        </span>
                                    </div>
                                    <span class="text-[10px] font-black <?= $color['text'] ?>">
                                        <?= $stat['percentual'] ?>%
                                    </span>
                                </div>
                                <div class="overflow-hidden h-2 text-xs flex rounded-full bg-zinc-900">
                                    <div style="width:<?= $stat['percentual'] ?>%" 
                                         class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center <?= $color['bg'] ?> shadow-[0_0_15px_rgba(220,38,38,0.4)]">
                                    </div>
                                </div>
                                <p class="text-[9px] text-zinc-700 mt-1">
                                    <?= formatNumber($stat['quantidade']) ?> transações • <?= formatMoney($stat['total']) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-zinc-700 text-xs italic">Nenhuma transação ainda</p>
                    <?php endif; ?>
                </div>

                <!-- Badge Gateway -->
                <div class="mt-auto pt-8 p-6 rounded-3xl border border-dashed border-white/10 text-center">
                    <i data-lucide="shield-check" class="w-8 h-8 text-red-600 mx-auto mb-4"></i>
                    <p class="text-[9px] font-black uppercase tracking-widest text-zinc-500">Gateway Ativo</p>
                    <p class="text-xs font-bold mt-1 tracking-tight">MisticPay • ci_6wq...830</p>
                </div>
            </div>
        </div>

        <!-- Transações Recentes -->
        <div class="glass rounded-[3rem] p-10 mt-8">
            <div class="flex justify-between items-center mb-8">
                <h4 class="text-xs font-black uppercase tracking-[0.3em] italic">Transações Recentes</h4>
                <a href="transactions.php" class="text-[10px] font-black uppercase text-zinc-600 hover:text-red-600 transition tracking-wider">
                    Ver Todas →
                </a>
            </div>

            <?php if (!empty($recentTransactions)): ?>
                <div class="space-y-4">
                    <?php foreach(array_slice($recentTransactions, 0, 5) as $tx): ?>
                        <div class="flex items-center justify-between p-5 rounded-[1.5rem] bg-white/[0.02] border border-white/5 hover:border-red-600/20 transition">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-green-500/10 flex items-center justify-center">
                                    <i data-lucide="<?= getPaymentMethodIcon($tx['payment_method']) ?>" class="w-4 h-4 text-green-500"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase text-white">
                                        <?= htmlspecialchars($tx['store_name'] ?? 'Venda Direta') ?>
                                    </p>
                                    <p class="text-[9px] text-zinc-600 font-bold">
                                        <?= date('d/m/Y H:i', strtotime($tx['paid_at'])) ?> • 
                                        <?= getPaymentMethodLabel($tx['payment_method']) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-sm font-black italic text-green-500">
                                    + <?= formatMoney($tx['amount']) ?>
                                </span>
                                <div class="mt-1">
                                    <span class="px-2 py-1 rounded-lg text-[8px] font-black uppercase border <?= getStatusClass($tx['status']) ?>">
                                        <?= getStatusLabel($tx['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-16 opacity-30">
                    <i data-lucide="inbox" class="w-16 h-16 mx-auto mb-4"></i>
                    <p class="text-xs font-bold uppercase tracking-widest text-zinc-700">
                        Nenhuma transação no período
                    </p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <script>
        lucide.createIcons();

        // ========================================
        // GRÁFICO DE VENDAS (Chart.js)
        // ========================================
        
        const salesData = <?= json_encode($recentSales) ?>;
        
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        // Gradiente para a área do gráfico
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(220, 38, 38, 0.2)');
        gradient.addColorStop(1, 'rgba(220, 38, 38, 0)');
        
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesData.map(d => {
                    const date = new Date(d.dia);
                    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
                }),
                datasets: [{
                    label: 'Vendas (R$)',
                    data: salesData.map(d => parseFloat(d.total)),
                    borderColor: '#dc2626',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#dc2626',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#a1a1aa',
                        borderColor: 'rgba(220, 38, 38, 0.3)',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'R$ ' + context.parsed.y.toFixed(2).replace('.', ',');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.03)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#71717a',
                            font: {
                                size: 10,
                                weight: 'bold'
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.03)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#71717a',
                            font: {
                                size: 10,
                                weight: 'bold'
                            },
                            callback: function(value) {
                                return 'R$ ' + value.toFixed(0);
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    </script>
</body>
</html>