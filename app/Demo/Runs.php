<?php

namespace App\Demo;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Artisan commands the Console starts in the background. Each run is a few
 * files under storage/app/console-runs: its description, its output as it
 * is written, its exit code, and the table counts before and after. The
 * page polls them, so a run of minutes never meets a web server timeout,
 * and a run that rebuilds the database can still be followed.
 */
final class Runs
{
    public static function directory(): string
    {
        $directory = storage_path('app/console-runs');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory;
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed> the run
     */
    public static function start(string $command, array $arguments = []): array
    {
        $id = now()->format('Ymd-His').'-'.Str::lower(Str::random(4));
        $base = self::directory().'/'.$id;
        $artisan = fn (string $command, array $arguments = []): string => 'php artisan '.escapeshellarg($command).' '.implode(' ', array_map(escapeshellarg(...), $arguments));

        $run = [
            'id' => $id,
            'command' => trim($command.' '.implode(' ', $arguments)),
            'started_at' => now()->toIso8601String(),
        ];

        // Counts before, the command, its exit code, counts after, and a
        // fresh snapshot for the Console, in one detached shell.
        $script = implode('; ', [
            'cd '.escapeshellarg(base_path()),
            $artisan('demo:examine', ['--counts']).' > '.escapeshellarg("{$base}.before.json").' 2>/dev/null',
            $artisan($command, [...$arguments, '--no-ansi', '--no-interaction']).' > '.escapeshellarg("{$base}.log").' 2>&1',
            'echo $? > '.escapeshellarg("{$base}.exit"),
            $artisan('demo:examine', ['--counts']).' > '.escapeshellarg("{$base}.after.json").' 2>/dev/null',
            $artisan('demo:examine').' > /dev/null 2>&1',
            'touch '.escapeshellarg("{$base}.done"),
        ]);

        $result = Process::run(['sh', '-c', 'nohup sh -c '.escapeshellarg($script).' > /dev/null 2>&1 & echo $!']);
        $run['pid'] = (int) trim($result->output());

        file_put_contents("{$base}.json", json_encode($run, JSON_PRETTY_PRINT));

        return $run;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $id): ?array
    {
        if (preg_match('/^[0-9a-z-]+$/', $id) !== 1) {
            return null;
        }

        $base = self::directory().'/'.$id;

        if (! is_file("{$base}.json")) {
            return null;
        }

        $run = json_decode((string) file_get_contents("{$base}.json"), true);
        $exit = is_file("{$base}.exit") ? (int) trim((string) file_get_contents("{$base}.exit")) : null;
        $alive = ! is_file("{$base}.done") && ($run['pid'] ?? 0) > 0 && function_exists('posix_kill') && posix_kill($run['pid'], 0);

        $run['exit'] = $exit;
        $run['status'] = match (true) {
            $exit === null && $alive => 'running',
            $exit === null => 'stopped',
            $alive => 'finishing',
            default => 'finished',
        };
        $run['output'] = self::tail("{$base}.log");
        $run['before'] = self::json("{$base}.before.json");
        $run['after'] = self::json("{$base}.after.json");
        $run['seconds'] = (is_file("{$base}.exit") ? filemtime("{$base}.exit") : time()) - strtotime($run['started_at']);

        return $run;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 8): array
    {
        $files = glob(self::directory().'/*.json') ?: [];
        $ids = collect($files)
            ->map(fn (string $file): string => basename($file, '.json'))
            ->filter(fn (string $id): bool => preg_match('/^\d{8}-\d{6}-[a-z0-9]{4}$/', $id) === 1)
            ->sortDesc()
            ->take($limit);

        return $ids->map(fn (string $id): ?array => self::find($id))->filter()->values()->all();
    }

    public static function active(): ?array
    {
        return collect(self::recent(3))->first(fn (array $run): bool => in_array($run['status'], ['running', 'finishing'], true));
    }

    private static function tail(string $file, int $bytes = 60_000): string
    {
        if (! is_file($file)) {
            return '';
        }

        $size = filesize($file);
        $handle = fopen($file, 'r');
        fseek($handle, max(0, $size - $bytes));
        $output = (string) stream_get_contents($handle);
        fclose($handle);

        // Progress bars redraw a line with carriage returns: keep the last draw.
        $output = preg_replace('/^.*\r(?!\n)/m', '', str_replace("\r\n", "\n", $output));

        return ($size > $bytes ? "…\n" : '').$output;
    }

    private static function json(string $file): ?array
    {
        return is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    }
}
