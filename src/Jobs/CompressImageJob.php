<?php

namespace OctoSqueeze\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
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
        $result = OctoSqueeze::compress($this->path, $this->options);

        if (! $result['state']) {
            Log::error('OctoSqueeze compression failed', [
                'path' => $this->path,
                'error' => $result['error'] ?? 'Unknown error',
            ]);

            $exception = new \Exception($result['error'] ?? 'Compression failed');

            // Retry only when sending it again cannot compress (and bill) the same
            // image twice: php-client says so in `retryable`. A timeout, a gateway
            // timeout or a monthly/daily limit fails the job now.
            if (($result['retryable'] ?? false) && $this->attempts() < $this->tries) {
                throw $exception;
            }

            $this->fail($exception);

            return;
        }

        // If we have a download URL and save path, download and save
        if ($this->savePath && isset($result['data']['download_url'])) {
            $saved = OctoSqueeze::downloadAndSave(
                $result['data']['download_url'],
                $this->savePath,
                $this->disk
            );

            if (! $saved) {
                Log::error('OctoSqueeze: Failed to save compressed image', [
                    'path' => $this->savePath,
                ]);
            }
        } elseif (! $this->savePath) {
            Log::warning('OctoSqueeze: compressed without a save path, the result was not kept', [
                'path' => $this->path,
            ]);
        }

        Log::info('OctoSqueeze compression completed', [
            'path' => $this->path,
            'savings' => $result['data']['savings_percent'] ?? null,
        ]);

        $this->removeQueuedCopy();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('OctoSqueeze job failed', [
            'path' => $this->path,
            'error' => $exception->getMessage(),
        ]);

        $this->removeQueuedCopy();
    }

    /**
     * The copy queue() stored for this job, removed once the job is done —
     * never between attempts, or a retry finds no file to send.
     */
    protected function removeQueuedCopy(): void
    {
        if (str_contains($this->path, 'octosqueeze-queue') && file_exists($this->path)) {
            @unlink($this->path);
        }
    }
}
