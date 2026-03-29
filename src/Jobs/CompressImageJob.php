<?php

namespace OctoSqueeze\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OctoSqueeze\Laravel\Facades\OctoSqueeze;

class CompressImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        protected string $path,
        protected array $options = [],
        protected ?string $disk = null,
        protected ?string $savePath = null,
    ) {}

    public function handle(): void
    {
        try {
            $result = OctoSqueeze::compress($this->path, $this->options);

            if (!$result['state']) {
                Log::error('OctoSqueeze compression failed', [
                    'path' => $this->path,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                throw new \Exception($result['error'] ?? 'Compression failed');
            }

            // If we have a download URL and save path, download and save
            if ($this->savePath && isset($result['data']['download_url'])) {
                $saved = OctoSqueeze::downloadAndSave(
                    $result['data']['download_url'],
                    $this->savePath,
                    $this->disk
                );

                if (!$saved) {
                    Log::error('OctoSqueeze: Failed to save compressed image', [
                        'path' => $this->savePath,
                    ]);
                }
            }

            Log::info('OctoSqueeze compression completed', [
                'path' => $this->path,
                'savings' => $result['data']['savings_percent'] ?? null,
            ]);
        } finally {
            // Clean up queued file if it was stored in the octosqueeze-queue directory
            if (str_contains($this->path, 'octosqueeze-queue')) {
                @unlink($this->path);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('OctoSqueeze job failed', [
            'path' => $this->path,
            'error' => $exception->getMessage(),
        ]);
    }
}
