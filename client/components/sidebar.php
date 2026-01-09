<?php
/**
 * ============================================
 * SIDEBAR COM CONTROLE DE ACESSO - CORRIGIDA
 * ============================================
 */

// Verifica variáveis da sessão
$store_name = $_SESSION['store_name'] ?? 'Minha Loja';
$store_plan = $_SESSION['store_plan'] ?? 'basic';
$store_id = $_SESSION['store_id'] ?? 0;

// Página atual
$current_page = basename($_SERVER['PHP_SELF']);

// Nível de acesso
$accessLevel = getAccessLevel();
$isRestricted = ($accessLevel === 'restricted');
$isSuspended = ($accessLevel === 'suspended');

// Busca métricas APENAS DA LOJA DO USUÁRIO
$quick_stats = ['vendas_mes' => 0];
if (isset($pdo) && $store_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM transactions 
            WHERE store_id = ? 
            AND status = 'completed'
            AND MONTH(paid_at) = MONTH(CURRENT_DATE())
            AND YEAR(paid_at) = YEAR(CURRENT_DATE())
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

// Define estrutura do menu
$menuItems = [
    [
        'page' => 'dashboard.php',
        'icon' => 'layout-dashboard',
        'label' => 'Overview',
        'restricted' => false
    ],
    [
        'page' => 'faturas.php',
        'icon' => 'receipt',
        'label' => 'Faturas',
        'restricted' => false,
        'badge' => hasPendingInvoice() ? 'pending' : null
    ],
    [
        'type' => 'divider',
        'label' => 'GESTÃO',
        'restricted' => false
    ],
    [
        'page' => 'products.php',
        'icon' => 'package',
        'label' => 'Produtos',
        'restricted' => true
    ],
    [
        'page' => 'gerenciar_pedidos.php',
        'icon' => 'shopping-cart',
        'label' => 'Pedidos',
        'restricted' => true
    ],
    [
        'page' => 'clientes.php',
        'icon' => 'users',
        'label' => 'Clientes',
        'restricted' => true
    ],
        [
        'page' => 'usuarios.php',
        'icon' => 'users',
        'label' => 'Usuários',
        'restricted' => true
    ],
    [
        'page' => 'descontos.php',
        'icon' => 'ticket',
        'label' => 'Cupons',
        'restricted' => true
    ],
    [
        'page' => 'noticias.php',
        'icon' => 'newspaper',
        'label' => 'Notícias',
        'restricted' => true
    ],
    [
        'type' => 'divider',
        'label' => 'CONFIGURAÇÕES',
        'restricted' => false
    ],
    [
        'page' => 'customize.php',
        'icon' => 'palette',
        'label' => 'Customização',
        'restricted' => true
    ],
    [
        'page' => 'servers.php',
        'icon' => 'server',
        'label' => 'Servidores',
        'restricted' => true
    ],
    [
        'page' => 'pagamentos.php',
        'icon' => 'credit-card',
        'label' => 'Pagamentos',
        'restricted' => true
    ],
    [
        'page' => 'integrations.php',
        'icon' => 'plug',
        'label' => 'Integrações',
        'restricted' => true
    ],
    [
        'page' => 'contatos.php',
        'icon' => 'mail',
        'label' => 'Contatos',
        'restricted' => true
    ],
    [
        'page' => 'settings.php',
        'icon' => 'settings',
        'label' => 'Configurações',
        'restricted' => false // Sempre acessível
    ]
];
?>

<!-- SIDEBAR -->
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
        
        <!-- Status Badge -->
        <div class="mb-4">
            <?= getPaymentStatusBadge() ?>
        </div>
        
        <!-- Quick Stats -->
        <div class="glass rounded-xl p-4 border-white/5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[9px] font-black uppercase text-zinc-600">Este Mês</span>
                <span class="text-xs font-black text-green-500">↑</span>
            </div>
            <p class="text-xl font-black italic"><?= formatMoneyShort($quick_stats['vendas_mes']) ?></p>
            <p class="text-[8px] text-zinc-700 mt-1">Receita da sua loja</p>
        </div>
    </div>

    <!-- Menu -->
    <nav class="flex-1 px-6 space-y-1 overflow-y-auto scrollbar-hide">
        <?php foreach ($menuItems as $item): ?>
            
            <?php if (isset($item['type']) && $item['type'] === 'divider'): ?>
                <!-- Divisor -->
                <div class="pt-4 pb-2">
                    <span class="text-[8px] font-black uppercase text-zinc-700 tracking-[0.15em] pl-4">
                        <?= $item['label'] ?>
                    </span>
                </div>
                
            <?php else: ?>
                <!-- Link do Menu -->
                <?php 
                $isActive = ($current_page == $item['page']);
                $canAccess = canAccessPage($item['page']);
                $isBlocked = !$canAccess;
                ?>
                
                <a href="<?= $canAccess ? $item['page'] : '#' ?>" 
                   class="group flex items-center justify-between gap-4 p-4 rounded-2xl text-xs font-black uppercase tracking-widest transition-all
                          <?php if ($isActive): ?>
                              bg-red-600/10 text-red-600 border border-red-600/20
                          <?php elseif ($isBlocked): ?>
                              text-zinc-700 cursor-not-allowed opacity-50
                          <?php else: ?>
                              text-zinc-500 hover:bg-red-600/5 hover:text-red-600
                          <?php endif; ?>"
                   <?= $isBlocked ? 'onclick="showLockedFeature(); return false;"' : '' ?>>
                    
                    <div class="flex items-center gap-4">
                        <i data-lucide="<?= $item['icon'] ?>" class="w-4 h-4"></i>
                        <span><?= $item['label'] ?></span>
                    </div>
                    
                    <?php if ($isBlocked): ?>
                        <i data-lucide="lock" class="w-3 h-3 text-zinc-700"></i>
                    <?php elseif (isset($item['badge']) && $item['badge'] === 'pending'): ?>
                        <div class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></div>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            
        <?php endforeach; ?>
    </nav>

    <!-- Footer -->
    <div class="p-6 border-t border-white/5">
        <a href="logout.php" class="flex items-center gap-2 text-zinc-600 hover:text-white transition text-[9px] font-black uppercase tracking-[0.2em]">
            <i data-lucide="log-out" class="w-3 h-3"></i> Sair
        </a>
    </div>
</aside>

<!-- Modal de Feature Bloqueada -->
<div id="lockedFeatureModal" class="hidden fixed inset-0 z-[9999] bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="glass max-w-md w-full p-8 rounded-3xl border-2 border-yellow-600/20 shadow-2xl animate-in">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-yellow-600/10 border-2 border-yellow-600/30 rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-lucide="lock" class="w-8 h-8 text-yellow-500"></i>
            </div>
            <h3 class="text-xl font-black uppercase tracking-tight mb-2">
                Recurso <span class="text-yellow-500">Bloqueado</span>
            </h3>
            <p class="text-zinc-400 text-sm">
                Complete seu pagamento para desbloquear todas as funcionalidades da plataforma.
            </p>
        </div>
        
        <div class="bg-yellow-600/5 border border-yellow-600/20 rounded-xl p-4 mb-6">
            <p class="text-xs text-zinc-400 leading-relaxed">
                <i data-lucide="info" class="w-4 h-4 inline text-yellow-500 mr-1"></i>
                Você está em <strong>modo de avaliação</strong>. Após confirmar o pagamento, 
                você terá acesso imediato a todos os recursos premium.
            </p>
        </div>
        
        <div class="flex gap-3">
            <button onclick="closeLockedModal()" 
                    class="flex-1 bg-zinc-900 hover:bg-zinc-800 text-white py-3 rounded-xl font-bold text-sm transition">
                Fechar
            </button>
            <a href="faturas.php" 
               class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-white py-3 rounded-xl font-black text-sm text-center transition">
                Ver Faturas
            </a>
        </div>
    </div>
</div>

<style>
.glass { 
    background: rgba(255, 255, 255, 0.02); 
    backdrop-filter: blur(12px); 
    border: 1px solid rgba(255, 255, 255, 0.05); 
}

@keyframes animate-in {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.animate-in {
    animation: animate-in 0.3s ease-out;
}

/* Scrollbar customizada */
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>

<script>
function showLockedFeature() {
    document.getElementById('lockedFeatureModal').classList.remove('hidden');
    lucide.createIcons();
}

function closeLockedModal() {
    document.getElementById('lockedFeatureModal').classList.add('hidden');
}

// Fecha ao clicar fora
document.getElementById('lockedFeatureModal')?.addEventListener('click', (e) => {
    if (e.target === e.currentTarget) {
        closeLockedModal();
    }
});

// Fecha com ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeLockedModal();
    }
});
</script>