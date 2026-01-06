<?php
/**
 * ============================================
 * SPLITSTORE - SISTEMA DE CRIAÇÃO AUTOMÁTICA DE LOJAS
 * ============================================
 * Salve como: includes/store_creator.php
 * 
 * FUNÇÕES:
 * 1. Cria pasta física em store/
 * 2. Copia template da loja
 * 3. Configura permissões
 * 4. Valida e trata erros
 */

class StoreCreator {
    
    private $base_path;
    private $template_path;
    
    public function __construct() {
        // Define caminhos
        $this->base_path = $_SERVER['DOCUMENT_ROOT'] . '/store/';
        $this->template_path = __DIR__ . '/../store_template/';
        
        // Cria diretório store/ se não existir
        if (!is_dir($this->base_path)) {
            @mkdir($this->base_path, 0755, true);
        }
    }
    
    /**
     * Cria a estrutura completa da loja
     * 
     * @param string $store_slug - URL da loja (ex: "minhaloja")
     * @param array $store_data - Dados da loja do banco
     * @return array ['success' => bool, 'message' => string, 'path' => string]
     */
    public function createStore($store_slug, $store_data = []) {
        try {
            // 1. VALIDA SLUG
            if (empty($store_slug) || !preg_match('/^[a-z0-9-]+$/', $store_slug)) {
                return [
                    'success' => false,
                    'message' => 'Slug inválido. Use apenas letras minúsculas, números e traços.'
                ];
            }
            
            // 2. VERIFICA SE JÁ EXISTE
            $store_path = $this->base_path . $store_slug . '/';
            if (is_dir($store_path)) {
                return [
                    'success' => false,
                    'message' => 'Uma loja com esta URL já existe.'
                ];
            }
            
            // 3. VERIFICA PERMISSÕES
            if (!is_writable($this->base_path)) {
                return [
                    'success' => false,
                    'message' => 'Sem permissão de escrita em /store/. Execute: chmod 755 /store/'
                ];
            }
            
            // 4. CRIA PASTA DA LOJA
            if (!mkdir($store_path, 0755, true)) {
                return [
                    'success' => false,
                    'message' => 'Falha ao criar diretório da loja.'
                ];
            }
            
            // 5. COPIA TEMPLATE
            $template_created = $this->copyTemplate($store_path, $store_slug, $store_data);
            
            if (!$template_created) {
                // Remove pasta se falhar
                @rmdir($store_path);
                return [
                    'success' => false,
                    'message' => 'Falha ao criar template da loja.'
                ];
            }
            
            // 6. CRIA .htaccess (SE APACHE)
            $this->createHtaccess($store_path);
            
            // 7. LOG DE SUCESSO
            error_log("✅ Loja criada com sucesso: {$store_slug}");
            
            return [
                'success' => true,
                'message' => 'Loja criada com sucesso!',
                'path' => $store_path,
                'url' => "https://{$_SERVER['HTTP_HOST']}/store/{$store_slug}/"
            ];
            
        } catch (Exception $e) {
            error_log("❌ Erro ao criar loja: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao criar loja: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Copia o template da loja
     */
    private function copyTemplate($store_path, $store_slug, $store_data) {
        // Conteúdo do template index.php
        $template_content = <<<'PHP'
<?php
/**
 * ============================================
 * LOJA: {{STORE_NAME}}
 * ============================================
 * Gerada automaticamente pelo SplitStore
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

// ==================================================================
// CONEXÃO COM BANCO
// ==================================================================
$db_found = false;
$possible_paths = [
    __DIR__ . '/../../includes/db.php',
    $_SERVER['DOCUMENT_ROOT'] . '/includes/db.php'
];

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_found = true;
        break;
    }
}

if (!$db_found || !isset($pdo)) {
    die("Erro: Banco de dados não conectado.");
}

// ==================================================================
// BUSCA DADOS DA LOJA
// ==================================================================
$store_slug = '{{STORE_SLUG}}';

try {
    // Busca loja pelo slug
    $stmt = $pdo->prepare("
        SELECT s.*, sc.* 
        FROM stores s
        LEFT JOIN store_customization sc ON s.id = sc.store_id
        WHERE s.store_slug = ? AND s.status = 'active'
    ");
    $stmt->execute([$store_slug]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$store) {
        die("Loja não encontrada ou inativa.");
    }
    
    // Busca produtos ativos
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE store_id = ? AND status = 'active'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$store['id']]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}

// Cores padrão se não configuradas
$primaryColor = $store['primary_color'] ?? '#8b5cf6';
$secondaryColor = $store['secondary_color'] ?? '#0f172a';
$logoUrl = $store['logo_url'] ?? '';

function formatMoney($val) { 
    return 'R$ ' . number_format((float)$val, 2, ',', '.'); 
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($store['store_name']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($store['store_description'] ?? 'Loja oficial') ?>">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?= $primaryColor ?>',
                        secondary: '<?= $secondaryColor ?>'
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        body { background: <?= $secondaryColor ?>; color: white; }
        .glass { background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        .glass-strong { background: rgba(20,20,20,0.8); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .product-card { transition: all 0.3s ease; }
        .product-card:hover { 
            transform: translateY(-5px); 
            border-color: <?= $primaryColor ?>;
            box-shadow: 0 10px 30px -10px <?= $primaryColor ?>40; 
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    
    <!-- NAVBAR -->
    <nav class="fixed top-0 w-full z-50 glass-strong h-20">
        <div class="max-w-7xl mx-auto px-6 h-full flex items-center justify-between">
            <div class="flex items-center gap-4">
                <?php if ($logoUrl): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" class="h-10 object-contain" alt="Logo">
                <?php else: ?>
                    <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center font-black text-white text-lg shadow-lg">
                        <?= strtoupper(substr($store['store_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <span class="font-black text-xl uppercase tracking-tight text-white">
                    <?= htmlspecialchars($store['store_name']) ?>
                </span>
            </div>
            
            <button onclick="toggleCart()" class="relative p-3 glass rounded-xl hover:bg-white/10 transition">
                <i data-lucide="shopping-cart" class="w-6 h-6"></i>
                <span id="cartBadge" class="absolute -top-1 -right-1 bg-primary text-white text-[10px] font-black w-5 h-5 flex items-center justify-center rounded-full opacity-0">0</span>
            </button>
        </div>
    </nav>
    
    <!-- HEADER -->
    <header class="pt-32 pb-16 text-center px-6">
        <h1 class="text-5xl md:text-7xl font-black uppercase mb-6">
            <?= htmlspecialchars($store['store_title'] ?? $store['store_name']) ?>
        </h1>
        <p class="text-zinc-400 text-lg max-w-2xl mx-auto">
            <?= htmlspecialchars($store['store_description'] ?? 'Bem-vindo à nossa loja') ?>
        </p>
    </header>
    
    <!-- PRODUTOS -->
    <main class="flex-grow max-w-7xl mx-auto px-6 pb-24 w-full">
        <?php if (empty($products)): ?>
            <div class="glass rounded-3xl p-24 text-center">
                <i data-lucide="package-open" class="w-16 h-16 text-zinc-600 mx-auto mb-4"></i>
                <h3 class="text-xl font-bold mb-2">Nenhum produto disponível</h3>
                <p class="text-zinc-500">Os produtos aparecerão aqui em breve.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($products as $product): ?>
                <div class="product-card glass rounded-2xl p-4 flex flex-col border border-white/5">
                    <div class="aspect-square rounded-xl bg-black/40 mb-4 flex items-center justify-center overflow-hidden">
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i data-lucide="box" class="w-12 h-12 text-zinc-700"></i>
                        <?php endif; ?>
                    </div>
                    <h3 class="font-bold text-lg uppercase mb-2"><?= htmlspecialchars($product['name']) ?></h3>
                    <p class="text-xs text-zinc-500 flex-grow"><?= htmlspecialchars($product['description'] ?? '') ?></p>
                    <div class="mt-4 pt-4 border-t border-white/5 flex items-center justify-between">
                        <span class="text-xl font-black text-primary"><?= formatMoney($product['price']) ?></span>
                        <button onclick='addToCart(<?= json_encode($product) ?>)' class="bg-white text-black w-10 h-10 rounded-lg flex items-center justify-center hover:bg-primary hover:text-white transition">
                            <i data-lucide="plus" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- FOOTER -->
    <footer class="border-t border-white/5 bg-black/20 py-10">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <p class="text-white font-bold">© <?= date('Y') ?> <?= htmlspecialchars($store['store_name']) ?></p>
            <p class="text-zinc-600 text-xs mt-1">Powered by SplitStore</p>
        </div>
    </footer>
    
    <!-- MODAL CARRINHO -->
    <div id="cartModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="toggleCart()"></div>
        <div class="absolute right-0 top-0 h-full w-full max-w-md bg-secondary border-l border-white/10 p-6 flex flex-col" id="cartPanel">
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-2xl font-black uppercase">Carrinho</h2>
                <button onclick="toggleCart()" class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div id="cartItems" class="flex-1 overflow-y-auto"></div>
            <div class="mt-6 pt-6 border-t border-white/10">
                <div class="flex justify-between mb-6">
                    <span class="text-zinc-400 font-bold">Total</span>
                    <span id="cartTotal" class="text-3xl font-black text-primary">R$ 0,00</span>
                </div>
                <button onclick="checkout()" class="w-full bg-primary text-white py-4 rounded-xl font-black uppercase">
                    Finalizar Compra
                </button>
            </div>
        </div>
    </div>
    
    <script>
        lucide.createIcons();
        let cart = JSON.parse(localStorage.getItem('cart_<?= $store_slug ?>') || '[]');
        
        function updateCart() {
            const badge = document.getElementById('cartBadge');
            const items = document.getElementById('cartItems');
            const total = document.getElementById('cartTotal');
            
            badge.innerText = cart.length;
            badge.style.opacity = cart.length > 0 ? '1' : '0';
            
            if (cart.length === 0) {
                items.innerHTML = '<p class="text-center text-zinc-500">Carrinho vazio</p>';
                total.innerText = 'R$ 0,00';
                return;
            }
            
            let sum = 0;
            items.innerHTML = cart.map((item, i) => {
                sum += parseFloat(item.price);
                return `<div class="flex gap-4 bg-white/5 p-3 rounded-xl mb-3">
                    <div class="w-16 h-16 bg-black/30 rounded-lg"></div>
                    <div class="flex-1">
                        <div class="font-bold text-sm">${item.name}</div>
                        <div class="text-xs text-primary font-bold">R$ ${parseFloat(item.price).toFixed(2)}</div>
                    </div>
                    <button onclick="removeFromCart(${i})" class="text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </div>`;
            }).join('');
            
            total.innerText = 'R$ ' + sum.toFixed(2).replace('.', ',');
            lucide.createIcons();
        }
        
        function addToCart(product) {
            cart.push(product);
            localStorage.setItem('cart_<?= $store_slug ?>', JSON.stringify(cart));
            updateCart();
            toggleCart(true);
        }
        
        function removeFromCart(index) {
            cart.splice(index, 1);
            localStorage.setItem('cart_<?= $store_slug ?>', JSON.stringify(cart));
            updateCart();
        }
        
        function toggleCart(force) {
            const modal = document.getElementById('cartModal');
            if (modal.classList.contains('hidden') || force) {
                modal.classList.remove('hidden');
            } else {
                modal.classList.add('hidden');
            }
        }
        
        function checkout() {
            alert('Sistema de pagamento será integrado em breve!');
        }
        
        updateCart();
    </script>
</body>
</html>
PHP;
        
        // Substitui variáveis
        $template_content = str_replace(
            ['{{STORE_NAME}}', '{{STORE_SLUG}}'],
            [$store_data['store_name'] ?? 'Loja', $store_slug],
            $template_content
        );
        
        // Salva o arquivo
        return file_put_contents($store_path . 'index.php', $template_content) !== false;
    }
    
    /**
     * Cria .htaccess para Apache
     */
    private function createHtaccess($store_path) {
        $htaccess_content = <<<'HTACCESS'
# SplitStore - Configuração da Loja
RewriteEngine On
RewriteBase /

# Se não for arquivo ou diretório, redireciona para index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Proteção de arquivos sensíveis
<FilesMatch "\.(env|log|sql|bak)$">
    Order allow,deny
    Deny from all
</FilesMatch>
HTACCESS;
        
        @file_put_contents($store_path . '.htaccess', $htaccess_content);
    }
    
    /**
     * Remove loja (CUIDADO!)
     */
    public function deleteStore($store_slug) {
        $store_path = $this->base_path . $store_slug . '/';
        
        if (!is_dir($store_path)) {
            return ['success' => false, 'message' => 'Loja não existe'];
        }
        
        // Remove recursivamente
        $this->removeDirectory($store_path);
        
        return ['success' => true, 'message' => 'Loja removida'];
    }
    
    private function removeDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}