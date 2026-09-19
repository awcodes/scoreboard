<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('provider_id')->unique();
            $table->unsignedSmallInteger('season');
            $table->string('season_type');
            $table->unsignedTinyInteger('week');

            $table->foreignId('home_team_id')->constrained('teams');
            $table->foreignId('away_team_id')->constrained('teams');

            // Classification and conference are captured per game because
            // teams change subdivisions and conferences between seasons.
            $table->string('home_classification')->nullable();
            $table->string('away_classification')->nullable();
            $table->string('home_conference')->nullable();
            $table->string('away_conference')->nullable();

            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();

            $table->dateTime('start_at');
            $table->boolean('start_time_tbd')->default(false);
            $table->string('status');
            $table->unsignedTinyInteger('period')->nullable();
            $table->string('clock')->nullable();
            $table->string('network')->nullable();
            $table->string('venue')->nullable();
            $table->boolean('neutral_site')->default(false);
            $table->boolean('conference_game')->default(false);

            $table->dateTime('completed_at')->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();

            $table->index(['season', 'season_type', 'week']);
            $table->index(['status', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
