<?php

namespace App\Providers;

use App\Domain\Channel\ChannelManager;
use App\Infra\Evolution\EvolutionApiClient;
use App\Infra\N8n\N8nClient;
use App\Infra\Realtime\RealtimePresence;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public array $singletons = [
        EvolutionApiClient::class => EvolutionApiClient::class,
        N8nClient::class          => N8nClient::class,
        RealtimePresence::class   => RealtimePresence::class,
        ChannelManager::class     => ChannelManager::class,
    ];

    public function boot(): void {}
}
