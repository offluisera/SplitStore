<?php
/**
 * ============================================
 * SISTEMA DE AUTENTICAÇÃO E CONTROLE DE ACESSO
 * ============================================
 * includes/auth_guard.php
 * 
 * Controla acesso baseado no status de pagamento
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
 * Verifica o nível de acesso da loja
 */
function getAccessLevel() {
    global $pdo;
    
    if (!isLoggedIn()) {
        return 'none';
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT access_level, first_payment_confirmed, status
            FROM stores
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['store_id']]);
        $store = $stmt->fetch();
        
        if (!$store) {
            return 'none';
        }
        
        // Se está suspenso, acesso zero
        if ($store['status'] === 'suspended') {
            return 'suspended';
        }
        
        // Se tem pagamento confirmado, acesso total
        if ($store['first_payment_confirmed'] == 1) {
            return 'full';
        }
        
        // Senão, acesso restrito
        return 'restricted';
        
    } catch (Exception $e) {
        error_log("Auth Guard Error: " . $e->getMessage());
        return 'none';
    }
}

/**
 * Verifica se a página pode ser acessada
 */
function canAccessPage($page) {
    $accessLevel = getAccessLevel();
    
    // Páginas sempre permitidas (mesmo sem login)
    $publicPages = ['login.php', 'logout.php'];
    if (in_array($page, $publicPages)) {
        return true;
    }
    
    // Não logado: apenas público
    if ($accessLevel === 'none') {
        return false;
    }
    
    // Suspenso: nada
    if ($accessLevel === 'suspended') {
        return in_array($page, ['suspended.php']);
    }
    
    // Acesso restrito: apenas dashboard e faturas
    if ($accessLevel === 'restricted') {
        $allowedPages = [
            'dashboard.php',
            'faturas.php',
            'settings.php' // Apenas visualização
        ];
        return in_array($page, $allowedPages);
    }
    
    // Acesso total: tudo
    if ($accessLevel === 'full') {
        return true;
    }
    
    return false;
}

/**
 * Protege uma página
 * Uso: requireAccess(__FILE__);
 */
function requireAccess($filePath) {
    global $pdo;
    
    $page = basename($filePath);
    
    // Verifica se está logado
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php?error=login_required');
        exit;
    }
    
    // Verifica se pode acessar
    if (!canAccessPage($page)) {
        $accessLevel = getAccessLevel();
        
        if ($accessLevel === 'suspended') {
            header('Location: suspended.php');
            exit;
        }
        
        if ($accessLevel === 'restricted') {
            // Redireciona para faturas com mensagem
            $_SESSION['access_denied_message'] = 'Complete seu pagamento para acessar esta funcionalidade.';
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
 * Exibe badge de status de pagamento
 */
function getPaymentStatusBadge() {
    $accessLevel = getAccessLevel();
    
    $badges = [
        'full' => '<span class="bg-green-500/10 text-green-500 border border-green-500/20 px-3 py-1 rounded-full text-[10px] font-black uppercase">✓ Ativo</span>',
        'restricted' => '<span class="bg-yellow-500/10 text-yellow-500 border border-yellow-500/20 px-3 py-1 rounded-full text-[10px] font-black uppercase">⚠ Pagamento Pendente</span>',
        'suspended' => '<span class="bg-red-500/10 text-red-500 border border-red-500/20 px-3 py-1 rounded-full text-[10px] font-black uppercase">✗ Suspenso</span>',
    ];
    
    return $badges[$accessLevel] ?? '';
}

/**
 * Marca primeiro pagamento como confirmado
 */
function confirmFirstPayment($store_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE stores
            SET 
                first_payment_confirmed = 1,
                access_level = 'full',
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
 * Retorna URL de redirecionamento pós-login
 */
function getRedirectAfterLogin() {
    if (isset($_SESSION['redirect_after_login'])) {
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        return $redirect;
    }
    
    // Se tem fatura pendente, vai para faturas
    if (hasPendingInvoice()) {
        return 'faturas.php';
    }
    
    return 'dashboard.php';
}
?>