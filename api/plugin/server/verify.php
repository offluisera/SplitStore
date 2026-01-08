<?php
/**
 * ============================================
 * API: VERIFICAR SERVIDOR
 * ============================================
 * Endpoint: POST /api/plugin/server/verify
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, X-API-Secret');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ajuste o caminho do db.php baseado na sua estrutura
require_once '../../../includes/db.php';

// Log para debug
error_log("=== VERIFY ENDPOINT ACESSADO ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);

// Apenas POST permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

try {
    // Lê o corpo da requisição
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    error_log("Body recebido: " . $input);

    // Valida JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON'
        ]);
        exit;
    }

    // Valida headers de autenticação
    $headers = getallheaders();
    $apiKey = $headers['X-API-Key'] ?? $headers['X-Api-Key'] ?? null;
    $apiSecret = $headers['X-API-Secret'] ?? $headers['X-Api-Secret'] ?? null;

    if (!$apiKey || !$apiSecret) {
        error_log("Credenciais ausentes");
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Missing API credentials'
        ]);
        exit;
    }

    // Valida código de verificação
    $verificationCode = $data['verification_code'] ?? null;

    if (!$verificationCode) {
        error_log("Código de verificação ausente");
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing verification_code'
        ]);
        exit;
    }

    error_log("Validando credenciais - API Key: " . substr($apiKey, 0, 10) . "...");

    // Busca a loja pelas credenciais
    $stmt = $pdo->prepare("
        SELECT id, store_name 
        FROM stores 
        WHERE api_key = ? 
        AND api_secret = ? 
        AND status = 'active'
    ");
    $stmt->execute([$apiKey, $apiSecret]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$store) {
        error_log("Credenciais inválidas - API Key: " . substr($apiKey, 0, 10));
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid API credentials'
        ]);
        exit;
    }

    $storeId = $store['id'];
    error_log("Loja encontrada: " . $store['store_name'] . " (ID: $storeId)");

    // CORREÇÃO: Usa tabela minecraft_servers ao invés de servers
    error_log("Buscando servidor - Store ID: $storeId, Código: $verificationCode");
    
    $stmt = $pdo->prepare("
        SELECT id, server_name, server_id, status 
        FROM minecraft_servers 
        WHERE store_id = ? 
        AND verification_code = ? 
        AND status = 'pending'
    ");
    $stmt->execute([$storeId, $verificationCode]);
    $server = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$server) {
        error_log("Servidor não encontrado ou já verificado");
        
        // Verifica se o servidor existe mas já está verificado
        $stmt = $pdo->prepare("
            SELECT id, server_name, status 
            FROM minecraft_servers 
            WHERE store_id = ? 
            AND verification_code = ?
        ");
        $stmt->execute([$storeId, $verificationCode]);
        $existingServer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingServer) {
            error_log("Servidor existe mas status é: " . $existingServer['status']);
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Server already verified or inactive',
                'current_status' => $existingServer['status']
            ]);
        } else {
            error_log("Código de verificação inválido");
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid verification code'
            ]);
        }
        exit;
    }

    error_log("Servidor encontrado: " . $server['server_name'] . " (ID: " . $server['id'] . ")");

    // Atualiza o servidor como verificado
    $stmt = $pdo->prepare("
        UPDATE minecraft_servers 
        SET status = 'active',
            verified_at = NOW(),
            minecraft_version = ?,
            plugin_version = ?,
            online_players = ?,
            max_players = ?,
            last_ping = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");

    $minecraftVersion = $data['server_version'] ?? 'Unknown';
    $pluginVersion = $data['plugin_version'] ?? '1.0.0';
    $onlinePlayers = $data['online_players'] ?? 0;
    $maxPlayers = $data['max_players'] ?? 0;

    $updateResult = $stmt->execute([
        $minecraftVersion,
        $pluginVersion,
        $onlinePlayers,
        $maxPlayers,
        $server['id']
    ]);

    if ($updateResult) {
        error_log("✅ Servidor verificado com sucesso! ID: " . $server['id']);

        // Resposta de sucesso
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Server verified successfully',
            'data' => [
                'server_id' => $server['server_id'],
                'server_name' => $server['server_name'],
                'store_name' => $store['store_name'],
                'verified_at' => date('Y-m-d H:i:s'),
                'minecraft_version' => $minecraftVersion,
                'plugin_version' => $pluginVersion
            ]
        ]);
    } else {
        error_log("❌ Falha ao atualizar servidor");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update server status'
        ]);
    }

} catch (PDOException $e) {
    error_log("❌ Erro de banco de dados: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'details' => $e->getMessage() // Remova em produção
    ]);
} catch (Exception $e) {
    error_log("❌ Erro geral: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'details' => $e->getMessage() // Remova em produção
    ]);
}