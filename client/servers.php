<?php
/**
 * ============================================
 * SPLITSTORE - GERENCIAMENTO DE SERVIDORES
 * ============================================
 * VERSÃO FINAL - TODAS AS CORREÇÕES APLICADAS
 */

session_start();
require_once '../includes/db.php';

// Proteção de acesso
if (!isset($_SESSION['store_logged']) || $_SESSION['store_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// CORREÇÃO CRÍTICA: Verificar se store_id existe
if (!isset($_SESSION['store_id']) || empty($_SESSION['store_id'])) {
    die("ERRO CRÍTICO: store_id não encontrado na sessão. <a href='login.php'>Faça login novamente</a>");
}

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'] ?? 'Loja';
$store_plan = $_SESSION['store_plan'] ?? 'basic';

$message = "";
$messageType = "";

// ========================================
// GERAR NOVAS CREDENCIAIS
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_credentials') {
    try {
        error_log("=== GERAÇÃO DE CREDENCIAIS ===");
        error_log("Store ID: " . $store_id);
        
        // Gera credenciais aleatórias seguras
        $api_key = 'ca_' . bin2hex(random_bytes(16));
        $api_secret = 'ck_' . bin2hex(random_bytes(24));
        
        error_log("API Key gerada: " . $api_key);
        error_log("API Secret gerada: " . $api_secret);
        
        // UPDATE usando 'id' (que é a primary key)
        $stmt = $pdo->prepare("
            UPDATE stores 
            SET api_key = ?, 
                api_secret = ?, 
                updated_at = NOW() 
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$api_key, $api_secret, $store_id]);
        $affectedRows = $stmt->rowCount();
        
        error_log("Execute result: " . ($result ? 'true' : 'false'));
        error_log("Affected rows: " . $affectedRows);
        
        if ($result && $affectedRows > 0) {
            error_log("✅ Credenciais salvas com sucesso!");
            header('Location: servers.php?success=generated');
            exit;
        } else {
            error_log("❌ Nenhuma linha foi atualizada");
            $message = "Erro: Nenhuma alteração foi feita. Verifique se o registro existe.";
            $messageType = "error";
        }
    } catch (PDOException $e) {
        error_log("❌ Erro SQL: " . $e->getMessage());
        $message = "Erro ao gerar credenciais: " . $e->getMessage();
        $messageType = "error";
    }
}

// ========================================
// BUSCAR CREDENCIAIS ATUAIS
// ========================================
$credentials = [
    'api_key' => null,
    'api_secret' => null,
    'has_credentials' => false
];

