<?php

namespace App\Domain\Channel\Contracts;

use App\Domain\Channel\Models\Channel;

interface MessagingChannel
{
    public function sendText(string $identifier, string $text, ?string $quotedId = null): array;

    public function sendMedia(string $identifier, string $mediaType, string $mediaUrl, ?string $caption = null, ?string $fileName = null): array;

    public function sendAudio(string $identifier, string $audioUrl): array;

    public function markAsRead(string $messageId, string $identifier): array;

    public function presence(string $identifier, string $presence = 'composing'): array;

    public function handleWebhook(array $payload): ?array;

    public function getChannelType(): string;

    public static function getChannelName(): string;
}
