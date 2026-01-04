<?php
session_start();
// Se já estiver logado, redireciona para o dashboard (que criaremos a seguir)
if(isset($_SESSION['admin_logged'])){
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SplitStore | Acesso Restrito</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #000; 
            color: white;
            overflow: hidden;
            height: 100vh;
        }

        #particles-js {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: 1;
            top: 0;
            left: 0;
        }

        .login-wrapper {
            position: relative;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            padding: 20px;
        }

        .glass-card {
            background: rgba(10, 10, 10, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            width: 100%;
            max-width: 400px;
        }

        .glow-red-focus:focus-within {
            border-color: rgba(220, 38, 38, 0.5);
            box-shadow: 0 0 20px -5px rgba(220, 38, 38, 0.3);
        }

        input {
            background: rgba(20, 20, 20, 0.8) !important;
            outline: none;
        }
    </style>
</head>
<body class="antialiased">

    <div id="particles-js"></div>

    <div class="login-wrapper">
        <div class="glass-card p-10 rounded-[2.5rem] shadow-2xl border border-white/5">
            
            <div class="text-center mb-10">
                <a href="../index.php" class="inline-block mb-6 opacity-50 hover:opacity-100 transition-opacity">
                    <i data-lucide="arrow-left" class="w-5 h-5 mx-auto"></i>
                </a>
                <h2 class="text-2xl font-black uppercase italic tracking-tighter">
                    Split<span class="text-red-600">Admin</span>
                </h2>
                <p class="text-zinc-500 text-[10px] font-bold uppercase tracking-[0.3em] mt-2">Área Restrita</p>
            </div>

            <form action="auth.php" method="POST" class="space-y-6">
                <div class="space-y-2 group">
                    <label class="text-[10px] font-black uppercase tracking-widest text-zinc-600 ml-1">Usuário</label>
                    <div class="relative flex items-center glow-red-focus rounded-2xl border border-white/5 transition-all">
                        <i data-lucide="user" class="absolute left-4 w-4 h-4 text-zinc-600"></i>
                        <input type="text" name="username" required
                            class="w-full py-4 pl-12 pr-4 rounded-2xl text-sm text-white border-none placeholder:text-zinc-800"
                            placeholder="Seu usuário">
                    </div>
                </div>

                <div class="space-y-2 group">
                    <label class="text-[10px] font-black uppercase tracking-widest text-zinc-600 ml-1">Senha</label>
                    <div class="relative flex items-center glow-red-focus rounded-2xl border border-white/5 transition-all">
                        <i data-lucide="lock" class="absolute left-4 w-4 h-4 text-zinc-600"></i>
                        <input type="password" name="password" required
                            class="w-full py-4 pl-12 pr-4 rounded-2xl text-sm text-white border-none placeholder:text-zinc-800"
                            placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" 
                    class="w-full bg-red-600 hover:bg-red-700 text-white py-4 rounded-2xl font-black uppercase text-[11px] tracking-widest transition-all hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-red-900/20">
                    Autenticar Acesso
                </button>
            </form>

            <div class="mt-8 text-center">
                <p class="text-zinc-700 text-[9px] font-bold uppercase tracking-widest leading-relaxed">
                    © 2026 Grupo Split<br>Soluções WEB's
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
                "shape": { "type": "circle" },
                "opacity": { "value": 0.2, "random": true },
                "size": { "value": 2, "random": true },
                "line_linked": { "enable": true, "distance": 150, "color": "#ff0000", "opacity": 0.1, "width": 1 },
                "move": { "enable": true, "speed": 0.8, "direction": "none", "random": true, "straight": false, "out_mode": "out", "bounce": false }
            },
            "retina_detect": true
        });
    </script>
</body>
</html>