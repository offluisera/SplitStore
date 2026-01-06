<?php
/**
 * ============================================
 * VERIFICADOR DE CONFIGURAÇÃO NGINX
 * ============================================
 * Salve como: api/verify-nginx.php
 * Acesse: http://seu-dominio.com/api/verify-nginx.php
 * 
 * Este script ajuda a identificar problemas
 * ============================================
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Verificador NGINX - SplitStore</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Courier New', monospace; 
            background: #0a0a0a; 
            color: #fff; 
            padding: 40px 20px; 
        }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { 
            color: #ef4444; 
            margin-bottom: 30px; 
            font-size: 2em; 
            text-transform: uppercase;
        }
        .section { 
            background: rgba(255,255,255,0.05); 
            border: 1px solid rgba(255,255,255,0.1); 
            border-radius: 10px; 
            padding: 20px; 
            margin-bottom: 20px; 
        }
        .section h2 { 
            color: #ef4444; 
            font-size: 1.2em; 
            margin-bottom: 15px; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status { 
            display: inline-block; 
            padding: 5px 15px; 
            border-radius: 5px; 
            font-weight: bold; 
            font-size: 0.9em;
        }
        .status.ok { background: rgba(34,197,94,0.2); color: #22c55e; }
        .status.error { background: rgba(239,68,68,0.2); color: #ef4444; }
        .status.warning { background: rgba(234,179,8,0.2); color: #eab308; }
        .info { 
            background: rgba(0,0,0,0.3); 
            padding: 15px; 
            border-radius: 5px; 
            margin-top: 10px;
            font-size: 0.9em;
            line-height: 1.6;
        }
        .code { 
            background: #000; 
            padding: 15px; 
            border-radius: 5px; 
            border: 1px solid rgba(239,68,68,0.3);
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            overflow-x: auto;
            margin-top: 10px;
        }
        .code pre { margin: 0; color: #0f0; }
        .test-item { 
            padding: 10px; 
            margin: 5px 0; 
            background: rgba(0,0,0,0.3); 
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .icon { 
            width: 24px; 
            height: 24px; 
            display: inline-block;
            text-align: center;
        }
        .help { 
            background: rgba(234,179,8,0.1); 
            border-left: 4px solid #eab308; 
            padding: 15px; 
            margin-top: 15px;
            border-radius: 5px;
        }
        .help h3 { color: #eab308; margin-bottom: 10px; font-size: 1em; }
        ul { list-style: none; padding-left: 20px; }
        li { margin: 5px 0; }
        li:before { content: "→ "; color: #ef4444; }
        a { color: #ef4444; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Verificador de Configuração NGINX</h1>
        
        <?php
        $checks = [];
        $issues = [];
        
        // 1. Verificar servidor web
        $server = $_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido';
        $isNginx = stripos($server, 'nginx') !== false;
        
        $checks['servidor'] = [
            'nome' => 'Servidor Web',
            'valor' => $server,
            'status' => $isNginx ? 'ok' : 'warning',
            'mensagem' => $isNginx ? 'NGINX detectado ✓' : 'Servidor não é NGINX'
        ];
        
        if (!$isNginx) {
            $issues[] = [
                'titulo' => 'Servidor não é NGINX',
                'descricao' => 'Este guia é para NGINX. Se você usa Apache, precisa de um .htaccess diferente.',
                'solucao' => 'Verifique qual servidor web você está usando no painel.'
            ];
        }
        
        // 2. Testar acesso ao index.php
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $indexUrl = $protocol . '://' . $host . '/api/index.php';
        
        $indexWorking = false;
        $ch = curl_init($indexUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $indexWorking = ($httpCode === 200);
        
        $checks['index_php'] = [
            'nome' => 'Acesso ao index.php',
            'valor' => $indexUrl,
            'status' => $indexWorking ? 'ok' : 'error',
            'mensagem' => $indexWorking ? 'index.php acessível ✓' : 'index.php não responde'
        ];
        
        if (!$indexWorking) {
            $issues[] = [
                'titulo' => 'index.php não está acessível',
                'descricao' => 'O arquivo index.php da API não está respondendo.',
                'solucao' => 'Verifique se o arquivo existe em: /www/wwwroot/seu-dominio/api/index.php'
            ];
        }
        
        // 3. Testar roteamento
        $verifyUrl = $protocol . '://' . $host . '/api/plugin/verify';
        
        $routingWorking = false;
        $ch = curl_init($verifyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: test',
            'X-API-Secret: test'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // 401 é esperado (credenciais inválidas), 404 significa que roteamento não funciona
        $routingWorking = ($httpCode !== 404);
        
        $checks['roteamento'] = [
            'nome' => 'Roteamento de Rotas',
            'valor' => $verifyUrl,
            'status' => $routingWorking ? 'ok' : 'error',
            'mensagem' => $routingWorking ? 'Rotas funcionando ✓' : 'Rotas retornam 404 (configuração NGINX necessária)'
        ];
        
        if (!$routingWorking) {
            $issues[] = [
                'titulo' => 'Roteamento não está funcionando',
                'descricao' => 'As rotas da API estão retornando 404. Isso significa que o NGINX não está processando as URLs corretamente.',
                'solucao' => 'Você precisa adicionar a configuração dos blocos location no arquivo de configuração do seu site no NGINX.'
            ];
        }
        
        // 4. Verificar arquivos necessários
        $requiredFiles = [
            'index.php' => 'Router principal',
            'PluginAuthMiddleware.php' => 'Autenticação',
            'PluginController.php' => 'Controller',
            'config.php' => 'Configurações'
        ];
        
        $missingFiles = [];
        foreach ($requiredFiles as $file => $desc) {
            if (!file_exists(__DIR__ . '/' . $file)) {
                $missingFiles[] = $file . ' (' . $desc . ')';
            }
        }
        
        $checks['arquivos'] = [
            'nome' => 'Arquivos Necessários',
            'valor' => count($missingFiles) === 0 ? 'Todos presentes' : count($missingFiles) . ' faltando',
            'status' => count($missingFiles) === 0 ? 'ok' : 'error',
            'mensagem' => count($missingFiles) === 0 ? 'Todos os arquivos encontrados ✓' : 'Arquivos faltando'
        ];
        
        if (count($missingFiles) > 0) {
            $issues[] = [
                'titulo' => 'Arquivos faltando',
                'descricao' => 'Os seguintes arquivos não foram encontrados: ' . implode(', ', $missingFiles),
                'solucao' => 'Faça upload de todos os arquivos da API para a pasta /api/'
            ];
        }
        
        // 5. Verificar PHP-FPM
        $phpVersion = PHP_VERSION;
        $checks['php'] = [
            'nome' => 'Versão do PHP',
            'valor' => $phpVersion,
            'status' => 'ok',
            'mensagem' => 'PHP ' . $phpVersion . ' ✓'
        ];
        
        // 6. Verificar permissões
        $writable = is_writable(__DIR__);
        $checks['permissoes'] = [
            'nome' => 'Permissões',
            'valor' => $writable ? 'Gravável' : 'Somente leitura',
            'status' => $writable ? 'ok' : 'warning',
            'mensagem' => $writable ? 'Pasta gravável ✓' : 'Pasta não é gravável'
        ];
        
        // Exibir resultados
        ?>
        
        <div class="section">
            <h2>📊 Resultados da Verificação</h2>
            <?php foreach ($checks as $check): ?>
                <div class="test-item">
                    <div>
                        <strong><?= htmlspecialchars($check['nome']) ?>:</strong><br>
                        <small style="color: #999;"><?= htmlspecialchars($check['valor']) ?></small>
                    </div>
                    <span class="status <?= $check['status'] ?>">
                        <?= $check['mensagem'] ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($issues) > 0): ?>
            <div class="section">
                <h2>⚠️ Problemas Encontrados</h2>
                <?php foreach ($issues as $issue): ?>
                    <div class="help">
                        <h3><?= htmlspecialchars($issue['titulo']) ?></h3>
                        <p style="margin: 10px 0;"><?= htmlspecialchars($issue['descricao']) ?></p>
                        <p><strong>Solução:</strong> <?= htmlspecialchars($issue['solucao']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="section">
                <h2>✅ Tudo Certo!</h2>
                <p class="info" style="color: #22c55e;">
                    Nenhum problema detectado. Sua API está configurada corretamente!
                </p>
            </div>
        <?php endif; ?>
        
        <?php if (!$routingWorking): ?>
            <div class="section">
                <h2>🔧 Como Resolver o Problema de Roteamento</h2>
                
                <div class="info">
                    <p><strong>Você precisa adicionar a configuração NGINX no arquivo do seu site.</strong></p>
                </div>
                
                <h3 style="color: #ef4444; margin: 20px 0 10px;">Passo 1: Encontrar o arquivo</h3>
                <p class="info">
                    No seu painel (BT-Panel/aaPanel):<br>
                    • Vá em <strong>"Sites"</strong><br>
                    • Clique no seu domínio<br>
                    • Clique em <strong>"Configurações"</strong><br>
                    • Clique em <strong>"Arquivo Config"</strong> ou <strong>"Config File"</strong>
                </p>
                
                <h3 style="color: #ef4444; margin: 20px 0 10px;">Passo 2: Copiar e colar</h3>
                <p class="info">
                    Encontre os outros blocos <code>location</code> no arquivo e cole DEPOIS deles, ANTES da última chave <code>}</code>:
                </p>
                
                <div class="code">
                    <pre># Configuração da API
location /api/ {
    try_files $uri $uri/ /api/index.php?$query_string;
    
    add_header 'Access-Control-Allow-Origin' '*' always;
    add_header 'Access-Control-Allow-Methods' 'GET, POST, OPTIONS' always;
    add_header 'Access-Control-Allow-Headers' 'X-API-Key, X-API-Secret, Content-Type' always;
    
    if ($request_method = 'OPTIONS') {
        return 204;
    }
}

location ~ ^/api/.*\.php$ {
    include /www/server/nginx/conf/fastcgi.conf;
    fastcgi_pass unix:/tmp/php-cgi-<?= substr($phpVersion, 0, 1) . substr($phpVersion, 2, 1) ?>.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}</pre>
                </div>
                
                <h3 style="color: #ef4444; margin: 20px 0 10px;">Passo 3: Salvar e testar</h3>
                <p class="info">
                    • Clique em <strong>"Salvar"</strong><br>
                    • O painel vai testar automaticamente<br>
                    • Se der OK, o NGINX será reiniciado<br>
                    • Recarregue esta página para verificar novamente
                </p>
            </div>
        <?php endif; ?>
        
        <div class="section">
            <h2>🔗 Links Úteis</h2>
            <ul>
                <li><a href="/api/diagnostico.php" target="_blank">Diagnóstico Completo</a></li>
                <li><a href="/api/index.php" target="_blank">Testar index.php</a></li>
                <li><a href="/api/simple_test.php" target="_blank">Página de Testes</a></li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 40px; color: #666; font-size: 0.9em;">
            SplitStore API Verificador v1.0
        </div>
    </div>
</body>
</html>