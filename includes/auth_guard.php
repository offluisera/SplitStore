<?php
/**
 * ============================================
 * AUTH GUARD - CONTROLE DE ACESSO
 * ============================================
 * Sistema de verificação de autenticação e controle de acesso
 * baseado no status de pagamento da loja
 */

// Inicia sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se usuário está logado
 */
function isLoggedIn() {
    return isset($_SESSION['store_logged']) && $_SESSION['store_logged'] === true;
}

/**
 * Redireciona para login se não estiver autenticado
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Busca o nível de acesso da loja
 * - full: Loja com pagamento confirmado
 * - restricted: Loja aguardando primeiro pagamento
 * - suspended: Loja suspensa por falta de pagamento
 */
function getAccessLevel() {
    global $pdo;
    
    if (!isLoggedIn()) {
        return 'restricted';
    }
    
    $store_id = $_SESSION['store_id'];
    
    try {
        // Busca status da loja
        $stmt = $pdo->prepare("
            SELECT 
                s.status,
                s.access_level,
                s.first_payment_confirmed,
                s.activated_at
            FROM stores s
            WHERE s.id = ?
        ");
        $stmt->execute([$store_id]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$store) {
            return 'restricted';
        }
        
        // Se loja está suspensa
        if ($store['status'] === 'suspended') {
            return 'suspended';
        }
        
        // Se primeiro pagamento foi confirmado
        if ($store['first_payment_confirmed'] == 1) {
            return 'full';
        }
        
        // Caso contrário, acesso restrito
        return 'restricted';
        
    } catch (PDOException $e) {
        error_log("Access Level Error: " . $e->getMessage());
        return 'restricted';
    }
}

/**
 * Verifica se tem fatura pendente
 */
function hasPendingInvoice() {
    global $pdo;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $store_id = $_SESSION['store_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM invoices
            WHERE store_id = ?
            AND status = 'pending'
            AND due_date > NOW()
        ");
        $stmt->execute([$store_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
        
    } catch (PDOException $e) {
        error_log("Pending Invoice Check Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Retorna badge de status de pagamento
 */
function getPaymentStatusBadge() {
    $accessLevel = getAccessLevel();
    
    if ($accessLevel === 'full') {
        return '<div class="flex items-center gap-2 bg-green-500/10 border border-green-500/20 text-green-500 px-3 py-2 rounded-xl">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    <span class="text-[10px] font-black uppercase">Sistema Ativo</span>
                </div>';
    } elseif ($accessLevel === 'suspended') {
        return '<div class="flex items-center gap-2 bg-red-500/10 border border-red-500/20 text-red-500 px-3 py-2 rounded-xl">
                    <i data-lucide="alert-circle" class="w-3 h-3"></i>
                    <span class="text-[10px] font-black uppercase">Suspenso</span>
                </div>';
    } else {
        return '<div class="flex items-center gap-2 bg-yellow-500/10 border border-yellow-500/20 text-yellow-500 px-3 py-2 rounded-xl">
                    <i data-lucide="clock" class="w-3 h-3"></i>
                    <span class="text-[10px] font-black uppercase">Aguardando Pagamento</span>
                </div>';
    }
}

/**
 * Define páginas que são sempre acessíveis (mesmo em modo restrito)
 */
function getPublicPages() {
    return [
        'dashboard.php',
        'faturas.php',
        'logout.php',
        'settings.php', // Permite acesso a configurações básicas
        'noticias.php'  // Permite acesso ao gerenciamento de notícias
    ];
}

/**
 * Verifica se pode acessar uma página específica
 */
function canAccessPage($page) {
    $accessLevel = getAccessLevel();
    
    // Se tem acesso total, pode acessar tudo
    if ($accessLevel === 'full') {
        return true;
    }
    
    // Se está suspenso, só pode acessar dashboard e faturas
    if ($accessLevel === 'suspended') {
        return in_array($page, ['dashboard.php', 'faturas.php', 'logout.php']);
    }
    
    // Se está em modo restrito, verifica se é página pública
    return in_array($page, getPublicPages());
}

/**
 * Middleware principal - verifica acesso à página atual
 * USO: requireAccess(__FILE__) no início de cada página protegida
 */
function requireAccess($currentFile) {
    // Primeiro verifica se está logado
    requireLogin();
    
    // Extrai o nome do arquivo
    $page = basename($currentFile);
    
    // Verifica se pode acessar
    if (!canAccessPage($page)) {
        // Se não pode acessar, redireciona para dashboard
        header('Location: dashboard.php?access_denied=1');
        exit;
    }
}

/**
 * Verifica se store_id existe na sessão
 */
function hasStoreId() {
    return isset($_SESSION['store_id']) && !empty($_SESSION['store_id']);
}

/**
 * Exibe mensagem de acesso negado (se houver)
 */
function showAccessDeniedMessage() {
    if (isset($_GET['access_denied'])) {
        return '<div class="glass border-yellow-600/20 bg-yellow-600/5 text-yellow-500 p-5 rounded-2xl mb-8 flex items-center gap-3">
                    <i data-lucide="lock" class="w-5 h-5"></i>
                    <div>
                        <p class="font-bold mb-1">Acesso Restrito</p>
                        <p class="text-sm text-zinc-400">Complete seu primeiro pagamento para desbloquear todas as funcionalidades.</p>
                        <a href="faturas.php" class="text-sm text-yellow-500 font-bold hover:underline mt-2 inline-block">
                            Ver Faturas →
                        </a>
                    </div>
                </div>';
    }
    return '';
}

/**
 * Helper para debug - mostra informações de acesso
 */
function debugAccessInfo() {
    if (!isset($_GET['debug_access'])) {
        return;
    }
    
    echo '<div class="glass p-6 rounded-2xl mb-8 border border-blue-600/20">';
    echo '<h3 class="text-lg font-black mb-4 text-blue-500">DEBUG - Informações de Acesso</h3>';
    echo '<pre class="text-xs text-zinc-400 bg-black/30 p-4 rounded-xl overflow-auto">';
    echo 'Logged In: ' . (isLoggedIn() ? 'YES' : 'NO') . "\n";
    echo 'Store ID: ' . ($_SESSION['store_id'] ?? 'N/A') . "\n";
    echo 'Store Name: ' . ($_SESSION['store_name'] ?? 'N/A') . "\n";
    echo 'Access Level: ' . getAccessLevel() . "\n";
    echo 'Has Pending Invoice: ' . (hasPendingInvoice() ? 'YES' : 'NO') . "\n";
    echo 'Current Page: ' . basename($_SERVER['PHP_SELF']) . "\n";
    echo 'Can Access: ' . (canAccessPage(basename($_SERVER['PHP_SELF'])) ? 'YES' : 'NO') . "\n";
    echo "\nPublic Pages:\n";
    foreach (getPublicPages() as $page) {
        echo "  - $page\n";
    }
    echo '</pre>';
    echo '</div>';
}