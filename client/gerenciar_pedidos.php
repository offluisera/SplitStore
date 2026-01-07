<?php
/**
 * ============================================
 * SPLITSTORE - GERENCIAMENTO DE PEDIDOS
 * ============================================
 * Histórico completo de vendas e transações
 */

session_start();
require_once '../includes/db.php';
require_once '../includes/auth_guard.php';

requireAccess(__FILE__);

if (!isset($_SESSION['store_logged'])) {
    header('Location: login.php');
    exit;
}

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];

// ========================================
// FILTROS E PAGINAÇÃO
// ========================================

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$filter_status = $_GET['status'] ?? 'all';
$filter_method = $_GET['method'] ?? 'all';
$filter_date = $_GET['date'] ?? 'all'; // today, week, month, all
$search = $_GET['search'] ?? '';

// ========================================
// CONSTRUIR QUERY COM FILTROS
// ========================================

$where = ["t.store_id = ?"];
$params = [$store_id];

// Filtro de Status
if ($filter_status !== 'all') {
    $where[] = "t.status = ?";
    $params[] = $filter_status;
}

// Filtro de Método de Pagamento
if ($filter_method !== 'all') {
    $where[] = "t.payment_method = ?";
    $params[] = $filter_method;
}

// Filtro de Data
switch ($filter_date) {
    case 'today':
        $where[] = "DATE(t.created_at) = CURDATE()";
        break;
    case 'week':
        $where[] = "t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $where[] = "MONTH(t.created_at) = MONTH(CURRENT_DATE()) AND YEAR(t.created_at) = YEAR(CURRENT_DATE())";
        break;
}

// Busca por ID ou Email
if (!empty($search)) {
    $where[] = "(t.id LIKE ? OR t.customer_email LIKE ? OR p.name LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(" AND ", $where);

// ========================================
// BUSCAR PEDIDOS
// ========================================

try {
    // Total de registros (para paginação)
    $count_sql = "SELECT COUNT(*) FROM transactions t 
                  LEFT JOIN products p ON t.product_id = p.id 
                  WHERE {$where_clause}";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_orders = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_orders / $per_page);
    
    // Buscar pedidos com paginação
    $sql = "SELECT 
                t.*,
                p.name as product_name,
                p.image_url as product_image
            FROM transactions t
            LEFT JOIN products p ON t.product_id = p.id
            WHERE {$where_clause}
            ORDER BY t.created_at DESC
            LIMIT {$per_page} OFFSET {$offset}";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // ========================================
    // ESTATÍSTICAS DO PERÍODO FILTRADO
    // ========================================
    
    $stats_sql = "SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as total_revenue,
                    COALESCE(AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END), 0) as avg_ticket,
                    COUNT(DISTINCT customer_email) as unique_customers
                  FROM transactions t
                  WHERE {$where_clause}";
    
    $stmt = $pdo->prepare($stats_sql);
    $stmt->execute($params);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Orders Error: " . $e->getMessage());
    $orders = [];
    $stats = [
        'total_orders' => 0,
        'total_revenue' => 0,
        'avg_ticket' => 0,
        'unique_customers' => 0
    ];
}

// ========================================
// FUNÇÕES AUXILIARES
// ========================================

