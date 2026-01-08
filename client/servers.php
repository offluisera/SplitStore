<?php
/**
 * ============================================
 * SPLITSTORE - GERENCIAMENTO DE SERVIDORES
 * ============================================
 * Sistema completo com múltiplos servidores e validação de planos
 */

session_start();
require_once '../includes/db.php';
require_once '../includes/auth_guard.php';

requireAccess(__FILE__);

if (!isset($_SESSION['store_logged']) || $_SESSION['store_logged'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['store_id']) || empty($_SESSION['store_id'])) {
    die("ERRO CRÍTICO: store_id não encontrado. <a href='login.php'>Faça login novamente</a>");
}

$store_id = $_SESSION['store_id'];
$store_name = $_SESSION['store_name'] ?? 'Loja';
$store_plan = $_SESSION['store_plan'] ?? 'basic';

$message = "";
$messageType = "";

// ========================================
// BUSCAR LIMITE DO PLANO
// ========================================
function getServerLimit($pdo, $plan) {
    try {
        $stmt = $pdo->prepare("SELECT max_servers FROM plan_limits WHERE plan_type = ?");
        $stmt->execute([$plan]);
        $result = $stmt->fetch();
        return $result ? (int)$result['max_servers'] : 1;
    } catch (PDOException $e) {
        error_log("Error fetching plan limit: " . $e->getMessage());
        return 1;
    }
}

$max_servers = getServerLimit($pdo, $store_plan);
$is_unlimited = ($max_servers === -1);

// ========================================
// BUSCAR SERVIDORES CADASTRADOS
// ========================================
try {
    $stmt = $pdo->prepare("
        SELECT *, 
               TIMESTAMPDIFF(MINUTE, last_ping, NOW()) as minutes_since_ping
        FROM minecraft_servers 
        WHERE store_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$store_id]);
    $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $server_count = count($servers);
} catch (PDOException $e) {
    error_log("Error fetching servers: " . $e->getMessage());
    $servers = [];
    $server_count = 0;
}

$can_add_server = $is_unlimited || ($server_count < $max_servers);

// ========================================
// ADICIONAR SERVIDOR
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_server') {
    
    if (!$can_add_server) {
        $message = "Limite de servidores atingido! Seu plano permite " . $max_servers . " servidor(es).";
        $messageType = "error";
    } else {
        $server_name = trim($_POST['server_name'] ?? '');
        $server_id = trim($_POST['server_id'] ?? '');
        $server_ip = trim($_POST['server_ip'] ?? '');
        $server_port = (int)($_POST['server_port'] ?? 25565);
        
        if (empty($server_name) || empty($server_id) || empty($server_ip)) {
            $message = "Preencha todos os campos obrigatórios.";
            $messageType = "error";
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM minecraft_servers WHERE server_id = ?");
                $check->execute([$server_id]);
                
                if ($check->fetch()) {
                    $message = "Este Server ID já está em uso!";
                    $messageType = "error";
                } else {
                    $verification_code = 'SV_' . bin2hex(random_bytes(16));
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO minecraft_servers 
                        (store_id, server_name, server_id, verification_code, server_ip, server_port, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    
                    if ($stmt->execute([$store_id, $server_name, $server_id, $verification_code, $server_ip, $server_port])) {
                        header('Location: servers.php?success=server_added');
                        exit;
                    }
                }
            } catch (PDOException $e) {
                error_log("Error adding server: " . $e->getMessage());
                $message = "Erro ao adicionar servidor: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

// ========================================
// DELETAR SERVIDOR
// ========================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM minecraft_servers WHERE id = ? AND store_id = ?");
        $stmt->execute([$_GET['delete'], $store_id]);
        header('Location: servers.php?success=server_deleted');
        exit;
    } catch (PDOException $e) {
        $message = "Erro ao deletar: " . $e->getMessage();
        $messageType = "error";
    }
}

// ========================================
// TOGGLE STATUS
// ========================================
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $current = $_GET['current'] ?? 'active';
    $newStatus = ($current === 'active') ? 'inactive' : 'active';
    
    try {
        $stmt = $pdo->prepare("UPDATE minecraft_servers SET status = ? WHERE id = ? AND store_id = ?");
        $stmt->execute([$newStatus, $id, $store_id]);
        header('Location: servers.php?success=status_updated');
        exit;
    } catch (PDOException $e) {
        $message = "Erro ao alterar status: " . $e->getMessage();
        $messageType = "error";
    }
}

