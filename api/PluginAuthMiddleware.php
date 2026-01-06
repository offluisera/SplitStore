<?php
/**
 * ============================================
 * SPLITSTORE - PLUGIN AUTH MIDDLEWARE
 * ============================================
 * Middleware de autenticação para API do plugin
 */

class PluginAuthMiddleware {
    
    /**
     * Autentica a requisição do plugin
     */
    public static function authenticate() {
        global $pdo;
        
        // Obter headers de autenticação (suporta ambos os formatos)
        $headers = getallheaders();
        
        // Tentar diferentes variações dos headers
        $apiKey = $headers['X-API-Key'] ?? 
                  $headers['X-Api-Key'] ?? 
                  $_SERVER['HTTP_X_API_KEY'] ?? 
                  null;
                  
        $apiSecret = $headers['X-API-Secret'] ?? 
                     $headers['X-Api-Secret'] ?? 
                     $_SERVER['HTTP_X_API_SECRET'] ?? 
                     null;
        
        error_log("API Key recebida: " . ($apiKey ?? 'null'));
        error_log("API Secret recebida: " . ($apiSecret ? substr($apiSecret, 0, 10) . '...' : 'null'));
        
        // Validar presença das credenciais
        if (empty($apiKey) || empty($apiSecret)) {
            return [
                'success' => false,
                'message' => 'Credenciais não fornecidas. Headers X-API-Key e X-API-Secret são obrigatórios.'
            ];
        }
        
        // Validar formato das credenciais
        if (!self::validateCredentialFormat($apiKey, $apiSecret)) {
            return [
                'success' => false,
                'message' => 'Formato de credenciais inválido.'
            ];
        }
        
        try {
            // Buscar loja pelas credenciais
            $stmt = $pdo->prepare("
                SELECT id, store_name, plan, status
                FROM stores
                WHERE api_key = ? 
                AND api_secret = ?
                AND status = 'active'
            ");
            
            $stmt->execute([$apiKey, $apiSecret]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$store) {
                error_log("Credenciais inválidas ou loja inativa");
                return [
                    'success' => false,
                    'message' => 'Credenciais inválidas ou loja inativa.'
                ];
            }
            
            // Atualizar último acesso
            self::updateLastAccess($pdo, $store['id']);
            
            error_log("Autenticação bem-sucedida para loja: " . $store['store_name']);
            
            return [
                'success' => true,
                'store_id' => $store['id'],
                'store_name' => $store['store_name'],
                'plan' => $store['plan']
            ];
            
        } catch (PDOException $e) {
            error_log("Erro na autenticação: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao validar credenciais.'
            ];
        }
    }
    
    /**
     * Valida formato das credenciais
     */
    private static function validateCredentialFormat($apiKey, $apiSecret) {
        // API Key deve começar com 'ca_' e ter 35 caracteres
        if (!preg_match('/^ca_[a-f0-9]{32}$/i', $apiKey)) {
            error_log("Formato de API Key inválido: " . $apiKey);
            return false;
        }
        
        // API Secret deve começar com 'ck_' e ter 51 caracteres
        if (!preg_match('/^ck_[a-f0-9]{48}$/i', $apiSecret)) {
            error_log("Formato de API Secret inválido");
            return false;
        }
        
        return true;
    }
    
    /**
     * Atualiza timestamp do último acesso da loja
     */
    private static function updateLastAccess($pdo, $store_id) {
        try {
            $stmt = $pdo->prepare("
                UPDATE stores 
                SET last_api_access = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$store_id]);
        } catch (Exception $e) {
            error_log("Erro ao atualizar last_api_access: " . $e->getMessage());
        }
    }
}