try {
    // SELECT usando 'id' (primary key)
    $stmt = $pdo->prepare("
        SELECT id, store_name, api_key, api_secret 
        FROM stores 
        WHERE id = ?
    ");
    $stmt->execute([$store_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        error_log("Store encontrado: " . $result['store_name']);
        
        $credentials['api_key'] = $result['api_key'];
        $credentials['api_secret'] = $result['api_secret'];
        $credentials['has_credentials'] = !empty($result['api_key']) && !empty($result['api_secret']);
        
        error_log("Tem credenciais: " . ($credentials['has_credentials'] ? 'SIM' : 'NÃO'));
    } else {
        error_log("❌ Store não encontrado com id = " . $store_id);
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar credenciais: " . $e->getMessage());
    $message = "Erro ao buscar credenciais: " . $e->getMessage();
    $messageType = "error";
}

// Mensagens
if (isset($_GET['success']) && $_GET['success'] === 'generated') {
    $message = "Credenciais geradas com sucesso!";
    $messageType = "success";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servidores | <?= htmlspecialchars($store_name) ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: #000; 
            color: white;
        }
        
        .glass { 
            background: rgba(255, 255, 255, 0.02); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255, 255, 255, 0.05); 
        }
        
        .glass-strong {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(40px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .credential-input {
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .credential-input:focus {
            border-color: rgba(220, 38, 38, 0.5);
            box-shadow: 0 0 20px rgba(220, 38, 38, 0.2);
        }
        
        .credential-box {
            position: relative;
            transition: all 0.3s ease;
        }
        
        .credential-box:hover {
            transform: translateY(-2px);
        }
        
        .glow-red {
            box-shadow: 0 0 40px rgba(220, 38, 38, 0.3);
        }
        
        .glow-red:hover {
            box-shadow: 0 0 60px rgba(220, 38, 38, 0.5);
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .slide-down {
            animation: slideDown 0.5s ease-out;
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #fff 0%, #999 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .gradient-red {
            background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 9999;
            animation: fadeIn 0.3s ease-out;
        }

        .modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: linear-gradient(135deg, rgba(20, 20, 20, 0.95) 0%, rgba(10, 10, 10, 0.98) 100%);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 2rem;
            padding: 3rem;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 0 60px rgba(239, 68, 68, 0.4);
            animation: slideUp 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%);
            border-radius: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            box-shadow: 0 0 40px rgba(239, 68, 68, 0.5);
        }

        .modal-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .modal-btn {
            flex: 1;
            padding: 1rem;
            border-radius: 1rem;
            font-weight: 900;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.1em;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .modal-btn-cancel {
            background: rgba(255, 255, 255, 0.05);
            color: #999;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-btn-cancel:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            transform: translateY(-2px);
        }

        .modal-btn-confirm {
            background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%);
            color: white;
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.3);
        }

        .modal-btn-confirm:hover {
            box-shadow: 0 0 50px rgba(239, 68, 68, 0.5);
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        
        <header class="mb-16">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-16 h-16 gradient-red rounded-2xl flex items-center justify-center shadow-lg shadow-red-900/40">
                    <i data-lucide="server" class="w-8 h-8"></i>
                </div>
                <div>
                    <h1 class="text-4xl font-black uppercase italic tracking-tighter gradient-text">
                        Gestão de Servidores
                    </h1>
                    <p class="text-zinc-500 text-sm font-bold mt-1">
                        Configure o plugin Java e conecte seu servidor Minecraft
                    </p>
                </div>
            </div>
        </header>

        <?php if($message): ?>
            <div class="glass-strong border-<?= $messageType === 'success' ? 'green' : 'red' ?>-600/30 text-<?= $messageType === 'success' ? 'green' : 'red' ?>-500 p-6 rounded-3xl mb-8 text-sm font-bold flex items-center gap-4 slide-down shadow-lg">
                <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-6 h-6"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="glass-strong rounded-[2.5rem] p-10 mb-12 border-white/10 relative overflow-hidden">
            <div class="absolute inset-0 opacity-5">
                <div class="absolute inset-0" style="background-image: radial-gradient(circle, #ef4444 1px, transparent 1px); background-size: 40px 40px;"></div>
            </div>
            
            <div class="relative z-10 flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <div class="w-20 h-20 rounded-2xl flex items-center justify-center <?= $credentials['has_credentials'] ? 'gradient-red' : 'bg-yellow-600' ?> shadow-2xl">
                        <i data-lucide="<?= $credentials['has_credentials'] ? 'shield-check' : 'shield-alert' ?>" class="w-10 h-10"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black uppercase italic tracking-tight mb-2">
                            Status da Integração
                        </h2>
                        <p class="text-zinc-400 text-base">
                            <?= $credentials['has_credentials'] 
                                ? '✓ Sistema configurado e operacional' 
                                : '⚠ Configure suas credenciais para ativar' ?>
                        </p>
                    </div>
                </div>
                
                <div class="text-center">
                    <div class="inline-flex items-center gap-3 px-6 py-3 rounded-2xl border-2 <?= $credentials['has_credentials'] ? 'bg-green-600/20 border-green-600/30' : 'bg-yellow-600/20 border-yellow-600/30' ?> mb-3">
                        <span class="w-3 h-3 rounded-full bg-current animate-pulse"></span>
                        <span class="text-sm font-black uppercase tracking-wider <?= $credentials['has_credentials'] ? 'text-green-500' : 'text-yellow-500' ?>">
                            <?= $credentials['has_credentials'] ? 'Sistema Ativo' : 'Aguardando Config' ?>
                        </span>
                    </div>
                    <p class="text-[10px] text-zinc-600 font-bold uppercase tracking-widest">
                        <?= $credentials['has_credentials'] ? 'Pronto para uso' : 'Ação necessária' ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-5 gap-8">
            
            <div class="lg:col-span-3 glass-strong rounded-[2.5rem] p-10 border-white/10">
                <div class="flex items-center justify-between mb-10">
                    <div>
                        <h3 class="text-2xl font-black uppercase italic tracking-tight mb-2 gradient-text">
                            Credenciais de Acesso
                        </h3>
                        <p class="text-zinc-500 text-sm">
                            Configure estas chaves no plugin Java do seu servidor
                        </p>
                    </div>
                    
                    <button type="button" onclick="showConfirmModal()" class="gradient-red hover:shadow-xl px-8 py-4 rounded-2xl font-black text-sm uppercase tracking-widest transition-all hover:scale-105 flex items-center gap-3 glow-red">
                        <i data-lucide="key" class="w-5 h-5"></i>
                        <?= $credentials['has_credentials'] ? 'Regerar Credenciais' : 'Gerar Credenciais' ?>
                    </button>
                </div>

                <?php if ($credentials['has_credentials']): ?>
                    <div class="space-y-6">
                        
                        <div class="credential-box glass rounded-2xl p-6 border border-white/10 hover:border-red-600/30">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-red-600/10 rounded-xl flex items-center justify-center">
                                        <i data-lucide="key" class="w-5 h-5 text-red-600"></i>
                                    </div>
                                    <div>
                                        <label class="text-xs font-black uppercase text-white tracking-widest block">
                                            API Key
                                        </label>
                                        <span class="text-[9px] text-zinc-600">Chave de identificação</span>
                                    </div>
                                </div>
                                <button onclick="copyToClipboard('api_key', this)" class="flex items-center gap-2 bg-red-600/10 hover:bg-red-600/20 text-red-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all hover:scale-105">
                                    <i data-lucide="copy" class="w-4 h-4"></i> Copiar
                                </button>
                            </div>
                            <input type="text" 
                                   id="api_key" 
                                   readonly 
                                   value="<?= htmlspecialchars($credentials['api_key']) ?>"
                                   class="w-full credential-input p-4 rounded-xl text-sm font-mono text-white outline-none">
                        </div>

                        <div class="credential-box glass rounded-2xl p-6 border border-white/10 hover:border-red-600/30">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-blue-600/10 rounded-xl flex items-center justify-center">
                                        <i data-lucide="shield" class="w-5 h-5 text-blue-600"></i>
                                    </div>
                                    <div>
                                        <label class="text-xs font-black uppercase text-white tracking-widest block">
                                            API Secret
                                        </label>
                                        <span class="text-[9px] text-zinc-600">Chave de autenticação</span>
                                    </div>
                                </div>
                                <button onclick="copyToClipboard('api_secret', this)" class="flex items-center gap-2 bg-red-600/10 hover:bg-red-600/20 text-red-600 px-4 py-2 rounded-xl text-[10px] font-black uppercase transition-all hover:scale-105">
                                    <i data-lucide="copy" class="w-4 h-4"></i> Copiar
                                </button>
                            </div>
                            <input type="text" 
                                   id="api_secret" 
                                   readonly 
                                   value="<?= htmlspecialchars($credentials['api_secret']) ?>"
                                   class="w-full credential-input p-4 rounded-xl text-sm font-mono text-white outline-none">
                        </div>

                        <div class="glass-strong p-6 rounded-2xl border border-yellow-600/30 bg-yellow-600/5">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 bg-yellow-600/20 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="shield-alert" class="w-6 h-6 text-yellow-500"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-black uppercase text-yellow-500 mb-2">Segurança Crítica</h4>
                                    <p class="text-xs text-zinc-400 leading-relaxed">
                                        Estas credenciais concedem acesso total ao seu sistema. <strong class="text-white">Nunca compartilhe com terceiros.</strong> 
                                        Em caso de comprometimento, regere imediatamente novas credenciais.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-24 opacity-40">
                        <div class="w-24 h-24 bg-zinc-900 rounded-3xl flex items-center justify-center mx-auto mb-6 border border-white/5">
                            <i data-lucide="key-round" class="w-12 h-12 text-zinc-700"></i>
                        </div>
                        <h4 class="text-xl font-black uppercase tracking-tight text-zinc-700 mb-3">
                            Credenciais não Configuradas
                        </h4>
                        <p class="text-sm text-zinc-600 max-w-md mx-auto leading-relaxed">
                            Clique no botão "Gerar Credenciais" acima para criar suas chaves de acesso e conectar o plugin Java ao sistema.
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-2 glass-strong rounded-[2.5rem] p-10 border-white/10">
                <div class="mb-8">
                    <h3 class="text-xl font-black uppercase italic tracking-tight mb-2 gradient-text">
                        Guia de Instalação
                    </h3>
                    <p class="text-zinc-500 text-xs">
                        Configure em menos de 5 minutos
                    </p>
                </div>

                <div class="space-y-6">
                    
                    <div class="flex gap-4">
                        <div class="w-12 h-12 gradient-red rounded-xl flex items-center justify-center flex-shrink-0 font-black shadow-lg">
                            1
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-black uppercase mb-2">Download</h4>
                            <p class="text-xs text-zinc-500 leading-relaxed mb-3">
                                Baixe o SplitStore.jar
                            </p>
                            <a href="#" class="inline-flex items-center gap-2 glass px-4 py-2 rounded-xl text-[10px] font-black uppercase hover:border-red-600/50 transition border border-white/5">
                                <i data-lucide="download" class="w-3 h-3"></i>
                                Download
                            </a>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="w-12 h-12 bg-zinc-800 rounded-xl flex items-center justify-center flex-shrink-0 font-black">
                            2
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-black uppercase mb-2">Instalar</h4>
                            <p class="text-xs text-zinc-500 leading-relaxed mb-3">
                                Coloque na pasta <code class="bg-black/50 px-2 py-1 rounded text-red-600 text-[10px]">/plugins</code>
                            </p>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="w-12 h-12 bg-zinc-800 rounded-xl flex items-center justify-center flex-shrink-0 font-black">
                            3
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-black uppercase mb-2">Configurar</h4>
                            <p class="text-xs text-zinc-500 leading-relaxed mb-3">
                                Edite o <code class="bg-black/50 px-2 py-1 rounded text-red-600 text-[10px]">config.yml</code>
                            </p>
                            <?php if($credentials['has_credentials']): ?>
                            <div class="glass p-3 rounded-xl font-mono text-[9px] text-zinc-400 space-y-1 border border-white/5">
                                <div>api-key: "<span class="text-red-500"><?= substr($credentials['api_key'], 0, 20) ?>...</span>"</div>
                                <div>api-secret: "<span class="text-blue-500"><?= substr($credentials['api_secret'], 0, 20) ?>...</span>"</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="w-12 h-12 bg-zinc-800 rounded-xl flex items-center justify-center flex-shrink-0 font-black">
                            4
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-black uppercase mb-2">Testar</h4>
                            <p class="text-xs text-zinc-500 leading-relaxed mb-3">
                                Verifique com o comando
                            </p>
                            <div class="glass p-3 rounded-xl font-mono text-[10px] text-green-500 border border-white/5">
                                /splitstore status
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-10 pt-8 border-t border-white/5">
                    <div class="space-y-3">
                        <a href="#" class="flex items-center gap-3 text-xs text-zinc-500 hover:text-red-600 transition p-3 rounded-xl hover:bg-white/5">
                            <i data-lucide="book-open" class="w-4 h-4"></i>
                            Documentação
                        </a>
                        <a href="#" class="flex items-center gap-3 text-xs text-zinc-500 hover:text-red-600 transition p-3 rounded-xl hover:bg-white/5">
                            <i data-lucide="youtube" class="w-4 h-4"></i>
                            Vídeo Tutorial
                        </a>
                        <a href="#" class="flex items-center gap-3 text-xs text-zinc-500 hover:text-red-600 transition p-3 rounded-xl hover:bg-white/5">
                            <i data-lucide="headset" class="w-4 h-4"></i>
                            Suporte 24/7
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <div id="confirmModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-icon">
                <i data-lucide="alert-triangle" class="w-10 h-10"></i>
            </div>
            
            <h2 class="text-2xl font-black uppercase text-center mb-3 tracking-tight">
                Gerar Novas Credenciais?
            </h2>
            
            <p class="text-zinc-400 text-center text-sm leading-relaxed mb-2">
                As credenciais antigas serão <strong class="text-red-500">INVALIDADAS</strong> imediatamente.
            </p>
            
            <p class="text-zinc-500 text-center text-xs leading-relaxed">
                O plugin precisará ser reconfigurado com as novas chaves para continuar funcionando.
            </p>

            <div class="modal-buttons">
                <button type="button" onclick="closeConfirmModal()" class="modal-btn modal-btn-cancel">
                    <i data-lucide="x" class="w-4 h-4"></i>
                    Cancelar
                </button>
                <button type="button" onclick="confirmGenerate()" class="modal-btn modal-btn-confirm">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    Confirmar
                </button>
            </div>
        </div>
    </div>

    <form id="generateForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="generate_credentials">
    </form>

    <script>
        lucide.createIcons();
        
        function showConfirmModal() {
            document.getElementById('confirmModal').classList.add('active');
            lucide.createIcons();
        }
        
        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('active');
        }
        
        function confirmGenerate() {
            document.getElementById('generateForm').submit();
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeConfirmModal();
            }
        });

        document.getElementById('confirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfirmModal();
            }
        });
        
        function copyToClipboard(elementId, button) {
            const input = document.getElementById(elementId);
            input.select();
            document.execCommand('copy');
            
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Copiado!';
            button.classList.remove('bg-red-600/10', 'text-red-600');
            button.classList.add('bg-green-600/20', 'text-green-600');
            
            lucide.createIcons();
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('bg-green-600/20', 'text-green-600');
                button.classList.add('bg-red-600/10', 'text-red-600');
                lucide.createIcons();
            }, 2500);
        }
    </script>
</body>
</html>