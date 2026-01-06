<?php
/**
 * ============================================
 * SPLITSTORE - API TESTER
 * ============================================
 * Script para testar endpoints da API
 * Acesse: http://seusite.com/api/test.php
 * ============================================
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SplitStore API Tester</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background: #0a0a0a;
            color: #fff;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            color: #ef4444;
            margin-bottom: 30px;
            font-size: 2em;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .endpoint {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .endpoint h2 {
            color: #ef4444;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        
        .method {
            display: inline-block;
            padding: 5px 15px;
            background: #ef4444;
            color: white;
            border-radius: 5px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .credentials {
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .credentials label {
            display: block;
            color: #999;
            margin-bottom: 5px;
            font-size: 0.9em;
        }
        
        .credentials input {
            width: 100%;
            background: #000;
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fff;
            padding: 10px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            margin-bottom: 10px;
        }
        
        button {
            background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(239, 68, 68, 0.5);
        }
        
        .response {
            background: #000;
            border: 1px solid rgba(0, 255, 0, 0.3);
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
            max-height: 400px;
            overflow-y: auto;
            display: none;
        }
        
        .response.show {
            display: block;
        }
        
        .response pre {
            color: #0f0;
            font-size: 0.9em;
            line-height: 1.5;
        }
        
        .error {
            border-color: rgba(255, 0, 0, 0.3);
        }
        
        .error pre {
            color: #f00;
        }
        
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .status.success {
            background: rgba(0, 255, 0, 0.2);
            color: #0f0;
        }
        
        .status.error {
            background: rgba(255, 0, 0, 0.2);
            color: #f00;
        }
        
        .loading {
            display: inline-block;
            margin-left: 10px;
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔌 SplitStore API Tester</h1>
        
        <div class="credentials">
            <h2 style="color: #ef4444; margin-bottom: 15px;">Credenciais</h2>
            <label>API Key:</label>
            <input type="text" id="apiKey" placeholder="ca_..." value="">
            
            <label>API Secret:</label>
            <input type="text" id="apiSecret" placeholder="ck_..." value="">
        </div>
        
        <!-- Endpoint: Verify -->
        <div class="endpoint">
            <h2><span class="method">POST</span> /plugin/verify</h2>
            <p style="color: #999; margin-bottom: 15px;">Verifica se as credenciais são válidas</p>
            <button onclick="testVerify()">Testar Endpoint</button>
            <div id="response-verify" class="response"></div>
        </div>
        
        <!-- Endpoint: Pending Purchases -->
        <div class="endpoint">
            <h2><span class="method">POST</span> /plugin/purchases/pending</h2>
            <p style="color: #999; margin-bottom: 15px;">Busca compras pendentes de um jogador</p>
            <label style="color: #999; margin-top: 10px; display: block;">Player UUID:</label>
            <input type="text" id="playerUUID" placeholder="f3c8d4a1-1234-5678-9abc-def012345678" style="width: 100%; background: #000; border: 1px solid rgba(239, 68, 68, 0.3); color: #fff; padding: 10px; border-radius: 5px; font-family: 'Courier New', monospace; margin-bottom: 10px;">
            <button onclick="testPendingPurchases()">Testar Endpoint</button>
            <div id="response-pending" class="response"></div>
        </div>
        
        <!-- Endpoint: Confirm Delivery -->
        <div class="endpoint">
            <h2><span class="method">POST</span> /plugin/purchases/confirm</h2>
            <p style="color: #999; margin-bottom: 15px;">Confirma entrega de uma compra</p>
            <label style="color: #999; margin-top: 10px; display: block;">Purchase ID:</label>
            <input type="text" id="purchaseId" placeholder="1" style="width: 100%; background: #000; border: 1px solid rgba(239, 68, 68, 0.3); color: #fff; padding: 10px; border-radius: 5px; font-family: 'Courier New', monospace; margin-bottom: 10px;">
            <button onclick="testConfirmDelivery()">Testar Endpoint</button>
            <div id="response-confirm" class="response"></div>
        </div>
        
        <!-- Endpoint: Player Logout -->
        <div class="endpoint">
            <h2><span class="method">POST</span> /plugin/player/logout</h2>
            <p style="color: #999; margin-bottom: 15px;">Registra logout de um jogador</p>
            <button onclick="testPlayerLogout()">Testar Endpoint</button>
            <div id="response-logout" class="response"></div>
        </div>
        
        <!-- Endpoint: Server Status -->
        <div class="endpoint">
            <h2><span class="method">POST</span> /plugin/server/status</h2>
            <p style="color: #999; margin-bottom: 15px;">Atualiza status do servidor</p>
            <button onclick="testServerStatus()">Testar Endpoint</button>
            <div id="response-status" class="response"></div>
        </div>
    </div>
    
    <script>
        const API_BASE = window.location.origin + '/api';
        
        function getCredentials() {
            const apiKey = document.getElementById('apiKey').value;
            const apiSecret = document.getElementById('apiSecret').value;
            
            if (!apiKey || !apiSecret) {
                alert('Por favor, preencha as credenciais!');
                return null;
            }
            
            return { apiKey, apiSecret };
        }
        
        function showResponse(elementId, data, isError = false) {
            const element = document.getElementById(elementId);
            element.className = 'response show' + (isError ? ' error' : '');
            element.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        }
        
        function showLoading(button) {
            button.disabled = true;
            button.innerHTML = button.innerHTML + ' <span class="loading">⏳</span>';
        }
        
        function hideLoading(button, originalText) {
            button.disabled = false;
            button.innerHTML = originalText;
        }
        
        async function testVerify() {
            const credentials = getCredentials();
            if (!credentials) return;
            
            const button = event.target;
            const originalText = button.innerHTML;
            showLoading(button);
            
            try {
                const response = await fetch(API_BASE + '/plugin/verify', {
                    method: 'POST',
                    mode: 'cors',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Api-Key': credentials.apiKey,
                        'X-Api-Secret': credentials.apiSecret
                    },
                    body: JSON.stringify({
                        server: 'minecraft',
                        version: '1.20.1'
                    })
                });
                
                const text = await response.text();
                let data;
                
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    data = { 
                        error: 'Invalid JSON response', 
                        raw_response: text.substring(0, 500) 
                    };
                }
                
                showResponse('response-verify', data, !response.ok);
            } catch (error) {
                showResponse('response-verify', { error: error.message, stack: error.stack }, true);
            } finally {
                hideLoading(button, originalText);
            }
        }
        
        async function testPendingPurchases() {
            const credentials = getCredentials();
            if (!credentials) return;
            
            const playerUUID = document.getElementById('playerUUID').value;
            if (!playerUUID) {
                alert('Por favor, preencha o Player UUID!');
                return;
            }
            
            const button = event.target;
            const originalText = button.innerHTML;
            showLoading(button);
            
            try {
                const response = await fetch(API_BASE + '/plugin/purchases/pending', {
                    method: 'POST',
                    mode: 'cors',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Api-Key': credentials.apiKey,
                        'X-Api-Secret': credentials.apiSecret
                    },
                    body: JSON.stringify({
                        player_uuid: playerUUID,
                        player_name: 'TestPlayer'
                    })
                });
                
                const text = await response.text();
                let data;
                
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    data = { 
                        error: 'Invalid JSON response', 
                        raw_response: text.substring(0, 500) 
                    };
                }
                
                showResponse('response-pending', data, !response.ok);
            } catch (error) {
                showResponse('response-pending', { error: error.message, stack: error.stack }, true);
            } finally {
                hideLoading(button, originalText);
            }
        }
        
        async function testConfirmDelivery() {
            const credentials = getCredentials();
            if (!credentials) return;
            
            const purchaseId = document.getElementById('purchaseId').value;
            const playerUUID = document.getElementById('playerUUID').value;
            
            if (!purchaseId || !playerUUID) {
                alert('Por favor, preencha Purchase ID e Player UUID!');
                return;
            }
            
            const button = event.target;
            const originalText = button.innerHTML;
            showLoading(button);
            
            try {
                const response = await fetch(API_BASE + '/plugin/purchases/confirm', {
                    method: 'POST',
                    mode: 'cors',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Api-Key': credentials.apiKey,
                        'X-Api-Secret': credentials.apiSecret
                    },
                    body: JSON.stringify({
                        purchase_id: purchaseId,
                        player_uuid: playerUUID,
                        delivered_at: Date.now()
                    })
                });
                
                const text = await response.text();
                let data;
                
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    data = { 
                        error: 'Invalid JSON response', 
                        raw_response: text.substring(0, 500) 
                    };
                }
                
                showResponse('response-confirm', data, !response.ok);
            } catch (error) {
                showResponse('response-confirm', { error: error.message, stack: error.stack }, true);
            } finally {
                hideLoading(button, originalText);
            }
        }
        
        async function testPlayerLogout() {
            const credentials = getCredentials();
            if (!credentials) return;
            
            const playerUUID = document.getElementById('playerUUID').value || 'test-uuid-123';
            
            const button = event.target;
            const originalText = button.innerHTML;
            showLoading(button);
            
            try {
                const response = await fetch(API_BASE + '/plugin/player/logout', {
                    method: 'POST',
                    mode: 'cors',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Api-Key': credentials.apiKey,
                        'X-Api-Secret': credentials.apiSecret
                    },
                    body: JSON.stringify({
                        player_uuid: playerUUID,
                        player_name: 'TestPlayer',
                        timestamp: Date.now()
                    })
                });
                
                const text = await response.text();
                let data;
                
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    data = { 
                        error: 'Invalid JSON response', 
                        raw_response: text.substring(0, 500) 
                    };
                }
                
                showResponse('response-logout', data, !response.ok);
            } catch (error) {
                showResponse('response-logout', { error: error.message, stack: error.stack }, true);
            } finally {
                hideLoading(button, originalText);
            }
        }
        
        async function testServerStatus() {
            const credentials = getCredentials();
            if (!credentials) return;
            
            const button = event.target;
            const originalText = button.innerHTML;
            showLoading(button);
            
            try {
                const response = await fetch(API_BASE + '/plugin/server/status', {
                    method: 'POST',
                    mode: 'cors',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Api-Key': credentials.apiKey,
                        'X-Api-Secret': credentials.apiSecret
                    },
                    body: JSON.stringify({
                        online_players: 42,
                        max_players: 100,
                        timestamp: Date.now()
                    })
                });
                
                const text = await response.text();
                let data;
                
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    data = { 
                        error: 'Invalid JSON response', 
                        raw_response: text.substring(0, 500) 
                    };
                }
                
                showResponse('response-status', data, !response.ok);
            } catch (error) {
                showResponse('response-status', { error: error.message, stack: error.stack }, true);
            } finally {
                hideLoading(button, originalText);
            }
        }
    </script>
</body>
</html>