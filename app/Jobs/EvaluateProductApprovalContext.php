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

    public ?string $contextHash = null;

    public ?string $expectedContentFingerprint = null;

    public ?string $expectedContextHash = null;

    public function __construct(
        public readonly int $productId,
        public readonly int $moderationVersion,
        ?string $expectedContentFingerprint = null,
        ?string $expectedContextHash = null,
    ) {
        $this->contextHash = $expectedContextHash;
        $this->expectedContentFingerprint = $expectedContentFingerprint;
        $this->expectedContextHash = $expectedContextHash;
    }

    public function uniqueId(): string
    {
        return implode(':', [
            $this->productId,
            $this->moderationVersion,
            $this->expectedContentFingerprint ?? 'missing-content',
            $this->expectedContextHash ?? 'missing-context',
        ]);
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
            $this->expectedContentFingerprint,
            $this->expectedContextHash,
        );
    }
}
