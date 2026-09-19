<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_rankings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedSmallInteger('season');
            $table->string('season_type');
            $table->unsignedTinyInteger('week');
            $table->string('poll');
            $table->unsignedTinyInteger('rank');
            $table->timestamps();

            $table->unique(['season', 'season_type', 'week', 'poll', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_rankings');
    }
};
