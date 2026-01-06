<?php
/**
 * SIDEBAR DO PAINEL DO CLIENTE
 * Componente reutilizável
 */

// Verifica se as variáveis da sessão existem
$store_name = $_SESSION['store_name'] ?? 'Minha Loja';
$store_plan = $_SESSION['store_plan'] ?? 'basic';
$store_id = $_SESSION['store_id'] ?? 0;

// Identifica a página atual
$current_page = basename($_SERVER['PHP_SELF']);

// Busca métricas rápidas para a sidebar (com cache)
$quick_stats = ['vendas_mes' => 0];
if (isset($pdo) && $store_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM transactions 
            WHERE store_id = ? 
            AND status = 'completed'
            AND MONTH(paid_at) = MONTH(CURRENT_DATE())
        ");
        $stmt->execute([$store_id]);
        $result = $stmt->fetch();
        $quick_stats['vendas_mes'] = (float)($result['total'] ?? 0);
    } catch (PDOException $e) {
        error_log("Sidebar Stats Error: " . $e->getMessage());
    }
}

function formatMoneyShort($value) {
    if ($value >= 1000) {
        return 'R$ ' . number_format($value / 1000, 1, ',', '.') . 'K';
    }
    return 'R$ ' . number_format($value, 2, ',', '.');
}
?>

<!-- SIDEBAR DO CLIENTE -->
<aside class="w-72 border-r border-white/5 bg-black flex flex-col sticky top-0 h-screen">
    <!-- Logo/Loja -->
    <div class="p-8">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 bg-gradient-to-br from-red-600 to-red-900 rounded-2xl flex items-center justify-center font-black shadow-lg shadow-red-900/40 text-xl">
                <?= strtoupper(substr($store_name, 0, 1)) ?>
            </div>
            <div>
                <h2 class="text-sm font-black uppercase italic tracking-tight truncate max-w-[140px]" title="<?= htmlspecialchars($store_name) ?>">
                    <?= htmlspecialchars($store_name) ?>
                </h2>
                <span class="text-[9px] font-bold uppercase tracking-widest text-red-500">
                    Plano <?= htmlspecialchars(ucfirst($store_plan)) ?>
                </span>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="glass rounded-xl p-4 border-white/5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[9px] font-black uppercase text-zinc-600">Este Mês</span>
                <span class="text-xs font-black text-green-500">↑</span>
            </div>
            <p class="text-xl font-black italic"><?= formatMoneyShort($quick_stats['vendas_mes']) ?></p>
        </div>
    </div>

    <!-- Menu -->
    <nav class="flex-1 px-6 space-y-2 overflow-y-auto">
        <a href="dashboard.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $current_page == 'dashboard.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 hover:bg-red-600/5 hover:text-red-600 transition' ?>">
            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Overview
        </a>
        
        <a href="products.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $current_page == 'products.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 hover:bg-red-600/5 hover:text-red-600 transition' ?>">
            <i data-lucide="package" class="w-4 h-4"></i> Produtos
        </a>
        
        <a href="orders.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $current_page == 'orders.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 hover:bg-red-600/5 hover:text-red-600 transition' ?>">
            <i data-lucide="shopping-cart" class="w-4 h-4"></i> Pedidos
        </a>
        
        <a href="customers.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $current_page == 'customers.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 hover:bg-red-600/5 hover:text-red-600 transition' ?>">
            <i data-lucide="users" class="w-4 h-4"></i> Clientes
        </a>
        
        <div class="h-px bg-white/5 my-4"></div>
        
        <a href="customize.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $current_page == 'customize.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 hover:bg-red-600/5 hover:text-red-600 transition' ?>">
            <i data-lucide="palette" class="w-4 h-4"></i> Customização
        </a>
        
        <a href="servers.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $current_page == 'servers.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 hover:bg-red-600/5 hover:text-red-600 transition' ?>">
            <i data-lucide="server" class="w-4 h-4"></i> Servidores
        </a>
        
        <a href="integrations.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $current_page == 'integrations.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 hover:bg-red-600/5 hover:text-red-600 transition' ?>">
            <i data-lucide="plug" class="w-4 h-4"></i> Integrações
        </a>
        
        <a href="settings.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $current_page == 'settings.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 hover:bg-red-600/5 hover:text-red-600 transition' ?>">
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

<style>
.glass { 
    background: rgba(255, 255, 255, 0.02); 
    backdrop-filter: blur(12px); 
    border: 1px solid rgba(255, 255, 255, 0.05); 
}
</style>