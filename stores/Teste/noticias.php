<?php
/**
 * ============================================
 * SPLITSTORE - PÁGINA DE NOTÍCIAS
 * ============================================
 */

session_start();
require_once '../../includes/db.php';

$store_slug = basename(dirname(__FILE__));
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

try {
    $stmt = $pdo->prepare("
        SELECT s.*, sc.* 
        FROM stores s
        LEFT JOIN store_customization sc ON s.id = sc.store_id
        WHERE s.store_slug = ? AND s.status = 'active'
    ");
    $stmt->execute([$store_slug]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$store) die("Loja não encontrada.");
    
    // Menu
    $stmt = $pdo->prepare("
        SELECT * FROM store_menu 
        WHERE store_id = ? AND is_enabled = 1
        ORDER BY order_position ASC
    ");
    $stmt->execute([$store['id']]);
    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total de notícias
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE store_id = ? AND status = 'published'");
    $stmt->execute([$store['id']]);
    $total = $stmt->fetchColumn();
    $total_pages = ceil($total / $per_page);
    
    // Notícias paginadas
    $stmt = $pdo->prepare("
        SELECT * FROM news 
        WHERE store_id = ? AND status = 'published'
        ORDER BY COALESCE(published_at, created_at) DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$store['id'], $per_page, $offset]);
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

$primaryColor = $store['primary_color'] ?? '#dc2626';

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 3600) return floor($diff / 60) . 'min atrás';
    if ($diff < 86400) return floor($diff / 3600) . 'h atrás';
    if ($diff < 604800) return floor($diff / 86400) . 'd atrás';
    return date('d/m/Y', $time);
}

$is_logged = isset($_SESSION['store_user_logged']) && $_SESSION['store_user_logged'] === true;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notícias | <?= htmlspecialchars($store['store_name']) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
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
        body { 
            background: #0f0f0f; 
            color: white;
            font-family: 'Inter', sans-serif;
        }
        
        .glass { 
            background: rgba(255, 255, 255, 0.02); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255, 255, 255, 0.05); 
        }
        
        .news-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .news-card:hover {
            transform: translateY(-8px);
            border-color: <?= $primaryColor ?>;
            box-shadow: 0 20px 60px -20px <?= $primaryColor ?>80;
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <?php include 'components/header.php'; ?>

    <main class="max-w-7xl mx-auto px-6 py-12">
        
        <!-- Header -->
        <div class="mb-12">
            <div class="flex items-center gap-3 mb-6">
                <a href="index.php" class="text-zinc-600 hover:text-white transition">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-4xl font-black uppercase tracking-tighter">
                    <i data-lucide="newspaper" class="w-8 h-8 inline text-primary"></i>
                    Notícias do <span class="text-primary">Servidor</span>
                </h1>
            </div>
            <p class="text-zinc-500 text-lg">
                Fique por dentro de todas as atualizações, eventos e novidades
            </p>
        </div>

        <?php if (empty($news)): ?>
            <div class="glass rounded-3xl p-20 text-center border border-white/5">
                <div class="w-20 h-20 bg-zinc-900 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="newspaper" class="w-10 h-10 text-zinc-700"></i>
                </div>
                <h3 class="text-2xl font-black uppercase mb-3 text-zinc-600">Nenhuma Notícia Publicada</h3>
                <p class="text-zinc-500 mb-6">Em breve teremos novidades por aqui!</p>
                <a href="index.php" class="inline-block bg-primary hover:brightness-110 px-8 py-3 rounded-xl font-black uppercase text-sm transition">
                    Voltar ao Início
                </a>
            </div>
        <?php else: ?>
            
            <!-- Grid de Notícias -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
                <?php foreach ($news as $article): ?>
                <article class="news-card glass rounded-2xl overflow-hidden border border-white/5 flex flex-col">
                    
                    <div class="aspect-video bg-zinc-900 overflow-hidden group">
                        <?php if (!empty($article['image_url'])): ?>
                            <img src="<?= htmlspecialchars($article['image_url']) ?>" 
                                 class="w-full h-full object-cover group-hover:scale-110 transition duration-500"
                                 alt="<?= htmlspecialchars($article['title']) ?>">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <i data-lucide="file-text" class="w-16 h-16 text-zinc-700"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-6 flex flex-col flex-1">
                        <div class="flex items-center gap-3 text-xs text-zinc-600 mb-3">
                            <div class="flex items-center gap-1">
                                <i data-lucide="user" class="w-3 h-3"></i>
                                <?= htmlspecialchars($article['author'] ?? 'Admin') ?>
                            </div>
                            <span>•</span>
                            <div class="flex items-center gap-1">
                                <i data-lucide="clock" class="w-3 h-3"></i>
                                <?= timeAgo($article['published_at'] ?? $article['created_at']) ?>
                            </div>
                        </div>
                        
                        <h3 class="text-lg font-black uppercase tracking-tight mb-3 line-clamp-2 flex-1">
                            <?= htmlspecialchars($article['title']) ?>
                        </h3>
                        
                        <p class="text-sm text-zinc-500 line-clamp-3 mb-4 leading-relaxed">
                            <?= htmlspecialchars(substr($article['content'], 0, 150)) ?>...
                        </p>
                        
                        <a href="noticia.php?id=<?= $article['id'] ?>" 
                           class="inline-flex items-center gap-2 text-xs font-black uppercase text-primary hover:underline">
                            Ler Completo
                            <i data-lucide="arrow-right" class="w-3 h-3"></i>
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <!-- Paginação -->
            <?php if ($total_pages > 1): ?>
            <div class="flex items-center justify-center gap-2">
                <?php if ($page > 1): ?>
                <a href="?p=<?= $page - 1 ?>" class="glass px-4 py-2 rounded-xl hover:bg-white/10 transition">
                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                    <span class="bg-primary px-4 py-2 rounded-xl font-black"><?= $i ?></span>
                    <?php else: ?>
                    <a href="?p=<?= $i ?>" class="glass px-4 py-2 rounded-xl hover:bg-white/10 transition font-bold">
                        <?= $i ?>
                    </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?p=<?= $page + 1 ?>" class="glass px-4 py-2 rounded-xl hover:bg-white/10 transition">
                    <i data-lucide="chevron-right" class="w-5 h-5"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
        <?php endif; ?>

    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>