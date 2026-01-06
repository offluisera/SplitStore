<?php
/**
 * ============================================
 * SPLITSTORE - PÁGINA DE PAGAMENTO PIX V2
 * ============================================
 * Exibe QR Code e monitora status do pagamento
 */

session_start();
require_once 'includes/db.php';

// Verifica se há dados de pagamento
if (!isset($_SESSION['payment_data']) || !isset($_SESSION['store_data'])) {
    header('Location: index.php');
    exit;
}

$payment = $_SESSION['payment_data'];
$store = $_SESSION['store_data'];

// Calcula tempo restante
$expires_at = $payment['expires_at'] ?? null;
$time_remaining = null;
if ($expires_at) {
    $expires_timestamp = strtotime($expires_at);
    $time_remaining = max(0, $expires_timestamp - time());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX - SplitStore</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

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
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .pulse-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .spin-animation {
            animation: spin 2s linear infinite;
        }
        
        .qr-code-container {
            position: relative;
            transition: all 0.3s ease;
        }
        
        .qr-code-container:hover {
            transform: scale(1.02);
        }
        
        .step-indicator {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .step-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .step-dot.active {
            background: #22c55e;
            box-shadow: 0 0 20px rgba(34, 197, 94, 0.5);
        }
    </style>
</head>
<body>

    <div id="particles-js"></div>

    <div class="content-wrapper min-h-screen py-12 px-4">
        
        <!-- Header -->
        <div class="max-w-5xl mx-auto mb-12">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-red-600 to-red-900 rounded-xl flex items-center justify-center font-black shadow-lg">
                        S
                    </div>
                    <span class="text-xl font-black tracking-tighter uppercase">
                        Split<span class="text-red-600">Store</span>
                    </span>
                </div>
                
                <div class="glass px-4 py-2 rounded-full flex items-center gap-2">
                    <div class="w-2 h-2 bg-yellow-500 rounded-full pulse-animation"></div>
                    <span class="text-xs font-bold text-zinc-400">Aguardando Pagamento</span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-5xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-8">
                
                <!-- QR Code Section -->
                <div class="glass-strong rounded-3xl p-10">
                    
                    <!-- Header -->
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-green-600/10 border-2 border-green-600/30 rounded-2xl mb-4">
                            <i data-lucide="qr-code" class="w-8 h-8 text-green-500"></i>
                        </div>
                        <h1 class="text-3xl font-black uppercase tracking-tight mb-2">
                            Pague com <span class="text-green-500">PIX</span>
                        </h1>
                        <p class="text-zinc-400 text-sm">Aprovação instantânea em segundos</p>
                    </div>

                    <!-- QR Code -->
                    <?php if (!empty($payment['qr_code'])): ?>
                    <div class="qr-code-container bg-white p-8 rounded-2xl mb-6">
                        <img src="<?= htmlspecialchars($payment['qr_code']) ?>" 
                             alt="QR Code PIX" 
                             class="w-full h-auto max-w-sm mx-auto">
                    </div>
                    <?php else: ?>
                    <div class="bg-zinc-900 p-16 rounded-2xl mb-6 text-center">
                        <i data-lucide="alert-circle" class="w-12 h-12 text-red-500 mx-auto mb-4"></i>
                        <p class="text-zinc-500">Erro ao gerar QR Code</p>
                    </div>
                    <?php endif; ?>

                    <!-- Código Copia e Cola -->
                    <?php if (!empty($payment['qr_code_text'])): ?>
                    <div class="glass rounded-2xl p-4 mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-black uppercase text-zinc-400 tracking-wider">
                                Código PIX
                            </h3>
                            <button onclick="copyPixCode()" 
                                    id="copyButton"
                                    class="flex items-center gap-2 bg-green-600/10 hover:bg-green-600/20 text-green-500 px-4 py-2 rounded-xl text-xs font-black uppercase transition-all hover:scale-105">
                                <i data-lucide="copy" class="w-4 h-4"></i>
                                Copiar
                            </button>
                        </div>
                        <input type="text" 
                               id="pixCode" 
                               readonly 
                               value="<?= htmlspecialchars($payment['qr_code_text']) ?>"
                               class="w-full bg-black/50 border border-white/10 p-3 rounded-xl text-xs font-mono text-zinc-400 outline-none select-all">
                    </div>
                    <?php endif; ?>

                    <!-- Timer -->
                    <?php if ($time_remaining): ?>
                    <div class="glass rounded-2xl p-4 border border-yellow-600/20 bg-yellow-600/5">
                        <div class="flex items-center justify-center gap-3">
                            <i data-lucide="clock" class="w-5 h-5 text-yellow-500"></i>
                            <div class="text-center">
                                <p class="text-xs text-zinc-400 mb-1">Tempo restante para pagar</p>
                                <p class="text-2xl font-black text-yellow-500" id="countdown">
                                    <?= gmdate("i:s", $time_remaining) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Instructions Section -->
                <div class="space-y-6">
                    
                    <!-- Status Card -->
                    <div class="glass-strong rounded-2xl p-8">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-black uppercase tracking-tight">Status do Pedido</h2>
                            <div class="spin-animation">
                                <i data-lucide="loader" class="w-6 h-6 text-yellow-500"></i>
                            </div>
                        </div>

                        <div class="space-y-4 mb-6">
                            <div class="step-indicator">
                                <div class="step-dot active"></div>
                                <span class="text-sm text-zinc-400">Pedido criado</span>
                            </div>
                            <div class="step-indicator">
                                <div class="step-dot active"></div>
                                <span class="text-sm text-zinc-400">Aguardando pagamento</span>
                            </div>
                            <div class="step-indicator">
                                <div class="step-dot"></div>
                                <span class="text-sm text-zinc-500">Confirmação do pagamento</span>
                            </div>
                            <div class="step-indicator">
                                <div class="step-dot"></div>
                                <span class="text-sm text-zinc-500">Loja ativada</span>
                            </div>
                        </div>

                        <div class="glass rounded-xl p-4 border border-white/5">
                            <div class="flex items-center gap-3 mb-3">
                                <i data-lucide="store" class="w-5 h-5 text-primary"></i>
                                <div>
                                    <p class="text-xs text-zinc-500 font-bold uppercase">Sua Loja</p>
                                    <p class="text-sm font-black text-white"><?= htmlspecialchars($store['name']) ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <i data-lucide="package" class="w-5 h-5 text-purple-500"></i>
                                <div>
                                    <p class="text-xs text-zinc-500 font-bold uppercase">Plano</p>
                                    <p class="text-sm font-black text-white"><?= htmlspecialchars($store['plan']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div class="glass-strong rounded-2xl p-8">
                        <h3 class="text-lg font-black uppercase tracking-tight mb-6 flex items-center gap-2">
                            <i data-lucide="info" class="w-5 h-5 text-blue-500"></i>
                            Como Pagar
                        </h3>

                        <div class="space-y-5">
                            <div class="flex gap-4">
                                <div class="w-8 h-8 bg-gradient-to-br from-green-600 to-green-800 rounded-xl flex items-center justify-center flex-shrink-0 font-black text-white shadow-lg">
                                    1
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white mb-1">Abra seu App de Pagamentos</h4>
                                    <p class="text-xs text-zinc-400 leading-relaxed">
                                        Acesse o aplicativo do seu banco ou carteira digital (Nubank, PicPay, Mercado Pago, etc)
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-4">
                                <div class="w-8 h-8 bg-zinc-800 rounded-xl flex items-center justify-center flex-shrink-0 font-black text-white">
                                    2
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white mb-1">Escolha Pagar com PIX</h4>
                                    <p class="text-xs text-zinc-400 leading-relaxed">
                                        Selecione a opção "Pagar com PIX" ou "Ler QR Code"
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-4">
                                <div class="w-8 h-8 bg-zinc-800 rounded-xl flex items-center justify-center flex-shrink-0 font-black text-white">
                                    3
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white mb-1">Escaneie ou Cole o Código</h4>
                                    <p class="text-xs text-zinc-400 leading-relaxed">
                                        Aponte a câmera para o QR Code acima ou copie e cole o código PIX
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-4">
                                <div class="w-8 h-8 bg-zinc-800 rounded-xl flex items-center justify-center flex-shrink-0 font-black text-white">
                                    4
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white mb-1">Confirme e Pronto!</h4>
                                    <p class="text-xs text-zinc-400 leading-relaxed">
                                        Após a confirmação, sua loja será ativada automaticamente em alguns segundos
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Support -->
                    <div class="glass rounded-2xl p-6 border border-blue-600/20 bg-blue-600/5">
                        <div class="flex items-start gap-3">
                            <i data-lucide="headset" class="w-6 h-6 text-blue-500 flex-shrink-0"></i>
                            <div>
                                <h5 class="text-sm font-black text-blue-500 mb-1">Precisa de Ajuda?</h5>
                                <p class="text-xs text-zinc-400 leading-relaxed mb-3">
                                    Nossa equipe está disponível 24/7 para ajudar você
                                </p>
                                <a href="#" class="text-xs font-bold text-blue-500 hover:underline">
                                    Falar com Suporte →
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Security -->
                    <div class="flex items-center justify-center gap-6 text-xs text-zinc-600 font-bold uppercase tracking-wider">
                        <div class="flex items-center gap-2">
                            <i data-lucide="shield-check" class="w-4 h-4 text-green-500"></i>
                            Pagamento Seguro
                        </div>
                        <div class="flex items-center gap-2">
                            <i data-lucide="lock" class="w-4 h-4 text-blue-500"></i>
                            SSL Criptografado
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
        // COPIAR CÓDIGO PIX
        // ========================================
        function copyPixCode() {
            const input = document.getElementById('pixCode');
            const button = document.getElementById('copyButton');
            
            input.select();
            input.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Copiado!';
            button.classList.remove('text-green-500', 'bg-green-600/10');
            button.classList.add('text-white', 'bg-green-600');
            
            lucide.createIcons();
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('text-white', 'bg-green-600');
                button.classList.add('text-green-500', 'bg-green-600/10');
                lucide.createIcons();
            }, 2500);
        }
        
        // ========================================
        // COUNTDOWN TIMER
        // ========================================
        <?php if ($time_remaining): ?>
        let timeRemaining = <?= $time_remaining ?>;
        
        function updateCountdown() {
            if (timeRemaining <= 0) {
                document.getElementById('countdown').textContent = 'Expirado';
                document.getElementById('countdown').classList.add('text-red-500');
                clearInterval(countdownInterval);
                
                // Mostra mensagem de expiração
                alert('O tempo para pagamento expirou. Por favor, gere um novo pedido.');
                window.location.href = 'checkout_v2.php?plan=<?= urlencode($_GET['plan'] ?? 'basic') ?>';
                return;
            }
            
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            document.getElementById('countdown').textContent = 
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            
            timeRemaining--;
        }
        
        const countdownInterval = setInterval(updateCountdown, 1000);
        <?php endif; ?>
        
        // ========================================
        // POLLING - VERIFICAR STATUS DO PAGAMENTO
        // ========================================
        let checkCount = 0;
        const maxChecks = 600; // 30 minutos (600 * 3 segundos)
        
        function checkPaymentStatus() {
            if (checkCount >= maxChecks) {
                clearInterval(pollingInterval);
                console.log('Polling timeout');
                return;
            }
            
            fetch('api/check_payment_status.php?transaction_id=<?= $store['transaction_id'] ?? '' ?>')
                .then(res => res.json())
                .then(data => {
                    console.log('Payment status:', data.status);
                    
                    if (data.status === 'completed' || data.status === 'paid') {
                        clearInterval(pollingInterval);
                        
                        // Atualiza UI
                        document.querySelector('.pulse-animation').parentElement.innerHTML = `
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                <span class="text-xs font-bold text-green-500">Pagamento Confirmado!</span>
                            </div>
                        `;
                        
                        // Redireciona para página de sucesso
                        setTimeout(() => {
                            window.location.href = 'welcome.php';
                        }, 1500);
                    }
                    
                    checkCount++;
                })
                .catch(error => {
                    console.error('Error checking payment:', error);
                    checkCount++;
                });
        }
        
        // Verifica a cada 3 segundos
        const pollingInterval = setInterval(checkPaymentStatus, 3000);
        checkPaymentStatus(); // Primeira verificação imediata
        
        // ========================================
        // PARTICLES.JS
        // ========================================
        particlesJS("particles-js", {
            particles: {
                number: { value: 30, density: { enable: true, value_area: 800 } },
                color: { value: "#22c55e" },
                opacity: { value: 0.12, random: true },
                size: { value: 2, random: true },
                line_linked: { enable: true, distance: 150, color: "#22c55e", opacity: 0.06, width: 1 },
                move: { enable: true, speed: 0.5 }
            }
        });
    </script>
</body>
</html>