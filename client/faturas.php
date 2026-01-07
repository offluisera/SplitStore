<?php
/**
 * ============================================
 * PÁGINA DE FATURAS - client/faturas.php
 * ============================================
 */

session_start();
require_once '../includes/db.php';
require_once '../includes/auth_guard.php';

// Protege a página
requireAccess(__FILE__);

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];

// Mensagem de acesso negado
$access_denied_message = $_SESSION['access_denied'] ?? null;
unset($_SESSION['access_denied']);

// Busca faturas
try {
    $stmt = $pdo->prepare("
        SELECT *,
            TIMESTAMPDIFF(SECOND, NOW(), due_date) as seconds_remaining
        FROM invoices
        WHERE store_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$store_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separa pendente da primeira
    $pending_invoice = null;
    $history_invoices = [];
    
    foreach ($invoices as $inv) {
        if ($inv['status'] === 'pending' && $inv['seconds_remaining'] > 0) {
            $pending_invoice = $inv;
        } else {
            $history_invoices[] = $inv;
        }
    }
    
} catch (Exception $e) {
    error_log("Invoices Error: " . $e->getMessage());
    $invoices = [];
}

function formatMoney($val) {
    return 'R$ ' . number_format($val, 2, ',', '.');
}

function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="bg-yellow-500/10 text-yellow-500 border border-yellow-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">Pendente</span>',
        'paid' => '<span class="bg-green-500/10 text-green-500 border border-green-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">Pago</span>',
        'expired' => '<span class="bg-red-500/10 text-red-500 border border-red-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">Vencido</span>',
        'cancelled' => '<span class="bg-zinc-500/10 text-zinc-500 border border-zinc-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">Cancelado</span>',
    ];
    return $badges[$status] ?? $status;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Faturas | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050505; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        
        .invoice-card { transition: all 0.3s ease; }
        .invoice-card:hover { transform: translateY(-2px); border-color: rgba(239, 68, 68, 0.3); }
        
        .countdown-warning { animation: pulse 1s ease-in-out infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        
        <!-- Header -->
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">
                    Suas <span class="text-red-600">Faturas</span>
                </h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">
                    Gerencie seus pagamentos e histórico
                </p>
            </div>
            
            <?php if (getAccessLevel() === 'restricted'): ?>
                <div class="glass border-yellow-600/20 bg-yellow-600/5 px-6 py-3 rounded-2xl">
                    <p class="text-yellow-500 text-sm font-bold flex items-center gap-2">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                        Complete o pagamento para liberar todas as funcionalidades
                    </p>
                </div>
            <?php endif; ?>
        </header>

        <!-- Mensagem de Acesso Negado -->
        <?php if ($access_denied_message): ?>
            <div class="glass border-2 border-yellow-600/30 bg-yellow-600/5 rounded-3xl p-6 mb-8 animate-in">
                <div class="flex items-start gap-4">
                    <i data-lucide="lock" class="w-6 h-6 text-yellow-500 flex-shrink-0"></i>
                    <div>
                        <h3 class="text-lg font-black text-yellow-500 mb-2">Acesso Restrito</h3>
                        <p class="text-zinc-400 mb-4"><?= htmlspecialchars($access_denied_message) ?></p>
                        
                        <?php if ($pending_invoice): ?>
                            <a href="../payment.php" 
                               class="inline-flex items-center gap-2 bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-3 rounded-xl font-bold text-sm transition">
                                <i data-lucide="credit-card" class="w-4 h-4"></i>
                                Efetuar Pagamento Agora
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Fatura Pendente (Destaque) -->
        <?php if ($pending_invoice): ?>
            <div class="glass border-2 border-red-600/30 bg-red-600/5 rounded-3xl p-10 mb-12">
                <div class="flex items-start justify-between mb-8">
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <i data-lucide="alert-circle" class="w-8 h-8 text-red-500"></i>
                            <h2 class="text-2xl font-black uppercase">Fatura Pendente</h2>
                        </div>
                        <p class="text-zinc-400 text-sm">Aguardando confirmação de pagamento</p>
                    </div>
                    
                    <?php if ($pending_invoice['seconds_remaining'] > 0): ?>
                        <div class="text-right">
                            <p class="text-xs text-zinc-600 mb-1">Expira em</p>
                            <p class="text-3xl font-black <?= $pending_invoice['seconds_remaining'] < 600 ? 'text-red-500 countdown-warning' : 'text-yellow-500' ?>" 
                               id="countdown-<?= $pending_invoice['id'] ?>"
                               data-expiry="<?= strtotime($pending_invoice['due_date']) ?>">
                                <?= gmdate("i:s", $pending_invoice['seconds_remaining']) ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grid md:grid-cols-2 gap-8 mb-8">
                    <div>
                        <p class="text-xs text-zinc-600 font-bold uppercase mb-2">Número da Fatura</p>
                        <p class="text-xl font-black text-white"><?= $pending_invoice['invoice_number'] ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-600 font-bold uppercase mb-2">Valor Total</p>
                        <p class="text-3xl font-black text-red-500"><?= formatMoney($pending_invoice['amount']) ?></p>
                    </div>
                </div>

                <div class="bg-black/40 rounded-2xl p-6 mb-6">
                    <p class="text-sm text-zinc-400 mb-2"><?= htmlspecialchars($pending_invoice['description']) ?></p>
                    <p class="text-xs text-zinc-600">Forma de pagamento: <span class="text-white font-bold"><?= strtoupper($pending_invoice['payment_method']) ?></span></p>
                </div>

                <div class="flex gap-4">
                    <a href="../payment.php" 
                       class="flex-1 bg-red-600 hover:bg-red-700 text-white py-4 rounded-xl font-black uppercase text-sm transition flex items-center justify-center gap-2">
                        <i data-lucide="credit-card" class="w-5 h-5"></i>
                        Ver Detalhes do Pagamento
                    </a>
                    <button onclick="checkPaymentStatus(<?= $pending_invoice['id'] ?>)" 
                            class="glass hover:border-green-600/50 text-white px-8 py-4 rounded-xl font-bold text-sm transition">
                        <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Histórico de Faturas -->
        <div class="glass rounded-3xl p-10">
            <h3 class="text-xl font-black uppercase tracking-tight mb-8">Histórico de Faturas</h3>

            <?php if (empty($history_invoices)): ?>
                <div class="text-center py-16 opacity-30">
                    <i data-lucide="file-text" class="w-16 h-16 mx-auto mb-4 text-zinc-700"></i>
                    <p class="text-xs font-bold uppercase tracking-widest text-zinc-700">
                        Nenhuma fatura processada ainda
                    </p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($history_invoices as $inv): ?>
                        <div class="invoice-card glass rounded-2xl p-6 border border-white/5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-6">
                                    <div class="w-12 h-12 bg-zinc-900 rounded-xl flex items-center justify-center">
                                        <i data-lucide="file-text" class="w-6 h-6 text-zinc-600"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-black text-white mb-1"><?= $inv['invoice_number'] ?></p>
                                        <p class="text-xs text-zinc-500"><?= htmlspecialchars($inv['description']) ?></p>
                                        <p class="text-xs text-zinc-700 mt-1"><?= date('d/m/Y H:i', strtotime($inv['created_at'])) ?></p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-6">
                                    <div class="text-right">
                                        <p class="text-xl font-black text-white"><?= formatMoney($inv['amount']) ?></p>
                                        <p class="text-xs text-zinc-600">via <?= strtoupper($inv['payment_method']) ?></p>
                                    </div>
                                    <?= getStatusBadge($inv['status']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <script>
        lucide.createIcons();
        
        // Countdown Timer
        document.querySelectorAll('[id^="countdown-"]').forEach(el => {
            const expiryTimestamp = parseInt(el.dataset.expiry);
            
            function updateCountdown() {
                const now = Math.floor(Date.now() / 1000);
                const remaining = expiryTimestamp - now;
                
                if (remaining <= 0) {
                    el.textContent = 'Expirado';
                    el.classList.add('text-red-500');
                    location.reload();
                    return;
                }
                
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                el.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                
                if (remaining < 600) {
                    el.classList.add('text-red-500', 'countdown-warning');
                }
            }
            
            updateCountdown();
            setInterval(updateCountdown, 1000);
        });
        
        // Verificar status de pagamento
        function checkPaymentStatus(invoiceId) {
            const btn = event.target.closest('button');
            const icon = btn.querySelector('i');
            
            icon.classList.add('animate-spin');
            
            fetch(`../api/check_invoice_status.php?invoice_id=${invoiceId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'paid') {
                        location.reload();
                    } else {
                        setTimeout(() => {
                            icon.classList.remove('animate-spin');
                        }, 1000);
                    }
                })
                .catch(() => {
                    icon.classList.remove('animate-spin');
                });
        }
    </script>
</body>
</html>