<!-- ============================================ -->
<!-- ARQUIVO 3: wiki.php -->
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
    
    // Buscar página wiki
    $stmt = $pdo->prepare("SELECT * FROM store_pages WHERE store_id = ? AND slug = 'wiki' AND is_published = 1");
    $stmt->execute([$store['id']]);
    $wiki_page = $stmt->fetch();
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

$primaryColor = $store['primary_color'] ?? '#dc2626';

$wiki_sections = [
    [
        'icon' => 'rocket',
        'title' => 'Como Começar',
        'items' => [
            'Baixe o Minecraft Java Edition versão 1.8+',
            'Entre em Multijogador e adicione nosso IP',
            'Conecte-se e comece sua jornada!',
            'Use /ajuda para ver comandos básicos'
        ]
    ],
    [
        'icon' => 'zap',
        'title' => 'Comandos Básicos',
        'items' => [
            '/spawn - Voltar ao spawn',
            '/sethome [nome] - Definir home',
            '/home [nome] - Teleportar para home',
            '/tpa [jogador] - Pedir teleporte'
        ]
    ],
    [
        'icon' => 'coins',
        'title' => 'Economia',
        'items' => [
            'Ganhe dinheiro vendendo itens em /loja',
            'Complete missões diárias para bônus',
            'Participe de eventos especiais',
            'Vote no servidor e receba recompensas'
        ]
    ],
    [
        'icon' => 'shield',
        'title' => 'Proteção de Terreno',
        'items' => [
            'Use machado de ouro para selecionar área',
            '/claim - Proteger sua região',
            '/trust [jogador] - Adicionar amigos',
            '/abandonclaim - Remover proteção'
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wiki | <?= htmlspecialchars($store['store_name']) ?></title>
    
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
                    <i data-lucide="book-open" class="w-8 h-8 inline text-primary"></i>
                    Wiki do <span class="text-primary">Servidor</span>
                </h1>
            </div>
            <p class="text-zinc-500 text-lg">
                Tudo que você precisa saber para começar a jogar
            </p>
        </div>

        <?php if ($wiki_page): ?>
            <div class="glass rounded-3xl p-10 border border-white/5">
                <div class="prose prose-invert max-w-none">
                    <?= nl2br(htmlspecialchars($wiki_page['content'])) ?>
                </div>
            </div>
        <?php else: ?>
            
            <!-- IP do Servidor -->
            <div class="glass rounded-3xl p-8 mb-8 border border-primary/20 bg-primary/5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase text-zinc-500 mb-2">Conecte-se ao Servidor</p>
                        <p class="text-2xl font-black uppercase">
                            play.<?= strtolower($store['store_name']) ?>.com.br
                        </p>
                    </div>
                    <button onclick="copyIP()" class="bg-primary hover:brightness-110 px-6 py-3 rounded-xl font-black uppercase text-sm transition">
                        <i data-lucide="copy" class="w-4 h-4 inline mr-2"></i>
                        Copiar IP
                    </button>
                </div>
            </div>

            <!-- Seções -->
            <div class="grid md:grid-cols-2 gap-6">
                <?php foreach ($wiki_sections as $section): ?>
                <div class="glass rounded-2xl p-6 border border-white/5">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                            <i data-lucide="<?= $section['icon'] ?>" class="w-5 h-5 text-primary"></i>
                        </div>
                        <h3 class="font-black uppercase text-lg"><?= $section['title'] ?></h3>
                    </div>
                    
                    <ul class="space-y-2">
                        <?php foreach ($section['items'] as $item): ?>
                        <li class="flex items-start gap-2 text-sm text-zinc-400">
                            <i data-lucide="check" class="w-4 h-4 text-primary flex-shrink-0 mt-0.5"></i>
                            <span><?= $item ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </main>

    <script>
        lucide.createIcons();
        
        function copyIP() {
            const ip = 'play.<?= strtolower($store['store_name']) ?>.com.br';
            navigator.clipboard.writeText(ip).then(() => {
                alert('IP copiado: ' + ip);
            });
        }
    </script>
</body>
</html>