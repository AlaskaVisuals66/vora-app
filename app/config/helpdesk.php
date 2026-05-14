<?php

return [
    'menu' => [
        'welcome' => "Olá! 👋 Bem-vindo ao nosso atendimento.\n\nPara te direcionar ao setor responsável, digite o número da opção desejada:\n\n1 - Comercial\n2 - Financeiro\n3 - Manutenção\n\nVocê seguirá conversando por este mesmo número e todo o histórico fica registrado para acompanhamento.\n\nDigite #sair a qualquer momento para encerrar.",
        'maintenance_submenu' => "Você escolheu Manutenção. Selecione:\n\n1 - Assessoria Técnica\n2 - Assessoria Científica",
        'invalid' => "Opção inválida. Por favor, digite somente o número da opção desejada (1, 2 ou 3).",
        'queued'  => "Obrigado! Você foi direcionado para o setor {sector}.\nProtocolo: {protocol}.\n\nUm de nossos atendentes já foi notificado e responderá em instantes por este mesmo número. ✅",
        'closed'  => "Atendimento encerrado. Caso precise novamente, basta enviar uma nova mensagem. ✨",
        'no_attendant' => "No momento todos os atendentes estão ocupados. Você está na posição {position} da fila e será atendido em instantes.",
        'transferred' => "Seu atendimento foi transferido para o setor {sector}. Aguarde um instante.",
    ],
    'sla' => [
        'first_response_seconds' => env('SLA_FIRST_RESPONSE', 180),
        'resolution_minutes'     => env('SLA_RESOLUTION', 60),
    ],
    'distribution' => [
        'strategy' => env('DISTRIBUTION_STRATEGY', 'round_robin'),
        'lock_ttl' => 30,
        'max_concurrent_per_attendant' => env('MAX_CONCURRENT_TICKETS', 5),
    ],
    'protocol' => [ 'prefix' => env('PROTOCOL_PREFIX', 'HD'), 'pad' => 6 ],
];
