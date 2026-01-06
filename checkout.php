<?php
/**
 * ============================================
 * SPLITSTORE - CHECKOUT COMPLETO V2.0
 * ============================================
 * Sistema integrado de Registro + Pagamento
 * Inspirado em Minecart.net e LojaSquare.net
 */

session_start();
require_once 'includes/db.php';

// Verifica se um plano foi selecionado
$plan = $_GET['plan'] ?? 'basic';

// Define os planos disponíveis
$planos = [
    'basic' => [
        'nome' => 'Starter',
        'slug' => 'basic',
        'preco' => 14.99,
        'preco_anual' => 149.99,
        'economia_anual' => '17%',
        'descricao' => 'Ideal para começar',
        'features' => [
            '1 Servidor Minecraft',
            'Checkout Responsivo',
            'Suporte via Ticket 24/7',
            'Plugin de Entrega Automática',
            'Estatísticas Básicas',
            'SSL Gratuito',
            '99.9% Uptime'
        ],
        'icon' => 'rocket',
        'color' => 'blue'
    ],
    'pro' => [
        'nome' => 'Enterprise',
        'slug' => 'pro',
        'preco' => 25.99,
        'preco_anual' => 259.99,
        'economia_anual' => '17%',
        'descricao' => 'Para redes profissionais',
        'features' => [
            'Até 5 Servidores',
            'Checkout Customizável',
            'Suporte Prioritário',
            'Analytics Avançado',
            'Sem Taxas por Transação',
            'API de Integração',
            'Backup Automático',
            'Relatórios Personalizados'
        ],
        'icon' => 'trending-up',
        'color' => 'purple',
        'destaque' => true
    ],
    'ultra' => [
        'nome' => 'Gerencial',
        'slug' => 'ultra',
        'preco' => 39.99,
        'preco_anual' => 399.99,
        'economia_anual' => '17%',
        'descricao' => 'Solução enterprise completa',
        'features' => [
            'Servidores Ilimitados',
            'Whitelabel Completo',
            'Gerente de Contas Dedicado',
            'Backup em Tempo Real',
            'Integrações Personalizadas',
            'SLA 99.95%',
            'Consultoria Técnica Mensal',
            'Suporte via WhatsApp',
            'Revisão de Código'
        ],
        'icon' => 'crown',
        'color' => 'red'
    ]
];

if (!isset($planos[$plan])) {
    header('Location: index.php#planos');
    exit;
}

$planData = $planos[$plan];

// Cores por plano
$colors = [
    'blue' => ['primary' => '#3b82f6', 'secondary' => '#1e40af'],
    'purple' => ['primary' => '#8b5cf6', 'secondary' => '#6d28d9'],
    'red' => ['primary' => '#ef4444', 'secondary' => '#dc2626']
];

$planColor = $colors[$planData['color']];

