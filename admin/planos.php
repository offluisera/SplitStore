<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

session_start();

// 1. Verifica se está logado
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// 2. Timeout de sessão (30 minutos de inatividade)
$timeout = 1800; // 30 minutos
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=timeout');
    exit;
}
$_SESSION['last_activity'] = time();

// 3. Proteção contra session hijacking (opcional, mas recomendado)
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
}

if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=invalid_session');
    exit;
}

// 4. Regenera ID da sessão periodicamente (a cada 30 minutos)
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}


$message = "";
$messageType = "";

// Criar/Editar Plano
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $nome = trim($_POST['nome'] ?? '');
    $slug = strtolower(trim($_POST['slug'] ?? ''));
    $preco = (float)($_POST['preco'] ?? 0);
    $descricao = trim($_POST['descricao'] ?? '');
    $features = trim($_POST['features'] ?? ''); // JSON ou texto separado por linha
    $servidores = (int)($_POST['servidores'] ?? 1);
    $destaque = isset($_POST['destaque']) ? 1 : 0;
    $ordem = (int)($_POST['ordem'] ?? 0);
    $status = 'active';
    
    // Converte features para JSON se for texto
    $featuresArray = array_filter(array_map('trim', explode("\n", $features)));
    $featuresJson = json_encode($featuresArray);

    if (!empty($nome) && !empty($slug) && $preco > 0) {
        try {
            if ($_POST['action'] == 'add_plan') {
                $sql = "INSERT INTO planos (nome, slug, preco, descricao, features, servidores, destaque, ordem, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$nome, $slug, $preco, $descricao, $featuresJson, $servidores, $destaque, $ordem, $status])) {
                    $message = "Plano criado com sucesso!";
                    $messageType = "success";
                    if ($redis) $redis->del('planos_ativos');
                    header('Location: planos.php?success=created');
                    exit;
                }
            } elseif ($_POST['action'] == 'edit_plan' && isset($_POST['plan_id'])) {
                $sql = "UPDATE planos SET nome=?, slug=?, preco=?, descricao=?, features=?, servidores=?, destaque=?, ordem=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$nome, $slug, $preco, $descricao, $featuresJson, $servidores, $destaque, $ordem, $_POST['plan_id']])) {
                    $message = "Plano atualizado!";
                    $messageType = "success";
                    if ($redis) $redis->del('planos_ativos');
                    header('Location: planos.php?success=updated');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $message = "Erro: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "Preencha todos os campos obrigatórios.";
        $messageType = "error";
    }
}

// Deletar Plano
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        // Verifica se há lojas usando este plano
        $check = $pdo->prepare("SELECT COUNT(*) FROM stores WHERE plan_type = (SELECT slug FROM planos WHERE id = ?)");
        $check->execute([$_GET['delete']]);
        
        if ($check->fetchColumn() > 0) {
            $message = "Não é possível deletar: existem lojas usando este plano!";
            $messageType = "error";
        } else {
            $stmt = $pdo->prepare("DELETE FROM planos WHERE id = ?");
            $stmt->execute([$_GET['delete']]);
            if ($redis) $redis->del('planos_ativos');
            header('Location: planos.php?success=deleted');
            exit;
        }
    } catch (PDOException $e) {
        $message = "Erro ao deletar: " . $e->getMessage();
        $messageType = "error";
    }
}

// Toggle Status
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = $_GET['toggle'];
    $current = $_GET['current'] ?? 'active';
    $newStatus = ($current == 'active') ? 'inactive' : 'active';
    
    $stmt = $pdo->prepare("UPDATE planos SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    if ($redis) $redis->del('planos_ativos');
    header('Location: planos.php?success=status_updated');
    exit;
}

// Mensagens
if (isset($_GET['success'])) {
    $messages = [
        'created' => 'Plano criado com sucesso!',
        'updated' => 'Plano atualizado!',
        'deleted' => 'Plano removido!',
        'status_updated' => 'Status atualizado!'
    ];
    $message = $messages[$_GET['success']] ?? '';
    $messageType = "success";
}

