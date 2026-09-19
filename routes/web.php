<?php

declare(strict_types=1);

use App\Livewire\Scoreboard;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Scoreboard::class)->name('home');

Route::livewire('/{season}/{week}', Scoreboard::class)
    ->whereNumber('season')
    ->where('week', '\d+|post(-\d+)?')
    ->name('week');
