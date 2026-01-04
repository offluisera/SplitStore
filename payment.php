<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['payment_data']) || !isset($_SESSION['store_data'])) {
    header('Location: index.php');
    exit;
}

$payment = $_SESSION['payment_data'];
$store = $_SESSION['store_data'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento PIX | SplitStore</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #000; 
            color: white;
        }
        #particles-js {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: 1;
            top: 0;
            left: 0;
        }
        .content-wrapper {
            position: relative;
            z-index: 10;
        }
        .glass { 
            background: rgba(10, 10, 10, 0.7); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255, 255, 255, 0.05); 
        }
        .pulse-green {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }
    </style>
</head>
<body class="antialiased min-h-screen flex items-center justify-center p-6">

    <div id="particles-js"></div>

    <div class="content-wrapper w-full max-w-2xl">
        <div class="glass rounded-[3rem] p-12">
            
            <!-- Header -->
            <div class="text-center mb-10">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-red-600/10 border-2 border-red-600/30 rounded-2xl mb-6">
                    <i data-lucide="qr-code" class="w-8 h-8 text-red-600"></i>
                </div>
                <h1 class="text-3xl font-black uppercase italic tracking-tighter mb-2">
                    Pagamento via <span class="text-red-600">PIX</span>
                </h1>
                <p class="text-zinc-500 text-sm font-medium">Escaneie o QR Code para finalizar</p>
            </div>

            <!-- QR Code -->
            <div class="bg-white p-8 rounded-3xl mb-8 flex items-center justify-center">
                <?php if(isset($payment['qr_code'])): ?>
                    <img src="data:image/png;base64,<?= $payment['qr_code'] ?>" 
                         alt="QR Code PIX" 
                         class="w-72 h-72 object-contain">
                <?php else: ?>
                    <div class="w-72 h-72 flex items-center justify-center bg-zinc-100 rounded-2xl">
                        <p class="text-zinc-500 font-bold text-sm">QR Code não disponível</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Código Copia e Cola -->
            <?php if(isset($payment['qr_code_text'])): ?>
            <div class="glass p-6 rounded-2xl mb-8">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-[10px] font-black uppercase text-zinc-600 tracking-widest">Código PIX</h3>
                    <button onclick="copyPixCode()" 
                            class="flex items-center gap-2 bg-red-600/10 hover:bg-red-600/20 text-red-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase transition">
                        <i data-lucide="copy" class="w-3 h-3"></i> Copiar
                    </button>
                </div>
                <input type="text" 
                       id="pixCode" 
                       readonly 
                       value="<?= htmlspecialchars($payment['qr_code_text']) ?>"
                       class="w-full bg-black/50 border border-white/10 p-3 rounded-xl text-xs font-mono text-zinc-400 outline-none">
            </div>
            <?php endif; ?>

            <!-- Instruções -->
            <div class="space-y-4 mb-8">
                <h3 class="text-sm font-black uppercase text-zinc-400">Como Pagar:</h3>
                <div class="space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 bg-red-600/10 border border-red-600/20 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="text-red-600 text-xs font-black">1</span>
                        </div>
                        <p class="text-sm text-zinc-400 leading-relaxed">Abra o app do seu banco e escolha a opção <strong class="text-white">Pagar com PIX</strong></p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 bg-red-600/10 border border-red-600/20 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="text-red-600 text-xs font-black">2</span>
                        </div>
                        <p class="text-sm text-zinc-400 leading-relaxed">Escaneie o <strong class="text-white">QR Code</strong> ou copie e cole o código acima</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 bg-red-600/10 border border-red-600/20 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                            <span class="text-red-600 text-xs font-black">3</span>
                        </div>
                        <p class="text-sm text-zinc-400 leading-relaxed">Confirme o pagamento e aguarde a <strong class="text-white">ativação automática</strong></p>
                    </div>
                </div>
            </div>

            <!-- Status de Aguardando -->
            <div class="bg-zinc-900/50 border border-white/5 rounded-2xl p-6 text-center">
                <div class="flex items-center justify-center gap-3 mb-3">
                    <div class="w-3 h-3 bg-yellow-500 rounded-full pulse-green"></div>
                    <span class="text-sm font-black uppercase text-yellow-500">Aguardando Pagamento</span>
                </div>
                <p class="text-xs text-zinc-500 mb-4">Estamos monitorando seu pagamento em tempo real</p>
                
                <!-- Informações da Loja -->
                <div class="pt-4 border-t border-white/5">
                    <p class="text-[10px] text-zinc-600 font-bold uppercase tracking-widest mb-2">Sua Loja:</p>
                    <p class="text-sm font-black italic text-white"><?= htmlspecialchars($store['name']) ?></p>
                    <p class="text-xs text-zinc-500 mt-1">Plano: <?= htmlspecialchars($store['plan']) ?></p>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 pt-6 border-t border-white/5 flex items-center justify-between">
                <a href="index.php" class="text-zinc-600 hover:text-white text-xs font-bold uppercase transition">
                    ← Voltar ao Início
                </a>
                <div class="flex items-center gap-2 text-zinc-600 text-[10px] font-bold uppercase">
                    <i data-lucide="shield-check" class="w-3 h-3"></i>
                    Pagamento Seguro
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        lucide.createIcons();
        
        function copyPixCode() {
            const input = document.getElementById('pixCode');
            input.select();
            document.execCommand('copy');
            
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="check" class="w-3 h-3"></i> Copiado!';
            lucide.createIcons();
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                lucide.createIcons();
            }, 2000);
        }

        // Polling para verificar pagamento a cada 3 segundos
        let checkInterval = setInterval(() => {
            fetch('check_payment.php?transaction_id=<?= $payment['id'] ?? '' ?>')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'completed') {
                        clearInterval(checkInterval);
                        window.location.href = 'success.php';
                    }
                });
        }, 3000);

        particlesJS("particles-js", {
            "particles": {
                "number": { "value": 25, "density": { "enable": true, "value_area": 800 } },
                "color": { "value": "#ff0000" },
                "shape": { "type": "circle" },
                "opacity": { "value": 0.12, "random": true },
                "size": { "value": 2, "random": true },
                "line_linked": { "enable": true, "distance": 150, "color": "#ff0000", "opacity": 0.06, "width": 1 },
                "move": { "enable": true, "speed": 0.5 }
            }
        });
    </script>
</body>
</html>