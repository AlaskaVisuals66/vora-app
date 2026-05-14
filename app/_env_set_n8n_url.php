<?php
$path = __DIR__ . '/.env';
$env = file_get_contents($path);
$line = 'N8N_FORWARD_URL=https://projeto-n8n-n8n.8w2toq.easypanel.host/webhook/evo-incoming';
if (preg_match('/^N8N_FORWARD_URL=.*$/m', $env)) {
    $env = preg_replace('/^N8N_FORWARD_URL=.*$/m', $line, $env);
} else {
    $env .= "\n" . $line . "\n";
}
file_put_contents($path, $env);
echo "OK\n";
