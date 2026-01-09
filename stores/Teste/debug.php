<?php
/**
 * ============================================
 * PÁGINA DE DEBUG - SISTEMA DE AUTENTICAÇÃO
 * ============================================
 * URL: stores/Teste/debug.php
 * 
 * ATENÇÃO: REMOVER EM PRODUÇÃO!
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/db.php';

$store_slug = basename(dirname(__FILE__));
$results = [];

// 1. Testa conexão com banco
$results['db_connection'] = [
    'status' => isset($pdo) ? '✅ Conectado' : '❌ Não conectado',
    'pdo_exists' => isset($pdo)
];

if (isset($pdo)) {
    try {
        // 2. Busca loja
        $stmt = $pdo->prepare("SELECT * FROM stores WHERE store_slug = ?");
        $stmt->execute([$store_slug]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $results['store'] = [
            'status' => $store ? '✅ Loja encontrada' : '❌ Loja não encontrada',
            'id' => $store['id'] ?? null,
            'name' => $store['store_name'] ?? null,
            'slug' => $store_slug
        ];
        
        if ($store) {
            // 3. Verifica tabela store_users
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'store_users'");
            $stmt->execute();
            $tableExists = $stmt->fetch();
            
            $results['table_users'] = [
                'status' => $tableExists ? '✅ Tabela existe' : '❌ Tabela não existe'
            ];
            
            if ($tableExists) {
                // 4. Verifica estrutura da tabela
                $stmt = $pdo->prepare("DESCRIBE store_users");
                $stmt->execute();
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $results['table_structure'] = [
                    'status' => '✅ Estrutura OK',
                    'columns' => array_column($columns, 'Field')
                ];
                
                // 5. Conta usuários existentes
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM store_users WHERE store_id = ?");
                $stmt->execute([$store['id']]);
                $count = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $results['users_count'] = [
                    'status' => '✅ Contagem OK',
                    'total' => $count['total']
                ];
                
                // 6. Lista últimos usuários
                $stmt = $pdo->prepare("SELECT id, minecraft_nick, email, created_at FROM store_users WHERE store_id = ? ORDER BY created_at DESC LIMIT 5");
                $stmt->execute([$store['id']]);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $results['last_users'] = [
                    'status' => '✅ Listagem OK',
                    'users' => $users
                ];
            }
            
            // 7. Testa API do Minecraft
            $testNick = 'Notch';
            $url = "https://api.mojang.com/users/profiles/minecraft/{$testNick}";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $results['minecraft_api'] = [
                'status' => $httpCode === 200 ? '✅ API funcionando' : '❌ API com erro',
                'test_nick' => $testNick,
                'http_code' => $httpCode,
                'response' => $response,
                'error' => $error
            ];
            
            // 8. Verifica PASSWORD_ARGON2ID
            $results['password_hash'] = [
                'ARGON2ID_available' => defined('PASSWORD_ARGON2ID') ? '✅ Sim' : '❌ Não (usando BCRYPT)',
                'BCRYPT_available' => defined('PASSWORD_BCRYPT') ? '✅ Sim' : '❌ Não'
            ];
            
            // 9. Testa criação de senha
            $testPassword = 'test123';
            if (defined('PASSWORD_ARGON2ID')) {
                $hash = password_hash($testPassword, PASSWORD_ARGON2ID);
            } else {
                $hash = password_hash($testPassword, PASSWORD_BCRYPT);
            }
            
            $results['hash_test'] = [
                'status' => strlen($hash) > 0 ? '✅ Hash gerado' : '❌ Erro ao gerar hash',
                'hash_length' => strlen($hash),
                'verify' => password_verify($testPassword, $hash) ? '✅ Verificação OK' : '❌ Verificação falhou'
            ];
        }
        
    } catch (Exception $e) {
        $results['error'] = [
            'status' => '❌ Erro',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

// 10. Info do servidor
$results['server_info'] = [
    'PHP_VERSION' => phpversion(),
    'PDO_DRIVERS' => PDO::getAvailableDrivers(),
    'CURL_ENABLED' => function_exists('curl_init') ? '✅ Sim' : '❌ Não',
    'JSON_ENABLED' => function_exists('json_encode') ? '✅ Sim' : '❌ Não',
    'PASSWORD_HASH_ALGOS' => password_algos()
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Sistema de Autenticação</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #0a0a0a;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            padding: 40px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #111;
            border: 2px solid #00ff00;
            border-radius: 10px;
            padding: 30px;
        }
        
        h1 {
            color: #00ff00;
            text-align: center;
            margin-bottom: 30px;
            font-size: 24px;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        
        .section {
            background: #0a0a0a;
            border: 1px solid #00ff0040;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .section h2 {
            color: #00ff00;
            font-size: 16px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
            margin-right: 10px;
        }
        
        .success {
            background: #00ff0020;
            color: #00ff00;
            border: 1px solid #00ff00;
        }
        
        .error {
            background: #ff000020;
            color: #ff0000;
            border: 1px solid #ff0000;
        }
        
        .info-line {
            padding: 8px 0;
            border-bottom: 1px solid #00ff0020;
        }
        
        .info-line:last-child {
            border-bottom: none;
        }
        
        .label {
            color: #00ff0080;
            display: inline-block;
            width: 200px;
            font-weight: bold;
        }
        
        .value {
            color: #00ff00;
        }
        
        pre {
            background: #000;
            border: 1px solid #00ff0040;
            border-radius: 4px;
            padding: 15px;
            overflow-x: auto;
            color: #00ff00;
            font-size: 12px;
            margin-top: 10px;
        }
        
        .warning {
            background: #ff660020;
            border: 1px solid #ff6600;
            color: #ff6600;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .users-table th,
        .users-table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #00ff0040;
        }
        
        .users-table th {
            background: #00ff0020;
            color: #00ff00;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
        }
        
        .users-table td {
            color: #00ff0080;
            font-size: 12px;
        }
        
        .back-btn {
            display: inline-block;
            background: #00ff0020;
            border: 1px solid #00ff00;
            color: #00ff00;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 20px;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background: #00ff00;
            color: #000;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug - Sistema de Autenticação</h1>
        
        <div class="warning">
            ⚠️ ATENÇÃO: Esta página contém informações sensíveis. REMOVA em produção!
        </div>
        
        <?php foreach ($results as $key => $data): ?>
            <div class="section">
                <h2><?= str_replace('_', ' ', strtoupper($key)) ?></h2>
                
                <?php if (isset($data['status'])): ?>
                    <span class="status <?= strpos($data['status'], '✅') !== false ? 'success' : 'error' ?>">
                        <?= $data['status'] ?>
                    </span>
                <?php endif; ?>
                
                <?php foreach ($data as $prop => $value): ?>
                    <?php if ($prop === 'status') continue; ?>
                    
                    <div class="info-line">
                        <span class="label"><?= ucfirst(str_replace('_', ' ', $prop)) ?>:</span>
                        <span class="value">
                            <?php if (is_array($value)): ?>
                                <?php if ($prop === 'users' && !empty($value)): ?>
                                    <table class="users-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Nick</th>
                                                <th>Email</th>
                                                <th>Criado em</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($value as $user): ?>
                                                <tr>
                                                    <td><?= $user['id'] ?></td>
                                                    <td><?= $user['minecraft_nick'] ?></td>
                                                    <td><?= $user['email'] ?></td>
                                                    <td><?= $user['created_at'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <pre><?= json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                                <?php endif; ?>
                            <?php elseif (is_bool($value)): ?>
                                <?= $value ? 'true' : 'false' ?>
                            <?php elseif (is_null($value)): ?>
                                NULL
                            <?php else: ?>
                                <?= htmlspecialchars($value) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        
        <div style="text-align: center;">
            <a href="auth.php" class="back-btn">← Voltar para Login</a>
        </div>
    </div>
</body>
</html>