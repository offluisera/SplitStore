<?php
/**
 * ============================================
 * SPLITSTORE - API ROUTER (UNIVERSAL)
 * ============================================
 * Funciona com Apache (.htaccess) e NGINX
 * Versão: 2.0.0
 */

// Carregar configurações
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, X-API-Secret');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/db.php';
require_once 'PluginAuthMiddleware.php';
require_once 'PluginController.php';

// Função para responder com JSON
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Função para log de API
function logAPI($endpoint, $method, $store_id, $status, $message = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO api_logs (store_id, endpoint, method, status_code, message, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$store_id, $endpoint, $method, $status, $message]);
    } catch (Exception $e) {
        error_log("Erro ao salvar log da API: " . $e->getMessage());
    }
}

// ============================================
// DETECÇÃO INTELIGENTE DE ROTA
// ============================================

$uri = null;
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Método 1: PATH_INFO (preferencial)
if (isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
    $uri = $_SERVER['PATH_INFO'];
    error_log("Rota detectada via PATH_INFO: " . $uri);
}
// Método 2: REQUEST_URI (nginx)
elseif (isset($_SERVER['REQUEST_URI'])) {
    $requestUri = $_SERVER['REQUEST_URI'];
    // Remove query string
    $uri = parse_url($requestUri, PHP_URL_PATH);
    // Remove /api e /index.php
    $uri = preg_replace('#^/api(/index\.php)?#', '', $uri);
    error_log("Rota detectada via REQUEST_URI: " . $uri);
}
// Método 3: Query string (fallback)
elseif (isset($_GET['route'])) {
    $uri = '/' . ltrim($_GET['route'], '/');
    error_log("Rota detectada via query string: " . $uri);
}

// Se ainda não tiver rota, verificar se é acesso direto
if (empty($uri) || $uri === '/') {
    jsonResponse([
        'success' => true,
        'message' => 'SplitStore API está online',
        'version' => '2.0.0',
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoints' => [
            '/plugin/verify',
            '/plugin/purchases/pending',
            '/plugin/purchases/confirm',
            '/plugin/player/logout',
            '/plugin/server/status'
        ],
        'documentation' => 'https://docs.splitstore.com.br',
        'help' => [
            'Se você está vendo isso, a API está acessível',
            'Configure o roteamento (nginx.conf ou .htaccess)',
            'Teste os endpoints com /api/simple_test.php',
            'Rode diagnóstico em /api/diagnostico.php'
        ]
    ]);
}

// Limpar e normalizar URI
$uri = '/' . trim($uri, '/');

// Obter dados do body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Se não houver dados no body, inicializar como array vazio
if ($data === null) {
    $data = [];
}

// Log da requisição
error_log("=== API REQUEST ===");
error_log("Method: " . $requestMethod);
error_log("URI: " . $uri);
error_log("Body: " . $input);
error_log("Headers: " . json_encode(getallheaders()));

// ============================================
// MIDDLEWARE DE AUTENTICAÇÃO
// ============================================

$auth = PluginAuthMiddleware::authenticate();

if (!$auth['success']) {
    logAPI($uri, $requestMethod, null, 401, $auth['message']);
    jsonResponse([
        'success' => false,
        'error' => $auth['message'],
        'timestamp' => date('Y-m-d H:i:s')
    ], 401);
}

$store_id = $auth['store_id'];
$controller = new PluginController($pdo, $store_id);

// ============================================
// ROTEAMENTO DE ENDPOINTS
// ============================================

try {
    switch ($uri) {
        // Verificar credenciais
        case '/plugin/verify':
            if ($requestMethod !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            $result = $controller->verify($data);
            logAPI($uri, $requestMethod, $store_id, 200, 'Verificação bem-sucedida');
            jsonResponse($result);
            break;

        // Buscar compras pendentes
        case '/plugin/purchases/pending':
            if ($requestMethod !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            $result = $controller->getPendingPurchases($data);
            logAPI($uri, $requestMethod, $store_id, 200);
            jsonResponse($result);
            break;

        // Confirmar entrega de compra
        case '/plugin/purchases/confirm':
            if ($requestMethod !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            $result = $controller->confirmDelivery($data);
            logAPI($uri, $requestMethod, $store_id, 200, 'Entrega confirmada');
            jsonResponse($result);
            break;

        // Logout de jogador
        case '/plugin/player/logout':
            if ($requestMethod !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            $result = $controller->playerLogout($data);
            logAPI($uri, $requestMethod, $store_id, 200);
            jsonResponse($result);
            break;

        // Status do servidor
        case '/plugin/server/status':
            if ($requestMethod !== 'POST') {
                jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            }
            $result = $controller->serverStatus($data);
            logAPI($uri, $requestMethod, $store_id, 200);
            jsonResponse($result);
            break;

        // Rota não encontrada
        default:
            logAPI($uri, $requestMethod, $store_id, 404, 'Endpoint não encontrado');
            jsonResponse([
                'success' => false,
                'error' => 'Endpoint não encontrado: ' . $uri,
                'available_endpoints' => [
                    '/plugin/verify',
                    '/plugin/purchases/pending',
                    '/plugin/purchases/confirm',
                    '/plugin/player/logout',
                    '/plugin/server/status'
                ],
                'help' => 'Verifique a documentação em https://docs.splitstore.com.br'
            ], 404);
            break;
    }
} catch (Exception $e) {
    error_log("Erro na API: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    logAPI($uri, $requestMethod, $store_id, 500, $e->getMessage());
    
    // Em desenvolvimento, retornar detalhes do erro
    $response = [
        'success' => false,
        'error' => 'Erro interno do servidor',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Adicionar detalhes apenas se DEBUG estiver ativo
    if (defined('DEBUG') && DEBUG === true) {
        $response['debug'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    jsonResponse($response, 500);
}