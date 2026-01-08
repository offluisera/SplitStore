<?php
/**
 * ============================================
 * PÁGINA INDIVIDUAL DE NOTÍCIA + COMENTÁRIOS
 * ============================================
 * stores/Teste/noticia.php
 */

session_start();
require_once '../../includes/db.php';

$store_slug = basename(dirname(__FILE__));
$news_id = (int)($_GET['id'] ?? 0);

if ($news_id === 0) {
    header('Location: index.php');
    exit;
}

// Busca loja e notícia
try {
    $stmt = $pdo->prepare("
        SELECT s.*, sc.* 
        FROM stores s
        LEFT JOIN store_customization sc ON s.id = sc.store_id
        WHERE s.store_slug = ? AND s.status = 'active'
    ");
    $stmt->execute([$store_slug]);
    $store = $stmt->fetch();
    
    if (!$store) {
        die("Loja não encontrada.");
    }
    
    // Busca notícia
    $stmt = $pdo->prepare("
        SELECT * FROM news 
        WHERE id = ? AND store_id = ? AND status = 'published'
    ");
    $stmt->execute([$news_id, $store['id']]);
    $news = $stmt->fetch();
    
    if (!$news) {
        die("Notícia não encontrada.");
    }
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

$message = "";
$messageType = "";

// Verifica se usuário está logado
$is_logged = isset($_SESSION['store_user_logged']) && $_SESSION['store_user_logged'] === true;
$current_user = null;

if ($is_logged) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM store_users WHERE id = ?");
        $stmt->execute([$_SESSION['store_user_id']]);
        $current_user = $stmt->fetch();
    } catch (Exception $e) {
        $is_logged = false;
    }
}

// ========================================
// ENVIAR COMENTÁRIO
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'comment') {
    
    if (!$is_logged) {
        $message = "Você precisa estar logado para comentar.";
        $messageType = "error";
    } else {
        $comment = trim($_POST['comment'] ?? '');
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        
        if (empty($comment)) {
            $message = "Comentário não pode estar vazio.";
            $messageType = "error";
        } elseif (strlen($comment) < 5) {
            $message = "Comentário muito curto (mínimo 5 caracteres).";
            $messageType = "error";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO news_comments (news_id, user_id, store_id, comment, parent_id, is_approved)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                
                if ($stmt->execute([$news_id, $current_user['id'], $store['id'], $comment, $parent_id])) {
                    header("Location: noticia.php?id={$news_id}&success=comment_posted");
                    exit;
                }
            } catch (PDOException $e) {
                $message = "Erro ao postar comentário.";
                $messageType = "error";
            }
        }
    }
}

// ========================================
// CURTIR COMENTÁRIO
// ========================================
if (isset($_GET['like']) && $is_logged) {
    $comment_id = (int)$_GET['like'];
    
    try {
        // Verifica se já curtiu
        $check = $pdo->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
        $check->execute([$comment_id, $current_user['id']]);
        
        if ($check->fetch()) {
            // Remove curtida
            $pdo->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?")
                ->execute([$comment_id, $current_user['id']]);
        } else {
            // Adiciona curtida
            $pdo->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)")
                ->execute([$comment_id, $current_user['id']]);
        }
        
        header("Location: noticia.php?id={$news_id}#comment-{$comment_id}");
        exit;
    } catch (Exception $e) {
        error_log("Like Error: " . $e->getMessage());
    }
}

