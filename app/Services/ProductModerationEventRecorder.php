<?php

namespace App\Services;

use App\Models\ProductApprovalReview;
use App\Models\ProductModerationEvent;
use Illuminate\Database\Eloquent\Model;

class ProductModerationEventRecorder
{
    /**
     * @param  array<string, mixed>|null  $riskResult
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ProductApprovalReview $review,
        string $eventType,
        ?Model $actor = null,
        ?string $reason = null,
        ?array $riskResult = null,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): ProductModerationEvent {
        $occurredAt = now();
        $eventKey = hash('sha256', $idempotencyKey ?? implode('|', [
            $review->getKey(),
            $eventType,
            $occurredAt->format('U.u'),
            bin2hex(random_bytes(16)),
        ]));

        return ProductModerationEvent::query()->firstOrCreate(
            ['event_key' => $eventKey],
            [
                'product_id' => $review->product_id,
                'product_approval_review_id' => $review->getKey(),
                'version' => (int) $review->version,
                'event_type' => $eventType,
                'source' => $review->source,
                'actor_type' => $actor?->getMorphClass(),
                'actor_id' => $actor?->getKey(),
                'content_hash' => $review->content_hash,
                'context_hash' => $review->context_hash,
                'snapshot' => $review->snapshot,
                'evaluation_context' => $review->evaluation_context,
                'risk_result' => $riskResult,
                'reason' => $this->cleanReason($reason),
                'metadata' => $metadata === [] ? null : $metadata,
                'occurred_at' => $occurredAt,
            ],
        );
    }

    private function cleanReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : mb_substr($reason, 0, 5000);
    }
}
