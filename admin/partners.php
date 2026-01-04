<?php
session_start();
require_once '../includes/db.php';

// Proteção de Sessão
if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

$message = "";
$messageType = "";

// Adicionar Parceiro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_partner') {
    $nome = trim($_POST['nome'] ?? '');
    $logo_url = trim($_POST['logo_url'] ?? '');
    $site_url = trim($_POST['site_url'] ?? '');
    $ordem = (int)($_POST['ordem'] ?? 0);
    $status = trim($_POST['status'] ?? 'active');
    
    // Garante que status seja exatamente 'active' ou 'inactive'
    $status = ($status === 'inactive') ? 'inactive' : 'active';

    if (!empty($nome) && !empty($logo_url)) {
        try {
            $sql = "INSERT INTO parceiros (nome, logo_url, site_url, ordem, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$nome, $logo_url, $site_url, $ordem, $status])) {
                $message = "Parceiro cadastrado com sucesso!";
                $messageType = "success";
                if ($redis) $redis->del('site_public_data_v3');
                
                // Redireciona para limpar POST
                header('Location: partners.php?success=created');
                exit;
            }
        } catch (PDOException $e) {
            $message = "Erro ao cadastrar: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "Nome e Logo são obrigatórios.";
        $messageType = "error";
    }
}

// Deletar Parceiro
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM parceiros WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        if ($redis) $redis->del('site_public_data_v3');
        header('Location: partners.php?success=deleted');
        exit;
    } catch (PDOException $e) {
        $message = "Erro ao deletar: " . $e->getMessage();
        $messageType = "error";
    }
}

// Toggle Status
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $current = $_GET['current'];
    $newStatus = ($current == 'active') ? 'inactive' : 'active';
    
    $stmt = $pdo->prepare("UPDATE parceiros SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    
    if ($redis) $redis->del('site_public_data_v3');
    header('Location: partners.php?success=status_updated');
    exit;
}

// Atualizar Ordem
if (isset($_POST['update_order']) && isset($_POST['partner_id']) && isset($_POST['new_order'])) {
    $stmt = $pdo->prepare("UPDATE parceiros SET ordem = ? WHERE id = ?");
    $stmt->execute([$_POST['new_order'], $_POST['partner_id']]);
    if ($redis) $redis->del('site_public_data_v3');
    echo json_encode(['success' => true]);
    exit;
}

// Mensagens de URL
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $message = "Parceiro cadastrado com sucesso!";
            $messageType = "success";
            break;
        case 'deleted':
            $message = "Parceiro removido com sucesso!";
            $messageType = "success";
            break;
        case 'status_updated':
            $message = "Status atualizado com sucesso!";
            $messageType = "success";
            break;
    }
}

