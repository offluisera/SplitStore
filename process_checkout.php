<?php
/**
 * ============================================
 * SPLITSTORE - PROCESSAR CHECKOUT
 * ============================================
 * Cria loja, gera PIX e redireciona para pagamento
 */

session_start();
require_once 'includes/db.php';

// Apenas requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// ========================================
// 1. VALIDAÇÃO DOS DADOS
// ========================================

$errors = [];
$old_data = [];

// Dados Pessoais
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
$phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');

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

// Salva dados para repopular em caso de erro
$old_data = compact('first_name', 'last_name', 'email', 'cpf', 'phone', 'store_name', 'store_slug');

// Validações
if (empty($first_name)) $errors[] = "Nome é obrigatório";
if (empty($last_name)) $errors[] = "Sobrenome é obrigatório";
if (!$email) $errors[] = "E-mail inválido";
if (strlen($cpf) !== 11) $errors[] = "CPF inválido";
if (strlen($phone) < 10) $errors[] = "Telefone inválido";
if (empty($store_name)) $errors[] = "Nome da loja é obrigatório";
if (empty($store_slug)) $errors[] = "URL da loja é obrigatória";
if (strlen($password) < 8) $errors[] = "Senha deve ter no mínimo 8 caracteres";
if ($password !== $password_confirm) $errors[] = "As senhas não coincidem";
if (!$terms) $errors[] = "Você deve aceitar os termos de serviço";

// Verifica se e-mail já existe
try {
    $stmt = $pdo->prepare("SELECT id FROM stores WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = "Este e-mail já está cadastrado";
    }
} catch (PDOException $e) {
    error_log("Error checking email: " . $e->getMessage());
}

// Verifica se slug já existe
try {
    $stmt = $pdo->prepare("SELECT id FROM stores WHERE store_slug = ?");
    $stmt->execute([$store_slug]);
    if ($stmt->fetch()) {
        $errors[] = "Esta URL de loja já está em uso";
    }
} catch (PDOException $e) {
    error_log("Error checking slug: " . $e->getMessage());
}

// Se houver erros, volta pro checkout
if (!empty($errors)) {
    $_SESSION['checkout_errors'] = $errors;
    $_SESSION['checkout_data'] = $old_data;
    header('Location: checkout.php?plan=' . urlencode($plan));
    exit;
}

// ========================================
// 2. DEFINIR VALORES DO PLANO
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
// 3. GERAR CREDENCIAIS API
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
// 4. CRIAR LOJA (STATUS: PENDING)
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
    
    // ========================================
    // 5. CRIAR TRANSAÇÃO
    // ========================================
    
    $stmt = $pdo->prepare("
        INSERT INTO transactions (
            store_id,
            amount,
            payment_method,
            status,
            created_at
        ) VALUES (?, ?, 'pix', 'pending', NOW())
    ");
    
    $stmt->execute([$store_id, $amount]);
    $transaction_id = $pdo->lastInsertId();
    
    // ========================================
    // 6. GERAR QR CODE PIX (SIMULAÇÃO)
    // ========================================
    
    // NOTA: Aqui você deve integrar com o gateway real (MisticPay, Mercado Pago, etc)
    // Por enquanto, vamos simular a resposta
    
    // Código PIX simulado (na produção, vem do gateway)
    $qr_code_text = "00020126580014br.gov.bcb.pix0136" . $api_key . "520400005303986540" . number_format($amount, 2, '', '') . "5802BR5925SPLITSTORE6009SAO PAULO62070503***6304";
    
    // QR Code Image (na produção, vem do gateway)
    // Para teste, vamos usar um serviço público de geração de QR Code
    $qr_code_image = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_code_text);
    
    // Atualiza transação com dados do PIX
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET gateway_transaction_id = ?,
            qr_code = ?,
            qr_code_text = ?,
            expires_at = ?
        WHERE id = ?
    ");
    
    $gateway_id = 'TEST_' . uniqid();
    $stmt->execute([$gateway_id, $qr_code_image, $qr_code_text, $expires_at, $transaction_id]);
    
    $pdo->commit();
    
    // ========================================
    // 7. SALVAR NA SESSÃO E REDIRECIONAR
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
    
    // Log de sucesso
    error_log("Store created: ID=$store_id, Email=$email, Plan=$plan");
    
    // Redireciona para página de pagamento
    header('Location: payment.php');
    exit;
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Checkout Error: " . $e->getMessage());
    
    $_SESSION['checkout_errors'] = ["Erro ao processar pedido. Tente novamente."];
    $_SESSION['checkout_data'] = $old_data;
    
    header('Location: checkout.php?plan=' . urlencode($plan));
    exit;
}
?>