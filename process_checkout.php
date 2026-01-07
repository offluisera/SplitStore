<?php
/**
 * ============================================
 * SPLITSTORE - PROCESSAR CHECKOUT (CORRIGIDO)
 * ============================================
 * Fluxo: Seus Dados → Configuração → Pagamento
 * Versão: 2.0 - Com logs detalhados
 */

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Função de log para debug
function logCheckout($message, $data = null) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/checkout_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        $logMessage .= "\n" . print_r($data, true);
    }
    
    $logMessage .= "\n" . str_repeat('-', 80) . "\n";
    
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

logCheckout("🚀 INÍCIO DO PROCESSO DE CHECKOUT");

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logCheckout("❌ Método não permitido: " . $_SERVER['REQUEST_METHOD']);
    header('Location: index.php');
    exit;
}

require_once 'includes/db.php';

logCheckout("✓ Banco de dados conectado");

// ========================================
// 1. CAPTURAR DADOS DO FORMULÁRIO
// ========================================

$errors = [];
$old_data = [];

// ETAPA 1: Dados Pessoais
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
$phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';

// ETAPA 2: Configuração da Loja
$store_name = trim($_POST['store_name'] ?? '');
$store_slug = strtolower(preg_replace('/[^a-z0-9-]/', '', $_POST['store_slug'] ?? ''));
$payment_method = $_POST['payment_method'] ?? 'pix';

// Plano e Ciclo
$plan = $_POST['plan'] ?? 'basic';
$billing_cycle = $_POST['billing_cycle'] ?? 'monthly';

// Termos
$terms = isset($_POST['terms']);

// Salva para repopular em caso de erro
$old_data = compact('first_name', 'last_name', 'email', 'cpf', 'phone', 'store_name', 'store_slug');

logCheckout("📝 Dados recebidos do formulário", [
    'first_name' => $first_name,
    'last_name' => $last_name,
    'email' => $email,
    'cpf_length' => strlen($cpf),
    'phone_length' => strlen($phone),
    'store_name' => $store_name,
    'store_slug' => $store_slug,
    'plan' => $plan,
    'billing_cycle' => $billing_cycle,
    'payment_method' => $payment_method,
    'terms_accepted' => $terms ? 'SIM' : 'NÃO'
]);

// ========================================
// 2. VALIDAÇÕES
// ========================================

logCheckout("🔍 Iniciando validações...");

// Validar campos obrigatórios
if (empty($first_name)) {
    $errors[] = "Nome é obrigatório";
    logCheckout("❌ Nome vazio");
}

if (empty($last_name)) {
    $errors[] = "Sobrenome é obrigatório";
    logCheckout("❌ Sobrenome vazio");
}

// Validar e-mail
$email_validated = filter_var($email, FILTER_VALIDATE_EMAIL);
if (!$email_validated) {
    $errors[] = "E-mail inválido";
    logCheckout("❌ E-mail inválido: " . $email);
} else {
    $email = $email_validated;
}

// Validar CPF
if (strlen($cpf) !== 11) {
    $errors[] = "CPF inválido (deve ter 11 dígitos)";
    logCheckout("❌ CPF inválido. Comprimento: " . strlen($cpf));
}

// Validar telefone
if (strlen($phone) < 10) {
    $errors[] = "Telefone inválido (mínimo 10 dígitos)";
    logCheckout("❌ Telefone inválido. Comprimento: " . strlen($phone));
}

// Validar loja
if (empty($store_name)) {
    $errors[] = "Nome da loja é obrigatório";
    logCheckout("❌ Nome da loja vazio");
}

if (empty($store_slug) || strlen($store_slug) < 3) {
    $errors[] = "URL da loja deve ter no mínimo 3 caracteres";
    logCheckout("❌ Store slug inválido: " . $store_slug);
}

// Validar senha
if (strlen($password) < 8) {
    $errors[] = "Senha deve ter no mínimo 8 caracteres";
    logCheckout("❌ Senha muito curta. Comprimento: " . strlen($password));
}

// Validar termos
if (!$terms) {
    $errors[] = "Você deve aceitar os termos de serviço";
    logCheckout("❌ Termos não aceitos");
}

