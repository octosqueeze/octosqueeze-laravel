<?php

namespace OctoSqueeze\Laravel\Tests;

use Illuminate\Support\Facades\Storage;
use OctoSqueeze\Laravel\Jobs\CompressStorageFileJob;
use OctoSqueeze\Laravel\OctoSqueezeManager;
use OctoSqueeze\Laravel\OctoSqueezeServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * Runs CompressStorageFileJob::handle() against a fake disk. The manager is
 * replaced so the test controls exactly what "the download" returns.
 */
class CompressStorageFileJobHandleTest extends TestCase
{
    private const PNG_1PX = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function getPackageProviders($app): array
    {
        return [OctoSqueezeServiceProvider::class];
    }

    private function runJobWithDownload(?string $downloaded): string
    {
        Storage::fake('r2');
        // A PNG with trailing padding: a valid image that is larger than the "compressed" one
        $original = base64_decode(self::PNG_1PX).str_repeat("\0", 4096);
        Storage::disk('r2')->put('avatars/1/a.png', $original);

        $manager = new class($this->app, $downloaded) extends OctoSqueezeManager
        {
            public function __construct($app, private ?string $downloaded)
            {
                parent::__construct($app);
            }

            public function compress($file, array $options = []): array
            {
                return ['state' => true, 'data' => ['download_url' => 'https://api.test/api/v1/download/job-1']];
            }

            public function download(string $url): ?string
            {
                return $this->downloaded;
            }
        };

        (new CompressStorageFileJob('r2', 'avatars/1/a.png', 'public'))->handle($manager);

        return Storage::disk('r2')->get('avatars/1/a.png');
    }

    public function test_it_replaces_the_file_with_a_smaller_compressed_image(): void
    {
        $compressed = base64_decode(self::PNG_1PX);

        $this->assertSame($compressed, $this->runJobWithDownload($compressed));
    }

    public function test_it_never_overwrites_the_file_with_an_html_page(): void
    {
        $page = '<!DOCTYPE html><html><head><title>Sign in</title></head><body>Sign in</body></html>';

        $stored = $this->runJobWithDownload($page);

        $this->assertStringStartsWith(base64_decode(self::PNG_1PX), $stored);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $stored);
    }

    public function test_it_never_overwrites_the_file_with_a_json_error(): void
    {
        $stored = $this->runJobWithDownload('{"success":false,"error":{"code":"unauthenticated"}}');

        $this->assertStringStartsWith(base64_decode(self::PNG_1PX), $stored);
    }

    public function test_it_keeps_the_file_when_the_download_failed(): void
    {
        $stored = $this->runJobWithDownload(null);

        $this->assertStringStartsWith(base64_decode(self::PNG_1PX), $stored);
    }

    public function test_looks_like_file_refuses_pages_and_accepts_images(): void
    {
        $this->assertFalse(OctoSqueezeManager::looksLikeFile(''));
        $this->assertFalse(OctoSqueezeManager::looksLikeFile('<!DOCTYPE html><html></html>'));
        $this->assertFalse(OctoSqueezeManager::looksLikeFile('{"message":"Unauthenticated."}'));
        $this->assertTrue(OctoSqueezeManager::looksLikeFile(base64_decode(self::PNG_1PX)));
        $this->assertTrue(OctoSqueezeManager::looksLikeFile(random_bytes(64)));
    }
}
