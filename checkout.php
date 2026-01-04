<?php
require_once 'includes/db.php';
require_once 'includes/gateway.php';

$plan = $_GET['plan'] ?? 'basic';
$plans = [
    'basic' => ['nome' => 'Starter', 'preco' => 14.99, 'features' => ['1 Servidor', 'Checkout Padrão', 'Suporte via Ticket', 'Plugin de Entrega']],
    'pro' => ['nome' => 'Enterprise', 'preco' => 25.99, 'features' => ['5 Servidores', 'Checkout Customizável', 'Suporte Prioritário', 'Estatísticas Avançadas', 'Sem Taxas Extras']],
    'ultra' => ['nome' => 'Gerencial', 'preco' => 39.99, 'features' => ['Servidores Ilimitados', 'API de Integração', 'Gerente de Contas', 'Whitelabel Total', 'Backup em Tempo Real']]
];

if (!isset($plans[$plan])) {
    header('Location: index.php#planos');
    exit;
}

$planData = $plans[$plan];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['owner_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $store_name = $_POST['store_name'] ?? '';
    
    if (!empty($nome) && !empty($email) && !empty($store_name) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            // 1. Cria a loja no banco com status pending
            $client_secret = 'cs_' . bin2hex(random_bytes(16));
            $api_key = 'ak_' . bin2hex(random_bytes(16));
            $store_slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $store_name));
            
            $stmt = $pdo->prepare("
                INSERT INTO stores (owner_name, store_name, email, plan_type, status, store_slug, client_secret, api_key, created_at) 
                VALUES (?, ?, ?, ?, 'inactive', ?, ?, ?, NOW())
            ");
            $stmt->execute([$nome, $store_name, $email, $plan, $store_slug, $client_secret, $api_key]);
            $store_id = $pdo->lastInsertId();
            
            // 2. Gera o pagamento PIX via MisticPay
            $payment = MisticPay::createPayment(
                $planData['preco'],
                "PLAN-{$store_id}-" . time(),
                [
                    'store_id' => $store_id,
                    'plan' => $plan,
                    'customer_name' => $nome,
                    'customer_email' => $email
                ]
            );
            
            if ($payment && isset($payment['qr_code'])) {
                // 3. Registra a assinatura como pendente
                $stmt = $pdo->prepare("
                    INSERT INTO subscriptions (store_id, plan_type, amount, payment_method, status, gateway_transaction_id) 
                    VALUES (?, ?, ?, 'pix', 'pending', ?)
                ");
                $stmt->execute([$store_id, $plan, $planData['preco'], $payment['id']]);
                
                // Redireciona para página de pagamento
                $_SESSION['payment_data'] = $payment;
                $_SESSION['store_data'] = [
                    'id' => $store_id,
                    'name' => $store_name,
                    'plan' => $planData['nome']
                ];
                header('Location: payment.php');
                exit;
            } else {
                $error = "Erro ao gerar pagamento. Tente novamente.";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Este e-mail já está cadastrado.";
            } else {
                $error = "Erro ao processar. Tente novamente.";
            }
        }
    } else {
        $error = "Preencha todos os campos corretamente.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | SplitStore</title>
    
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
            top: 0;
            left: 0;
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
        .glow-input:focus {
            border-color: rgba(220, 38, 38, 0.5);
            box-shadow: 0 0 20px -5px rgba(220, 38, 38, 0.3);
        }
    </style>
</head>
<body class="antialiased min-h-screen">

    <div id="particles-js"></div>

    <div class="content-wrapper">
        <nav class="max-w-[1200px] mx-auto px-6 py-8">
            <a href="index.php" class="inline-flex items-center gap-2 text-zinc-500 hover:text-white transition text-sm font-bold uppercase">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Voltar
            </a>
        </nav>

        <main class="max-w-[1100px] mx-auto px-6 py-12">
            <div class="grid lg:grid-cols-2 gap-12 items-start">
                
                <!-- Formulário -->
                <div class="glass p-10 rounded-[3rem] border-red-600/10">
                    <div class="mb-10">
                        <h1 class="text-3xl font-black uppercase italic tracking-tighter mb-2">
                            Finalize sua <span class="text-red-600">Compra</span>
                        </h1>
                        <p class="text-zinc-500 text-sm font-medium">Preencha seus dados para ativar sua loja</p>
                    </div>

                    <?php if($error): ?>
                        <div class="bg-red-900/20 border border-red-600/30 text-red-500 p-4 rounded-2xl mb-6 text-sm font-bold">
                            <?= $error ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-5">
                        <div>
                            <label class="text-[10px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Nome Completo</label>
                            <input type="text" name="owner_name" required 
                                   placeholder="João Silva"
                                   class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none glow-input transition">
                        </div>

                        <div>
                            <label class="text-[10px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">E-mail</label>
                            <input type="email" name="email" required 
                                   placeholder="seu@email.com"
                                   class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none glow-input transition">
                        </div>

                        <div>
                            <label class="text-[10px] font-black uppercase text-zinc-600 ml-2 tracking-widest block mb-2">Nome da Loja</label>
                            <input type="text" name="store_name" required 
                                   placeholder="Minha Loja VIP"
                                   class="w-full bg-white/5 border border-white/10 p-4 rounded-xl text-sm outline-none glow-input transition">
                            <p class="text-[10px] text-zinc-600 mt-2 ml-2 font-medium">Este será o nome exibido na sua loja</p>
                        </div>

                        <button type="submit" 
                                class="w-full bg-red-600 hover:bg-red-700 text-white py-5 rounded-2xl font-black uppercase text-xs tracking-widest transition-all hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-red-600/20 mt-8">
                            Gerar Pagamento PIX
                        </button>

                        <p class="text-center text-zinc-600 text-[10px] font-medium mt-4">
                            Ao continuar, você concorda com nossos <a href="#" class="text-red-600 hover:underline">Termos de Uso</a>
                        </p>
                    </form>
                </div>

                <!-- Resumo do Pedido -->
                <div class="glass p-10 rounded-[3rem] sticky top-8">
                    <h2 class="text-lg font-black uppercase italic mb-8">Resumo do Pedido</h2>
                    
                    <div class="space-y-6 mb-8">
                        <div class="flex justify-between items-start pb-6 border-b border-white/5">
                            <div>
                                <h3 class="font-black uppercase text-sm mb-1"><?= $planData['nome'] ?></h3>
                                <p class="text-[10px] text-zinc-600 font-bold uppercase">Plano Mensal</p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-black italic">R$ <?= number_format($planData['preco'], 2, ',', '.') ?></p>
                                <p class="text-[10px] text-zinc-600">/mês</p>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-[10px] font-black uppercase text-zinc-600 mb-4 tracking-widest">Incluído no Plano:</h4>
                            <ul class="space-y-3">
                                <?php foreach($planData['features'] as $feature): ?>
                                    <li class="flex items-center gap-3 text-sm text-zinc-400">
                                        <i data-lucide="check-circle" class="w-4 h-4 text-red-600 flex-shrink-0"></i>
                                        <span><?= $feature ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="border-t border-white/5 pt-6 space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-500 font-medium">Subtotal</span>
                            <span class="font-bold">R$ <?= number_format($planData['preco'], 2, ',', '.') ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-zinc-500 font-medium">Taxa de Ativação</span>
                            <span class="font-bold text-green-500">Grátis</span>
                        </div>
                        <div class="flex justify-between pt-3 border-t border-white/5">
                            <span class="text-lg font-black uppercase">Total</span>
                            <span class="text-2xl font-black italic text-red-600">R$ <?= number_format($planData['preco'], 2, ',', '.') ?></span>
                        </div>
                    </div>

                    <div class="mt-8 p-4 bg-white/5 rounded-2xl border border-white/5">
                        <div class="flex items-start gap-3">
                            <i data-lucide="shield-check" class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5"></i>
                            <div>
                                <h5 class="text-[11px] font-black uppercase mb-1">Pagamento Seguro</h5>
                                <p class="text-[10px] text-zinc-500 leading-relaxed">Processado via MisticPay com criptografia SSL</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        lucide.createIcons();
        particlesJS("particles-js", {
            "particles": {
                "number": { "value": 30, "density": { "enable": true, "value_area": 800 } },
                "color": { "value": "#ff0000" },
                "shape": { "type": "circle" },
                "opacity": { "value": 0.15, "random": true },
                "size": { "value": 2, "random": true },
                "line_linked": { "enable": true, "distance": 150, "color": "#ff0000", "opacity": 0.08, "width": 1 },
                "move": { "enable": true, "speed": 0.6, "direction": "none", "random": true, "straight": false }
            },
            "retina_detect": true
        });
    </script>
</body>
</html>