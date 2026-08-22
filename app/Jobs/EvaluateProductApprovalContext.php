<?php

namespace App\Jobs;

use App\Services\ProductModerationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateProductApprovalContext implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $productId,
        public readonly int $moderationVersion,
        public readonly string $contextHash,
    ) {}

    public function uniqueId(): string
    {
        return $this->productId.':'.$this->moderationVersion.':'.$this->contextHash;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(ProductModerationService $moderation): void
    {
        $moderation->evaluatePending(
            $this->productId,
            $this->moderationVersion,
            $this->contextHash,
        );
    }
}
