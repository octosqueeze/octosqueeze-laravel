<?php

namespace OctoSqueeze\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use OctoSqueeze\Laravel\OctoSqueezeManager;

/**
 * @method static \OctoSqueeze\Client\OctoSqueeze client()
 * @method static array compress(\Illuminate\Http\UploadedFile|string $file, array $options = [])
 * @method static array compressUrls(array $urls, array $options = [])
 * @method static void queue(\Illuminate\Http\UploadedFile|string $file, array $options = [])
 * @method static array usage()
 * @method static array status(string $jobId)
 * @method static string|null download(string $url)
 * @method static bool downloadAndSave(string $url, string $path, ?string $disk = null)
 *
 * @see \OctoSqueeze\Laravel\OctoSqueezeManager
 */
class OctoSqueeze extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OctoSqueezeManager::class;
    }
}
