<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Poll;
use App\Enums\SeasonType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TeamRanking extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    protected function casts(): array
    {
        return [
            'season_type' => SeasonType::class,
            'poll' => Poll::class,
        ];
    }
}
