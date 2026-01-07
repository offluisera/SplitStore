<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Pagamento PIX - SplitStore</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #000; color: #fff; }
        .glass { background: rgba(15, 15, 15, 0.8); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
    </style>
</head>
<body>

<?php
session_start();
require_once 'includes/db.php';

// Verifica se usuário está logado
if (!isset($_SESSION['store_logged'])) {
    header('Location: index.php');
    exit;
}

$store_id = $_SESSION['store_id'];

// Busca fatura ativa (pendente e não vencida)
try {
    $stmt = $pdo->prepare("
        SELECT *,
            TIMESTAMPDIFF(SECOND, NOW(), due_date) as seconds_remaining
        FROM invoices
        WHERE store_id = ?
        AND status = 'pending'
        AND due_date > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$store_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        // Não tem fatura pendente, redireciona
        header('Location: client/faturas.php');
        exit;
    }
} catch (Exception $e) {
    die("Erro ao carregar fatura");
}

$time_remaining = max(0, (int)$invoice['seconds_remaining']);
?>

    <div class="min-h-screen py-8 px-4">
        
        <!-- Header com Botão Voltar -->
        <div class="max-w-6xl mx-auto mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="client/dashboard.php" 
                       class="glass p-3 rounded-xl hover:bg-white/10 transition flex items-center gap-2 text-zinc-400 hover:text-white">
                        <i data-lucide="arrow-left" class="w-5 h-5"></i>
                        <span class="text-sm font-bold hidden md:inline">Voltar para Dashboard</span>
                    </a>
                    
                    <div class="w-10 h-10 bg-gradient-to-br from-red-600 to-red-900 rounded-xl flex items-center justify-center font-black">S</div>
                    <span class="text-xl font-black tracking-tighter uppercase hidden md:inline">
                        Split<span class="text-red-600">Store</span>
                    </span>
                </div>
                
                <div class="glass px-4 py-2 rounded-full flex items-center gap-2">
                    <div class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></div>
                    <span class="text-xs font-bold text-zinc-400">Aguardando Pagamento</span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-6xl mx-auto">
            <div class="grid lg:grid-cols-5 gap-8">
                
                <!-- QR Code (3 colunas) -->
                <div class="lg:col-span-3">
                    <div class="glass rounded-3xl p-8">
                        
                        <div class="text-center mb-8">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-600/10 border-2 border-green-600/30 rounded-2xl mb-4">
                                <i data-lucide="qr-code" class="w-8 h-8 text-green-500"></i>
                            </div>
                            <h1 class="text-3xl font-black uppercase tracking-tight mb-2">
                                Pague com <span class="text-green-500">PIX</span>
                            </h1>
                            <p class="text-zinc-400 text-sm">Aprovação automática em segundos</p>
                        </div>

                        <!-- QR Code -->
                        <div class="bg-white p-6 rounded-2xl mb-6 mx-auto max-w-sm">
                            <img src="<?= htmlspecialchars($invoice['qr_code']) ?>" 
                                 alt="QR Code PIX" 
                                 class="w-full h-auto">
                        </div>

                        <!-- Código Copia e Cola -->
                        <div class="glass rounded-2xl p-5 mb-6">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-black uppercase text-zinc-400 tracking-wider">
                                    Código PIX Copia e Cola
                                </h3>
                                <button onclick="copyPixCode()" 
                                        id="copyButton"
                                        class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-xl text-xs font-black uppercase transition">
                                    <i data-lucide="copy" class="w-4 h-4"></i>
                                    <span id="copyText">Copiar</span>
                                </button>
                            </div>
                            <input type="text" 
                                   id="pixCode" 
                                   readonly 
                                   value="<?= htmlspecialchars($invoice['qr_code_text']) ?>"
                                   class="w-full bg-black/50 border border-white/10 p-3 rounded-xl text-xs font-mono text-zinc-400 outline-none select-all">
                        </div>

                        <!-- Timer -->
                        <?php if ($time_remaining > 0): ?>
                        <div class="glass rounded-2xl p-5 border <?= $time_remaining < 300 ? 'border-red-600/30 bg-red-600/5' : 'border-yellow-600/20 bg-yellow-600/5' ?>">
                            <div class="flex items-center justify-center gap-3">
                                <i data-lucide="clock" class="w-5 h-5 text-yellow-500"></i>
                                <div class="text-center">
                                    <p class="text-xs text-zinc-400 mb-1">Tempo restante</p>
                                    <p class="text-2xl font-black <?= $time_remaining < 300 ? 'text-red-500' : 'text-yellow-500' ?>" 
                                       id="countdown">
                                        <?= gmdate("i:s", $time_remaining) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Aviso: Pode Voltar -->
                        <div class="mt-6 glass border-blue-600/20 bg-blue-600/5 p-5 rounded-2xl">
                            <div class="flex items-start gap-3">
                                <i data-lucide="info" class="w-5 h-5 text-blue-500 flex-shrink-0"></i>
                                <div>
                                    <h5 class="text-sm font-black text-blue-500 mb-1">Fique Tranquilo</h5>
                                    <p class="text-xs text-zinc-400 leading-relaxed">
                                        Você pode voltar para a dashboard a qualquer momento. 
                                        Esta fatura ficará disponível em <strong>Faturas</strong> até a confirmação do pagamento.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumo (2 colunas) -->
                <div class="lg:col-span-2">
                    <div class="glass rounded-2xl p-8">
                        <h2 class="text-xl font-black uppercase tracking-tight mb-6">Resumo</h2>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-zinc-400">Fatura</span>
                                <span class="text-sm font-black"><?= $invoice['invoice_number'] ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-zinc-400">Descrição</span>
                                <span class="text-sm font-bold text-right"><?= htmlspecialchars($invoice['description']) ?></span>
                            </div>
                            <div class="flex items-center justify-between pt-4 border-t border-white/5">
                                <span class="text-xs text-zinc-500 uppercase font-bold">Total</span>
                                <span class="text-2xl font-black text-green-500">R$ <?= number_format($invoice['amount'], 2, ',', '.') ?></span>
                            </div>
                        </div>

                        <div class="mt-8 space-y-3">
                            <div class="flex items-center gap-3 text-xs">
                                <i data-lucide="shield-check" class="w-4 h-4 text-green-500"></i>
                                <span class="text-zinc-400 font-bold">Pagamento 100% Seguro</span>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                <i data-lucide="zap" class="w-4 h-4 text-yellow-500"></i>
                                <span class="text-zinc-400 font-bold">Ativação Instantânea</span>
                            </div>
                        </div>
                    </div>

                    <!-- Suporte -->
                    <div class="glass rounded-2xl p-6 border border-blue-600/20 bg-blue-600/5 mt-6">
                        <div class="flex items-start gap-3">
                            <i data-lucide="headset" class="w-6 h-6 text-blue-500 flex-shrink-0"></i>
                            <div>
                                <h5 class="text-sm font-black text-blue-500 mb-2">Precisa de Ajuda?</h5>
                                <p class="text-xs text-zinc-400 leading-relaxed mb-3">
                                    Suporte online 24/7
                                </p>
                                <a href="#" class="text-xs font-bold text-blue-500 hover:underline">
                                    Falar com Suporte →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        let timeRemaining = <?= $time_remaining ?>;
        
        function updateCountdown() {
            if (timeRemaining <= 0) {
                document.getElementById('countdown').textContent = 'Expirado';
                clearInterval(countdownInterval);
                setTimeout(() => location.reload(), 2000);
                return;
            }
            
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            document.getElementById('countdown').textContent = 
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            
            timeRemaining--;
        }
        
        const countdownInterval = setInterval(updateCountdown, 1000);
        
        function copyPixCode() {
            const input = document.getElementById('pixCode');
            input.select();
            document.execCommand('copy');
            
            const btn = document.getElementById('copyButton');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Copiado!';
            btn.classList.add('bg-green-500');
            lucide.createIcons();
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.classList.remove('bg-green-500');
                lucide.createIcons();
            }, 2500);
        }
        
        // Polling para verificar pagamento
        let checkCount = 0;
        function checkPayment() {
            if (checkCount >= 600) {
                clearInterval(pollingInterval);
                return;
            }
            
            fetch('api/check_invoice_status.php?invoice_id=<?= $invoice['id'] ?>')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'paid' || data.status === 'completed') {
                        clearInterval(pollingInterval);
                        window.location.href = 'client/dashboard.php?payment_confirmed=1';
                    }
                    checkCount++;
                })
                .catch(() => checkCount++);
        }
        
        const pollingInterval = setInterval(checkPayment, 3000);
    </script>
</body>
</html>