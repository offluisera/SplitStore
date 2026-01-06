<?php
/**
 * ============================================
 * SPLITSTORE - API DEBUG
 * ============================================
 * Script para debugar problemas da API
 * Acesse: http://seusite.com/api/debug.php
 * ============================================
 */

header('Content-Type: application/json; charset=utf-8');

// Informações do ambiente
$debug_info = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
];

// Testar conexão com banco de dados
try {
    require_once '../includes/db.php';
    $debug_info['database'] = [
        'status' => 'Connected',
        'pdo_available' => isset($pdo) ? 'Yes' : 'No'
    ];
    
    if (isset($pdo)) {
        // Testar query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM stores");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $debug_info['database']['stores_count'] = $result['count'];
        
        // Verificar tabelas necessárias
        $tables = ['stores', 'products', 'purchases', 'player_sessions', 'server_status', 'api_logs'];
        $existing_tables = [];
        
        foreach ($tables as $table) {
            try {
                $pdo->query("SELECT 1 FROM $table LIMIT 1");
                $existing_tables[$table] = 'OK';
            } catch (Exception $e) {
                $existing_tables[$table] = 'NOT FOUND';
            }
        }
        
        $debug_info['database']['tables'] = $existing_tables;
    }
} catch (Exception $e) {
    $debug_info['database'] = [
        'status' => 'Error',
        'error' => $e->getMessage()
    ];
}

// Testar headers
$debug_info['headers'] = [
    'X-API-Key' => $_SERVER['HTTP_X_API_KEY'] ?? 'Not provided',
    'X-API-Secret' => isset($_SERVER['HTTP_X_API_SECRET']) ? substr($_SERVER['HTTP_X_API_SECRET'], 0, 10) . '...' : 'Not provided',
    'Content-Type' => $_SERVER['CONTENT_TYPE'] ?? 'Not set',
    'User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Not set'
];

// Testar body
$input = file_get_contents('php://input');
$debug_info['request_body'] = [
    'raw' => $input,
    'parsed' => json_decode($input, true),
    'json_error' => json_last_error_msg()
];

// Verificar mod_rewrite
$debug_info['mod_rewrite'] = [
    'enabled' => function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules()) ? 'Yes' : 'Unknown',
    'htaccess_exists' => file_exists(__DIR__ . '/.htaccess') ? 'Yes' : 'No'
];

// Verificar arquivos necessários
$required_files = ['index.php', 'PluginAuthMiddleware.php', 'PluginController.php'];
$files_status = [];

foreach ($required_files as $file) {
    $files_status[$file] = file_exists(__DIR__ . '/' . $file) ? 'OK' : 'MISSING';
}

$debug_info['files'] = $files_status;

// Testar autenticação se credenciais forem fornecidas
if (isset($_SERVER['HTTP_X_API_KEY']) && isset($_SERVER['HTTP_X_API_SECRET'])) {
    try {
        require_once 'PluginAuthMiddleware.php';
        $auth = PluginAuthMiddleware::authenticate();
        $debug_info['authentication'] = $auth;
    } catch (Exception $e) {
        $debug_info['authentication'] = [
            'status' => 'Error',
            'error' => $e->getMessage()
        ];
    }
}

// Retornar informações
echo json_encode($debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);