// Buscar Planos
$stmt = $pdo->query("SELECT p.*, COUNT(s.id) as total_lojas 
                     FROM planos p 
                     LEFT JOIN stores s ON s.plan_type = p.slug 
                     GROUP BY p.id 
                     ORDER BY p.ordem ASC, p.preco ASC");
$planos = $stmt->fetchAll();

// Estatísticas
$totalPlanos = count($planos);
$planosAtivos = count(array_filter($planos, fn($p) => $p['status'] == 'active'));
$totalAssinaturas = array_sum(array_column($planos, 'total_lojas'));
$receitaMensal = 0;
foreach ($planos as $p) {
    $receitaMensal += ($p['preco'] * $p['total_lojas']);
}

// Edição
$editingPlan = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM planos WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editingPlan = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Planos | SplitStore Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050505; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .sidebar-item:hover { background: rgba(220, 38, 38, 0.05); color: #dc2626; }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">Gestão de <span class="text-red-600">Planos</span></h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">Configuração de pacotes e preços</p>
            </div>
            <button onclick="openModal(<?= $editingPlan ? 'true' : 'false' ?>)" class="bg-red-600 px-8 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-red-700 transition shadow-lg shadow-red-600/20 flex items-center gap-2">
                <i data-lucide="<?= $editingPlan ? 'edit' : 'plus' ?>" class="w-4 h-4"></i>
                <?= $editingPlan ? 'Editar Plano' : 'Novo Plano' ?>
            </button>
        </header>

        <?php if($message): ?>
            <div class="glass border-<?= $messageType == 'success' ? 'green' : 'red' ?>-600/20 text-<?= $messageType == 'success' ? 'green' : 'red' ?>-500 p-4 rounded-2xl mb-8 text-xs font-bold flex items-center gap-3">
                <i data-lucide="<?= $messageType == 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Total de Planos</p>
                        <h3 class="text-3xl font-black"><?= $totalPlanos ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-blue-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="package" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Planos Ativos</p>
                        <h3 class="text-3xl font-black text-green-500"><?= $planosAtivos ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-green-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                    </div>
                </div>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Assinaturas</p>
                        <h3 class="text-3xl font-black text-purple-500"><?= $totalAssinaturas ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-purple-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="users" class="w-6 h-6 text-purple-600"></i>
                    </div>
                </div>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">MRR Mensal</p>
                        <h3 class="text-3xl font-black text-red-500">R$ <?= number_format($receitaMensal, 0, ',', '.') ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-red-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="trending-up" class="w-6 h-6 text-red-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grid de Planos -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php if(empty($planos)): ?>
                <div class="col-span-full glass rounded-3xl p-24 flex flex-col items-center justify-center opacity-30">
                    <i data-lucide="inbox" class="w-16 h-16 mb-6"></i>
                    <p class="font-bold uppercase text-xs tracking-widest">Nenhum plano cadastrado</p>
                </div>
            <?php else: ?>
                <?php foreach($planos as $plan): 
                    $features = json_decode($plan['features'], true) ?: [];
                ?>
                    <div class="glass p-8 rounded-3xl border <?= $plan['destaque'] ? 'border-red-600/30 relative' : 'border-white/5' ?> hover:border-red-600/20 transition-all">
                        
                        <?php if($plan['destaque']): ?>
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-red-600 text-white text-[9px] font-black uppercase px-6 py-2 rounded-full">
                                Mais Popular
                            </div>
                        <?php endif; ?>

                        <!-- Header -->
                        <div class="text-center mb-8 pb-8 border-b border-white/5">
                            <h3 class="text-zinc-400 text-xs font-black uppercase tracking-[0.3em] mb-4"><?= htmlspecialchars($plan['nome']) ?></h3>
                            <div class="flex items-baseline justify-center gap-2">
                                <span class="text-zinc-500 text-xl">R$</span>
                                <span class="text-5xl font-black tracking-tighter"><?= number_format($plan['preco'], 2, ',', '.') ?></span>
                                <span class="text-zinc-600 text-sm">/mês</span>
                            </div>
                            <?php if($plan['descricao']): ?>
                                <p class="text-zinc-600 text-xs mt-2"><?= htmlspecialchars($plan['descricao']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Features -->
                        <ul class="space-y-3 mb-8">
                            <li class="flex items-center gap-3 text-zinc-400 text-sm">
                                <div class="w-5 h-5 bg-red-600/10 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="server" class="w-3 h-3 text-red-600"></i>
                                </div>
                                <?= $plan['servidores'] == 999 ? 'Servidores Ilimitados' : $plan['servidores'] . ' Servidor(es)' ?>
                            </li>
                            <?php foreach($features as $feature): ?>
                                <li class="flex items-center gap-3 text-zinc-400 text-sm">
                                    <div class="w-5 h-5 bg-red-600/10 rounded-full flex items-center justify-center flex-shrink-0">
                                        <i data-lucide="check" class="w-3 h-3 text-red-600"></i>
                                    </div>
                                    <?= htmlspecialchars($feature) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <!-- Stats -->
                        <div class="flex items-center justify-between mb-6 p-4 rounded-xl bg-white/5">
                            <div class="text-center">
                                <p class="text-2xl font-black text-white"><?= $plan['total_lojas'] ?></p>
                                <p class="text-[9px] text-zinc-600 font-bold uppercase">Assinaturas</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-black text-green-500">R$ <?= number_format($plan['preco'] * $plan['total_lojas'], 0, ',', '.') ?></p>
                                <p class="text-[9px] text-zinc-600 font-bold uppercase">MRR</p>
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <div class="flex items-center justify-center gap-2 mb-6">
                            <?php if($plan['status'] == 'active'): ?>
                                <span class="px-4 py-2 bg-green-600/10 border border-green-600/20 rounded-xl text-[10px] font-black uppercase text-green-500 flex items-center gap-2">
                                    <i data-lucide="check-circle" class="w-3 h-3"></i>
                                    Ativo
                                </span>
                            <?php else: ?>
                                <span class="px-4 py-2 bg-zinc-800 border border-white/5 rounded-xl text-[10px] font-black uppercase text-zinc-600 flex items-center gap-2">
                                    <i data-lucide="eye-off" class="w-3 h-3"></i>
                                    Inativo
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Ações -->
                        <div class="grid grid-cols-3 gap-2">
                            <a href="?edit=<?= $plan['id'] ?>" 
                               class="bg-blue-900/20 hover:bg-blue-900/30 text-blue-500 text-[10px] font-black uppercase py-3 rounded-xl text-center transition flex items-center justify-center gap-2">
                                <i data-lucide="edit" class="w-3 h-3"></i>
                                Editar
                            </a>
                            <a href="?toggle=<?= $plan['id'] ?>&current=<?= $plan['status'] ?>" 
                               class="bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white text-[10px] font-black uppercase py-3 rounded-xl text-center transition flex items-center justify-center gap-2">
                                <i data-lucide="<?= $plan['status'] == 'active' ? 'eye-off' : 'eye' ?>" class="w-3 h-3"></i>
                                <?= $plan['status'] == 'active' ? 'Ocultar' : 'Ativar' ?>
                            </a>
                            <a href="?delete=<?= $plan['id'] ?>" 
                               onclick="return confirm('Tem certeza? Esta ação não pode ser desfeita.')"
                               class="bg-red-900/20 hover:bg-red-900/30 text-red-500 text-[10px] font-black uppercase py-3 rounded-xl text-center transition flex items-center justify-center gap-2">
                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                                Deletar
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Criar/Editar Plano -->
    <div id="modalPlan" class="<?= $editingPlan ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-2xl p-10 rounded-[3rem] border-red-600/20 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black italic uppercase">
                    <?= $editingPlan ? 'Editar' : 'Novo' ?> <span class="text-red-600">Plano</span>
                </h3>
                <button onclick="closeModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="<?= $editingPlan ? 'edit_plan' : 'add_plan' ?>">
                <?php if($editingPlan): ?>
                    <input type="hidden" name="plan_id" value="<?= $editingPlan['id'] ?>">
                <?php endif; ?>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Nome do Plano</label>
                        <input type="text" name="nome" placeholder="Starter" required 
                               value="<?= $editingPlan ? htmlspecialchars($editingPlan['nome']) : '' ?>"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Slug (URL)</label>
                        <input type="text" name="slug" placeholder="starter" required 
                               value="<?= $editingPlan ? htmlspecialchars($editingPlan['slug']) : '' ?>"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition lowercase">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Descrição Curta</label>
                    <input type="text" name="descricao" placeholder="Perfeito para começar" 
                           value="<?= $editingPlan ? htmlspecialchars($editingPlan['descricao']) : '' ?>"
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Preço Mensal (R$)</label>
                        <input type="number" name="preco" step="0.01" min="0" placeholder="14.99" required 
                               value="<?= $editingPlan ? $editingPlan['preco'] : '' ?>"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Servidores</label>
                        <input type="number" name="servidores" min="1" placeholder="1" required 
                               value="<?= $editingPlan ? $editingPlan['servidores'] : '1' ?>"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                        <p class="text-[8px] text-zinc-700 ml-2">Use 999 para ilimitado</p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Ordem</label>
                        <input type="number" name="ordem" min="0" placeholder="0" 
                               value="<?= $editingPlan ? $editingPlan['ordem'] : '0' ?>"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Features (uma por linha)</label>
                    <textarea name="features" rows="6" placeholder="Checkout Responsivo&#10;Suporte via Ticket&#10;Plugin de Entrega" required 
                              class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition resize-none font-mono"><?= $editingPlan ? implode("\n", json_decode($editingPlan['features'], true) ?: []) : '' ?></textarea>
                </div>

                <div class="flex items-center gap-3 glass p-4 rounded-xl">
                    <input type="checkbox" name="destaque" id="destaque" 
                           <?= ($editingPlan && $editingPlan['destaque']) ? 'checked' : '' ?>
                           class="w-5 h-5 bg-white/5 border border-white/10 rounded">
                    <label for="destaque" class="text-sm font-bold cursor-pointer">
                        Marcar como <span class="text-red-600">"Mais Popular"</span>
                    </label>
                </div>

                <div class="flex gap-4 pt-6">
                    <button type="button" onclick="closeModal()" 
                            class="flex-1 py-4 font-black uppercase text-xs text-zinc-500 hover:text-white transition">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-red-600 py-4 rounded-xl font-black uppercase text-xs tracking-widest hover:bg-red-700 transition flex items-center justify-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <?= $editingPlan ? 'Salvar Alterações' : 'Criar Plano' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function openModal(isEditing = false) {
            document.getElementById('modalPlan').classList.remove('hidden');
            lucide.createIcons();
        }
        
        function closeModal() {
            const modal = document.getElementById('modalPlan');
            modal.classList.add('hidden');
            // Se estava editando, recarrega para limpar
            if (window.location.search.includes('edit=')) {
                window.location.href = 'planos.php';
            }
        }
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
        
        document.getElementById('modalPlan').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeModal();
        });
        
        <?php if($editingPlan): ?>
            openModal(true);
        <?php endif; ?>
    </script>
</body>
</html>