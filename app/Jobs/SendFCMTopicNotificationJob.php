<?php

namespace App\Jobs;

use App\Services\FCMService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFCMTopicNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 30;
    public $tries = 3;
    public $backoff = 10;

    protected string $topic;
    protected string $title;
    protected string $body;
    protected array $data;

    /**
     * Create a new job instance.
     */
    public function __construct(string $topic, string $title, string $body, array $data = [])
    {
        $this->topic = $topic;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(FCMService $fcmService): void
    {
        Log::info('Processing FCM topic notification job', [
            'topic' => $this->topic,
            'title' => $this->title,
        ]);

        $fcmService->sendToTopic($this->topic, $this->title, $this->body, $this->data);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FCM topic notification job failed', [
            'topic' => $this->topic,
            'title' => $this->title,
            'error' => $exception->getMessage(),
        ]);
    }
}
