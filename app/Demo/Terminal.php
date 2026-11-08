<?php

namespace App\Demo;

use Illuminate\Support\Facades\Process;

/**
 * Runs an artisan command in its own process, as you would in a terminal.
 * The package only registers its commands when the app runs in the console,
 * and a separate process also keeps a long maintenance run out of PHP-FPM's
 * time and memory limits.
 */
final class Terminal
{
    /**
     * @param  list<string>  $arguments
     * @return array{exit: int, output: string}
     */
    public static function artisan(string $command, array $arguments = [], int $timeout = 600): array
    {
        $result = Process::path(base_path())
            ->timeout($timeout)
            ->env(['COLUMNS' => '110'])
            ->run(['php', 'artisan', $command, ...$arguments, '--no-ansi', '--no-interaction']);

        return [
            'exit' => $result->exitCode() ?? 1,
            'output' => trim($result->output()."\n".$result->errorOutput()),
        ];
    }
}
