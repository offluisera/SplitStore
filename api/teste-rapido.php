<?php
/**
 * ============================================
 * TESTE RÁPIDO - SEM NECESSIDADE DE CONFIG
 * ============================================
 * Salve como: api/teste-rapido.php
 * Acesse: http://splitstore.com.br/api/teste-rapido.php
 */

header('Content-Type: application/json; charset=utf-8');

$resultado = [
    'teste' => 'SplitStore API - Teste Rápido',
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => []
];

// Teste 1: PHP está funcionando?
$resultado['status']['php'] = [
    'funciona' => true,
    'versao' => PHP_VERSION,
    'mensagem' => '✅ PHP está funcionando'
];

// Teste 2: Arquivos existem?
$arquivos = [
    'index.php',
    'PluginAuthMiddleware.php', 
    'PluginController.php',
    'config.php'
];

$arquivos_ok = true;
$arquivos_status = [];

foreach ($arquivos as $arquivo) {
    $existe = file_exists(__DIR__ . '/' . $arquivo);
    $arquivos_status[$arquivo] = $existe ? '✅ OK' : '❌ FALTANDO';
    if (!$existe) $arquivos_ok = false;
}

$resultado['status']['arquivos'] = [
    'funciona' => $arquivos_ok,
    'detalhes' => $arquivos_status,
    'mensagem' => $arquivos_ok ? '✅ Todos os arquivos encontrados' : '❌ Alguns arquivos estão faltando'
];

// Teste 3: Banco de dados conecta?
try {
    require_once '../includes/db.php';
    
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM stores");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $resultado['status']['banco_dados'] = [
            'funciona' => true,
            'lojas_cadastradas' => $count,
            'mensagem' => '✅ Banco conectado'
        ];
    } else {
        $resultado['status']['banco_dados'] = [
            'funciona' => false,
            'mensagem' => '❌ PDO não inicializado'
        ];
    }
} catch (Exception $e) {
    $resultado['status']['banco_dados'] = [
        'funciona' => false,
        'erro' => $e->getMessage(),
        'mensagem' => '❌ Erro ao conectar no banco'
    ];
}

// Teste 4: Roteamento funciona?
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

// Testar se a rota /plugin/verify retorna 404 ou 401
$ch = curl_init($protocol . '://' . $host . '/api/plugin/verify');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Key: test',
    'X-API-Secret: test'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$roteamento_ok = ($httpCode !== 404);

$resultado['status']['roteamento'] = [
    'funciona' => $roteamento_ok,
    'codigo_http' => $httpCode,
    'mensagem' => $roteamento_ok 
        ? '✅ Roteamento configurado (retornou ' . $httpCode . ')' 
        : '❌ Roteamento NÃO configurado (retornou 404)',
    'explicacao' => $roteamento_ok
        ? 'O NGINX está processando as rotas da API corretamente'
        : 'O NGINX está retornando 404. Você PRECISA adicionar a configuração no painel!'
];

// Resumo
$tudo_ok = $resultado['status']['php']['funciona'] && 
           $resultado['status']['arquivos']['funciona'] && 
           $resultado['status']['banco_dados']['funciona'] && 
           $resultado['status']['roteamento']['funciona'];

$resultado['resumo'] = [
    'tudo_funcionando' => $tudo_ok,
    'mensagem' => $tudo_ok 
        ? '🎉 TUDO OK! Sua API está pronta para usar!' 
        : '⚠️ Alguns problemas precisam ser corrigidos'
];

// Ações necessárias
if (!$tudo_ok) {
    $resultado['acoes_necessarias'] = [];
    
    if (!$resultado['status']['arquivos']['funciona']) {
        $resultado['acoes_necessarias'][] = '1. Faça upload de todos os arquivos da API';
    }
    
    if (!$resultado['status']['banco_dados']['funciona']) {
        $resultado['acoes_necessarias'][] = '2. Verifique a conexão com o banco de dados (includes/db.php)';
    }
    
    if (!$resultado['status']['roteamento']['funciona']) {
        $resultado['acoes_necessarias'][] = '3. ADICIONE A CONFIGURAÇÃO DO NGINX NO PAINEL (veja o guia)';
    }
}

// URLs de teste
$resultado['proximos_passos'] = [
    'teste_completo' => $protocol . '://' . $host . '/api/diagnostico.php',
    'verificar_nginx' => $protocol . '://' . $host . '/api/verify-nginx.php',
    'pagina_teste' => $protocol . '://' . $host . '/api/simple_test.php'
];

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);