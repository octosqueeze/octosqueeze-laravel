<?php

namespace OctoSqueeze\Laravel\Tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Http\UploadedFile;
use OctoSqueeze\Client\OctoSqueeze as OctoSqueezeClient;
use OctoSqueeze\Laravel\OctoSqueezeManager;

/**
 * Testable subclass of OctoSqueezeManager that allows injecting a fake client
 * without needing to mock the final OctoSqueezeClient class.
 */
class TestableManager extends OctoSqueezeManager
{
    private ?OctoSqueezeClient $fakeClient = null;
    public array $lastCompressArgs = [];

    public function setFakeClient(OctoSqueezeClient $client): void
    {
        $this->fakeClient = $client;
    }

    public function client(): OctoSqueezeClient
    {
        if ($this->fakeClient !== null) {
            return $this->fakeClient;
        }

        return parent::client();
    }
}

/**
 * Minimal Application stub for unit tests.
 *
 * Implements ArrayAccess (needed for $app['config'] usage in OctoSqueezeManager)
 * plus the full Application interface contract.
 */
class FakeApp implements \Illuminate\Contracts\Foundation\Application, \ArrayAccess
{
    public function __construct(private array $config) {}

    // -- ArrayAccess: handles $app['config'] --

    public function offsetExists(mixed $offset): bool
    {
        return $offset === 'config';
    }

    public function offsetGet(mixed $offset): mixed
    {
        if ($offset === 'config') {
            return new class($this->config) implements \ArrayAccess {
                public function __construct(private array $config) {}

                public function offsetExists(mixed $offset): bool
                {
                    // 'octosqueeze' => whole config array exists
                    if ($offset === 'octosqueeze') {
                        return true;
                    }
                    // 'octosqueeze.disk' => dot-notation lookup
                    if (str_contains($offset, '.')) {
                        [, $key] = explode('.', $offset, 2);
                        return isset($this->config[$key]);
                    }
                    return false;
                }

                public function offsetGet(mixed $offset): mixed
                {
                    // $app['config']['octosqueeze'] => return full config array
                    if ($offset === 'octosqueeze') {
                        return $this->config;
                    }
                    // $app['config']['octosqueeze.disk'] => dot-notation lookup
                    if (str_contains($offset, '.')) {
                        [, $key] = explode('.', $offset, 2);
                        return $this->config[$key] ?? null;
                    }
                    return null;
                }

                public function offsetSet(mixed $offset, mixed $value): void {}
                public function offsetUnset(mixed $offset): void {}
            };
        }
        return null;
    }

    public function offsetSet(mixed $offset, mixed $value): void {}
    public function offsetUnset(mixed $offset): void {}

    // -- Container interface stubs --

    public function bound($abstract) { return false; }
    public function alias($abstract, $alias) {}
    public function tag($abstracts, $tags) {}
    public function tagged($tag) { return []; }
    public function bind($abstract, $concrete = null, $shared = false) {}
    public function bindIf($abstract, $concrete = null, $shared = false) {}
    public function singleton($abstract, $concrete = null) {}
    public function singletonIf($abstract, $concrete = null) {}
    public function scoped($abstract, $concrete = null) {}
    public function scopedIf($abstract, $concrete = null) {}
    public function extend($abstract, \Closure $closure) {}
    public function instance($abstract, $instance) { return $instance; }
    public function addContextualBinding($concrete, $abstract, $implementation) {}
    public function when($concrete) { return new class { public function needs($a) { return new class { public function give($b) {} }; } }; }
    public function factory($abstract) { return fn() => null; }
    public function flush() {}
    public function make($abstract, array $parameters = []) { return null; }
    public function call($callback, array $parameters = [], $defaultMethod = null) { return null; }
    public function resolved($abstract) { return false; }
    public function beforeResolving($abstract, ?\Closure $callback = null) {}
    public function resolving($abstract, ?\Closure $callback = null) {}
    public function afterResolving($abstract, ?\Closure $callback = null) {}
    public function bindMethod($method, $callback) {}
    public function get(string $id) { return null; }
    public function has(string $id): bool { return false; }

