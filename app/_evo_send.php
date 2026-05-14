<?php
$base    = 'https://projeto-n8n-evolution-api.8w2toq.easypanel.host';
$apikey  = 'y7Se2JIvspkCrmA7t38tQq8BJNsI0jWoqS3l6Q2r4WP';
$instance= 'helpdesk-main';
$jid     = '5521982212296'; // Patrick

$payload = [
    'number' => $jid,
    'text'   => 'Teste direto da API – se vc recebeu, o sendText funciona ✅',
    'options'=> ['delay' => 600, 'presence' => 'composing'],
];

$ch = curl_init($base.'/message/sendText/'.$instance);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'apikey: '.$apikey,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP $code\n".$resp."\n";