// ========================================
// GERAR CREDENCIAIS API
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_credentials') {
    try {
        $api_key = 'ca_' . bin2hex(random_bytes(16));
        $api_secret = 'ck_' . bin2hex(random_bytes(24));
        
        $stmt = $pdo->prepare("UPDATE stores SET api_key = ?, api_secret = ?, updated_at = NOW() WHERE id = ?");
        
        if ($stmt->execute([$api_key, $api_secret, $store_id])) {
            header('Location: servers.php?success=credentials_generated');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error generating credentials: " . $e->getMessage());
        $message = "Erro ao gerar credenciais: " . $e->getMessage();
        $messageType = "error";
    }
}

// ========================================
// BUSCAR CREDENCIAIS
// ========================================
$credentials = ['api_key' => null, 'api_secret' => null, 'has_credentials' => false];

try {
    $stmt = $pdo->prepare("SELECT api_key, api_secret FROM stores WHERE id = ?");
    $stmt->execute([$store_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $credentials['api_key'] = $result['api_key'];
        $credentials['api_secret'] = $result['api_secret'];
        $credentials['has_credentials'] = !empty($result['api_key']) && !empty($result['api_secret']);
    }
} catch (PDOException $e) {
    error_log("Error fetching credentials: " . $e->getMessage());
}

// ========================================
// MENSAGENS DE SUCESSO
// ========================================
if (isset($_GET['success'])) {
    $messages = [
        'server_added' => 'Servidor adicionado! Configure o plugin e execute /splitstore verify',
        'server_deleted' => 'Servidor removido com sucesso!',
        'status_updated' => 'Status atualizado!',
        'credentials_generated' => 'Credenciais geradas com sucesso!'
    ];
    $message = $messages[$_GET['success']] ?? '';
    $messageType = "success";
}

// ========================================
// FUNÇÕES AUXILIARES
// ========================================
function getStatusBadge($status, $minutes_offline) {
    if ($status === 'active' && $minutes_offline < 5) {
        return '<span class="flex items-center gap-2 bg-green-500/10 text-green-500 border border-green-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> Online
                </span>';
    } elseif ($status === 'pending') {
        return '<span class="bg-yellow-500/10 text-yellow-500 border border-yellow-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">Pendente</span>';
    } elseif ($status === 'inactive') {
        return '<span class="bg-zinc-500/10 text-zinc-500 border border-zinc-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">Inativo</span>';
    } else {
        return '<span class="bg-red-500/10 text-red-500 border border-red-500/20 px-3 py-1 rounded-lg text-xs font-black uppercase">Offline</span>';
    }
}

function formatLastSeen($minutes) {
    if ($minutes < 1) return 'Agora mesmo';
    if ($minutes < 60) return $minutes . 'm atrás';
    $hours = floor($minutes / 60);
    if ($hours < 24) return $hours . 'h atrás';
    $days = floor($hours / 24);
    return $days . 'd atrás';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Servidores | <?= htmlspecialchars($store_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #000; color: white; }
        .glass { background: rgba(255, 255, 255, 0.02); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .glass-strong { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(40px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .gradient-red { background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%); }
        .server-card { transition: all 0.3s ease; }
        .server-card:hover { transform: translateY(-4px); border-color: rgba(239, 68, 68, 0.3); }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.9); backdrop-filter: blur(10px); z-index: 9999; }
        .modal-overlay.active { display: flex; align-items: center; justify-content: center; }
        .modal-content {
            background: linear-gradient(135deg, rgba(20, 20, 20, 0.95) 0%, rgba(10, 10, 10, 0.98) 100%);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 2rem;
            padding: 3rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 0 60px rgba(239, 68, 68, 0.4);
        }
    </style>
</head>
<body class="flex min-h-screen">

    <?php include 'components/sidebar.php'; ?>

    <main class="flex-1 p-12">
        
        <header class="mb-12">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-4xl font-black uppercase italic tracking-tighter mb-2">
                        Gestão de <span class="text-red-600">Servidores</span>
                    </h1>
                    <p class="text-zinc-500 text-sm font-bold">Configure e monitore seus servidores Minecraft</p>
                </div>
                
                <div class="glass-strong px-6 py-3 rounded-2xl border-white/10">
                    <p class="text-xs font-black uppercase text-zinc-500 mb-1">Plano <?= ucfirst($store_plan) ?></p>
                    <p class="text-2xl font-black">
                        <span class="text-white"><?= $server_count ?></span>
                        <span class="text-zinc-700">/</span>
                        <span class="text-red-600"><?= $is_unlimited ? '∞' : $max_servers ?></span>
                    </p>
                </div>
            </div>

            <?php if($message): ?>
                <div class="glass-strong border-<?= $messageType === 'success' ? 'green' : 'red' ?>-600/30 text-<?= $messageType === 'success' ? 'green' : 'red' ?>-500 p-4 rounded-2xl mb-8 text-sm font-bold flex items-center gap-3">
                    <i data-lucide="<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
        </header>

        <!-- Seção: Adicionar Servidor -->
        <div class="glass-strong rounded-3xl p-10 mb-8 border-white/10">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-black uppercase italic mb-2">Adicionar Servidor</h2>
                    <p class="text-zinc-500 text-sm">Conecte um novo servidor Minecraft à sua loja</p>
                </div>
                
                <?php if ($can_add_server): ?>
                    <button onclick="openAddServerModal()" class="gradient-red px-8 py-4 rounded-2xl font-black text-sm uppercase tracking-widest hover:scale-105 transition flex items-center gap-3 shadow-lg shadow-red-600/30">
                        <i data-lucide="plus" class="w-5 h-5"></i>
                        Novo Servidor
                    </button>
                <?php else: ?>
                    <div class="glass px-6 py-3 rounded-2xl border-yellow-600/30 bg-yellow-600/5">
                        <p class="text-yellow-500 text-sm font-bold flex items-center gap-2">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                            Limite atingido - Faça upgrade do plano
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Guia Rápido -->
            <div class="grid md:grid-cols-3 gap-6">
                <div class="flex gap-4">
                    <div class="w-12 h-12 gradient-red rounded-xl flex items-center justify-center font-black flex-shrink-0 shadow-lg">1</div>
                    <div>
                        <h3 class="text-sm font-black uppercase mb-2">Gere o Server ID</h3>
                        <p class="text-xs text-zinc-500 leading-relaxed">
                            No console: <code class="bg-black/50 px-2 py-1 rounded text-red-600">/splitstore genserver NomeDoSeu</code>
                        </p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="w-12 h-12 bg-zinc-800 rounded-xl flex items-center justify-center font-black flex-shrink-0">2</div>
                    <div>
                        <h3 class="text-sm font-black uppercase mb-2">Adicione Aqui</h3>
                        <p class="text-xs text-zinc-500 leading-relaxed">
                            Clique em "Novo Servidor" e cole o Server ID gerado + IP
                        </p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="w-12 h-12 bg-zinc-800 rounded-xl flex items-center justify-center font-black flex-shrink-0">3</div>
                    <div>
                        <h3 class="text-sm font-black uppercase mb-2">Verifique</h3>
                        <p class="text-xs text-zinc-500 leading-relaxed">
                            Execute <code class="bg-black/50 px-2 py-1 rounded text-green-600">/splitstore verify</code>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista de Servidores -->
        <div class="space-y-6">
            <h2 class="text-2xl font-black uppercase italic">Servidores Cadastrados</h2>

            <?php if (empty($servers)): ?>
                <div class="glass rounded-3xl p-24 flex flex-col items-center justify-center opacity-30">
                    <i data-lucide="server" class="w-16 h-16 mb-6"></i>
                    <p class="font-bold uppercase text-xs tracking-widest">Nenhum servidor cadastrado</p>
                    <p class="text-zinc-700 text-xs mt-2">Adicione seu primeiro servidor acima</p>
                </div>
            <?php else: ?>
                <div class="grid md:grid-cols-2 gap-6">
                    <?php foreach ($servers as $server): ?>
                        <div class="server-card glass-strong p-8 rounded-3xl border border-white/5">
                            
                            <!-- Header -->
                            <div class="flex items-start justify-between mb-6">
                                <div class="flex items-center gap-4">
                                    <div class="w-16 h-16 <?= $server['status'] === 'active' && $server['minutes_since_ping'] < 5 ? 'gradient-red' : 'bg-zinc-800' ?> rounded-2xl flex items-center justify-center shadow-lg">
                                        <i data-lucide="server" class="w-8 h-8"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-black uppercase"><?= htmlspecialchars($server['server_name']) ?></h3>
                                        <p class="text-xs text-zinc-600 font-mono"><?= htmlspecialchars($server['server_id']) ?></p>
                                    </div>
                                </div>
                                
                                <?= getStatusBadge($server['status'], $server['minutes_since_ping']) ?>
                            </div>

                            <!-- Info -->
                            <div class="space-y-4 mb-6">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-zinc-500">Endereço</span>
                                    <span class="font-bold"><?= htmlspecialchars($server['server_ip']) ?>:<?= $server['server_port'] ?></span>
                                </div>

                                <?php if ($server['status'] === 'pending'): ?>
                                    <div class="glass p-4 rounded-xl border border-yellow-600/20 bg-yellow-600/5">
                                        <p class="text-xs font-bold text-yellow-500 mb-2">⏳ Aguardando Verificação</p>
                                        <div class="space-y-2">
                                            <div>
                                                <p class="text-[9px] text-zinc-600 uppercase font-black mb-1">Código de Verificação:</p>
                                                <div class="flex items-center gap-2">
                                                    <code class="flex-1 bg-black/50 px-3 py-2 rounded text-xs font-mono text-yellow-500"><?= htmlspecialchars($server['verification_code']) ?></code>
                                                    <button onclick="copyCode('<?= htmlspecialchars($server['verification_code']) ?>')" class="bg-yellow-600/20 hover:bg-yellow-600/30 p-2 rounded-lg transition">
                                                        <i data-lucide="copy" class="w-4 h-4 text-yellow-500"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <p class="text-[9px] text-zinc-600">
                                                Execute: <code class="text-yellow-600">/splitstore verify <?= htmlspecialchars($server['verification_code']) ?></code>
                                            </p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-zinc-500">Última Conexão</span>
                                        <span class="font-bold"><?= $server['last_ping'] ? formatLastSeen($server['minutes_since_ping']) : 'Nunca' ?></span>
                                    </div>

                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-zinc-500">Jogadores</span>
                                        <span class="font-bold"><?= $server['online_players'] ?> / <?= $server['max_players'] ?></span>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 pt-3 border-t border-white/5">
                                        <div class="text-center">
                                            <p class="text-2xl font-black text-green-500"><?= number_format($server['total_purchases_delivered']) ?></p>
                                            <p class="text-[9px] text-zinc-600 font-bold uppercase">Entregas</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-2xl font-black text-blue-500"><?= number_format($server['total_commands_sent']) ?></p>
                                            <p class="text-[9px] text-zinc-600 font-bold uppercase">Comandos</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Ações -->
                            <div class="grid grid-cols-2 gap-3">
                                <a href="?toggle=<?= $server['id'] ?>&current=<?= $server['status'] ?>" 
                                   class="bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white text-xs font-black uppercase py-3 rounded-xl text-center transition">
                                    <?= $server['status'] === 'active' ? 'Desativar' : 'Ativar' ?>
                                </a>
                                <a href="?delete=<?= $server['id'] ?>" 
                                   onclick="return confirm('Tem certeza? Isso não pode ser desfeito.')"
                                   class="bg-red-900/20 hover:bg-red-900/30 text-red-500 text-xs font-black uppercase py-3 rounded-xl text-center transition">
                                    Remover
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Credenciais API -->
        <?php if ($credentials['has_credentials']): ?>
            <div class="glass-strong rounded-3xl p-10 mt-8 border-white/10">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-xl font-black uppercase italic mb-2">Credenciais Globais</h3>
                        <p class="text-zinc-500 text-sm">Válidas para todos os servidores</p>
                    </div>
                    <button onclick="showRegenerateModal()" class="glass px-6 py-3 rounded-xl hover:border-red-600/50 transition text-xs font-black uppercase text-zinc-500 hover:text-red-600">
                        <i data-lucide="refresh-cw" class="w-4 h-4 inline mr-2"></i>
                        Regerar
                    </button>
                </div>

                <div class="grid md:grid-cols-2 gap-6">
                    <div class="glass p-6 rounded-2xl border border-white/5">
                        <p class="text-xs font-black uppercase text-zinc-600 mb-3">API Key</p>
                        <div class="flex items-center gap-2">
                            <input type="text" id="api_key" readonly value="<?= htmlspecialchars($credentials['api_key']) ?>" 
                                   class="flex-1 bg-black/50 border border-white/10 p-3 rounded-xl text-sm font-mono outline-none">
                            <button onclick="copyToClipboard('api_key')" class="bg-red-600/10 hover:bg-red-600/20 p-3 rounded-xl transition">
                                <i data-lucide="copy" class="w-4 h-4 text-red-600"></i>
                            </button>
                        </div>
                    </div>

                    <div class="glass p-6 rounded-2xl border border-white/5">
                        <p class="text-xs font-black uppercase text-zinc-600 mb-3">API Secret</p>
                        <div class="flex items-center gap-2">
                            <input type="text" id="api_secret" readonly value="<?= htmlspecialchars($credentials['api_secret']) ?>" 
                                   class="flex-1 bg-black/50 border border-white/10 p-3 rounded-xl text-sm font-mono outline-none">
                            <button onclick="copyToClipboard('api_secret')" class="bg-red-600/10 hover:bg-red-600/20 p-3 rounded-xl transition">
                                <i data-lucide="copy" class="w-4 h-4 text-red-600"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="glass-strong rounded-3xl p-10 mt-8 border-yellow-600/20 bg-yellow-600/5">
                <div class="flex items-center gap-6">
                    <div class="w-16 h-16 bg-yellow-600/20 rounded-2xl flex items-center justify-center">
                        <i data-lucide="alert-triangle" class="w-8 h-8 text-yellow-500"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-xl font-black uppercase mb-2">Credenciais Não Configuradas</h3>
                        <p class="text-zinc-500 text-sm">Gere suas credenciais API para começar</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_credentials">
                        <button type="submit" class="gradient-red px-8 py-4 rounded-2xl font-black text-sm uppercase tracking-widest hover:scale-105 transition shadow-lg">
                            Gerar Agora
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Modal: Adicionar Servidor -->
    <div id="addServerModal" class="modal-overlay">
        <div class="modal-content">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-2xl font-black uppercase">Adicionar <span class="text-red-600">Servidor</span></h2>
                <button onclick="closeAddServerModal()" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-xl flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="add_server">

                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Nome do Servidor</label>
                    <input type="text" name="server_name" required placeholder="Ex: Survival Principal" 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl outline-none focus:border-red-600 transition">
                    <p class="text-[9px] text-zinc-600 mt-2 ml-1">Identificação amigável</p>
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Server ID</label>
                    <input type="text" name="server_id" required placeholder="SV_abc123..." 
                           class="w-full bg-white/5 border border-white/10 p-4 rounded-xl outline-none focus:border-red-600 transition font-mono text-sm">
                    <p class="text-[9px] text-zinc-600 mt-2 ml-1">
                        Gerado com: <code class="text-red-600">/splitstore genserver NomeDoSeu</code>
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">IP do Servidor</label>
                        <input type="text" name="server_ip" required placeholder="play.seuservidor.com" 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl outline-none focus:border-red-600 transition">
                    </div>
                    <div>
                        <label class="text-xs font-black uppercase text-zinc-500 mb-3 block">Porta</label>
                        <input type="number" name="server_port" value="25565" min="1" max="65535" 
                               class="w-full bg-white/5 border border-white/10 p-4 rounded-xl outline-none focus:border-red-600 transition">
                    </div>
                </div>

                <div class="glass p-5 rounded-2xl border border-blue-600/20 bg-blue-600/5">
                    <div class="flex items-start gap-3">
                        <i data-lucide="info" class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <p class="text-xs font-bold text-blue-500 mb-2">Importante</p>
                            <ul class="text-[10px] text-zinc-400 space-y-1 leading-relaxed">
                                <li>• O Server ID deve ser único</li>
                                <li>• Você receberá um código de verificação após adicionar</li>
                                <li>• Execute o comando de verificação no console</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeAddServerModal()" 
                            class="flex-1 bg-zinc-900 hover:bg-zinc-800 py-4 rounded-xl font-black uppercase text-xs transition">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="flex-1 gradient-red py-4 rounded-xl font-black uppercase text-xs tracking-widest hover:scale-105 transition shadow-lg flex items-center justify-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Adicionar Servidor
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Regerar Credenciais -->
    <div id="regenerateModal" class="modal-overlay">
        <div class="modal-content max-w-md">
            <div class="w-20 h-20 bg-red-600/20 rounded-2xl flex items-center justify-center mx-auto mb-6">
                <i data-lucide="alert-triangle" class="w-10 h-10 text-red-600"></i>
            </div>
            
            <h2 class="text-2xl font-black uppercase text-center mb-3">Regerar Credenciais?</h2>
            <p class="text-zinc-400 text-center text-sm leading-relaxed mb-6">
                As credenciais antigas serão <strong class="text-red-500">invalidadas</strong>. 
                Todos os servidores precisarão ser reconfigurados.
            </p>

            <form method="POST" class="flex gap-4">
                <input type="hidden" name="action" value="generate_credentials">
                <button type="button" onclick="closeRegenerateModal()" 
                        class="flex-1 bg-zinc-900 hover:bg-zinc-800 py-4 rounded-xl font-black uppercase text-xs transition">
                    Cancelar
                </button>
                <button type="submit" 
                        class="flex-1 gradient-red py-4 rounded-xl font-black uppercase text-xs hover:scale-105 transition shadow-lg">
                    Confirmar
                </button>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        function openAddServerModal() {
            document.getElementById('addServerModal').classList.add('active');
            lucide.createIcons();
        }
        
        function closeAddServerModal() {
            document.getElementById('addServerModal').classList.remove('active');
        }

        function showRegenerateModal() {
            document.getElementById('regenerateModal').classList.add('active');
            lucide.createIcons();
        }
        
        function closeRegenerateModal() {
            document.getElementById('regenerateModal').classList.remove('active');
        }

        function copyToClipboard(elementId) {
            const input = document.getElementById(elementId);
            input.select();
            document.execCommand('copy');
            
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i data-lucide="check" class="w-4 h-4 text-green-600"></i>';
            button.classList.add('bg-green-600/20');
            
            lucide.createIcons();
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('bg-green-600/20');
                lucide.createIcons();
            }, 2000);
        }

        function copyCode(code) {
            navigator.clipboard.writeText(code);
            
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i data-lucide="check" class="w-4 h-4 text-green-600"></i>';
            
            lucide.createIcons();
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                lucide.createIcons();
            }, 2000);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAddServerModal();
                closeRegenerateModal();
            }
        });

        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>