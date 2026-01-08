<?php
/**
 * ============================================
 * NOTÍCIAS - GERENCIAMENTO
 * ============================================
 */

session_start();
require_once '../includes/db.php';
require_once '../includes/auth_guard.php';

requireAccess(__FILE__);

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];

$message = "";
$messageType = "";

// Criar/Editar notícia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'create_news' || $_POST['action'] === 'edit_news') {
        
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $author = trim($_POST['author'] ?? '') ?: $_SESSION['store_name'];
        $status = $_POST['status'] ?? 'draft';
        $published_at = ($status === 'published') ? date('Y-m-d H:i:s') : null;
        
        if (empty($title) || empty($content)) {
            $message = "Título e conteúdo são obrigatórios";
            $messageType = "error";
        } else {
            try {
                if ($_POST['action'] === 'create_news') {
                    $stmt = $pdo->prepare("
                        INSERT INTO news (store_id, title, content, image_url, author, status, published_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$store_id, $title, $content, $image_url, $author, $status, $published_at]);
                    
                    header('Location: noticias.php?success=created');
                    exit;
                } else {
                    $news_id = (int)$_POST['news_id'];
                    $stmt = $pdo->prepare("
                        UPDATE news 
                        SET title = ?, content = ?, image_url = ?, author = ?, status = ?, 
                            published_at = CASE WHEN status = 'draft' AND ? = 'published' THEN NOW() ELSE published_at END
                        WHERE id = ? AND store_id = ?
                    ");
                    $stmt->execute([$title, $content, $image_url, $author, $status, $status, $news_id, $store_id]);
                    
                    header('Location: noticias.php?success=updated');
                    exit;
                }
            } catch (PDOException $e) {
                $message = "Erro: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

// Deletar notícia
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM news WHERE id = ? AND store_id = ?");
        $stmt->execute([$_GET['delete'], $store_id]);
        header('Location: noticias.php?success=deleted');
        exit;
    } catch (PDOException $e) {
        $message = "Erro ao deletar: " . $e->getMessage();
        $messageType = "error";
    }
}

// Buscar notícias
try {
    $stmt = $pdo->prepare("
        SELECT * FROM news 
        WHERE store_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$store_id]);
    $news_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("News Error: " . $e->getMessage());
    $news_list = [];
}

// Buscar notícia para edição
$editing = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM news WHERE id = ? AND store_id = ?");
        $stmt->execute([$_GET['edit'], $store_id]);
        $editing = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Edit Error: " . $e->getMessage());
    }
}

if (isset($_GET['success'])) {
    $messages = [
        'created' => '✓ Notícia criada com sucesso!',
        'updated' => '✓ Notícia atualizada!',
        'deleted' => '✓ Notícia removida!'
    ];
    $message = $messages[$_GET['success']] ?? '';
    $messageType = "success";
}