// Verificar e-mail duplicado
if (empty($errors) && $email) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM stores WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Este e-mail já está cadastrado";
            logCheckout("❌ E-mail duplicado: " . $email);
        }
    } catch (PDOException $e) {
        error_log("Error checking email: " . $e->getMessage());
        logCheckout("❌ Erro ao verificar e-mail", ['error' => $e->getMessage()]);
        $errors[] = "Erro ao verificar e-mail. Tente novamente.";
    }
}

// Verificar slug duplicado
if (empty($errors) && $store_slug) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM stores WHERE store_slug = ?");
        $stmt->execute([$store_slug]);
        if ($stmt->fetch()) {
            $suggested_slug = $store_slug . "-" . rand(100, 999);
            $errors[] = "Esta URL de loja já está em uso. Tente: " . $suggested_slug;
            logCheckout("❌ Store slug duplicado: " . $store_slug);
        }
    } catch (PDOException $e) {
        error_log("Error checking slug: " . $e->getMessage());
        logCheckout("❌ Erro ao verificar slug", ['error' => $e->getMessage()]);
        $errors[] = "Erro ao verificar URL. Tente novamente.";
    }
}

// Se houver erros, volta para o checkout
if (!empty($errors)) {
    logCheckout("❌ Validação falhou. Total de erros: " . count($errors), $errors);
    $_SESSION['checkout_errors'] = $errors;
    $_SESSION['checkout_data'] = $old_data;
    header('Location: checkout.php?plan=' . urlencode($plan));
    exit;
}

logCheckout("✓ Todas as validações passaram");

// ========================================
// 3. CONFIGURAÇÃO DO PLANO
// ========================================

$plans_config = [
    'basic' => [
        'name' => 'Starter',
        'price_monthly' => 14.99,
        'price_annual' => 149.99
    ],
    'pro' => [
        'name' => 'Enterprise',
        'price_monthly' => 25.99,
        'price_annual' => 259.99
    ],
    'ultra' => [
        'name' => 'Gerencial',
        'price_monthly' => 39.99,
        'price_annual' => 399.99
    ]
];

if (!isset($plans_config[$plan])) {
    logCheckout("❌ Plano inválido: " . $plan);
    header('Location: index.php');
    exit;
}

$plan_config = $plans_config[$plan];
$amount = $billing_cycle === 'annual' ? $plan_config['price_annual'] : $plan_config['price_monthly'];

logCheckout("💰 Plano selecionado", [
    'plan' => $plan,
    'name' => $plan_config['name'],
    'billing_cycle' => $billing_cycle,
    'amount' => $amount
]);

// ========================================
// 4. GERAR CREDENCIAIS API
// ========================================

function generateApiKey() {
    return 'ca_' . bin2hex(random_bytes(16));
}

function generateApiSecret() {
    return 'ck_' . bin2hex(random_bytes(24));
}

$api_key = generateApiKey();
$api_secret = generateApiSecret();

logCheckout("🔑 Credenciais geradas", [
    'api_key' => $api_key,
    'api_secret_preview' => substr($api_secret, 0, 20) . '...'
]);

// ========================================
// 5. CRIAR LOJA NO BANCO
// ========================================

logCheckout("💾 Iniciando inserção no banco de dados...");