// Buscar parceiros ordenados
$stmt = $pdo->query("SELECT * FROM parceiros ORDER BY ordem ASC, created_at DESC");
$parceiros = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Parceiros | SplitStore Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050505; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .sidebar-item:hover { background: rgba(220, 38, 38, 0.05); color: #dc2626; }
        .btn-red { background: #dc2626; box-shadow: 0 0 20px rgba(220, 38, 38, 0.2); }
        .partner-card { transition: all 0.3s ease; }
        .partner-card:hover { border-color: rgba(220, 38, 38, 0.3); transform: translateY(-2px); }
        
        /* Drag and Drop */
        .dragging {
            opacity: 0.5;
            cursor: grabbing !important;
        }
        
        .drag-over {
            border-color: #dc2626 !important;
            transform: scale(1.02);
        }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">Redes <span class="text-red-600">Parceiras</span></h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">Exibição no carrossel da landing page</p>
            </div>
            <button onclick="openModal()" class="btn-red px-8 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:scale-105 transition flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Novo Parceiro
            </button>
        </header>

        <?php if($message): ?>
            <div class="glass border-<?= $messageType == 'success' ? 'green' : 'red' ?>-600/20 text-<?= $messageType == 'success' ? 'green' : 'red' ?>-500 p-4 rounded-2xl mb-8 text-xs font-bold flex items-center gap-3">
                <i data-lucide="<?= $messageType == 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Total</p>
                        <h3 class="text-3xl font-black"><?= count($parceiros) ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-blue-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="users" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Ativos</p>
                        <h3 class="text-3xl font-black text-green-500">
                            <?= count(array_filter($parceiros, fn($p) => $p['status'] == 'active')) ?>
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
                        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Inativos</p>
                        <h3 class="text-3xl font-black text-zinc-500">
                            <?= count(array_filter($parceiros, fn($p) => $p['status'] == 'inactive')) ?>
                        </h3>
                    </div>
                    <div class="w-12 h-12 bg-zinc-800/50 rounded-xl flex items-center justify-center">
                        <i data-lucide="eye-off" class="w-6 h-6 text-zinc-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grid de Parceiros -->
        <div class="mb-6 flex items-center justify-between">
            <p class="text-zinc-600 text-xs font-bold uppercase tracking-wider">
                <i data-lucide="grip-vertical" class="w-4 h-4 inline mr-2"></i>
                Arraste para reordenar
            </p>
        </div>

        <div id="partnersGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php if(empty($parceiros)): ?>
                <div class="col-span-full glass rounded-[2.5rem] p-24 flex flex-col items-center justify-center opacity-30">
                    <i data-lucide="inbox" class="w-16 h-16 mb-6"></i>
                    <p class="font-bold uppercase text-xs tracking-widest">Nenhum parceiro cadastrado.</p>
                </div>
            <?php else: ?>
                <?php foreach($parceiros as $p): ?>
                    <div class="partner-card glass p-6 rounded-[2rem] flex flex-col justify-between cursor-move" 
                         draggable="true" 
                         data-id="<?= $p['id'] ?>"
                         data-order="<?= $p['ordem'] ?>">
                        
                        <!-- Logo -->
                        <div class="mb-6">
                            <div class="aspect-square bg-black/40 rounded-2xl flex items-center justify-center p-4 border border-white/5 mb-4 group-hover:border-red-600/20 transition">
                                <img src="<?= htmlspecialchars($p['logo_url']) ?>" 
                                     alt="<?= htmlspecialchars($p['nome']) ?>"
                                     class="max-w-full max-h-full object-contain"
                                     loading="lazy">
                            </div>
                            
                            <!-- Info -->
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-black text-sm uppercase italic truncate flex-1"><?= htmlspecialchars($p['nome']) ?></h3>
                                <span class="w-2 h-2 rounded-full flex-shrink-0 ml-2 <?= $p['status'] == 'active' ? 'bg-green-500' : 'bg-zinc-700' ?>"></span>
                            </div>
                            
                            <div class="flex items-center gap-2 text-[10px] text-zinc-600 font-bold uppercase">
                                <i data-lucide="grip-vertical" class="w-3 h-3"></i>
                                Posição #<?= $p['ordem'] ?>
                            </div>
                            
                            <?php if (!empty($p['site_url'])): ?>
                                <a href="<?= htmlspecialchars($p['site_url']) ?>" 
                                   target="_blank"
                                   class="text-[9px] text-zinc-700 hover:text-red-600 transition mt-2 flex items-center gap-1">
                                    <i data-lucide="external-link" class="w-3 h-3"></i>
                                    Visitar Site
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Ações -->
                        <div class="flex gap-2 pt-4 border-t border-white/5">
                            <a href="?toggle_status=1&id=<?= $p['id'] ?>&current=<?= $p['status'] ?>" 
                               class="flex-1 bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white text-[10px] font-black uppercase py-2 rounded-xl text-center transition flex items-center justify-center gap-2">
                                <i data-lucide="<?= $p['status'] == 'active' ? 'eye-off' : 'eye' ?>" class="w-3 h-3"></i>
                                <?= $p['status'] == 'active' ? 'Ocultar' : 'Ativar' ?>
                            </a>
                            <a href="?delete=<?= $p['id'] ?>" 
                               onclick="return confirm('Tem certeza que deseja remover este parceiro?')"
                               class="flex-1 bg-red-900/20 hover:bg-red-900/30 text-red-500 text-[10px] font-black uppercase py-2 rounded-xl text-center transition flex items-center justify-center gap-2">
                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                                Deletar
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Novo Parceiro -->
    <div id="modalPartner" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-lg p-10 rounded-[3rem] border-red-600/20">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black italic uppercase">Adicionar <span class="text-red-600">Parceiro</span></h3>
                <button onclick="closeModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_partner">
                
                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Nome da Rede</label>
                    <input type="text" name="nome" placeholder="Ex: Rede Split" required 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">URL da Logo</label>
                    <input type="url" name="logo_url" placeholder="https://i.imgur.com/..." required 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    <p class="text-[9px] text-zinc-700 ml-2 mt-1">Recomendado: 200x200px, fundo transparente</p>
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Site (Opcional)</label>
                    <input type="url" name="site_url" placeholder="https://redesplit.com" 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Posição</label>
                        <input type="number" name="ordem" value="<?= count($parceiros) ?>" min="0"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Status</label>
                        <select name="status" class="w-full bg-zinc-900 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition appearance-none">
                            <option value="active">Ativo</option>
                            <option value="inactive">Inativo</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-4 pt-6">
                    <button type="button" onclick="closeModal()" 
                            class="flex-1 py-4 font-black uppercase text-xs text-zinc-500 hover:text-white transition">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="flex-1 btn-red py-4 rounded-xl font-black uppercase text-xs tracking-widest hover:bg-red-700 transition flex items-center justify-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Adicionar Parceiro
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function openModal() {
            document.getElementById('modalPartner').classList.remove('hidden');
            lucide.createIcons();
        }
        
        function closeModal() {
            document.getElementById('modalPartner').classList.add('hidden');
        }
        
        // Fecha modal com ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
        
        // Fecha clicando fora
        document.getElementById('modalPartner').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeModal();
        });

        // DRAG AND DROP PARA REORDENAR
        const grid = document.getElementById('partnersGrid');
        const cards = document.querySelectorAll('.partner-card');
        
        let draggedElement = null;
        
        cards.forEach(card => {
            card.addEventListener('dragstart', (e) => {
                draggedElement = card;
                card.classList.add('dragging');
            });
            
            card.addEventListener('dragend', (e) => {
                card.classList.remove('dragging');
                
                // Atualiza ordem no backend
                updateOrder();
            });
            
            card.addEventListener('dragover', (e) => {
                e.preventDefault();
                card.classList.add('drag-over');
            });
            
            card.addEventListener('dragleave', (e) => {
                card.classList.remove('drag-over');
            });
            
            card.addEventListener('drop', (e) => {
                e.preventDefault();
                card.classList.remove('drag-over');
                
                if (draggedElement !== card) {
                    // Swap elements
                    const allCards = [...grid.querySelectorAll('.partner-card')];
                    const draggedIndex = allCards.indexOf(draggedElement);
                    const targetIndex = allCards.indexOf(card);
                    
                    if (draggedIndex < targetIndex) {
                        card.parentNode.insertBefore(draggedElement, card.nextSibling);
                    } else {
                        card.parentNode.insertBefore(draggedElement, card);
                    }
                }
            });
        });
        
        function updateOrder() {
            const cards = [...document.querySelectorAll('.partner-card')];
            const updates = [];
            
            cards.forEach((card, index) => {
                const id = card.dataset.id;
                const newOrder = index;
                
                fetch('partners.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `update_order=1&partner_id=${id}&new_order=${newOrder}`
                });
            });
            
            setTimeout(() => location.reload(), 500);
        }
    </script>
</body>
</html>