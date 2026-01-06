<?php
session_start();
// Se já estiver logado, redireciona para o dashboard
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background: #0a0a0a;
            color: white;
            overflow: hidden;
            height: 100vh;
            position: relative;
        }

        /* Background gradient animado */
        .bg-gradient {
            position: fixed;
            width: 200%;
            height: 200%;
            top: -50%;
            left: -50%;
            background: radial-gradient(circle at 20% 50%, rgba(220, 38, 38, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(139, 0, 0, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 40% 20%, rgba(185, 28, 28, 0.08) 0%, transparent 50%);
            animation: rotate 30s linear infinite;
            z-index: 1;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Grid animado */
        .grid-bg {
            position: fixed;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: 2;
            animation: grid-move 20s linear infinite;
        }

        @keyframes grid-move {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        #particles-js {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: 3;
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

        /* Card premium com glassmorphism */
        .glass-card {
            background: rgba(15, 15, 15, 0.7);
            backdrop-filter: blur(40px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 
                0 8px 32px 0 rgba(0, 0, 0, 0.6),
                inset 0 1px 0 0 rgba(255, 255, 255, 0.05);
            width: 100%;
            max-width: 440px;
            position: relative;
            overflow: hidden;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, 
                transparent 30%, 
                rgba(220, 38, 38, 0.1) 50%, 
                transparent 70%);
            border-radius: inherit;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.5s;
        }

        .glass-card:hover::before {
            opacity: 1;
        }

        /* Logo animado */
        .logo-container {
            position: relative;
            display: inline-block;
        }

        .logo-glow {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 120px;
            height: 120px;
            background: radial-gradient(circle, rgba(220, 38, 38, 0.3) 0%, transparent 70%);
            border-radius: 50%;
            animation: pulse-glow 3s ease-in-out infinite;
            z-index: -1;
        }

        @keyframes pulse-glow {
            0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.5; }
            50% { transform: translate(-50%, -50%) scale(1.2); opacity: 0.8; }
        }

        /* Input groups com efeitos premium */
        .input-group {
            position: relative;
            transition: all 0.3s ease;
        }

        .input-wrapper {
            position: relative;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(20, 20, 20, 0.6);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .input-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(220, 38, 38, 0.1), 
                transparent);
            transition: left 0.5s;
        }

        .input-wrapper:focus-within {
            border-color: rgba(220, 38, 38, 0.4);
            background: rgba(25, 25, 25, 0.8);
            box-shadow: 
                0 0 0 3px rgba(220, 38, 38, 0.1),
                0 8px 20px rgba(220, 38, 38, 0.15);
            transform: translateY(-2px);
        }

        .input-wrapper:focus-within::before {
            left: 100%;
        }

        input {
            background: transparent !important;
            outline: none;
            transition: all 0.3s ease;
        }

        input::placeholder {
            color: rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        input:focus::placeholder {
            color: rgba(255, 255, 255, 0.3);
            transform: translateX(5px);
        }

        /* Botão premium */
        .btn-premium {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            border: 1px solid rgba(220, 38, 38, 0.3);
            box-shadow: 
                0 4px 15px rgba(220, 38, 38, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .btn-premium::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-premium:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-premium:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 8px 25px rgba(220, 38, 38, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .btn-premium:active {
            transform: translateY(-1px);
        }

        /* Label animado */
        .input-label {
            display: block;
            margin-bottom: 8px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: rgba(255, 255, 255, 0.4);
            transition: all 0.3s ease;
        }

        .input-group:focus-within .input-label {
            color: rgba(220, 38, 38, 0.8);
            transform: translateX(4px);
        }

        /* Ícones com animação */
        .icon-wrapper {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .input-wrapper:focus-within .icon-wrapper {
            color: rgba(220, 38, 38, 0.8);
            transform: translateY(-50%) scale(1.1);
        }

        /* Fade in animation */
        .fade-in {
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Link de volta */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.4);
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(220, 38, 38, 0.3);
            color: rgba(220, 38, 38, 0.8);
            transform: translateX(-4px);
        }

        /* Scanline effect */
        .scanline {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                to bottom,
                transparent 50%,
                rgba(255, 255, 255, 0.01) 51%
            );
            background-size: 100% 4px;
            pointer-events: none;
            z-index: 100;
            opacity: 0.1;
        }
    </style>
</head>
<body class="antialiased">

    <div class="bg-gradient"></div>
    <div class="grid-bg"></div>
    <div id="particles-js"></div>
    <div class="scanline"></div>

    <div class="login-wrapper">
        <div class="glass-card p-12 rounded-[2rem] fade-in">
            
            <!-- Header -->
            <div class="text-center mb-12">
                <a href="../index.php" class="back-link mb-8">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span class="text-xs font-semibold uppercase tracking-wider">Voltar</span>
                </a>
                
                <div class="logo-container mt-8 mb-6">
                    <div class="logo-glow"></div>
                    <h2 class="text-3xl font-black uppercase italic tracking-tighter relative z-10">
                        Split<span class="text-red-600">Admin</span>
                    </h2>
                </div>
                
                <div class="space-y-1">
                    <p class="text-zinc-500 text-[10px] font-bold uppercase tracking-[0.4em]">Área Restrita</p>
                    <div class="flex items-center justify-center gap-2 mt-3">
                        <div class="w-1 h-1 rounded-full bg-red-600 animate-pulse"></div>
                        <p class="text-zinc-700 text-[9px] font-medium uppercase tracking-widest">Sistema Seguro</p>
                        <div class="w-1 h-1 rounded-full bg-red-600 animate-pulse"></div>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <form action="auth.php" method="POST" class="space-y-6">
                
                <!-- Username -->
                <div class="input-group">
                    <label class="input-label">Usuário</label>
                    <div class="input-wrapper">
                        <div class="icon-wrapper">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </div>
                        <input 
                            type="text" 
                            name="username" 
                            required
                            class="w-full py-4 pl-12 pr-4 text-sm text-white"
                            placeholder="Digite seu usuário"
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="input-group">
                    <label class="input-label">Senha</label>
                    <div class="input-wrapper">
                        <div class="icon-wrapper">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <input 
                            type="password" 
                            name="password" 
                            required
                            class="w-full py-4 pl-12 pr-4 text-sm text-white"
                            placeholder="Digite sua senha"
                        >
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-premium w-full py-4 rounded-2xl font-black uppercase text-xs tracking-[0.2em] mt-8 relative z-10">
                    <span class="relative z-10 flex items-center justify-center gap-2">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>
                        Autenticar Acesso
                    </span>
                </button>
            </form>

            <!-- Footer -->
            <div class="mt-10 pt-8 border-t border-white/5">
                <div class="flex items-center justify-center gap-3 text-zinc-700 text-[9px] font-semibold uppercase tracking-[0.2em]">
                    <i data-lucide="shield" class="w-3 h-3 text-red-900/50"></i>
                    <span>© 2026 Grupo Split</span>
                    <span class="text-zinc-800">•</span>
                    <span>Soluções Web's</span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        // Inicializa Lucide icons
        lucide.createIcons();

        // Particles.js config
        particlesJS("particles-js", {
            "particles": {
                "number": { 
                    "value": 50, 
                    "density": { "enable": true, "value_area": 1000 } 
                },
                "color": { "value": "#dc2626" },
                "shape": { 
                    "type": "circle",
                    "stroke": { "width": 0 }
                },
                "opacity": { 
                    "value": 0.15, 
                    "random": true,
                    "anim": { 
                        "enable": true, 
                        "speed": 0.5, 
                        "opacity_min": 0.05, 
                        "sync": false 
                    }
                },
                "size": { 
                    "value": 2, 
                    "random": true,
                    "anim": { 
                        "enable": true, 
                        "speed": 2, 
                        "size_min": 0.3, 
                        "sync": false 
                    }
                },
                "line_linked": { 
                    "enable": true, 
                    "distance": 150, 
                    "color": "#dc2626", 
                    "opacity": 0.08, 
                    "width": 1 
                },
                "move": { 
                    "enable": true, 
                    "speed": 1, 
                    "direction": "none", 
                    "random": true, 
                    "straight": false, 
                    "out_mode": "out", 
                    "bounce": false,
                    "attract": { 
                        "enable": true, 
                        "rotateX": 600, 
                        "rotateY": 1200 
                    }
                }
            },
            "interactivity": {
                "detect_on": "canvas",
                "events": {
                    "onhover": { 
                        "enable": true, 
                        "mode": "grab" 
                    },
                    "onclick": { 
                        "enable": true, 
                        "mode": "push" 
                    },
                    "resize": true
                },
                "modes": {
                    "grab": { 
                        "distance": 140, 
                        "line_linked": { "opacity": 0.2 } 
                    },
                    "push": { "particles_nb": 3 }
                }
            },
            "retina_detect": true
        });

        // Efeito de digitação nos placeholders
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            const originalPlaceholder = input.placeholder;
            
            input.addEventListener('focus', function() {
                this.placeholder = '';
                let i = 0;
                const typing = setInterval(() => {
                    if (i < originalPlaceholder.length) {
                        this.placeholder += originalPlaceholder.charAt(i);
                        i++;
                    } else {
                        clearInterval(typing);
                    }
                }, 50);
            });
            
            input.addEventListener('blur', function() {
                if (this.value === '') {
                    this.placeholder = originalPlaceholder;
                }
            });
        });
    </script>
</body>
</html>