<?php
/**
 * ============================================
 * CONTATOS - INFORMAÇÕES DE CONTATO
 * ============================================
 */

session_start();
require_once '../includes/db.php';
require_once '../includes/auth_guard.php';

requireAccess(__FILE__);

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'];

$message = "";
$messageType = "";

// Salvar contatos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_contacts') {
    
    $data = [
        'email' => trim($_POST['email'] ?? ''),
        'whatsapp' => trim($_POST['whatsapp'] ?? ''),
        'instagram' => trim($_POST['instagram'] ?? ''),
        'discord' => trim($_POST['discord'] ?? ''),
        'facebook' => trim($_POST['facebook'] ?? ''),
        'twitter' => trim($_POST['twitter'] ?? ''),
        'youtube' => trim($_POST['youtube'] ?? ''),
        'tiktok' => trim($_POST['tiktok'] ?? ''),
        'telegram' => trim($_POST['telegram'] ?? '')
    ];
    
    try {
        // Busca ou cria registro de customização
        $check = $pdo->prepare("SELECT id FROM store_customization WHERE store_id = ?");
        $check->execute([$store_id]);
        
        if ($check->fetch()) {
            // Atualiza redes sociais em formato JSON
            $stmt = $pdo->prepare("
                UPDATE store_customization 
                SET custom_js = ? 
                WHERE store_id = ?
            ");
            $stmt->execute([json_encode($data), $store_id]);
        } else {
            // Cria novo registro
            $stmt = $pdo->prepare("
                INSERT INTO store_customization (store_id, custom_js) 
                VALUES (?, ?)
            ");
            $stmt->execute([$store_id, json_encode($data)]);
        }
        
        $message = "✓ Informações de contato salvas com sucesso!";
        $messageType = "success";
        
    } catch (PDOException $e) {
        $message = "Erro ao salvar: " . $e->getMessage();
        $messageType = "error";
    }
}

// Buscar contatos atuais
$contacts = [
    'email' => '',
    'whatsapp' => '',
    'instagram' => '',
    'discord' => '',
    'facebook' => '',
    'twitter' => '',
    'youtube' => '',
    'tiktok' => '',
    'telegram' => ''
];

try {
    $stmt = $pdo->prepare("SELECT custom_js FROM store_customization WHERE store_id = ?");
    $stmt->execute([$store_id]);
    $result = $stmt->fetch();
    
    if ($result && !empty($result['custom_js'])) {
        $stored = json_decode($result['custom_js'], true);
        if (is_array($stored)) {
            $contacts = array_merge($contacts, $stored);
        }
    }
} catch (PDOException $e) {
    error_log("Error loading contacts: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Contatos | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #000; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .social-card { transition: all 0.3s ease; }
        .social-card:hover { transform: translateY(-2px); border-color: rgba(220, 38, 38, 0.3); }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        
        <!-- Header -->
        <header class="mb-12">
            <h1 class="text-3xl font-black italic uppercase tracking-tighter mb-2">
                Informações de <span class="text-red-600">Contato</span>
            </h1>
            <p class="text-zinc-500 text-sm">Configure as formas de contato que aparecem na sua loja</p>
        </header>

        <?php if($message): ?>
            <div class="glass border-<?= $messageType === 'success' ? 'green' : 'red' ?>-600/20 text-<?= $messageType === 'success' ? 'green' : 'red' ?>-500 p-5 rounded-2xl mb-8 flex items-center gap-3">
                <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                <span class="font-bold"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <input type="hidden" name="action" value="save_contacts">

            <!-- Email de Contato -->
            <div class="glass rounded-3xl p-10">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 bg-red-600/10 rounded-xl flex items-center justify-center">
                        <i data-lucide="mail" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-black uppercase">Email de Contato</h2>
                        <p class="text-sm text-zinc-500">Email principal para atendimento</p>
                    </div>
                </div>
                
                <input type="email" name="email" value="<?= htmlspecialchars($contacts['email']) ?>"
                       placeholder="contato@sualoja.com"
                       class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none focus:border-red-600 transition">
            </div>

            <!-- Redes Sociais -->
            <div class="glass rounded-3xl p-10">
                <div class="mb-8">
                    <h2 class="text-xl font-black uppercase mb-2">Redes Sociais</h2>
                    <p class="text-sm text-zinc-500">Links para suas redes sociais</p>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    
                    <!-- WhatsApp -->
                    <div class="social-card glass p-6 rounded-2xl border border-white/5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-green-600/20 rounded-xl flex items-center justify-center">
                                <i data-lucide="message-circle" class="w-5 h-5 text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-black uppercase">WhatsApp</p>
                                <p class="text-[10px] text-zinc-600">Com código do país</p>
                            </div>
                        </div>
                        <input type="text" name="whatsapp" value="<?= htmlspecialchars($contacts['whatsapp']) ?>"
                               placeholder="+55 11 99999-9999"
                               class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-green-600 transition">
                    </div>

                    <!-- Instagram -->
                    <div class="social-card glass p-6 rounded-2xl border border-white/5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-pink-600/20 rounded-xl flex items-center justify-center">
                                <i data-lucide="instagram" class="w-5 h-5 text-pink-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-black uppercase">Instagram</p>
                                <p class="text-[10px] text-zinc-600">@usuario ou URL completa</p>
                            </div>
                        </div>
                        <input type="text" name="instagram" value="<?= htmlspecialchars($contacts['instagram']) ?>"
                               placeholder="@sualoja"
                               class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-pink-600 transition">
                    </div>

                    <!-- Discord -->
                    <div class="social-card glass p-6 rounded-2xl border border-white/5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-[#5865F2]/20 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-[#5865F2]" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.892a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.096 2.157 2.42c0 1.333-.946 2.418-2.157 2.418z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-black uppercase">Discord</p>
                                <p class="text-[10px] text-zinc-600">Link do convite</p>
                            </div>
                        </div>
                        <input type="text" name="discord" value="<?= htmlspecialchars($contacts['discord']) ?>"
                               placeholder="https://discord.gg/seuservidor"
                               class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-[#5865F2] transition">
                    </div>

                    <!-- Facebook -->
                    <div class="social-card glass p-6 rounded-2xl border border-white/5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-blue-600/20 rounded-xl flex items-center justify-center">
                                <i data-lucide="facebook" class="w-5 h-5 text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-black uppercase">Facebook</p>
                                <p class="text-[10px] text-zinc-600">URL da página</p>
                            </div>
                        </div>
                        <input type="text" name="facebook" value="<?= htmlspecialchars($contacts['facebook']) ?>"
                               placeholder="https://facebook.com/sualoja"
                               class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-blue-600 transition">
                    </div>

                    <!-- Twitter -->
                    <div class="social-card glass p-6 rounded-2xl border border-white/5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-sky-600/20 rounded-xl flex items-center justify-center">
                                <i data-lucide="twitter" class="w-5 h-5 text-sky-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-black uppercase">Twitter</p>
                                <p class="text-[10px] text-zinc-600">@usuario ou URL</p>
                            </div>
                        </div>
                        <input type="text" name="twitter" value="<?= htmlspecialchars($contacts['twitter']) ?>"
                               placeholder="@sualoja"
                               class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-sky-600 transition">
                    </div>

                    <!-- YouTube -->
                    <div class="social-card glass p-6 rounded-2xl border border-white/5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-red-600/20 rounded-xl flex items-center justify-center">
                                <i data-lucide="youtube" class="w-5 h-5 text-red-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-black uppercase">YouTube</p>
                                <p class="text-[10px] text-zinc-600">Link do canal</p>
                            </div>
                        </div>
                        <input type="text" name="youtube" value="<?= htmlspecialchars($contacts['youtube']) ?>"
                               placeholder="https://youtube.com/@seucanal"
                               class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-red-600 transition">
                    </div>

                    <!-- TikTok -->
                    <div class="social-card glass p-6 rounded-2xl border border-white/5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-zinc-700 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-black uppercase">TikTok</p>
                                <p class="text-[10px] text-zinc-600">@usuario</p>
                            </div>
                        </div>
                        <input type="text" name="tiktok" value="<?= htmlspecialchars($contacts['tiktok']) ?>"
                               placeholder="@sualoja"
                               class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-white transition">
                    </div>

                    <!-- Telegram -->
                    <div class="social-card glass p-6 rounded-2xl border border-white/5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-blue-500/20 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-500" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-black uppercase">Telegram</p>
                                <p class="text-[10px] text-zinc-600">Link do grupo/canal</p>
                            </div>
                        </div>
                        <input type="text" name="telegram" value="<?= htmlspecialchars($contacts['telegram']) ?>"
                               placeholder="https://t.me/seugrupo"
                               class="w-full bg-white/5 border border-white/10 p-3 rounded-xl text-sm outline-none focus:border-blue-500 transition">
                    </div>
                </div>
            </div>

            <!-- Botão Salvar -->
            <div class="flex justify-end gap-4">
                <button type="submit" class="bg-red-600 hover:bg-red-700 px-12 py-4 rounded-2xl font-black uppercase text-sm tracking-widest transition shadow-lg shadow-red-600/20 flex items-center gap-3">
                    <i data-lucide="save" class="w-5 h-5"></i>
                    Salvar Alterações
                </button>
            </div>
        </form>

    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>