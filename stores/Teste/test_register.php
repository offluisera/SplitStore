<?php
/**
 * ============================================
 * TESTE MANUAL DE REGISTRO
 * ============================================
 * Salve como: stores/Teste/test_register.php
 * Acesse: http://seu-site.com/stores/Teste/test_register.php
 * 
 * REMOVER APÓS TESTAR!
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/db.php';

$store_slug = 'Teste';
$results = [];

try {
    // Busca loja
    $stmt = $pdo->prepare("SELECT id, store_name FROM stores WHERE store_slug = ?");
    $stmt->execute([$store_slug]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$store) {
        die("❌ Loja não encontrada");
    }
    
    $results[] = "✅ Loja encontrada: {$store['store_name']} (ID: {$store['id']})";
    
    // Dados de teste
    $minecraft_nick = "TestUser" . rand(1000, 9999);
    $minecraft_uuid = sprintf('%08x-%04x-%04x-%04x-%012x',
        mt_rand(0, 0xffffffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffffffffffff)
    );
    $email = "test" . rand(1000, 9999) . "@test.com";
    $password_hash = password_hash("test123", PASSWORD_BCRYPT);
    $skin_url = "https://mc-heads.net/avatar/{$minecraft_uuid}/100";
    
    $results[] = "✅ Dados gerados:";
    $results[] = "   Nick: {$minecraft_nick}";
    $results[] = "   UUID: {$minecraft_uuid}";
    $results[] = "   Email: {$email}";
    $results[] = "   Hash: " . substr($password_hash, 0, 20) . "...";
    
    // Tenta inserir
    $results[] = "⏳ Tentando inserir usuário...";
    
    $stmt = $pdo->prepare("
        INSERT INTO store_users 
        (store_id, minecraft_nick, minecraft_uuid, email, password_hash, skin_url, `rank`, rank_color) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $success = $stmt->execute([
        $store['id'],
        $minecraft_nick,
        $minecraft_uuid,
        $email,
        $password_hash,
        $skin_url,
        'Membro',
        '#9CA3AF'
    ]);
    
    if ($success) {
        $user_id = $pdo->lastInsertId();
        $results[] = "✅ USUÁRIO CRIADO COM SUCESSO!";
        $results[] = "   ID do usuário: {$user_id}";
        
        // Verifica se foi salvo
        $stmt = $pdo->prepare("SELECT * FROM store_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $results[] = "✅ Usuário confirmado no banco:";
            $results[] = "   Nick: {$user['minecraft_nick']}";
            $results[] = "   Email: {$user['email']}";
            $results[] = "   Rank: {$user['rank']}";
            $results[] = "   Criado em: {$user['created_at']}";
        }
    } else {
        $errorInfo = $stmt->errorInfo();
        $results[] = "❌ ERRO AO INSERIR:";
        $results[] = "   SQLSTATE: {$errorInfo[0]}";
        $results[] = "   Error Code: {$errorInfo[1]}";
        $results[] = "   Message: {$errorInfo[2]}";
    }
    
} catch (PDOException $e) {
    $results[] = "❌ ERRO DE BANCO:";
    $results[] = "   Mensagem: {$e->getMessage()}";
    $results[] = "   Código: {$e->getCode()}";
    $results[] = "   Arquivo: {$e->getFile()}";
    $results[] = "   Linha: {$e->getLine()}";
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Registro</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: #0a0a0a;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            padding: 40px;
            line-height: 1.8;
        }
        
        .container {
            max-width: 800px;
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
        
        .result {
            background: #0a0a0a;
            border: 1px solid #00ff0040;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .line {
            padding: 5px 0;
            font-size: 14px;
        }
        
        .success {
            color: #00ff00;
        }
        
        .error {
            color: #ff0000;
        }
        
        .info {
            color: #ffff00;
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
        
        .retry-btn {
            background: #ffff0020;
            border: 1px solid #ffff00;
            color: #ffff00;
            margin-left: 10px;
        }
        
        .retry-btn:hover {
            background: #ffff00;
            color: #000;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Teste de Registro</h1>
        
        <div class="result">
            <?php foreach ($results as $line): ?>
                <?php
                $class = 'info';
                if (strpos($line, '✅') !== false) $class = 'success';
                if (strpos($line, '❌') !== false) $class = 'error';
                if (strpos($line, '⏳') !== false) $class = 'info';
                ?>
                <div class="line <?= $class ?>"><?= htmlspecialchars($line) ?></div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center;">
            <a href="auth.php" class="back-btn">← Voltar para Login</a>
            <a href="test_register.php" class="back-btn retry-btn">🔄 Testar Novamente</a>
        </div>
    </div>
</body>
</html>