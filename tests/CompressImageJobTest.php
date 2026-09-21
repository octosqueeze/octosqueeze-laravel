<?php

namespace OctoSqueeze\Laravel\Tests;

use Illuminate\Support\Facades\Queue;
use OctoSqueeze\Laravel\Jobs\CompressImageJob;
use OctoSqueeze\Laravel\OctoSqueezeManager;
use OctoSqueeze\Laravel\OctoSqueezeServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * CompressImageJob, the job behind OctoSqueeze::queue(). A queued file must
 * survive until the job is done (a retry needs it), a failure that may already
 * have been compressed and billed must not be sent again, and queue() must be
 * able to say where the result goes.
 */
class CompressImageJobTest extends TestCase
{
    public array $saved = [];

    protected function getPackageProviders($app): array
    {
        return [OctoSqueezeServiceProvider::class];
    }

    /** The manager answers compress() with $result and records downloadAndSave(). */
    private function fakeManager(array $result): void
    {
        $test = $this;

        $this->app->instance(OctoSqueezeManager::class, new class($this->app, $result, $test) extends OctoSqueezeManager
        {
            public function __construct($app, private array $result, private $test)
            {
                parent::__construct($app);
            }

            public function compress($file, array $options = []): array
            {
                return $this->result;
            }

            public function downloadAndSave(string $url, string $path, ?string $disk = null): bool
            {
                $this->test->saved[] = [$url, $path, $disk];

                return true;
            }
        });
    }

    /** A copy the way queue() stores an upload: in the octosqueeze-queue directory. */
    private function queuedCopy(): string
    {
        $dir = sys_get_temp_dir().'/octosqueeze-queue';
        @mkdir($dir);
        $path = $dir.'/'.uniqid('upload_').'.jpg';
        file_put_contents($path, 'jpeg-bytes');

        return $path;
    }

    /**
     * The queue job the worker would hand the job (withFakeQueueInteractions()
     * only exists from Laravel 11; this package supports 10).
     */
    private function onQueue(CompressImageJob $job, bool $expectFail): CompressImageJob
    {
        $queueJob = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $expectFail
            ? $queueJob->shouldReceive('fail')->once()
            : $queueJob->shouldNotReceive('fail');
        $job->setJob($queueJob);

        return $job;
    }

    public function test_a_failure_that_is_safe_to_resend_is_retried_and_keeps_the_file(): void
    {
        $this->fakeManager(['state' => false, 'error' => 'Service unavailable', 'retryable' => true]);
        $path = $this->queuedCopy();
        $job = $this->onQueue(new CompressImageJob($path), expectFail: false);

        try {
            $job->handle();
            $this->fail('a retryable failure is thrown so the queue tries again');
        } catch (\Exception $e) {
            $this->assertSame('Service unavailable', $e->getMessage());
        }

        $this->assertFileExists($path, 'the next attempt needs the file');
        @unlink($path);
    }

    public function test_a_failure_that_may_have_been_billed_fails_the_job_without_a_retry(): void
    {
        $this->fakeManager(['state' => false, 'error' => 'Request to OctoSqueeze API failed: 0', 'retryable' => false]);
        $path = $this->queuedCopy();
        $job = $this->onQueue(new CompressImageJob($path), expectFail: true);

        $job->handle(); // does not throw, and fails the job: no retry

        // the queue calls failed() for a failed job; that removes the copy
        $job->failed(new \Exception('x'));
        $this->assertFileDoesNotExist($path);
    }

    public function test_an_old_client_without_the_flag_is_not_retried(): void
    {
        $this->fakeManager(['state' => false, 'error' => 'HTTP 504 error from API']);
        $path = $this->queuedCopy();
        $job = $this->onQueue(new CompressImageJob($path), expectFail: true);

        $job->handle();
        @unlink($path);
    }

    public function test_success_saves_the_result_and_removes_the_queued_copy(): void
    {
        $this->fakeManager(['state' => true, 'data' => ['download_url' => 'https://api.test/api/v1/download/job-1/signed', 'savings_percent' => 70]]);
        $path = $this->queuedCopy();
        $job = $this->onQueue(new CompressImageJob($path, [], 's3', 'images/out.jpg'), expectFail: false);

        $job->handle();
        $this->assertSame([['https://api.test/api/v1/download/job-1/signed', 'images/out.jpg', 's3']], $this->saved);
        $this->assertFileDoesNotExist($path);
    }

    public function test_queue_passes_where_the_result_should_be_saved(): void
    {
        Queue::fake();
        $manager = $this->app->make(OctoSqueezeManager::class);

        $manager->queue('/tmp/some/image.jpg', ['mode' => 'quality'], 'images/out.jpg', 's3');

        Queue::assertPushed(CompressImageJob::class, function (CompressImageJob $job) {
            $read = fn (string $name) => (fn () => $this->{$name})->call($job);

            return $read('path') === '/tmp/some/image.jpg'
                && $read('options') === ['mode' => 'quality']
                && $read('savePath') === 'images/out.jpg'
                && $read('disk') === 's3';
        });
    }

    public function test_queue_without_a_save_path_still_works_as_before(): void
    {
        Queue::fake();

        $this->app->make(OctoSqueezeManager::class)->queue('/tmp/some/image.jpg');

        Queue::assertPushed(CompressImageJob::class, fn (CompressImageJob $job) => (fn () => $this->savePath)->call($job) === null);
    }
}
