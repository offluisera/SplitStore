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

// Criar Promoção
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_promo') {
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $tipo = $_POST['tipo'] ?? 'percentual'; // percentual ou fixo
    $valor = (float)($_POST['valor'] ?? 0);
    $descricao = trim($_POST['descricao'] ?? '');
    $data_inicio = $_POST['data_inicio'] ?? date('Y-m-d');
    $data_fim = $_POST['data_fim'] ?? null;
    $limite_uso = (int)($_POST['limite_uso'] ?? 0); // 0 = ilimitado
    $valor_minimo = (float)($_POST['valor_minimo'] ?? 0);
    $status = 'active';

    if (!empty($codigo) && $valor > 0) {
        try {
            $sql = "INSERT INTO promocoes (codigo, tipo, valor, descricao, data_inicio, data_fim, limite_uso, usado, valor_minimo, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$codigo, $tipo, $valor, $descricao, $data_inicio, $data_fim, $limite_uso, $valor_minimo, $status])) {
                $message = "Promoção criada com sucesso!";
                $messageType = "success";
                if ($redis) $redis->del('promocoes_ativas');
                header('Location: promocoes.php?success=created');
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $message = "Este código de promoção já existe!";
            } else {
                $message = "Erro ao criar: " . $e->getMessage();
            }
            $messageType = "error";
        }
    } else {
        $message = "Código e valor são obrigatórios.";
        $messageType = "error";
    }
}

// Deletar Promoção
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM promocoes WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        if ($redis) $redis->del('promocoes_ativas');
        header('Location: promocoes.php?success=deleted');
        exit;
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
    
    $stmt = $pdo->prepare("UPDATE promocoes SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    if ($redis) $redis->del('promocoes_ativas');
    header('Location: promocoes.php?success=status_updated');
    exit;
}

// Mensagens
if (isset($_GET['success'])) {
    $messages = [
        'created' => 'Promoção criada com sucesso!',
        'deleted' => 'Promoção removida!',
        'status_updated' => 'Status atualizado!'
    ];
    $message = $messages[$_GET['success']] ?? '';
    $messageType = "success";
}

// Buscar Promoções
$stmt = $pdo->query("SELECT * FROM promocoes ORDER BY created_at DESC");
$promocoes = $stmt->fetchAll();

// Estatísticas
$totalPromos = count($promocoes);
$promosAtivas = count(array_filter($promocoes, fn($p) => $p['status'] == 'active'));
$totalUsados = array_sum(array_column($promocoes, 'usado'));
$totalDesconto = 0;

