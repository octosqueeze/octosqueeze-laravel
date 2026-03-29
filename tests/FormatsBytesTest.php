<?php

namespace OctoSqueeze\Laravel\Tests;

use PHPUnit\Framework\TestCase;
use OctoSqueeze\Laravel\Console\Commands\Concerns\FormatsBytes;

class FormatsBytesTest extends TestCase
{
    use FormatsBytes;

    public function test_formats_zero_bytes(): void
    {
        $this->assertSame('0 B', $this->formatBytes(0));
    }

    public function test_formats_bytes(): void
    {
        $this->assertSame('500 B', $this->formatBytes(500));
    }

    public function test_formats_one_kilobyte(): void
    {
        $this->assertSame('1 KB', $this->formatBytes(1024));
    }

    public function test_formats_fractional_kilobytes(): void
    {
        $this->assertSame('1.5 KB', $this->formatBytes(1536));
    }

    public function test_formats_one_megabyte(): void
    {
        $this->assertSame('1 MB', $this->formatBytes(1048576));
    }

    public function test_formats_fractional_megabytes(): void
    {
        // 2.5 MB = 2621440 bytes
        $this->assertSame('2.5 MB', $this->formatBytes(2621440));
    }

    public function test_formats_one_gigabyte(): void
    {
        $this->assertSame('1 GB', $this->formatBytes(1073741824));
    }

    public function test_formats_one_terabyte(): void
    {
        $this->assertSame('1 TB', $this->formatBytes(1099511627776));
    }

    public function test_formats_large_megabyte_value(): void
    {
        // 150 MB = 157286400 bytes
        $this->assertSame('150 MB', $this->formatBytes(157286400));
    }

    public function test_formats_small_kilobyte_value(): void
    {
        // 10 KB = 10240 bytes
        $this->assertSame('10 KB', $this->formatBytes(10240));
    }
}
