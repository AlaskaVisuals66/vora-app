<?php
$base    = 'https://projeto-n8n-evolution-api.8w2toq.easypanel.host';
$apikey  = 'y7Se2JIvspkCrmA7t38tQq8BJNsI0jWoqS3l6Q2r4WP';
$name    = 'helpdesk-main';

$ch = curl_init($base.'/instance/connect/'.$name);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['apikey: '.$apikey],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP $code\n";
$j = json_decode($resp, true);
if (!empty($j['base64'])) {
    $b64 = preg_replace('#^data:image/\w+;base64,#', '', $j['base64']);
    $out = __DIR__.'/qr_helpdesk.png';
    file_put_contents($out, base64_decode($b64));
    echo "QR salvo em: $out\n";
    echo "code: ".substr($j['code'] ?? '', 0, 80)."...\n";
} else {
    echo substr($resp, 0, 1000)."\n";
}
