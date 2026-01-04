<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="w-72 border-r border-white/5 bg-black flex flex-col sticky top-0 h-screen">
    <div class="p-10 text-center">
        <h2 class="text-2xl font-black italic tracking-tighter">SPLIT<span class="text-red-600">ADMIN</span></h2>
        <div class="mt-2 py-1 px-3 glass rounded-full inline-block">
            <span class="text-[8px] font-black uppercase tracking-widest text-red-500">v3.0 Advanced</span>
        </div>
    </div>

    <nav class="flex-1 px-6 space-y-2 mt-4 overflow-y-auto">
        <a href="dashboard.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $currentPage == 'dashboard.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 sidebar-item transition' ?>">
            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Overview
        </a>
        
        <a href="stores.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $currentPage == 'stores.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 sidebar-item transition' ?>">
            <i data-lucide="shopping-cart" class="w-4 h-4"></i> Lojas & Clientes
        </a>
        
        <a href="transactions.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $currentPage == 'transactions.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 sidebar-item transition' ?>">
            <i data-lucide="banknote" class="w-4 h-4"></i> Financeiro
        </a>
        
        <a href="planos.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $currentPage == 'planos.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 sidebar-item transition' ?>">
            <i data-lucide="package" class="w-4 h-4"></i> Planos
        </a>
        
        <a href="promocoes.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $currentPage == 'promocoes.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 sidebar-item transition' ?>">
            <i data-lucide="percent" class="w-4 h-4"></i> Promoções
        </a>
        
        <div class="h-px bg-white/5 my-4"></div>
        
        <a href="partners.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $currentPage == 'partners.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 sidebar-item transition' ?>">
            <i data-lucide="users" class="w-4 h-4"></i> Parceiros
        </a>
        
        <a href="feedbacks.php" class="flex items-center gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest <?= $currentPage == 'feedbacks.php' ? 'bg-red-600/10 text-red-600 border border-red-600/20' : 'text-zinc-500 sidebar-item transition' ?>">
            <i data-lucide="message-square" class="w-4 h-4"></i> Feedbacks
        </a>
    </nav>

    <div class="p-8 border-t border-white/5 bg-zinc-950/50">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-600 to-red-900 flex items-center justify-center font-black shadow-lg shadow-red-900/40">S</div>
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

<style>
    .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
    .sidebar-item:hover { background: rgba(220, 38, 38, 0.05); color: #dc2626; }
</style>