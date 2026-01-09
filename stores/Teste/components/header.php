<?php
/**
 * ============================================
 * HEADER COMPONENT - Reutilizável
 * ============================================
 * Inclua com: <?php include 'components/header.php'; ?>
 */

if (!isset($store) || !isset($menu_items)) {
    die("Header component requires \$store and \$menu_items variables");
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- HEADER FIXO -->
<header class="sticky top-0 z-50" style="background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(30px); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex items-center justify-between h-20">
            
            <!-- Logo -->
            <a href="index.php" class="flex items-center gap-3 group">
                <?php if (!empty($store['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($store['logo_url']) ?>" 
                         class="h-10 object-contain group-hover:scale-110 transition">
                <?php else: ?>
                    <div class="w-12 h-12 bg-gradient-to-br from-primary to-red-600 rounded-xl flex items-center justify-center font-black shadow-lg shadow-primary/30 group-hover:scale-110 transition">
                        <?= strtoupper(substr($store['store_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="hidden md:block">
                    <div class="font-black text-lg uppercase tracking-tight group-hover:text-primary transition">
                        <?= htmlspecialchars($store['store_name']) ?>
                    </div>
                    <div class="text-[9px] text-zinc-600 font-bold uppercase tracking-widest">
                        Servidor Minecraft
                    </div>
                </div>
            </a>

            <!-- Menu Desktop -->
            <nav class="hidden lg:flex items-center gap-6">
                <?php foreach ($menu_items as $item): 
                    $is_active = $current_page == basename($item['url']);
                ?>
                <a href="<?= htmlspecialchars($item['url']) ?>" 
                   class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider transition group <?= $is_active ? 'text-primary' : 'text-zinc-400 hover:text-white' ?>">
                    <?php if ($item['icon']): ?>
                    <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" 
                       class="w-4 h-4 <?= $is_active ? 'text-primary' : 'group-hover:text-primary' ?> transition"></i>
                    <?php endif; ?>
                    <span class="group-hover:text-primary transition"><?= htmlspecialchars($item['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </nav>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <?php if ($is_logged): ?>
                    <a href="auth.php?action=logout" class="flex items-center gap-2 glass px-4 py-2 rounded-xl hover:bg-white/10 transition">
                        <img src="<?= htmlspecialchars($_SESSION['store_user_skin']) ?>" class="w-6 h-6 rounded-lg">
                        <span class="hidden md:block text-xs font-bold"><?= htmlspecialchars($_SESSION['store_user_nick']) ?></span>
                    </a>
                <?php else: ?>
                    <a href="auth.php" class="bg-gradient-to-r from-primary to-red-600 hover:brightness-110 px-6 py-2 rounded-xl text-xs font-black uppercase transition shadow-lg shadow-primary/30">
                        Login
                    </a>
                <?php endif; ?>
                
                <button onclick="toggleMobileMenu()" class="lg:hidden w-10 h-10 glass rounded-xl flex items-center justify-center hover:bg-white/10 transition">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
            </div>
        </div>
    </div>
</header>

<!-- Mobile Menu -->
<div id="mobileMenu" class="fixed inset-0 z-40 hidden lg:hidden">
    <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="toggleMobileMenu()"></div>
    <div class="absolute right-0 top-0 h-full w-80 bg-secondary border-l border-white/10 p-6">
        <div class="flex items-center justify-between mb-8">
            <h3 class="font-black uppercase text-lg">Menu</h3>
            <button onclick="toggleMobileMenu()" class="w-10 h-10 glass rounded-xl flex items-center justify-center">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <nav class="flex flex-col gap-4">
            <?php foreach ($menu_items as $item): 
                $is_active = $current_page == basename($item['url']);
            ?>
            <a href="<?= htmlspecialchars($item['url']) ?>" 
               class="flex items-center gap-3 text-sm font-bold uppercase tracking-wider transition py-3 border-b border-white/5 <?= $is_active ? 'text-primary' : 'text-zinc-400 hover:text-white' ?>">
                <?php if ($item['icon']): ?>
                <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="w-4 h-4"></i>
                <?php endif; ?>
                <?= htmlspecialchars($item['label']) ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<script>
function toggleMobileMenu() {
    document.getElementById('mobileMenu').classList.toggle('hidden');
    lucide.createIcons();
}

// Fechar ao clicar fora
document.getElementById('mobileMenu')?.addEventListener('click', (e) => {
    if (e.target.classList.contains('bg-black/80')) {
        toggleMobileMenu();
    }
});

// Fechar com ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !document.getElementById('mobileMenu').classList.contains('hidden')) {
        toggleMobileMenu();
    }
});
</script>

<style>
.glass { 
    background: rgba(255, 255, 255, 0.02); 
    backdrop-filter: blur(20px); 
    border: 1px solid rgba(255, 255, 255, 0.05); 
}
</style>