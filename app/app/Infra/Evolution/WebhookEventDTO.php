<?php

namespace App\Infra\Evolution;

final class WebhookEventDTO
{
    public function __construct(
        public readonly string  $event,
        public readonly string  $instance,
        public readonly array   $data,
        public readonly ?string $messageId = null,
        public readonly ?string $remoteJid = null,
        public readonly ?string $fromNumber = null,
        public readonly ?string $pushName  = null,
        public readonly bool    $fromMe    = false,
        public readonly string  $messageType = 'text',
        public readonly ?string $body      = null,
        public readonly ?array  $media     = null,
    ) {}

    public static function fromPayload(array $payload): self
    {
        $event    = (string) ($payload['event'] ?? 'unknown');
        $instance = (string) ($payload['instance'] ?? $payload['instanceName'] ?? '');
        $data     = (array)  ($payload['data'] ?? []);

        $messageId = $data['key']['id']        ?? null;
        $remoteJid = $data['key']['remoteJid'] ?? null;
        $fromMe    = (bool) ($data['key']['fromMe'] ?? false);
        $pushName  = $data['pushName'] ?? null;
        $number    = $remoteJid ? preg_replace('/@.*$/', '', $remoteJid) : null;

        $type = 'text'; $body = null; $media = null;

        if (isset($data['message'])) {
            $msg = $data['message'];
            if (isset($msg['conversation'])) {
                $body = (string) $msg['conversation'];
            } elseif (isset($msg['extendedTextMessage']['text'])) {
                $body = (string) $msg['extendedTextMessage']['text'];
            } elseif (isset($msg['imageMessage'])) {
                $type = 'image';
                $body = $msg['imageMessage']['caption'] ?? null;
                $media = $msg['imageMessage'];
            } elseif (isset($msg['videoMessage'])) {
                $type = 'video';
                $body = $msg['videoMessage']['caption'] ?? null;
                $media = $msg['videoMessage'];
            } elseif (isset($msg['audioMessage'])) {
                $type = 'audio';
                $media = $msg['audioMessage'];
            } elseif (isset($msg['documentMessage'])) {
                $type = 'document';
                $body = $msg['documentMessage']['caption'] ?? null;
                $media = $msg['documentMessage'];
            } elseif (isset($msg['stickerMessage'])) {
                $type = 'sticker';
                $media = $msg['stickerMessage'];
            } elseif (isset($msg['locationMessage'])) {
                $type = 'location';
                $media = $msg['locationMessage'];
            }
        }

        return new self(
            event:       $event,
            instance:    $instance,
            data:        $data,
            messageId:   $messageId,
            remoteJid:   $remoteJid,
            fromNumber:  $number,
            pushName:    $pushName,
            fromMe:      $fromMe,
            messageType: $type,
            body:        $body,
            media:       $media,
        );
    }
}
