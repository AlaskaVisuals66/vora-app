<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('helpdesk:rollup-analytics')->dailyAt('00:15');
Schedule::command('helpdesk:check-sla')->everyFiveMinutes();
// Auto-close DESATIVADO a pedido — o sistema não deve fechar conversas sozinho.
// Schedule::command('helpdesk:auto-close-idle --hours=24')->hourly();
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Monitora as conexões de WhatsApp e alerta por WhatsApp quando um número cai.
Schedule::command('app:monitor-whatsapp')->everyTwoMinutes()->withoutOverlapping();
