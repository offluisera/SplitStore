<?php
/**
 * ========================================================================
 * CORREÇÃO: Cores do Tailwind + Checkboxes na Customização
 * ========================================================================
 * 
 * Salve como: stores/Teste/fix_theme_colors.php
 * Acesse UMA VEZ: http://seu-site.com/stores/Teste/fix_theme_colors.php
 * DELETE após executar!
 */

$store_dir = __DIR__;
$backup_dir = $store_dir . '/fix_backup_' . date('Ymd_His');

// Páginas para corrigir
$pages = [
    'index.php',
    'loja.php',
    'noticias.php',
    'wiki.php',
    'regras.php',
    'equipe.php'
];

$results = [];

// Criar backup
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
    $results[] = "✅ Backup criado: $backup_dir";
}

foreach ($pages as $page) {
    $file = $store_dir . '/' . $page;
    
    if (!file_exists($file)) {
        continue;
    }
    
    try {
        // Backup
        copy($file, $backup_dir . '/' . $page);
        
        // Ler conteúdo
        $content = file_get_contents($file);
        $original = $content;
        
        // ==========================================
        // REMOVER TAILWIND CONFIG DUPLICADO
        // ==========================================
        // Remove configs Tailwind duplicadas (mantém apenas uma)
        $pattern = '/<script>\s*tailwind\.config\s*=\s*\{[^}]+\}\s*<\/script>/i';
        preg_match_all($pattern, $content, $matches);
        
        if (count($matches[0]) > 1) {
            // Remove todas as ocorrências duplicadas
            $content = preg_replace($pattern, '', $content, count($matches[0]) - 1);
            $results[] = "✅ {$page}: Removido Tailwind config duplicado";
        }
        
        // ==========================================
        // ADICIONAR ESTILO INLINE PARA SOBRESCREVER TAILWIND
        // ==========================================
        // Adiciona CSS que força as cores corretas DEPOIS do Tailwind
        if (!strpos($content, 'FORCE THEME COLORS')) {
            $forceColors = "
    <style>
        /* FORCE THEME COLORS - Sobrescreve Tailwind */
        .bg-primary,
        button.bg-primary,
        a.bg-primary,
        .text-primary {
            color: var(--primary) !important;
        }
        
        .bg-gradient-to-r.from-primary {
            background: linear-gradient(to right, var(--primary), var(--accent)) !important;
        }
        
        .border-primary {
            border-color: var(--primary) !important;
        }
        
        .gradient-text {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
        }
    </style>";
            
            // Adiciona antes de </head>
            $content = str_replace('</head>', $forceColors . "\n</head>", $content);
            $results[] = "✅ {$page}: Adicionado force colors";
        }
        
        // Salvar se mudou
        if ($content !== $original) {
            file_put_contents($file, $content);
            $results[] = "✅ {$page}: Atualizado com sucesso!";
        }
        
    } catch (Exception $e) {
        $results[] = "❌ Erro em {$page}: " . $e->getMessage();
    }
}

// ==========================================
// CORRIGIR CUSTOMIZE.PHP - CHECKBOXES
// ==========================================
$customize_file = dirname(__DIR__, 2) . '/client/customize.php';

if (file_exists($customize_file)) {
    try {
        copy($customize_file, $backup_dir . '/customize.php');
        
        $content = file_get_contents($customize_file);
        $original = $content;
        
        // Corrige a verificação dos checkboxes
        // Problema: estava usando $c['show_particles'] direto sem verificar se é string ou int
        
        $old_checkbox_pattern = '/\<\?=\s*\$c\[\'(show_[^\']+)\'\]\s*\?\s*\'checked\'\s*:\s*\'\'\s*\?\>/';
        $new_checkbox = '<?= !empty($c[\'$1\']) ? \'checked\' : \'\' ?>';
        
        $content = preg_replace($old_checkbox_pattern, $new_checkbox, $content);
        
        if ($content !== $original) {
            file_put_contents($customize_file, $content);
            $results[] = "✅ customize.php: Checkboxes corrigidas!";
        } else {
            $results[] = "ℹ️ customize.php: Já estava correto";
        }
        
    } catch (Exception $e) {
        $results[] = "❌ Erro no customize.php: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Correção de Cores e Checkboxes</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0a2e 100%);
            color: #fff;
            font-family: 'Segoe UI', sans-serif;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 50px;
            padding: 40px;
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(220, 38, 38, 0.3);
        }
        
        .header h1 {
            font-size: 42px;
            font-weight: 900;
            margin-bottom: 15px;
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
            color: #dc2626;
            text-transform: uppercase;
        }
        
        .result-item {
            padding: 15px 20px;
            margin: 10px 0;
            border-radius: 10px;
            font-size: 15px;
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
        
        .btn {
            display: inline-block;
            padding: 15px 35px;
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            text-transform: uppercase;
            margin: 10px;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(220, 38, 38, 0.5);
        }
        
        .actions {
            text-align: center;
            margin-top: 40px;
        }
        
        .warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .warning strong {
            font-size: 22px;
            display: block;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="header">
            <h1>🔧 Correção Aplicada</h1>
            <p>Cores do tema e checkboxes corrigidos!</p>
        </div>
        
        <div class="section">
            <h2>✅ Resultados</h2>
            <?php foreach ($results as $result): ?>
                <?php
                $class = 'info';
                if (strpos($result, '✅') !== false) $class = 'success';
                if (strpos($result, '❌') !== false) $class = 'error';
                ?>
                <div class="result-item <?= $class ?>">
                    <?= htmlspecialchars($result) ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="section">
            <h2>🔍 O Que Foi Feito</h2>
            <div class="result-item info">
                <strong>1. Removido Tailwind Config duplicado</strong><br>
                Cada página tinha 2 configs Tailwind conflitantes
            </div>
            <div class="result-item info">
                <strong>2. Adicionado Force Colors CSS</strong><br>
                CSS com !important para sobrescrever cores do Tailwind
            </div>
            <div class="result-item info">
                <strong>3. Corrigido checkboxes do customize.php</strong><br>
                Agora verifica corretamente o valor salvo no banco
            </div>
        </div>
        
        <div class="section">
            <h2>📋 Teste Agora</h2>
            <div class="result-item success">
                <strong>1.</strong> Vá em <code>/client/customize.php</code>
            </div>
            <div class="result-item success">
                <strong>2.</strong> Marque as checkboxes de "Efeitos Especiais"
            </div>
            <div class="result-item success">
                <strong>3.</strong> Clique em "Salvar Tudo"
            </div>
            <div class="result-item success">
                <strong>4.</strong> Recarregue a página - checkboxes devem continuar marcadas
            </div>
            <div class="result-item success">
                <strong>5.</strong> Mude o tema e veja as cores mudarem corretamente
            </div>
        </div>
        
        <div class="warning">
            <strong>⚠️ IMPORTANTE!</strong>
            <p>DELETE este arquivo após testar!</p>
        </div>
        
        <div class="actions">
            <a href="index.php" class="btn">🏠 Ir para Home</a>
            <a href="../../client/customize.php" class="btn">🎨 Testar Customização</a>
        </div>
        
        <div style="text-align: center; margin-top: 40px; opacity: 0.5;">
            <p>Backup salvo em: <?= htmlspecialchars($backup_dir) ?></p>
        </div>
    </div>
</body>
</html>