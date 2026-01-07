<?php
/**
 * ============================================
 * SISTEMA DE CONTROLE DE ACESSO - AUTH GUARD
 * ============================================
 * includes/auth_guard.php
 * 
 * Gerencia níveis de acesso baseado no status de pagamento
 */

if (!isset($_SESSION)) {
    session_start();
}

/**
 * Verifica se o usuário está logado
 */
function isLoggedIn() {
    return isset($_SESSION['store_logged']) && $_SESSION['store_logged'] === true;
}

/**
 * Retorna o nível de acesso do usuário
 * - 'none': Não logado
 * - 'suspended': Conta suspensa
 * - 'restricted': Logado mas sem pagamento confirmado
 * - 'full': Pagamento confirmado, acesso total
 */
function getAccessLevel() {
    global $pdo;
    
    if (!isLoggedIn()) {
        return 'none';
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                status,
                first_payment_confirmed
            FROM stores
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['store_id']]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$store) {
            return 'none';
        }
        
        // Conta suspensa
        if ($store['status'] === 'suspended') {
            return 'suspended';
        }
        
        // Pagamento confirmado = acesso total
        if ($store['first_payment_confirmed'] == 1) {
            return 'full';
        }
        
        // Aguardando pagamento = acesso restrito
        return 'restricted';
        
    } catch (Exception $e) {
        error_log("Auth Guard Error: " . $e->getMessage());
        return 'none';
    }
}

/**
 * Define quais páginas cada nível pode acessar
 */
function getAllowedPages($accessLevel) {
    $pages = [
        'none' => ['login.php', 'logout.php'],
        'suspended' => ['login.php', 'logout.php', 'suspended.php'],
        'restricted' => [
            'login.php',
            'logout.php',
            'dashboard.php',
            'faturas.php'
        ],
        'full' => [
            'login.php',
            'logout.php',
            'dashboard.php',
            'faturas.php',
            'products.php',
            'gerenciar_pedidos.php',
            'customize.php',
            'servers.php',
            'integrations.php',
            'settings.php'
        ]
    ];
    
    return $pages[$accessLevel] ?? [];
}

/**
 * Verifica se pode acessar uma página específica
 */
function canAccessPage($page) {
    $accessLevel = getAccessLevel();
    $allowedPages = getAllowedPages($accessLevel);
    return in_array($page, $allowedPages);
}

/**
 * Protege uma página com redirecionamento automático
 * USO: requireAccess(__FILE__); no início de cada página protegida
 */
function requireAccess($filePath) {
    $page = basename($filePath);
    
    // Verifica login
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php?error=login_required');
        exit;
    }
    
    $accessLevel = getAccessLevel();
    
    // Conta suspensa
    if ($accessLevel === 'suspended') {
        if ($page !== 'suspended.php') {
            header('Location: suspended.php');
            exit;
        }
        return true;
    }
    
    // Verifica permissão
    if (!canAccessPage($page)) {
        // Acesso restrito tentando acessar página bloqueada
        if ($accessLevel === 'restricted') {
            $_SESSION['access_denied'] = 'Complete seu pagamento para acessar esta funcionalidade.';
            header('Location: faturas.php');
            exit;
        }
        
        // Fallback
        header('Location: dashboard.php');
        exit;
    }
    
    return true;
}

/**
 * Retorna badge HTML do status de pagamento
 */
function getPaymentStatusBadge() {
    $accessLevel = getAccessLevel();
    
    $badges = [
        'full' => '<span class="bg-green-500/10 text-green-500 border border-green-500/20 px-3 py-1 rounded-full text-[10px] font-black uppercase">✓ Ativo</span>',
        'restricted' => '<span class="bg-yellow-500/10 text-yellow-500 border border-yellow-500/20 px-3 py-1 rounded-full text-[10px] font-black uppercase animate-pulse">⚠ Pagamento Pendente</span>',
        'suspended' => '<span class="bg-red-500/10 text-red-500 border border-red-500/20 px-3 py-1 rounded-full text-[10px] font-black uppercase">✗ Suspenso</span>',
    ];
    
    return $badges[$accessLevel] ?? '';
}

/**
 * Verifica se tem fatura pendente
 */
function hasPendingInvoice($store_id = null) {
    global $pdo;
    
    if ($store_id === null) {
        $store_id = $_SESSION['store_id'] ?? null;
    }
    
    if (!$store_id) return false;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM invoices 
            WHERE store_id = ? 
            AND status = 'pending'
            AND due_date > NOW()
        ");
        $stmt->execute([$store_id]);
        
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Marca primeiro pagamento como confirmado
 * Chamado pelo webhook após confirmação
 */
function confirmFirstPayment($store_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE stores
            SET 
                first_payment_confirmed = 1,
                status = 'active',
                activated_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$store_id]);
    } catch (Exception $e) {
        error_log("Error confirming first payment: " . $e->getMessage());
        return false;
    }
}

/**
 * Retorna mensagem amigável baseada no nível de acesso
 */
function getAccessLevelMessage($accessLevel = null) {
    if ($accessLevel === null) {
        $accessLevel = getAccessLevel();
    }
    
    $messages = [
        'restricted' => [
            'title' => 'Aguardando Pagamento',
            'description' => 'Complete seu pagamento para desbloquear todas as funcionalidades.',
            'icon' => 'clock',
            'color' => 'yellow'
        ],
        'full' => [
            'title' => 'Acesso Total',
            'description' => 'Todos os recursos estão disponíveis.',
            'icon' => 'check-circle',
            'color' => 'green'
        ],
        'suspended' => [
            'title' => 'Conta Suspensa',
            'description' => 'Entre em contato com o suporte.',
            'icon' => 'x-circle',
            'color' => 'red'
        ]
    ];
    
    return $messages[$accessLevel] ?? null;
}

/**
 * Retorna informações sobre features bloqueadas
 */
function getBlockedFeaturesInfo() {
    return [
        'products' => [
            'name' => 'Gerenciar Produtos',
            'description' => 'Crie e gerencie produtos da sua loja',
            'icon' => 'package'
        ],
        'customize' => [
            'name' => 'Personalização',
            'description' => 'Customize cores, logo e design',
            'icon' => 'palette'
        ],
        'servers' => [
            'name' => 'Servidores',
            'description' => 'Configure credenciais e plugin',
            'icon' => 'server'
        ],
        'integrations' => [
            'name' => 'Integrações',
            'description' => 'Gateway de pagamento e APIs',
            'icon' => 'plug'
        ],
        'settings' => [
            'name' => 'Configurações',
            'description' => 'Ajustes gerais da plataforma',
            'icon' => 'settings'
        ]
    ];
}