<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Cfbd\CfbdClient;
use App\Cfbd\CfbdException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/**
 * Provider spike: fetch the raw responses this app depends on, save them,
 * and print what they reveal (tier access, TBDs, FCS games, poll weeks...).
 * Uses roughly nine API calls.
 */
final class SpikeCommand extends CfbdCommand
{
    use ResolvesSeason;

    protected $signature = 'cfbd:spike
        {--season= : Season year (defaults to the current season)}
        {--week=1 : Regular-season week to sample}';

    protected $description = 'Inspect live CFBD responses and save them to storage/app/cfbd-spike';

    public function handle(CfbdClient $cfbd): int
    {
        $season = $this->season();
        $week = (int) $this->option('week');
        $dir = storage_path('app/cfbd-spike');
        File::ensureDirectoryExists($dir);

        $requests = [
            'info' => ['/info', []],
            'teams-fbs' => ['/teams/fbs', ['year' => $season]],
            'calendar' => ['/calendar', ['year' => $season]],
            'games-fbs' => ['/games', ['year' => $season, 'week' => $week, 'seasonType' => 'regular', 'classification' => 'fbs']],
            'games-fcs' => ['/games', ['year' => $season, 'week' => $week, 'seasonType' => 'regular', 'classification' => 'fcs']],
            'media' => ['/games/media', ['year' => $season, 'week' => $week, 'seasonType' => 'regular']],
            'rankings' => ['/rankings', ['year' => $season, 'seasonType' => 'both']],
            'records' => ['/records', ['year' => $season]],
            'scoreboard' => ['/scoreboard', ['classification' => 'fbs']],
        ];

        $data = [];

        foreach ($requests as $name => [$endpoint, $query]) {
            try {
                $data[$name] = $cfbd->raw($endpoint, $query);
                File::put("{$dir}/{$name}.json", json_encode($data[$name], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $this->line("<info>✓</info> {$endpoint} → {$name}.json");
            } catch (CfbdException $e) {
                $data[$name] = null;
                $this->line("<error>✗</error> {$endpoint}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->report($data, $week);

        return self::SUCCESS;
    }

    private function report(array $data, int $week): void
    {
        $info = $data['info'] ?? [];
        $this->components->twoColumnDetail('Tier', ($info['tierName'] ?? '?').' (patron level '.($info['patronLevel'] ?? '?').')');
        $this->components->twoColumnDetail('Calls used / remaining', ($info['usedCalls'] ?? '?').' / '.($info['remainingCalls'] ?? '?'));
        $this->components->twoColumnDetail('Scoreboard access', var_export(data_get($info, 'features.scoreboard'), true));

        $this->components->twoColumnDetail('FBS teams', (string) count($data['teams-fbs'] ?? []));
        $this->components->twoColumnDetail('Calendar weeks', collect($data['calendar'] ?? [])->map(fn ($w): string => $w['seasonType'][0].$w['week'])->implode(' '));

        $games = collect($data['games-fbs'] ?? []);
        $fcsOnly = collect($data['games-fcs'] ?? [])->whereNotIn('id', $games->pluck('id'));
        $this->components->twoColumnDetail("Week {$week} FBS games", (string) $games->count());
        $this->components->twoColumnDetail('  with an FCS/lower opponent', (string) $games->filter(fn ($g): bool => mb_strtolower($g['homeClassification'] ?? '') !== 'fbs' || mb_strtolower($g['awayClassification'] ?? '') !== 'fbs')->count());
        $this->components->twoColumnDetail('  startTimeTBD', (string) $games->where('startTimeTBD', true)->count());
        $this->components->twoColumnDetail('  completed', (string) $games->where('completed', true)->count());
        $this->components->twoColumnDetail('  classification values', $games->flatMap(fn ($g): array => [$g['homeClassification'] ?? 'null', $g['awayClassification'] ?? 'null'])->unique()->implode(', '));
        $this->components->twoColumnDetail("Week {$week} FCS-only games", (string) $fcsOnly->count());

        $media = collect($data['media'] ?? []);
        $this->components->twoColumnDetail('Media rows', $media->countBy('mediaType')->map(fn ($n, $t): string => "{$t}: {$n}")->implode(', '));
        $this->components->twoColumnDetail('  FBS games without TV/web outlet', (string) $games->pluck('id')->diff($media->whereIn('mediaType', ['tv', 'web'])->pluck('id'))->count());
        $this->components->twoColumnDetail('  outlets', $media->pluck('outlet')->unique()->sort()->implode(', '));

        $rankings = collect($data['rankings'] ?? []);
        $this->components->twoColumnDetail('Polls', $rankings->flatMap(fn ($w) => Arr::pluck($w['polls'] ?? [], 'poll'))->unique()->implode(', '));
        $this->components->twoColumnDetail('AP poll weeks', $rankings->filter(fn ($w) => collect($w['polls'] ?? [])->contains('poll', 'AP Top 25'))->map(fn ($w): string => $w['seasonType'][0].$w['week'])->implode(' '));

        $scoreboard = collect($data['scoreboard'] ?? []);
        $this->components->twoColumnDetail('Scoreboard games', $scoreboard->countBy('status')->map(fn ($n, $s): string => "{$s}: {$n}")->implode(', ') ?: 'none');
        $this->components->twoColumnDetail('  sample clock values', $scoreboard->pluck('clock')->filter()->take(5)->implode(', ') ?: '-');
    }
}