    // -- Application interface stubs --

    public function version() { return '12.0.0'; }
    public function basePath($path = '') { return '/tmp'; }
    public function bootstrapPath($path = '') { return '/tmp/bootstrap'; }
    public function configPath($path = '') { return '/tmp/config'; }
    public function databasePath($path = '') { return '/tmp/database'; }
    public function langPath($path = '') { return '/tmp/lang'; }
    public function publicPath($path = '') { return '/tmp/public'; }
    public function resourcePath($path = '') { return '/tmp/resources'; }
    public function storagePath($path = '') { return '/tmp/storage'; }
    public function environment(...$environments) { return 'testing'; }
    public function runningInConsole() { return true; }
    public function runningUnitTests() { return true; }
    public function hasDebugModeEnabled() { return false; }
    public function maintenanceMode() { return new class implements \Illuminate\Contracts\Foundation\MaintenanceMode { public function activate(array $payload): void {} public function deactivate(): void {} public function active(): bool { return false; } public function data(): array { return []; } }; }
    public function isDownForMaintenance() { return false; }
    public function registerConfiguredProviders() {}
    public function register($provider, $force = false) { return new class($this) extends \Illuminate\Support\ServiceProvider { public function register(): void {} }; }
    public function registerDeferredProvider($provider, $service = null) {}
    public function resolveProvider($provider) { return new class($this) extends \Illuminate\Support\ServiceProvider { public function register(): void {} }; }
    public function boot() {}
    public function booting($callback) {}
    public function booted($callback) {}
    public function bootstrapWith(array $bootstrappers) {}
    public function getLocale() { return 'en'; }
    public function getNamespace() { return 'App\\'; }
    public function getProviders($provider) { return []; }
    public function hasBeenBootstrapped() { return true; }
    public function loadDeferredProviders() {}
    public function setLocale($locale) {}
    public function shouldSkipMiddleware() { return false; }
    public function terminating($callback) { return $this; }
    public function terminate() {}
}

class OctoSqueezeManagerTest extends TestCase
{
    private array $config = [
        'api_key' => 'test-api-key-123',
        'endpoint' => 'https://api.octosqueeze.com/api/v1',
        'mode' => 'balanced',
        'formats' => ['webp', 'avif'],
        'hash_check' => true,
        'verify_ssl' => true,
        'disk' => 'public',
        'queue' => 'default',
    ];

    private function app(?array $overrides = null): FakeApp
    {
        return new FakeApp(
            $overrides ? array_merge($this->config, $overrides) : $this->config
        );
    }

    /**
     * Create a TestableManager with a mocked OctoSqueezeClient.
     * Since OctoSqueezeClient is not final in the dev version, we can use createMock.
     */
    private function managerWithMockClient(): array
    {
        $mockClient = $this->createMock(OctoSqueezeClient::class);
        $manager = new TestableManager($this->app());
        $manager->setFakeClient($mockClient);

        return [$manager, $mockClient];
    }

    // ---- Client creation tests ----

    public function test_client_creates_instance_with_api_key(): void
    {
        $manager = new OctoSqueezeManager($this->app());
        $client = $manager->client();

        $this->assertInstanceOf(OctoSqueezeClient::class, $client);
    }

    public function test_client_is_cached_as_singleton(): void
    {
        $manager = new OctoSqueezeManager($this->app());
        $client1 = $manager->client();
        $client2 = $manager->client();

        $this->assertSame($client1, $client2);
    }

    public function test_client_ssl_verification_disabled_when_config_is_false(): void
    {
        $manager = new OctoSqueezeManager($this->app(['verify_ssl' => false]));
        $client = $manager->client();

        // No exception = client created successfully with verify => false
        $this->assertInstanceOf(OctoSqueezeClient::class, $client);
    }

    public function test_client_with_custom_endpoint(): void
    {
        $manager = new OctoSqueezeManager($this->app([
            'endpoint' => 'https://custom.api.local/v2',
        ]));
        $client = $manager->client();

        $this->assertInstanceOf(OctoSqueezeClient::class, $client);
    }

