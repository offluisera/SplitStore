<?php
ini_set('display_errors', 0);
if (file_exists('includes/db.php')) {
    require_once 'includes/db.php';
}

// Lógica de Cache para Parceiros e Feedbacks
$partners = [];
$feedbacks = [];

if (isset($pdo)) {
    try {
        // Tenta buscar do Redis primeiro
        if (isset($redis) && $redis->exists('site_public_data')) {
            $cachedData = json_decode($redis->get('site_public_data'), true);
            $partners = $cachedData['partners'] ?? [];
            $feedbacks = $cachedData['feedbacks'] ?? [];
        } else {
            // Busca Parceiros Ativos
            $partners = $pdo->query("SELECT nome, logo_url as logo FROM parceiros ORDER BY ordem ASC")->fetchAll(PDO::FETCH_ASSOC);
            
            // Busca Feedbacks Ativos
            $feedbacks = $pdo->query("SELECT nome, cargo, texto, estrelas FROM feedbacks ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
            
            // Salva no Redis por 10 minutos se disponível
            if (isset($redis)) {
                $redis->setex('site_public_data', 600, json_encode(['partners' => $partners, 'feedbacks' => $feedbacks]));
            }
        }
    } catch (Exception $e) {
        // Silencioso em produção, mas garante que os arrays existam
        $partners = [];
        $feedbacks = [];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SplitStore | Premium Systems</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        html { scroll-behavior: smooth; scroll-padding-top: 80px; }
        body { font-family: 'Inter', sans-serif; background-color: #000; color: white; overflow-x: hidden; margin: 0; }
        #particles-js { position: fixed; width: 100%; height: 100%; z-index: 1; top: 0; left: 0; }
        .content-wrapper { position: relative; z-index: 10; }
        .text-hero { font-size: clamp(2.2rem, 6vw, 4rem); line-height: 0.95; letter-spacing: -0.04em; }
        .glow-red { box-shadow: 0 0 35px -10px rgba(220, 38, 38, 0.5); }
        .glass-card { background: rgba(10, 10, 10, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        
        /* FAQ Accordion */
        .faq-content { max-height: 0; overflow: hidden; transition: all 0.3s ease-in-out; opacity: 0; }
        .faq-item.active .faq-content { max-height: 200px; opacity: 1; padding-top: 1rem; }
        .faq-item.active .faq-icon { transform: rotate(180deg); color: #ef4444; }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #000; }
        ::-webkit-scrollbar-thumb { background: #ef4444; border-radius: 10px; }
    </style>
</head>
<body class="antialiased">

    <div id="particles-js"></div>

    <div class="content-wrapper">
        <nav class="max-w-[1200px] mx-auto px-6 py-10 flex justify-between items-center">
            <div class="z-20">
                <span class="text-xl font-[900] tracking-tighter uppercase italic">Split<span class="text-red-600">Store</span></span>
            </div>
            <div class="hidden md:flex gap-10 text-zinc-500 text-[10px] font-bold uppercase tracking-[0.2em]">
                <a href="#recursos" class="hover:text-white transition">Recursos</a>
                <a href="#parceiros" class="hover:text-white transition">Parceiros</a>
                <a href="#planos" class="hover:text-white transition">Planos</a>
                <a href="#faq" class="hover:text-white transition">FAQ</a>
            </div>
            <div class="z-20">
                <a href="admin/login.php" class="bg-white text-black px-6 py-2.5 rounded-xl font-black text-[10px] uppercase hover:bg-zinc-200 transition">
                    Área do Cliente
                </a>
            </div>
        </nav>

        <main class="max-w-[1200px] mx-auto px-6 pt-12 md:pt-24 pb-32">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div data-aos="fade-right">
                    <div class="inline-block bg-red-950/20 border border-red-500/20 px-4 py-1.5 rounded-full mb-8 text-red-500 text-[9px] font-black uppercase tracking-[0.2em]">
                        Sistema V3.0 Disponível
                    </div>
                    <h1 class="text-hero font-[900] uppercase mb-8">
                        Lojas de <br>
                        <span class="text-white">Servidores</span><br>
                        <span class="text-red-600 italic">Profissionais</span>
                    </h1>
                    <p class="text-zinc-500 text-base max-w-md mb-12 font-medium leading-relaxed">
                        A solução definitiva para o seu servidor. Plugin de entrega automatizada e uma interface premium para seus jogadores.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-5">
                        <button onclick="window.location.href='#planos'" class="bg-red-600 hover:bg-red-700 text-white px-10 py-5 rounded-2xl font-black uppercase text-[11px] tracking-widest glow-red transition-all hover:scale-105 active:scale-95">
                            Criar Loja Agora
                        </button>
                        <button class="bg-zinc-900/40 border border-white/5 hover:bg-zinc-800 text-white px-10 py-5 rounded-2xl font-black uppercase text-[11px] tracking-widest transition-all">
                            Ver Demo
                        </button>
                    </div>
                </div>
                <div class="relative" data-aos="fade-left">
                    <div class="absolute -inset-10 bg-red-600 opacity-[0.1] blur-[100px] rounded-full"></div>
                    <div class="relative bg-zinc-900/50 border border-white/10 rounded-[2.5rem] overflow-hidden aspect-video flex items-center justify-center shadow-2xl group">
                        <span class="text-zinc-800 font-black text-4xl italic tracking-[0.3em] uppercase group-hover:text-zinc-700 transition">Preview</span>
                    </div>
                </div>
            </div>
        </main>

        <section id="recursos" class="max-w-[1200px] mx-auto px-6 py-24">
            <div class="text-center mb-20" data-aos="fade-up">
                <h2 class="text-zinc-600 text-[10px] font-black uppercase tracking-[0.4em] mb-4">Diferenciais</h2>
                <p class="text-3xl md:text-4xl font-black uppercase tracking-tighter italic">Por que escolher a <span class="text-red-600">SplitStore?</span></p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php
                $beneficios = [
                    ['tit' => 'Instantâneo', 'des' => 'Sincronização imediata entre loja e servidor.', 'icon' => 'zap'],
                    ['tit' => 'Checkout', 'des' => 'Processo de compra fluido em 2 cliques.', 'icon' => 'shopping-cart'],
                    ['tit' => 'Gestão', 'des' => 'Dashboard analítico para suas finanças.', 'icon' => 'bar-chart-3'],
                    ['tit' => 'Segurança', 'des' => 'Proteção nativa contra ataques e fraudes.', 'icon' => 'shield-check'],
                    ['tit' => 'Suporte', 'des' => 'Time de especialistas à sua disposição.', 'icon' => 'headset'],
                    ['tit' => 'Design', 'des' => 'Visual moderno que valoriza sua marca.', 'icon' => 'palette'],
                ];
                foreach ($beneficios as $i => $b): ?>
                    <div class="group relative" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
                        <div class="absolute -inset-0.5 bg-red-600 rounded-[2rem] opacity-0 group-hover:opacity-10 transition duration-500 blur-xl"></div>
                        <div class="relative glass-card p-10 rounded-[2rem] h-full transition-all duration-500 hover:border-red-600/30 hover:-translate-y-2">
                            <div class="w-12 h-12 bg-red-600/10 border border-red-600/20 rounded-xl flex items-center justify-center mb-6">
                                <i data-lucide="<?= $b['icon'] ?>" class="w-6 h-6 text-red-600"></i>
                            </div>
                            <h3 class="text-lg font-black uppercase italic mb-3 tracking-tight"><?= $b['tit'] ?></h3>
                            <p class="text-zinc-500 text-sm leading-relaxed"><?= $b['des'] ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="parceiros" class="max-w-[1200px] mx-auto px-6 py-24">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-zinc-600 text-[10px] font-black uppercase tracking-[0.4em] mb-4">Confiança</h2>
                <p class="text-3xl md:text-4xl font-black uppercase tracking-tighter italic">Servidores que <span class="text-red-600">Confiam</span></p>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6">
                <?php foreach ($partners as $i => $p): ?>
                    <div class="group relative" data-aos="zoom-in" data-aos-delay="<?= $i * 50 ?>">
                        <div class="relative glass-card aspect-square rounded-2xl flex flex-col items-center justify-center p-6 grayscale opacity-40 group-hover:grayscale-0 group-hover:opacity-100 transition-all duration-500 border border-white/5 hover:border-red-600/30">
                            <img src="<?= htmlspecialchars($p['logo']) ?>" alt="<?= htmlspecialchars($p['nome']) ?>" class="w-16 h-16 mb-4 rounded-lg object-contain">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-zinc-500 group-hover:text-red-500 transition-colors"><?= htmlspecialchars($p['nome']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-16 text-center" data-aos="fade-up">
                <p class="text-zinc-600 text-sm font-medium">Sua rede merece o melhor sistema.</p>
                <a href="#" class="text-red-600 font-black uppercase text-[10px] tracking-widest hover:text-red-500 transition-colors inline-flex items-center gap-2 mt-2">
                    Seja um parceiro <i data-lucide="arrow-right" class="w-3 h-3"></i>
                </a>
            </div>
        </section>

        <section id="planos" class="max-w-[1200px] mx-auto px-6 py-24">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-zinc-600 text-[10px] font-black uppercase tracking-[0.4em] mb-4">Investimento</h2>
                <p class="text-3xl md:text-4xl font-black uppercase tracking-tighter italic">Escolha o seu <span class="text-red-600">Plano</span></p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php
                $planos = [
                    ['nome' => 'Starter', 'preco' => '14,99', 'destaque' => false, 'beneficios' => ['1 Servidor', 'Checkout Padrão', 'Suporte via Ticket', 'Plugin de Entrega']],
                    ['nome' => 'Enterprise', 'preco' => '25,99', 'destaque' => true, 'beneficios' => ['5 Servidores', 'Checkout Customizável', 'Suporte Prioritário', 'Estatísticas Avançadas', 'Sem Taxas Extras']],
                    ['nome' => 'Gerencial', 'preco' => '39,99', 'destaque' => false, 'beneficios' => ['Servidores Ilimitados', 'API de Integração', 'Gerente de Contas', 'Whitelabel Total', 'Backup em Tempo Real']]
                ];
                foreach ($planos as $p): ?>
                    <div class="group relative" data-aos="fade-up">
                        <?php if($p['destaque']): ?>
                            <div class="absolute -inset-1 bg-red-600 rounded-[2.5rem] opacity-20 blur-2xl"></div>
                            <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-red-600 text-white text-[9px] font-black uppercase px-4 py-1 rounded-full z-20 tracking-widest">Mais Popular</div>
                        <?php endif; ?>
                        <div class="relative glass-card p-10 rounded-[2.5rem] border <?= $p['destaque'] ? 'border-red-600/50' : 'border-white/5' ?> flex flex-col h-full transition-all duration-500 hover:-translate-y-2">
                            <h3 class="text-zinc-400 text-[10px] font-black uppercase tracking-[0.3em] mb-4"><?= $p['nome'] ?></h3>
                            <div class="flex items-baseline gap-1 mb-8">
                                <span class="text-zinc-500 text-lg">R$</span>
                                <span class="text-4xl font-[900] tracking-tighter"><?= $p['preco'] ?></span>
                                <span class="text-zinc-600 text-xs">/mês</span>
                            </div>
                            <ul class="space-y-4 mb-10 flex-grow">
                                <?php foreach ($p['beneficios'] as $b): ?>
                                    <li class="flex items-center gap-3 text-zinc-400 text-sm">
                                        <i data-lucide="check" class="w-4 h-4 text-red-600"></i> <?= $b ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <button class="w-full py-4 rounded-xl font-black uppercase text-[10px] tracking-[0.2em] transition-all <?= $p['destaque'] ? 'bg-red-600 text-white glow-red hover:bg-red-700' : 'bg-white text-black hover:bg-zinc-200' ?>">
                                Assinar Agora
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="max-w-[1200px] mx-auto px-6 py-24">
            <div class="text-left mb-16" data-aos="fade-up">
                <h2 class="text-zinc-600 text-[10px] font-black uppercase tracking-[0.4em] mb-4">Depoimentos</h2>
                <p class="text-3xl md:text-4xl font-black uppercase tracking-tighter italic">Quem usa, <span class="text-red-600">Aprova</span></p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($feedbacks as $i => $f): ?>
                    <div class="group relative" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
                        <div class="relative glass-card p-8 rounded-[2rem] border border-white/5 flex flex-col h-full transition-all duration-500 hover:border-red-600/20">
                            <div class="flex gap-1 mb-6">
                                <?php for($s=0; $s < (int)$f['estrelas']; $s++): ?>
                                    <i data-lucide="star" class="w-3 h-3 fill-red-600 text-red-600"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="text-zinc-400 text-sm leading-relaxed mb-8 flex-grow italic">"<?= htmlspecialchars($f['texto']) ?>"</p>
                            <div class="flex items-center gap-4 border-t border-white/5 pt-6">
                                <div class="w-10 h-10 bg-zinc-900 rounded-full border border-red-600/20 flex items-center justify-center font-black text-red-600 text-xs"><?= strtoupper(substr($f['nome'], 0, 1)) ?></div>
                                <div>
                                    <h4 class="text-white text-xs font-black uppercase tracking-wider"><?= htmlspecialchars($f['nome']) ?></h4>
                                    <span class="text-zinc-600 text-[10px] font-bold uppercase"><?= htmlspecialchars($f['cargo']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="faq" class="max-w-[800px] mx-auto px-6 py-24">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-zinc-600 text-[10px] font-black uppercase tracking-[0.4em] mb-4">Dúvidas</h2>
                <p class="text-3xl font-black uppercase tracking-tighter italic">Perguntas <span class="text-red-600">Frequentes</span></p>
            </div>
            <div class="space-y-4">
                <?php
                $faqs = [
                    ['q' => 'Como funciona a entrega dos produtos?', 'a' => 'Assim que o pagamento é aprovado, nosso plugin sincroniza com seu servidor e executa os comandos automaticamente em segundos.'],
                    ['q' => 'Quais meios de pagamento são aceitos?', 'a' => 'Aceitamos Pix, Boleto e Cartão de Crédito através das principais gateways como Mercado Pago e Stripe.'],
                    ['q' => 'O sistema possui proteção contra fraudes?', 'a' => 'Sim, incluímos sistemas de verificação e integração com ferramentas de análise de risco para evitar chargebacks.'],
                ];
                foreach ($faqs as $faq): ?>
                    <div class="faq-item glass-card p-6 rounded-2xl cursor-pointer transition-all hover:border-red-600/30" onclick="this.classList.toggle('active')">
                        <div class="flex justify-between items-center">
                            <h3 class="text-sm font-bold uppercase tracking-tight italic"><?= $faq['q'] ?></h3>
                            <i data-lucide="chevron-down" class="faq-icon w-4 h-4 text-zinc-500 transition-transform"></i>
                        </div>
                        <div class="faq-content">
                            <p class="text-zinc-500 text-sm leading-relaxed font-medium"><?= $faq['a'] ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <footer class="relative z-10 bg-black pt-24 pb-12 border-t border-white/5">
            <div class="max-w-[1200px] mx-auto px-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-16">
                    <div class="col-span-1">
                        <span class="text-xl font-[900] tracking-tighter uppercase italic block mb-6">Split<span class="text-red-600">Store</span></span>
                        <p class="text-zinc-500 text-sm leading-relaxed font-medium">A maior infraestrutura de vendas para servidores do Brasil.</p>
                    </div>
                    <div>
                        <h4 class="text-white text-[10px] font-black uppercase tracking-[0.3em] mb-6">Plataforma</h4>
                        <ul class="space-y-4 text-zinc-500 text-xs font-bold uppercase tracking-wider">
                            <li><a href="#recursos" class="hover:text-red-600 transition-colors">Recursos</a></li>
                            <li><a href="#planos" class="hover:text-white transition-colors">Planos</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white text-[10px] font-black uppercase tracking-[0.3em] mb-6">Ajuda</h4>
                        <ul class="space-y-4 text-zinc-500 text-xs font-bold uppercase tracking-wider">
                            <li><a href="#" class="hover:text-white transition-colors">Documentação</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white text-[10px] font-black uppercase tracking-[0.3em] mb-6">Redes</h4>
                        <div class="flex gap-4">
                            <a href="#" class="w-10 h-10 bg-zinc-900 border border-white/5 rounded-xl flex items-center justify-center hover:border-red-600/50 transition-all text-zinc-400 hover:text-red-600">
                                <i data-lucide="instagram" class="w-4 h-4"></i>
                            </a>
                            <a href="#" class="w-10 h-10 bg-zinc-900 border border-white/5 rounded-xl flex items-center justify-center hover:border-red-600/50 transition-all text-zinc-400 hover:text-red-600">
                                <i data-lucide="message-circle" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="pt-8 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-6">
                    <p class="text-zinc-600 text-[10px] font-bold uppercase tracking-widest">© 2026 SplitStore - Grupo Split.</p>
                    <div class="flex gap-8">
                        <a href="#" class="text-zinc-700 text-[9px] font-bold uppercase hover:text-zinc-500 transition-colors tracking-widest">Termos</a>
                        <a href="#" class="text-zinc-700 text-[9px] font-bold uppercase hover:text-zinc-500 transition-colors tracking-widest">Privacidade</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        lucide.createIcons();
        AOS.init({ duration: 1000, once: true });
        particlesJS("particles-js", {
            "particles": {
                "number": { "value": 60, "density": { "enable": true, "value_area": 800 } },
                "color": { "value": "#ff0000" },
                "shape": { "type": "circle" },
                "opacity": { "value": 0.2, "random": true },
                "size": { "value": 2, "random": true },
                "line_linked": { "enable": true, "distance": 150, "color": "#ff0000", "opacity": 0.1, "width": 1 },
                "move": { "enable": true, "speed": 0.8, "direction": "none", "random": true, "straight": false, "out_mode": "out", "bounce": false }
            },
            "interactivity": {
                "events": { "onhover": { "enable": true, "mode": "grab" } },
                "modes": { "grab": { "distance": 140, "line_linked": { "opacity": 0.4 } } }
            },
            "retina_detect": true
        });
    </script>
</body>
</html>