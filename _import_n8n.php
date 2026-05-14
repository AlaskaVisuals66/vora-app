<?php
$base   = __DIR__.'/n8n/workflows';
$cookie = __DIR__.'/n8n_cookies.txt';
$url    = 'https://projeto-n8n-n8n.8w2toq.easypanel.host/rest/workflows';

foreach (glob($base.'/*.json') as $file) {
    $data = json_decode(file_get_contents($file), true);
    unset($data['tags'], $data['active'], $data['id'], $data['versionId']);
    $payload = json_encode($data);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'browser-id: helpdesk-import'],
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_COOKIEFILE     => $cookie,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo basename($file)." -> $code\n";
    if ($code >= 400) echo "   ".substr($resp,0,300)."\n";
    else {
        $j = json_decode($resp, true);
        echo "   id=".($j['data']['id'] ?? '?')."\n";
    }
}
