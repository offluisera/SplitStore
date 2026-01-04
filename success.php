<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['store_data'])) {
    header('Location: index.php');
    exit;
}

$store = $_SESSION['store_data'];

// Busca as credenciais da loja
$stmt = $pdo->prepare("SELECT client_secret, api_key, store_slug FROM stores WHERE id = ?");
$stmt->execute([$store['id']]);
$credentials = $stmt->fetch();

// Limpa a sessão
unset($_SESSION['payment_data']);
unset($_SESSION['store_data']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Confirmado | SplitStore</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #000; 
            color: white;
        }
        .glass { 
            background: rgba(10, 10, 10, 0.7); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255, 255, 255, 0.05); 
        }
        .success-icon {
            animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body class="antialiased min-h-screen flex items-center justify-center p-6">

    <div class="w-full max-w-3xl">
        <div class="glass rounded-[3rem] p-12">
            
            <!-- Success Icon -->
            <div class="text-center mb-10">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-green-600/10 border-4 border-green-600/30 rounded-full mb-6 success-icon">
                    <i data-lucide="check" class="w-12 h-12 text-green-500"></i>
                </div>
                <h1 class="text-4xl font-black uppercase italic tracking-tighter mb-3">
                    Pagamento <span class="text-green-500">Confirmado!</span>
                </h1>
                <p class="text-zinc-400 text-lg font-medium">Sua loja foi ativada com sucesso</p>
            </div>

            <!-- Informações da Loja -->
            <div class="bg-zinc-900/50 border border-white/5 rounded-2xl p-8 mb-8">
                <div class="grid md:grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-[10px] font-black uppercase text-zinc-600 tracking-widest mb-3">Sua Loja</h3>
                        <p class="text-2xl font-black italic text-white mb-2"><?= htmlspecialchars($store['name']) ?></p>
                        <p class="text-sm text-zinc-500">Plano: <span class="text-red-600 font-bold"><?= htmlspecialchars($store['plan']) ?></span></p>
                        <p class="text-sm text-zinc-500 mt-1">URL: <span class="text-white font-mono text-xs">splitstore.com.br/<?= $credentials['store_slug'] ?></span></p>
                    </div>
                    <div>
                        <h3 class="text-[10px] font-black uppercase text-zinc-600 tracking-widest mb-3">Status</h3>
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                            <span class="text-sm font-black uppercase text-green-500">Ativo</span>
                        </div>
                        <p class="text-xs text-zinc-500">Próxima cobrança: <?= date('d/m/Y', strtotime('+30 days')) ?></p>
                    </div>
                </div>
            </div>

            <!-- Credenciais API -->
            <div class="glass p-8 rounded-2xl mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-sm font-black uppercase text-zinc-400">Credenciais do Plugin</h3>
                    <span class="text-[9px] font-black uppercase bg-red-600/10 text-red-500 px-3 py-1 rounded-full border border-red-600/20">Guarde em local seguro</span>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="text-[10px] font-black uppercase text-zinc-600 tracking-widest block mb-2">Client Secret</label>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly value="<?= $credentials['client_secret'] ?>" 
                                   id="clientSecret"
                                   class="flex-1 bg-black border border-white/10 p-3 rounded-xl text-xs font-mono text-zinc-400 outline-none">
                            <button onclick="copyToClipboard('clientSecret')" 
                                    class="bg-red-600/10 hover:bg-red-600/20 text-red-600 px-4 py-3 rounded-xl transition">
                                <i data-lucide="copy" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label class="text-[10px] font-black uppercase text-zinc-600 tracking-widest block mb-2">API Key</label>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly value="<?= $credentials['api_key'] ?>" 
                                   id="apiKey"
                                   class="flex-1 bg-black border border-white/10 p-3 rounded-xl text-xs font-mono text-zinc-400 outline-none">
                            <button onclick="copyToClipboard('apiKey')" 
                                    class="bg-red-600/10 hover:bg-red-600/20 text-red-600 px-4 py-3 rounded-xl transition">
                                <i data-lucide="copy" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-6 p-4 bg-yellow-900/10 border border-yellow-600/20 rounded-xl">
                    <div class="flex items-start gap-3">
                        <i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <h5 class="text-xs font-black uppercase text-yellow-500 mb-1">Importante</h5>
                            <p class="text-[10px] text-zinc-400 leading-relaxed">Estas credenciais são necessárias para configurar o plugin Java no seu servidor. Não compartilhe com terceiros.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Próximos Passos -->
            <div class="space-y-4 mb-8">
                <h3 class="text-sm font-black uppercase text-zinc-400">Próximos Passos:</h3>
                <div class="space-y-3">
                    <div class="flex items-start gap-3 p-4 bg-white/5 rounded-xl">
                        <div class="w-6 h-6 bg-red-600 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="text-white text-xs font-black">1</span>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-white mb-1">Acesse o Painel de Controle</p>
                            <p class="text-xs text-zinc-500">Configure sua loja, adicione produtos e personalize as cores</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-white/5 rounded-xl">
                        <div class="w-6 h-6 bg-zinc-700 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="text-white text-xs font-black">2</span>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-white mb-1">Instale o Plugin Java</p>
                            <p class="text-xs text-zinc-500">Baixe o SplitStore.jar e configure com suas credenciais</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 p-4 bg-white/5 rounded-xl">
                        <div class="w-6 h-6 bg-zinc-700 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="text-white text-xs font-black">3</span>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-white mb-1">Configure seu Gateway</p>
                            <p class="text-xs text-zinc-500">Adicione suas credenciais do Mercado Pago, PagSeguro ou Pagar.me</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTAs -->
            <div class="flex flex-col sm:flex-row gap-4">
                <a href="client/dashboard.php" 
                   class="flex-1 bg-red-600 hover:bg-red-700 text-white py-4 rounded-xl font-black uppercase text-xs text-center tracking-widest transition">
                    Acessar Painel
                </a>
                <a href="https://docs.splitstore.com.br/plugin" 
                   target="_blank"
                   class="flex-1 bg-zinc-900 hover:bg-zinc-800 text-white py-4 rounded-xl font-black uppercase text-xs text-center tracking-widest transition border border-white/5">
                    Baixar Plugin
                </a>
            </div>

            <div class="mt-8 pt-6 border-t border-white/5 text-center">
                <p class="text-zinc-600 text-xs">Precisa de ajuda? <a href="#" class="text-red-600 hover:underline font-bold">Fale com o suporte</a></p>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        // Confetti de celebração
        confetti({
            particleCount: 100,
            spread: 70,
            origin: { y: 0.6 }
        });

        function copyToClipboard(elementId) {
            const input = document.getElementById(elementId);
            input.select();
            document.execCommand('copy');
            
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i>';
            lucide.createIcons();
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                lucide.createIcons();
            }, 2000);
        }
    </script>
</body>
</html>