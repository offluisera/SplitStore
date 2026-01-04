<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

$message = "";

// Adicionar Feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_feedback') {
    $nome = $_POST['nome'] ?? '';
    $cargo = $_POST['cargo'] ?? '';
    $texto = $_POST['texto'] ?? '';
    $estrelas = (int)($_POST['estrelas'] ?? 5);
    $avatar_url = $_POST['avatar_url'] ?? '';

    if (!empty($nome) && !empty($cargo) && !empty($texto)) {
        try {
            $sql = "INSERT INTO feedbacks (nome, cargo, texto, estrelas, avatar_url, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'active', NOW())";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$nome, $cargo, $texto, $estrelas, $avatar_url])) {
                $message = "Feedback cadastrado com sucesso!";
                if ($redis) $redis->del('site_public_data');
            }
        } catch (PDOException $e) {
            $message = "Erro: " . $e->getMessage();
        }
    }
}

// Deletar Feedback
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM feedbacks WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        if ($redis) $redis->del('site_public_data');
        header('Location: feedbacks.php?success=deleted');
        exit;
    } catch (PDOException $e) {
        $message = "Erro ao deletar: " . $e->getMessage();
    }
}

// Toggle Status
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $current = $_GET['current'];
    $newStatus = ($current == 'active') ? 'inactive' : 'active';
    
    $stmt = $pdo->prepare("UPDATE feedbacks SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    
    if ($redis) $redis->del('site_public_data');
    header('Location: feedbacks.php?success=status_updated');
    exit;
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
            <button onclick="document.getElementById('modalFeedback').classList.remove('hidden')" class="bg-red-600 px-8 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-red-700 transition shadow-lg shadow-red-600/20">
                + Novo Feedback
            </button>
        </header>

        <?php if($message): ?>
            <div class="glass border-red-600/20 text-red-500 p-4 rounded-2xl mb-8 text-xs font-bold text-center">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php if(empty($feedbacks)): ?>
                <div class="col-span-full glass rounded-[2.5rem] p-24 flex flex-col items-center justify-center opacity-30">
                    <p class="font-bold uppercase text-xs tracking-widest">Nenhum feedback cadastrado.</p>
                </div>
            <?php else: ?>
                <?php foreach($feedbacks as $f): ?>
                    <div class="glass p-8 rounded-[2rem] flex flex-col justify-between transition-all hover:border-red-600/20">
                        <div>
                            <div class="flex gap-1 mb-4">
                                <?php for($s=0; $s < $f['estrelas']; $s++): ?>
                                    <i data-lucide="star" class="w-3 h-3 fill-red-600 text-red-600"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="text-zinc-400 text-sm leading-relaxed mb-6 italic">"<?= htmlspecialchars($f['texto']) ?>"</p>
                        </div>
                        
                        <div class="border-t border-white/5 pt-6">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-zinc-900 rounded-full border border-red-600/20 flex items-center justify-center font-black text-red-600 text-xs">
                                        <?= strtoupper(substr($f['nome'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <h4 class="text-white text-xs font-black uppercase"><?= htmlspecialchars($f['nome']) ?></h4>
                                        <span class="text-zinc-600 text-[10px] font-bold uppercase"><?= htmlspecialchars($f['cargo']) ?></span>
                                    </div>
                                </div>
                                <span class="w-2 h-2 rounded-full <?= $f['status'] == 'active' ? 'bg-green-500' : 'bg-zinc-700' ?>"></span>
                            </div>
                            
                            <div class="flex gap-2">
                                <a href="?toggle_status=1&id=<?= $f['id'] ?>&current=<?= $f['status'] ?>" 
                                   class="flex-1 bg-zinc-900 hover:bg-zinc-800 text-zinc-400 text-[10px] font-black uppercase py-2 rounded-xl text-center transition">
                                    <?= $f['status'] == 'active' ? 'Ocultar' : 'Ativar' ?>
                                </a>
                                <a href="?delete=<?= $f['id'] ?>" 
                                   onclick="return confirm('Tem certeza que deseja deletar este feedback?')"
                                   class="flex-1 bg-red-900/20 hover:bg-red-900/30 text-red-500 text-[10px] font-black uppercase py-2 rounded-xl text-center transition">
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
    <div id="modalFeedback" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-lg p-10 rounded-[3rem] border-red-600/20">
            <h3 class="text-2xl font-black italic uppercase mb-8">Adicionar <span class="text-red-600">Depoimento</span></h3>
            
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_feedback">
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Nome</label>
                        <input type="text" name="nome" placeholder="João Silva" required 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Cargo</label>
                        <input type="text" name="cargo" placeholder="Dono - Rede X" required 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Depoimento</label>
                    <textarea name="texto" rows="4" placeholder="O sistema mudou a forma como gerencio minha loja..." required 
                              class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition resize-none"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Estrelas</label>
                        <select name="estrelas" class="w-full bg-zinc-900 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition appearance-none">
                            <option value="5">⭐⭐⭐⭐⭐</option>
                            <option value="4">⭐⭐⭐⭐</option>
                            <option value="3">⭐⭐⭐</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Avatar (opcional)</label>
                        <input type="url" name="avatar_url" placeholder="https://..." 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                </div>

                <div class="flex gap-4 pt-6">
                    <button type="button" onclick="document.getElementById('modalFeedback').classList.add('hidden')" 
                            class="flex-1 py-4 font-black uppercase text-xs text-zinc-500 hover:text-white transition">
                        Cancelar
                    </button>
                    <button type="submit" class="flex-1 bg-red-600 py-4 rounded-xl font-black uppercase text-xs tracking-widest hover:bg-red-700 transition">
                        Publicar Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') document.getElementById('modalFeedback').classList.add('hidden');
        });
    </script>
</body>
</html>