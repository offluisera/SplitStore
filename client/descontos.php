<?php
/**
 * ============================================
 * DESCONTOS - SISTEMA DE CUPONS
 * ============================================
 */

session_start();
require_once '../includes/db.php';
require_once '../includes/auth_guard.php';

requireAccess(__FILE__);

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];

$message = "";
$messageType = "";

// Criar cupom
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_coupon') {
    
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $type = $_POST['type'] ?? 'percentage';
    $value = (float)($_POST['value'] ?? 0);
    $min_amount = (float)($_POST['min_amount'] ?? 0);
    $max_uses = !empty($_POST['max_uses']) ? (int)$_POST['max_uses'] : null;
    $valid_from = $_POST['valid_from'] ?? date('Y-m-d');
    $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
    
    if (empty($code) || $value <= 0) {
        $message = "Código e valor são obrigatórios";
        $messageType = "error";
    } else {
        try {
            // Verifica se código já existe
            $check = $pdo->prepare("SELECT id FROM coupons WHERE store_id = ? AND code = ?");
            $check->execute([$store_id, $code]);
            
            if ($check->fetch()) {
                $message = "Este código já existe!";
                $messageType = "error";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO coupons (store_id, code, type, value, min_amount, max_uses, valid_from, valid_until, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                
                if ($stmt->execute([$store_id, $code, $type, $value, $min_amount, $max_uses, $valid_from, $valid_until])) {
                    header('Location: descontos.php?success=created');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $message = "Erro: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Deletar cupom
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ? AND store_id = ?");
        $stmt->execute([$_GET['delete'], $store_id]);
        header('Location: descontos.php?success=deleted');
        exit;
    } catch (PDOException $e) {
        $message = "Erro ao deletar: " . $e->getMessage();
        $messageType = "error";
    }
}

// Toggle status
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE coupons 
            SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END 
            WHERE id = ? AND store_id = ?
        ");
        $stmt->execute([$_GET['toggle'], $store_id]);
        header('Location: descontos.php?success=updated');
        exit;
    } catch (PDOException $e) {
        $message = "Erro: " . $e->getMessage();
        $messageType = "error";
    }
}

// Buscar cupons
try {
    $stmt = $pdo->prepare("
        SELECT * FROM coupons 
        WHERE store_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$store_id]);
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas
    $active_coupons = count(array_filter($coupons, fn($c) => $c['status'] === 'active'));
    $total_uses = array_sum(array_column($coupons, 'used_count'));
    
} catch (PDOException $e) {
    error_log("Coupons Error: " . $e->getMessage());
    $coupons = [];
    $active_coupons = 0;
    $total_uses = 0;
}

if (isset($_GET['success'])) {
    $messages = [
        'created' => '✓ Cupom criado com sucesso!',
        'updated' => '✓ Cupom atualizado!',
        'deleted' => '✓ Cupom removido!'
    ];
    $message = $messages[$_GET['success']] ?? '';
    $messageType = "success";
}

function formatMoney($val) {
    return 'R$ ' . number_format($val, 2, ',', '.');
}

function getCouponType($type) {
    return $type === 'percentage' ? '%' : 'R$';
}

function getStatusBadge($status) {
    if ($status === 'active') {
        return '<span class="bg-green-500/10 text-green-500 border border-green-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">Ativo</span>';
    } elseif ($status === 'expired') {
        return '<span class="bg-red-500/10 text-red-500 border border-red-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">Expirado</span>';
    } else {
        return '<span class="bg-zinc-500/10 text-zinc-500 border border-zinc-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">Inativo</span>';
    }
}