try {
    $pdo->beginTransaction();
    
    logCheckout("✓ Transação iniciada");
    
    // Hash da senha
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    logCheckout("✓ Senha hasheada");
    
    // Insere a loja
    $stmt = $pdo->prepare("
        INSERT INTO stores (
            store_name, 
            store_slug,
            owner_name, 
            email, 
            cpf,
            phone,
            password_hash,
            plan,
            billing_cycle,
            api_key,
            api_secret,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $full_name = $first_name . ' ' . $last_name;
    
    $insertData = [
        $store_name,
        $store_slug,
        $full_name,
        $email,
        $cpf,
        $phone,
        $password_hash,
        $plan,
        $billing_cycle,
        $api_key,
        $api_secret
    ];
    
    logCheckout("📤 Executando INSERT na tabela stores", [
        'store_name' => $store_name,
        'store_slug' => $store_slug,
        'owner_name' => $full_name,
        'email' => $email,
        'plan' => $plan
    ]);
    
    $stmt->execute($insertData);
    
    $store_id = $pdo->lastInsertId();
    
    logCheckout("✓ Loja inserida com sucesso. ID: " . $store_id);
    
    // Cria transação
    $stmt = $pdo->prepare("
        INSERT INTO transactions (
            store_id,
            amount,
            payment_method,
            status,
            created_at
        ) VALUES (?, ?, ?, 'pending', NOW())
    ");
    
    logCheckout("📤 Executando INSERT na tabela transactions");
    
    $stmt->execute([$store_id, $amount, $payment_method]);
    $transaction_id = $pdo->lastInsertId();
    
    logCheckout("✓ Transação criada. ID: " . $transaction_id);
    
    // ========================================
    // 6. GERAR QR CODE PIX
    // ========================================
    
    logCheckout("🔲 Gerando QR Code PIX...");
    
    // Código PIX simulado (substituir por integração real)
    $qr_code_text = "00020126580014br.gov.bcb.pix0136" . 
                    str_replace('-', '', $api_key) . 
                    "520400005303986540" . 
                    str_pad(number_format($amount, 2, '', ''), 10, '0', STR_PAD_LEFT) . 
                    "5802BR5925SPLITSTORE6009SAOPAULO62070503***6304";
    
    // QR Code Image
    $qr_code_image = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . urlencode($qr_code_text);
    
    // Validade: 30 minutos
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    // Atualiza transação com dados do PIX
    $gateway_id = 'SPLIT_' . time() . '_' . $transaction_id;
    
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET gateway_transaction_id = ?,
            qr_code = ?,
            qr_code_text = ?,
            expires_at = ?
        WHERE id = ?
    ");
    
    logCheckout("📤 Atualizando transação com dados do PIX");
    
    $stmt->execute([
        $gateway_id, 
        $qr_code_image, 
        $qr_code_text, 
        $expires_at, 
        $transaction_id
    ]);
    
    logCheckout("✓ Transação atualizada com QR Code");
    
    $pdo->commit();
    
    logCheckout("✓ Transação do banco COMMITADA com sucesso");
    
    // ========================================
    // 7. SALVAR NA SESSÃO
    // ========================================
    
    $_SESSION['store_data'] = [
        'id' => $store_id,
        'name' => $store_name,
        'slug' => $store_slug,
        'email' => $email,
        'plan' => $plan_config['name'],
        'api_key' => $api_key,
        'api_secret' => $api_secret
    ];
    
    $_SESSION['payment_data'] = [
        'transaction_id' => $transaction_id,
        'amount' => $amount,
        'qr_code' => $qr_code_image,
        'qr_code_text' => $qr_code_text,
        'expires_at' => $expires_at,
        'gateway_id' => $gateway_id
    ];
    
    logCheckout("✓ Dados salvos na sessão", [
        'store_id' => $store_id,
        'transaction_id' => $transaction_id,
        'amount' => $amount
    ]);
    
    // Limpa erros
    unset($_SESSION['checkout_errors'], $_SESSION['checkout_data']);
    
    logCheckout("🎉 PROCESSO CONCLUÍDO COM SUCESSO!");
    logCheckout("🔄 Redirecionando para: payment.php");
    
    // Redireciona para página de pagamento
    header('Location: payment.php');
    exit;
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        logCheckout("⚠️ Transação revertida (ROLLBACK)");
    }
    
    $errorDetails = [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    
    error_log("Checkout Error: " . $e->getMessage());
    logCheckout("❌ ERRO FATAL NO BANCO DE DADOS", $errorDetails);
    
    $_SESSION['checkout_errors'] = [
        "Erro ao processar pedido. Tente novamente.",
        "Código: " . $e->getCode()
    ];
    $_SESSION['checkout_data'] = $old_data;
    
    logCheckout("🔄 Redirecionando de volta para checkout.php com erros");
    
    header('Location: checkout.php?plan=' . urlencode($plan));
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        logCheckout("⚠️ Transação revertida (ROLLBACK)");
    }
    
    error_log("Checkout General Error: " . $e->getMessage());
    logCheckout("❌ ERRO GERAL NO PROCESSO", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    $_SESSION['checkout_errors'] = ["Erro inesperado. Por favor, tente novamente."];
    $_SESSION['checkout_data'] = $old_data;
    
    header('Location: checkout.php?plan=' . urlencode($plan));
    exit;
}
?>