// Buscar comentários
try {
    $stmt = $pdo->prepare("
        SELECT 
            nc.*,
            su.minecraft_nick,
            su.skin_url,
            su.rank,
            su.rank_color,
            (SELECT COUNT(*) FROM comment_likes WHERE comment_id = nc.id) as like_count,
            " . ($is_logged ? "(SELECT COUNT(*) FROM comment_likes WHERE comment_id = nc.id AND user_id = ?) as user_liked" : "0 as user_liked") . "
        FROM news_comments nc
        JOIN store_users su ON nc.user_id = su.id
        WHERE nc.news_id = ? AND nc.is_deleted = 0
        ORDER BY nc.created_at ASC
    ");
    
    $params = $is_logged ? [$current_user['id'], $news_id] : [$news_id];
    $stmt->execute($params);
    $all_comments = $stmt->fetchAll();
    
    // Organizar em threads
    $comments = [];
    $replies = [];
    
    foreach ($all_comments as $comment) {
        if ($comment['parent_id'] === null) {
            $comments[] = $comment;
        } else {
            if (!isset($replies[$comment['parent_id']])) {
                $replies[$comment['parent_id']] = [];
            }
            $replies[$comment['parent_id']][] = $comment;
        }
    }
    
} catch (Exception $e) {
    $comments = [];
    $replies = [];
}

$primaryColor = $store['primary_color'] ?? '#dc2626';

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'agora mesmo';
    if ($diff < 3600) return floor($diff / 60) . ' min atrás';
    if ($diff < 86400) return floor($diff / 3600) . 'h atrás';
    if ($diff < 604800) return floor($diff / 86400) . 'd atrás';
    
    return date('d/m/Y', $time);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($news['title']) ?> | <?= htmlspecialchars($store['store_name']) ?></title>
    
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
        
        .comment-card {
            transition: all 0.2s ease;
        }
        
        .comment-card:hover {
            background: rgba(255, 255, 255, 0.03);
        }
    </style>
</head>
<body>

    <!-- Header Fixo -->
    <header class="fixed top-0 w-full z-50 glass border-b border-white/5">
        <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3 hover:opacity-80 transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
                <span class="font-bold text-sm uppercase">Voltar</span>
            </a>
            
            <?php if ($is_logged): ?>
                <div class="flex items-center gap-3">
                    <img src="<?= htmlspecialchars($current_user['skin_url']) ?>" class="w-8 h-8 rounded-lg">
                    <span class="text-sm font-bold"><?= htmlspecialchars($current_user['minecraft_nick']) ?></span>
                </div>
            <?php else: ?>
                <a href="auth.php" class="bg-primary hover:brightness-110 px-6 py-2 rounded-xl text-xs font-black uppercase transition">
                    Login
                </a>
            <?php endif; ?>
        </div>
    </header>

    <main class="pt-24 pb-16 px-6">
        <article class="max-w-4xl mx-auto">
            
            <!-- Imagem Destaque -->
            <?php if (!empty($news['image_url'])): ?>
            <div class="aspect-video rounded-3xl overflow-hidden mb-8">
                <img src="<?= htmlspecialchars($news['image_url']) ?>" class="w-full h-full object-cover">
            </div>
            <?php endif; ?>

            <!-- Meta Info -->
            <div class="flex items-center gap-4 mb-6 text-sm text-zinc-500">
                <div class="flex items-center gap-2">
                    <i data-lucide="user" class="w-4 h-4"></i>
                    <span><?= htmlspecialchars($news['author'] ?? 'Admin') ?></span>
                </div>
                <span>•</span>
                <div class="flex items-center gap-2">
                    <i data-lucide="calendar" class="w-4 h-4"></i>
                    <span><?= date('d/m/Y H:i', strtotime($news['published_at'] ?? $news['created_at'])) ?></span>
                </div>
                <span>•</span>
                <div class="flex items-center gap-2">
                    <i data-lucide="message-circle" class="w-4 h-4"></i>
                    <span><?= count($comments) ?> comentários</span>
                </div>
            </div>

            <!-- Título -->
            <h1 class="text-5xl font-black uppercase tracking-tight leading-tight mb-8">
                <?= htmlspecialchars($news['title']) ?>
            </h1>

            <!-- Conteúdo -->
            <div class="prose prose-invert prose-lg max-w-none mb-16">
                <p class="text-zinc-300 text-lg leading-relaxed whitespace-pre-line">
                    <?= nl2br(htmlspecialchars($news['content'])) ?>
                </p>
            </div>

            <!-- Seção de Comentários -->
            <section id="comentarios" class="pt-16 border-t border-white/5">
                <div class="flex items-center justify-between mb-8">
                    <h2 class="text-2xl font-black uppercase">
                        Comentários <span class="text-primary">(<?= count($comments) ?>)</span>
                    </h2>
                </div>

                <!-- Form: Novo Comentário -->
                <?php if ($is_logged): ?>
                    <form method="POST" class="glass rounded-2xl p-6 mb-8">
                        <input type="hidden" name="action" value="comment">
                        
                        <div class="flex gap-4">
                            <img src="<?= htmlspecialchars($current_user['skin_url']) ?>" class="w-12 h-12 rounded-lg flex-shrink-0">
                            
                            <div class="flex-1">
                                <textarea name="comment" required rows="3" placeholder="Escreva seu comentário..."
                                          class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-primary transition resize-none mb-3"></textarea>
                                
                                <div class="flex justify-end">
                                    <button type="submit" class="bg-primary hover:brightness-110 px-6 py-2.5 rounded-xl text-xs font-black uppercase transition">
                                        Comentar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="glass rounded-2xl p-8 text-center mb-8">
                        <i data-lucide="lock" class="w-12 h-12 mx-auto mb-4 text-zinc-600"></i>
                        <p class="text-zinc-400 mb-4">Faça login para comentar</p>
                        <a href="auth.php" class="inline-block bg-primary hover:brightness-110 px-8 py-3 rounded-xl text-sm font-black uppercase transition">
                            Fazer Login
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Lista de Comentários -->
                <?php if (empty($comments)): ?>
                    <div class="glass rounded-2xl p-16 text-center opacity-30">
                        <i data-lucide="message-circle" class="w-16 h-16 mx-auto mb-4 text-zinc-700"></i>
                        <p class="text-zinc-600">Nenhum comentário ainda. Seja o primeiro!</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($comments as $comment): ?>
                        <div id="comment-<?= $comment['id'] ?>" class="comment-card glass rounded-2xl p-6">
                            
                            <!-- Header do Comentário -->
                            <div class="flex gap-4">
                                <img src="<?= htmlspecialchars($comment['skin_url']) ?>" class="w-12 h-12 rounded-lg flex-shrink-0">
                                
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="font-black text-sm"><?= htmlspecialchars($comment['minecraft_nick']) ?></span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase" 
                                              style="background: <?= $comment['rank_color'] ?>15; color: <?= $comment['rank_color'] ?>;">
                                            <?= htmlspecialchars($comment['rank']) ?>
                                        </span>
                                        <span class="text-xs text-zinc-600">•</span>
                                        <span class="text-xs text-zinc-600"><?= timeAgo($comment['created_at']) ?></span>
                                    </div>
                                    
                                    <p class="text-sm text-zinc-300 leading-relaxed mb-3 whitespace-pre-line"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                                    
                                    <!-- Ações -->
                                    <div class="flex items-center gap-4">
                                        <a href="?id=<?= $news_id ?>&like=<?= $comment['id'] ?>" 
                                           class="flex items-center gap-1.5 text-xs font-bold uppercase <?= $comment['user_liked'] > 0 ? 'text-primary' : 'text-zinc-600 hover:text-primary' ?> transition">
                                            <i data-lucide="heart" class="w-3.5 h-3.5 <?= $comment['user_liked'] > 0 ? 'fill-current' : '' ?>"></i>
                                            <?= $comment['like_count'] ?>
                                        </a>
                                        
                                        <?php if ($is_logged): ?>
                                        <button onclick="showReplyForm(<?= $comment['id'] ?>)" class="text-xs font-bold uppercase text-zinc-600 hover:text-white transition">
                                            Responder
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Form de Resposta (oculto) -->
                                    <?php if ($is_logged): ?>
                                    <form method="POST" id="reply-form-<?= $comment['id'] ?>" class="hidden mt-4 pl-4 border-l-2 border-primary/30">
                                        <input type="hidden" name="action" value="comment">
                                        <input type="hidden" name="parent_id" value="<?= $comment['id'] ?>">
                                        
                                        <textarea name="comment" required rows="2" placeholder="Escreva sua resposta..."
                                                  class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-primary transition resize-none mb-2"></textarea>
                                        
                                        <div class="flex gap-2">
                                            <button type="submit" class="bg-primary hover:brightness-110 px-4 py-2 rounded-lg text-xs font-black uppercase transition">
                                                Enviar
                                            </button>
                                            <button type="button" onclick="hideReplyForm(<?= $comment['id'] ?>)" class="bg-zinc-900 hover:bg-zinc-800 px-4 py-2 rounded-lg text-xs font-black uppercase transition">
                                                Cancelar
                                            </button>
                                        </div>
                                    </form>
                                    <?php endif; ?>

                                    <!-- Respostas -->
                                    <?php if (isset($replies[$comment['id']])): ?>
                                        <div class="mt-4 space-y-3 pl-4 border-l-2 border-white/5">
                                            <?php foreach ($replies[$comment['id']] as $reply): ?>
                                            <div id="comment-<?= $reply['id'] ?>" class="flex gap-3">
                                                <img src="<?= htmlspecialchars($reply['skin_url']) ?>" class="w-8 h-8 rounded-lg flex-shrink-0">
                                                
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span class="font-bold text-xs"><?= htmlspecialchars($reply['minecraft_nick']) ?></span>
                                                        <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase" 
                                                              style="background: <?= $reply['rank_color'] ?>15; color: <?= $reply['rank_color'] ?>;">
                                                            <?= htmlspecialchars($reply['rank']) ?>
                                                        </span>
                                                        <span class="text-[10px] text-zinc-600"><?= timeAgo($reply['created_at']) ?></span>
                                                    </div>
                                                    
                                                    <p class="text-xs text-zinc-400 leading-relaxed whitespace-pre-line"><?= nl2br(htmlspecialchars($reply['comment'])) ?></p>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </article>
    </main>

    <script>
        lucide.createIcons();
        
        function showReplyForm(commentId) {
            document.querySelectorAll('[id^="reply-form-"]').forEach(form => form.classList.add('hidden'));
            document.getElementById('reply-form-' + commentId).classList.remove('hidden');
            document.getElementById('reply-form-' + commentId).querySelector('textarea').focus();
        }
        
        function hideReplyForm(commentId) {
            document.getElementById('reply-form-' + commentId).classList.add('hidden');
        }
    </script>
</body>
</html>