<?php

namespace App\Support;

/**
 * Normaliza números de WhatsApp para um formato canônico, evitando contatos
 * duplicados que surgiam pela variação de formato (9º dígito do celular BR,
 * código do país 55 faltando, etc.).
 *
 * Canônico BR (celular): 55 + DDD(2) + 9 + 8 dígitos = 13 dígitos.
 * Números fora do padrão BR são mantidos só com os dígitos (sem inventar nada).
 */
class Phone
{
    public static function canonical(?string $raw): string
    {
        $d = preg_replace('/\D+/', '', (string) $raw);
        $d = ltrim($d, '0');
        if ($d === '') {
            return '';
        }

        // Já no padrão 55 + DDD + 9 dígitos (13).
        if (strlen($d) === 13 && str_starts_with($d, '55')) {
            return $d;
        }

        // 55 + DDD + 8 dígitos (12) -> insere o 9º dígito do celular.
        if (strlen($d) === 12 && str_starts_with($d, '55')) {
            return '55' . substr($d, 2, 2) . '9' . substr($d, 4);
        }

        // DDD + 9 + 8 (11, sem o 55) -> prefixa 55.
        if (strlen($d) === 11) {
            return '55' . $d;
        }

        // DDD + 8 (10, sem 55 e sem o 9) -> prefixa 55 e insere o 9.
        if (strlen($d) === 10) {
            return '55' . substr($d, 0, 2) . '9' . substr($d, 2);
        }

        // Formato desconhecido / internacional: mantém só os dígitos.
        return $d;
    }
}
