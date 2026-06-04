<?php

namespace App\Domain\Channel\Channels;

use App\Domain\Channel\Contracts\MessagingChannel;

class WebChatChannel implements MessagingChannel
{
    public static function getChannelName(): string
    {
        return 'web_chat';
    }

    public function getChannelType(): string
    {
        return 'web_chat';
    }

    public function sendText(string $identifier, string $text, ?string $quotedId = null): array
    {
        return ['key' => ['id' => null], 'status' => 'sent'];
    }

    public function sendMedia(string $identifier, string $mediaType, string $mediaUrl, ?string $caption = null, ?string $fileName = null): array
    {
        return ['key' => ['id' => null], 'status' => 'sent'];
    }

    public function sendAudio(string $identifier, string $audioUrl): array
    {
        return ['key' => ['id' => null], 'status' => 'sent'];
    }

    public function markAsRead(string $messageId, string $identifier): array
    {
        return ['status' => 'ok'];
    }

    public function presence(string $identifier, string $presence = 'composing'): array
    {
        return ['status' => 'ok'];
    }

    public function handleWebhook(array $payload): ?array
    {
        return null;
    }
}
