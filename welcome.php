<?php
/**
 * ============================================
 * SPLITSTORE - PÁGINA DE BOAS-VINDAS
 * ============================================
 * Exibida após confirmação do pagamento
 */

session_start();
require_once 'includes/db.php';

// Verifica se há dados na sessão
if (!isset($_SESSION['store_data'])) {
    header('Location: index.php');
    exit;
}

$store = $_SESSION['store_data'];

// Busca informações completas da loja
try {
    $stmt = $pdo->prepare("
        SELECT 
            id, store_name, store_slug, email, 
            api_key, api_secret, plan, status
        FROM stores 
        WHERE id = ?
    ");
    $stmt->execute([$store['id']]);
    $store_full = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$store_full) {
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching store: " . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Limpa os dados da sessão (pagamento concluído)
unset($_SESSION['payment_data']);
unset($_SESSION['store_data']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo ao SplitStore! 🎉</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background: #000; 
            color: #fff;
        }
        
        #particles-js {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: 1;
            top: 0;
            left: 0;
            pointer-events: none;
        }
        
        .content-wrapper {
            position: relative;
            z-index: 10;
        }
        
        .glass {
            background: rgba(15, 15, 15, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .glass-strong {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .success-icon {
            animation: scaleIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
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
        
        .slide-up {
            animation: slideUp 0.6s ease-out;
        }
        
        .credential-box {
            transition: all 0.3s ease;
        }
        
        .credential-box:hover {
            transform: translateY(-2px);
            border-color: rgba(34, 197, 94, 0.3);
        }
    </style>
</head>
<body>

    <div id="particles-js"></div>

    <div class="content-wrapper min-h-screen py-12 px-4">
        
        <!-- Header -->
        <div class="max-w-6xl mx-auto mb-12 slide-up">
            <div class="flex items-center justify-center mb-8">
                <div class="w-12 h-12 bg-gradient-to-br from-red-600 to-red-900 rounded-xl flex items-center justify-center font-black shadow-lg">
                    S
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-5xl mx-auto">
            
            <!-- Success Message -->
            <div class="text-center mb-16 slide-up" style="animation-delay: 0.1s;">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-green-600/10 border-4 border-green-600/30 rounded-full mb-8 success-icon">
                    <i data-lucide="check" class="w-12 h-12 text-green-500"></i>
                </div>
                
                <h1 class="text-5xl md:text-6xl font-black uppercase tracking-tighter mb-4">
                    Bem-vindo ao <span class="text-red-600">SplitStore!</span>
                </h1>
                
                <p class="text-zinc-400 text-xl max-w-2xl mx-auto mb-8">
                    Sua loja foi criada com sucesso e está pronta para começar a vender. 🚀
                </p>
                
                <!-- Status Badge -->
                <div class="inline-flex items-center gap-2 bg-green-600/10 border border-green-600/20 px-6 py-3 rounded-full">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-sm font-black uppercase text-green-500 tracking-wider">
                        Loja Ativa • Pronto para Vender
                    </span>
                </div>
            </div>

            <!-- Store Info -->
            <div class="grid md:grid-cols-2 gap-6 mb-12 slide-up" style="animation-delay: 0.2s;">
                <div class="glass-strong rounded-3xl p-8">
                    <div class="flex items-start justify-between mb-6">
                        <div class="w-12 h-12 bg-red-600/10 rounded-xl flex items-center justify-center">
                            <i data-lucide="store" class="w-6 h-6 text-red-600"></i>
                        </div>
                        <span class="text-[9px] font-black uppercase bg-red-600/10 text-red-500 px-3 py-1 rounded-full border border-red-600/20">
                            Plano <?= htmlspecialchars($store_full['plan']) ?>
                        </span>
                    </div>
                    
                    <h3 class="text-xs font-black uppercase text-zinc-400 tracking-wider mb-3">Sua Loja</h3>
                    <h2 class="text-3xl font-black text-white mb-4"><?= htmlspecialchars($store_full['store_name']) ?></h2>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center gap-3 text-sm">
                            <i data-lucide="mail" class="w-4 h-4 text-zinc-500"></i>
                            <span class="text-zinc-400"><?= htmlspecialchars($store_full['email']) ?></span>
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <i data-lucide="link" class="w-4 h-4 text-zinc-500"></i>
                            <span class="text-zinc-400 font-mono text-xs">
                                splitstore.com.br/<span class="text-red-500"><?= htmlspecialchars($store_full['store_slug']) ?></span>
                            </span>
                        </div>
                    </div>
                    
                    <a href="https://splitstore.com.br/<?= htmlspecialchars($store_full['store_slug']) ?>" 
                       target="_blank"
                       class="block w-full bg-zinc-900 hover:bg-zinc-800 text-white py-3 rounded-xl font-bold text-sm text-center transition-all">
                        Visitar Minha Loja →
                    </a>
                </div>

                <div class="glass-strong rounded-3xl p-8">
                    <div class="w-12 h-12 bg-green-600/10 rounded-xl flex items-center justify-center mb-6">
                        <i data-lucide="calendar" class="w-6 h-6 text-green-500"></i>
                    </div>
                    
                    <h3 class="text-xs font-black uppercase text-zinc-400 tracking-wider mb-3">Assinatura</h3>
                    <div class="space-y-4">
                        <div>
                            <p class="text-sm text-zinc-500 mb-1">Status</p>
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                <span class="text-lg font-black text-green-500">ATIVA</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm text-zinc-500 mb-1">Próxima cobrança</p>
                            <p class="text-xl font-black text-white"><?= date('d/m/Y', strtotime('+30 days')) ?></p>
                        </div>
                        <div class="bg-blue-600/10 border border-blue-600/20 p-4 rounded-xl">
                            <p class="text-xs text-blue-400 leading-relaxed">
                                <i data-lucide="info" class="w-3 h-3 inline mr-1"></i>
                                Você pode cancelar ou alterar seu plano a qualquer momento no painel
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Credentials -->
            <div class="glass-strong rounded-3xl p-10 mb-12 slide-up" style="animation-delay: 0.3s;">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h3 class="text-2xl font-black uppercase tracking-tight mb-2">Credenciais da API</h3>
                        <p class="text-zinc-500 text-sm">Necessárias para configurar o plugin Java</p>
                    </div>
                    <span class="text-[9px] font-black uppercase bg-yellow-600/10 text-yellow-500 px-4 py-2 rounded-full border border-yellow-600/20 flex items-center gap-2">
                        <i data-lucide="alert-triangle" class="w-3 h-3"></i>
                        Não Compartilhe
                    </span>
                </div>
                
                <div class="space-y-6">
                    <!-- API Key -->
                    <div class="credential-box glass rounded-2xl p-6 border border-white/5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-600/10 rounded-xl flex items-center justify-center">
                                    <i data-lucide="key" class="w-5 h-5 text-blue-500"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-black uppercase text-white">API Key</h4>
                                    <p class="text-xs text-zinc-500">Identificador público da sua loja</p>
                                </div>
                            </div>
                            <button onclick="copyToClipboard('apiKey')" 
                                    id="copyApiKeyBtn"
                                    class="flex items-center gap-2 bg-blue-600/10 hover:bg-blue-600/20 text-blue-500 px-4 py-2 rounded-xl text-xs font-black uppercase transition-all hover:scale-105">
                                <i data-lucide="copy" class="w-4 h-4"></i>
                                Copiar
                            </button>
                        </div>
                        <input type="text" 
                               id="apiKey" 
                               readonly 
                               value="<?= htmlspecialchars($store_full['api_key']) ?>"
                               class="w-full bg-black/50 border border-white/10 p-4 rounded-xl text-sm font-mono text-zinc-400 outline-none select-all">
                    </div>

                    <!-- API Secret -->
                    <div class="credential-box glass rounded-2xl p-6 border border-white/5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-red-600/10 rounded-xl flex items-center justify-center">
                                    <i data-lucide="shield" class="w-5 h-5 text-red-500"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-black uppercase text-white">API Secret</h4>
                                    <p class="text-xs text-zinc-500">Chave privada (mantenha segura)</p>
                                </div>
                            </div>
                            <button onclick="copyToClipboard('apiSecret')" 
                                    id="copyApiSecretBtn"
                                    class="flex items-center gap-2 bg-red-600/10 hover:bg-red-600/20 text-red-500 px-4 py-2 rounded-xl text-xs font-black uppercase transition-all hover:scale-105">
                                <i data-lucide="copy" class="w-4 h-4"></i>
                                Copiar
                            </button>
                        </div>
                        <input type="password" 
                               id="apiSecret" 
                               readonly 
                               value="<?= htmlspecialchars($store_full['api_secret']) ?>"
                               class="w-full bg-black/50 border border-white/10 p-4 rounded-xl text-sm font-mono text-zinc-400 outline-none select-all">
                        <button onclick="toggleSecretVisibility()" 
                                id="toggleSecretBtn"
                                class="mt-2 text-xs text-zinc-500 hover:text-white transition-colors flex items-center gap-2">
                            <i data-lucide="eye" class="w-3 h-3"></i>
                            Mostrar
                        </button>
                    </div>
                </div>

                <!-- Warning -->
                <div class="mt-8 p-6 bg-red-900/10 border-2 border-red-600/20 rounded-2xl">
                    <div class="flex items-start gap-4">
                        <i data-lucide="shield-alert" class="w-6 h-6 text-red-500 flex-shrink-0 mt-1"></i>
                        <div>
                            <h5 class="text-sm font-black uppercase text-red-500 mb-2">⚠️ Importante - Leia com Atenção</h5>
                            <ul class="text-xs text-zinc-400 leading-relaxed space-y-2">
                                <li>• Estas credenciais são necessárias para configurar o <strong>plugin Java</strong> no seu servidor</li>
                                <li>• <strong>Nunca compartilhe</strong> seu API Secret com terceiros ou em fóruns públicos</li>
                                <li>• Se as credenciais forem expostas, você pode regerar novas no painel de controle</li>
                                <li>• Guarde-as em um local seguro (gerenciador de senhas, documento criptografado)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="glass-strong rounded-3xl p-10 mb-12 slide-up" style="animation-delay: 0.4s;">
                <h3 class="text-2xl font-black uppercase tracking-tight mb-8 flex items-center gap-3">
                    <i data-lucide="check-square" class="w-7 h-7 text-green-500"></i>
                    Próximos Passos
                </h3>

                <div class="space-y-6">
                    <!-- Step 1 -->
                    <div class="flex gap-6 group">
                        <div class="w-12 h-12 bg-gradient-to-br from-red-600 to-red-900 rounded-xl flex items-center justify-center flex-shrink-0 font-black text-white text-xl shadow-lg group-hover:scale-110 transition-transform">
                            1
                        </div>
                        <div>
                            <h4 class="text-lg font-black text-white mb-2">Acesse o Painel de Controle</h4>
                            <p class="text-zinc-400 leading-relaxed mb-4">
                                Configure sua loja, adicione produtos, personalize as cores e gerencie tudo em um só lugar.
                            </p>
                            <a href="client/dashboard.php" 
                               class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-xl font-bold text-sm transition-all hover:scale-105">
                                Ir para o Painel
                                <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="flex gap-6 group">
                        <div class="w-12 h-12 bg-zinc-800 rounded-xl flex items-center justify-center flex-shrink-0 font-black text-white text-xl group-hover:scale-110 transition-transform">
                            2
                        </div>
                        <div>
                            <h4 class="text-lg font-black text-white mb-2">Instale o Plugin Java</h4>
                            <p class="text-zinc-400 leading-relaxed mb-4">
                                Baixe o SplitStore.jar, coloque na pasta plugins e configure com suas credenciais.
                            </p>
                            <a href="https://docs.splitstore.com.br/plugin" 
                               target="_blank"
                               class="inline-flex items-center gap-2 bg-zinc-900 hover:bg-zinc-800 text-white px-6 py-3 rounded-xl font-bold text-sm transition-all border border-white/5">
                                Baixar Plugin
                                <i data-lucide="download" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="flex gap-6 group">
                        <div class="w-12 h-12 bg-zinc-800 rounded-xl flex items-center justify-center flex-shrink-0 font-black text-white text-xl group-hover:scale-110 transition-transform">
                            3
                        </div>
                        <div>
                            <h4 class="text-lg font-black text-white mb-2">Configure o Gateway de Pagamento</h4>
                            <p class="text-zinc-400 leading-relaxed mb-4">
                                Adicione suas credenciais do Mercado Pago, MisticPay ou outro gateway para começar a receber.
                            </p>
                            <a href="client/settings.php?tab=gateway" 
                               class="inline-flex items-center gap-2 text-zinc-400 hover:text-white transition-colors text-sm font-bold">
                                Configurar Gateway
                                <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Support -->
            <div class="glass rounded-2xl p-8 border border-blue-600/20 bg-blue-600/5 slide-up" style="animation-delay: 0.5s;">
                <div class="flex items-start gap-4">
                    <i data-lucide="headset" class="w-8 h-8 text-blue-500 flex-shrink-0"></i>
                    <div class="flex-1">
                        <h5 class="text-lg font-black text-blue-500 mb-2">Precisa de Ajuda?</h5>
                        <p class="text-zinc-400 leading-relaxed mb-4">
                            Nossa equipe de suporte está disponível 24/7 para ajudar você a configurar e usar o SplitStore da melhor forma.
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <a href="https://docs.splitstore.com.br" 
                               target="_blank"
                               class="text-sm font-bold text-blue-500 hover:underline flex items-center gap-2">
                                <i data-lucide="book-open" class="w-4 h-4"></i>
                                Documentação
                            </a>
                            <a href="https://discord.gg/splitstore" 
                               target="_blank"
                               class="text-sm font-bold text-blue-500 hover:underline flex items-center gap-2">
                                <i data-lucide="message-circle" class="w-4 h-4"></i>
                                Discord
                            </a>
                            <a href="mailto:suporte@splitstore.com.br" 
                               class="text-sm font-bold text-blue-500 hover:underline flex items-center gap-2">
                                <i data-lucide="mail" class="w-4 h-4"></i>
                                E-mail
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        lucide.createIcons();
        
        // ========================================
        // CONFETTI DE CELEBRAÇÃO
        // ========================================
        confetti({
            particleCount: 150,
            spread: 80,
            origin: { y: 0.6 },
            colors: ['#ef4444', '#dc2626', '#ffffff']
        });
        
        setTimeout(() => {
            confetti({
                particleCount: 100,
                angle: 60,
                spread: 55,
                origin: { x: 0 },
                colors: ['#ef4444', '#dc2626']
            });
        }, 250);
        
        setTimeout(() => {
            confetti({
                particleCount: 100,
                angle: 120,
                spread: 55,
                origin: { x: 1 },
                colors: ['#ef4444', '#dc2626']
            });
        }, 400);
        
        // ========================================
        // COPIAR CREDENCIAIS
        // ========================================
        function copyToClipboard(elementId) {
            const input = document.getElementById(elementId);
            const button = document.getElementById('copy' + elementId.charAt(0).toUpperCase() + elementId.slice(1) + 'Btn');
            
            input.select();
            input.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Copiado!';
            button.classList.remove('bg-blue-600/10', 'bg-red-600/10');
            button.classList.add('bg-green-600', 'text-white');
            
            lucide.createIcons();
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('bg-green-600', 'text-white');
                if (elementId === 'apiKey') {
                    button.classList.add('bg-blue-600/10', 'text-blue-500');
                } else {
                    button.classList.add('bg-red-600/10', 'text-red-500');
                }
                lucide.createIcons();
            }, 2500);
        }
        
        // ========================================
        // TOGGLE SECRET VISIBILITY
        // ========================================
        function toggleSecretVisibility() {
            const input = document.getElementById('apiSecret');
            const button = document.getElementById('toggleSecretBtn');
            
            if (input.type === 'password') {
                input.type = 'text';
                button.innerHTML = '<i data-lucide="eye-off" class="w-3 h-3"></i> Ocultar';
            } else {
                input.type = 'password';
                button.innerHTML = '<i data-lucide="eye" class="w-3 h-3"></i> Mostrar';
            }
            
            lucide.createIcons();
        }
        
        // ========================================
        // PARTICLES.JS
        // ========================================
        particlesJS("particles-js", {
            particles: {
                number: { value: 40, density: { enable: true, value_area: 800 } },
                color: { value: "#22c55e" },
                opacity: { value: 0.15, random: true },
                size: { value: 3, random: true },
                line_linked: { enable: true, distance: 150, color: "#22c55e", opacity: 0.08, width: 1 },
                move: { enable: true, speed: 0.8 }
            }
        });
    </script>
</body>
</html>