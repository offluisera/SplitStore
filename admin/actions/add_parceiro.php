<?php
// 1. Proteção por Sessão
session_start();
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: ../login.php'); // Ajuste o caminho para sua tela de login
    exit();
}

// 2. Diagnóstico de Erros (Ativar apenas para testes)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 3. Conexão com Banco
$db_path = '../../includes/db.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("Erro crítico: Arquivo de conexão não encontrado.");
}

$message = "";

// 4. Lógica de Processamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verificamos se todos os campos necessários existem antes de prosseguir
    if (isset($_POST['nome'], $_POST['status'], $_FILES['logo'])) {
        
        $nome = $_POST['nome'];
        $site_url = $_POST['site_url'] ?? '';
        $ordem = (int)($_POST['ordem'] ?? 0);
        $status = $_POST['status'];
        
        // Configuração de Upload
        $target_dir = "../../uploads/parceiros/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = $_FILES["logo"]["name"];
        $file_tmp = $_FILES["logo"]["tmp_name"];
        
        if (!empty($file_tmp)) {
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            $db_logo_path = "uploads/parceiros/" . $new_filename;

            if (move_uploaded_file($file_tmp, $target_file)) {
                try {
                    // Query baseada na sua print: id, nome, logo_url, site_url, ordem, status, created_at
                    $sql = "INSERT INTO parceiros (nome, logo_url, site_url, ordem, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())";
                    $stmt = $pdo->prepare($sql);
                    
                    if ($stmt->execute([$nome, $db_logo_path, $site_url, $ordem, $status])) {
                        $message = "Parceiro cadastrado com sucesso!";
                        if (isset($redis)) { $redis->del('site_public_data'); }
                    }
                } catch (PDOException $e) {
                    $message = "Erro no banco: " . $e->getMessage();
                }
            } else {
                $message = "Erro ao mover arquivo de imagem.";
            }
        } else {
            $message = "Por favor, selecione uma logo.";
        }
    } else {
        $message = "Campos obrigatórios ausentes no envio.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Adicionar Parceiro</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-zinc-950 text-white font-sans p-6">

    <div class="max-w-lg mx-auto bg-zinc-900 p-8 rounded-3xl border border-white/5 shadow-2xl mt-10">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-black uppercase italic">Novo <span class="text-red-600">Parceiro</span></h2>
            <span class="text-[9px] bg-red-600/10 text-red-500 px-2 py-1 rounded border border-red-500/20 uppercase font-bold">Protegido</span>
        </div>

        <?php if($message): ?>
            <div class="bg-zinc-800 border-l-4 border-red-600 text-zinc-300 p-4 rounded-r-xl mb-6 text-sm">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-zinc-500 text-[10px] uppercase font-bold mb-1 tracking-widest">Nome do Servidor</label>
                <input type="text" name="nome" required placeholder="Ex: Rede Split" 
                       class="w-full bg-black border border-white/10 rounded-xl p-3 focus:border-red-600 outline-none transition">
            </div>

            <div>
                <label class="block text-zinc-500 text-[10px] uppercase font-bold mb-1 tracking-widest">URL do Site</label>
                <input type="url" name="site_url" placeholder="https://..." 
                       class="w-full bg-black border border-white/10 rounded-xl p-3 focus:border-red-600 outline-none transition text-sm">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-zinc-500 text-[10px] uppercase font-bold mb-1 tracking-widest">Ordem</label>
                    <input type="number" name="ordem" value="0" 
                           class="w-full bg-black border border-white/10 rounded-xl p-3 focus:border-red-600 outline-none transition">
                </div>
                <div>
                    <label class="block text-zinc-500 text-[10px] uppercase font-bold mb-1 tracking-widest">Status</label>
                    <select name="status" class="w-full bg-black border border-white/10 rounded-xl p-3 focus:border-red-600 outline-none transition text-sm">
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-zinc-500 text-[10px] uppercase font-bold mb-1 tracking-widest">Logo do Parceiro</label>
                <input type="file" name="logo" required 
                       class="w-full bg-black border border-white/10 rounded-xl p-3 text-xs file:mr-4 file:py-1 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-bold file:bg-red-600 file:text-white hover:file:bg-red-700">
            </div>

            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-black uppercase py-4 rounded-xl transition-all transform hover:scale-[1.01] mt-4 shadow-lg shadow-red-600/20">
                Cadastrar Parceiro
            </button>
            
            <div class="flex justify-between items-center mt-6">
                <a href="../../index.php" class="text-zinc-600 text-[10px] uppercase font-bold hover:text-white transition tracking-widest">Site Principal</a>
                <a href="../logout.php" class="text-red-900 text-[10px] uppercase font-bold hover:text-red-500 transition tracking-widest">Sair da Sessão</a>
            </div>
        </form>
    </div>

</body>
</html>