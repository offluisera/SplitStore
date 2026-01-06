<?php
/**
 * ============================================
 * SPLITSTORE - PROCESSAR CHECKOUT 3 ETAPAS
 * ============================================
 * Fluxo: Seus Dados → Configuração → Pagamento
 */

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_once 'includes/db.php';

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

// ========================================
// 2. VALIDAÇÕES
// ========================================

// Validar campos obrigatórios
if (empty($first_name)) $errors[] = "Nome é obrigatório";
if (empty($last_name)) $errors[] = "Sobrenome é obrigatório";

// Validar e-mail
$email = filter_var($email, FILTER_VALIDATE_EMAIL);
if (!$email) $errors[] = "E-mail inválido";

// Validar CPF
if (strlen($cpf) !== 11) {
    $errors[] = "CPF inválido (deve ter 11 dígitos)";
}

// Validar telefone
if (strlen($phone) < 10) {
    $errors[] = "Telefone inválido (mínimo 10 dígitos)";
}

// Validar loja
if (empty($store_name)) {
    $errors[] = "Nome da loja é obrigatório";
}

if (empty($store_slug) || strlen($store_slug) < 3) {
    $errors[] = "URL da loja deve ter no mínimo 3 caracteres";
}

// Validar senha
if (strlen($password) < 8) {
    $errors[] = "Senha deve ter no mínimo 8 caracteres";
}

// Validar termos
if (!$terms) {
    $errors[] = "Você deve aceitar os termos de serviço";
}

// Verificar e-mail duplicado
if (empty($errors) && $email) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM stores WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Este e-mail já está cadastrado";
        }
    } catch (PDOException $e) {
        error_log("Error checking email: " . $e->getMessage());
        $errors[] = "Erro ao verificar e-mail. Tente novamente.";
    }
}

// Verificar slug duplicado
if (empty($errors) && $store_slug) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM stores WHERE store_slug = ?");
        $stmt->execute([$store_slug]);
        if ($stmt->fetch()) {
            $errors[] = "Esta URL de loja já está em uso. Tente: " . $store_slug . "-" . rand(100, 999);
        }
    } catch (PDOException $e) {
        error_log("Error checking slug: " . $e->getMessage());
        $errors[] = "Erro ao verificar URL. Tente novamente.";
    }
}

// Se houver erros, volta para o checkout
if (!empty($errors)) {
    $_SESSION['checkout_errors'] = $errors;
    $_SESSION['checkout_data'] = $old_data;
    header('Location: checkout.php?plan=' . urlencode($plan));
    exit;
}

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
    header('Location: index.php');
    exit;
}

$plan_config = $plans_config[$plan];
$amount = $billing_cycle === 'annual' ? $plan_config['price_annual'] : $plan_config['price_monthly'];

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

// ========================================
// 5. CRIAR LOJA NO BANCO
// ========================================

try {
    $pdo->beginTransaction();
    
    // Hash da senha
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
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
    
    $stmt->execute([
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
    
    $store_id = $pdo->lastInsertId();
    
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
    
    $stmt->execute([$store_id, $amount, $payment_method]);
    $transaction_id = $pdo->lastInsertId();
    
    // ========================================
    // 6. GERAR QR CODE PIX
    // ========================================
    
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
    
    $stmt->execute([
        $gateway_id, 
        $qr_code_image, 
        $qr_code_text, 
        $expires_at, 
        $transaction_id
    ]);
    
    $pdo->commit();
    
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
    
    // Limpa erros
    unset($_SESSION['checkout_errors'], $_SESSION['checkout_data']);
    
    // Redireciona para página de pagamento
    header('Location: payment.php');
    exit;
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
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
