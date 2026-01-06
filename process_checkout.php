<?php
/**
 * ============================================
 * SPLITSTORE - PROCESSAR CHECKOUT V3.1
 * ============================================
 * Versão com debug e tratamento robusto de erros
 */

session_start();

// Ativar logs de erro temporariamente
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log de debug
function debugLog($message, $data = null) {
    $logFile = __DIR__ . '/logs/checkout_debug.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        $logMessage .= ": " . json_encode($data);
    }
    
    $logMessage .= PHP_EOL;
    
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

debugLog("=== INÍCIO DO PROCESSAMENTO ===");

// Apenas requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("Método inválido", ['method' => $_SERVER['REQUEST_METHOD']]);
    header('Location: index.php');
    exit;
}

debugLog("Método POST confirmado");

// Conectar banco de dados
try {
    require_once 'includes/db.php';
    debugLog("Conexão com banco OK");
} catch (Exception $e) {
    debugLog("ERRO ao conectar banco", ['error' => $e->getMessage()]);
    $_SESSION['checkout_errors'] = ["Erro de conexão com banco de dados. Tente novamente."];
    $_SESSION['checkout_data'] = $_POST;
    header('Location: checkout.php?plan=' . urlencode($_POST['plan'] ?? 'basic'));
    exit;
}

// ========================================
// 1. CAPTURAR E VALIDAR DADOS
// ========================================

$errors = [];
$old_data = [];

// Dados Pessoais
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
$phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');

debugLog("Dados recebidos", [
    'first_name' => $first_name,
    'last_name' => $last_name,
    'email' => $email,
    'cpf' => substr($cpf, 0, 3) . '***',
    'phone' => substr($phone, 0, 3) . '***'
]);

// Dados da Loja
$store_name = trim($_POST['store_name'] ?? '');
$store_slug = strtolower(preg_replace('/[^a-z0-9-]/', '', $_POST['store_slug'] ?? ''));

// Senha
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// Plano e Ciclo
$plan = $_POST['plan'] ?? 'basic';
$billing_cycle = $_POST['billing_cycle'] ?? 'monthly';

// Termos
$terms = isset($_POST['terms']);

// Salva dados para repopular
$old_data = compact('first_name', 'last_name', 'email', 'cpf', 'phone', 'store_name', 'store_slug');

// ========================================
// 2. VALIDAÇÕES
// ========================================

debugLog("Iniciando validações");

if (empty($first_name)) {
    $errors[] = "Nome é obrigatório";
    debugLog("Erro: Nome vazio");
}

if (empty($last_name)) {
    $errors[] = "Sobrenome é obrigatório";
    debugLog("Erro: Sobrenome vazio");
}

// Validar e-mail
$email = filter_var($email, FILTER_VALIDATE_EMAIL);
if (!$email) {
    $errors[] = "E-mail inválido";
    debugLog("Erro: E-mail inválido", ['email_input' => $_POST['email'] ?? '']);
}

if (strlen($cpf) !== 11) {
    $errors[] = "CPF inválido (deve ter 11 dígitos)";
    debugLog("Erro: CPF inválido", ['length' => strlen($cpf)]);
}

if (strlen($phone) < 10) {
    $errors[] = "Telefone inválido (mínimo 10 dígitos)";
    debugLog("Erro: Telefone inválido", ['length' => strlen($phone)]);
}

if (empty($store_name)) {
    $errors[] = "Nome da loja é obrigatório";
    debugLog("Erro: Nome da loja vazio");
}

if (empty($store_slug)) {
    $errors[] = "URL da loja é obrigatória";
    debugLog("Erro: Slug vazio");
} elseif (strlen($store_slug) < 3) {
    $errors[] = "URL da loja deve ter no mínimo 3 caracteres";
    debugLog("Erro: Slug muito curto", ['length' => strlen($store_slug)]);
}

if (strlen($password) < 8) {
    $errors[] = "Senha deve ter no mínimo 8 caracteres";
    debugLog("Erro: Senha curta", ['length' => strlen($password)]);
}

if ($password !== $password_confirm) {
    $errors[] = "As senhas não coincidem";
    debugLog("Erro: Senhas não coincidem");
}

if (!$terms) {
    $errors[] = "Você deve aceitar os termos de serviço";
    debugLog("Erro: Termos não aceitos");
}

debugLog("Validações concluídas", ['total_errors' => count($errors)]);

// Verifica se e-mail já existe
if (empty($errors) && $email) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM stores WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Este e-mail já está cadastrado";
            debugLog("Erro: E-mail duplicado", ['email' => $email]);
        }
    } catch (PDOException $e) {
        debugLog("Erro ao verificar e-mail", ['error' => $e->getMessage()]);
        $errors[] = "Erro ao verificar e-mail. Tente novamente.";
    }
}

// Verifica se slug já existe
if (empty($errors) && $store_slug) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM stores WHERE store_slug = ?");
        $stmt->execute([$store_slug]);
        if ($stmt->fetch()) {
            $errors[] = "Esta URL de loja já está em uso. Tente: " . $store_slug . "-" . rand(100, 999);
            debugLog("Erro: Slug duplicado", ['slug' => $store_slug]);
        }
    } catch (PDOException $e) {
        debugLog("Erro ao verificar slug", ['error' => $e->getMessage()]);
        $errors[] = "Erro ao verificar URL da loja. Tente novamente.";
    }
}

