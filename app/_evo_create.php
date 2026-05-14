<?php
$base    = 'https://projeto-n8n-evolution-api.8w2toq.easypanel.host';
$apikey  = 'y7Se2JIvspkCrmA7t38tQq8BJNsI0jWoqS3l6Q2r4WP';
$name    = 'helpdesk-main';
$webhook = 'https://d0f9-177-200-189-30.ngrok-free.app/api/v1/webhooks/evolution';

$payload = [
    'instanceName' => $name,
    'integration'  => 'WHATSAPP-BAILEYS',
    'qrcode'       => true,
    'webhook'      => [
        'url'    => $webhook,
        'byEvents' => false,
        'base64' => true,
        'events' => [
            'QRCODE_UPDATED',
            'CONNECTION_UPDATE',
            'MESSAGES_UPSERT',
            'MESSAGES_UPDATE',
            'SEND_MESSAGE',
        ],
    ],
];

$ch = curl_init($base.'/instance/create');
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

echo "HTTP $code\n";
echo substr($resp, 0, 2000)."\n";