// Mensagens de erro
$errors = $_SESSION['checkout_errors'] ?? [];
$old_data = $_SESSION['checkout_data'] ?? [];
unset($_SESSION['checkout_errors'], $_SESSION['checkout_data']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?= htmlspecialchars($planData['nome']) ?> | SplitStore</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?= $planColor['primary'] ?>',
                        secondary: '<?= $planColor['secondary'] ?>'
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #000; 
            color: #fff;
            -webkit-font-smoothing: antialiased;
        }
        
        #particles-js {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: 1;
            top: 0;
            left: 0;
            pointer-events: none;
        }
        
        .content-wrapper {
            position: relative;
            z-index: 10;
        }
        
        .glass {
            background: rgba(15, 15, 15, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .glass-strong {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .input-field {
            background: rgba(20, 20, 20, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .input-field:focus {
            background: rgba(30, 30, 30, 0.8);
            border-color: <?= $planColor['primary'] ?>;
            box-shadow: 0 0 0 3px <?= $planColor['primary'] ?>20;
        }
        
        .step-indicator {
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: rgba(255, 255, 255, 0.1);
            z-index: -1;
        }
        
        .step-item.active .step-circle {
            background: <?= $planColor['primary'] ?>;
            border-color: <?= $planColor['primary'] ?>;
            color: white;
        }
        
        .step-item.completed .step-circle {
            background: rgba(34, 197, 94, 0.2);
            border-color: #22c55e;
            color: #22c55e;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .billing-option {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .billing-option:hover {
            transform: translateY(-2px);
        }
        
        .billing-option.selected {
            border-color: <?= $planColor['primary'] ?> !important;
            background: <?= $planColor['primary'] ?>10;
        }
        
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 8px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #22c55e; width: 100%; }
    </style>
</head>
<body>

    <div id="particles-js"></div>

    <div class="content-wrapper min-h-screen py-12 px-4">
        
        <!-- Header -->
        <div class="max-w-7xl mx-auto mb-12">
            <div class="flex items-center justify-between">
                <a href="index.php" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 bg-gradient-to-br from-red-600 to-red-900 rounded-xl flex items-center justify-center font-black shadow-lg">
                        S
                    </div>
                    <span class="text-xl font-black tracking-tighter uppercase hidden md:block">
                        Split<span class="text-red-600">Store</span>
                    </span>
                </a>
                
                <div class="glass px-4 py-2 rounded-full flex items-center gap-2">
                    <i data-lucide="shield-check" class="w-4 h-4 text-green-500"></i>
                    <span class="text-xs font-bold text-zinc-400">Checkout Seguro</span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-3 gap-8">
                
                <!-- Formulário Principal -->
                <div class="lg:col-span-2 fade-in-up">
                    
                    <!-- Erros -->
                    <?php if (!empty($errors)): ?>
                    <div class="glass-strong rounded-2xl p-6 mb-8 border-2 border-red-600/30 bg-red-600/5">
                        <div class="flex items-start gap-3">
                            <i data-lucide="alert-circle" class="w-6 h-6 text-red-500 flex-shrink-0"></i>
                            <div class="flex-1">
                                <h3 class="text-sm font-black text-red-500 mb-2 uppercase">Corrija os erros abaixo:</h3>
                                <ul class="space-y-1">
                                    <?php foreach ($errors as $error): ?>
                                        <li class="text-xs text-red-400">• <?= $error ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Progress Steps -->
                    <div class="glass-strong rounded-2xl p-6 mb-8">
                        <div class="step-indicator flex items-center justify-between relative">
                            <div class="step-item active flex flex-col items-center relative z-10">
                                <div class="step-circle w-10 h-10 rounded-full border-2 flex items-center justify-center font-black text-sm mb-2">
                                    1
                                </div>
                                <span class="text-xs font-bold text-zinc-400">Seus Dados</span>
                            </div>
                            <div class="step-item flex flex-col items-center relative z-10">
                                <div class="step-circle w-10 h-10 rounded-full border-2 border-zinc-800 bg-zinc-900 flex items-center justify-center font-black text-sm mb-2 text-zinc-600">
                                    2
                                </div>
                                <span class="text-xs font-bold text-zinc-600">Configuração</span>
                            </div>
                            <div class="step-item flex flex-col items-center relative z-10">
                                <div class="step-circle w-10 h-10 rounded-full border-2 border-zinc-800 bg-zinc-900 flex items-center justify-center font-black text-sm mb-2 text-zinc-600">
                                    3
                                </div>
                                <span class="text-xs font-bold text-zinc-600">Pagamento</span>
                            </div>
                        </div>
                    </div>

                    <!-- Formulário -->
                    <form id="checkoutForm" method="POST" action="process_checkout.php" class="space-y-6">
                        <input type="hidden" name="plan" value="<?= htmlspecialchars($plan) ?>">
                        <input type="hidden" name="billing_cycle" id="billing_cycle" value="monthly">
                        
                        <!-- Seção 1: Dados Pessoais -->
                        <div class="glass-strong rounded-2xl p-8">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                                    <i data-lucide="user" class="w-5 h-5 text-primary"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-black uppercase tracking-tight">Dados Pessoais</h2>
                                    <p class="text-xs text-zinc-500">Informações do titular da conta</p>
                                </div>
                            </div>

                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-bold text-zinc-400 ml-1 mb-2 block uppercase tracking-wider">
                                        Nome <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="first_name" required
                                           placeholder="João"
                                           value="<?= htmlspecialchars($old_data['first_name'] ?? '') ?>"
                                           class="input-field w-full px-4 py-3 rounded-xl text-sm outline-none">
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-zinc-400 ml-1 mb-2 block uppercase tracking-wider">
                                        Sobrenome <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="last_name" required
                                           placeholder="Silva"
                                           value="<?= htmlspecialchars($old_data['last_name'] ?? '') ?>"
                                           class="input-field w-full px-4 py-3 rounded-xl text-sm outline-none">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="text-xs font-bold text-zinc-400 ml-1 mb-2 block uppercase tracking-wider">
                                        E-mail <span class="text-red-500">*</span>
                                    </label>
                                    <input type="email" name="email" required
                                           placeholder="seu@email.com"
                                           value="<?= htmlspecialchars($old_data['email'] ?? '') ?>"
                                           class="input-field w-full px-4 py-3 rounded-xl text-sm outline-none">
                                    <p class="text-xs text-zinc-600 mt-1 ml-1">Será usado para login e notificações</p>
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-zinc-400 ml-1 mb-2 block uppercase tracking-wider">
                                        CPF <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="cpf" required
                                           placeholder="000.000.000-00"
                                           maxlength="14"
                                           value="<?= htmlspecialchars($old_data['cpf'] ?? '') ?>"
                                           class="input-field w-full px-4 py-3 rounded-xl text-sm outline-none"
                                           oninput="formatCPF(this)">
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-zinc-400 ml-1 mb-2 block uppercase tracking-wider">
                                        Telefone <span class="text-red-500">*</span>
                                    </label>
                                    <input type="tel" name="phone" required
                                           placeholder="(00) 00000-0000"
                                           maxlength="15"
                                           value="<?= htmlspecialchars($old_data['phone'] ?? '') ?>"
                                           class="input-field w-full px-4 py-3 rounded-xl text-sm outline-none"
                                           oninput="formatPhone(this)">
                                </div>
                            </div>
                        </div>

                        <!-- Seção 2: Configuração da Loja -->
                        <div class="glass-strong rounded-2xl p-8">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                                    <i data-lucide="store" class="w-5 h-5 text-primary"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-black uppercase tracking-tight">Sua Loja</h2>
                                    <p class="text-xs text-zinc-500">Configure os dados iniciais</p>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs font-bold text-zinc-400 ml-1 mb-2 block uppercase tracking-wider">
                                        Nome da Loja <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="store_name" required
                                           placeholder="Minha Loja VIP"
                                           maxlength="50"
                                           value="<?= htmlspecialchars($old_data['store_name'] ?? '') ?>"
                                           class="input-field w-full px-4 py-3 rounded-xl text-sm outline-none">
                                    <p class="text-xs text-zinc-600 mt-1 ml-1">Este será o nome exibido na sua loja</p>
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-zinc-400 ml-1 mb-2 block uppercase tracking-wider">
                                        URL da Loja <span class="text-red-500">*</span>
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm text-zinc-500 font-mono">splitstore.com.br/</span>
                                        <input type="text" name="store_slug" required
                                               placeholder="minhaloja"
                                               pattern="[a-z0-9-]+"
                                               maxlength="30"
                                               value="<?= htmlspecialchars($old_data['store_slug'] ?? '') ?>"
                                               class="input-field flex-1 px-4 py-3 rounded-xl text-sm outline-none lowercase"
                                               oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '')">
                                    </div>
                                    <p class="text-xs text-zinc-600 mt-1 ml-1">Apenas letras minúsculas, números e traços</p>
                                </div>
                            </div>
                        </div>

                        <!-- Seção 3: Senha -->
                        <div class="glass-strong rounded-2xl p-8">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                                    <i data-lucide="lock" class="w-5 h-5 text-primary"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-black uppercase tracking-tight">Segurança</h2>
                                    <p class="text-xs text-zinc-500">Crie uma senha forte</p>
                                </div>
                            </div>

                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-bold text-zinc-400 ml-1 mb-2 block uppercase tracking-wider">
                                        Senha <span class="text-red-500">*</span>
                                    </label>
                                    <input type="password" name="password" required
                                           minlength="8"
                                           placeholder="••••••••"
                                           id="password"
                                           class="input-field w-full px-4 py-3 rounded-xl text-sm outline-none"
                                           oninput="checkPasswordStrength()">
                                    <div class="password-strength" id="passwordStrength"></div>
                                    <p class="text-xs text-zinc-600 mt-2 ml-1">Mínimo 8 caracteres</p>
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-zinc-400 ml-1 mb-2 block uppercase tracking-wider">
                                        Confirmar Senha <span class="text-red-500">*</span>
                                    </label>
                                    <input type="password" name="password_confirm" required
                                           minlength="8"
                                           placeholder="••••••••"
                                           id="password_confirm"
                                           class="input-field w-full px-4 py-3 rounded-xl text-sm outline-none"
                                           oninput="checkPasswordMatch()">
                                    <p class="text-xs text-red-500 mt-1 ml-1 hidden" id="passwordMismatch">
                                        As senhas não coincidem
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Termos -->
                        <div class="glass-strong rounded-2xl p-6">
                            <label class="flex items-start gap-3 cursor-pointer group">
                                <input type="checkbox" name="terms" required
                                       class="w-5 h-5 rounded border-2 border-zinc-700 bg-zinc-900 checked:bg-primary checked:border-primary mt-0.5">
                                <span class="text-sm text-zinc-400 leading-relaxed">
                                    Concordo com os <a href="#" class="text-primary hover:underline font-bold">Termos de Serviço</a> e 
                                    <a href="#" class="text-primary hover:underline font-bold">Política de Privacidade</a> do SplitStore
                                </span>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" 
                                class="w-full bg-primary hover:brightness-110 text-white py-5 rounded-xl font-black uppercase text-sm tracking-wider transition-all hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-primary/30 flex items-center justify-center gap-3 group">
                            Continuar para Pagamento
                            <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                        </button>
                    </form>
                </div>

                <!-- Sidebar Resumo -->
                <div class="lg:sticky lg:top-24 h-fit fade-in-up" style="animation-delay: 0.2s;">
                    
                    <!-- Plano Selecionado -->
                    <div class="glass-strong rounded-2xl p-8 mb-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-sm font-black uppercase text-zinc-400 tracking-wider">Plano Selecionado</h3>
                            <a href="index.php#planos" class="text-xs text-primary hover:underline font-bold">Alterar</a>
                        </div>

                        <div class="mb-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                                    <i data-lucide="<?= $planData['icon'] ?>" class="w-6 h-6 text-primary"></i>
                                </div>
                                <div>
                                    <h4 class="text-lg font-black uppercase text-white"><?= $planData['nome'] ?></h4>
                                    <p class="text-xs text-zinc-500"><?= $planData['descricao'] ?></p>
                                </div>
                            </div>

                            <!-- Ciclo de Cobrança -->
                            <div class="space-y-3 mb-6">
                                <div class="billing-option selected glass rounded-xl p-4 border border-zinc-800" onclick="selectBilling('monthly')" id="billing_monthly">
                                    <label class="flex items-center justify-between cursor-pointer">
                                        <div class="flex items-center gap-3">
                                            <input type="radio" name="billing" value="monthly" checked class="w-4 h-4">
                                            <div>
                                                <p class="text-sm font-bold text-white">Mensal</p>
                                                <p class="text-xs text-zinc-500">Cobrado mensalmente</p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xl font-black text-primary">R$ <?= number_format($planData['preco'], 2, ',', '.') ?></p>
                                            <p class="text-xs text-zinc-500">/mês</p>
                                        </div>
                                    </label>
                                </div>

                                <div class="billing-option glass rounded-xl p-4 border border-zinc-800" onclick="selectBilling('annual')" id="billing_annual">
                                    <label class="flex items-center justify-between cursor-pointer relative overflow-hidden">
                                        <div class="absolute top-2 right-2 bg-green-600 text-white text-[9px] font-black uppercase px-2 py-1 rounded">
                                            Economize <?= $planData['economia_anual'] ?>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <input type="radio" name="billing" value="annual" class="w-4 h-4">
                                            <div>
                                                <p class="text-sm font-bold text-white">Anual</p>
                                                <p class="text-xs text-zinc-500">Cobrado anualmente</p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xl font-black text-green-500">R$ <?= number_format($planData['preco_anual'], 2, ',', '.') ?></p>
                                            <p class="text-xs text-zinc-500">/ano</p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Features -->
                            <div class="space-y-3">
                                <h5 class="text-xs font-black uppercase text-zinc-400 tracking-wider">Incluído:</h5>
                                <?php foreach ($planData['features'] as $feature): ?>
                                <div class="flex items-center gap-2">
                                    <i data-lucide="check" class="w-4 h-4 text-primary flex-shrink-0"></i>
                                    <span class="text-xs text-zinc-400"><?= $feature ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Resumo de Pagamento -->
                    <div class="glass-strong rounded-2xl p-6">
                        <h3 class="text-sm font-black uppercase text-zinc-400 tracking-wider mb-4">Resumo</h3>
                        
                        <div class="space-y-3 mb-6 pb-6 border-b border-zinc-800">
                            <div class="flex justify-between text-sm">
                                <span class="text-zinc-400">Plano <?= $planData['nome'] ?></span>
                                <span class="font-bold text-white" id="subtotal">R$ <?= number_format($planData['preco'], 2, ',', '.') ?></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-zinc-400">Taxa de Ativação</span>
                                <span class="font-bold text-green-500">Grátis</span>
                            </div>
                        </div>

                        <div class="flex justify-between items-end mb-6">
                            <span class="text-sm font-bold text-zinc-400 uppercase">Total</span>
                            <div class="text-right">
                                <span class="text-3xl font-black text-primary" id="total">R$ <?= number_format($planData['preco'], 2, ',', '.') ?></span>
                                <p class="text-xs text-zinc-500" id="billing_text">cobrado mensalmente</p>
                            </div>
                        </div>

                        <!-- Trust Badges -->
                        <div class="space-y-3 pt-6 border-t border-zinc-800">
                            <div class="flex items-center gap-3">
                                <i data-lucide="shield-check" class="w-5 h-5 text-green-500"></i>
                                <span class="text-xs text-zinc-400">Pagamento 100% Seguro</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i data-lucide="lock" class="w-5 h-5 text-blue-500"></i>
                                <span class="text-xs text-zinc-400">Dados Protegidos SSL</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i data-lucide="credit-card" class="w-5 h-5 text-purple-500"></i>
                                <span class="text-xs text-zinc-400">PIX, Cartão e Boleto</span>
                            </div>
                        </div>
                    </div>

                    <!-- Garantia -->
                    <div class="glass rounded-2xl p-6 mt-6 border border-green-600/20 bg-green-600/5">
                        <div class="flex items-start gap-3">
                            <i data-lucide="shield-check" class="w-6 h-6 text-green-500 flex-shrink-0"></i>
                            <div>
                                <h5 class="text-sm font-black text-green-500 mb-1">Garantia de 7 Dias</h5>
                                <p class="text-xs text-zinc-400 leading-relaxed">
                                    Se não estiver satisfeito, devolvemos 100% do seu dinheiro sem perguntas.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        lucide.createIcons();

        // Particles.js
        particlesJS("particles-js", {
            particles: {
                number: { value: 40, density: { enable: true, value_area: 800 } },
                color: { value: "<?= $planColor['primary'] ?>" },
                opacity: { value: 0.12, random: true },
                size: { value: 2, random: true },
                line_linked: { enable: true, distance: 150, color: "<?= $planColor['primary'] ?>", opacity: 0.08, width: 1 },
                move: { enable: true, speed: 0.6 }
            }
        });

        // ========================================
        // MÁSCARAS DE FORMATAÇÃO
        // ========================================
        
        function formatCPF(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }
            input.value = value;
        }
        
        function formatPhone(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
                value = value.replace(/(\d)(\d{4})$/, '$1-$2');
            }
            input.value = value;
        }

        // ========================================
        // FORÇA DA SENHA
        // ========================================
        
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthBar.className = 'password-strength';
                return;
            }
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            if (strength <= 2) {
                strengthBar.className = 'password-strength strength-weak';
            } else if (strength <= 3) {
                strengthBar.className = 'password-strength strength-medium';
            } else {
                strengthBar.className = 'password-strength strength-strong';
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirm').value;
            const mismatch = document.getElementById('passwordMismatch');
            
            if (confirm.length > 0 && password !== confirm) {
                mismatch.classList.remove('hidden');
            } else {
                mismatch.classList.add('hidden');
            }
        }

        // ========================================
        // SELEÇÃO DE CICLO DE COBRANÇA
        // ========================================
        
        const priceMonthly = <?= $planData['preco'] ?>;
        const priceAnnual = <?= $planData['preco_anual'] ?>;
        
        function selectBilling(cycle) {
            document.getElementById('billing_cycle').value = cycle;
            
            const monthlyOption = document.getElementById('billing_monthly');
            const annualOption = document.getElementById('billing_annual');
            
            if (cycle === 'monthly') {
                monthlyOption.classList.add('selected');
                annualOption.classList.remove('selected');
                monthlyOption.querySelector('input').checked = true;
                updateTotal(priceMonthly, 'mensalmente');
            } else {
                annualOption.classList.add('selected');
                monthlyOption.classList.remove('selected');
                annualOption.querySelector('input').checked = true;
                updateTotal(priceAnnual, 'anualmente');
            }
        }
        
        function updateTotal(price, text) {
            document.getElementById('subtotal').textContent = 'R$ ' + price.toFixed(2).replace('.', ',');
            document.getElementById('total').textContent = 'R$ ' + price.toFixed(2).replace('.', ',');
            document.getElementById('billing_text').textContent = 'cobrado ' + text;
        }

        // ========================================
        // VALIDAÇÃO DO FORMULÁRIO
        // ========================================
        
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirm').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('As senhas não coincidem!');
                document.getElementById('password_confirm').focus();
                return false;
            }
            
            // Mostra loading
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<i data-lucide="loader" class="w-5 h-5 animate-spin"></i> Processando...';
            lucide.createIcons();
        });
    </script>
</body>
</html>