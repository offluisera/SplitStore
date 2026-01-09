<!-- ============================================ -->
<!-- ARQUIVO 1: regras.php -->
<!-- ============================================ -->
<?php
session_start();
require_once '../../includes/db.php';

$store_slug = basename(dirname(__FILE__));

try {
    $stmt = $pdo->prepare("SELECT s.*, sc.* FROM stores s LEFT JOIN store_customization sc ON s.id = sc.store_id WHERE s.store_slug = ? AND s.status = 'active'");
    $stmt->execute([$store_slug]);
    $store = $stmt->fetch();
    
    if (!$store) die("Loja não encontrada.");
    
    // Menu
    $stmt = $pdo->prepare("SELECT * FROM store_menu WHERE store_id = ? AND is_enabled = 1 ORDER BY order_position ASC");
    $stmt->execute([$store['id']]);
    $menu_items = $stmt->fetchAll();
    
    // Buscar página de regras (se existir)
    $stmt = $pdo->prepare("SELECT * FROM store_pages WHERE store_id = ? AND slug = 'regras' AND is_published = 1");
    $stmt->execute([$store['id']]);
    $page_content = $stmt->fetch();
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

$primaryColor = $store['primary_color'] ?? '#dc2626';
$is_logged = isset($_SESSION['store_user_logged']) && $_SESSION['store_user_logged'] === true;

// Regras padrão se não houver conteúdo customizado
$default_rules = [
    [
        'icon' => 'shield-check',
        'title' => 'Respeito Mútuo',
        'description' => 'Trate todos os jogadores com respeito. Bullying, assédio, discriminação ou qualquer forma de ofensa não será tolerada.'
    ],
    [
        'icon' => 'ban',
        'title' => 'Uso de Hacks/Cheats',
        'description' => 'O uso de qualquer modificação que dê vantagem injusta (fly, x-ray, kill aura, etc) resultará em banimento permanente.'
    ],
    [
        'icon' => 'message-circle-off',
        'title' => 'Spam e Flood',
        'description' => 'Evite repetir mensagens, usar CAPSLOCK excessivo ou enviar conteúdo irrelevante no chat.'
    ],
    [
        'icon' => 'shield-alert',
        'title' => 'Exploits e Bugs',
        'description' => 'Reporte bugs encontrados à staff. Explorar falhas para obter vantagens resultará em punição.'
    ],
    [
        'icon' => 'link-2',
        'title' => 'Divulgação',
        'description' => 'Divulgar outros servidores, sites ou conteúdo impróprio é proibido e resultará em mute/ban.'
    ],
    [
        'icon' => 'users',
        'title' => 'Contas Múltiplas',
        'description' => 'O uso de contas secundárias para burlar punições ou obter vantagens indevidas não é permitido.'
    ],
    [
        'icon' => 'user-x',
        'title' => 'Nick Inapropriado',
        'description' => 'Nicks ofensivos, racistas ou inapropriados devem ser alterados. A recusa resultará em banimento.'
    ],
    [
        'icon' => 'alert-triangle',
        'title' => 'Construções Impróprias',
        'description' => 'Construções ofensivas, inapropriadas ou que promovam ódio serão removidas e o responsável punido.'
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regras | <?= htmlspecialchars($store['store_name']) ?></title>
    
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
        .rule-card { transition: all 0.3s; }
        .rule-card:hover { transform: translateY(-4px); border-color: <?= $primaryColor ?>; }
    </style>
</head>
<body>

    <main class="max-w-5xl mx-auto px-6 py-12">
        
        <div class="mb-12">
            <div class="flex items-center gap-3 mb-6">
                <a href="index.php" class="text-zinc-600 hover:text-white transition">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-4xl font-black uppercase tracking-tighter">
                    <i data-lucide="shield-check" class="w-8 h-8 inline text-primary"></i>
                    Regras do <span class="text-primary">Servidor</span>
                </h1>
            </div>
            <p class="text-zinc-500 text-lg">
                Leia atentamente e siga as regras para garantir uma experiência agradável para todos
            </p>
        </div>

        <div class="glass rounded-3xl p-8 mb-8 border border-primary/20 bg-primary/5">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-primary/20 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i data-lucide="alert-circle" class="w-6 h-6 text-primary"></i>
                </div>
                <div>
                    <h3 class="font-black uppercase mb-2">Importante!</h3>
                    <p class="text-sm text-zinc-400 leading-relaxed">
                        O desconhecimento das regras não isenta você de punições. 
                        Todas as regras estão sujeitas à interpretação da equipe de moderação.
                    </p>
                </div>
            </div>
        </div>

        <?php if ($page_content): ?>
            <div class="glass rounded-3xl p-10 border border-white/5">
                <div class="prose prose-invert max-w-none">
                    <?= nl2br(htmlspecialchars($page_content['content'])) ?>
                </div>
            </div>
        <?php else: ?>
            <div class="grid md:grid-cols-2 gap-6">
                <?php foreach ($default_rules as $rule): ?>
                <div class="rule-card glass rounded-2xl p-6 border border-white/5">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i data-lucide="<?= $rule['icon'] ?>" class="w-6 h-6 text-primary"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-black uppercase text-sm mb-2"><?= $rule['title'] ?></h3>
                            <p class="text-xs text-zinc-500 leading-relaxed">
                                <?= $rule['description'] ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="glass rounded-2xl p-8 mt-8 border border-white/5">
                <h3 class="font-black uppercase mb-4 flex items-center gap-2">
                    <i data-lucide="gavel" class="w-5 h-5 text-primary"></i>
                    Sistema de Punições
                </h3>
                
                <div class="space-y-3 text-sm">
                    <div class="flex items-start gap-3">
                        <span class="text-yellow-500 font-bold">⚠️ Advertência:</span>
                        <span class="text-zinc-400">Infrações leves - O jogador recebe um aviso formal</span>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="text-orange-500 font-bold">🔇 Mute:</span>
                        <span class="text-zinc-400">Spam, flood ou linguagem inadequada - Silenciamento temporário</span>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="text-red-500 font-bold">⏱️ Ban Temporário:</span>
                        <span class="text-zinc-400">Infrações médias/graves - Suspensão de 1 dia a 30 dias</span>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="text-red-600 font-bold">🔒 Ban Permanente:</span>
                        <span class="text-zinc-400">Hack, exploits graves ou reincidência - Banimento definitivo</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <script>lucide.createIcons();</script>
</body>
</html>