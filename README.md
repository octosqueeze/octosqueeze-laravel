# OctoSqueeze for Laravel

Automatic image compression and WebP/AVIF conversion for Laravel applications.

## Features

- Simple facade for image compression
- Queue-based compression for large files
- Support for file uploads, URLs, and storage paths
- WebP and AVIF format conversion
- Artisan commands for CLI usage
- Full Laravel integration (config, queue, storage)

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- OctoSqueeze API key ([Get one free](https://octosqueeze.com))

## Installation

```bash
composer require octosqueeze/octosqueeze-laravel
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=octosqueeze-config
```

## Configuration

Add your API key to `.env`:

```env
OCTOSQUEEZE_API_KEY=your-api-key-here

# Optional settings
OCTOSQUEEZE_MODE=balanced           # size, balanced, or quality
OCTOSQUEEZE_AUTO_COMPRESS=true      # Auto-compress uploads
OCTOSQUEEZE_QUEUE=default           # Queue for async compression
OCTOSQUEEZE_DISK=public             # Storage disk for compressed files
```

## Usage

### Basic Compression

```php
use OctoSqueeze\Laravel\Facades\OctoSqueeze;

// Compress an uploaded file
$result = OctoSqueeze::compress($request->file('image'));

// Compress from URL
$result = OctoSqueeze::compress('https://example.com/image.jpg');

// Compress from storage path
$result = OctoSqueeze::compress('images/photo.jpg');

// With custom options
$result = OctoSqueeze::compress($file, [
    'mode' => 'size',
    'formats' => ['webp', 'avif'],
]);
```

### Queue-based Compression

```php
// Queue for background processing
OctoSqueeze::queue($request->file('image'));

// With options
OctoSqueeze::queue($file, [
    'mode' => 'quality',
    'formats' => ['webp'],
]);
```

### Batch Compression

```php
// Compress multiple URLs
$result = OctoSqueeze::compressUrls([
    'https://example.com/image1.jpg',
    'https://example.com/image2.jpg',
    [
        'url' => 'https://example.com/image3.jpg',
        'options' => ['mode' => 'size'],
    ],
]);
```

### Download and Save

```php
// Get the download URL from compression result
$downloadUrl = $result['data']['download_url'];

// Download and save to storage
OctoSqueeze::downloadAndSave($downloadUrl, 'compressed/image.webp');

// To specific disk
OctoSqueeze::downloadAndSave($downloadUrl, 'compressed/image.webp', 's3');
```

### Check Usage

```php
$usage = OctoSqueeze::usage();

// Returns:
// [
//     'state' => true,
//     'data' => [
//         'plan' => 'Pro',
//         'compressions_used' => 1234,
//         'compressions_limit' => 25000,
//         'api_calls_today' => 56,
//         'api_calls_limit' => 10000,
//         'bytes_saved' => 12345678,
//     ],
// ]
```

## Artisan Commands

```bash
# Compress a file
php artisan octosqueeze:compress /path/to/image.jpg

# Compress with options
php artisan octosqueeze:compress image.jpg --mode=size --format=webp --format=avif

# Save compressed output
php artisan octosqueeze:compress image.jpg --output=compressed/image.webp

# Check usage statistics
php artisan octosqueeze:usage
```

## Response Format

All compression methods return an array:

```php
// Success
[
    'state' => true,
    'data' => [
        'id' => 'comp_abc123',
        'original_size' => 2457600,
        'compressed_size' => 491520,
        'savings_bytes' => 1966080,
        'savings_percent' => 80,
        'format' => 'webp',
        'download_url' => 'https://app.octosqueeze.com/api/v1/download/...',
        'expires_at' => '2024-01-15T12:00:00Z',
    ],
]

// Error
[
    'state' => false,
    'error' => 'Error message',
    'code' => 400,
]
```

## Using with Models

Example: Auto-compress images on model save:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OctoSqueeze\Laravel\Facades\OctoSqueeze;

class Photo extends Model
{
    protected static function booted()
    {
        static::created(function ($photo) {
            // Queue compression after upload
            OctoSqueeze::queue($photo->path);
        });
    }
}
```

## License

MIT License. See LICENSE for details.