// Se houver erros, volta pro checkout
if (!empty($errors)) {
    debugLog("Retornando ao checkout com erros", ['errors' => $errors]);
    $_SESSION['checkout_errors'] = $errors;
    $_SESSION['checkout_data'] = $old_data;
    header('Location: checkout.php?plan=' . urlencode($plan));
    exit;
}

debugLog("Todas as validações passaram");

// ========================================
// 3. DEFINIR VALORES DO PLANO
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
    debugLog("Plano inválido", ['plan' => $plan]);
    header('Location: index.php');
    exit;
}

$plan_config = $plans_config[$plan];
$amount = $billing_cycle === 'annual' ? $plan_config['price_annual'] : $plan_config['price_monthly'];

debugLog("Plano selecionado", [
    'plan' => $plan,
    'cycle' => $billing_cycle,
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

debugLog("Credenciais geradas", [
    'api_key' => substr($api_key, 0, 10) . '...',
    'api_secret' => substr($api_secret, 0, 10) . '...'
]);

// ========================================
// 5. CRIAR LOJA E TRANSAÇÃO
// ========================================

try {
    debugLog("Iniciando transação no banco");
    $pdo->beginTransaction();
    
    // Hash da senha
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    debugLog("Senha hasheada");
    
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
    
    $result = $stmt->execute([
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
    ]);
    
    if (!$result) {
        throw new Exception("Falha ao inserir loja no banco");
    }
    
    $store_id = $pdo->lastInsertId();
    debugLog("Loja criada com sucesso", ['store_id' => $store_id]);
    
    // Cria transação
    $stmt = $pdo->prepare("
        INSERT INTO transactions (
            store_id,
            amount,
            payment_method,
            status,
            created_at
        ) VALUES (?, ?, 'pix', 'pending', NOW())
    ");
    
    $result = $stmt->execute([$store_id, $amount]);
    
    if (!$result) {
        throw new Exception("Falha ao criar transação");
    }
    
    $transaction_id = $pdo->lastInsertId();
    debugLog("Transação criada", ['transaction_id' => $transaction_id]);
    
    // ========================================
    // 6. GERAR QR CODE PIX (SIMULADO)
    // ========================================
    
    debugLog("Gerando QR Code PIX");
    
    // Código PIX simulado
    $qr_code_text = "00020126580014br.gov.bcb.pix0136" . 
                    str_replace('-', '', $api_key) . 
                    "520400005303986540" . 
                    str_pad(number_format($amount, 2, '', ''), 10, '0', STR_PAD_LEFT) . 
                    "5802BR5925SPLITSTORE6009SAOPAULO62070503***6304";
    
    // QR Code Image via serviço público
    $qr_code_image = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . urlencode($qr_code_text);
    
    // Validade: 30 minutos
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    debugLog("QR Code gerado", [
        'expires_at' => $expires_at,
        'code_length' => strlen($qr_code_text)
    ]);
    
    // Atualiza transação com dados do PIX
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET gateway_transaction_id = ?,
            qr_code = ?,
            qr_code_text = ?,
            expires_at = ?
        WHERE id = ?
    ");
    
    $gateway_id = 'TEST_' . uniqid() . '_' . $transaction_id;
    
    $result = $stmt->execute([
        $gateway_id, 
        $qr_code_image, 
        $qr_code_text, 
        $expires_at, 
        $transaction_id
    ]);
    
    if (!$result) {
        throw new Exception("Falha ao atualizar transação com dados do PIX");
    }
    
    debugLog("Transação atualizada com PIX", ['gateway_id' => $gateway_id]);
    
    $pdo->commit();
    debugLog("Transação do banco commitada com sucesso");
    
    // ========================================
    // 7. SALVAR NA SESSÃO
    // ========================================
    
    $_SESSION['store_data'] = [
        'id' => $store_id,
        'name' => $store_name,
        'email' => $email,
        'plan' => $plan_config['name'],
        'transaction_id' => $transaction_id,
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
    
    debugLog("Dados salvos na sessão");
    
    // Log de sucesso no banco de atividades
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (store_id, action, description, created_at)
            VALUES (?, 'checkout_completed', ?, NOW())
        ");
        $stmt->execute([
            $store_id,
            "Checkout concluído para o plano {$plan_config['name']} - R$ " . number_format($amount, 2, ',', '.')
        ]);
    } catch (Exception $e) {
        debugLog("Erro ao salvar log de atividade (não crítico)", ['error' => $e->getMessage()]);
    }
    
    debugLog("=== PROCESSAMENTO CONCLUÍDO COM SUCESSO ===");
    
    // Limpa erros anteriores
    unset($_SESSION['checkout_errors'], $_SESSION['checkout_data']);
    
    // Redireciona para página de pagamento
    header('Location: payment.php');
    exit;
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        debugLog("Rollback executado");
    }
    
    debugLog("ERRO PDO", [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    error_log("Checkout PDO Error: " . $e->getMessage());
    
    $_SESSION['checkout_errors'] = [
        "Erro ao processar pedido. Tente novamente.",
        "Detalhes técnicos: " . $e->getMessage()
    ];
    $_SESSION['checkout_data'] = $old_data;
    
    header('Location: checkout.php?plan=' . urlencode($plan));
    exit;
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        debugLog("Rollback executado");
    }
    
    debugLog("ERRO GERAL", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    error_log("Checkout Error: " . $e->getMessage());
    
    $_SESSION['checkout_errors'] = [
        "Erro ao processar pedido. Tente novamente.",
        "Detalhes: " . $e->getMessage()
    ];
    $_SESSION['checkout_data'] = $old_data;
    
    header('Location: checkout.php?plan=' . urlencode($plan));
    exit;
}
?>