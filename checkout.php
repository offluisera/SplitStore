<?php
/**
 * ============================================
 * SPLITSTORE - CHECKOUT 3 ETAPAS V4.0
 * ============================================
 * Seus Dados → Configuração → Pagamento
 */

session_start();
require_once 'includes/db.php';

// Verifica plano
$plan = $_GET['plan'] ?? 'basic';

$planos = [
    'basic' => [
        'nome' => 'Starter',
        'slug' => 'basic',
        'preco' => 14.99,
        'preco_anual' => 149.99,
        'economia_anual' => '17%',
        'features' => [
            '1 Servidor Minecraft',
            'Checkout Responsivo',
            'Suporte via Ticket 24/7',
            'Plugin de Entrega Automática',
            'Estatísticas Básicas'
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
        'features' => [
            'Até 5 Servidores',
            'Checkout Customizável',
            'Suporte Prioritário',
            'Analytics Avançado',
            'API de Integração'
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
        'features' => [
            'Servidores Ilimitados',
            'Whitelabel Completo',
            'Gerente de Contas',
            'Backup em Tempo Real',
            'Consultoria Mensal'
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #000; 
            color: #fff;
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
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }
        
        /* Step Progress */
        .step-indicator {
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: rgba(255, 255, 255, 0.1);
            z-index: -1;
        }
        
        .step-item {
            position: relative;
            z-index: 10;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .step-item.active .step-circle {
            background: #ef4444;
            border-color: #ef4444;
            color: white;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.5);
        }
        
        .step-item.completed .step-circle {
            background: rgba(34, 197, 94, 0.2);
            border-color: #22c55e;
            color: #22c55e;
        }
        
        /* Etapas */
        .checkout-step {
            display: none;
        }
        
        .checkout-step.active {
            display: block;
            animation: fadeInUp 0.4s ease-out;
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
        
        /* Payment Methods */
        .payment-method {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-method:hover {
            transform: translateY(-2px);
        }
        
        .payment-method.selected {
            border-color: #ef4444 !important;
            background: rgba(239, 68, 68, 0.1);
        }
        
        .billing-option {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .billing-option.selected {
            border-color: #ef4444 !important;
            background: rgba(239, 68, 68, 0.1);
        }
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
                <div class="lg:col-span-2">
                    
                    <!-- Erros -->
                    <?php if (!empty($errors)): ?>
                    <div class="glass-strong rounded-2xl p-6 mb-8 border-2 border-red-600/30 bg-red-600/5">
                        <div class="flex items-start gap-3">
                            <i data-lucide="alert-circle" class="w-6 h-6 text-red-500 flex-shrink-0"></i>
                            <div class="flex-1">
                                <h3 class="text-sm font-black text-red-500 mb-2 uppercase">Corrija os erros:</h3>
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
                            <div class="step-item active flex flex-col items-center relative z-10" id="stepIndicator1">
                                <div class="step-circle flex items-center justify-center font-black text-sm mb-2">
                                    1
                                </div>
                                <span class="text-xs font-bold text-zinc-400">Seus Dados</span>
                            </div>
                            <div class="step-item flex flex-col items-center relative z-10" id="stepIndicator2">
                                <div class="step-circle flex items-center justify-center font-black text-sm mb-2 text-zinc-600">
                                    2
                                </div>
                                <span class="text-xs font-bold text-zinc-600">Configuração</span>
                            </div>
                            <div class="step-item flex flex-col items-center relative z-10" id="stepIndicator3">
                                <div class="step-circle flex items-center justify-center font-black text-sm mb-2 text-zinc-600">
                                    3
                                </div>
                                <span class="text-xs font-bold text-zinc-600">Pagamento</span>
                            </div>
                        </div>
                    </div>

                    <!-- Formulário Multi-Step -->
                    <form id="checkoutForm" class="space-y-6">
                        <input type="hidden" name="plan" value="<?= htmlspecialchars($plan) ?>">
                        <input type="hidden" name="billing_cycle" id="billing_cycle" value="monthly">
                        <input type="hidden" name="payment_method" id="payment_method_input" value="pix">
                        
                        <!-- ============================================ -->
                        <!-- ETAPA 1: SEUS DADOS -->
                        <!-- ============================================ -->
                        <div class="checkout-step active" id="step1">
                            <div class="glass-strong rounded-2xl p-8">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 bg-red-600/10 rounded-xl flex items-center justify-center">
                                        <i data-lucide="user" class="w-5 h-5 text-red-600"></i>
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
                                        <p class="text-xs text-zinc-600 mt-1 ml-1">Será usado para login</p>
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
                                    
                                    <div class="md:col-span-2">
                                        <label class="text-xs font-bold text-zinc-400 ml-1 mb-2 block uppercase tracking-wider">
                                            Senha <span class="text-red-500">*</span>
                                        </label>
                                        <input type="password" name="password" required
                                               minlength="8"
                                               placeholder="••••••••"
                                               id="password"
                                               class="input-field w-full px-4 py-3 rounded-xl text-sm outline-none">
                                        <p class="text-xs text-zinc-600 mt-1 ml-1">Mínimo 8 caracteres</p>
                                    </div>
                                </div>
                                
                                <button type="button" onclick="nextStep(2)" 
                                        class="w-full bg-red-600 hover:bg-red-700 text-white py-4 rounded-xl font-black uppercase text-sm tracking-wider transition-all mt-6 flex items-center justify-center gap-3 group">
                                    Continuar
                                    <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                                </button>
                            </div>
                        </div>

                        <!-- ============================================ -->
                        <!-- ETAPA 2: CONFIGURAÇÃO -->
                        <!-- ============================================ -->
                        <div class="checkout-step" id="step2">
                            
                            <!-- Configuração da Loja -->
                            <div class="glass-strong rounded-2xl p-8 mb-6">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 bg-purple-600/10 rounded-xl flex items-center justify-center">
                                        <i data-lucide="store" class="w-5 h-5 text-purple-600"></i>
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
                                    </div>

                                    <div>
                                        <label class="text-xs font-bold text-zinc-400 ml-1 mb-2 block uppercase tracking-wider">
                                            URL da Loja <span class="text-red-500">*</span>
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm text-zinc-500 font-mono">splitstore.com.br/stores/</span>
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
                            
                            <!-- Cupom de Desconto -->
                            <div class="glass-strong rounded-2xl p-8 mb-6">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 bg-green-600/10 rounded-xl flex items-center justify-center">
                                        <i data-lucide="ticket" class="w-5 h-5 text-green-600"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-black uppercase tracking-tight">Cupom de Desconto</h2>
                                        <p class="text-xs text-zinc-500">Opcional</p>
                                    </div>
                                </div>

                                <div class="flex gap-3">
                                    <input type="text" 
                                           id="couponInput"
                                           placeholder="Digite o cupom"
                                           class="input-field flex-1 px-4 py-3 rounded-xl text-sm outline-none uppercase">
                                    <button type="button" 
                                            onclick="applyCoupon()"
                                            class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl font-bold text-sm transition-all">
                                        Aplicar
                                    </button>
                                </div>
                                
                                <div id="couponResult" class="hidden mt-4 p-4 rounded-xl"></div>
                            </div>
                            
                            <!-- Método de Pagamento -->
                            <div class="glass-strong rounded-2xl p-8 mb-6">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 bg-blue-600/10 rounded-xl flex items-center justify-center">
                                        <i data-lucide="credit-card" class="w-5 h-5 text-blue-600"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-black uppercase tracking-tight">Forma de Pagamento</h2>
                                        <p class="text-xs text-zinc-500">Escolha como deseja pagar</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <div class="payment-method selected glass rounded-xl p-4 border border-white/10 text-center" 
                                         onclick="selectPaymentMethod('pix', this)">
                                        <i data-lucide="smartphone" class="w-8 h-8 text-green-500 mx-auto mb-2"></i>
                                        <span class="text-sm font-bold block">PIX</span>
                                        <span class="text-xs text-zinc-500">Instantâneo</span>
                                    </div>
                                    
                                    <div class="payment-method glass rounded-xl p-4 border border-white/10 text-center" 
                                         onclick="selectPaymentMethod('credit_card', this)">
                                        <i data-lucide="credit-card" class="w-8 h-8 text-blue-500 mx-auto mb-2"></i>
                                        <span class="text-sm font-bold block">Cartão</span>
                                        <span class="text-xs text-zinc-500">Até 12x</span>
                                    </div>
                                    
                                    <div class="payment-method glass rounded-xl p-4 border border-white/10 text-center" 
                                         onclick="selectPaymentMethod('boleto', this)">
                                        <i data-lucide="barcode" class="w-8 h-8 text-yellow-500 mx-auto mb-2"></i>
                                        <span class="text-sm font-bold block">Boleto</span>
                                        <span class="text-xs text-zinc-500">3 dias úteis</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex gap-4">
                                <button type="button" onclick="prevStep(1)" 
                                        class="flex-1 bg-zinc-900 hover:bg-zinc-800 text-white py-4 rounded-xl font-bold text-sm transition-all">
                                    Voltar
                                </button>
                                <button type="button" onclick="nextStep(3)" 
                                        class="flex-1 bg-red-600 hover:bg-red-700 text-white py-4 rounded-xl font-black uppercase text-sm transition-all">
                                    Continuar
                                </button>
                            </div>
                        </div>

                        <!-- ============================================ -->
                        <!-- ETAPA 3: PAGAMENTO -->
                        <!-- ============================================ -->
                        <div class="checkout-step" id="step3">
                            <div class="glass-strong rounded-2xl p-8">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 bg-green-600/10 rounded-xl flex items-center justify-center">
                                        <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-black uppercase tracking-tight">Confirme seus Dados</h2>
                                        <p class="text-xs text-zinc-500">Revise antes de finalizar</p>
                                    </div>
                                </div>

                                <!-- Resumo -->
                                <div class="space-y-4 mb-6">
                                    <div class="glass rounded-xl p-4">
                                        <p class="text-xs text-zinc-500 mb-1">Nome</p>
                                        <p class="text-sm font-bold" id="summaryName">-</p>
                                    </div>
                                    <div class="glass rounded-xl p-4">
                                        <p class="text-xs text-zinc-500 mb-1">E-mail</p>
                                        <p class="text-sm font-bold" id="summaryEmail">-</p>
                                    </div>
                                    <div class="glass rounded-xl p-4">
                                        <p class="text-xs text-zinc-500 mb-1">Loja</p>
                                        <p class="text-sm font-bold" id="summaryStore">-</p>
                                    </div>
                                    <div class="glass rounded-xl p-4">
                                        <p class="text-xs text-zinc-500 mb-1">Pagamento</p>
                                        <p class="text-sm font-bold" id="summaryPayment">PIX</p>
                                    </div>
                                </div>

                                <!-- Termos -->
                                <div class="glass rounded-xl p-4 mb-6">
                                    <label class="flex items-start gap-3 cursor-pointer group">
                                        <input type="checkbox" name="terms" required
                                               class="w-5 h-5 rounded border-2 border-zinc-700 bg-zinc-900 checked:bg-red-600 checked:border-red-600 mt-0.5">
                                        <span class="text-sm text-zinc-400 leading-relaxed">
                                            Concordo com os <a href="#" class="text-red-600 hover:underline font-bold">Termos de Serviço</a> e 
                                            <a href="#" class="text-red-600 hover:underline font-bold">Política de Privacidade</a>
                                        </span>
                                    </label>
                                </div>
                                
                                <div class="flex gap-4">
                                    <button type="button" onclick="prevStep(2)" 
                                            class="flex-1 bg-zinc-900 hover:bg-zinc-800 text-white py-4 rounded-xl font-bold text-sm transition-all">
                                        Voltar
                                    </button>
                                    <button type="submit" 
                                            class="flex-1 bg-red-600 hover:bg-red-700 text-white py-4 rounded-xl font-black uppercase text-sm transition-all">
                                        Finalizar Pedido
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Sidebar Resumo -->
                <div class="lg:sticky lg:top-24 h-fit">
                    <div class="glass-strong rounded-2xl p-8 mb-6">
                        <h3 class="text-sm font-black uppercase text-zinc-400 tracking-wider mb-4">Plano Selecionado</h3>

                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-12 h-12 bg-red-600/10 rounded-xl flex items-center justify-center">
                                <i data-lucide="<?= $planData['icon'] ?>" class="w-6 h-6 text-red-600"></i>
                            </div>
                            <div>
                                <h4 class="text-lg font-black uppercase text-white"><?= $planData['nome'] ?></h4>
                                <p class="text-xs text-zinc-500"><?= $planData['descricao'] ?? '' ?></p>
                            </div>
                        </div>

                       <div class="space-y-3 mb-6">
                        <div class="billing-option selected glass rounded-xl p-4 border border-white/10" 
                             onclick="selectBilling('monthly')" 
                             id="billing_monthly">
                            <label class="flex items-center justify-between cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="billing" value="monthly" checked class="w-4 h-4 accent-red-600">
                                    <div>
                                        <p class="text-sm font-bold text-white">Mensal</p>
                                        <p class="text-xs text-zinc-500">Cobrado mensalmente</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xl font-black text-red-600">R$ <?= number_format($planData['preco'], 2, ',', '.') ?></p>
                                    <p class="text-xs text-zinc-500">/mês</p>
                                </div>
                            </label>
                        </div>

                        <div class="billing-option glass rounded-xl p-4 border border-white/10" 
                             onclick="selectBilling('annual')" 
                             id="billing_annual">
                            <label class="flex items-center justify-between cursor-pointer relative overflow-hidden">
                                <div class="absolute top-2 right-2 bg-green-600 text-white text-[9px] font-black uppercase px-2 py-1 rounded">
                                    Economize <?= $planData['economia_anual'] ?>
                                </div>
                                <div class="flex items-center gap-3">
                                    <input type="radio" name="billing" value="annual" class="w-4 h-4 accent-red-600">
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
                            <i data-lucide="check" class="w-4 h-4 text-red-600 flex-shrink-0"></i>
                            <span class="text-xs text-zinc-400"><?= $feature ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Resumo de Pagamento -->
                <div class="glass-strong rounded-2xl p-6 mt-6">
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

                    <div class="flex justify-between items-end">
                        <span class="text-sm font-bold text-zinc-400 uppercase">Total</span>
                        <div class="text-right">
                            <span class="text-3xl font-black text-red-600" id="total">R$ <?= number_format($planData['preco'], 2, ',', '.') ?></span>
                            <p class="text-xs text-zinc-500" id="billing_text">cobrado mensalmente</p>
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
                color: { value: "#ef4444" },
                opacity: { value: 0.12, random: true },
                size: { value: 2, random: true },
                line_linked: { enable: true, distance: 150, color: "#ef4444", opacity: 0.08, width: 1 },
                move: { enable: true, speed: 0.6 }
            }
        });

        // ========================================
        // NAVEGAÇÃO ENTRE ETAPAS
        // ========================================
        
        let currentStep = 1;
        
        function nextStep(step) {
            // Validação antes de avançar
            if (step === 2 && !validateStep1()) return;
            if (step === 3 && !validateStep2()) return;
            
            // Esconde etapa atual
            document.getElementById('step' + currentStep).classList.remove('active');
            document.getElementById('stepIndicator' + currentStep).classList.remove('active');
            document.getElementById('stepIndicator' + currentStep).classList.add('completed');
            
            // Mostra próxima etapa
            currentStep = step;
            document.getElementById('step' + currentStep).classList.add('active');
            document.getElementById('stepIndicator' + currentStep).classList.add('active');
            
            // Atualiza cores dos indicadores
            const stepTexts = document.querySelectorAll('#stepIndicator' + currentStep + ' span');
            stepTexts.forEach(el => {
                el.classList.remove('text-zinc-600');
                el.classList.add('text-white');
            });
            
            // Se for etapa 3, preenche resumo
            if (step === 3) {
                fillSummary();
            }
            
            // Scroll suave para o topo
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function prevStep(step) {
            // Esconde etapa atual
            document.getElementById('step' + currentStep).classList.remove('active');
            document.getElementById('stepIndicator' + currentStep).classList.remove('active');
            
            // Remove "completed" da etapa anterior
            document.getElementById('stepIndicator' + step).classList.remove('completed');
            
            // Mostra etapa anterior
            currentStep = step;
            document.getElementById('step' + currentStep).classList.add('active');
            document.getElementById('stepIndicator' + currentStep).classList.add('active');
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // ========================================
        // VALIDAÇÕES
        // ========================================
        
        function validateStep1() {
            const firstName = document.querySelector('input[name="first_name"]').value.trim();
            const lastName = document.querySelector('input[name="last_name"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();
            const cpf = document.querySelector('input[name="cpf"]').value.replace(/\D/g, '');
            const phone = document.querySelector('input[name="phone"]').value.replace(/\D/g, '');
            const password = document.querySelector('input[name="password"]').value;
            
            if (!firstName || !lastName) {
                showNotification('Preencha seu nome completo', 'error');
                return false;
            }
            
            if (!email || !validateEmail(email)) {
                showNotification('Digite um e-mail valido', 'error');
                document.querySelector('input[name="email"]').focus();
                return false;
            }
            
            if (cpf.length !== 11) {
                showNotification('CPF invalido. Digite os 11 digitos.', 'error');
                document.querySelector('input[name="cpf"]').focus();
                return false;
            }
            
            if (phone.length < 10) {
                showNotification('Telefone invalido. Digite com DDD.', 'error');
                document.querySelector('input[name="phone"]').focus();
                return false;
            }
            
            if (password.length < 8) {
                showNotification('A senha deve ter no minimo 8 caracteres', 'error');
                document.querySelector('input[name="password"]').focus();
                return false;
            }
            
            return true;
        }
        
        function validateStep2() {
            const storeName = document.querySelector('input[name="store_name"]').value.trim();
            const storeSlug = document.querySelector('input[name="store_slug"]').value.trim();
            
            if (!storeName) {
                showNotification('Digite o nome da sua loja', 'error');
                document.querySelector('input[name="store_name"]').focus();
                return false;
            }
            
            if (!storeSlug || storeSlug.length < 3) {
                showNotification('A URL da loja deve ter no minimo 3 caracteres', 'error');
                document.querySelector('input[name="store_slug"]').focus();
                return false;
            }
            
            if (!/^[a-z0-9-]+$/.test(storeSlug)) {
                showNotification('A URL da loja so pode conter letras minusculas, numeros e tracos', 'error');
                document.querySelector('input[name="store_slug"]').focus();
                return false;
            }
            
            return true;
        }
        
        function validateEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

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
        // CUPOM DE DESCONTO
        // ========================================
        
        function applyCoupon() {
            const couponInput = document.getElementById('couponInput');
            const couponCode = couponInput.value.trim().toUpperCase();
            const resultDiv = document.getElementById('couponResult');
            
            if (!couponCode) {
                alert('Digite um código de cupom');
                return;
            }
            
            // Simula validação de cupom (integrar com backend depois)
            const validCoupons = {
                'BEMVINDO10': { discount: 10, type: 'percent' },
                'PRIMEIRACOMPRA': { discount: 15, type: 'percent' },
                'DESCONTO20': { discount: 20, type: 'fixed' }
            };
            
            if (validCoupons[couponCode]) {
                const coupon = validCoupons[couponCode];
                const currentPrice = parseFloat(document.getElementById('billing_cycle').value === 'monthly' ? priceMonthly : priceAnnual);
                
                let discount = 0;
                if (coupon.type === 'percent') {
                    discount = currentPrice * (coupon.discount / 100);
                } else {
                    discount = coupon.discount;
                }
                
                const newPrice = currentPrice - discount;
                
                resultDiv.innerHTML = `
                    <div class="flex items-center justify-between bg-green-600/10 border border-green-600/30 p-3 rounded-xl">
                        <div class="flex items-center gap-2">
                            <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
                            <span class="text-xs font-bold text-green-500">Cupom aplicado: ${couponCode}</span>
                        </div>
                        <span class="text-xs font-bold text-green-500">-R$ ${discount.toFixed(2).replace('.', ',')}</span>
                    </div>
                `;
                resultDiv.classList.remove('hidden');
                
                // Atualiza total
                updateTotal(newPrice, document.getElementById('billing_cycle').value === 'monthly' ? 'mensalmente' : 'anualmente');
                
                lucide.createIcons();
            } else {
                resultDiv.innerHTML = `
                    <div class="flex items-center gap-2 bg-red-600/10 border border-red-600/30 p-3 rounded-xl">
                        <i data-lucide="x-circle" class="w-4 h-4 text-red-500"></i>
                        <span class="text-xs font-bold text-red-500">Cupom inválido ou expirado</span>
                    </div>
                `;
                resultDiv.classList.remove('hidden');
                lucide.createIcons();
            }
        }

        // ========================================
        // SELEÇÃO DE MÉTODO DE PAGAMENTO
        // ========================================
        
        function selectPaymentMethod(method, element) {
            // Remove seleção de todos
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Adiciona seleção ao clicado
            element.classList.add('selected');
            
            // Atualiza campo hidden
            document.getElementById('payment_method_input').value = method;
        }

        // ========================================
        // PREENCHER RESUMO (ETAPA 3)
        // ========================================
        
        function fillSummary() {
            const firstName = document.querySelector('input[name="first_name"]').value;
            const lastName = document.querySelector('input[name="last_name"]').value;
            const email = document.querySelector('input[name="email"]').value;
            const storeName = document.querySelector('input[name="store_name"]').value;
            const paymentMethod = document.getElementById('payment_method_input').value;
            
            document.getElementById('summaryName').textContent = `${firstName} ${lastName}`;
            document.getElementById('summaryEmail').textContent = email;
            document.getElementById('summaryStore').textContent = storeName;
            
            const paymentLabels = {
                'pix': 'PIX (Instantâneo)',
                'credit_card': 'Cartão de Crédito',
                'boleto': 'Boleto Bancário'
            };
            document.getElementById('summaryPayment').textContent = paymentLabels[paymentMethod] || 'PIX';
        }

        // ========================================
        // SUBMIT DO FORMULARIO
        // ========================================
        
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const termsCheckbox = document.querySelector('input[name="terms"]');
            
            if (!termsCheckbox.checked) {
                e.preventDefault();
                
                // Notificacao premium
                showNotification('Voce precisa aceitar os termos de servico', 'error');
                
                // Scroll ate o checkbox
                termsCheckbox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                termsCheckbox.parentElement.classList.add('animate-pulse');
                
                setTimeout(() => {
                    termsCheckbox.parentElement.classList.remove('animate-pulse');
                }, 2000);
                
                return false;
            }
            
            // Mostra loading
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<i data-lucide="loader" class="w-5 h-5 animate-spin inline mr-2"></i> Processando...';
            lucide.createIcons();
        });
        
        // ========================================
        // SISTEMA DE NOTIFICACOES PREMIUM
        // ========================================
        
        function showNotification(message, type = 'info') {
            // Remove notificacoes existentes
            const existing = document.querySelectorAll('.custom-notification');
            existing.forEach(n => n.remove());
            
            const notification = document.createElement('div');
            notification.className = 'custom-notification fixed top-6 right-6 z-[9999] max-w-md';
            
            const colors = {
                'error': 'bg-red-600/10 border-red-600/30 text-red-500',
                'success': 'bg-green-600/10 border-green-600/30 text-green-500',
                'warning': 'bg-yellow-600/10 border-yellow-600/30 text-yellow-500',
                'info': 'bg-blue-600/10 border-blue-600/30 text-blue-500'
            };
            
            const icons = {
                'error': 'alert-circle',
                'success': 'check-circle',
                'warning': 'alert-triangle',
                'info': 'info'
            };
            
            notification.innerHTML = `
                <div class="glass-strong rounded-2xl p-5 border-2 ${colors[type]} shadow-2xl backdrop-blur-xl animate-slideInRight">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 ${colors[type].split(' ')[0]} rounded-xl flex items-center justify-center flex-shrink-0">
                            <i data-lucide="${icons[type]}" class="w-5 h-5"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold leading-relaxed">${message}</p>
                        </div>
                        <button onclick="this.closest('.custom-notification').remove()" 
                                class="text-zinc-500 hover:text-white transition flex-shrink-0">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            lucide.createIcons();
            
            // Remove automaticamente apos 5 segundos
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }
        
        // Adiciona CSS para animacao
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100%);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            .animate-slideInRight {
                animation: slideInRight 0.3s ease-out;
            }
            .custom-notification {
                transition: all 0.3s ease;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
