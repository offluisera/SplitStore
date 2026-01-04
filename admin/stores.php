<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

// Lógica de Ativação/Desativação rápida
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $current = $_GET['current'];
    $newStatus = ($current == 'active') ? 'inactive' : 'active';
    
    $stmt = $pdo->prepare("UPDATE stores SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    
    if ($redis) $redis->del('splitstore_core_metrics'); // Limpa cache da dashboard
    header('Location: stores.php?success=status_updated');
    exit;
}

// Busca todas as lojas
$stmt = $pdo->query("SELECT * FROM stores ORDER BY created_at DESC");
$stores = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Lojas | SplitStore</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050505; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .btn-red { background: #dc2626; box-shadow: 0 0 20px rgba(220, 38, 38, 0.2); }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; // Recomendo mover a sidebar para um componente ?>

    <main class="flex-1 p-12">
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">Gestão de <span class="text-red-600">Lojas</span></h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">Controle de clientes e acessos</p>
            </div>
            <button onclick="document.getElementById('modalStore').classList.remove('hidden')" class="btn-red px-8 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:scale-105 transition">
                + Nova Loja
            </button>
        </header>

        <div class="glass rounded-[2.5rem] overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-white/5">
                    <tr class="text-[10px] font-black uppercase tracking-widest text-zinc-500">
                        <th class="p-6">Nome da Loja / Dono</th>
                        <th class="p-6">Plano</th>
                        <th class="p-6">Status</th>
                        <th class="p-6">Data Cadastro</th>
                        <th class="p-6 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php foreach($stores as $s): ?>
                    <tr class="border-b border-white/5 hover:bg-white/[0.01] transition">
                        <td class="p-6">
                            <p class="font-bold italic uppercase"><?= $s['store_name'] ?></p>
                            <p class="text-[10px] text-zinc-500"><?= $s['owner_name'] ?> (<?= $s['email'] ?>)</p>
                        </td>
                        <td class="p-6">
                            <span class="px-3 py-1 bg-white/5 border border-white/10 rounded-lg text-[9px] font-black uppercase">
                                <?= $s['plan_type'] ?>
                            </span>
                        </td>
                        <td class="p-6">
                            <?php if($s['status'] == 'active'): ?>
                                <span class="text-green-500 text-[9px] font-black uppercase">● Ativo</span>
                            <?php else: ?>
                                <span class="text-red-500 text-[9px] font-black uppercase">● Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-6 text-zinc-500 text-xs">
                            <?= date('d/m/Y', strtotime($s['created_at'])) ?>
                        </td>
                        <td class="p-6 text-right">
                            <a href="?toggle_status=1&id=<?= $s['id'] ?>&current=<?= $s['status'] ?>" class="text-zinc-500 hover:text-white transition inline-block mr-4">
                                <i data-lucide="<?= $s['status'] == 'active' ? 'shield-off' : 'shield-check' ?>" class="w-4 h-4"></i>
                            </a>
                            <button class="text-zinc-500 hover:text-red-500 transition">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <div id="modalStore" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/90 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-lg p-10 rounded-[3rem] border-red-600/20">
            <h3 class="text-2xl font-black italic uppercase mb-8">Cadastrar <span class="text-red-600">Cliente</span></h3>
            
            <form action="actions/add_store.php" method="POST" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <input type="text" name="owner_name" placeholder="Nome do Dono" required class="bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    <input type="text" name="store_name" placeholder="Nome da Loja" required class="bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                </div>
                <input type="email" name="email" placeholder="E-mail de Acesso" required class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                
                <select name="plan_type" class="w-full bg-zinc-900 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition appearance-none">
                    <option value="basic">Plano Basic</option>
                    <option value="pro">Plano PRO</option>
                    <option value="ultra">Plano ULTRA</option>
                </select>

                <div class="flex gap-4 pt-6">
                    <button type="button" onclick="document.getElementById('modalStore').classList.add('hidden')" class="flex-1 py-4 font-black uppercase text-xs text-zinc-500">Cancelar</button>
                    <button type="submit" class="flex-1 btn-red py-4 rounded-xl font-black uppercase text-xs tracking-widest">Ativar Contrato</button>
                </div>
            </form>
        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>