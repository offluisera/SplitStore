<?php
/**
 * ============================================
 * SPLITSTORE - DIAGNÓSTICO DO SISTEMA
 * ============================================
 * Acesse: http://splitstore.com.br/diagnostic.php
 * Apague após o diagnóstico por segurança!
 */

header('Content-Type: text/html; charset=utf-8');

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

function testResult($name, $status, $message, $details = null) {
    global $results;
    $results['tests'][] = [
        'name' => $name,
        'status' => $status,
        'message' => $message,
        'details' => $details
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico - SplitStore</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Courier New', monospace; 
            background: #0a0a0a; 
            color: #fff; 
            padding: 40px 20px;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { 
            color: #ef4444; 
            margin-bottom: 30px; 
            font-size: 2em;
            text-transform: uppercase;
        }
        .test-item {
            background: rgba(255,255,255,0.05);
            border-left: 4px solid #666;
            padding: 15px 20px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .test-item.success { border-color: #22c55e; }
        .test-item.error { border-color: #ef4444; }
        .test-item.warning { border-color: #eab308; }
        .test-name { 
            font-weight: bold; 
            margin-bottom: 8px;
            font-size: 1.1em;
        }
        .test-message { 
            color: #999; 
            margin-bottom: 8px;
            line-height: 1.6;
        }
        .test-details {
            background: #000;
            padding: 10px;
            border-radius: 3px;
            font-size: 0.85em;
            margin-top: 10px;
            overflow-x: auto;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 0.75em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge.success { background: #22c55e; color: #000; }
        .badge.error { background: #ef4444; color: #fff; }
        .badge.warning { background: #eab308; color: #000; }
        .summary {
            background: rgba(239,68,68,0.1);
            border: 2px solid #ef4444;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .summary h2 {
            color: #ef4444;
            margin-bottom: 15px;
        }
        pre { 
            white-space: pre-wrap; 
            word-wrap: break-word;
            color: #0f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico do Sistema</h1>
        
        <?php
        // ========================================
        // TESTE 1: PHP VERSION
        // ========================================
        $phpVersion = phpversion();
        $phpOk = version_compare($phpVersion, '7.4', '>=');
        testResult(
            'Versão do PHP',
            $phpOk ? 'success' : 'error',
            $phpOk ? "PHP $phpVersion ✓" : "PHP $phpVersion - Requer 7.4+",
            "Versão atual: $phpVersion"
        );
        
        // ========================================
        // TESTE 2: BANCO DE DADOS
        // ========================================
        try {
            require_once 'includes/db.php';
            
            if (isset($pdo)) {
                // Testa conexão
                $stmt = $pdo->query("SELECT 1");
                testResult(
                    'Conexão com Banco de Dados',
                    'success',
                    'Conexão estabelecida com sucesso ✓',
                    "Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)
                );
                
                // Verifica tabelas
                $requiredTables = [
                    'stores', 
                    'products', 
                    'transactions', 
                    'purchases',
                    'subscriptions',
                    'api_logs',
                    'activity_logs'
                ];
                
                $missingTables = [];
                foreach ($requiredTables as $table) {
                    try {
                        $pdo->query("SELECT 1 FROM $table LIMIT 1");
                    } catch (Exception $e) {
                        $missingTables[] = $table;
                    }
                }
                
                if (empty($missingTables)) {
                    testResult(
                        'Tabelas do Banco',
                        'success',
                        'Todas as tabelas necessárias existem ✓',
                        implode(', ', $requiredTables)
                    );
                } else {
                    testResult(
                        'Tabelas do Banco',
                        'error',
                        'Tabelas faltando: ' . implode(', ', $missingTables),
                        "Execute o arquivo database.sql"
                    );
                }
                
                // Verifica estrutura da tabela stores
                try {
                    $stmt = $pdo->query("DESCRIBE stores");
                    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $requiredColumns = [
                        'id', 'store_name', 'store_slug', 'email', 
                        'cpf', 'phone', 'password_hash', 'plan', 
                        'api_key', 'api_secret', 'status'
                    ];
                    
                    $missingColumns = array_diff($requiredColumns, $columns);
                    
                    if (empty($missingColumns)) {
                        testResult(
                            'Estrutura da Tabela Stores',
                            'success',
                            'Estrutura correta ✓',
                            count($columns) . " colunas encontradas"
                        );
                    } else {
                        testResult(
                            'Estrutura da Tabela Stores',
                            'error',
                            'Colunas faltando: ' . implode(', ', $missingColumns),
                            "Reimporte o database.sql"
                        );
                    }
                } catch (Exception $e) {
                    testResult(
                        'Estrutura da Tabela Stores',
                        'error',
                        'Erro ao verificar estrutura',
                        $e->getMessage()
                    );
                }
                
            } else {
                testResult(
                    'Conexão com Banco de Dados',
                    'error',
                    'Variável $pdo não foi inicializada',
                    "Verifique o arquivo includes/db.php"
                );
            }
        } catch (Exception $e) {
            testResult(
                'Conexão com Banco de Dados',
                'error',
                'Falha na conexão: ' . $e->getMessage(),
                "Verifique as credenciais em includes/db.php"
            );
        }
        
        // ========================================
        // TESTE 3: PERMISSÕES DE ARQUIVOS
        // ========================================
        $paths = [
            'logs' => 'logs/',
            'includes/db.php' => 'includes/db.php',
            'process_checkout.php' => 'process_checkout.php',
            'payment.php' => 'payment.php'
        ];
        
        $allWritable = true;
        $pathsDetails = [];
        
        foreach ($paths as $name => $path) {
            if (file_exists($path)) {
                $writable = is_writable($path);
                $pathsDetails[] = "$name: " . ($writable ? "✓ Gravável" : "✗ Somente leitura");
                if (!$writable && $name === 'logs') {
                    $allWritable = false;
                }
            } else {
                $pathsDetails[] = "$name: ✗ Não existe";
                if ($name === 'logs') {
                    $allWritable = false;
                }
            }
        }
        
        testResult(
            'Permissões de Arquivos',
            $allWritable ? 'success' : 'warning',
            $allWritable ? 'Permissões OK ✓' : 'Ajuste permissões da pasta logs',
            implode("\n", $pathsDetails)
        );
        
        // ========================================
        // TESTE 4: EXTENSÕES PHP
        // ========================================
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'curl', 'mbstring'];
        $missingExtensions = [];
        
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }
        
        if (empty($missingExtensions)) {
            testResult(
                'Extensões PHP',
                'success',
                'Todas as extensões necessárias estão instaladas ✓',
                implode(', ', $requiredExtensions)
            );
        } else {
            testResult(
                'Extensões PHP',
                'error',
                'Extensões faltando: ' . implode(', ', $missingExtensions),
                "Instale via: apt-get install php-" . implode(' php-', $missingExtensions)
            );
        }
        
        // ========================================
        // TESTE 5: SESSION
        // ========================================
        session_start();
        $_SESSION['test'] = 'working';
        
        if (isset($_SESSION['test']) && $_SESSION['test'] === 'working') {
            testResult(
                'Sessões PHP',
                'success',
                'Sessões funcionando corretamente ✓',
                "Session ID: " . session_id()
            );
            unset($_SESSION['test']);
        } else {
            testResult(
                'Sessões PHP',
                'error',
                'Falha ao criar sessão',
                "Verifique permissões em /tmp ou session.save_path"
            );
        }
        
        // ========================================
        // TESTE 6: LOGS DE CHECKOUT
        // ========================================
        $logFile = 'logs/checkout_debug.log';
        if (file_exists($logFile)) {
            $logSize = filesize($logFile);
            $lastLines = array_slice(file($logFile), -10);
            
            testResult(
                'Log de Checkout',
                'success',
                "Arquivo de log existe ✓",
                "Tamanho: " . number_format($logSize) . " bytes\n" .
                "Últimas 10 linhas:\n" . implode('', $lastLines)
            );
        } else {
            testResult(
                'Log de Checkout',
                'warning',
                'Arquivo de log ainda não foi criado',
                "Será criado automaticamente no primeiro checkout"
            );
        }
        
        // ========================================
        // TESTE 7: TESTE DE INSERÇÃO
        // ========================================
        if (isset($pdo)) {
            try {
                // Tenta inserir e remover um registro de teste
                $pdo->beginTransaction();
                
                $testData = [
                    'store_name' => 'TESTE_DIAGNOSTICO',
                    'store_slug' => 'teste-diag-' . uniqid(),
                    'owner_name' => 'Teste Sistema',
                    'email' => 'teste-' . uniqid() . '@teste.com',
                    'cpf' => '12345678901',
                    'phone' => '11999999999',
                    'password_hash' => password_hash('teste123', PASSWORD_BCRYPT),
                    'plan' => 'basic',
                    'billing_cycle' => 'monthly',
                    'api_key' => 'ca_test_' . uniqid(),
                    'api_secret' => 'ck_test_' . uniqid(),
                    'status' => 'pending'
                ];
                
                $stmt = $pdo->prepare("
                    INSERT INTO stores (
                        store_name, store_slug, owner_name, email, cpf, phone,
                        password_hash, plan, billing_cycle, api_key, api_secret, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute(array_values($testData));
                $testId = $pdo->lastInsertId();
                
                // Remove o registro de teste
                $stmt = $pdo->prepare("DELETE FROM stores WHERE id = ?");
                $stmt->execute([$testId]);
                
                $pdo->commit();
                
                testResult(
                    'Teste de Inserção',
                    'success',
                    'INSERT/DELETE funcionando corretamente ✓',
                    "ID temporário criado: $testId"
                );
                
            } catch (Exception $e) {
                $pdo->rollBack();
                testResult(
                    'Teste de Inserção',
                    'error',
                    'Falha ao inserir registro de teste',
                    $e->getMessage()
                );
            }
        }
        
        // ========================================
        // TESTE 8: GERAÇÃO DE CREDENCIAIS
        // ========================================
        try {
            $testKey = 'ca_' . bin2hex(random_bytes(16));
            $testSecret = 'ck_' . bin2hex(random_bytes(24));
            
            $keyValid = preg_match('/^ca_[a-f0-9]{32}$/', $testKey);
            $secretValid = preg_match('/^ck_[a-f0-9]{48}$/', $testSecret);
            
            if ($keyValid && $secretValid) {
                testResult(
                    'Geração de Credenciais',
                    'success',
                    'Geração de API Key/Secret funcionando ✓',
                    "Exemplo gerado:\nKey: $testKey\nSecret: " . substr($testSecret, 0, 20) . "..."
                );
            } else {
                testResult(
                    'Geração de Credenciais',
                    'error',
                    'Formato inválido das credenciais geradas',
                    "Key válida: " . ($keyValid ? 'Sim' : 'Não') . "\n" .
                    "Secret válido: " . ($secretValid ? 'Sim' : 'Não')
                );
            }
        } catch (Exception $e) {
            testResult(
                'Geração de Credenciais',
                'error',
                'Falha ao gerar credenciais',
                $e->getMessage()
            );
        }
        
        // ========================================
        // EXIBIR RESULTADOS
        // ========================================
        $totalTests = count($results['tests']);
        $successCount = 0;
        $errorCount = 0;
        $warningCount = 0;
        
        foreach ($results['tests'] as $test) {
            if ($test['status'] === 'success') $successCount++;
            if ($test['status'] === 'error') $errorCount++;
            if ($test['status'] === 'warning') $warningCount++;
        }
        
        echo '<div class="summary">';
        echo '<h2>📊 Resumo</h2>';
        echo '<p style="font-size: 1.2em; margin-bottom: 15px;">';
        echo "Total de Testes: <strong>$totalTests</strong><br>";
        echo "✓ Sucesso: <strong style=\"color: #22c55e;\">$successCount</strong><br>";
        echo "✗ Erros: <strong style=\"color: #ef4444;\">$errorCount</strong><br>";
        echo "⚠ Avisos: <strong style=\"color: #eab308;\">$warningCount</strong>";
        echo '</p>';
        
        if ($errorCount === 0) {
            echo '<p style="color: #22c55e; font-weight: bold;">✓ Sistema pronto para uso!</p>';
        } else {
            echo '<p style="color: #ef4444; font-weight: bold;">✗ Corrija os erros acima antes de continuar</p>';
        }
        echo '</div>';
        
        foreach ($results['tests'] as $test) {
            $statusClass = $test['status'];
            echo '<div class="test-item ' . $statusClass . '">';
            echo '<div class="test-name">';
            echo '<span class="badge ' . $statusClass . '">' . strtoupper($test['status']) . '</span> ';
            echo htmlspecialchars($test['name']);
            echo '</div>';
            echo '<div class="test-message">' . htmlspecialchars($test['message']) . '</div>';
            if ($test['details']) {
                echo '<div class="test-details"><pre>' . htmlspecialchars($test['details']) . '</pre></div>';
            }
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 40px; padding: 20px; background: rgba(234,179,8,0.1); border: 2px solid #eab308; border-radius: 10px;">
            <h3 style="color: #eab308; margin-bottom: 10px;">⚠️ IMPORTANTE</h3>
            <p style="color: #999; line-height: 1.6;">
                Este arquivo contém informações sensíveis do sistema.<br>
                <strong>APAGUE-O</strong> após resolver os problemas por segurança.<br><br>
                <code style="background: #000; padding: 5px 10px; border-radius: 3px;">rm diagnostic.php</code>
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 40px; color: #666; font-size: 0.9em;">
            <p>SplitStore Diagnostic Tool v1.0</p>
            <p><?= $results['timestamp'] ?></p>
        </div>
    </div>
</body>
</html>