<?php

namespace OctoSqueeze\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OctoSqueeze\Laravel\OctoSqueezeManager;

/**
 * Compresses a file on a storage disk via OctoSqueeze API.
 *
 * Generic job for any Laravel app using cloud storage (R2, S3, etc.).
 * Downloads the file to a temp path, sends it to OctoSqueeze for compression,
 * and replaces the original if the compressed version is smaller.
 */
class CompressStorageFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public function __construct(
        protected string $disk,
        protected string $path,
        protected ?string $visibility = null,
    ) {}

    public function handle(OctoSqueezeManager $manager): void
    {
        $storage = Storage::disk($this->disk);

        if (!$storage->exists($this->path)) {
            return;
        }

        $extension = pathinfo($this->path, PATHINFO_EXTENSION);
        $tempPath = tempnam(sys_get_temp_dir(), 'octosqueeze_') . '.' . $extension;
        file_put_contents($tempPath, $storage->get($this->path));

        try {
            $originalSize = filesize($tempPath);

            $result = $manager->compress($tempPath, ['formats' => []]);

            if (!($result['state'] ?? false)) {
                Log::warning('[OctoSqueeze] Compression failed', [
                    'path' => $this->path,
                    'disk' => $this->disk,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
                return;
            }

            $downloadUrl = $result['data']['download_url'] ?? null;

            if (!$downloadUrl) {
                Log::warning('[OctoSqueeze] No download URL in response', [
                    'path' => $this->path,
                ]);
                return;
            }

            $compressedContent = $manager->download($downloadUrl);

            if ($compressedContent === null) {
                Log::warning('[OctoSqueeze] Failed to download compressed image', [
                    'path' => $this->path,
                    'download_url' => $downloadUrl,
                ]);
                return;
            }

            $newSize = strlen($compressedContent);

            if ($newSize < $originalSize) {
                $options = $this->visibility ? ['visibility' => $this->visibility] : [];
                $storage->put($this->path, $compressedContent, $options);
            }

            $savings = $originalSize > 0
                ? round((1 - $newSize / $originalSize) * 100, 1)
                : 0;

            Log::info('[OctoSqueeze] Image compressed', [
                'path' => $this->path,
                'disk' => $this->disk,
                'original_size' => $originalSize,
                'compressed_size' => $newSize,
                'savings' => $savings . '%',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[OctoSqueeze] Compression error', [
                'path' => $this->path,
                'disk' => $this->disk,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }
}
