<?php

namespace OctoSqueeze\Laravel\Console\Commands;

use Illuminate\Console\Command;
use OctoSqueeze\Laravel\Facades\OctoSqueeze;

class UsageCommand extends Command
{
    protected $signature = 'octosqueeze:usage';

    protected $description = 'Display OctoSqueeze API usage statistics';

    public function handle(): int
    {
        $this->info('Fetching usage statistics...');

        $result = OctoSqueeze::usage();

        if (!$result['state']) {
            $this->error("Failed to fetch usage: " . ($result['error'] ?? 'Unknown error'));
            return self::FAILURE;
        }

        $data = $result['data'] ?? [];

        $this->newLine();
        $this->line('<fg=cyan>OctoSqueeze Usage Statistics</>');
        $this->line(str_repeat('-', 40));

        if (isset($data['plan'])) {
            $this->line("Plan: <fg=yellow>{$data['plan']}</>");
        }

        if (isset($data['compressions_used'])) {
            $limit = $data['compressions_limit'] ?? 'Unlimited';
            $this->line("Compressions: <fg=green>{$data['compressions_used']}</> / {$limit}");
        }

        if (isset($data['api_calls_today'])) {
            $limit = $data['api_calls_limit'] ?? 'Unlimited';
            $this->line("API Calls Today: <fg=green>{$data['api_calls_today']}</> / {$limit}");
        }

        if (isset($data['bytes_saved'])) {
            $this->line("Total Bytes Saved: <fg=green>" . $this->formatBytes($data['bytes_saved']) . "</>");
        }

        if (isset($data['reset_date'])) {
            $this->line("Resets: <fg=gray>{$data['reset_date']}</>");
        }

        $this->newLine();

        return self::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
