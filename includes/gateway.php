<?php
// Credenciais SplitStore
define('MISTIC_CLIENT_ID', 'ci_6wqrtigx1d8e430');
define('MISTIC_CLIENT_SECRET', 'cs_w810l4jlhnqs60rrmxh8xgd2u');
define('MISTIC_API_URL', 'https://api.misticpay.com/v1'); // Verifique a versão na docs

class MisticPay {
    
    /**
     * Gera cobrança PIX
     */
    public static function generatePix($amount, $external_id, $customer) {
        $ch = curl_init(MISTIC_API_URL . '/payments');
        
        $data = [
            'amount' => (float)$amount,
            'currency' => 'BRL',
            'method' => 'pix',
            'external_id' => $external_id,
            'customer' => [
                'name' => $customer['name'],
                'email' => $customer['email'],
                'document' => $customer['cpf']
            ],
            'notification_url' => 'https://splitstore.com.br/webhooks/misticpay.php'
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . MISTIC_CLIENT_SECRET
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("MisticPay Error: " . $response);
            return null;
        }

        return json_decode($response, true);
    }
    
    /**
     * Consulta status de pagamento
     */
    public static function checkPayment($payment_id) {
        $ch = curl_init(MISTIC_API_URL . '/payments/' . $payment_id);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . MISTIC_CLIENT_SECRET
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }

        return json_decode($response, true);
    }
}