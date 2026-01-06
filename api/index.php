<?php
/**
 * ============================================
 * SPLITSTORE - API ROUTER
 * ============================================
 * Router principal para endpoints da API
 * Versão: 1.0.0
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

// Pegar URI e Método
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string e base path
$uri = parse_url($requestUri, PHP_URL_PATH);
$uri = str_replace('/api', '', $uri);

// Obter dados do body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Se não houver dados no body, inicializar como array vazio
if ($data === null) {
    $data = [];
}

// Log da requisição
error_log("=== API REQUEST ===");
error_log("URI: " . $uri);
error_log("Method: " . $requestMethod);
error_log("Body: " . $input);
error_log("Parsed Data: " . print_r($data, true));

// Middleware de autenticação
$auth = PluginAuthMiddleware::authenticate();

if (!$auth['success']) {
    logAPI($uri, $requestMethod, null, 401, $auth['message']);
    jsonResponse([
        'success' => false,
        'error' => $auth['message']
    ], 401);
}

$store_id = $auth['store_id'];
$controller = new PluginController($pdo, $store_id);

// Rotas da API
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
                'error' => 'Endpoint não encontrado',
                'available_endpoints' => [
                    '/plugin/verify',
                    '/plugin/purchases/pending',
                    '/plugin/purchases/confirm',
                    '/plugin/player/logout',
                    '/plugin/server/status'
                ]
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
        'error' => 'Erro interno do servidor'
    ];
    
    // Adicionar detalhes apenas se DEBUG estiver ativo
    if (defined('DEBUG') && DEBUG === true) {
        $response['message'] = $e->getMessage();
        $response['trace'] = $e->getTraceAsString();
    }
    
    jsonResponse($response, 500);
}