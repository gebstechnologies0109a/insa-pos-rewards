<?php

namespace App\Services\POS;

use Illuminate\Support\Str;

class QRDecoderService
{
    /**
     * Decode a QR payload into a structured result.
     *
     * Supports:
     *  - Raw UUID strings
     *  - Card number prefixed with "CARD:"
     *  - Base64-encoded payloads wrapping either format
     *
     * @return array{type: string, value: string}
     */
    public function decode(string $payload): array
    {
        $payload = trim($payload);

        if (Str::isUuid($payload)) {
            return ['type' => 'uuid', 'value' => $payload];
        }

        if (str_starts_with(strtoupper($payload), 'CARD:')) {
            return ['type' => 'card_number', 'value' => substr($payload, 5)];
        }

        $decoded = base64_decode($payload, strict: true);
        if ($decoded !== false) {
            return $this->decode($decoded);
        }

        return ['type' => 'card_number', 'value' => $payload];
    }
}
