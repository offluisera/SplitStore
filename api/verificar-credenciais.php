<?php
/**
 * ============================================
 * VERIFICADOR DE CREDENCIAIS
 * ============================================
 * Salve como: api/verificar-credenciais.php
 * Acesse: http://splitstore.com.br/api/verificar-credenciais.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../includes/db.php';

$resultado = [
    'teste' => 'Verificação de Credenciais',
    'timestamp' => date('Y-m-d H:i:s')
];

try {
    // Buscar TODAS as lojas no banco
    $stmt = $pdo->query("
        SELECT 
            id,
            store_name,
            api_key,
            api_secret,
            status,
            created_at
        FROM stores
        ORDER BY id DESC
    ");
    
    $lojas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $resultado['total_lojas'] = count($lojas);
    $resultado['lojas'] = [];
    
    foreach ($lojas as $loja) {
        $resultado['lojas'][] = [
            'id' => $loja['id'],
            'nome' => $loja['store_name'],
            'status' => $loja['status'],
            'api_key' => $loja['api_key'] ?? 'NULL',
            'api_secret' => $loja['api_secret'] ?? 'NULL',
            'api_key_formatacao' => [
                'comprimento' => strlen($loja['api_key'] ?? ''),
                'comeca_com_ca' => substr($loja['api_key'] ?? '', 0, 3) === 'ca_',
                'formato_correto' => preg_match('/^ca_[a-f0-9]{32}$/i', $loja['api_key'] ?? '') ? 'SIM' : 'NÃO'
            ],
            'api_secret_formatacao' => [
                'comprimento' => strlen($loja['api_secret'] ?? ''),
                'comeca_com_ck' => substr($loja['api_secret'] ?? '', 0, 3) === 'ck_',
                'formato_correto' => preg_match('/^ck_[a-f0-9]{48}$/i', $loja['api_secret'] ?? '') ? 'SIM' : 'NÃO'
            ],
            'criada_em' => $loja['created_at']
        ];
    }
    
    // Testar com as credenciais da tela
    $api_key_teste = 'ca_2272e2d3254f47bfcd50b41e29ebcee0';
    $api_secret_teste = 'ck_a2c6b323cc843f1a88b77ee4d90e18139762ce6c27ad95e4';
    
    $resultado['teste_credenciais_tela'] = [
        'api_key' => $api_key_teste,
        'api_secret' => substr($api_secret_teste, 0, 20) . '...'
    ];
    
    // Buscar no banco
    $stmt = $pdo->prepare("
        SELECT 
            id,
            store_name,
            api_key,
            api_secret,
            status
        FROM stores
        WHERE api_key = ? AND api_secret = ?
    ");
    
    $stmt->execute([$api_key_teste, $api_secret_teste]);
    $loja_encontrada = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($loja_encontrada) {
        $resultado['autenticacao'] = [
            'status' => 'SUCESSO',
            'mensagem' => '✅ Credenciais encontradas no banco!',
            'loja' => [
                'id' => $loja_encontrada['id'],
                'nome' => $loja_encontrada['store_name'],
                'status' => $loja_encontrada['status']
            ]
        ];
    } else {
        $resultado['autenticacao'] = [
            'status' => 'FALHOU',
            'mensagem' => '❌ Credenciais NÃO encontradas no banco',
            'possiveis_causas' => [
                '1. As credenciais na tela não batem com as do banco',
                '2. A loja está com status diferente de "active"',
                '3. As credenciais foram geradas mas não salvaram corretamente'
            ]
        ];
        
        // Tentar buscar apenas por API Key
        $stmt = $pdo->prepare("SELECT id, store_name, api_key, api_secret, status FROM stores WHERE api_key = ?");
        $stmt->execute([$api_key_teste]);
        $loja_por_key = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($loja_por_key) {
            $resultado['debug'] = [
                'api_key_encontrada' => 'SIM',
                'api_secret_bate' => $loja_por_key['api_secret'] === $api_secret_teste ? 'SIM' : 'NÃO',
                'api_secret_banco' => substr($loja_por_key['api_secret'], 0, 20) . '...',
                'api_secret_tela' => substr($api_secret_teste, 0, 20) . '...',
                'status_loja' => $loja_por_key['status'],
                'problema' => $loja_por_key['api_secret'] !== $api_secret_teste 
                    ? 'API Secret diferente' 
                    : 'Status não é active'
            ];
        } else {
            $resultado['debug'] = [
                'api_key_encontrada' => 'NÃO',
                'problema' => 'API Key não existe no banco'
            ];
        }
    }
    
    // Verificar headers que chegariam na requisição real
    $resultado['headers_exemplo'] = [
        'X-API-Key' => $_SERVER['HTTP_X_API_KEY'] ?? 'Não enviado',
        'X-API-Secret' => isset($_SERVER['HTTP_X_API_SECRET']) ? 'Recebido' : 'Não enviado'
    ];
    
    // Solução
    $resultado['solucao'] = [
        'passo_1' => 'Vá em: http://splitstore.com.br/client/servers.php',
        'passo_2' => 'Clique em "Regerar Credenciais"',
        'passo_3' => 'Copie as novas credenciais',
        'passo_4' => 'Teste novamente em: http://splitstore.com.br/api/simple_test.php'
    ];
    
} catch (PDOException $e) {
    $resultado['erro'] = [
        'mensagem' => $e->getMessage(),
        'arquivo' => $e->getFile(),
        'linha' => $e->getLine()
    ];
}

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);