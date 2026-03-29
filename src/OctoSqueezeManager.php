<?php

namespace OctoSqueeze\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OctoSqueeze\Client\OctoSqueeze as OctoSqueezeClient;
use OctoSqueeze\Laravel\Jobs\CompressImageJob;

class OctoSqueezeManager
{
    protected Application $app;
    protected ?OctoSqueezeClient $client = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function client(): OctoSqueezeClient
    {
        if ($this->client === null) {
            $config = $this->app['config']['octosqueeze'];

            $this->client = OctoSqueezeClient::client($config['api_key'])
                ->setEndpointUri($config['endpoint'])
                ->setOptions([
                    'mode' => $config['mode'],
                    'formats' => $config['formats'],
                    'hash_check' => $config['hash_check'],
                ]);

            if (!$config['verify_ssl']) {
                $this->client->setHttpClientConfig(['verify' => false]);
            }
        }

        return $this->client;
    }

    /**
     * Compress an uploaded file
     */
    public function compress(UploadedFile|string $file, array $options = []): array
    {
        if ($file instanceof UploadedFile) {
            return $this->client()->compressFile(
                $file->getRealPath(),
                $options
            );
        }

        // If it's a path string
        if (file_exists($file)) {
            return $this->client()->compressFile($file, $options);
        }

        // If it's a URL
        if (filter_var($file, FILTER_VALIDATE_URL)) {
            return $this->client()->compressUrl($file, $options);
        }

        // Try as storage path
        $disk = $this->app['config']['octosqueeze.disk'];
        $path = Storage::disk($disk)->path($file);

        if (file_exists($path)) {
            return $this->client()->compressFile($path, $options);
        }

        return [
            'state' => false,
            'error' => 'File not found: ' . $file,
        ];
    }

    /**
     * Compress multiple files from URLs
     */
    public function compressUrls(array $urls, array $options = []): array
    {
        $items = array_map(function ($url) use ($options) {
            if (is_array($url)) {
                return array_merge(['options' => $options], $url);
            }
            return [
                'url' => $url,
                'options' => $options,
            ];
        }, $urls);

        return $this->client()->squeezeUrl($items);
    }

    /**
     * Queue a file for compression
     */
    public function queue(UploadedFile|string $file, array $options = []): void
    {
        $queueConnection = $this->app['config']['octosqueeze.queue'];

        if ($file instanceof UploadedFile) {
            // Copy to persistent storage — temp files are deleted after the request
            $storedPath = $file->store('octosqueeze-queue', 'local');
            $path = Storage::disk('local')->path($storedPath);
        } else {
            $path = $file;
        }

        CompressImageJob::dispatch($path, $options)
            ->onQueue($queueConnection);
    }

    /**
     * Get usage statistics
     */
    public function usage(): array
    {
        return $this->client()->getUsage();
    }

    /**
     * Get compression status
     */
    public function status(string $jobId): array
    {
        return $this->client()->getStatus($jobId);
    }

    /**
     * Download compressed image
     */
    public function download(string $url): ?string
    {
        return $this->client()->downloadRaw($url);
    }

    /**
     * Download and save compressed image to storage
     */
    public function downloadAndSave(string $url, string $path, ?string $disk = null): bool
    {
        $content = $this->download($url);

        if ($content === null) {
            return false;
        }

        $disk = $disk ?? $this->app['config']['octosqueeze.disk'];

        return Storage::disk($disk)->put($path, $content);
    }
}
