<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

$message = "";
$messageType = ""; // success ou error

// Adicionar Feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_feedback') {
    $author = $_POST['author'] ?? '';
    $role = $_POST['role'] ?? '';
    $content = $_POST['content'] ?? '';
    $rating = (int)($_POST['rating'] ?? 5);
    $avatar_url = $_POST['avatar_url'] ?? '';

    if (!empty($author) && !empty($role) && !empty($content)) {
        try {
            $sql = "INSERT INTO feedbacks (author, role, content, rating, avatar_url, is_approved, created_at) 
                    VALUES (?, ?, ?, ?, ?, 1, NOW())";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$author, $role, $content, $rating, $avatar_url])) {
                $message = "Feedback cadastrado e aprovado com sucesso!";
                $messageType = "success";
                if ($redis) $redis->del('site_public_data_v3');
            }
        } catch (PDOException $e) {
            $message = "Erro ao cadastrar: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "Preencha todos os campos obrigatórios.";
        $messageType = "error";
    }
}

// Deletar Feedback
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM feedbacks WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        if ($redis) $redis->del('site_public_data_v3');
        header('Location: feedbacks.php?success=deleted');
        exit;
    } catch (PDOException $e) {
        $message = "Erro ao deletar: " . $e->getMessage();
        $messageType = "error";
    }
}

// Toggle Aprovação
if (isset($_GET['toggle_approval']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $current = $_GET['current'];
    $newStatus = ($current == '1') ? 0 : 1;
    
    $stmt = $pdo->prepare("UPDATE feedbacks SET is_approved = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    
    if ($redis) $redis->del('site_public_data_v3');
    header('Location: feedbacks.php?success=status_updated');
    exit;
}

// Mensagens de URL
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'deleted':
            $message = "Feedback deletado com sucesso!";
            $messageType = "success";
            break;
        case 'status_updated':
            $message = "Status de aprovação atualizado!";
            $messageType = "success";
            break;
    }
}

