<?php
session_start();
require_once '../includes/db.php';

if (isset($_SESSION['store_logged'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($email)) {
        try {
            // Por enquanto usa email como autenticação (implementar senha em breve)
            $stmt = $pdo->prepare("SELECT * FROM stores WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $store = $stmt->fetch();
            
            if ($store) {
                $_SESSION['store_logged'] = true;
                $_SESSION['store_id'] = $store['id'];
                $_SESSION['store_name'] = $store['store_name'];
                $_SESSION['store_plan'] = $store['plan_type'];
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Loja não encontrada ou inativa.";
            }
        } catch (PDOException $e) {
            $error = "Erro ao processar login.";
        }
    } else {
        $error = "Preencha o e-mail.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Cliente | SplitStore</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #000; 
            color: white;
        }
        #particles-js {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        .content-wrapper {
            position: relative;
            z-index: 10;
        }
        .glass { 
            background: rgba(10, 10, 10, 0.7); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255, 255, 255, 0.05); 
        }
        .glow-focus:focus {
            border-color: rgba(220, 38, 38, 0.5);
            box-shadow: 0 0 20px -5px rgba(220, 38, 38, 0.3);
        }
    </style>
</head>
<body class="antialiased min-h-screen flex items-center justify-center p-6">

    <div id="particles-js"></div>

    <div class="content-wrapper w-full max-w-md">
        <div class="glass rounded-[3rem] p-10">
            
            <div class="text-center mb-10">
                <a href="../index.php" class="inline-block mb-6 text-zinc-600 hover:text-white transition">
                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                </a>
                <h1 class="text-3xl font-black uppercase italic tracking-tighter mb-2">
                    Área do <span class="text-red-600">Cliente</span>
                </h1>
                <p class="text-zinc-500 text-sm font-medium">Acesse seu painel de controle</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-900/20 border border-red-600/30 text-red-500 p-4 rounded-2xl mb-6 text-sm font-bold text-center">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="text-[10px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">E-mail da Loja</label>
                    <div class="relative">
                        <i data-lucide="mail" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-600"></i>
                        <input type="email" name="email" required 
                               placeholder="seu@email.com"
                               class="w-full bg-white/5 border border-white/10 pl-12 pr-4 py-4 rounded-xl text-sm outline-none glow-focus transition">
                    </div>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Senha (Temporário)</label>
                    <div class="relative">
                        <i data-lucide="lock" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-600"></i>
                        <input type="password" name="password" 
                               placeholder="••••••••"
                               class="w-full bg-white/5 border border-white/10 pl-12 pr-4 py-4 rounded-xl text-sm outline-none glow-focus transition">
                    </div>
                    <p class="text-[10px] text-zinc-600 mt-2 ml-2">Login temporário apenas com e-mail</p>
                </div>

                <button type="submit" 
                        class="w-full bg-red-600 hover:bg-red-700 text-white py-4 rounded-xl font-black uppercase text-xs tracking-widest transition-all hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-red-600/20">
                    Acessar Painel
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-white/5 text-center">
                <p class="text-zinc-600 text-xs">
                    Não tem uma loja? 
                    <a href="../index.php#planos" class="text-red-600 hover:underline font-bold">Criar agora</a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        lucide.createIcons();
        particlesJS("particles-js", {
            "particles": {
                "number": { "value": 40, "density": { "enable": true, "value_area": 800 } },
                "color": { "value": "#ff0000" },
                "opacity": { "value": 0.15 },
                "size": { "value": 2 },
                "line_linked": { "enable": true, "distance": 150, "color": "#ff0000", "opacity": 0.1, "width": 1 },
                "move": { "enable": true, "speed": 0.7 }
            }
        });
    </script>
</body>
</html>