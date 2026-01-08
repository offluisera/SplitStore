<?php
/**
 * ============================================
 * PAGAMENTOS - CONFIGURAÇÃO DE GATEWAYS
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

// Salvar configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_payment_config') {
    
    $gateway = $_POST['gateway'] ?? '';
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    $api_key = trim($_POST['api_key'] ?? '');
    $api_secret = trim($_POST['api_secret'] ?? '');
    $webhook_url = trim($_POST['webhook_url'] ?? '');
    
    if (empty($gateway)) {
        $message = "Gateway inválido";
        $messageType = "error";
    } else {
        try {
            // Verifica se já existe
            $check = $pdo->prepare("SELECT id FROM integrations WHERE store_id = ? AND service = ?");
            $check->execute([$store_id, $gateway]);
            
            $config = json_encode([
                'enabled' => $enabled,
                'test_mode' => isset($_POST['test_mode']) ? 1 : 0
            ]);
            
            if ($check->fetch()) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE integrations 
                    SET api_key = ?, api_secret = ?, webhook_url = ?, config = ?, status = ?
                    WHERE store_id = ? AND service = ?
                ");
                $stmt->execute([
                    $api_key, $api_secret, $webhook_url, $config,
                    $enabled ? 'active' : 'inactive',
                    $store_id, $gateway
                ]);
            } else {
                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO integrations (store_id, service, api_key, api_secret, webhook_url, config, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $store_id, $gateway, $api_key, $api_secret, $webhook_url, $config,
                    $enabled ? 'active' : 'inactive'
                ]);
            }
            
            $message = "✓ Configurações salvas com sucesso!";
            $messageType = "success";
            
        } catch (PDOException $e) {
            $message = "Erro: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Buscar configurações atuais
$gateways_config = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM integrations WHERE store_id = ?");
    $stmt->execute([$store_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $config_data = json_decode($row['config'] ?? '{}', true);
        $gateways_config[$row['service']] = [
            'enabled' => $row['status'] === 'active',
            'api_key' => $row['api_key'],
            'api_secret' => $row['api_secret'],
            'webhook_url' => $row['webhook_url'],
            'test_mode' => $config_data['test_mode'] ?? 0
        ];
    }
} catch (PDOException $e) {
    error_log("Error loading gateways: " . $e->getMessage());
}

// Definição dos gateways disponíveis
$gateways = [
    'mercadopago' => [
        'name' => 'Mercado Pago',
        'icon' => 'wallet',
        'color' => 'bg-blue-600',
        'description' => 'PIX, Cartão de Crédito, Boleto',
        'docs' => 'https://www.mercadopago.com.br/developers',
        'fields' => ['api_key' => 'Public Key', 'api_secret' => 'Access Token']
    ],
    'stripe' => [
        'name' => 'Stripe',
        'icon' => 'credit-card',
        'color' => 'bg-purple-600',
        'description' => 'Cartão de Crédito Internacional',
        'docs' => 'https://stripe.com/docs',
        'fields' => ['api_key' => 'Publishable Key', 'api_secret' => 'Secret Key']
    ],
    'pagseguro' => [
        'name' => 'PagSeguro',
        'icon' => 'shield',
        'color' => 'bg-yellow-600',
        'description' => 'PIX, Cartão, Boleto',
        'docs' => 'https://dev.pagseguro.uol.com.br',
        'fields' => ['api_key' => 'Email', 'api_secret' => 'Token']
    ],
    'paypal' => [
        'name' => 'PayPal',
        'icon' => 'dollar-sign',
        'color' => 'bg-blue-500',
        'description' => 'Pagamento Internacional',
        'docs' => 'https://developer.paypal.com',
        'fields' => ['api_key' => 'Client ID', 'api_secret' => 'Secret']
    ],
    'picpay' => [
        'name' => 'PicPay',
        'icon' => 'smartphone',
        'color' => 'bg-green-600',
        'description' => 'Carteira Digital',
        'docs' => 'https://developers.picpay.com',
        'fields' => ['api_key' => 'X-PicPay-Token', 'api_secret' => 'X-Seller-Token']
    ],
    'pagarme' => [
        'name' => 'Pagar.me',
        'icon' => 'zap',
        'color' => 'bg-emerald-600',
        'description' => 'PIX, Cartão, Boleto',
        'docs' => 'https://docs.pagar.me',
        'fields' => ['api_key' => 'API Key', 'api_secret' => 'Encryption Key']
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Pagamentos | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #000; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .gateway-card { transition: all 0.3s ease; cursor: pointer; }
        .gateway-card:hover { transform: translateY(-4px); border-color: rgba(220, 38, 38, 0.3); }
        .gateway-card.active { border-color: rgba(34, 197, 94, 0.5); background: rgba(34, 197, 94, 0.05); }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        
        <!-- Header -->
        <header class="mb-12">
            <h1 class="text-3xl font-black italic uppercase tracking-tighter mb-2">
                Gateways de <span class="text-red-600">Pagamento</span>
            </h1>
            <p class="text-zinc-500 text-sm">Configure os métodos de pagamento aceitos na sua loja</p>
        </header>

        <?php if($message): ?>
            <div class="glass border-<?= $messageType === 'success' ? 'green' : 'red' ?>-600/20 text-<?= $messageType === 'success' ? 'green' : 'red' ?>-500 p-5 rounded-2xl mb-8 flex items-center gap-3">
                <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <span class="font-bold"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Info Box -->
        <div class="glass border-blue-600/20 bg-blue-600/5 p-6 rounded-2xl mb-8">
            <div class="flex items-start gap-4">
                <i data-lucide="info" class="w-6 h-6 text-blue-500 flex-shrink-0"></i>
                <div>
                    <h3 class="text-sm font-black uppercase text-blue-500 mb-2">Como Funciona</h3>
                    <ul class="text-xs text-zinc-400 space-y-1 leading-relaxed">
                        <li>• Ative os gateways que deseja usar na sua loja</li>
                        <li>• Configure as credenciais de API de cada provedor</li>
                        <li>• Use o modo de teste para validar antes de ir para produção</li>
                        <li>• As transações serão processadas automaticamente</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Lista de Gateways -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($gateways as $key => $gateway): 
                $config = $gateways_config[$key] ?? ['enabled' => false, 'api_key' => '', 'api_secret' => '', 'webhook_url' => '', 'test_mode' => 0];
            ?>
            <div class="gateway-card glass rounded-3xl p-8 border border-white/5 <?= $config['enabled'] ? 'active' : '' ?>"
                 onclick="openGatewayConfig('<?= $key ?>')">
                
                <!-- Header -->
                <div class="flex items-start justify-between mb-6">
                    <div class="w-14 h-14 <?= $gateway['color'] ?>/20 rounded-2xl flex items-center justify-center">
                        <i data-lucide="<?= $gateway['icon'] ?>" class="w-7 h-7 <?= str_replace('bg-', 'text-', $gateway['color']) ?>"></i>
                    </div>
                    
                    <?php if ($config['enabled']): ?>
                        <span class="flex items-center gap-2 bg-green-500/10 text-green-500 border border-green-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">
                            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                            Ativo
                        </span>
                    <?php else: ?>
                        <span class="bg-zinc-800 text-zinc-500 px-3 py-1 rounded-lg text-xs font-black uppercase">
                            Inativo
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Info -->
                <h3 class="text-xl font-black uppercase mb-2"><?= $gateway['name'] ?></h3>
                <p class="text-sm text-zinc-500 mb-6"><?= $gateway['description'] ?></p>

                <!-- Status -->
                <div class="flex items-center gap-4 pt-6 border-t border-white/5">
                    <?php if ($config['enabled'] && !empty($config['api_key'])): ?>
                        <div class="flex-1">
                            <p class="text-[10px] text-zinc-600 font-black uppercase mb-1">Configurado</p>
                            <p class="text-xs text-green-500 font-bold">✓ Credenciais OK</p>
                        </div>
                    <?php else: ?>
                        <div class="flex-1">
                            <p class="text-[10px] text-zinc-600 font-black uppercase mb-1">Status</p>
                            <p class="text-xs text-yellow-500 font-bold">⚠ Configurar</p>
                        </div>
                    <?php endif; ?>
                    
                    <button type="button" class="bg-white/5 hover:bg-white/10 px-4 py-2 rounded-xl text-xs font-black uppercase transition">
                        Configurar
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </main>

    <!-- Modal: Configurar Gateway -->
    <div id="gatewayModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-sm p-4">
        <div class="glass w-full max-w-2xl p-10 rounded-[3rem] border-red-600/20 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-8">
                <h3 class="text-2xl font-black italic uppercase">
                    Configurar <span class="text-red-600" id="gatewayName">Gateway</span>
                </h3>
                <button onclick="closeGatewayModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5 text-zinc-500"></i>
                </button>
            </div>
            
            <form method="POST" id="gatewayForm" class="space-y-6">
                <input type="hidden" name="action" value="save_payment_config">
                <input type="hidden" name="gateway" id="gatewayKey">

                <!-- Ativar/Desativar -->
                <div class="glass p-6 rounded-2xl border border-white/5">
                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <p class="font-bold mb-1">Ativar Gateway</p>
                            <p class="text-xs text-zinc-500">Permitir pagamentos por este método</p>
                        </div>
                        <input type="checkbox" name="enabled" id="gatewayEnabled" class="w-12 h-6 rounded-full accent-green-600 cursor-pointer">
                    </label>
                </div>

                <!-- Modo de Teste -->
                <div class="glass p-6 rounded-2xl border border-white/5">
                    <label class="flex items-center justify-between cursor-pointer">
                        <div>
                            <p class="font-bold mb-1">Modo de Teste</p>
                            <p class="text-xs text-zinc-500">Use credenciais de sandbox</p>
                        </div>
                        <input type="checkbox" name="test_mode" id="gatewayTestMode" class="w-12 h-6 rounded-full accent-yellow-600 cursor-pointer">
                    </label>
                </div>

                <!-- Credenciais -->
                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-black uppercase text-zinc-500 mb-3 block" id="apiKeyLabel">API Key</label>
                        <input type="text" name="api_key" id="gatewayApiKey"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase text-zinc-500 mb-3 block" id="apiSecretLabel">API Secret</label>
                        <input type="text" name="api_secret" id="gatewayApiSecret"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Webhook URL (Opcional)</label>
                        <input type="text" name="webhook_url" id="gatewayWebhook"
                               placeholder="https://..."
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                        <p class="text-[10px] text-zinc-600 mt-2 ml-1">URL para notificações de pagamento</p>
                    </div>
                </div>

                <!-- Documentação -->
                <div class="glass p-6 rounded-2xl border border-blue-600/20 bg-blue-600/5">
                    <div class="flex items-start gap-3">
                        <i data-lucide="book-open" class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <p class="text-xs font-bold text-blue-500 mb-2">Documentação</p>
                            <a href="#" id="gatewayDocs" target="_blank" class="text-xs text-zinc-400 hover:text-blue-500 transition">
                                Ver documentação oficial →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Botões -->
                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeGatewayModal()" 
                            class="flex-1 bg-zinc-900 hover:bg-zinc-800 py-4 rounded-xl font-black uppercase text-xs transition">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-red-600 hover:bg-red-700 py-4 rounded-xl font-black uppercase text-xs tracking-widest transition shadow-lg shadow-red-600/20 flex items-center justify-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Salvar Configurações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        const gateways = <?= json_encode($gateways) ?>;
        const configs = <?= json_encode($gateways_config) ?>;
        
        function openGatewayConfig(key) {
            const gateway = gateways[key];
            const config = configs[key] || {enabled: false, api_key: '', api_secret: '', webhook_url: '', test_mode: 0};
            
            document.getElementById('gatewayKey').value = key;
            document.getElementById('gatewayName').textContent = gateway.name;
            document.getElementById('gatewayEnabled').checked = config.enabled;
            document.getElementById('gatewayTestMode').checked = config.test_mode;
            document.getElementById('gatewayApiKey').value = config.api_key || '';
            document.getElementById('gatewayApiSecret').value = config.api_secret || '';
            document.getElementById('gatewayWebhook').value = config.webhook_url || '';
            
            document.getElementById('apiKeyLabel').textContent = gateway.fields.api_key;
            document.getElementById('apiSecretLabel').textContent = gateway.fields.api_secret;
            document.getElementById('gatewayDocs').href = gateway.docs;
            
            document.getElementById('gatewayModal').classList.remove('hidden');
            lucide.createIcons();
        }
        
        function closeGatewayModal() {
            document.getElementById('gatewayModal').classList.add('hidden');
        }
        
        document.getElementById('gatewayModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeGatewayModal();
        });
    </script>
</body>
</html>