    public function test_client_with_custom_mode_and_formats(): void
    {
        $manager = new OctoSqueezeManager($this->app([
            'mode' => 'quality',
            'formats' => ['webp'],
            'hash_check' => false,
        ]));
        $client = $manager->client();

        $this->assertInstanceOf(OctoSqueezeClient::class, $client);
    }

    // ---- compress() routing tests ----

    public function test_compress_with_url_delegates_to_client_compress_url(): void
    {
        [$manager, $mockClient] = $this->managerWithMockClient();

        $mockClient->expects($this->once())
            ->method('compressUrl')
            ->with('https://example.com/image.jpg', ['mode' => 'size'])
            ->willReturn(['state' => true, 'items' => []]);

        $result = $manager->compress('https://example.com/image.jpg', ['mode' => 'size']);

        $this->assertTrue($result['state']);
    }

    public function test_compress_with_existing_file_path_delegates_to_client_compress_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'octosqueeze_test_');

        try {
            [$manager, $mockClient] = $this->managerWithMockClient();

            $mockClient->expects($this->once())
                ->method('compressFile')
                ->with($tempFile, [])
                ->willReturn(['state' => true, 'data' => []]);

            $result = $manager->compress($tempFile);

            $this->assertTrue($result['state']);
        } finally {
            @unlink($tempFile);
        }
    }

    public function test_compress_with_uploaded_file_delegates_to_client_compress_file(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'octosqueeze_test_');

        try {
            $uploadedFile = $this->createMock(UploadedFile::class);
            $uploadedFile->method('getRealPath')->willReturn($tempFile);

            [$manager, $mockClient] = $this->managerWithMockClient();

            $mockClient->expects($this->once())
                ->method('compressFile')
                ->with($tempFile, ['mode' => 'quality'])
                ->willReturn(['state' => true, 'data' => []]);

            $result = $manager->compress($uploadedFile, ['mode' => 'quality']);

            $this->assertTrue($result['state']);
        } finally {
            @unlink($tempFile);
        }
    }

    public function test_compress_with_url_failure_returns_error(): void
    {
        [$manager, $mockClient] = $this->managerWithMockClient();

        $mockClient->expects($this->once())
            ->method('compressUrl')
            ->willReturn(['state' => false, 'error' => 'Not found']);

        $result = $manager->compress('https://example.com/missing.jpg');

        $this->assertFalse($result['state']);
        $this->assertSame('Not found', $result['error']);
    }

    // ---- compressUrls() tests ----

    public function test_compress_urls_with_string_urls(): void
    {
        [$manager, $mockClient] = $this->managerWithMockClient();

        $mockClient->expects($this->once())
            ->method('squeezeUrl')
            ->with($this->callback(function (array $items) {
                return count($items) === 2
                    && $items[0]['url'] === 'https://example.com/a.jpg'
                    && $items[0]['options'] === ['mode' => 'size']
                    && $items[1]['url'] === 'https://example.com/b.jpg'
                    && $items[1]['options'] === ['mode' => 'size'];
            }))
            ->willReturn(['state' => true, 'items' => []]);

        $result = $manager->compressUrls(
            ['https://example.com/a.jpg', 'https://example.com/b.jpg'],
            ['mode' => 'size']
        );

        $this->assertTrue($result['state']);
    }

    public function test_compress_urls_with_array_items_merges_options(): void
    {
        [$manager, $mockClient] = $this->managerWithMockClient();

        $mockClient->expects($this->once())
            ->method('squeezeUrl')
            ->with($this->callback(function (array $items) {
                return count($items) === 1
                    && $items[0]['url'] === 'https://example.com/c.jpg'
                    && $items[0]['options'] === ['mode' => 'balanced'];
            }))
            ->willReturn(['state' => true, 'items' => []]);

        $result = $manager->compressUrls(
            [['url' => 'https://example.com/c.jpg']],
            ['mode' => 'balanced']
        );

        $this->assertTrue($result['state']);
    }

    public function test_compress_urls_with_empty_array(): void
    {
        [$manager, $mockClient] = $this->managerWithMockClient();

        $mockClient->expects($this->once())
            ->method('squeezeUrl')
            ->with([])
            ->willReturn(['state' => true, 'items' => []]);

        $result = $manager->compressUrls([]);

        $this->assertTrue($result['state']);
    }

    public function test_compress_urls_preserves_extra_keys_in_array_items(): void
    {
        [$manager, $mockClient] = $this->managerWithMockClient();

        $mockClient->expects($this->once())
            ->method('squeezeUrl')
            ->with($this->callback(function (array $items) {
                return $items[0]['url'] === 'https://example.com/d.jpg'
                    && $items[0]['hash'] === 'abc123'
                    && $items[0]['image_id'] === 42;
            }))
            ->willReturn(['state' => true, 'items' => []]);

        $result = $manager->compressUrls(
            [['url' => 'https://example.com/d.jpg', 'hash' => 'abc123', 'image_id' => 42]]
        );

        $this->assertTrue($result['state']);
    }

    // ---- usage() test ----

    public function test_usage_delegates_to_client_get_usage(): void
    {
        $expected = [
            'state' => true,
            'data' => [
                'plan' => 'pro',
                'compressions_used' => 150,
                'compressions_limit' => 1000,
            ],
        ];

        [$manager, $mockClient] = $this->managerWithMockClient();

        $mockClient->expects($this->once())
            ->method('getUsage')
            ->willReturn($expected);

        $result = $manager->usage();

        $this->assertSame($expected, $result);
    }

    // ---- status() test ----

    public function test_status_delegates_to_client_get_status(): void
    {
        $expected = [
            'state' => true,
            'data' => ['status' => 'completed'],
        ];

        [$manager, $mockClient] = $this->managerWithMockClient();

        $mockClient->expects($this->once())
            ->method('getStatus')
            ->with('job-abc-123')
            ->willReturn($expected);

        $result = $manager->status('job-abc-123');

        $this->assertSame($expected, $result);
    }

    // ---- download() test ----

    public function test_download_delegates_to_client_download_raw(): void
    {
        [$manager, $mockClient] = $this->managerWithMockClient();

        $mockClient->expects($this->once())
            ->method('downloadRaw')
            ->with('https://cdn.octosqueeze.com/compressed/abc.jpg')
            ->willReturn('binary-image-data');

        $result = $manager->download('https://cdn.octosqueeze.com/compressed/abc.jpg');

        $this->assertSame('binary-image-data', $result);
    }

    public function test_download_returns_null_when_client_fails(): void
    {
        [$manager, $mockClient] = $this->managerWithMockClient();

        $mockClient->expects($this->once())
            ->method('downloadRaw')
            ->willReturn(null);

        $result = $manager->download('https://cdn.octosqueeze.com/compressed/missing.jpg');

        $this->assertNull($result);
    }

    // ---- downloadAndSave() test ----

    public function test_download_and_save_returns_false_when_download_fails(): void
    {
        [$manager, $mockClient] = $this->managerWithMockClient();

        $mockClient->expects($this->once())
            ->method('downloadRaw')
            ->willReturn(null);

        $result = $manager->downloadAndSave(
            'https://cdn.octosqueeze.com/compressed/missing.jpg',
            'images/output.jpg'
        );

        $this->assertFalse($result);
    }

    // ---- Structural / integration tests ----

    public function test_manager_accepts_application_instance(): void
    {
        $manager = new OctoSqueezeManager($this->app());
        $this->assertInstanceOf(OctoSqueezeManager::class, $manager);
    }

    public function test_manager_public_api_methods_exist(): void
    {
        $manager = new OctoSqueezeManager($this->app());

        $this->assertTrue(method_exists($manager, 'client'));
        $this->assertTrue(method_exists($manager, 'compress'));
        $this->assertTrue(method_exists($manager, 'compressUrls'));
        $this->assertTrue(method_exists($manager, 'queue'));
        $this->assertTrue(method_exists($manager, 'usage'));
        $this->assertTrue(method_exists($manager, 'status'));
        $this->assertTrue(method_exists($manager, 'download'));
        $this->assertTrue(method_exists($manager, 'downloadAndSave'));
    }
}
