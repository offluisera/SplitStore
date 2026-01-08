<?php
/**
 * ============================================
 * CLIENTES - GESTÃO DE COMPRADORES
 * ============================================
 */

session_start();
require_once '../includes/db.php';
require_once '../includes/auth_guard.php';

requireAccess(__FILE__);

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];

// Filtros
$search = $_GET['search'] ?? '';
$order_by = $_GET['order_by'] ?? 'recent'; // recent, oldest, most_spent, most_orders

// Buscar clientes únicos com estatísticas
$sql = "SELECT 
            t.customer_name,
            t.customer_email,
            COUNT(DISTINCT t.id) as total_orders,
            SUM(CASE WHEN t.status = 'completed' THEN t.amount ELSE 0 END) as total_spent,
            MIN(t.created_at) as first_purchase,
            MAX(t.created_at) as last_purchase,
            GROUP_CONCAT(DISTINCT t.payment_method) as payment_methods
        FROM transactions t
        WHERE t.store_id = ?";

$params = [$store_id];

if (!empty($search)) {
    $sql .= " AND (t.customer_name LIKE ? OR t.customer_email LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " GROUP BY t.customer_email, t.customer_name";

// Ordenação
switch ($order_by) {
    case 'oldest':
        $sql .= " ORDER BY first_purchase ASC";
        break;
    case 'most_spent':
        $sql .= " ORDER BY total_spent DESC";
        break;
    case 'most_orders':
        $sql .= " ORDER BY total_orders DESC";
        break;
    case 'recent':
    default:
        $sql .= " ORDER BY last_purchase DESC";
        break;
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas gerais
    $total_customers = count($customers);
    $total_revenue = array_sum(array_column($customers, 'total_spent'));
    $avg_ticket = $total_customers > 0 ? $total_revenue / array_sum(array_column($customers, 'total_orders')) : 0;
    
} catch (PDOException $e) {
    error_log("Customers Error: " . $e->getMessage());
    $customers = [];
    $total_customers = 0;
    $total_revenue = 0;
    $avg_ticket = 0;
}

function formatMoney($val) {
    return 'R$ ' . number_format($val, 2, ',', '.');
}

function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

function getPaymentMethodIcon($method) {
    $icons = [
        'pix' => 'qr-code',
        'credit_card' => 'credit-card',
        'boleto' => 'file-text'
    ];
    return $icons[$method] ?? 'wallet';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Clientes | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #000; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .customer-card { transition: all 0.3s ease; }
        .customer-card:hover { transform: translateY(-2px); border-color: rgba(220, 38, 38, 0.3); }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        
        <!-- Header -->
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">
                    Base de <span class="text-red-600">Clientes</span>
                </h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">
                    Gerencie e analise sua carteira de clientes
                </p>
            </div>
            
            <div class="flex gap-3">
                <button onclick="exportCustomers()" class="glass px-6 py-3 rounded-2xl hover:border-red-600/40 transition flex items-center gap-2 group">
                    <i data-lucide="download" class="w-4 h-4 text-zinc-500 group-hover:text-red-600 transition"></i>
                    <span class="text-xs font-black uppercase tracking-wider">Exportar CSV</span>
                </button>
            </div>
        </header>

        <!-- Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-blue-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="users" class="w-5 h-5 text-blue-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Total de Clientes</p>
                <h3 class="text-3xl font-black"><?= number_format($total_customers, 0, ',', '.') ?></h3>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-green-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="dollar-sign" class="w-5 h-5 text-green-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Receita Total</p>
                <h3 class="text-3xl font-black text-green-500"><?= formatMoney($total_revenue) ?></h3>
            </div>

            <div class="glass p-6 rounded-2xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 bg-purple-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="receipt" class="w-5 h-5 text-purple-600"></i>
                    </div>
                </div>
                <p class="text-zinc-600 text-[10px] font-black uppercase tracking-widest mb-2">Ticket Médio</p>
                <h3 class="text-3xl font-black text-purple-500"><?= formatMoney($avg_ticket) ?></h3>
            </div>
        </div>

        <!-- Filtros -->
        <div class="glass p-6 rounded-3xl mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                
                <!-- Busca -->
                <div class="md:col-span-2">
                    <div class="relative">
                        <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-600"></i>
                        <input type="text" name="search" placeholder="Buscar por nome ou email..." 
                               value="<?= htmlspecialchars($search) ?>"
                               class="w-full bg-white/5 border border-white/10 pl-12 pr-4 py-3 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>
                </div>

                <!-- Ordenação -->
                <div>
                    <select name="order_by" class="w-full bg-zinc-900 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-600 transition appearance-none">
                        <option value="recent" <?= $order_by === 'recent' ? 'selected' : '' ?>>Mais Recentes</option>
                        <option value="oldest" <?= $order_by === 'oldest' ? 'selected' : '' ?>>Mais Antigos</option>
                        <option value="most_spent" <?= $order_by === 'most_spent' ? 'selected' : '' ?>>Maior Gasto</option>
                        <option value="most_orders" <?= $order_by === 'most_orders' ? 'selected' : '' ?>>Mais Pedidos</option>
                    </select>
                </div>

                <div class="md:col-span-3 flex gap-3">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 px-8 py-3 rounded-xl font-black text-xs uppercase tracking-widest transition">
                        Aplicar Filtros
                    </button>
                    <a href="clientes.php" class="glass px-8 py-3 rounded-xl font-black text-xs uppercase tracking-widest text-zinc-500 hover:text-white transition">
                        Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- Lista de Clientes -->
        <div class="glass rounded-3xl overflow-hidden">
            <?php if(empty($customers)): ?>
                <div class="p-24 text-center opacity-30">
                    <i data-lucide="users" class="w-16 h-16 mx-auto mb-4 text-zinc-700"></i>
                    <p class="text-xs font-bold uppercase tracking-widest text-zinc-700">
                        Nenhum cliente encontrado
                    </p>
                </div>
            <?php else: ?>
                <div class="grid gap-4 p-6">
                    <?php foreach($customers as $customer): ?>
                        <div class="customer-card glass p-6 rounded-2xl border border-white/5">
                            <div class="flex items-start justify-between">
                                
                                <!-- Info Principal -->
                                <div class="flex items-start gap-4 flex-1">
                                    <!-- Avatar -->
                                    <div class="w-16 h-16 bg-gradient-to-br from-red-600 to-red-900 rounded-xl flex items-center justify-center font-black text-xl flex-shrink-0">
                                        <?= strtoupper(substr($customer['customer_name'] ?? 'C', 0, 1)) ?>
                                    </div>
                                    
                                    <!-- Dados -->
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-lg font-black mb-1"><?= htmlspecialchars($customer['customer_name'] ?? 'Cliente') ?></h3>
                                        <p class="text-sm text-zinc-500 mb-3"><?= htmlspecialchars($customer['customer_email']) ?></p>
                                        
                                        <div class="flex flex-wrap items-center gap-4 text-xs">
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="shopping-bag" class="w-4 h-4 text-blue-500"></i>
                                                <span class="text-zinc-400"><?= $customer['total_orders'] ?> pedidos</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="calendar" class="w-4 h-4 text-purple-500"></i>
                                                <span class="text-zinc-400">Cliente desde <?= date('d/m/Y', strtotime($customer['first_purchase'])) ?></span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="clock" class="w-4 h-4 text-green-500"></i>
                                                <span class="text-zinc-400">Última compra: <?= date('d/m/Y', strtotime($customer['last_purchase'])) ?></span>
                                            </div>
                                        </div>

                                        <!-- Métodos de Pagamento Usados -->
                                        <?php if (!empty($customer['payment_methods'])): ?>
                                        <div class="flex items-center gap-2 mt-3">
                                            <span class="text-[10px] text-zinc-600 font-bold uppercase">Pagamentos:</span>
                                            <?php 
                                            $methods = array_unique(explode(',', $customer['payment_methods']));
                                            foreach($methods as $method): 
                                            ?>
                                                <div class="px-2 py-1 rounded bg-zinc-900 flex items-center gap-1">
                                                    <i data-lucide="<?= getPaymentMethodIcon($method) ?>" class="w-3 h-3 text-zinc-500"></i>
                                                    <span class="text-[9px] text-zinc-500 uppercase font-bold"><?= $method ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Valor Gasto -->
                                <div class="text-right">
                                    <p class="text-[10px] text-zinc-600 font-black uppercase tracking-wider mb-1">Total Gasto</p>
                                    <p class="text-2xl font-black text-green-500"><?= formatMoney($customer['total_spent']) ?></p>
                                    <p class="text-[10px] text-zinc-600 mt-1">
                                        Média: <?= formatMoney($customer['total_spent'] / $customer['total_orders']) ?>/pedido
                                    </p>
                                </div>
                            </div>

                            <!-- Ações -->
                            <div class="flex gap-3 mt-6 pt-6 border-t border-white/5">
                                <button onclick="viewCustomerDetails('<?= htmlspecialchars($customer['customer_email']) ?>')" 
                                        class="flex-1 bg-blue-900/20 hover:bg-blue-900/30 text-blue-500 text-[10px] font-black uppercase py-2 rounded-xl text-center transition">
                                    Ver Histórico
                                </button>
                                <button onclick="sendEmail('<?= htmlspecialchars($customer['customer_email']) ?>')" 
                                        class="flex-1 bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white text-[10px] font-black uppercase py-2 rounded-xl transition">
                                    Enviar Email
                                </button>
                                <button onclick="addNote('<?= htmlspecialchars($customer['customer_email']) ?>')" 
                                        class="flex-1 bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white text-[10px] font-black uppercase py-2 rounded-xl transition">
                                    Adicionar Nota
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Modal: Detalhes do Cliente -->
    <div id="customerModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-4xl p-10 rounded-[3rem] border-red-600/20 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black italic uppercase">
                    Histórico do <span class="text-red-600">Cliente</span>
                </h3>
                <button onclick="closeCustomerModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <div id="customerDetails">
                <div class="text-center py-8">
                    <div class="animate-spin w-8 h-8 border-2 border-red-600 border-t-transparent rounded-full mx-auto mb-4"></div>
                    <p class="text-sm text-zinc-500">Carregando...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function viewCustomerDetails(email) {
            document.getElementById('customerModal').classList.remove('hidden');
            // Aqui faria requisição AJAX para buscar histórico completo
            setTimeout(() => {
                document.getElementById('customerDetails').innerHTML = `
                    <div class="space-y-6">
                        <div class="glass p-6 rounded-2xl">
                            <h4 class="text-xs font-black uppercase text-zinc-600 mb-4">Histórico de Compras</h4>
                            <p class="text-sm text-zinc-500">Funcionalidade em desenvolvimento</p>
                        </div>
                    </div>
                `;
                lucide.createIcons();
            }, 500);
        }
        
        function closeCustomerModal() {
            document.getElementById('customerModal').classList.add('hidden');
        }
        
        function sendEmail(email) {
            window.location.href = 'mailto:' + email;
        }
        
        function addNote(email) {
            alert('Funcionalidade de notas será implementada');
        }
        
        function exportCustomers() {
            window.location.href = '../api/export_customers.php?store_id=<?= $store_id ?>';
        }
    </script>
</body>
</html>