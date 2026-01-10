<!-- ============================================ -->
<!-- WIKI.PHP - ATUALIZADO -->
<!-- ============================================ -->
<?php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/theme_engine.php'; // ← Theme Engine

$store_slug = basename(dirname(__FILE__));

try {
    $stmt = $pdo->prepare("
        SELECT s.*, sc.* 
        FROM stores s
        LEFT JOIN store_customization sc ON s.id = sc.store_id
        WHERE s.store_slug = ? AND s.status = 'active'
    ");
    $stmt->execute([$store_slug]);
    $store = $stmt->fetch();
    
    if (!$store) die("Loja não encontrada.");
    // Inicializa Theme Engine
    $theme = new ThemeEngine($pdo, $store['id']);
    
    // Busca menu para header
    $stmt = $pdo->prepare("
        SELECT * FROM store_menu 
        WHERE store_id = ? AND is_enabled = 1
        ORDER BY order_position ASC
    ");
    $stmt->execute([$store['id']]);
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    
    // Buscar página wiki
    $stmt = $pdo->prepare("
        SELECT * FROM store_pages 
        WHERE store_id = ? AND slug = 'wiki' AND is_published = 1
    ");
    $stmt->execute([$store['id']]);
    $wiki_page = $stmt->fetch();
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

$primaryColor = $store['primary_color'] ?? '#dc2626';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wiki | <?= htmlspecialchars($store['store_name']) ?></title>
    
    <?php $theme->renderHead(); // ← Theme CSS + Fonts ?>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?= $primaryColor ?>',
                        secondary: '<?= $store["secondary_color"] ?? "#0f172a" ?>'
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?= $primaryColor ?>'
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        body { background: #0f0f0f; color: white; font-family: 'Inter', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
    </style>

    <style>
        /* FORCE THEME COLORS - Sobrescreve Tailwind */
        .bg-primary,
        button.bg-primary,
        a.bg-primary,
        .text-primary {
            color: var(--primary) !important;
        }
        
        .bg-gradient-to-r.from-primary {
            background: linear-gradient(to right, var(--primary), var(--accent)) !important;
        }
        
        .border-primary {
            border-color: var(--primary) !important;
        }
        
        .gradient-text {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
        }
    </style>
</head>
<body>

    <!-- HEADER UNIVERSAL -->
    <?php include __DIR__ . '/components/header.php'; ?>


    

    <main class="max-w-7xl mx-auto px-6 py-12">
        
        <div class="mb-12">
            <div class="flex items-center gap-3 mb-6">
                <a href="index.php" class="text-zinc-600 hover:text-white transition">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-4xl font-black uppercase tracking-tighter">
                    <i data-lucide="book-open" class="w-8 h-8 inline text-primary"></i>
                    Wiki do <span class="text-primary">Servidor</span>
                </h1>
            </div>
            <p class="text-zinc-500 text-lg">
                Tudo que você precisa saber para começar a jogar
            </p>
        </div>

        <!-- IP do Servidor -->
        <div class="glass rounded-3xl p-8 mb-8 border border-primary/20 bg-primary/5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase text-zinc-500 mb-2">Conecte-se ao Servidor</p>
                    <p class="text-2xl font-black uppercase">
                        play.<?= strtolower($store['store_name']) ?>.com.br
                    </p>
                </div>
                <button onclick="copyIP()" class="bg-primary hover:brightness-110 px-6 py-3 rounded-xl font-black uppercase text-sm transition">
                    <i data-lucide="copy" class="w-4 h-4 inline mr-2"></i>
                    Copiar IP
                </button>
            </div>
        </div>

        <!-- Conteúdo da Wiki -->
        <div class="glass rounded-3xl p-10 border border-white/5">
            <?php if ($wiki_page): ?>
                <div class="prose prose-invert max-w-none">
                    <h2 class="text-3xl font-black uppercase mb-6">
                        <?= htmlspecialchars($wiki_page['title']) ?>
                    </h2>
                    <div class="text-zinc-300 leading-relaxed whitespace-pre-line">
                        <?= nl2br(htmlspecialchars($wiki_page['content'])) ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <i data-lucide="book-open" class="w-16 h-16 mx-auto mb-4 text-zinc-700"></i>
                    <h3 class="text-xl font-black uppercase text-zinc-600 mb-2">Wiki em Construção</h3>
                    <p class="text-zinc-500">Em breve teremos guias completos por aqui!</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <?php $theme->renderScripts(); // ← Theme JS ?>

    <script>
        lucide.createIcons();
        
        function copyIP() {
            const ip = 'play.<?= strtolower($store['store_name']) ?>.com.br';
            navigator.clipboard.writeText(ip).then(() => {
                alert('IP copiado: ' + ip);
            });
        }
    </script>
</body>
</html>