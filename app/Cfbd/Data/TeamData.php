<?php

declare(strict_types=1);

namespace App\Cfbd\Data;

final readonly class TeamData
{
    use ReadsPayload;

    public function __construct(
        public int $providerId,
        public string $name,
        public ?string $abbreviation,
        public ?string $conference,
        public ?string $classification,
        public ?string $logoUrl,
    ) {}

    public static function fromPayload(array $payload): self
    {
        return new self(
            providerId: (int) self::required($payload, 'id'),
            name: (string) self::required($payload, 'school'),
            abbreviation: self::string($payload, 'abbreviation'),
            conference: self::string($payload, 'conference'),
            classification: self::classification($payload, 'classification'),
            logoUrl: self::string($payload, 'logos.0'),
        );
    }
}
