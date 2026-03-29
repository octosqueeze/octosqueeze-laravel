<?php

namespace OctoSqueeze\Laravel\Tests;

use PHPUnit\Framework\TestCase;
use OctoSqueeze\Laravel\Jobs\CompressStorageFileJob;

class CompressStorageFileJobTest extends TestCase
{
    public function test_job_has_two_tries(): void
    {
        $job = new CompressStorageFileJob('s3', 'images/photo.jpg');
        $this->assertSame(2, $job->tries);
    }

    public function test_job_stores_disk_and_path(): void
    {
        $job = new CompressStorageFileJob('r2', 'avatars/user-1.png', 'public');

        $reflection = new \ReflectionClass($job);

        $diskProp = $reflection->getProperty('disk');
        $diskProp->setAccessible(true);
        $this->assertSame('r2', $diskProp->getValue($job));

        $pathProp = $reflection->getProperty('path');
        $pathProp->setAccessible(true);
        $this->assertSame('avatars/user-1.png', $pathProp->getValue($job));

        $visProp = $reflection->getProperty('visibility');
        $visProp->setAccessible(true);
        $this->assertSame('public', $visProp->getValue($job));
    }

    public function test_job_visibility_defaults_to_null(): void
    {
        $job = new CompressStorageFileJob('local', 'test.jpg');

        $reflection = new \ReflectionClass($job);
        $visProp = $reflection->getProperty('visibility');
        $visProp->setAccessible(true);

        $this->assertNull($visProp->getValue($job));
    }

    public function test_job_is_serializable(): void
    {
        $job = new CompressStorageFileJob('s3', 'images/banner.webp', 'public');
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        $reflection = new \ReflectionClass($unserialized);

        $diskProp = $reflection->getProperty('disk');
        $diskProp->setAccessible(true);
        $this->assertSame('s3', $diskProp->getValue($unserialized));

        $pathProp = $reflection->getProperty('path');
        $pathProp->setAccessible(true);
        $this->assertSame('images/banner.webp', $pathProp->getValue($unserialized));

        $visProp = $reflection->getProperty('visibility');
        $visProp->setAccessible(true);
        $this->assertSame('public', $visProp->getValue($unserialized));
    }

    public function test_job_implements_should_queue(): void
    {
        $job = new CompressStorageFileJob('s3', 'test.jpg');
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }
}