function getStatusBadge($status) {
    if ($status === 'published') {
        return '<span class="bg-green-500/10 text-green-500 border border-green-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">Publicado</span>';
    } else {
        return '<span class="bg-yellow-500/10 text-yellow-500 border border-yellow-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">Rascunho</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Notícias | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #000; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .news-card { transition: all 0.3s ease; }
        .news-card:hover { transform: translateY(-4px); border-color: rgba(220, 38, 38, 0.3); }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        
        <!-- Header -->
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">
                    Mural de <span class="text-red-600">Notícias</span>
                </h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">
                    Gerencie atualizações e novidades da sua loja
                </p>
            </div>
            
            <button onclick="openNewsModal()" class="bg-red-600 px-8 py-4 rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-red-700 transition shadow-lg shadow-red-600/20 flex items-center gap-3">
                <i data-lucide="plus" class="w-5 h-5"></i>
                Nova Notícia
            </button>
        </header>

        <?php if($message): ?>
            <div class="glass border-<?= $messageType === 'success' ? 'green' : 'red' ?>-600/20 text-<?= $messageType === 'success' ? 'green' : 'red' ?>-500 p-5 rounded-2xl mb-8 flex items-center gap-3">
                <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <span class="font-bold"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Preview da Loja -->
        <div class="glass border-blue-600/20 bg-blue-600/5 p-6 rounded-2xl mb-8">
            <div class="flex items-start gap-4">
                <i data-lucide="eye" class="w-6 h-6 text-blue-500 flex-shrink-0"></i>
                <div class="flex-1">
                    <h3 class="text-sm font-black uppercase text-blue-500 mb-2">Preview na Loja</h3>
                    <p class="text-xs text-zinc-400 mb-3">
                        As notícias publicadas aparecem no topo da sua loja para os clientes.
                    </p>
                    <a href="../stores/<?= htmlspecialchars($store['store_slug'] ?? 'Teste') ?>/" target="_blank" 
                       class="inline-flex items-center gap-2 text-xs font-bold text-blue-500 hover:underline">
                        Ver loja <i data-lucide="external-link" class="w-3 h-3"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Lista de Notícias -->
        <div class="grid gap-6">
            <?php if (empty($news_list)): ?>
                <div class="glass rounded-3xl p-24 text-center opacity-30">
                    <i data-lucide="newspaper" class="w-16 h-16 mx-auto mb-4 text-zinc-700"></i>
                    <p class="text-xs font-bold uppercase tracking-widest text-zinc-700">
                        Nenhuma notícia publicada ainda
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($news_list as $news): ?>
                <div class="news-card glass p-6 rounded-3xl border border-white/5">
                    <div class="flex gap-6">
                        
                        <!-- Imagem -->
                        <div class="w-48 h-32 bg-zinc-900 rounded-xl overflow-hidden flex-shrink-0">
                            <?php if (!empty($news['image_url'])): ?>
                                <img src="<?= htmlspecialchars($news['image_url']) ?>" 
                                     class="w-full h-full object-cover" 
                                     alt="<?= htmlspecialchars($news['title']) ?>">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i data-lucide="image" class="w-12 h-12 text-zinc-700"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Conteúdo -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h3 class="text-xl font-black uppercase mb-2 line-clamp-1">
                                        <?= htmlspecialchars($news['title']) ?>
                                    </h3>
                                    <p class="text-sm text-zinc-500 line-clamp-2 leading-relaxed mb-3">
                                        <?= htmlspecialchars($news['content']) ?>
                                    </p>
                                </div>
                                <?= getStatusBadge($news['status']) ?>
                            </div>

                            <!-- Meta Info -->
                            <div class="flex items-center gap-6 text-xs text-zinc-600 mb-4">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="user" class="w-3 h-3"></i>
                                    <span><?= htmlspecialchars($news['author'] ?? 'Autor') ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i data-lucide="calendar" class="w-3 h-3"></i>
                                    <span><?= date('d/m/Y H:i', strtotime($news['created_at'])) ?></span>
                                </div>
                                <?php if ($news['status'] === 'published' && $news['published_at']): ?>
                                <div class="flex items-center gap-2">
                                    <i data-lucide="radio" class="w-3 h-3"></i>
                                    <span>Publicado em <?= date('d/m/Y', strtotime($news['published_at'])) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Ações -->
                            <div class="flex gap-3">
                                <a href="?edit=<?= $news['id'] ?>" 
                                   onclick="openNewsModal(<?= htmlspecialchars(json_encode($news)) ?>); return false;"
                                   class="flex-1 bg-blue-900/20 hover:bg-blue-900/30 text-blue-500 text-[10px] font-black uppercase py-2 rounded-xl text-center transition">
                                    Editar
                                </a>
                                <a href="?delete=<?= $news['id'] ?>" 
                                   onclick="return confirm('Tem certeza que deseja deletar esta notícia?')"
                                   class="flex-1 bg-red-900/20 hover:bg-red-900/30 text-red-500 text-[10px] font-black uppercase py-2 rounded-xl text-center transition">
                                    Deletar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

    <!-- Modal: Criar/Editar Notícia -->
    <div id="newsModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-4xl p-10 rounded-[3rem] border-red-600/20 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black italic uppercase">
                    <span id="modalTitle">Nova</span> <span class="text-red-600">Notícia</span>
                </h3>
                <button onclick="closeNewsModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <form method="POST" id="newsForm" class="space-y-6">
                <input type="hidden" name="action" id="formAction" value="create_news">
                <input type="hidden" name="news_id" id="newsId">

                <!-- Título -->
                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Título da Notícia *</label>
                    <input type="text" name="title" id="newsTitle" required
                           placeholder="Ex: Nova atualização disponível!"
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                </div>

                <!-- Conteúdo -->
                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Conteúdo *</label>
                    <textarea name="content" id="newsContent" required rows="6"
                              placeholder="Escreva o conteúdo da notícia..."
                              class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition resize-none"></textarea>
                    <p class="text-[10px] text-zinc-600 mt-2 ml-1">Máximo de 500 caracteres recomendado</p>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <!-- URL da Imagem -->
                    <div>
                        <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">URL da Imagem</label>
                        <input type="url" name="image_url" id="newsImage"
                               placeholder="https://exemplo.com/imagem.jpg"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                        <p class="text-[10px] text-zinc-600 mt-2 ml-1">Opcional - Deixe vazio para sem imagem</p>
                    </div>

                    <!-- Autor -->
                    <div>
                        <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Autor</label>
                        <input type="text" name="author" id="newsAuthor"
                               placeholder="<?= htmlspecialchars($store_name) ?>"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                        <p class="text-[10px] text-zinc-600 mt-2 ml-1">Nome de quem está publicando</p>
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Status de Publicação</label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="glass p-4 rounded-xl cursor-pointer border-2 border-white/5 hover:border-green-600/30 transition">
                            <input type="radio" name="status" value="published" id="statusPublished" class="hidden peer">
                            <div class="peer-checked:border-green-600/50 peer-checked:bg-green-600/10 border border-white/5 rounded-lg p-4 transition">
                                <div class="flex items-center gap-3 mb-2">
                                    <i data-lucide="radio" class="w-5 h-5 text-green-500"></i>
                                    <span class="font-black uppercase text-sm">Publicar</span>
                                </div>
                                <p class="text-xs text-zinc-500">Visível na loja imediatamente</p>
                            </div>
                        </label>

                        <label class="glass p-4 rounded-xl cursor-pointer border-2 border-white/5 hover:border-yellow-600/30 transition">
                            <input type="radio" name="status" value="draft" id="statusDraft" checked class="hidden peer">
                            <div class="peer-checked:border-yellow-600/50 peer-checked:bg-yellow-600/10 border border-white/5 rounded-lg p-4 transition">
                                <div class="flex items-center gap-3 mb-2">
                                    <i data-lucide="edit" class="w-5 h-5 text-yellow-500"></i>
                                    <span class="font-black uppercase text-sm">Rascunho</span>
                                </div>
                                <p class="text-xs text-zinc-500">Salvar para publicar depois</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="glass p-6 rounded-2xl border border-blue-600/20 bg-blue-600/5">
                    <div class="flex items-start gap-3">
                        <i data-lucide="lightbulb" class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <p class="text-xs font-bold text-blue-500 mb-2">Dicas</p>
                            <ul class="text-xs text-zinc-400 space-y-1 leading-relaxed">
                                <li>• Use títulos atrativos e diretos</li>
                                <li>• Imagens chamam mais atenção dos clientes</li>
                                <li>• Notícias recentes aparecem primeiro na loja</li>
                                <li>• Publique atualizações, eventos e promoções</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Botões -->
                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeNewsModal()" 
                            class="flex-1 bg-zinc-900 hover:bg-zinc-800 py-4 rounded-xl font-black uppercase text-xs transition">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-red-600 hover:bg-red-700 py-4 rounded-xl font-black uppercase text-xs tracking-widest transition shadow-lg shadow-red-600/20 flex items-center justify-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        <span id="submitText">Criar Notícia</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function openNewsModal(news = null) {
            if (news) {
                // Modo edição
                document.getElementById('modalTitle').textContent = 'Editar';
                document.getElementById('formAction').value = 'edit_news';
                document.getElementById('newsId').value = news.id;
                document.getElementById('newsTitle').value = news.title;
                document.getElementById('newsContent').value = news.content;
                document.getElementById('newsImage').value = news.image_url || '';
                document.getElementById('newsAuthor').value = news.author || '';
                document.getElementById('submitText').textContent = 'Salvar Alterações';
                
                if (news.status === 'published') {
                    document.getElementById('statusPublished').checked = true;
                } else {
                    document.getElementById('statusDraft').checked = true;
                }
            } else {
                // Modo criação
                document.getElementById('modalTitle').textContent = 'Nova';
                document.getElementById('formAction').value = 'create_news';
                document.getElementById('newsForm').reset();
                document.getElementById('submitText').textContent = 'Criar Notícia';
            }
            
            document.getElementById('newsModal').classList.remove('hidden');
            lucide.createIcons();
        }
        
        function closeNewsModal() {
            document.getElementById('newsModal').classList.add('hidden');
        }
        
        document.getElementById('newsModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeNewsModal();
        });

        // Abre modal automaticamente se estiver editando
        <?php if ($editing): ?>
        openNewsModal(<?= json_encode($editing) ?>);
        <?php endif; ?>
    </script>
</body>
</html>