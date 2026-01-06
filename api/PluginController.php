<?php
/**
 * ============================================
 * SPLITSTORE - PLUGIN CONTROLLER
 * ============================================
 * Controller para processar requisições do plugin
 */

class PluginController {
    
    private $pdo;
    private $store_id;
    
    public function __construct($pdo, $store_id) {
        $this->pdo = $pdo;
        $this->store_id = $store_id;
    }
    
    /**
     * Verifica credenciais e retorna info da loja
     */
    public function verify($data) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    id,
                    store_name,
                    plan,
                    created_at,
                    status
                FROM stores
                WHERE id = ?
            ");
            
            $stmt->execute([$this->store_id]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                return [
                    'success' => false,
                    'error' => 'Loja não encontrada'
                ];
            }
            
            // Garantir que $data é um array
            if (!is_array($data)) {
                $data = [];
            }
            
            return [
                'success' => true,
                'message' => 'Credenciais válidas',
                'data' => [
                    'store_id' => (int)$store['id'],
                    'store_name' => $store['store_name'],
                    'plan' => $store['plan'],
                    'status' => $store['status'],
                    'member_since' => $store['created_at'],
                    'server_version' => isset($data['version']) ? $data['version'] : 'unknown'
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("Erro no verify: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro ao verificar credenciais',
                'details' => $e->getMessage()
            ];
        } catch (Exception $e) {
            error_log("Erro geral no verify: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro inesperado',
                'details' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Busca compras pendentes de um jogador
     */
    public function getPendingPurchases($data) {
        $playerUUID = $data['player_uuid'] ?? null;
        $playerName = $data['player_name'] ?? null;
        
        if (!$playerUUID) {
            return [
                'success' => false,
                'error' => 'player_uuid é obrigatório'
            ];
        }
        
        try {
            // Buscar compras pendentes
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id,
                    p.product_id,
                    p.player_uuid,
                    p.player_name,
                    p.amount,
                    p.status,
                    p.created_at,
                    pr.name as product_name,
                    pr.type as product_type,
                    pr.commands
                FROM purchases p
                LEFT JOIN products pr ON p.product_id = pr.id
                WHERE p.store_id = ?
                AND p.player_uuid = ?
                AND p.status = 'pending'
                ORDER BY p.created_at ASC
            ");
            
            $stmt->execute([$this->store_id, $playerUUID]);
            $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Processar comandos JSON
            foreach ($purchases as &$purchase) {
                if (!empty($purchase['commands'])) {
                    $purchase['commands'] = json_decode($purchase['commands'], true);
                }
            }
            
            return [
                'success' => true,
                'count' => count($purchases),
                'purchases' => $purchases
            ];
            
        } catch (PDOException $e) {
            error_log("Erro ao buscar compras pendentes: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro ao buscar compras pendentes'
            ];
        }
    }
    
    /**
     * Confirma entrega de uma compra
     */
    public function confirmDelivery($data) {
        $purchaseId = $data['purchase_id'] ?? null;
        $playerUUID = $data['player_uuid'] ?? null;
        $deliveredAt = $data['delivered_at'] ?? time() * 1000;
        
        if (!$purchaseId || !$playerUUID) {
            return [
                'success' => false,
                'error' => 'purchase_id e player_uuid são obrigatórios'
            ];
        }
        
        try {
            // Verificar se a compra existe e pertence a esta loja
            $stmt = $this->pdo->prepare("
                SELECT id, status, player_uuid
                FROM purchases
                WHERE id = ?
                AND store_id = ?
            ");
            
            $stmt->execute([$purchaseId, $this->store_id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$purchase) {
                return [
                    'success' => false,
                    'error' => 'Compra não encontrada'
                ];
            }
            
            if ($purchase['player_uuid'] !== $playerUUID) {
                return [
                    'success' => false,
                    'error' => 'Compra não pertence a este jogador'
                ];
            }
            
            if ($purchase['status'] === 'delivered') {
                return [
                    'success' => true,
                    'message' => 'Compra já foi entregue anteriormente'
                ];
            }
            
            // Atualizar status da compra
            $stmt = $this->pdo->prepare("
                UPDATE purchases
                SET 
                    status = 'delivered',
                    delivered_at = FROM_UNIXTIME(?),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$deliveredAt / 1000, $purchaseId]);
            
            return [
                'success' => true,
                'message' => 'Entrega confirmada com sucesso',
                'purchase_id' => $purchaseId
            ];
            
        } catch (PDOException $e) {
            error_log("Erro ao confirmar entrega: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro ao confirmar entrega'
            ];
        }
    }
    
    /**
     * Registra logout de jogador
     */
    public function playerLogout($data) {
        $playerUUID = $data['player_uuid'] ?? null;
        $playerName = $data['player_name'] ?? null;
        $timestamp = $data['timestamp'] ?? time() * 1000;
        
        if (!$playerUUID) {
            return [
                'success' => false,
                'error' => 'player_uuid é obrigatório'
            ];
        }
        
        try {
            // Registrar evento de logout
            $stmt = $this->pdo->prepare("
                INSERT INTO player_sessions 
                (store_id, player_uuid, player_name, event_type, event_time, created_at)
                VALUES (?, ?, ?, 'logout', FROM_UNIXTIME(?), NOW())
            ");
            
            $stmt->execute([
                $this->store_id,
                $playerUUID,
                $playerName,
                $timestamp / 1000
            ]);
            
            return [
                'success' => true,
                'message' => 'Logout registrado'
            ];
            
        } catch (PDOException $e) {
            error_log("Erro ao registrar logout: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro ao registrar logout'
            ];
        }
    }
    
    /**
     * Atualiza status do servidor
     */
    public function serverStatus($data) {
        $onlinePlayers = $data['online_players'] ?? 0;
        $maxPlayers = $data['max_players'] ?? 0;
        $timestamp = $data['timestamp'] ?? time() * 1000;
        
        try {
            // Atualizar ou inserir status do servidor
            $stmt = $this->pdo->prepare("
                INSERT INTO server_status 
                (store_id, online_players, max_players, last_update)
                VALUES (?, ?, ?, FROM_UNIXTIME(?))
                ON DUPLICATE KEY UPDATE
                    online_players = VALUES(online_players),
                    max_players = VALUES(max_players),
                    last_update = VALUES(last_update)
            ");
            
            $stmt->execute([
                $this->store_id,
                $onlinePlayers,
                $maxPlayers,
                $timestamp / 1000
            ]);
            
            return [
                'success' => true,
                'message' => 'Status atualizado',
                'data' => [
                    'online_players' => $onlinePlayers,
                    'max_players' => $maxPlayers
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("Erro ao atualizar status: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro ao atualizar status'
            ];
        }
    }
}