<?php
session_start();
require_once '../includes/db.php';

// Proteção de Sessão
if (!isset($_SESSION['admin_logged'])) {
    header('Location: login.php');
    exit;
}

$message = "";

// Lógica de Cadastro (Processada no mesmo arquivo para evitar Erro 500 externo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_partner') {
    $nome = $_POST['nome'] ?? '';
    $logo_url = $_POST['logo_url'] ?? '';
    $site_url = $_POST['site_url'] ?? '';
    $ordem = (int)($_POST['ordem'] ?? 0);
    $status = $_POST['status'] ?? 'active'; 

    if (!empty($nome) && !empty($logo_url)) {
        try {
            $sql = "INSERT INTO parceiros (nome, logo_url, site_url, ordem, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$nome, $logo_url, $site_url, $ordem, $status])) {
                $message = "Parceiro cadastrado com sucesso!";
            }
        } catch (PDOException $e) {
            $message = "Erro: " . $e->getMessage();
        }
    }
}

// Busca os parceiros
$stmt = $pdo->query("SELECT * FROM parceiros ORDER BY ordem ASC");
$parceiros = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Parceiros | SplitStore</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050505; color: white; }
        /* CSS IGUAL AO SEU STORES.PHP */
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .btn-red { background: #dc2626; box-shadow: 0 0 20px rgba(220, 38, 38, 0.2); }
        .partner-card { transition: all 0.3s ease; }
        .partner-card:hover { border-color: rgba(220, 38, 38, 0.3); transform: translateY(-2px); }
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
            <button onclick="document.getElementById('modalPartner').classList.remove('hidden')" class="btn-red px-8 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:scale-105 transition">
                + Novo Parceiro
            </button>
        </header>

        <?php if($message): ?>
            <div class="glass border-red-600/20 text-red-500 p-4 rounded-2xl mb-8 text-[10px] font-black uppercase tracking-widest text-center">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php if(empty($parceiros)): ?>
                <div class="col-span-full glass rounded-[2.5rem] p-24 flex flex-col items-center justify-center opacity-30">
                    <p class="font-bold uppercase text-[10px] tracking-widest">Nenhum parceiro cadastrado.</p>
                </div>
            <?php else: ?>
                <?php foreach($parceiros as $p): ?>
                    <div class="glass partner-card p-6 rounded-[2rem] flex items-center justify-between">
                        <div class="flex items-center gap-5">
                            <div class="w-14 h-14 bg-black/40 rounded-2xl flex items-center justify-center p-2 border border-white/5">
                                <img src="<?= $p['logo_url'] ?>" class="max-w-full max-h-full object-contain">
                            </div>
                            <div>
                                <h3 class="font-black text-sm uppercase italic"><?= $p['nome'] ?></h3>
                                <p class="text-[9px] text-zinc-500 font-bold uppercase tracking-tighter">Posição: #<?= $p['ordem'] ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <span class="w-2 h-2 rounded-full <?= $p['status'] == 'active' ? 'bg-green-500' : 'bg-zinc-700' ?>"></span>
                            <button class="text-zinc-600 hover:text-red-500 transition">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <div id="modalPartner" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-lg p-10 rounded-[3rem] border-red-600/20">
            <h3 class="text-2xl font-black italic uppercase mb-8">Adicionar <span class="text-red-600">Logo</span></h3>
            
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_partner">
                
                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Nome da Rede</label>
                    <input type="text" name="nome" placeholder="Ex: Rede Split" required class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                </div>

                <div class="space-y-1">
                    <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">URL da Logo (Imgur)</label>
                    <input type="text" name="logo_url" placeholder="https://i.imgur.com/..." required class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest">Ordem</label>
                        <input type="number" name="ordem" value="0" class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
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
                    <button type="button" onclick="document.getElementById('modalPartner').classList.add('hidden')" class="flex-1 py-4 font-black uppercase text-xs text-zinc-500">Cancelar</button>
                    <button type="submit" class="flex-1 btn-red py-4 rounded-xl font-black uppercase text-xs tracking-widest">Publicar Logo</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        // Tecla ESC fecha o modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') document.getElementById('modalPartner').classList.add('hidden');
        });
    </script>
</body>
</html>