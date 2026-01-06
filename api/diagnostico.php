<?php
/**
 * ============================================
 * SPLITSTORE - DIAGNÓSTICO DE API
 * ============================================
 * Salve como: api/diagnostico.php
 * Acesse: http://seusite.com/api/diagnostico.php
 */

header('Content-Type: application/json; charset=utf-8');

$diagnostico = [
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => 'EXECUTANDO',
];

// 1. Detectar servidor web
$diagnostico['servidor'] = [
    'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido',
    'tipo' => null,
    'configuracao_necessaria' => null
];

if (stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'nginx') !== false) {
    $diagnostico['servidor']['tipo'] = 'NGINX';
    $diagnostico['servidor']['configuracao_necessaria'] = 'nginx';
} elseif (stripos($_SERVER['SERVER_SOFTWARE'] ?? '', 'apache') !== false) {
    $diagnostico['servidor']['tipo'] = 'APACHE';
    $diagnostico['servidor']['configuracao_necessaria'] = 'htaccess';
} else {
    $diagnostico['servidor']['tipo'] = 'OUTRO';
}

// 2. Verificar mod_rewrite (Apache)
if ($diagnostico['servidor']['tipo'] === 'APACHE') {
    $diagnostico['mod_rewrite'] = function_exists('apache_get_modules') && 
                                   in_array('mod_rewrite', apache_get_modules()) 
                                   ? 'ATIVADO' : 'DESCONHECIDO';
}

// 3. Verificar arquivos
$diagnostico['arquivos'] = [
    'index.php' => file_exists(__DIR__ . '/index.php') ? 'OK' : 'FALTANDO',
    '.htaccess' => file_exists(__DIR__ . '/.htaccess') ? 'OK' : 'FALTANDO',
    'PluginAuthMiddleware.php' => file_exists(__DIR__ . '/PluginAuthMiddleware.php') ? 'OK' : 'FALTANDO',
    'PluginController.php' => file_exists(__DIR__ . '/PluginController.php') ? 'OK' : 'FALTANDO',
];

// 4. Testar conexão com banco
try {
    require_once '../includes/db.php';
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM stores");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $diagnostico['banco_dados'] = [
            'status' => 'CONECTADO',
            'lojas_cadastradas' => $result['count']
        ];
    } else {
        $diagnostico['banco_dados'] = [
            'status' => 'ERRO',
            'mensagem' => 'PDO não inicializado'
        ];
    }
} catch (Exception $e) {
    $diagnostico['banco_dados'] = [
        'status' => 'ERRO',
        'mensagem' => $e->getMessage()
    ];
}

// 5. Testar roteamento
$diagnostico['roteamento'] = [
    'uri_atual' => $_SERVER['REQUEST_URI'] ?? 'N/A',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'N/A',
    'path_info' => $_SERVER['PATH_INFO'] ?? 'N/A',
    'query_string' => $_SERVER['QUERY_STRING'] ?? 'N/A'
];

// 6. Testar se index.php é acessível diretamente
$diagnostico['testes_acesso'] = [
    'diagnostico.php' => 'FUNCIONANDO (você está vendo isso)',
    'index.php_direto' => 'Teste acessando: /api/index.php',
    'rota_verify' => 'Teste acessando: /api/plugin/verify'
];

// 7. Headers recebidos
$diagnostico['headers_recebidos'] = [
    'X-API-Key' => $_SERVER['HTTP_X_API_KEY'] ?? 'Não enviado',
    'X-API-Secret' => isset($_SERVER['HTTP_X_API_SECRET']) ? 'Recebido (oculto)' : 'Não enviado',
    'Content-Type' => $_SERVER['CONTENT_TYPE'] ?? 'Não definido'
];

// 8. Verificar permissões
$diagnostico['permissoes'] = [
    'pasta_api_gravavel' => is_writable(__DIR__) ? 'SIM' : 'NÃO',
    'index_php_legivel' => is_readable(__DIR__ . '/index.php') ? 'SIM' : 'NÃO'
];

// 9. Recomendações baseadas no servidor
if ($diagnostico['servidor']['tipo'] === 'NGINX') {
    $diagnostico['acoes_necessarias'] = [
        'status' => 'CONFIGURAÇÃO OBRIGATÓRIA',
        'motivo' => 'NGINX não lê arquivos .htaccess',
        'solucao' => [
            '1. Adicionar bloco location no nginx.conf',
            '2. Ou criar arquivo api.conf no /etc/nginx/sites-available/',
            '3. Reiniciar nginx: sudo service nginx restart',
            '4. Ver arquivo de configuração gerado'
        ]
    ];
} elseif ($diagnostico['servidor']['tipo'] === 'APACHE') {
    if (!file_exists(__DIR__ . '/.htaccess')) {
        $diagnostico['acoes_necessarias'] = [
            'status' => 'ARQUIVO FALTANDO',
            'motivo' => 'Arquivo .htaccess não encontrado',
            'solucao' => 'Criar arquivo .htaccess na pasta /api/'
        ];
    } else {
        $diagnostico['acoes_necessarias'] = [
            'status' => 'VERIFICAR',
            'solucao' => 'Se ainda não funcionar, verificar se mod_rewrite está ativo'
        ];
    }
}

// 10. URL de teste
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host;

$diagnostico['urls_teste'] = [
    'diagnostico' => $baseUrl . '/api/diagnostico.php',
    'index_direto' => $baseUrl . '/api/index.php',
    'verificar_rota' => $baseUrl . '/api/plugin/verify',
    'pagina_teste' => $baseUrl . '/api/simple_test.php'
];

echo json_encode($diagnostico, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);