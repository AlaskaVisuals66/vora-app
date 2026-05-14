<?php
$base   = 'https://projeto-n8n-evolution-api.8w2toq.easypanel.host';
$apikey = 'y7Se2JIvspkCrmA7t38tQq8BJNsI0jWoqS3l6Q2r4WP';
$name   = 'helpdesk-main';

$ch = curl_init($base.'/instance/connectionState/'.$name);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['apikey: '.$apikey],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP $code\n$resp\n";

$ch = curl_init($base.'/instance/fetchInstances?instanceName='.$name);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['apikey: '.$apikey],
]);
$resp = curl_exec($ch);
curl_close($ch);
echo "\n--- fetchInstances ---\n";
$j = json_decode($resp, true);
if (is_array($j)) {
    foreach ($j as $inst) {
        $n = $inst['name'] ?? $inst['instance']['instanceName'] ?? '?';
        $s = $inst['connectionStatus'] ?? $inst['instance']['state'] ?? '?';
        $owner = $inst['ownerJid'] ?? $inst['instance']['owner'] ?? '?';
        $profile = $inst['profileName'] ?? $inst['instance']['profileName'] ?? '?';
        echo "$n  state=$s  owner=$owner  profile=$profile\n";
    }
}
