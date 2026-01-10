<!-- ============================================ -->
<!-- REGRAS.PHP - ATUALIZADO -->
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

    
    // Buscar página de regras
    $stmt = $pdo->prepare("
        SELECT * FROM store_pages 
        WHERE store_id = ? AND slug = 'regras' AND is_published = 1
    ");
    $stmt->execute([$store['id']]);
    $regras_page = $stmt->fetch();
    
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
    <title>Regras | <?= htmlspecialchars($store['store_name']) ?></title>
    
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

    
    

    <main class="max-w-5xl mx-auto px-6 py-12">
        
        <div class="mb-12">
            <div class="flex items-center gap-3 mb-6">
                <a href="index.php" class="text-zinc-600 hover:text-white transition">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-4xl font-black uppercase tracking-tighter">
                    <i data-lucide="shield-check" class="w-8 h-8 inline text-primary"></i>
                    Regras do <span class="text-primary">Servidor</span>
                </h1>
            </div>
            <p class="text-zinc-500 text-lg">
                Leia atentamente e siga as regras para garantir uma experiência agradável para todos
            </p>
        </div>

        <div class="glass rounded-3xl p-8 mb-8 border border-primary/20 bg-primary/5">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-primary/20 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i data-lucide="alert-circle" class="w-6 h-6 text-primary"></i>
                </div>
                <div>
                    <h3 class="font-black uppercase mb-2">Importante!</h3>
                    <p class="text-sm text-zinc-400 leading-relaxed">
                        O desconhecimento das regras não isenta você de punições. 
                        Todas as regras estão sujeitas à interpretação da equipe de moderação.
                    </p>
                </div>
            </div>
        </div>

        <div class="glass rounded-3xl p-10 border border-white/5">
            <?php if ($regras_page): ?>
                <div class="prose prose-invert max-w-none">
                    <h2 class="text-3xl font-black uppercase mb-6">
                        <?= htmlspecialchars($regras_page['title']) ?>
                    </h2>
                    <div class="text-zinc-300 leading-relaxed whitespace-pre-line">
                        <?= nl2br(htmlspecialchars($regras_page['content'])) ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <i data-lucide="shield-check" class="w-16 h-16 mx-auto mb-4 text-zinc-700"></i>
                    <h3 class="text-xl font-black uppercase text-zinc-600 mb-2">Regras em Construção</h3>
                    <p class="text-zinc-500">Em breve teremos as regras completas do servidor!</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <?php $theme->renderScripts(); // ← Theme Engine JS ?>

    <script>lucide.createIcons();</script>
</body>
</html>