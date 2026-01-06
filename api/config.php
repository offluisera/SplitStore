<?php
/**
 * ============================================
 * SPLITSTORE - API CONFIG
 * ============================================
 * Configurações da API
 */

// Modo debug (DESATIVAR EM PRODUÇÃO!)
if (!defined('DEBUG')) {
    define('DEBUG', true);
}

// Configurações de erro
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de log
if (!defined('LOG_API_REQUESTS')) {
    define('LOG_API_REQUESTS', true);
}

if (!defined('LOG_FILE')) {
    define('LOG_FILE', __DIR__ . '/../logs/api.log');
}

// Rate limiting (requisições por hora)
if (!defined('RATE_LIMIT')) {
    define('RATE_LIMIT', 1000);
}

// Timeout de conexão (segundos)
if (!defined('CONNECTION_TIMEOUT')) {
    define('CONNECTION_TIMEOUT', 30);
}

// Função de log personalizada
function apiLog($message, $level = 'INFO') {
    if (!LOG_API_REQUESTS) {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    
    // Criar diretório de logs se não existir
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    // Escrever no arquivo
    @file_put_contents(LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
}

// Função para limpar logs antigos
function cleanOldLogs($days = 30) {
    if (!file_exists(LOG_FILE)) {
        return;
    }
    
    $lines = file(LOG_FILE);
    $cutoffDate = date('Y-m-d', strtotime("-$days days"));
    $newLines = [];
    
    foreach ($lines as $line) {
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
            if ($matches[1] >= $cutoffDate) {
                $newLines[] = $line;
            }
        }
    }
    
    if (count($newLines) > 0) {
        file_put_contents(LOG_FILE, implode('', $newLines));
    }
}

// Limpar logs antigos uma vez por dia (executar aleatoriamente)
if (rand(1, 100) === 1) {
    cleanOldLogs(30);
}