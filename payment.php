<?php
/**
 * ============================================
 * SPLITSTORE - PÁGINA DE PAGAMENTO V3.0
 * ============================================
 * Inspirado em Minecart.net e LojaSquare.net
 * UX Premium com animações e feedback em tempo real
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

// Calcula tempo restante para expiração do PIX
$expires_at = $payment['expires_at'] ?? null;
$time_remaining = 0;

if ($expires_at) {
    $expires_timestamp = strtotime($expires_at);
    $time_remaining = max(0, $expires_timestamp - time());
}

// Mapeamento de planos para exibição
$plan_names = [
    'basic' => 'Starter',
    'pro' => 'Enterprise',
    'ultra' => 'Gerencial'
];

$plan_display = $plan_names[$store['plan']] ?? $store['plan'];
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
            overflow-x: hidden;
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
        
        /* Animações */
        @keyframes pulse-glow {
            0%, 100% { 
                opacity: 1;
                box-shadow: 0 0 20px rgba(34, 197, 94, 0.3);
            }
            50% { 
                opacity: 0.8;
                box-shadow: 0 0 40px rgba(34, 197, 94, 0.6);
            }
        }
        
        .pulse-glow {
            animation: pulse-glow 2s ease-in-out infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .spin-slow {
            animation: spin 3s linear infinite;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }
        
        /* QR Code Hover */
        .qr-container {
            transition: all 0.3s ease;
        }
        
        .qr-container:hover {
            transform: scale(1.02);
            box-shadow: 0 20px 60px rgba(34, 197, 94, 0.2);
        }
        
        /* Copy Button Feedback */
        .copy-btn {
            transition: all 0.2s ease;
        }
        
        .copy-btn:active {
            transform: scale(0.95);
        }
        
        /* Status Steps */
        .step {
            position: relative;
            transition: all 0.3s ease;
        }
        
        .step::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 40px;
            bottom: -40px;
            width: 2px;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .step:last-child::before {
            display: none;
        }
        
        .step.active .step-circle {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            box-shadow: 0 0 20px rgba(34, 197, 94, 0.5);
        }
        
        .step.completed .step-circle {
            background: #22c55e;
        }
        
        .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        /* Countdown Warning */
        .countdown-warning {
            animation: pulse 1s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .qr-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

    <div id="particles-js"></div>

    <div class="content-wrapper min-h-screen py-8 md:py-12 px-4">
        
        <!-- Header -->
        <div class="max-w-6xl mx-auto mb-8 md:mb-12 fade-in">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-red-600 to-red-900 rounded-xl flex items-center justify-center font-black shadow-lg">
                        S
                    </div>
                    <div class="hidden md:block">
                        <span class="text-xl font-black tracking-tighter uppercase">
                            Split<span class="text-red-600">Store</span>
                        </span>
                        <p class="text-xs text-zinc-600 font-bold uppercase tracking-wider">Checkout Seguro</p>
                    </div>
                </div>
                
                <!-- Status Badge -->
                <div class="glass px-4 py-2 rounded-full flex items-center gap-2">
                    <div class="w-2 h-2 bg-yellow-500 rounded-full pulse-glow"></div>
                    <span class="text-xs font-bold text-zinc-400 hidden sm:inline">Aguardando Pagamento</span>
                    <span class="text-xs font-bold text-zinc-400 sm:hidden">Pendente</span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-6xl mx-auto">
            <div class="grid lg:grid-cols-5 gap-8">
                
                <!-- QR Code Section (3 colunas) -->
                <div class="lg:col-span-3 space-y-6">
                    
                    <!-- PIX QR Code Card -->
                    <div class="glass-strong rounded-3xl p-8 md:p-10 fade-in">
                        
                        <!-- Header -->
                        <div class="text-center mb-8">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-600/10 border-2 border-green-600/30 rounded-2xl mb-4">
                                <i data-lucide="qr-code" class="w-8 h-8 text-green-500"></i>
                            </div>
                            <h1 class="text-3xl md:text-4xl font-black uppercase tracking-tight mb-2">
                                Pague com <span class="text-green-500">PIX</span>
                            </h1>
                            <p class="text-zinc-400 text-sm md:text-base">Aprovação automática em segundos</p>
                        </div>

                        <!-- QR Code -->
                        <?php if (!empty($payment['qr_code'])): ?>
                        <div class="qr-container bg-white p-6 md:p-8 rounded-2xl mb-6 mx-auto max-w-sm">
                            <img src="<?= htmlspecialchars($payment['qr_code']) ?>" 
                                 alt="QR Code PIX" 
                                 class="w-full h-auto">
                        </div>
                        <?php else: ?>
                        <div class="bg-zinc-900 p-16 rounded-2xl mb-6 text-center">
                            <i data-lucide="alert-circle" class="w-12 h-12 text-red-500 mx-auto mb-4"></i>
                            <p class="text-zinc-500">Erro ao gerar QR Code</p>
                        </div>
                        <?php endif; ?>

                        <!-- Código Copia e Cola -->
                        <?php if (!empty($payment['qr_code_text'])): ?>
                        <div class="glass rounded-2xl p-5 mb-6">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-black uppercase text-zinc-400 tracking-wider flex items-center gap-2">
                                    <i data-lucide="copy" class="w-4 h-4"></i>
                                    Código PIX Copia e Cola
                                </h3>
                                <button onclick="copyPixCode()" 
                                        id="copyButton"
                                        class="copy-btn flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-xl text-xs font-black uppercase transition-all">
                                    <i data-lucide="copy" class="w-4 h-4"></i>
                                    <span id="copyText">Copiar</span>
                                </button>
                            </div>
                            <input type="text" 
                                   id="pixCode" 
                                   readonly 
                                   value="<?= htmlspecialchars($payment['qr_code_text']) ?>"
                                   class="w-full bg-black/50 border border-white/10 p-3 rounded-xl text-xs font-mono text-zinc-400 outline-none select-all cursor-pointer hover:border-green-600/30 transition-colors">
                        </div>
                        <?php endif; ?>

                        <!-- Timer -->
                        <?php if ($time_remaining > 0): ?>
                        <div class="glass rounded-2xl p-5 border <?= $time_remaining < 300 ? 'border-red-600/30 bg-red-600/5' : 'border-yellow-600/20 bg-yellow-600/5' ?>">
                            <div class="flex items-center justify-center gap-3">
                                <i data-lucide="clock" class="w-5 h-5 text-yellow-500"></i>
                                <div class="text-center">
                                    <p class="text-xs text-zinc-400 mb-1">Tempo restante para pagamento</p>
                                    <p class="text-2xl md:text-3xl font-black <?= $time_remaining < 300 ? 'text-red-500 countdown-warning' : 'text-yellow-500' ?>" 
                                       id="countdown">
                                        <?= gmdate("i:s", $time_remaining) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Como Pagar -->
                    <div class="glass-strong rounded-2xl p-8 fade-in" style="animation-delay: 0.1s;">
                        <h3 class="text-lg font-black uppercase tracking-tight mb-6 flex items-center gap-2">
                            <i data-lucide="smartphone" class="w-5 h-5 text-blue-500"></i>
                            Como Pagar com PIX
                        </h3>

                        <div class="space-y-5">
                            <div class="flex gap-4">
                                <div class="w-10 h-10 bg-gradient-to-br from-green-600 to-green-800 rounded-xl flex items-center justify-center flex-shrink-0 font-black text-white shadow-lg">
                                    1
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white mb-1">Abra o app do seu banco</h4>
                                    <p class="text-xs text-zinc-400 leading-relaxed">
                                        Acesse Nubank, Inter, C6, Mercado Pago ou qualquer app de pagamentos
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-4">
                                <div class="w-10 h-10 bg-zinc-800 rounded-xl flex items-center justify-center flex-shrink-0 font-black text-white">
                                    2
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white mb-1">Escolha "Pagar com PIX"</h4>
                                    <p class="text-xs text-zinc-400 leading-relaxed">
                                        Selecione a opção "Pagar com PIX" ou "Ler QR Code"
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-4">
                                <div class="w-10 h-10 bg-zinc-800 rounded-xl flex items-center justify-center flex-shrink-0 font-black text-white">
                                    3
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white mb-1">Escaneie ou cole o código</h4>
                                    <p class="text-xs text-zinc-400 leading-relaxed">
                                        Aponte a câmera para o QR Code ou use o botão "Copiar" e cole no app
                                    </p>
                                </div>
                            </div>

                            <div class="flex gap-4">
                                <div class="w-10 h-10 bg-zinc-800 rounded-xl flex items-center justify-center flex-shrink-0 font-black text-white">
                                    4
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-white mb-1">Confirme e pronto!</h4>
                                    <p class="text-xs text-zinc-400 leading-relaxed">
                                        Após confirmar, você será redirecionado automaticamente em segundos
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status & Info Section (2 colunas) -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Status do Pedido -->
                    <div class="glass-strong rounded-2xl p-8 fade-in" style="animation-delay: 0.2s;">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-black uppercase tracking-tight">Status</h2>
                            <div class="spin-slow">
                                <i data-lucide="loader" class="w-6 h-6 text-yellow-500"></i>
                            </div>
                        </div>

                        <div class="space-y-6 mb-8">
                            <div class="step completed">
                                <div class="flex items-start gap-4">
                                    <div class="step-circle">
                                        <i data-lucide="check" class="w-4 h-4 text-white"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-white">Pedido Criado</h4>
                                        <p class="text-xs text-zinc-500 mt-1">Loja registrada com sucesso</p>
                                    </div>
                                </div>
                            </div>

                            <div class="step active">
                                <div class="flex items-start gap-4">
                                    <div class="step-circle pulse-glow">
                                        <i data-lucide="clock" class="w-4 h-4 text-white"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-white">Aguardando Pagamento</h4>
                                        <p class="text-xs text-zinc-500 mt-1">Realize o pagamento via PIX</p>
                                    </div>
                                </div>
                            </div>

                            <div class="step">
                                <div class="flex items-start gap-4">
                                    <div class="step-circle">
                                        <i data-lucide="check-circle" class="w-4 h-4 text-zinc-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-zinc-600">Confirmação</h4>
                                        <p class="text-xs text-zinc-700 mt-1">Validando transação</p>
                                    </div>
                                </div>
                            </div>

                            <div class="step">
                                <div class="flex items-start gap-4">
                                    <div class="step-circle">
                                        <i data-lucide="sparkles" class="w-4 h-4 text-zinc-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-zinc-600">Loja Ativada</h4>
                                        <p class="text-xs text-zinc-700 mt-1">Tudo pronto para usar</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumo do Pedido -->
                        <div class="glass rounded-xl p-5 border border-white/5">
                            <h4 class="text-xs font-black uppercase text-zinc-500 mb-4 tracking-wider">Resumo do Pedido</h4>
                            
                            <div class="space-y-3 mb-5 pb-5 border-b border-white/5">
                                <div class="flex items-center gap-3">
                                    <i data-lucide="store" class="w-4 h-4 text-red-500"></i>
                                    <div class="flex-1">
                                        <p class="text-xs text-zinc-500">Loja</p>
                                        <p class="text-sm font-black text-white"><?= htmlspecialchars($store['name']) ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    <i data-lucide="package" class="w-4 h-4 text-purple-500"></i>
                                    <div class="flex-1">
                                        <p class="text-xs text-zinc-500">Plano</p>
                                        <p class="text-sm font-black text-white"><?= htmlspecialchars($plan_display) ?></p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <i data-lucide="mail" class="w-4 h-4 text-blue-500"></i>
                                    <div class="flex-1">
                                        <p class="text-xs text-zinc-500">E-mail</p>
                                        <p class="text-xs font-mono text-white truncate"><?= htmlspecialchars($store['email']) ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between items-end">
                                <span class="text-xs text-zinc-500 font-bold uppercase">Total</span>
                                <span class="text-2xl font-black text-green-500">
                                    R$ <?= number_format($payment['amount'], 2, ',', '.') ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Suporte -->
                    <div class="glass rounded-2xl p-6 border border-blue-600/20 bg-blue-600/5 fade-in" style="animation-delay: 0.3s;">
                        <div class="flex items-start gap-3">
                            <i data-lucide="headset" class="w-6 h-6 text-blue-500 flex-shrink-0"></i>
                            <div>
                                <h5 class="text-sm font-black text-blue-500 mb-2">Precisa de Ajuda?</h5>
                                <p class="text-xs text-zinc-400 leading-relaxed mb-3">
                                    Nosso suporte está online 24/7
                                </p>
                                <a href="https://wa.me/5535999999999" 
                                   target="_blank"
                                   class="text-xs font-bold text-blue-500 hover:underline inline-flex items-center gap-1">
                                    Falar com Suporte
                                    <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Segurança -->
                    <div class="glass rounded-2xl p-6 fade-in" style="animation-delay: 0.4s;">
                        <div class="space-y-3">
                            <div class="flex items-center gap-3 text-xs">
                                <i data-lucide="shield-check" class="w-4 h-4 text-green-500"></i>
                                <span class="text-zinc-400 font-bold">Pagamento 100% Seguro</span>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                <i data-lucide="lock" class="w-4 h-4 text-blue-500"></i>
                                <span class="text-zinc-400 font-bold">Conexão SSL Criptografada</span>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                <i data-lucide="zap" class="w-4 h-4 text-yellow-500"></i>
                                <span class="text-zinc-400 font-bold">Ativação Automática</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="max-w-6xl mx-auto mt-12 text-center">
            <p class="text-xs text-zinc-600 font-bold uppercase tracking-wider">
                © <?= date('Y') ?> SplitStore - Todos os direitos reservados
            </p>
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
            const textSpan = document.getElementById('copyText');
            
            input.select();
            input.setSelectionRange(0, 99999);
            
            try {
                document.execCommand('copy');
                
                // Feedback visual
                const originalText = textSpan.textContent;
                textSpan.textContent = 'Copiado!';
                button.classList.remove('bg-green-600', 'hover:bg-green-700');
                button.classList.add('bg-green-500');
                
                // Toast notification
                showToast('✓ Código PIX copiado!', 'success');
                
                setTimeout(() => {
                    textSpan.textContent = originalText;
                    button.classList.remove('bg-green-500');
                    button.classList.add('bg-green-600', 'hover:bg-green-700');
                }, 2500);
            } catch (err) {
                showToast('Erro ao copiar. Tente manualmente.', 'error');
            }
        }
        
        // ========================================
        // TOAST NOTIFICATIONS
        // ========================================
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed bottom-6 right-6 px-6 py-4 rounded-2xl font-bold text-sm shadow-2xl z-[9999] fade-in ${
                type === 'success' ? 'bg-green-600 text-white' :
                type === 'error' ? 'bg-red-600 text-white' :
                'bg-zinc-800 text-white'
            }`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(20px)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // ========================================
        // COUNTDOWN TIMER
        // ========================================
        <?php if ($time_remaining > 0): ?>
        let timeRemaining = <?= $time_remaining ?>;
        
        function updateCountdown() {
            if (timeRemaining <= 0) {
                document.getElementById('countdown').textContent = 'Expirado';
                document.getElementById('countdown').classList.add('text-red-500', 'countdown-warning');
                clearInterval(countdownInterval);
                
                showToast('⏱ Tempo expirado. Gerando novo QR Code...', 'error');
                
                setTimeout(() => {
                    window.location.href = 'checkout.php?plan=<?= urlencode($_GET['plan'] ?? 'basic') ?>';
                }, 3000);
                return;
            }
            
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            document.getElementById('countdown').textContent = 
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            
            // Alerta quando faltar 5 minutos
            if (timeRemaining === 300) {
                showToast('⚠️ Restam apenas 5 minutos!', 'error');
            }
            
            timeRemaining--;
        }
        
        const countdownInterval = setInterval(updateCountdown, 1000);
        updateCountdown(); // Executa imediatamente
        <?php endif; ?>
        
        // ========================================
        // POLLING - VERIFICAR STATUS DO PAGAMENTO
        // ========================================
        let checkCount = 0;
        const maxChecks = 600; // 30 minutos
        let isRedirecting = false;
        
        function checkPaymentStatus() {
            if (isRedirecting || checkCount >= maxChecks) {
                if (checkCount >= maxChecks) {
                    console.log('Polling timeout (30 minutos)');
                }
                clearInterval(pollingInterval);
                return;
            }
            
            fetch('api/check_payment_status.php?transaction_id=<?= $payment['transaction_id'] ?? '' ?>')
                .then(res => res.json())
                .then(data => {
                    console.log(`[${new Date().toLocaleTimeString()}] Status:`, data.status);
                    
                    if (data.status === 'completed' || data.status === 'paid' || data.status === 'approved') {
                        isRedirecting = true;
                        clearInterval(pollingInterval);
                        
                        // Atualiza UI
                        showToast('✓ Pagamento confirmado!', 'success');
                        
                        // Atualiza badge do header
                        const badge = document.querySelector('.pulse-glow').parentElement;
                        badge.innerHTML = `
                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                            <span class="text-xs font-bold text-green-500">Pagamento Confirmado!</span>
                        `;
                        
                        // Redireciona
                        setTimeout(() => {
                            window.location.href = 'welcome.php';
                        }, 1500);
                    }
                    
                    checkCount++;
                })
                .catch(error => {
                    console.error('Erro ao verificar pagamento:', error);
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
                number: { value: 40, density: { enable: true, value_area: 800 } },
                color: { value: "#22c55e" },
                opacity: { value: 0.1, random: true },
                size: { value: 2, random: true },
                line_linked: { 
                    enable: true, 
                    distance: 150, 
                    color: "#22c55e", 
                    opacity: 0.06, 
                    width: 1 
                },
                move: { 
                    enable: true, 
                    speed: 0.6,
                    direction: "none",
                    random: true,
                    out_mode: "out"
                }
            },
            interactivity: {
                detect_on: "canvas",
                events: {
                    onhover: { enable: true, mode: "grab" },
                    resize: true
                },
                modes: {
                    grab: { distance: 140, line_linked: { opacity: 0.2 } }
                }
            }
        });
        
        // ========================================
        // PREVENIR SAÍDA ACIDENTAL
        // ========================================
        window.addEventListener('beforeunload', function (e) {
            if (!isRedirecting) {
                e.preventDefault();
                e.returnValue = '';
                return 'Tem certeza que deseja sair? Seu pagamento pode estar sendo processado.';
            }
        });
    </script>
</body>
</html>