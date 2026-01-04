<?php
session_start();
require_once '../includes/db.php';

// Segurança: Bloqueio de acesso não autorizado
if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

/**
 * MOTOR DE MÉTRICAS (REDIS + MYSQL)
 * Buscamos os dados das tabelas específicas de métricas
 */
$metrics = [];
$cacheKey = 'splitstore_core_metrics';

if ($redis && $redis->exists($cacheKey)) {
    $metrics = json_decode($redis->get($cacheKey), true);
} else {
    // 1. Total de clientes (Lojas únicas cadastradas)
    $metrics['total_clientes'] = $pdo->query("SELECT COUNT(*) FROM stores")->fetchColumn() ?? 0;

    // 2. Lojas Ativas (Status active)
    $metrics['lojas_ativas'] = $pdo->query("SELECT COUNT(*) FROM stores WHERE status = 'active'")->fetchColumn() ?? 0;

    // 3. Faturamento Total (Transações completed)
    $metrics['faturamento_total'] = $pdo->query("SELECT SUM(amount) FROM transactions WHERE status = 'completed'")->fetchColumn() ?? 0;

    // 4. Vendas via PIX (MisticPay) - Filtro por método
    $metrics['faturamento_pix'] = $pdo->query("SELECT SUM(amount) FROM transactions WHERE status = 'completed' AND payment_method = 'pix'")->fetchColumn() ?? 0;

    // 5. Vendas por Período (Últimos 7 dias para gráfico/lista)
    $stmt = $pdo->query("SELECT DATE(paid_at) as dia, SUM(amount) as total FROM transactions WHERE status = 'completed' GROUP BY DATE(paid_at) ORDER BY dia DESC LIMIT 7");
    $metrics['recent_sales'] = $stmt->fetchAll();

    // Cache de 2 minutos (Equilíbrio entre tempo real e performance)
    if ($redis) $redis->setex($cacheKey, 120, json_encode($metrics));
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050505; color: white; scrollbar-width: none; }
        ::-webkit-scrollbar { display: none; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .red-gradient { background: linear-gradient(135deg, #dc2626 0%, #7f1d1d 100%); }
        .sidebar-item:hover { background: rgba(220, 38, 38, 0.05); color: #dc2626; }
    </style>
</head>
<body class="flex min-h-screen">

    <aside class="w-72 border-r border-white/5 bg-black flex flex-col sticky top-0 h-screen">
        <div class="p-10 text-center">
            <h2 class="text-2xl font-black italic tracking-tighter">SPLIT<span class="text-red-600">ADMIN</span></h2>
            <div class="mt-2 py-1 px-3 glass rounded-full inline-block">
                <span class="text-[8px] font-black uppercase tracking-widest text-red-500">v2.1.0 Stable</span>
            </div>
        </div>

        <nav class="flex-1 px-6 space-y-2 mt-4">
            <a href="dashboard.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest bg-red-600/10 text-red-600 border border-red-600/20">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Overview
            </a>
            <a href="stores.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest text-zinc-500 sidebar-item transition">
                <i data-lucide="shopping-cart" class="w-4 h-4"></i> Lojas & Clientes
            </a>
            <a href="transactions.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest text-zinc-500 sidebar-item transition">
                <i data-lucide="banknote" class="w-4 h-4"></i> Financeiro
            </a>
            <a href="partners.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest text-zinc-500 sidebar-item transition">
                <i data-lucide="users" class="w-4 h-4"></i> Parceiros (Site)
            </a>
        </nav>

        <div class="p-8 border-t border-white/5 bg-zinc-950/50">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl red-gradient flex items-center justify-center font-black shadow-lg shadow-red-900/40">S</div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-tighter">SplitStore Dev</p>
                    <p class="text-[9px] text-zinc-600 font-bold uppercase tracking-widest">Gateway: MisticPay</p>
                </div>
            </div>
            <a href="logout.php" class="flex items-center gap-2 text-zinc-600 hover:text-white transition text-[9px] font-black uppercase tracking-[0.2em]">
                <i data-lucide="power" class="w-3 h-3"></i> Encerrar Sessão
            </a>
        </div>
    </aside>

    <main class="flex-1 p-12">
        <header class="flex justify-between items-center mb-16">
            <div>
                <h1 class="text-4xl font-black italic uppercase tracking-tighter">Command <span class="text-red-600">Center</span></h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-[0.3em] mt-2 italic">Database: splitstore_prod</p>
            </div>
            <div class="flex gap-4">
                <button class="glass p-4 rounded-2xl hover:border-red-600/40 transition">
                    <i data-lucide="refresh-cw" class="w-4 h-4 text-zinc-500"></i>
                </button>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
            <div class="glass p-8 rounded-[2.5rem] border-white/5">
                <p class="text-zinc-600 text-[9px] font-black uppercase tracking-widest mb-4">Clientes Totais</p>
                <h3 class="text-4xl font-black italic"><?= number_format($metrics['total_clientes'], 0, '', '.') ?></h3>
            </div>
            <div class="glass p-8 rounded-[2.5rem] border-red-600/10">
                <p class="text-red-600/50 text-[9px] font-black uppercase tracking-widest mb-4">Lojas Ativas</p>
                <h3 class="text-4xl font-black italic"><?= $metrics['lojas_ativas'] ?></h3>
            </div>
            <div class="glass p-8 rounded-[2.5rem] border-white/5">
                <p class="text-zinc-600 text-[9px] font-black uppercase tracking-widest mb-4">Receita Geral</p>
                <h3 class="text-4xl font-black italic">R$ <?= number_format($metrics['faturamento_total'], 2, ',', '.') ?></h3>
            </div>
            <div class="glass p-8 rounded-[2.5rem] bg-red-600/5 border-red-600/20">
                <p class="text-red-500 text-[9px] font-black uppercase tracking-widest mb-4">MisticPay (PIX)</p>
                <h3 class="text-4xl font-black italic text-red-500">R$ <?= number_format($metrics['faturamento_pix'], 2, ',', '.') ?></h3>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-8">
            <div class="col-span-2 glass rounded-[3rem] p-10">
                <div class="flex justify-between items-center mb-10">
                    <h4 class="text-xs font-black uppercase tracking-[0.3em] italic">Atividade Recente (Vendas)</h4>
                    <span class="text-[9px] font-bold text-zinc-600 uppercase">Últimos 7 Dias</span>
                </div>
                
                <div class="space-y-4">
                    <?php if(empty($metrics['recent_sales'])): ?>
                        <p class="text-zinc-700 text-xs italic">Nenhuma venda registrada no período.</p>
                    <?php else: ?>
                        <?php foreach($metrics['recent_sales'] as $sale): ?>
                        <div class="flex items-center justify-between p-5 rounded-[1.5rem] bg-white/[0.02] border border-white/5">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-green-500/10 flex items-center justify-center">
                                    <i data-lucide="trending-up" class="w-4 h-4 text-green-500"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase text-zinc-400"><?= date('d/m/Y', strtotime($sale['dia'])) ?></p>
                                    <p class="text-xs font-bold italic">Processado via MisticPay</p>
                                </div>
                            </div>
                            <span class="text-sm font-black italic text-green-500">+ R$ <?= number_format($sale['total'], 2, ',', '.') ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="glass rounded-[3rem] p-10">
                <h4 class="text-xs font-black uppercase tracking-[0.3em] italic text-center mb-10">Status de Pagamento</h4>
                
                <div class="space-y-8">
                    <div class="relative pt-1">
                        <div class="flex mb-2 items-center justify-between">
                            <span class="text-[9px] font-black uppercase text-zinc-500">PIX (Confirmado)</span>
                            <span class="text-[9px] font-black text-red-600">100%</span>
                        </div>
                        <div class="overflow-hidden h-1.5 text-xs flex rounded-full bg-zinc-900">
                            <div style="width:100%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-red-600 shadow-[0_0_15px_rgba(220,38,38,0.4)]"></div>
                        </div>
                    </div>

                    <div class="p-6 rounded-3xl border border-dashed border-white/10 text-center">
                        <i data-lucide="shield-check" class="w-8 h-8 text-red-600 mx-auto mb-4"></i>
                        <p class="text-[9px] font-black uppercase tracking-widest text-zinc-500">Gateway Ativo</p>
                        <p class="text-xs font-bold mt-1 tracking-tight">CI: ci_6wq...830</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>