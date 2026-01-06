<?php
/**
 * ============================================
 * TESTE DE AUTENTICAÇÃO DIRETO
 * ============================================
 * Salve como: api/teste-auth.php
 * Acesse: http://splitstore.com.br/api/teste-auth.php
 * 
 * Simula exatamente o que acontece quando o plugin conecta
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../includes/db.php';

$resultado = [
    'teste' => 'Autenticação Plugin',
    'timestamp' => date('Y-m-d H:i:s')
];

// Credenciais da tela
$api_key = 'ca_2272e2d3254f47bfcd50b41e29ebcee0';
$api_secret = 'ck_a2c6b323cc843f1a88b77ee4d90e18139762ce6c27ad95e4';

$resultado['credenciais_testadas'] = [
    'api_key' => $api_key,
    'api_secret' => substr($api_secret, 0, 20) . '...'
];

// PASSO 1: Validar formato
$resultado['passo_1_formato'] = [
    'titulo' => 'Validação de Formato'
];

$api_key_valido = preg_match('/^ca_[a-f0-9]{32}$/i', $api_key);
$api_secret_valido = preg_match('/^ck_[a-f0-9]{48}$/i', $api_secret);

$resultado['passo_1_formato']['api_key'] = [
    'formato' => $api_key_valido ? '✅ VÁLIDO' : '❌ INVÁLIDO',
    'comprimento' => strlen($api_key),
    'esperado' => 35,
    'comeca_com_ca' => substr($api_key, 0, 3) === 'ca_' ? 'SIM' : 'NÃO'
];

$resultado['passo_1_formato']['api_secret'] = [
    'formato' => $api_secret_valido ? '✅ VÁLIDO' : '❌ INVÁLIDO',
    'comprimento' => strlen($api_secret),
    'esperado' => 51,
    'comeca_com_ck' => substr($api_secret, 0, 3) === 'ck_' ? 'SIM' : 'NÃO'
];

if (!$api_key_valido || !$api_secret_valido) {
    $resultado['erro'] = 'Formato de credenciais inválido';
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// PASSO 2: Buscar no banco (EXATAMENTE como o PluginAuthMiddleware faz)
$resultado['passo_2_banco'] = [
    'titulo' => 'Busca no Banco de Dados'
];

try {
    $stmt = $pdo->prepare("
        SELECT id, store_name, plan, status
        FROM stores
        WHERE api_key = ? 
        AND api_secret = ?
        AND status = 'active'
    ");
    
    $stmt->execute([$api_key, $api_secret]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($store) {
        $resultado['passo_2_banco']['status'] = '✅ ENCONTRADA';
        $resultado['passo_2_banco']['loja'] = [
            'id' => $store['id'],
            'nome' => $store['store_name'],
            'plano' => $store['plan'],
            'status' => $store['status']
        ];
        
        $resultado['autenticacao'] = [
            'sucesso' => true,
            'mensagem' => '✅ AUTENTICAÇÃO BEM-SUCEDIDA!',
            'store_id' => $store['id']
        ];
        
    } else {
        $resultado['passo_2_banco']['status'] = '❌ NÃO ENCONTRADA';
        
        // Debug: buscar por partes
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM stores WHERE api_key = ?");
        $stmt->execute([$api_key]);
        $tem_key = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM stores WHERE api_secret = ?");
        $stmt->execute([$api_secret]);
        $tem_secret = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
        
        $stmt = $pdo->prepare("SELECT id, store_name, status, api_key, api_secret FROM stores WHERE api_key = ?");
        $stmt->execute([$api_key]);
        $loja_por_key = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $resultado['debug'] = [
            'api_key_existe' => $tem_key ? 'SIM' : 'NÃO',
            'api_secret_existe' => $tem_secret ? 'SIM' : 'NÃO'
        ];
        
        if ($loja_por_key) {
            $resultado['debug']['loja_encontrada_por_key'] = [
                'id' => $loja_por_key['id'],
                'nome' => $loja_por_key['store_name'],
                'status' => $loja_por_key['status'],
                'api_secret_bate' => $loja_por_key['api_secret'] === $api_secret ? 'SIM' : 'NÃO'
            ];
            
            if ($loja_por_key['api_secret'] !== $api_secret) {
                $resultado['problema'] = [
                    'tipo' => 'API SECRET DIFERENTE',
                    'api_secret_banco' => $loja_por_key['api_secret'],
                    'api_secret_testado' => $api_secret,
                    'diferenca' => 'As credenciais da tela não batem com as do banco'
                ];
            } elseif ($loja_por_key['status'] !== 'active') {
                $resultado['problema'] = [
                    'tipo' => 'STATUS INVÁLIDO',
                    'status_banco' => $loja_por_key['status'],
                    'status_esperado' => 'active',
                    'solucao' => 'Ative a loja no banco de dados'
                ];
            }
        } else {
            $resultado['problema'] = [
                'tipo' => 'CREDENCIAIS NÃO EXISTEM',
                'solucao' => 'Gere novas credenciais em /client/servers.php'
            ];
        }
        
        $resultado['autenticacao'] = [
            'sucesso' => false,
            'mensagem' => '❌ AUTENTICAÇÃO FALHOU'
        ];
    }
    
} catch (PDOException $e) {
    $resultado['erro_banco'] = [
        'mensagem' => $e->getMessage(),
        'codigo' => $e->getCode()
    ];
}

// Verificar todas as lojas
try {
    $stmt = $pdo->query("SELECT id, store_name, api_key, api_secret, status FROM stores ORDER BY id DESC LIMIT 5");
    $todas_lojas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $resultado['todas_lojas_banco'] = [];
    foreach ($todas_lojas as $loja) {
        $resultado['todas_lojas_banco'][] = [
            'id' => $loja['id'],
            'nome' => $loja['store_name'],
            'status' => $loja['status'],
            'tem_api_key' => !empty($loja['api_key']),
            'tem_api_secret' => !empty($loja['api_secret']),
            'api_key_preview' => !empty($loja['api_key']) ? substr($loja['api_key'], 0, 15) . '...' : 'VAZIO',
            'api_secret_preview' => !empty($loja['api_secret']) ? substr($loja['api_secret'], 0, 15) . '...' : 'VAZIO'
        ];
    }
} catch (Exception $e) {
    $resultado['erro_listagem'] = $e->getMessage();
}

// Próximos passos
if (!$resultado['autenticacao']['sucesso']) {
    $resultado['proximos_passos'] = [
        '1' => 'Acesse: http://splitstore.com.br/client/servers.php',
        '2' => 'Clique em "Regerar Credenciais"',
        '3' => 'Aguarde a confirmação de sucesso',
        '4' => 'Copie as novas credenciais',
        '5' => 'Teste novamente em: http://splitstore.com.br/api/simple_test.php'
    ];
}

echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);