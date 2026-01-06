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


// Busca todas as transações cruzando com o nome da loja
$query = "
    SELECT t.*, s.store_name 
    FROM transactions t 
    LEFT JOIN stores s ON t.store_id = s.id 
    ORDER BY t.created_at DESC
";
$stmt = $pdo->query($query);
$transactions = $stmt->fetchAll();

// Busca lojas para o select do modal de nova transação
$stores = $pdo->query("SELECT id, store_name FROM stores WHERE status = 'active'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Financeiro | SplitStore</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050505; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .sidebar-item:hover { background: rgba(220, 38, 38, 0.05); color: #dc2626; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #222; border-radius: 10px; }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter text-white">Fluxo <span class="text-red-600">Financeiro</span></h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">Monitoramento de vendas MisticPay & Manuais</p>
            </div>
            <button onclick="document.getElementById('modalTransaction').classList.remove('hidden')" class="bg-red-600 px-8 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-red-700 transition shadow-lg shadow-red-600/20">
                + Nova Transação
            </button>
        </header>

        <div class="glass rounded-[2.5rem] overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-white/5">
                    <tr class="text-[10px] font-black uppercase tracking-widest text-zinc-500">
                        <th class="p-6">ID / Referência</th>
                        <th class="p-6">Loja Cliente</th>
                        <th class="p-6">Método</th>
                        <th class="p-6">Valor</th>
                        <th class="p-6">Status</th>
                        <th class="p-6">Data</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php if(empty($transactions)): ?>
                    <tr>
                        <td colspan="6" class="p-10 text-center text-zinc-600 italic uppercase text-xs font-bold">Nenhuma transação encontrada no banco.</td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach($transactions as $t): ?>
                    <tr class="border-b border-white/5 hover:bg-white/[0.01] transition">
                        <td class="p-6">
                            <p class="font-bold text-xs">#<?= $t['id'] ?></p>
                            <p class="text-[10px] text-zinc-600 font-mono"><?= $t['external_id'] ?? 'MANUAL-REF' ?></p>
                        </td>
                        <td class="p-6 font-bold italic uppercase text-red-500">
                            <?= $t['store_name'] ?? 'Venda Direta' ?>
                        </td>
                        <td class="p-6">
                            <div class="flex items-center gap-2">
                                <i data-lucide="<?= $t['payment_method'] == 'pix' ? 'qr-code' : 'credit-card' ?>" class="w-3 h-3 text-zinc-400"></i>
                                <span class="uppercase text-[10px] font-black"><?= $t['payment_method'] ?></span>
                            </div>
                        </td>
                        <td class="p-6 font-black text-white">
                            R$ <?= number_format($t['amount'], 2, ',', '.') ?>
                        </td>
                        <td class="p-6">
                            <?php 
                                $statusClass = [
                                    'completed' => 'text-green-500 bg-green-500/10',
                                    'pending'   => 'text-yellow-500 bg-yellow-500/10',
                                    'failed'    => 'text-red-500 bg-red-500/10'
                                ];
                                $statusLabel = [
                                    'completed' => 'Pago',
                                    'pending'   => 'Pendente',
                                    'failed'    => 'Falhou'
                                ];
                            ?>
                            <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase <?= $statusClass[$t['status']] ?>">
                                <?= $statusLabel[$t['status']] ?>
                            </span>
                        </td>
                        <td class="p-6 text-zinc-500 text-[10px] font-bold">
                            <?= date('d/m/Y H:i', strtotime($t['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="modalTransaction" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/90 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-md p-10 rounded-[3rem] border-red-600/20">
            <h3 class="text-2xl font-black italic uppercase mb-8">Lançar <span class="text-red-600">Venda</span></h3>
            
            <form action="actions/add_transaction.php" method="POST" class="space-y-4">
                <div>
                    <label class="text-[10px] font-black uppercase text-zinc-500 ml-1">Loja Destino</label>
                    <select name="store_id" required class="w-full bg-zinc-900 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition mt-1 appearance-none">
                        <?php foreach($stores as $store): ?>
                            <option value="<?= $store['id'] ?>"><?= $store['store_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase text-zinc-500 ml-1">Valor (R$)</label>
                    <input type="number" step="0.01" name="amount" placeholder="0.00" required class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition mt-1">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-black uppercase text-zinc-500 ml-1">Método</label>
                        <select name="payment_method" class="w-full bg-zinc-900 border border-white/10 p-4 rounded-xl text-sm appearance-none mt-1">
                            <option value="pix">PIX (MisticPay)</option>
                            <option value="transfer">Transferência</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase text-zinc-500 ml-1">Status</label>
                        <select name="status" class="w-full bg-zinc-900 border border-white/10 p-4 rounded-xl text-sm appearance-none mt-1">
                            <option value="completed">Pago</option>
                            <option value="pending">Pendente</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-4 pt-6">
                    <button type="button" onclick="document.getElementById('modalTransaction').classList.add('hidden')" class="flex-1 py-4 font-black uppercase text-xs text-zinc-500">Cancelar</button>
                    <button type="submit" class="flex-1 bg-red-600 py-4 rounded-xl font-black uppercase text-xs tracking-widest hover:bg-red-700 transition">Registrar</button>
                </div>
            </form>
        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>