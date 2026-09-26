<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dateTime('original_start_at')->nullable()->after('start_time_tbd');
            $table->boolean('start_delayed')->default(false)->after('original_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table): void {
            $table->dropColumn(['original_start_at', 'start_delayed']);
        });
    }
};
