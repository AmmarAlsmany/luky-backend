<?php

namespace App\Jobs;

use App\Services\FCMService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFCMNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 30;
    public $tries = 3;
    public $backoff = 10;

    protected int $userId;
    protected string $title;
    protected string $body;
    protected array $data;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, string $title, string $body, array $data = [])
    {
        $this->userId = $userId;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(FCMService $fcmService): void
    {
        Log::info('Processing FCM notification job', [
            'user_id' => $this->userId,
            'title' => $this->title,
        ]);

        $fcmService->sendToUser($this->userId, $this->title, $this->body, $this->data);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FCM notification job failed', [
            'user_id' => $this->userId,
            'title' => $this->title,
            'error' => $exception->getMessage(),
        ]);
    }
}
