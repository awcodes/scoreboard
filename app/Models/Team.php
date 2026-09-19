<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Team extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return HasMany<TeamRanking, $this>
     */
    public function rankings(): HasMany
    {
        return $this->hasMany(TeamRanking::class);
    }
}
