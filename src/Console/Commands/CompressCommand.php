<?php

namespace OctoSqueeze\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use OctoSqueeze\Laravel\Facades\OctoSqueeze;

class CompressCommand extends Command
{
    protected $signature = 'octosqueeze:compress
                            {path : File path or URL to compress}
                            {--disk= : Storage disk to use}
                            {--output= : Output path for compressed file}
                            {--mode= : Compression mode (size, balanced, quality)}
                            {--format=* : Output formats (webp, avif, jpeg, png)}';

    protected $description = 'Compress an image using OctoSqueeze';

    public function handle(): int
    {
        $path = $this->argument('path');
        $disk = $this->option('disk') ?? config('octosqueeze.disk');
        $output = $this->option('output');
        $mode = $this->option('mode');
        $formats = $this->option('format');

        $options = [];

        if ($mode) {
            $options['mode'] = $mode;
        }

        if (!empty($formats)) {
            $options['formats'] = $formats;
        }

        $this->info("Compressing: {$path}");

        // Check if it's a URL or file path
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $result = OctoSqueeze::client()->compressUrl($path, $options);
        } elseif (file_exists($path)) {
            $result = OctoSqueeze::compress($path, $options);
        } elseif (Storage::disk($disk)->exists($path)) {
            $fullPath = Storage::disk($disk)->path($path);
            $result = OctoSqueeze::compress($fullPath, $options);
        } else {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        if (!$result['state']) {
            $this->error("Compression failed: " . ($result['error'] ?? 'Unknown error'));
            return self::FAILURE;
        }

        $data = $result['data'] ?? [];

        $this->info("Compression successful!");

        if (isset($data['original_size'])) {
            $this->line("Original size: " . $this->formatBytes($data['original_size']));
        }

        if (isset($data['compressed_size'])) {
            $this->line("Compressed size: " . $this->formatBytes($data['compressed_size']));
        }

        if (isset($data['savings_percent'])) {
            $this->line("Savings: {$data['savings_percent']}%");
        }

        if ($output && isset($data['download_url'])) {
            $this->info("Downloading to: {$output}");

            $saved = OctoSqueeze::downloadAndSave($data['download_url'], $output, $disk);

            if ($saved) {
                $this->info("File saved successfully!");
            } else {
                $this->error("Failed to save file");
                return self::FAILURE;
            }
        } elseif (isset($data['download_url'])) {
            $this->line("Download URL: {$data['download_url']}");
        }

        return self::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
