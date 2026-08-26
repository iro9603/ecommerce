<?php

namespace App\Observers;

use App\Jobs\ReevaluateProductsAfterReferenceChange;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use WeakMap;

class ProductReferenceModerationObserver
{
    /** @var WeakMap<Model, array{ids: list<int>, reason: string, operation: string}>|null */
    private static ?WeakMap $pendingChanges = null;

    public function updating(Model $reference): void
    {
        if ($this->pendingOperation($reference) === 'restored') {
            return;
        }

        $this->prepare($reference, 'updated');
    }

    public function updated(Model $reference): void
    {
        if ($this->pendingOperation($reference) === 'restored') {
            return;
        }

        $this->complete($reference);
    }

    public function deleting(Model $reference): void
    {
        $operation = method_exists($reference, 'isForceDeleting')
            && $reference->isForceDeleting()
                ? 'forceDeleted'
                : 'deleted';

        $this->prepare($reference, $operation);
    }

    public function deleted(Model $reference): void
    {
        if ($this->pendingOperation($reference) !== 'forceDeleted') {
            $this->complete($reference);
        }
    }

    public function forceDeleted(Model $reference): void
    {
        $this->complete($reference);
    }

    public function restoring(Model $reference): void
    {
        $this->prepare($reference, 'restored');
    }

    public function restored(Model $reference): void
    {
        $this->complete($reference);
    }

    private function prepare(Model $reference, string $operation): void
    {
        if ($this->pendingOperation($reference) !== null) {
            return;
        }

        $ids = $this->productIds($reference);
        $reason = class_basename($reference).' reference '.$operation.' after product submission.';

        $this->applyPublicationBarrier($ids, $reason);
        $this->changes()[$reference] = [
            'ids' => $ids,
            'reason' => $reason,
            'operation' => $operation,
        ];
    }

    private function complete(Model $reference): void
    {
        $changes = $this->changes();

        if (! isset($changes[$reference])) {
            return;
        }

        $change = $changes[$reference];
        unset($changes[$reference]);

        $this->applyPublicationBarrier($change['ids'], $change['reason']);

        collect($change['ids'])->chunk(250)->each(function ($chunk) use ($change): void {
            ReevaluateProductsAfterReferenceChange::dispatch(
                $chunk->values()->all(),
                $change['reason'],
            )->afterCommit();
        });
    }

    /** @return list<int> */
    private function productIds(Model $reference): array
    {
        $ids = match (true) {
            $reference instanceof Brand => Product::withTrashed()
                ->where('brand_id', $reference->getKey())
                ->pluck('id'),
            $reference instanceof Category => DB::table('category_product')
                ->where('category_id', $reference->getKey())
                ->pluck('product_id'),
            $reference instanceof Tag => DB::table('product_tag')
                ->where('tag_id', $reference->getKey())
                ->pluck('product_id'),
            default => collect(),
        };

        return $ids
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** @param list<int> $ids */
    private function applyPublicationBarrier(array $ids, string $reason): void
    {
        if ($ids === []) {
            return;
        }

        Product::withTrashed()
            ->whereIn('id', $ids)
            ->where('approved_status', Product::APPROVAL_APPROVED)
            ->update([
                'approved_status' => Product::APPROVAL_PENDING,
                'reviewed_version' => null,
                'approved_at' => null,
                'approved_by' => null,
                'moderation_reason' => $reason,
                'moderation_fingerprint' => null,
                'risk_level' => null,
                'risk_score' => null,
                'updated_at' => now(),
            ]);
    }

    private function pendingOperation(Model $reference): ?string
    {
        $changes = $this->changes();

        return isset($changes[$reference]) ? $changes[$reference]['operation'] : null;
    }

    /** @return WeakMap<Model, array{ids: list<int>, reason: string, operation: string}> */
    private function changes(): WeakMap
    {
        return self::$pendingChanges ??= new WeakMap();
    }
}
