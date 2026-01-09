<?php
/**
 * ========================================================================
 * SCRIPT AUTOMÁTICO: ATUALIZA TODAS AS PÁGINAS PARA USAR THEME ENGINE
 * ========================================================================
 * 
 * Salve como: stores/Teste/update_pages.php
 * Acesse: http://seu-site.com/stores/Teste/update_pages.php
 * 
 * ⚠️ EXECUTE APENAS UMA VEZ E DELETE DEPOIS!
 */

$store_dir = __DIR__;
$backup_dir = $store_dir . '/backups_' . date('Ymd_His');

// Páginas que serão atualizadas
$pages = [
    'loja.php',
    'noticias.php', 
    'wiki.php',
    'regras.php',
    'equipe.php'
];

$results = [];
$errors = [];

// Criar backup
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
    $results[] = "✅ Backup criado: $backup_dir";
}

foreach ($pages as $page) {
    $file = $store_dir . '/' . $page;
    
    if (!file_exists($file)) {
        $errors[] = "❌ Arquivo não encontrado: $page";
        continue;
    }
    
    try {
        // Backup
        copy($file, $backup_dir . '/' . $page);
        
        // Ler conteúdo
        $content = file_get_contents($file);
        $original = $content;
        
        // ==========================================
        // 1. ADICIONAR THEME ENGINE REQUIRE
        // ==========================================
        if (!strpos($content, "require_once '../../includes/theme_engine.php'")) {
            $content = str_replace(
                "require_once '../../includes/db.php';",
                "require_once '../../includes/db.php';\nrequire_once '../../includes/theme_engine.php'; // ← Theme Engine",
                $content
            );
        }
        
        // ==========================================
        // 2. ADICIONAR INICIALIZAÇÃO DO THEME
        // ==========================================
        if (!strpos($content, '$theme = new ThemeEngine')) {
            // Procura onde a loja é buscada e adiciona logo depois
            $pattern = '/(if \(\!\$store\) die\(.*?\);)/s';
            $replacement = '$1' . "\n    \n    // Inicializa Theme Engine\n    \$theme = new ThemeEngine(\$pdo, \$store['id']);\n    \n    // Busca menu para header\n    \$stmt = \$pdo->prepare(\"\n        SELECT * FROM store_menu \n        WHERE store_id = ? AND is_enabled = 1\n        ORDER BY order_position ASC\n    \");\n    \$stmt->execute([\$store['id']]);\n    \$menu_items = \$stmt->fetchAll(PDO::FETCH_ASSOC);";
            
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        // ==========================================
        // 3. SUBSTITUIR <HEAD> CONTENT
        // ==========================================
        // Remove imports de fontes antigas
        $content = preg_replace(
            '/<link[^>]*fonts\.googleapis\.com[^>]*>/i',
            '',
            $content
        );
        
        // Adiciona renderHead() após <title>
        if (!strpos($content, '$theme->renderHead()')) {
            $content = preg_replace(
                '/(<title>.*?<\/title>)/s',
                '$1' . "\n    \n    <?php \$theme->renderHead(); // ← Theme Engine CSS + Fonts ?>\n    ",
                $content
            );
        }
        
        // ==========================================
        // 4. ADICIONAR HEADER COMPONENT
        // ==========================================
        if (!strpos($content, "include __DIR__ . '/components/header.php'")) {
            // Remove headers antigos
            $content = preg_replace(
                '/<header.*?<\/header>/s',
                '<?php include __DIR__ . \'/components/header.php\'; ?>',
                $content,
                1 // Apenas o primeiro
            );
        }
        
        // ==========================================
        // 5. ADICIONAR SCRIPTS ANTES DE </body>
        // ==========================================
        if (!strpos($content, '$theme->renderScripts()')) {
            $content = preg_replace(
                '/(<script>\s*lucide\.createIcons\(\);)/i',
                '<?php $theme->renderScripts(); // ← Theme Engine JS ?>' . "\n\n    " . '$1',
                $content
            );
        }
        
        // ==========================================
        // 6. ADICIONAR CONFIG DO TAILWIND
        // ==========================================
        if (!strpos($content, 'tailwind.config')) {
            $tailwindConfig = "    <script>\n        tailwind.config = {\n            theme: {\n                extend: {\n                    colors: {\n                        primary: '<?= \$primaryColor ?>',\n                        secondary: '<?= \$store[\"secondary_color\"] ?? \"#0f172a\" ?>'\n                    }\n                }\n            }\n        }\n    </script>";
            
            $content = str_replace(
                '<script src="https://cdn.tailwindcss.com"></script>',
                '<script src="https://cdn.tailwindcss.com"></script>' . "\n" . $tailwindConfig,
                $content
            );
        }
        
        // Salvar
        if ($content !== $original) {
            file_put_contents($file, $content);
            $results[] = "✅ $page atualizado com sucesso!";
        } else {
            $results[] = "ℹ️ $page já estava atualizado";
        }
        
    } catch (Exception $e) {
        $errors[] = "❌ Erro em $page: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 Atualização de Páginas - Theme Engine</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 100%);
            color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 40px 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 50px;
            padding: 40px;
            background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(139, 92, 246, 0.3);
        }
        
        .header h1 {
            font-size: 42px;
            font-weight: 900;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 18px;
            opacity: 0.95;
        }
        
        .warning {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
        }
        
        .warning strong {
            font-size: 22px;
            display: block;
            margin-bottom: 10px;
        }
        
        .section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
        }
        
        .section h2 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 20px;
            color: #8b5cf6;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .result-item {
            padding: 15px 20px;
            margin: 10px 0;
            border-radius: 10px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
        }
        
        .result-item:hover {
            transform: translateX(5px);
        }
        
        .result-item.success {
            background: rgba(16, 185, 129, 0.1);
            border-left: 4px solid #10b981;
        }
        
        .result-item.error {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid #ef4444;
        }
        
        .result-item.info {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #3b82f6;
        }
        
        .icon {
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 48px;
            font-weight: 900;
            background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.7;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 35px;
            background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 10px;
            transition: all 0.3s;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(139, 92, 246, 0.5);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }
        
        .actions {
            text-align: center;
            margin-top: 40px;
        }
        
        .code {
            background: #000;
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            color: #8b5cf6;
        }
        
        footer {
            text-align: center;
            margin-top: 60px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="header">
            <h1>🚀 Atualização Completa</h1>
            <p>Theme Engine integrado com sucesso em todas as páginas!</p>
        </div>
        
        <div class="warning">
            <strong>⚠️ ATENÇÃO!</strong>
            <p>Delete este arquivo (update_pages.php) após verificar que tudo está funcionando!</p>
        </div>
        
        <!-- RESULTADOS -->
        <?php if (!empty($results)): ?>
        <div class="section">
            <h2>✅ Operações Realizadas</h2>
            <?php foreach ($results as $result): ?>
                <div class="result-item <?= strpos($result, '✅') !== false ? 'success' : 'info' ?>">
                    <span class="icon"><?= strpos($result, '✅') !== false ? '✅' : 'ℹ️' ?></span>
                    <span><?= htmlspecialchars($result) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- ERROS -->
        <?php if (!empty($errors)): ?>
        <div class="section">
            <h2>❌ Erros Encontrados</h2>
            <?php foreach ($errors as $error): ?>
                <div class="result-item error">
                    <span class="icon">❌</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- ESTATÍSTICAS -->
        <div class="section">
            <h2>📊 Estatísticas</h2>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value"><?= count(array_filter($results, fn($r) => strpos($r, '✅') !== false)) ?></div>
                    <div class="stat-label">Páginas Atualizadas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= count($errors) ?></div>
                    <div class="stat-label">Erros</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= count($pages) ?></div>
                    <div class="stat-label">Total Processadas</div>
                </div>
            </div>
        </div>
        
        <!-- O QUE FOI FEITO -->
        <div class="section">
            <h2>🔧 Alterações Aplicadas</h2>
            <div class="result-item info">
                <span class="icon">1️⃣</span>
                <span>Adicionado <code>require_once theme_engine.php</code></span>
            </div>
            <div class="result-item info">
                <span class="icon">2️⃣</span>
                <span>Inicializado <code>$theme = new ThemeEngine()</code></span>
            </div>
            <div class="result-item info">
                <span class="icon">3️⃣</span>
                <span>Adicionado busca de menu para header</span>
            </div>
            <div class="result-item info">
                <span class="icon">4️⃣</span>
                <span>Substituído imports de fontes por <code>$theme->renderHead()</code></span>
            </div>
            <div class="result-item info">
                <span class="icon">5️⃣</span>
                <span>Adicionado component <code>header.php</code> universal</span>
            </div>
            <div class="result-item info">
                <span class="icon">6️⃣</span>
                <span>Adicionado <code>$theme->renderScripts()</code> antes de &lt;/body&gt;</span>
            </div>
            <div class="result-item info">
                <span class="icon">7️⃣</span>
                <span>Configurado Tailwind com cores dinâmicas</span>
            </div>
        </div>
        
        <!-- PRÓXIMOS PASSOS -->
        <div class="section">
            <h2>📋 Próximos Passos</h2>
            <div class="result-item info">
                <span class="icon">1️⃣</span>
                <span>Acesse cada página e verifique se está funcionando</span>
            </div>
            <div class="result-item info">
                <span class="icon">2️⃣</span>
                <span>Vá em <code>client/customize.php</code> e mude o tema</span>
            </div>
            <div class="result-item info">
                <span class="icon">3️⃣</span>
                <span>Verifique se o tema aplica em TODAS as páginas</span>
            </div>
            <div class="result-item info">
                <span class="icon">4️⃣</span>
                <span>Teste o menu de navegação</span>
            </div>
            <div class="result-item success">
                <span class="icon">✅</span>
                <span><strong>DELETE este arquivo (update_pages.php) após testar!</strong></span>
            </div>
        </div>
        
        <!-- BACKUP INFO -->
        <div class="section">
            <h2>💾 Backup dos Arquivos Originais</h2>
            <p style="margin-bottom: 15px;">Os arquivos originais foram salvos em:</p>
            <div class="code">
                <?= htmlspecialchars($backup_dir) ?>
            </div>
            <p style="margin-top: 15px; opacity: 0.7; font-size: 14px;">
                💡 Se algo der errado, você pode restaurar os arquivos desta pasta.
            </p>
        </div>
        
        <!-- AÇÕES -->
        <div class="actions">
            <a href="index.php" class="btn">🏠 Ir para Home</a>
            <a href="loja.php" class="btn btn-secondary">🛒 Testar Loja</a>
            <a href="wiki.php" class="btn btn-secondary">📖 Testar Wiki</a>
            <a href="../../../client/customize.php" class="btn" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">🎨 Abrir Customização</a>
        </div>
        
        <!-- WARNING FINAL -->
        <div class="warning" style="margin-top: 50px;">
            <strong>🗑️ IMPORTANTE:</strong>
            <p>Após verificar que tudo está funcionando, DELETE este arquivo!</p>
            <div class="code" style="margin-top: 20px;">
                rm stores/Teste/update_pages.php
            </div>
        </div>
        
        <footer>
            <p>SplitStore Theme Engine Auto-Update v3.1</p>
            <p>Executado em <?= date('d/m/Y H:i:s') ?></p>
        </footer>
    </div>
</body>
</html>