<?php
/**
 * ============================================
 * API: HEARTBEAT DO SERVIDOR
 * ============================================
 * Endpoint: POST /api/plugin/server/heartbeat
 * Atualiza status do servidor (online players, comandos executados, etc)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, X-API-Secret');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../../includes/db.php';

// Log
error_log("=== HEARTBEAT RECEBIDO ===");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

try {
    // Lê dados
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    error_log("Heartbeat data: " . $input);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON'
        ]);
        exit;
    }

    // Valida credenciais
    $headers = getallheaders();
    $apiKey = $headers['X-API-Key'] ?? $headers['X-Api-Key'] ?? null;
    $apiSecret = $headers['X-API-Secret'] ?? $headers['X-Api-Secret'] ?? null;

    if (!$apiKey || !$apiSecret) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Missing API credentials'
        ]);
        exit;
    }

    // Busca a loja
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
    
    // Dados do heartbeat
    $serverId = $data['server_id'] ?? null;
    $onlinePlayers = $data['online_players'] ?? 0;
    $maxPlayers = $data['max_players'] ?? 0;
    $minecraftVersion = $data['minecraft_version'] ?? null;
    $pluginVersion = $data['plugin_version'] ?? '1.0.0';
    $totalCommandsSent = $data['total_commands_sent'] ?? 0;
    $totalPurchasesDelivered = $data['total_purchases_delivered'] ?? 0;

    error_log("Store ID: $storeId | Server ID: $serverId | Players: $onlinePlayers/$maxPlayers");

    // Busca o servidor
    $stmt = $pdo->prepare("
        SELECT id 
        FROM minecraft_servers 
        WHERE store_id = ? 
        AND server_id = ? 
        AND status = 'active'
    ");
    $stmt->execute([$storeId, $serverId]);
    $server = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$server) {
        error_log("Servidor não encontrado ou inativo");
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Server not found or inactive'
        ]);
        exit;
    }

    // Atualiza o servidor
    $stmt = $pdo->prepare("
        UPDATE minecraft_servers 
        SET 
            online_players = ?,
            max_players = ?,
            last_ping = NOW(),
            minecraft_version = COALESCE(?, minecraft_version),
            plugin_version = ?,
            total_commands_sent = ?,
            total_purchases_delivered = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $updateResult = $stmt->execute([
        $onlinePlayers,
        $maxPlayers,
        $minecraftVersion,
        $pluginVersion,
        $totalCommandsSent,
        $totalPurchasesDelivered,
        $server['id']
    ]);

    if ($updateResult) {
        error_log("✅ Heartbeat processado - Server ID: " . $server['id']);

        // Atualiza também a tabela server_status (se existir)
        try {
            $stmt = $pdo->prepare("
                INSERT INTO server_status 
                    (store_id, online_players, max_players, last_update) 
                VALUES 
                    (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    online_players = VALUES(online_players),
                    max_players = VALUES(max_players),
                    last_update = NOW()
            ");
            $stmt->execute([$storeId, $onlinePlayers, $maxPlayers]);
        } catch (PDOException $e) {
            // Ignora erro se tabela não existir
            error_log("Aviso: não foi possível atualizar server_status: " . $e->getMessage());
        }

        // Busca compras pendentes para retornar
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.player_uuid,
                p.player_name,
                p.amount,
                pr.commands,
                pr.name as product_name
            FROM purchases p
            INNER JOIN products pr ON p.product_id = pr.id
            WHERE p.store_id = ? 
            AND p.status = 'pending'
            ORDER BY p.created_at ASC
            LIMIT 50
        ");
        $stmt->execute([$storeId]);
        $pendingPurchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Heartbeat received',
            'data' => [
                'server_online' => true,
                'pending_deliveries' => count($pendingPurchases),
                'purchases' => $pendingPurchases
            ]
        ]);

    } else {
        error_log("❌ Falha ao processar heartbeat");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update server'
        ]);
    }

} catch (PDOException $e) {
    error_log("❌ Erro de banco: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
} catch (Exception $e) {
    error_log("❌ Erro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}