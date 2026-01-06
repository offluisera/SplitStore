<?php
/**
 * ============================================
 * SCRIPT PARA CORRIGIR COMANDOS NO BANCO
 * ============================================
 * Execute: php fix_commands.php
 * Ou acesse: http://seusite.com/api/fix_commands.php
 * ============================================
 */

require_once '../includes/db.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Corrigir Comandos</title>
    <style>
        body { font-family: monospace; background: #0a0a0a; color: #fff; padding: 20px; }
        .success { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        .info { color: #0ff; }
        pre { background: #000; padding: 10px; border: 1px solid #333; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 Corrigir Comandos dos Produtos</h1>
";

try {
    // Buscar todos os produtos
    $stmt = $pdo->query("SELECT id, name, commands FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p class='info'>Total de produtos encontrados: " . count($products) . "</p>";
    echo "<hr>";
    
    $updated = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($products as $product) {
        echo "<div style='margin-bottom: 20px; border: 1px solid #333; padding: 15px;'>";
        echo "<h3>Produto #{$product['id']}: {$product['name']}</h3>";
        
        $currentCommands = $product['commands'];
        echo "<p><strong>Comando Atual:</strong></p>";
        echo "<pre>" . htmlspecialchars($currentCommands) . "</pre>";
        
        // Verificar se já é um JSON válido
        $decoded = json_decode($currentCommands, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            echo "<p class='success'>✓ Já está em formato JSON correto</p>";
            $skipped++;
        } else {
            // Não é JSON, converter para array JSON
            if (empty($currentCommands)) {
                $newCommands = json_encode([]);
                echo "<p class='warning'>⚠ Comando vazio, definindo como array vazio</p>";
            } else {
                $newCommands = json_encode([$currentCommands]);
                echo "<p class='info'>→ Convertendo para array JSON</p>";
            }
            
            echo "<p><strong>Novo Comando:</strong></p>";
            echo "<pre>" . htmlspecialchars($newCommands) . "</pre>";
            
            // Atualizar no banco
            try {
                $updateStmt = $pdo->prepare("UPDATE products SET commands = ? WHERE id = ?");
                $updateStmt->execute([$newCommands, $product['id']]);
                echo "<p class='success'>✓ Atualizado com sucesso!</p>";
                $updated++;
            } catch (Exception $e) {
                echo "<p class='error'>✗ Erro ao atualizar: " . htmlspecialchars($e->getMessage()) . "</p>";
                $errors++;
            }
        }
        
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h2>Resumo:</h2>";
    echo "<ul>";
    echo "<li class='success'>Atualizados: $updated</li>";
    echo "<li class='info'>Já corretos: $skipped</li>";
    echo "<li class='error'>Erros: $errors</li>";
    echo "</ul>";
    
    if ($updated > 0) {
        echo "<p class='success'><strong>✓ Correção concluída! Teste agora o comando /splitstore claim no servidor</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>ERRO: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
?>