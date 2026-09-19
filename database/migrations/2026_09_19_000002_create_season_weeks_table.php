<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_weeks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('season');
            $table->string('season_type');
            $table->unsignedTinyInteger('week');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('first_game_at')->nullable();
            $table->dateTime('last_game_at')->nullable();
            $table->timestamps();

            $table->unique(['season', 'season_type', 'week']);
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_weeks');
    }
};