// Buscar todos os feedbacks
$stmt = $pdo->query("SELECT * FROM feedbacks ORDER BY created_at DESC");
$feedbacks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Feedbacks | SplitStore Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050505; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .sidebar-item:hover { background: rgba(220, 38, 38, 0.05); color: #dc2626; }
        .red-gradient { background: linear-gradient(135deg, #dc2626 0%, #7f1d1d 100%); }
        
        /* Modal Animation */
        .modal-backdrop {
            backdrop-filter: blur(8px);
            animation: fadeIn 0.2s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">Gestão de <span class="text-red-600">Feedbacks</span></h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">Depoimentos exibidos na landing page</p>
            </div>
            <button onclick="openModal()" class="bg-red-600 px-8 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-red-700 transition shadow-lg shadow-red-600/20 flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Novo Feedback
            </button>
        </header>

        <?php if($message): ?>
            <div class="glass border-<?= $messageType == 'success' ? 'green' : 'red' ?>-600/20 text-<?= $messageType == 'success' ? 'green' : 'red' ?>-500 p-4 rounded-2xl mb-8 text-xs font-bold flex items-center gap-3">
                <i data-lucide="<?= $messageType == 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas Rápidas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Total</p>
                        <h3 class="text-3xl font-black"><?= count($feedbacks) ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-blue-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="message-square" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Aprovados</p>
                        <h3 class="text-3xl font-black text-green-500">
                            <?= count(array_filter($feedbacks, fn($f) => $f['is_approved'] == 1)) ?>
                        </h3>
                    </div>
                    <div class="w-12 h-12 bg-green-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                    </div>
                </div>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Pendentes</p>
                        <h3 class="text-3xl font-black text-yellow-500">
                            <?= count(array_filter($feedbacks, fn($f) => $f['is_approved'] == 0)) ?>
                        </h3>
                    </div>
                    <div class="w-12 h-12 bg-yellow-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="clock" class="w-6 h-6 text-yellow-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grid de Feedbacks -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php if(empty($feedbacks)): ?>
                <div class="col-span-full glass rounded-[2.5rem] p-24 flex flex-col items-center justify-center opacity-30">
                    <i data-lucide="inbox" class="w-16 h-16 mb-6"></i>
                    <p class="font-bold uppercase text-xs tracking-widest">Nenhum feedback cadastrado.</p>
                </div>
            <?php else: ?>
                <?php foreach($feedbacks as $f): ?>
                    <div class="glass p-8 rounded-[2rem] flex flex-col justify-between transition-all hover:border-red-600/20 group">
                        <div>
                            <!-- Estrelas -->
                            <div class="flex gap-1 mb-4">
                                <?php for($s=0; $s < $f['rating']; $s++): ?>
                                    <i data-lucide="star" class="w-3 h-3 fill-red-600 text-red-600"></i>
                                <?php endfor; ?>
                                <?php for($s=$f['rating']; $s < 5; $s++): ?>
                                    <i data-lucide="star" class="w-3 h-3 text-zinc-800"></i>
                                <?php endfor; ?>
                            </div>
                            
                            <!-- Conteúdo -->
                            <p class="text-zinc-400 text-sm leading-relaxed mb-6 italic line-clamp-4">
                                "<?= htmlspecialchars($f['content']) ?>"
                            </p>
                        </div>
                        
                        <div class="border-t border-white/5 pt-6">
                            <!-- Autor -->
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($f['avatar_url'])): ?>
                                        <img src="<?= htmlspecialchars($f['avatar_url']) ?>" 
                                             class="w-10 h-10 rounded-full object-cover border border-red-600/20">
                                    <?php else: ?>
                                        <div class="w-10 h-10 bg-zinc-900 rounded-full border border-red-600/20 flex items-center justify-center font-black text-red-600 text-xs">
                                            <?= strtoupper(substr($f['author'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <h4 class="text-white text-xs font-black uppercase"><?= htmlspecialchars($f['author']) ?></h4>
                                        <span class="text-zinc-600 text-[10px] font-bold uppercase"><?= htmlspecialchars($f['role']) ?></span>
                                    </div>
                                </div>
                                
                                <!-- Status Badge -->
                                <span class="w-2 h-2 rounded-full <?= $f['is_approved'] == 1 ? 'bg-green-500' : 'bg-yellow-500' ?>"></span>
                            </div>
                            
                            <!-- Ações -->
                            <div class="flex gap-2">
                                <a href="?toggle_approval=1&id=<?= $f['id'] ?>&current=<?= $f['is_approved'] ?>" 
                                   class="flex-1 bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white text-[10px] font-black uppercase py-2 rounded-xl text-center transition flex items-center justify-center gap-2">
                                    <i data-lucide="<?= $f['is_approved'] == 1 ? 'eye-off' : 'check' ?>" class="w-3 h-3"></i>
                                    <?= $f['is_approved'] == 1 ? 'Ocultar' : 'Aprovar' ?>
                                </a>
                                <a href="?delete=<?= $f['id'] ?>" 
                                   onclick="return confirm('Tem certeza que deseja deletar este feedback?')"
                                   class="flex-1 bg-red-900/20 hover:bg-red-900/30 text-red-500 text-[10px] font-black uppercase py-2 rounded-xl text-center transition flex items-center justify-center gap-2">
                                    <i data-lucide="trash-2" class="w-3 h-3"></i>
                                    Deletar
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Novo Feedback -->
    <div id="modalFeedback" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 modal-backdrop p-4">
        <div class="glass w-full max-w-lg p-10 rounded-[3rem] border-red-600/20 modal-content">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black italic uppercase">Adicionar <span class="text-red-600">Depoimento</span></h3>
                <button onclick="closeModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_feedback">
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Nome do Cliente</label>
                        <input type="text" name="author" placeholder="João Silva" required 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Cargo/Servidor</label>
                        <input type="text" name="role" placeholder="Dono - Rede X" required 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Depoimento</label>
                    <textarea name="content" rows="4" placeholder="O sistema mudou a forma como gerencio minha loja..." required 
                              class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition resize-none"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Avaliação</label>
                        <select name="rating" class="w-full bg-zinc-900 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition appearance-none">
                            <option value="5">⭐⭐⭐⭐⭐ (5 estrelas)</option>
                            <option value="4">⭐⭐⭐⭐ (4 estrelas)</option>
                            <option value="3">⭐⭐⭐ (3 estrelas)</option>
                            <option value="2">⭐⭐ (2 estrelas)</option>
                            <option value="1">⭐ (1 estrela)</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Avatar (URL)</label>
                        <input type="url" name="avatar_url" placeholder="https://..." 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                </div>

                <div class="flex gap-4 pt-6">
                    <button type="button" onclick="closeModal()" 
                            class="flex-1 py-4 font-black uppercase text-xs text-zinc-500 hover:text-white transition">
                        Cancelar
                    </button>
                    <button type="submit" class="flex-1 bg-red-600 py-4 rounded-xl font-black uppercase text-xs tracking-widest hover:bg-red-700 transition flex items-center justify-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Publicar Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function openModal() {
            document.getElementById('modalFeedback').classList.remove('hidden');
            lucide.createIcons();
        }
        
        function closeModal() {
            document.getElementById('modalFeedback').classList.add('hidden');
        }
        
        // Fecha modal com ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
        
        // Fecha modal clicando fora
        document.getElementById('modalFeedback').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeModal();
        });
    </script>
</body>
</html>