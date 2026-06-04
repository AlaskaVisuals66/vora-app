<?php

namespace App\Domain\Channel\Channels;

use App\Domain\Channel\Contracts\MessagingChannel;
use App\Infra\Evolution\EvolutionApiClient;

class EvolutionChannel implements MessagingChannel
{
    public function __construct(
        private readonly EvolutionApiClient $evolution,
        private readonly string $instanceName,
    ) {}

    public static function getChannelName(): string
    {
        return 'whatsapp';
    }

    public function getChannelType(): string
    {
        return 'evolution';
    }

    public function sendText(string $identifier, string $text, ?string $quotedId = null): array
    {
        return $this->evolution->sendText($this->instanceName, $identifier, $text, $quotedId);
    }

    public function sendMedia(string $identifier, string $mediaType, string $mediaUrl, ?string $caption = null, ?string $fileName = null): array
    {
        return $this->evolution->sendMedia($this->instanceName, $identifier, $mediaType, $mediaUrl, $caption, $fileName);
    }

    public function sendAudio(string $identifier, string $audioUrl): array
    {
        return $this->evolution->sendAudio($this->instanceName, $identifier, $audioUrl);
    }

    public function markAsRead(string $messageId, string $identifier): array
    {
        return $this->evolution->markAsRead($this->instanceName, $messageId, $identifier);
    }

    public function presence(string $identifier, string $presence = 'composing'): array
    {
        return $this->evolution->presence($this->instanceName, $identifier, $presence);
    }

    public function handleWebhook(array $payload): ?array
    {
        return null;
    }
}
