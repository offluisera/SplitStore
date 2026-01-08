<?php
/**
 * ============================================
 * ADMIN: GERENCIAR LIMITES DE PLANOS
 * ============================================
 * Arquivo: admin/plan_limits.php
 * 
 * Configurar limites de servidores, produtos e transações por plano
 */

session_start();
require_once '../includes/db.php';

// Proteção admin (ajustar conforme seu sistema de auth)
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$message = "";
$messageType = "";

// ========================================
// SALVAR LIMITES
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_limits') {
    
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['plans'] as $plan_type => $limits) {
            $max_servers = (int)$limits['max_servers'];
            $max_products = (int)$limits['max_products'];
            $max_transactions = (int)$limits['max_transactions'];
            
            // -1 significa ilimitado
            if ($max_servers === 0) $max_servers = -1;
            if ($max_products === 0) $max_products = -1;
            if ($max_transactions === 0) $max_transactions = -1;
            
            $stmt = $pdo->prepare("
                INSERT INTO plan_limits 
                (plan_type, max_servers, max_products, max_transactions_per_month) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    max_servers = VALUES(max_servers),
                    max_products = VALUES(max_products),
                    max_transactions_per_month = VALUES(max_transactions_per_month)
            ");
            
            $stmt->execute([
                $plan_type,
                $max_servers,
                $max_products,
                $max_transactions
            ]);
        }
        
        $pdo->commit();
        $message = "Limites atualizados com sucesso!";
        $messageType = "success";
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Plan Limits Error: " . $e->getMessage());
        $message = "Erro ao salvar: " . $e->getMessage();
        $messageType = "error";
    }
}

// ========================================
// BUSCAR LIMITES ATUAIS
// ========================================
try {
    $stmt = $pdo->query("SELECT * FROM plan_limits ORDER BY FIELD(plan_type, 'basic', 'enterprise', 'gerencial')");
    $limits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Criar array associativo por plan_type
    $plan_limits = [];
    foreach ($limits as $limit) {
        $plan_limits[$limit['plan_type']] = $limit;
    }
    
} catch (PDOException $e) {
    error_log("Error fetching limits: " . $e->getMessage());
    $plan_limits = [];
}

// ========================================
// ESTATÍSTICAS DE USO
// ========================================
try {
    $usage_stats = [];
    
    $plans = ['basic', 'enterprise', 'gerencial'];
    
    foreach ($plans as $plan) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT s.id) as total_stores,
                COUNT(DISTINCT ms.id) as total_servers,
                COUNT(DISTINCT p.id) as total_products,
                COALESCE(AVG(server_count.servers), 0) as avg_servers_per_store
            FROM stores s
            LEFT JOIN minecraft_servers ms ON s.id = ms.store_id
            LEFT JOIN products p ON s.id = p.store_id
            LEFT JOIN (
                SELECT store_id, COUNT(*) as servers 
                FROM minecraft_servers 
                GROUP BY store_id
            ) server_count ON s.id = server_count.store_id
            WHERE s.plan_type = ? AND s.status = 'active'
        ");
        $stmt->execute([$plan]);
        $usage_stats[$plan] = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    error_log("Error fetching usage stats: " . $e->getMessage());
    $usage_stats = [];
}

