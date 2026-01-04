<?php
// Credenciais SplitStore
define('MISTIC_CLIENT_ID', 'ci_6wqrtigx1d8e430');
define('MISTIC_CLIENT_SECRET', 'cs_w810l4jlhnqs60rrmxh8xgd2u');
define('MISTIC_API_URL', 'https://api.misticpay.com/api'); // Verifique a versão na docs

class MisticPay {
    public static function generatePix($amount, $order_id) {
        $ch = curl_init(MISTIC_API_URL . '/payments');
        $data = [
            'amount' => $amount,
            'currency' => 'BRL',
            'method' => 'pix',
            'external_id' => $order_id,
            'client_id' => MISTIC_CLIENT_ID,
            'client_secret' => MISTIC_CLIENT_SECRET
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        return json_decode($response, true);
    }
}