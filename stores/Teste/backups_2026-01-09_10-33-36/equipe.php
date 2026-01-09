<?php
/**
 * ============================================
 * EQUIPE.PHP - ATUALIZADO
 * ============================================
 * Busca membros da staff do banco de dados
 * stores/Teste/equipe.php
 */

session_start();
require_once '../../includes/db.php';

$store_slug = basename(dirname(__FILE__));

try {
    $stmt = $pdo->prepare("
        SELECT s.*, sc.* 
        FROM stores s
        LEFT JOIN store_customization sc ON s.id = sc.store_id
        WHERE s.store_slug = ? AND s.status = 'active'
    ");
    $stmt->execute([$store_slug]);
    $store = $stmt->fetch();
    
    if (!$store) die("Loja não encontrada.");
    
    // Buscar membros da equipe
    $stmt = $pdo->prepare("
        SELECT * FROM store_team 
        WHERE store_id = ? AND is_visible = 1 
        ORDER BY order_position ASC, created_at ASC
    ");
    $stmt->execute([$store['id']]);
    $team_members = $stmt->fetchAll();
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

$primaryColor = $store['primary_color'] ?? '#dc2626';
$is_logged = isset($_SESSION['store_user_logged']) && $_SESSION['store_user_logged'] === true;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipe | <?= htmlspecialchars($store['store_name']) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?= $primaryColor ?>'
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        body { background: #0f0f0f; color: white; font-family: 'Inter', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .member-card { transition: all 0.3s; }
        .member-card:hover { transform: translateY(-8px); box-shadow: 0 20px 60px -20px rgba(220, 38, 38, 0.5); }
    </style>
</head>
<body>

    <main class="max-w-7xl mx-auto px-6 py-12">
        
        <div class="mb-12">
            <div class="flex items-center gap-3 mb-6">
                <a href="index.php" class="text-zinc-600 hover:text-white transition">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-4xl font-black uppercase tracking-tighter">
                    <i data-lucide="users" class="w-8 h-8 inline text-primary"></i>
                    Nossa <span class="text-primary">Equipe</span>
                </h1>
            </div>
            <p class="text-zinc-500 text-lg">
                Conheça as pessoas que fazem o servidor funcionar e garantem a melhor experiência para você
            </p>
        </div>

        <?php if (empty($team_members)): ?>
            <div class="glass rounded-3xl p-20 text-center border border-white/5">
                <div class="w-20 h-20 bg-zinc-900 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i data-lucide="users" class="w-10 h-10 text-zinc-700"></i>
                </div>
                <h3 class="text-2xl font-black uppercase mb-3 text-zinc-600">Equipe em Formação</h3>
                <p class="text-zinc-500">Em breve apresentaremos nossa equipe aqui!</p>
            </div>
        <?php else: ?>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($team_members as $member): ?>
                <div class="member-card glass rounded-2xl p-6 border border-white/5 text-center">
                    
                    <div class="relative w-24 h-24 mx-auto mb-4">
                        <img src="<?= htmlspecialchars($member['skin_url']) ?>" 
                             class="w-full h-full rounded-xl object-cover"
                             alt="<?= htmlspecialchars($member['minecraft_nick']) ?>">
                        <div class="absolute -bottom-2 -right-2 w-8 h-8 rounded-lg flex items-center justify-center"
                             style="background: <?= htmlspecialchars($member['role_color']) ?>;">
                            <i data-lucide="shield-check" class="w-4 h-4 text-white"></i>
                        </div>
                    </div>
                    
                    <h3 class="font-black uppercase text-lg mb-1">
                        <?= htmlspecialchars($member['minecraft_nick']) ?>
                    </h3>
                    
                    <div class="px-3 py-1 rounded-full text-xs font-black uppercase inline-block mb-3"
                         style="background: <?= htmlspecialchars($member['role_color']) ?>20; color: <?= htmlspecialchars($member['role_color']) ?>;">
                        <?= htmlspecialchars($member['role']) ?>
                    </div>
                    
                    <?php if (!empty($member['description'])): ?>
                    <p class="text-xs text-zinc-500 leading-relaxed">
                        <?= nl2br(htmlspecialchars($member['description'])) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <script>lucide.createIcons();</script>
</body>
</html>