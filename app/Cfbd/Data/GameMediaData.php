<?php

declare(strict_types=1);

namespace App\Cfbd\Data;

final readonly class GameMediaData
{
    use ReadsPayload;

    /**
     * CFBD sometimes lists the same outlet twice under different names, or
     * under an abbreviated one.
     */
    private const array ALIASES = [
        'The CW Network' => 'CW',
        'ACC Extra' => 'ACCNX',
        'USA Net' => 'USA Network',
    ];

    public function __construct(
        public int $gameProviderId,
        public string $mediaType,
        public string $outlet,
    ) {}

    public static function fromPayload(array $payload): self
    {
        $outlet = mb_trim((string) self::required($payload, 'outlet'));

        return new self(
            gameProviderId: (int) self::required($payload, 'id'),
            mediaType: mb_strtolower((string) self::required($payload, 'mediaType')),
            outlet: self::normalizeOutlet($outlet),
        );
    }

    public static function normalizeOutlet(string $outlet): string
    {
        $outlet = mb_trim($outlet);

        return self::ALIASES[$outlet] ?? $outlet;
    }

    /**
     * Collapse a game's media rows into one display string. Television
     * outlets win; streaming-only games fall back to web/other outlets.
     * Radio is ignored.
     *
     * @param  iterable<self>  $media
     */
    public static function networkFor(iterable $media): ?string
    {
        $outlets = collect($media)->reject(fn (self $m): bool => $m->mediaType === 'radio');

        $preferred = $outlets->where('mediaType', 'tv');

        if ($preferred->isEmpty()) {
            $preferred = $outlets;
        }

        $names = $preferred->pluck('outlet')->filter()->unique()->values();

        return $names->isEmpty() ? null : $names->implode(' / ');
    }
}