// Planos disponíveis
$available_plans = [
    'basic' => [
        'name' => 'Básico',
        'color' => 'blue',
        'icon' => 'box',
        'description' => 'Plano inicial para começar'
    ],
    'enterprise' => [
        'name' => 'Enterprise',
        'color' => 'purple',
        'icon' => 'zap',
        'description' => 'Para servidores em crescimento'
    ],
    'gerencial' => [
        'name' => 'Gerencial',
        'color' => 'red',
        'icon' => 'crown',
        'description' => 'Solução completa sem limites'
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Limites de Planos | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #050505; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-strong { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(40px); border: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body class="p-12">

    <div class="max-w-7xl mx-auto">
        
        <header class="mb-12">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-black uppercase italic tracking-tighter mb-2">
                        Limites de <span class="text-red-600">Planos</span>
                    </h1>
                    <p class="text-zinc-500 text-sm font-bold">
                        Configure os limites de recursos para cada plano
                    </p>
                </div>
                
                <a href="dashboard.php" class="glass px-6 py-3 rounded-2xl hover:border-red-600/40 transition flex items-center gap-3">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span class="text-xs font-black uppercase">Voltar</span>
                </a>
            </div>

            <?php if($message): ?>
                <div class="mt-8 glass-strong border-<?= $messageType === 'success' ? 'green' : 'red' ?>-600/30 text-<?= $messageType === 'success' ? 'green' : 'red' ?>-500 p-4 rounded-2xl text-sm font-bold flex items-center gap-3">
                    <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
        </header>

        <!-- Estatísticas de Uso -->
        <div class="grid md:grid-cols-3 gap-6 mb-12">
            <?php foreach ($available_plans as $plan_key => $plan_info): ?>
                <?php $stats = $usage_stats[$plan_key] ?? ['total_stores' => 0, 'total_servers' => 0, 'avg_servers_per_store' => 0]; ?>
                <div class="glass-strong p-8 rounded-3xl border-<?= $plan_info['color'] ?>-600/20">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-14 h-14 bg-<?= $plan_info['color'] ?>-600/20 rounded-2xl flex items-center justify-center">
                            <i data-lucide="<?= $plan_info['icon'] ?>" class="w-7 h-7 text-<?= $plan_info['color'] ?>-500"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-black uppercase"><?= $plan_info['name'] ?></h3>
                            <p class="text-xs text-zinc-500"><?= $plan_info['description'] ?></p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Lojas Ativas</span>
                            <span class="font-black"><?= number_format($stats['total_stores']) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Total de Servidores</span>
                            <span class="font-black"><?= number_format($stats['total_servers']) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-500">Média/Loja</span>
                            <span class="font-black"><?= number_format($stats['avg_servers_per_store'], 1) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Formulário de Limites -->
        <form method="POST" class="space-y-8">
            <input type="hidden" name="action" value="save_limits">

            <?php foreach ($available_plans as $plan_key => $plan_info): ?>
                <?php $current = $plan_limits[$plan_key] ?? ['max_servers' => 1, 'max_products' => 10, 'max_transactions_per_month' => 100]; ?>
                
                <div class="glass-strong rounded-3xl p-10 border-white/10">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-16 h-16 bg-<?= $plan_info['color'] ?>-600/20 rounded-2xl flex items-center justify-center">
                            <i data-lucide="<?= $plan_info['icon'] ?>" class="w-8 h-8 text-<?= $plan_info['color'] ?>-500"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-black uppercase italic"><?= $plan_info['name'] ?></h2>
                            <p class="text-zinc-500 text-sm"><?= $plan_info['description'] ?></p>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-3 gap-6">
                        
                        <!-- Max Servidores -->
                        <div>
                            <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">
                                Máximo de Servidores
                            </label>
                            <input type="number" 
                                   name="plans[<?= $plan_key ?>][max_servers]" 
                                   value="<?= $current['max_servers'] === -1 ? 0 : $current['max_servers'] ?>"
                                   min="0"
                                   placeholder="0 = Ilimitado"
                                   class="w-full bg-white/5 border border-white/10 p-4 rounded-xl outline-none focus:border-<?= $plan_info['color'] ?>-600 transition">
                            <p class="text-[9px] text-zinc-600 mt-2 ml-1">0 = Ilimitado | -1 também é ilimitado</p>
                        </div>

                        <!-- Max Produtos -->
                        <div>
                            <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">
                                Máximo de Produtos
                            </label>
                            <input type="number" 
                                   name="plans[<?= $plan_key ?>][max_products]" 
                                   value="<?= $current['max_products'] === -1 ? 0 : $current['max_products'] ?>"
                                   min="0"
                                   placeholder="0 = Ilimitado"
                                   class="w-full bg-white/5 border border-white/10 p-4 rounded-xl outline-none focus:border-<?= $plan_info['color'] ?>-600 transition">
                        </div>

                        <!-- Max Transações/Mês -->
                        <div>
                            <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">
                                Transações/Mês
                            </label>
                            <input type="number" 
                                   name="plans[<?= $plan_key ?>][max_transactions]" 
                                   value="<?= $current['max_transactions_per_month'] === -1 ? 0 : $current['max_transactions_per_month'] ?>"
                                   min="0"
                                   placeholder="0 = Ilimitado"
                                   class="w-full bg-white/5 border border-white/10 p-4 rounded-xl outline-none focus:border-<?= $plan_info['color'] ?>-600 transition">
                        </div>
                    </div>

                    <!-- Preview dos Limites -->
                    <div class="mt-6 pt-6 border-t border-white/5">
                        <p class="text-xs font-black uppercase text-zinc-600 mb-3">Preview:</p>
                        <div class="flex items-center gap-6 text-sm">
                            <div class="flex items-center gap-2">
                                <i data-lucide="server" class="w-4 h-4 text-<?= $plan_info['color'] ?>-500"></i>
                                <span class="text-zinc-500">Servidores:</span>
                                <span class="font-black text-<?= $plan_info['color'] ?>-500">
                                    <?= $current['max_servers'] === -1 ? '∞' : $current['max_servers'] ?>
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="package" class="w-4 h-4 text-<?= $plan_info['color'] ?>-500"></i>
                                <span class="text-zinc-500">Produtos:</span>
                                <span class="font-black text-<?= $plan_info['color'] ?>-500">
                                    <?= $current['max_products'] === -1 ? '∞' : $current['max_products'] ?>
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i data-lucide="trending-up" class="w-4 h-4 text-<?= $plan_info['color'] ?>-500"></i>
                                <span class="text-zinc-500">Transações:</span>
                                <span class="font-black text-<?= $plan_info['color'] ?>-500">
                                    <?= $current['max_transactions_per_month'] === -1 ? '∞' : number_format($current['max_transactions_per_month']) . '/mês' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Botão Salvar -->
            <div class="flex justify-end">
                <button type="submit" 
                        class="bg-red-600 hover:bg-red-700 px-12 py-5 rounded-2xl font-black uppercase text-sm tracking-widest transition-all hover:scale-105 shadow-lg shadow-red-600/30 flex items-center gap-3">
                    <i data-lucide="save" class="w-5 h-5"></i>
                    Salvar Todas as Alterações
                </button>
            </div>
        </form>

        <!-- Legenda -->
        <div class="glass p-6 rounded-2xl border-blue-600/20 bg-blue-600/5 mt-8">
            <div class="flex items-start gap-4">
                <i data-lucide="info" class="w-6 h-6 text-blue-500 flex-shrink-0"></i>
                <div>
                    <p class="text-sm font-bold text-blue-500 mb-2">Observações Importantes:</p>
                    <ul class="text-xs text-zinc-400 space-y-1 leading-relaxed">
                        <li>• Valor 0 ou -1 = <strong class="text-white">Ilimitado</strong></li>
                        <li>• Alterações afetam <strong class="text-white">imediatamente</strong> todas as lojas do plano</li>
                        <li>• Se uma loja já exceder o novo limite, ela continuará funcionando mas não poderá adicionar mais itens</li>
                        <li>• Transações/Mês são resetadas automaticamente no dia 1º de cada mês</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>

    <script>
        lucide.createIcons();
        
        // Preview dinâmico dos valores
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('input', () => {
                const value = parseInt(input.value) || 0;
                // Atualizar preview em tempo real se desejar
            });
        });
    </script>
</body>
</html>