// Calcula desconto total dado
foreach ($promocoes as $p) {
    if ($p['tipo'] == 'percentual') {
        // Simulação - precisaria de dados de transações
        $totalDesconto += ($p['usado'] * 50 * ($p['valor'] / 100)); // Valor médio R$ 50
    } else {
        $totalDesconto += ($p['usado'] * $p['valor']);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Promoções | SplitStore Admin</title>
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
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">Sistema de <span class="text-red-600">Promoções</span></h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">Cupons e descontos para clientes</p>
            </div>
            <button onclick="openModal()" class="bg-red-600 px-8 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-red-700 transition shadow-lg shadow-red-600/20 flex items-center gap-2">
                <i data-lucide="percent" class="w-4 h-4"></i>
                Nova Promoção
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
                        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Total</p>
                        <h3 class="text-3xl font-black"><?= $totalPromos ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-blue-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="ticket" class="w-6 h-6 text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Ativas</p>
                        <h3 class="text-3xl font-black text-green-500"><?= $promosAtivas ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-green-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                    </div>
                </div>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Usos Totais</p>
                        <h3 class="text-3xl font-black text-purple-500"><?= $totalUsados ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-purple-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="trending-up" class="w-6 h-6 text-purple-600"></i>
                    </div>
                </div>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Desconto Dado</p>
                        <h3 class="text-3xl font-black text-red-500">R$ <?= number_format($totalDesconto, 0, ',', '.') ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-red-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="gift" class="w-6 h-6 text-red-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de Promoções -->
        <div class="glass rounded-3xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-white/5">
                    <tr class="text-[10px] font-black uppercase tracking-widest text-zinc-500">
                        <th class="p-6 text-left">Código</th>
                        <th class="p-6 text-left">Tipo</th>
                        <th class="p-6 text-left">Desconto</th>
                        <th class="p-6 text-left">Período</th>
                        <th class="p-6 text-center">Usado</th>
                        <th class="p-6 text-center">Status</th>
                        <th class="p-6 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($promocoes)): ?>
                        <tr>
                            <td colspan="7" class="p-12 text-center text-zinc-600 text-sm">
                                <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-4 opacity-30"></i>
                                <p class="font-bold uppercase text-xs">Nenhuma promoção cadastrada</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($promocoes as $promo): 
                            $expired = $promo['data_fim'] && strtotime($promo['data_fim']) < time();
                            $limitReached = $promo['limite_uso'] > 0 && $promo['usado'] >= $promo['limite_uso'];
                        ?>
                            <tr class="border-b border-white/5 hover:bg-white/[0.01] transition">
                                <td class="p-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-red-600/10 rounded-xl flex items-center justify-center">
                                            <i data-lucide="ticket" class="w-5 h-5 text-red-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-black uppercase text-sm"><?= htmlspecialchars($promo['codigo']) ?></p>
                                            <p class="text-[10px] text-zinc-600"><?= htmlspecialchars($promo['descricao']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-6">
                                    <span class="px-3 py-1 bg-blue-600/10 border border-blue-600/20 rounded-lg text-[10px] font-black uppercase text-blue-500">
                                        <?= $promo['tipo'] == 'percentual' ? 'Percentual' : 'Valor Fixo' ?>
                                    </span>
                                </td>
                                <td class="p-6">
                                    <p class="font-black text-lg">
                                        <?php if($promo['tipo'] == 'percentual'): ?>
                                            <?= $promo['valor'] ?>%
                                        <?php else: ?>
                                            R$ <?= number_format($promo['valor'], 2, ',', '.') ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if($promo['valor_minimo'] > 0): ?>
                                        <p class="text-[9px] text-zinc-600">Mín: R$ <?= number_format($promo['valor_minimo'], 2, ',', '.') ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="p-6 text-xs text-zinc-400">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i data-lucide="calendar" class="w-3 h-3"></i>
                                        <?= date('d/m/Y', strtotime($promo['data_inicio'])) ?>
                                    </div>
                                    <?php if($promo['data_fim']): ?>
                                        <div class="flex items-center gap-2 <?= $expired ? 'text-red-500' : '' ?>">
                                            <i data-lucide="calendar-x" class="w-3 h-3"></i>
                                            <?= date('d/m/Y', strtotime($promo['data_fim'])) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-[9px] text-green-500">Sem expiração</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-6 text-center">
                                    <div class="text-lg font-black">
                                        <?= $promo['usado'] ?><?= $promo['limite_uso'] > 0 ? '/' . $promo['limite_uso'] : '' ?>
                                    </div>
                                    <?php if($promo['limite_uso'] > 0): ?>
                                        <div class="w-full bg-zinc-900 rounded-full h-1 mt-2">
                                            <div class="bg-red-600 h-1 rounded-full" style="width: <?= min(100, ($promo['usado'] / $promo['limite_uso']) * 100) ?>%"></div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-6 text-center">
                                    <?php if($expired): ?>
                                        <span class="px-3 py-1 bg-red-600/10 border border-red-600/20 rounded-lg text-[10px] font-black uppercase text-red-500">
                                            Expirado
                                        </span>
                                    <?php elseif($limitReached): ?>
                                        <span class="px-3 py-1 bg-orange-600/10 border border-orange-600/20 rounded-lg text-[10px] font-black uppercase text-orange-500">
                                            Esgotado
                                        </span>
                                    <?php elseif($promo['status'] == 'active'): ?>
                                        <span class="px-3 py-1 bg-green-600/10 border border-green-600/20 rounded-lg text-[10px] font-black uppercase text-green-500">
                                            Ativo
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-zinc-800 border border-white/5 rounded-lg text-[10px] font-black uppercase text-zinc-600">
                                            Inativo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-6">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="?toggle=<?= $promo['id'] ?>&current=<?= $promo['status'] ?>" 
                                           class="w-8 h-8 bg-zinc-900 hover:bg-zinc-800 rounded-xl flex items-center justify-center transition text-zinc-400 hover:text-white">
                                            <i data-lucide="<?= $promo['status'] == 'active' ? 'eye-off' : 'eye' ?>" class="w-4 h-4"></i>
                                        </a>
                                        <a href="?delete=<?= $promo['id'] ?>" 
                                           onclick="return confirm('Tem certeza?')"
                                           class="w-8 h-8 bg-red-900/20 hover:bg-red-900/30 rounded-xl flex items-center justify-center transition text-red-500">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal Nova Promoção -->
    <div id="modalPromo" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-2xl p-10 rounded-[3rem] border-red-600/20 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black italic uppercase">Nova <span class="text-red-600">Promoção</span></h3>
                <button onclick="closeModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_promo">
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Código do Cupom</label>
                        <input type="text" name="codigo" placeholder="BLACKFRIDAY" required 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition uppercase">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Tipo de Desconto</label>
                        <select name="tipo" id="tipoDesconto" class="w-full bg-zinc-900 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition appearance-none">
                            <option value="percentual">Percentual (%)</option>
                            <option value="fixo">Valor Fixo (R$)</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Valor do Desconto</label>
                    <input type="number" name="valor" step="0.01" min="0" placeholder="10" required 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    <p class="text-[9px] text-zinc-700 ml-2">Ex: 10 para 10% ou R$ 10,00</p>
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Descrição</label>
                    <input type="text" name="descricao" placeholder="Black Friday - 10% OFF" 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Data Início</label>
                        <input type="date" name="data_inicio" value="<?= date('Y-m-d') ?>" 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Data Fim (Opcional)</label>
                        <input type="date" name="data_fim" 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Limite de Uso (0 = Ilimitado)</label>
                        <input type="number" name="limite_uso" value="0" min="0" 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Valor Mínimo (R$)</label>
                        <input type="number" name="valor_minimo" value="0" step="0.01" min="0" 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                </div>

                <div class="flex gap-4 pt-6">
                    <button type="button" onclick="closeModal()" 
                            class="flex-1 py-4 font-black uppercase text-xs text-zinc-500 hover:text-white transition">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-red-600 py-4 rounded-xl font-black uppercase text-xs tracking-widest hover:bg-red-700 transition flex items-center justify-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Criar Promoção
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function openModal() {
            document.getElementById('modalPromo').classList.remove('hidden');
            lucide.createIcons();
        }
        
        function closeModal() {
            document.getElementById('modalPromo').classList.add('hidden');
        }
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
        
        document.getElementById('modalPromo').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeModal();
        });
    </script>
</body>
</html>