<?php
/**
 * ============================================
 * SPLITSTORE - CUSTOMIZAÇÃO DA LOJA
 * ============================================
 * Editor visual completo para personalizar a loja
 */

session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['store_logged'])) {
    header('Location: login.php');
    exit;
}

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];

$message = "";
$messageType = "";

// SALVAR CUSTOMIZAÇÕES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'save_design') {
        $primary_color = $_POST['primary_color'] ?? '#dc2626';
        $secondary_color = $_POST['secondary_color'] ?? '#000000';
        $logo_url = trim($_POST['logo_url'] ?? '');
        $favicon_url = trim($_POST['favicon_url'] ?? '');
        $banner_url = trim($_POST['banner_url'] ?? '');
        $store_title = trim($_POST['store_title'] ?? '');
        $store_description = trim($_POST['store_description'] ?? '');
        $template = $_POST['template'] ?? 'modern';
        
        try {
            // Verifica se já existe customização
            $check = $pdo->prepare("SELECT id FROM store_customization WHERE store_id = ?");
            $check->execute([$store_id]);
            
            if ($check->fetch()) {
                // UPDATE
                $sql = "UPDATE store_customization SET 
                        primary_color = ?, secondary_color = ?, logo_url = ?, favicon_url = ?,
                        banner_url = ?, store_title = ?, store_description = ?, template = ?
                        WHERE store_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$primary_color, $secondary_color, $logo_url, $favicon_url, 
                               $banner_url, $store_title, $store_description, $template, $store_id]);
            } else {
                // INSERT
                $sql = "INSERT INTO store_customization 
                        (store_id, primary_color, secondary_color, logo_url, favicon_url, 
                         banner_url, store_title, store_description, template) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$store_id, $primary_color, $secondary_color, $logo_url, $favicon_url, 
                               $banner_url, $store_title, $store_description, $template]);
            }
            
            $message = "Customizações salvas com sucesso!";
            $messageType = "success";
            
        } catch (PDOException $e) {
            $message = "Erro ao salvar: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// BUSCAR CUSTOMIZAÇÕES ATUAIS
$stmt = $pdo->prepare("SELECT * FROM store_customization WHERE store_id = ?");
$stmt->execute([$store_id]);
$customization = $stmt->fetch();

// Valores padrão se não existir
if (!$customization) {
    $customization = [
        'primary_color' => '#dc2626',
        'secondary_color' => '#000000',
        'logo_url' => '',
        'favicon_url' => '',
        'banner_url' => '',
        'store_title' => $store_name,
        'store_description' => 'Loja premium de itens Minecraft',
        'template' => 'modern'
    ];
}

// Templates disponíveis
$templates = [
    'modern' => [
        'name' => 'Modern Dark',
        'preview' => 'https://via.placeholder.com/400x300/0a0a0a/ffffff?text=Modern+Dark',
        'description' => 'Design moderno com fundo escuro e elementos glassmorphism'
    ],
    'gaming' => [
        'name' => 'Gaming Pro',
        'preview' => 'https://via.placeholder.com/400x300/1a1a2e/ffffff?text=Gaming+Pro',
        'description' => 'Visual gamer com gradientes neon e animações'
    ],
    'minimal' => [
        'name' => 'Minimal Clean',
        'preview' => 'https://via.placeholder.com/400x300/f5f5f5/000000?text=Minimal+Clean',
        'description' => 'Minimalista e clean, focado nos produtos'
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Customização | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #050505; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .sidebar-item:hover { background: rgba(220, 38, 38, 0.05); color: #dc2626; }
        
        /* Color Picker Styles */
        input[type="color"] {
            width: 60px;
            height: 60px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
        }
        
        input[type="color"]::-webkit-color-swatch-wrapper {
            padding: 0;
        }
        
        input[type="color"]::-webkit-color-swatch {
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
        }
        
        .template-card {
            transition: all 0.3s ease;
        }
        
        .template-card:hover {
            transform: translateY(-4px);
        }
        
        .template-card.selected {
            border-color: #dc2626;
            background: rgba(220, 38, 38, 0.05);
        }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        <header class="flex justify-between items-center mb-12">
            <div>
                <h1 class="text-3xl font-black italic uppercase tracking-tighter">
                    Personalizar <span class="text-red-600">Loja</span>
                </h1>
                <p class="text-zinc-500 text-xs font-bold uppercase tracking-widest mt-1">
                    Design, cores e identidade visual
                </p>
            </div>
            
            <div class="flex gap-4">
                <a href="<?= htmlspecialchars($customization['store_title'] ?? $store_name) ?>.splitstore.com.br" 
                   target="_blank" 
                   class="glass px-6 py-3 rounded-2xl hover:border-red-600/40 transition flex items-center gap-3">
                    <i data-lucide="external-link" class="w-4 h-4 text-zinc-500"></i>
                    <span class="text-xs font-black uppercase tracking-wider text-zinc-500">Pré-visualizar</span>
                </a>
            </div>
        </header>

        <?php if($message): ?>
            <div class="glass border-<?= $messageType == 'success' ? 'green' : 'red' ?>-600/20 text-<?= $messageType == 'success' ? 'green' : 'red' ?>-500 p-4 rounded-2xl mb-8 text-xs font-bold flex items-center gap-3">
                <i data-lucide="<?= $messageType == 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <input type="hidden" name="action" value="save_design">
            
            <!-- TEMPLATES -->
            <div class="glass p-10 rounded-3xl">
                <div class="mb-8">
                    <h2 class="text-xl font-black uppercase italic mb-2">Escolha seu Template</h2>
                    <p class="text-zinc-500 text-sm">Selecione o layout base da sua loja</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach($templates as $key => $template): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="template" value="<?= $key ?>" 
                                   <?= $customization['template'] === $key ? 'checked' : '' ?>
                                   class="hidden template-input">
                            <div class="template-card glass p-6 rounded-2xl border border-white/5 hover:border-red-600/30 transition-all">
                                <div class="aspect-video bg-black/40 rounded-xl mb-4 overflow-hidden">
                                    <img src="<?= $template['preview'] ?>" 
                                         alt="<?= $template['name'] ?>"
                                         class="w-full h-full object-cover">
                                </div>
                                <h3 class="text-sm font-black uppercase mb-2"><?= $template['name'] ?></h3>
                                <p class="text-xs text-zinc-500 leading-relaxed"><?= $template['description'] ?></p>
                                <div class="mt-4 pt-4 border-t border-white/5">
                                    <div class="flex items-center gap-2 text-[10px] font-bold uppercase text-zinc-600">
                                        <i data-lucide="check-circle" class="w-3 h-3"></i>
                                        Responsivo • SEO Otimizado
                                    </div>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- CORES -->
            <div class="glass p-10 rounded-3xl">
                <div class="mb-8">
                    <h2 class="text-xl font-black uppercase italic mb-2">Paleta de Cores</h2>
                    <p class="text-zinc-500 text-sm">Defina as cores principais da sua loja</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-4">
                            Cor Primária (Botões, Links, Destaques)
                        </label>
                        <div class="flex items-center gap-4">
                            <input type="color" name="primary_color" 
                                   value="<?= htmlspecialchars($customization['primary_color']) ?>"
                                   class="glass">
                            <div class="flex-1 glass p-4 rounded-xl">
                                <input type="text" name="primary_color_hex" 
                                       value="<?= htmlspecialchars($customization['primary_color']) ?>"
                                       placeholder="#dc2626"
                                       class="w-full bg-transparent border-none outline-none text-sm font-mono uppercase">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-4">
                            Cor Secundária (Fundos, Cards)
                        </label>
                        <div class="flex items-center gap-4">
                            <input type="color" name="secondary_color" 
                                   value="<?= htmlspecialchars($customization['secondary_color']) ?>"
                                   class="glass">
                            <div class="flex-1 glass p-4 rounded-xl">
                                <input type="text" name="secondary_color_hex" 
                                       value="<?= htmlspecialchars($customization['secondary_color']) ?>"
                                       placeholder="#000000"
                                       class="w-full bg-transparent border-none outline-none text-sm font-mono uppercase">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview de Cores -->
                <div class="mt-8 p-6 glass rounded-2xl">
                    <p class="text-[9px] font-black uppercase text-zinc-600 mb-4 tracking-widest">Pré-visualização</p>
                    <div class="flex gap-4">
                        <button type="button" id="previewPrimary" 
                                style="background-color: <?= $customization['primary_color'] ?>;"
                                class="flex-1 py-4 rounded-xl font-black uppercase text-xs text-white">
                            Botão Primário
                        </button>
                        <div id="previewSecondary" 
                             style="background-color: <?= $customization['secondary_color'] ?>;"
                             class="flex-1 p-4 rounded-xl text-center">
                            <span class="text-xs font-bold">Card / Fundo</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- IDENTIDADE VISUAL -->
            <div class="glass p-10 rounded-3xl">
                <div class="mb-8">
                    <h2 class="text-xl font-black uppercase italic mb-2">Identidade Visual</h2>
                    <p class="text-zinc-500 text-sm">Logo, banner e elementos da marca</p>
                </div>

                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block">
                                URL da Logo
                            </label>
                            <input type="url" name="logo_url" 
                                   value="<?= htmlspecialchars($customization['logo_url']) ?>"
                                   placeholder="https://i.imgur.com/logo.png"
                                   class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                            <p class="text-[8px] text-zinc-700 ml-2">Recomendado: PNG transparente, 200x200px</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block">
                                URL do Favicon
                            </label>
                            <input type="url" name="favicon_url" 
                                   value="<?= htmlspecialchars($customization['favicon_url']) ?>"
                                   placeholder="https://i.imgur.com/favicon.png"
                                   class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                            <p class="text-[8px] text-zinc-700 ml-2">Ícone da aba do navegador, 32x32px</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block">
                            URL do Banner Principal
                        </label>
                        <input type="url" name="banner_url" 
                               value="<?= htmlspecialchars($customization['banner_url']) ?>"
                               placeholder="https://i.imgur.com/banner.jpg"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                        <p class="text-[8px] text-zinc-700 ml-2">Banner do topo da loja, 1920x400px</p>
                    </div>

                    <!-- Preview do Banner -->
                    <?php if (!empty($customization['banner_url'])): ?>
                        <div class="aspect-[16/3] bg-black/40 rounded-2xl overflow-hidden">
                            <img src="<?= htmlspecialchars($customization['banner_url']) ?>" 
                                 alt="Banner Preview"
                                 class="w-full h-full object-cover">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TEXTOS E SEO -->
            <div class="glass p-10 rounded-3xl">
                <div class="mb-8">
                    <h2 class="text-xl font-black uppercase italic mb-2">Textos e SEO</h2>
                    <p class="text-zinc-500 text-sm">Títulos e descrições para mecanismos de busca</p>
                </div>

                <div class="space-y-6">
                    <div class="space-y-2">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block">
                            Título da Loja (SEO)
                        </label>
                        <input type="text" name="store_title" 
                               value="<?= htmlspecialchars($customization['store_title']) ?>"
                               placeholder="<?= htmlspecialchars($store_name) ?> - Loja VIP"
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
                        <p class="text-[8px] text-zinc-700 ml-2">Aparece na aba do navegador e Google</p>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[9px] font-black uppercase text-zinc-600 ml-2 tracking-widest block">
                            Descrição (Meta Description)
                        </label>
                        <textarea name="store_description" rows="3"
                                  placeholder="Compre VIPs, kits e itens premium no melhor servidor..."
                                  class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition resize-none"><?= htmlspecialchars($customization['store_description']) ?></textarea>
                        <p class="text-[8px] text-zinc-700 ml-2">Máximo 160 caracteres, aparece no Google</p>
                    </div>
                </div>
            </div>

            <!-- BOTÕES DE AÇÃO -->
            <div class="flex gap-4">
                <button type="submit" 
                        class="flex-1 bg-red-600 hover:bg-red-700 py-5 rounded-2xl font-black uppercase text-xs tracking-widest transition-all hover:scale-[1.02] shadow-lg shadow-red-600/20 flex items-center justify-center gap-3">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Salvar Alterações
                </button>
                
                <button type="button" 
                        onclick="resetToDefaults()"
                        class="px-8 py-5 glass rounded-2xl font-black uppercase text-xs tracking-widest text-zinc-500 hover:text-white hover:border-white/10 transition">
                    Restaurar Padrão
                </button>
            </div>
        </form>

    </main>

    <script>
        lucide.createIcons();
        
        // Atualiza preview ao mudar cores
        document.querySelectorAll('input[type="color"]').forEach(input => {
            input.addEventListener('input', (e) => {
                const name = e.target.name;
                const value = e.target.value;
                
                // Atualiza o campo de texto hex
                const hexInput = document.querySelector(`input[name="${name}_hex"]`);
                if (hexInput) hexInput.value = value;
                
                // Atualiza preview
                if (name === 'primary_color') {
                    document.getElementById('previewPrimary').style.backgroundColor = value;
                } else if (name === 'secondary_color') {
                    document.getElementById('previewSecondary').style.backgroundColor = value;
                }
            });
        });
        
        // Sincroniza campos hex com color picker
        document.querySelectorAll('input[name$="_hex"]').forEach(input => {
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                const colorName = e.target.name.replace('_hex', '');
                const colorInput = document.querySelector(`input[name="${colorName}"]`);
                
                if (colorInput && /^#[0-9A-F]{6}$/i.test(value)) {
                    colorInput.value = value;
                    
                    if (colorName === 'primary_color') {
                        document.getElementById('previewPrimary').style.backgroundColor = value;
                    } else if (colorName === 'secondary_color') {
                        document.getElementById('previewSecondary').style.backgroundColor = value;
                    }
                }
            });
        });
        
        // Marca template selecionado
        document.querySelectorAll('.template-input').forEach(input => {
            input.addEventListener('change', (e) => {
                document.querySelectorAll('.template-card').forEach(card => {
                    card.classList.remove('selected');
                });
                if (e.target.checked) {
                    e.target.closest('.template-card').classList.add('selected');
                }
            });
            
            // Marca o inicial
            if (input.checked) {
                input.closest('.template-card').classList.add('selected');
            }
        });
        
        function resetToDefaults() {
            if (confirm('Tem certeza que deseja restaurar as configurações padrão?')) {
                document.querySelector('input[name="primary_color"]').value = '#dc2626';
                document.querySelector('input[name="secondary_color"]').value = '#000000';
                document.querySelector('input[name="logo_url"]').value = '';
                document.querySelector('input[name="favicon_url"]').value = '';
                document.querySelector('input[name="banner_url"]').value = '';
                document.querySelector('input[name="store_title"]').value = '<?= addslashes($store_name) ?>';
                document.querySelector('input[name="store_description"]').value = 'Loja premium de itens Minecraft';
                
                // Atualiza previews
                document.getElementById('previewPrimary').style.backgroundColor = '#dc2626';
                document.getElementById('previewSecondary').style.backgroundColor = '#000000';
            }
        }
    </script>
</body>
</html>