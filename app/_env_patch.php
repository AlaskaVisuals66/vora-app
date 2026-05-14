<?php
$path = __DIR__.'/.env';
$src  = file_get_contents($path);

$replacements = [
    '/^APP_URL=.*$/m' => 'APP_URL=https://d0f9-177-200-189-30.ngrok-free.app',
];

foreach ($replacements as $pat => $rep) {
    $src = preg_replace($pat, $rep, $src);
}

file_put_contents($path, $src);
echo "patched\n";