function formatMoney($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function getStatusClass($status) {
    $classes = [
        'completed' => 'bg-green-500/10 text-green-500 border-green-500/20',
        'pending' => 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20',
        'failed' => 'bg-red-500/10 text-red-500 border-red-500/20',
        'refunded' => 'bg-blue-500/10 text-blue-500 border-blue-500/20',
        'cancelled' => 'bg-zinc-500/10 text-zinc-500 border-zinc-500/20'
    ];
    return $classes[$status] ?? 'bg-zinc-800 text-zinc-500 border-white/5';
}

function getStatusLabel($status) {
    $labels = [
        'completed' => 'Pago',
        'pending' => 'Pendente',
        'failed' => 'Falhou',
        'refunded' => 'Reembolsado',
        'cancelled' => 'Cancelado'
    ];
    return $labels[$status] ?? ucfirst($status);
}

function getPaymentMethodIcon($method) {
    $icons = [
        'pix' => 'qr-code',
        'credit_card' => 'credit-card',
        'boleto' => 'file-text',
        'paypal' => 'wallet',
        'transfer' => 'arrow-right-left'
    ];
    return $icons[$method] ?? 'wallet';
}

function getPaymentMethodLabel($method) {
    $labels = [
        'pix' => 'PIX',
        'credit_card' => 'Cartão de Crédito',
        'boleto' => 'Boleto',
        'paypal' => 'PayPal',
        'transfer' => 'Transferência'
    ];
    return $labels[$method] ?? ucfirst($method);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Pedidos | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050505; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .sidebar-item:hover { background: rgba(220, 38, 38, 0.05); color: #dc2626; }
        
        /* Animação de entrada */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .order-row {
            animation: slideIn 0.3s ease-out;
        }
        
        /* Tooltip customizado */
        .tooltip {
            position: relative;
        }
        
        .tooltip:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 8px 12px;
            background: rgba(0, 0, 0, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
            z-index: 1000;
            margin-bottom: 8px;
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
                    Histórico de <span class="text-red-600">Pedidos</span>
                </h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">
                    Gestão completa de vendas e transações
                </p>
            </div>
            
            <div class="flex gap-4">
                <button onclick="exportOrders()" class="glass px-6 py-3 rounded-2xl hover:border-red-600/40 transition flex items-center gap-3 group">
                    <i data-lucide="download" class="w-4 h-4 text-zinc-500 group-hover:text-red-600 transition"></i>
                    <span class="text-xs font-black uppercase tracking-wider text-zinc-500 group-hover:text-white transition">Exportar</span>
                </button>
                
                <button onclick="location.reload()" class="glass p-4 rounded-2xl hover:border-red-600/40 transition group">
                    <i data-lucide="refresh-cw" class="w-4 h-4 text-zinc-500 group-hover:text-red-600 group-hover:rotate-180 transition-all duration-500"></i>
                </button>
            </div>
        </header>

        <!-- Estatísticas do Período -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-blue-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="shopping-cart" class="w-5 h-5 text-blue-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Total de Pedidos</p>
                <h3 class="text-3xl font-black"><?= number_format($stats['total_orders'], 0, ',', '.') ?></h3>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-green-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="dollar-sign" class="w-5 h-5 text-green-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Receita Total</p>
                <h3 class="text-3xl font-black text-green-500"><?= formatMoney($stats['total_revenue']) ?></h3>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-purple-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="receipt" class="w-5 h-5 text-purple-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Ticket Médio</p>
                <h3 class="text-3xl font-black text-purple-500"><?= formatMoney($stats['avg_ticket']) ?></h3>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-red-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="users" class="w-5 h-5 text-red-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Clientes Únicos</p>
                <h3 class="text-3xl font-black text-red-500"><?= number_format($stats['unique_customers'], 0, ',', '.') ?></h3>
            </div>
        </div>

        <!-- Filtros -->
        <div class="glass p-6 rounded-3xl mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                
                <!-- Busca -->
                <div class="md:col-span-2">
                    <div class="relative">
                        <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-600"></i>
                        <input type="text" name="search" placeholder="Buscar por ID, email ou produto..." 
                               value="<?= htmlspecialchars($search) ?>"
                               class="w-full bg-white/5 border border-white/10 pl-12 pr-4 py-3 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <select name="status" class="w-full bg-zinc-900 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-600 transition appearance-none">
                        <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>Todos Status</option>
                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Pago</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pendente</option>
                        <option value="failed" <?= $filter_status === 'failed' ? 'selected' : '' ?>>Falhou</option>
                        <option value="refunded" <?= $filter_status === 'refunded' ? 'selected' : '' ?>>Reembolsado</option>
                    </select>
                </div>

                <!-- Método -->
                <div>
                    <select name="method" class="w-full bg-zinc-900 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-600 transition appearance-none">
                        <option value="all" <?= $filter_method === 'all' ? 'selected' : '' ?>>Todos Métodos</option>
                        <option value="pix" <?= $filter_method === 'pix' ? 'selected' : '' ?>>PIX</option>
                        <option value="credit_card" <?= $filter_method === 'credit_card' ? 'selected' : '' ?>>Cartão</option>
                        <option value="boleto" <?= $filter_method === 'boleto' ? 'selected' : '' ?>>Boleto</option>
                    </select>
                </div>

                <!-- Período -->
                <div>
                    <select name="date" class="w-full bg-zinc-900 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-600 transition appearance-none">
                        <option value="all" <?= $filter_date === 'all' ? 'selected' : '' ?>>Todo Período</option>
                        <option value="today" <?= $filter_date === 'today' ? 'selected' : '' ?>>Hoje</option>
                        <option value="week" <?= $filter_date === 'week' ? 'selected' : '' ?>>Última Semana</option>
                        <option value="month" <?= $filter_date === 'month' ? 'selected' : '' ?>>Este Mês</option>
                    </select>
                </div>

                <!-- Botões -->
                <div class="md:col-span-5 flex gap-3">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 px-8 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition flex items-center gap-2">
                        <i data-lucide="filter" class="w-4 h-4"></i>
                        Aplicar Filtros
                    </button>
                    <a href="orders.php" class="glass px-8 py-3 rounded-xl font-black text-xs uppercase tracking-widest text-zinc-500 hover:text-white transition flex items-center gap-2">
                        <i data-lucide="x" class="w-4 h-4"></i>
                        Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabela de Pedidos -->
        <div class="glass rounded-3xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-white/5">
                        <tr class="text-[10px] font-black uppercase tracking-widest text-zinc-500">
                            <th class="p-6 text-left">Pedido</th>
                            <th class="p-6 text-left">Produto</th>
                            <th class="p-6 text-left">Cliente</th>
                            <th class="p-6 text-left">Método</th>
                            <th class="p-6 text-right">Valor</th>
                            <th class="p-6 text-center">Status</th>
                            <th class="p-6 text-center">Data</th>
                            <th class="p-6 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="p-16 text-center">
                                    <div class="opacity-30">
                                        <i data-lucide="inbox" class="w-16 h-16 mx-auto mb-4"></i>
                                        <p class="text-xs font-bold uppercase tracking-widest text-zinc-700">
                                            Nenhum pedido encontrado
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($orders as $order): ?>
                                <tr class="order-row border-b border-white/5 hover:bg-white/[0.01] transition">
                                    <!-- ID do Pedido -->
                                    <td class="p-6">
                                        <div>
                                            <p class="text-sm font-black">#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></p>
                                            <?php if(!empty($order['external_id'])): ?>
                                                <p class="text-[9px] text-zinc-600 font-mono mt-1"><?= htmlspecialchars(substr($order['external_id'], 0, 16)) ?>...</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Produto -->
                                    <td class="p-6">
                                        <div class="flex items-center gap-3">
                                            <?php if(!empty($order['product_image'])): ?>
                                                <img src="<?= htmlspecialchars($order['product_image']) ?>" 
                                                     alt="<?= htmlspecialchars($order['product_name']) ?>"
                                                     class="w-10 h-10 rounded-lg object-cover border border-white/10">
                                            <?php else: ?>
                                                <div class="w-10 h-10 bg-zinc-900 rounded-lg flex items-center justify-center border border-white/10">
                                                    <i data-lucide="package" class="w-5 h-5 text-zinc-700"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <p class="text-xs font-bold"><?= htmlspecialchars($order['product_name'] ?? 'Produto removido') ?></p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Cliente -->
                                    <td class="p-6">
                                        <div>
                                            <p class="text-xs font-bold"><?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?></p>
                                            <p class="text-[10px] text-zinc-600"><?= htmlspecialchars($order['customer_email'] ?? 'N/A') ?></p>
                                        </div>
                                    </td>

                                    <!-- Método de Pagamento -->
                                    <td class="p-6">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 bg-white/5 rounded-lg flex items-center justify-center">
                                                <i data-lucide="<?= getPaymentMethodIcon($order['payment_method']) ?>" class="w-4 h-4 text-zinc-400"></i>
                                            </div>
                                            <span class="text-xs font-bold uppercase"><?= getPaymentMethodLabel($order['payment_method']) ?></span>
                                        </div>
                                    </td>

                                    <!-- Valor -->
                                    <td class="p-6 text-right">
                                        <p class="text-base font-black"><?= formatMoney($order['amount']) ?></p>
                                    </td>

                                    <!-- Status -->
                                    <td class="p-6 text-center">
                                        <span class="inline-block px-3 py-1 rounded-lg text-[9px] font-black uppercase border <?= getStatusClass($order['status']) ?>">
                                            <?= getStatusLabel($order['status']) ?>
                                        </span>
                                    </td>

                                    <!-- Data -->
                                    <td class="p-6 text-center">
                                        <div>
                                            <p class="text-xs font-bold"><?= date('d/m/Y', strtotime($order['created_at'])) ?></p>
                                            <p class="text-[10px] text-zinc-600"><?= date('H:i', strtotime($order['created_at'])) ?></p>
                                        </div>
                                    </td>

                                    <!-- Ações -->
                                    <td class="p-6">
                                        <div class="flex items-center justify-end gap-2">
                                            <button onclick="viewOrder(<?= $order['id'] ?>)" 
                                                    class="tooltip w-8 h-8 glass rounded-lg flex items-center justify-center hover:border-red-600/50 transition text-zinc-400 hover:text-red-600"
                                                    data-tooltip="Ver Detalhes">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </button>
                                            
                                            <?php if($order['status'] === 'completed'): ?>
                                                <button onclick="downloadReceipt(<?= $order['id'] ?>)" 
                                                        class="tooltip w-8 h-8 glass rounded-lg flex items-center justify-center hover:border-green-600/50 transition text-zinc-400 hover:text-green-600"
                                                        data-tooltip="Baixar Recibo">
                                                    <i data-lucide="download" class="w-4 h-4"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <?php if($total_pages > 1): ?>
                <div class="border-t border-white/5 p-6">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-zinc-600 font-bold">
                            Mostrando <?= $offset + 1 ?> - <?= min($offset + $per_page, $total_orders) ?> de <?= $total_orders ?> pedidos
                        </p>
                        
                        <div class="flex gap-2">
                            <?php if($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&status=<?= $filter_status ?>&method=<?= $filter_method ?>&date=<?= $filter_date ?>&search=<?= urlencode($search) ?>" 
                                   class="glass px-4 py-2 rounded-xl text-xs font-black uppercase hover:border-red-600/50 transition">
                                    Anterior
                                </a>
                            <?php endif; ?>
                            
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <a href="?page=<?= $i ?>&status=<?= $filter_status ?>&method=<?= $filter_method ?>&date=<?= $filter_date ?>&search=<?= urlencode($search) ?>" 
                                   class="<?= $i === $page ? 'bg-red-600 text-white' : 'glass text-zinc-500 hover:text-white' ?> px-4 py-2 rounded-xl text-xs font-black transition">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&status=<?= $filter_status ?>&method=<?= $filter_method ?>&date=<?= $filter_date ?>&search=<?= urlencode($search) ?>" 
                                   class="glass px-4 py-2 rounded-xl text-xs font-black uppercase hover:border-red-600/50 transition">
                                    Próxima
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Modal de Detalhes do Pedido -->
    <div id="orderModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-2xl p-10 rounded-[3rem] border-red-600/20 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black italic uppercase">
                    Detalhes do <span class="text-red-600">Pedido</span>
                </h3>
                <button onclick="closeOrderModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <div id="orderDetails">
                <!-- Conteúdo será carregado via JavaScript -->
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function viewOrder(orderId) {
            // Aqui você faria uma requisição AJAX para buscar os detalhes
            document.getElementById('orderModal').classList.remove('hidden');
            document.getElementById('orderDetails').innerHTML = `
                <div class="text-center py-8">
                    <div class="animate-spin w-8 h-8 border-2 border-red-600 border-t-transparent rounded-full mx-auto mb-4"></div>
                    <p class="text-sm text-zinc-500">Carregando detalhes...</p>
                </div>
            `;
            
            // Simulação - substituir por AJAX real
            setTimeout(() => {
                document.getElementById('orderDetails').innerHTML = `
                    <div class="space-y-6">
                        <div class="glass p-6 rounded-2xl">
                            <h4 class="text-xs font-black uppercase text-zinc-600 mb-4">Informações do Pedido</h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-zinc-500 text-xs mb-1">ID do Pedido</p>
                                    <p class="font-black">#${String(orderId).padStart(6, '0')}</p>
                                </div>
                                <div>
                                    <p class="text-zinc-500 text-xs mb-1">Status</p>
                                    <span class="inline-block px-3 py-1 rounded-lg text-[9px] font-black uppercase bg-green-500/10 text-green-500 border border-green-500/20">
                                        PAGO
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="glass p-6 rounded-2xl">
                            <h4 class="text-xs font-black uppercase text-zinc-600 mb-4">Cliente</h4>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-zinc-500">Nome:</span>
                                    <span class="font-bold">João Silva</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-zinc-500">Email:</span>
                                    <span class="font-bold">joao@email.com</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-zinc-500">Nickname:</span>
                                    <span class="font-bold">JoaoGamer123</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="glass p-6 rounded-2xl">
                            <h4 class="text-xs font-black uppercase text-zinc-600 mb-4">Produto</h4>
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-zinc-900 rounded-xl"></div>
                                <div class="flex-1">
                                    <p class="font-black">VIP Diamante</p>
                                    <p class="text-xs text-zinc-500">30 dias de acesso</p>
                                </div>
                                <p class="text-2xl font-black text-green-500">R$ 29,90</p>
                            </div>
                        </div>
                        
                        <button onclick="closeOrderModal()" class="w-full bg-red-600 hover:bg-red-700 py-4 rounded-xl font-black uppercase text-xs tracking-widest transition">
                            Fechar
                        </button>
                    </div>
                `;
                lucide.createIcons();
            }, 800);
        }
        
        function closeOrderModal() {
            document.getElementById('orderModal').classList.add('hidden');
        }
        
        function exportOrders() {
            // Implementar exportação CSV/Excel
            alert('Funcionalidade de exportação será implementada');
        }
        
        function downloadReceipt(orderId) {
            // Implementar geração de PDF
            alert('Recibo #' + orderId + ' será baixado');
        }
        
        // Fecha modal ao clicar fora
        document.getElementById('orderModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeOrderModal();
        });
        
        // Fecha modal com ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeOrderModal();
        });
    </script>
</body>
</html>