function isExpired($valid_until) {
    if (empty($valid_until)) return false;
    return strtotime($valid_until) < time();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Cupons de Desconto | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #000; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .coupon-card { transition: all 0.3s ease; position: relative; overflow: hidden; }
        .coupon-card:hover { transform: translateY(-4px); border-color: rgba(220, 38, 38, 0.3); }
        .coupon-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.05), transparent);
            transition: left 0.5s;
        }
        .coupon-card:hover::before {
            left: 100%;
        }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        
        <!-- Header -->
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">
                    Cupons de <span class="text-red-600">Desconto</span>
                </h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">
                    Crie e gerencie cupons promocionais
                </p>
            </div>
            
            <button onclick="openCouponModal()" class="bg-red-600 px-8 py-4 rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-red-700 transition shadow-lg shadow-red-600/20 flex items-center gap-3">
                <i data-lucide="plus" class="w-5 h-5"></i>
                Novo Cupom
            </button>
        </header>

        <?php if($message): ?>
            <div class="glass border-<?= $messageType === 'success' ? 'green' : 'red' ?>-600/20 text-<?= $messageType === 'success' ? 'green' : 'red' ?>-500 p-5 rounded-2xl mb-8 flex items-center gap-3">
                <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <span class="font-bold"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-blue-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="ticket" class="w-5 h-5 text-blue-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Total de Cupons</p>
                <h3 class="text-3xl font-black"><?= count($coupons) ?></h3>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-green-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Cupons Ativos</p>
                <h3 class="text-3xl font-black text-green-500"><?= $active_coupons ?></h3>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-purple-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="trending-up" class="w-5 h-5 text-purple-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Total de Usos</p>
                <h3 class="text-3xl font-black text-purple-500"><?= number_format($total_uses, 0, ',', '.') ?></h3>
            </div>
        </div>

        <!-- Lista de Cupons -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($coupons)): ?>
                <div class="col-span-full glass rounded-3xl p-24 text-center opacity-30">
                    <i data-lucide="ticket" class="w-16 h-16 mx-auto mb-4 text-zinc-700"></i>
                    <p class="text-xs font-bold uppercase tracking-widest text-zinc-700">
                        Nenhum cupom criado ainda
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($coupons as $coupon): 
                    $expired = isExpired($coupon['valid_until']);
                    $remaining_uses = $coupon['max_uses'] ? ($coupon['max_uses'] - $coupon['used_count']) : null;
                ?>
                <div class="coupon-card glass p-8 rounded-3xl border border-white/5">
                    
                    <!-- Header -->
                    <div class="flex items-start justify-between mb-6">
                        <div class="flex-1">
                            <div class="inline-block px-4 py-2 rounded-xl bg-gradient-to-r from-red-600 to-pink-600 mb-3">
                                <p class="text-2xl font-black uppercase tracking-wider"><?= htmlspecialchars($coupon['code']) ?></p>
                            </div>
                            <p class="text-xs text-zinc-600">Criado em <?= date('d/m/Y', strtotime($coupon['created_at'])) ?></p>
                        </div>
                        
                        <?php if ($expired): ?>
                            <span class="bg-red-500/10 text-red-500 border border-red-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">Expirado</span>
                        <?php else: ?>
                            <?= getStatusBadge($coupon['status']) ?>
                        <?php endif; ?>
                    </div>

                    <!-- Valor do Desconto -->
                    <div class="mb-6">
                        <p class="text-[10px] text-zinc-600 font-black uppercase tracking-wider mb-2">Desconto</p>
                        <div class="flex items-baseline gap-2">
                            <p class="text-5xl font-black text-green-500"><?= number_format($coupon['value'], $coupon['type'] === 'percentage' ? 0 : 2, ',', '.') ?></p>
                            <p class="text-2xl font-black text-green-500"><?= getCouponType($coupon['type']) ?></p>
                        </div>
                    </div>

                    <!-- Detalhes -->
                    <div class="space-y-3 mb-6 text-xs">
                        <?php if ($coupon['min_amount'] > 0): ?>
                        <div class="flex items-center gap-2 text-zinc-500">
                            <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                            <span>Valor mínimo: <strong class="text-white"><?= formatMoney($coupon['min_amount']) ?></strong></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($coupon['max_uses']): ?>
                        <div class="flex items-center gap-2 text-zinc-500">
                            <i data-lucide="users" class="w-4 h-4"></i>
                            <span>Usos: <strong class="text-white"><?= $coupon['used_count'] ?>/<?= $coupon['max_uses'] ?></strong></span>
                        </div>
                        <?php else: ?>
                        <div class="flex items-center gap-2 text-zinc-500">
                            <i data-lucide="infinity" class="w-4 h-4"></i>
                            <span>Usos ilimitados <strong class="text-white">(<?= $coupon['used_count'] ?> até agora)</strong></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($coupon['valid_until'])): ?>
                        <div class="flex items-center gap-2 text-zinc-500">
                            <i data-lucide="calendar" class="w-4 h-4"></i>
                            <span>Válido até: <strong class="text-white"><?= date('d/m/Y', strtotime($coupon['valid_until'])) ?></strong></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Ações -->
                    <div class="grid grid-cols-2 gap-3 pt-6 border-t border-white/5">
                        <a href="?toggle=<?= $coupon['id'] ?>" 
                           class="bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white text-[10px] font-black uppercase py-2 rounded-xl text-center transition">
                            <?= $coupon['status'] === 'active' ? 'Desativar' : 'Ativar' ?>
                        </a>
                        <a href="?delete=<?= $coupon['id'] ?>" 
                           onclick="return confirm('Tem certeza que deseja deletar este cupom?')"
                           class="bg-red-900/20 hover:bg-red-900/30 text-red-500 text-[10px] font-black uppercase py-2 rounded-xl text-center transition">
                            Deletar
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

    <!-- Modal: Criar Cupom -->
    <div id="couponModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-3xl p-10 rounded-[3rem] border-red-600/20 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black italic uppercase">
                    Criar <span class="text-red-600">Cupom</span>
                </h3>
                <button onclick="closeCouponModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="create_coupon">

                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Código -->
                    <div>
                        <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Código do Cupom *</label>
                        <input type="text" name="code" required placeholder="BEMVINDO10"
                               oninput="this.value = this.value.toUpperCase()"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition uppercase font-bold">
                        <p class="text-[10px] text-zinc-600 mt-2 ml-1">Apenas letras, números e hífen</p>
                    </div>

                    <!-- Tipo -->
                    <div>
                        <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Tipo de Desconto *</label>
                        <select name="type" required
                                class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                            <option value="percentage">Percentual (%)</option>
                            <option value="fixed">Valor Fixo (R$)</option>
                        </select>
                    </div>

                    <!-- Valor -->
                    <div>
                        <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Valor do Desconto *</label>
                        <input type="number" name="value" required min="0.01" step="0.01" placeholder="10.00"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>

                    <!-- Valor Mínimo -->
                    <div>
                        <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Valor Mínimo da Compra</label>
                        <input type="number" name="min_amount" min="0" step="0.01" placeholder="0.00"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                        <p class="text-[10px] text-zinc-600 mt-2 ml-1">Deixe 0 para sem mínimo</p>
                    </div>

                    <!-- Máximo de Usos -->
                    <div>
                        <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Máximo de Usos</label>
                        <input type="number" name="max_uses" min="1" placeholder="Ilimitado"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                        <p class="text-[10px] text-zinc-600 mt-2 ml-1">Deixe vazio para ilimitado</p>
                    </div>

                    <!-- Data de Início -->
                    <div>
                        <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Válido A Partir De</label>
                        <input type="date" name="valid_from" value="<?= date('Y-m-d') ?>"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>

                    <!-- Data de Expiração -->
                    <div class="md:col-span-2">
                        <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Data de Expiração</label>
                        <input type="date" name="valid_until"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                        <p class="text-[10px] text-zinc-600 mt-2 ml-1">Deixe vazio para sem data de expiração</p>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="glass p-6 rounded-2xl border border-blue-600/20 bg-blue-600/5">
                    <div class="flex items-start gap-3">
                        <i data-lucide="lightbulb" class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <p class="text-xs font-bold text-blue-500 mb-2">Dica</p>
                            <ul class="text-xs text-zinc-400 space-y-1 leading-relaxed">
                                <li>• Códigos curtos e memoráveis convertem mais</li>
                                <li>• Use descontos menores com limite de usos para criar urgência</li>
                                <li>• Defina valor mínimo para incentivar compras maiores</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Botões -->
                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeCouponModal()" 
                            class="flex-1 bg-zinc-900 hover:bg-zinc-800 py-4 rounded-xl font-black uppercase text-xs transition">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-red-600 hover:bg-red-700 py-4 rounded-xl font-black uppercase text-xs tracking-widest transition shadow-lg shadow-red-600/20 flex items-center justify-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Criar Cupom
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function openCouponModal() {
            document.getElementById('couponModal').classList.remove('hidden');
            lucide.createIcons();
        }
        
        function closeCouponModal() {
            document.getElementById('couponModal').classList.add('hidden');
        }
        
        document.getElementById('couponModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeCouponModal();
        });
    </script>
</body>
</html>