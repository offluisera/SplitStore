<?php
/**
 * ============================================
 * PROCESS CHECKOUT V2.0
 * ============================================
 * - Cria login automático ao preencher dados
 * - Salva carrinho no banco
 * - Cria fatura ao invés de transaction
 * - Permite voltar e retomar
 */

session_start();
require_once 'includes/db.php';

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$errors = [];
$old_data = [];

// ========================================
// 1. CAPTURAR DADOS
// ========================================

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
$phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';

$store_name = trim($_POST['store_name'] ?? '');
$store_slug = strtolower(preg_replace('/[^a-z0-9-]/', '', $_POST['store_slug'] ?? ''));
$payment_method = $_POST['payment_method'] ?? 'pix';

$plan = $_POST['plan'] ?? 'basic';
$billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
$terms = isset($_POST['terms']);

$old_data = compact('first_name', 'last_name', 'email', 'cpf', 'phone', 'store_name', 'store_slug');

// ========================================
// 2. VALIDAÇÕES (simplificado)
// ========================================

if (empty($first_name) || empty($last_name)) {
    $errors[] = "Nome completo é obrigatório";
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "E-mail inválido";
}

if (strlen($cpf) !== 11) {
    $errors[] = "CPF inválido";
}

if (strlen($phone) < 10) {
    $errors[] = "Telefone inválido";
}

if (empty($store_name) || strlen($store_slug) < 3) {
    $errors[] = "Nome e URL da loja são obrigatórios";
}

if (strlen($password) < 8) {
    $errors[] = "Senha deve ter no mínimo 8 caracteres";
}

if (!$terms) {
    $errors[] = "Aceite os termos de serviço";
}

// Verifica e-mail duplicado
if (empty($errors)) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM stores WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Este e-mail já está cadastrado";
        }
    } catch (Exception $e) {
        $errors[] = "Erro ao verificar e-mail";
    }
}

// Verifica slug duplicado
if (empty($errors)) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM stores WHERE store_slug = ?");
        $stmt->execute([$store_slug]);
        if ($stmt->fetch()) {
            $errors[] = "Esta URL já está em uso. Tente: " . $store_slug . "-" . rand(100, 999);
        }
    } catch (Exception $e) {
        $errors[] = "Erro ao verificar URL";
    }
}

// Se houver erros, volta
if (!empty($errors)) {
    $_SESSION['checkout_errors'] = $errors;
    $_SESSION['checkout_data'] = $old_data;
    header('Location: checkout.php?plan=' . urlencode($plan));
    exit;
}

// ========================================
// 3. CONFIGURAÇÃO DE PLANO
// ========================================

$plans = [
    'basic' => ['name' => 'Starter', 'monthly' => 14.99, 'annual' => 149.99],
    'pro' => ['name' => 'Enterprise', 'monthly' => 25.99, 'annual' => 259.99],
    'ultra' => ['name' => 'Gerencial', 'monthly' => 39.99, 'annual' => 399.99]
];

if (!isset($plans[$plan])) {
    header('Location: index.php');
    exit;
}

$plan_config = $plans[$plan];
$amount = $billing_cycle === 'annual' ? $plan_config['annual'] : $plan_config['monthly'];

// ========================================
// 4. GERAR CREDENCIAIS
// ========================================

$api_key = 'ca_' . bin2hex(random_bytes(16));
$api_secret = 'ck_' . bin2hex(random_bytes(24));

// ========================================
// 5. CRIAR LOJA NO BANCO
// ========================================

try {
    $pdo->beginTransaction();
    
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $full_name = $first_name . ' ' . $last_name;
    
    // INSERT na tabela stores
    $stmt = $pdo->prepare("
        INSERT INTO stores (
            store_name, store_slug, owner_name, email, cpf, phone,
            password_hash, plan, billing_cycle, api_key, api_secret,
            status, access_level, first_payment_confirmed, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'restricted', 0, NOW())
    ");
    
    $stmt->execute([
        $store_name, $store_slug, $full_name, $email, $cpf, $phone,
        $password_hash, $plan, $billing_cycle, $api_key, $api_secret
    ]);
    
    $store_id = $pdo->lastInsertId();
    
    // ========================================
    // 6. CRIAR FATURA (ao invés de transaction)
    // ========================================
    
    $invoice_number = 'INV-' . date('Ym') . '-' . str_pad($store_id, 5, '0', STR_PAD_LEFT);
    $due_date = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    // Gera QR Code PIX simulado
    $qr_code_text = "00020126580014br.gov.bcb.pix0136" . str_replace('-', '', $api_key) . 
                    "520400005303986540" . str_pad(number_format($amount, 2, '', ''), 10, '0', STR_PAD_LEFT) . 
                    "5802BR5925SPLITSTORE6304";
    
    $qr_code_image = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . urlencode($qr_code_text);
    
    $gateway_id = 'SPLIT_' . time() . '_' . $store_id;
    
    $stmt = $pdo->prepare("
        INSERT INTO invoices (
            store_id, invoice_number, type, amount, description,
            status, payment_method, qr_code, qr_code_text,
            gateway_transaction_id, due_date, created_at
        ) VALUES (?, ?, 'subscription', ?, ?, 'pending', ?, ?, ?, ?, ?, NOW())
    ");
    
    $description = "Plano {$plan_config['name']} - " . ($billing_cycle === 'annual' ? 'Anual' : 'Mensal');
    
    $stmt->execute([
        $store_id, $invoice_number, $amount, $description,
        $payment_method, $qr_code_image, $qr_code_text,
        $gateway_id, $due_date
    ]);
    
    $invoice_id = $pdo->lastInsertId();
    
    // ========================================
    // 7. LOGIN AUTOMÁTICO (IMPORTANTE!)
    // ========================================
    
    $_SESSION['store_logged'] = true;
    $_SESSION['store_id'] = $store_id;
    $_SESSION['store_name'] = $store_name;
    $_SESSION['store_plan'] = $plan;
    $_SESSION['store_slug'] = $store_slug;
    $_SESSION['owner_name'] = $full_name;
    
    // Salva dados de pagamento
    $_SESSION['payment_data'] = [
        'invoice_id' => $invoice_id,
        'amount' => $amount,
        'qr_code' => $qr_code_image,
        'qr_code_text' => $qr_code_text,
        'expires_at' => $due_date,
        'gateway_id' => $gateway_id
    ];
    
    $_SESSION['store_data'] = [
        'id' => $store_id,
        'name' => $store_name,
        'slug' => $store_slug,
        'email' => $email,
        'plan' => $plan_config['name'],
        'api_key' => $api_key,
        'api_secret' => $api_secret
    ];
    
    $pdo->commit();
    
    // Limpa erros
    unset($_SESSION['checkout_errors'], $_SESSION['checkout_data']);
    
    // ========================================
    // 8. REDIRECIONA PARA PAGAMENTO
    // ========================================
    
    header('Location: payment.php');
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Checkout Error: " . $e->getMessage());
    
    $_SESSION['checkout_errors'] = ["Erro ao processar. Tente novamente."];
    $_SESSION['checkout_data'] = $old_data;
    
    header('Location: checkout.php?plan=' . urlencode($plan));
    exit;
}
?>