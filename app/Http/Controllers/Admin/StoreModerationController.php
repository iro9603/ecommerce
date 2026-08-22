<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Store;
use App\Services\AlertService;
use App\Services\StoreModerationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;

class StoreModerationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:Store Management')];
    }

    public function index(Request $request): View
    {
        $stores = Store::query()
            ->with(['seller.kyc', 'approver'])
            ->when(
                $request->query('status')
                    && in_array($request->query('status'), [
                        Store::STATUS_DRAFT,
                        Store::STATUS_PENDING,
                        Store::STATUS_APPROVED,
                        Store::STATUS_SUSPENDED,
                        Store::STATUS_REJECTED,
                    ], true),
                fn($query) => $query->where('status', $request->query('status')),
            )
            ->latest()
            ->paginate(25);

        return view('admin.store.index', compact('stores'));
    }

    public function show(Store $store): View
    {
        $store->load(['seller.kyc', 'approver', 'products', 'approvalReviews']);

        return view('admin.store.show', compact('store'));
    }

    public function approve(Request $request, Store $store, StoreModerationService $moderation)
    {
        $this->requireAdmin();
        $expectedVersion = $this->expectedVersion($request);

        try {
            $review = $moderation->approve($store, $this->admin(), $expectedVersion);
        } catch (\Throwable $exception) {
            AlertService::error($exception->getMessage());

            return back();
        }

        if ($review === null) {
            return $this->staleResponse($request);
        }

        $moderation->reevaluatePendingProducts($store, 'Store was approved by an administrator; pending products were re-evaluated.');

        AlertService::updated('Store approved.');

        return back();
    }

    public function reject(Request $request, Store $store, StoreModerationService $moderation)
    {
        $this->requireAdmin();

        $validated = $request->validate([
            'moderation_version' => ['required', 'integer', 'min:0'],
            'rejection_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $review = $moderation->reject(
            $store,
            $this->admin(),
            $validated['rejection_reason'],
            (int) $validated['moderation_version'],
        );

        if ($review === null) {
            return $this->staleResponse($request);
        }

        AlertService::updated('Store rejected.');

        return back();
    }

    public function suspend(Request $request, Store $store, StoreModerationService $moderation)
    {
        $this->requireAdmin();

        $validated = $request->validate([
            'moderation_version' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $review = $moderation->suspend(
            $store,
            $this->admin(),
            $validated['reason'],
            (int) $validated['moderation_version'],
        );

        if ($review === null) {
            return $this->staleResponse($request);
        }

        AlertService::updated('Store suspended.');

        return back();
    }

    public function restore(Request $request, Store $store, StoreModerationService $moderation)
    {
        $this->requireAdmin();
        $expectedVersion = $this->expectedVersion($request);

        try {
            $review = $moderation->restore($store, $this->admin(), $expectedVersion);
        } catch (\Throwable $exception) {
            AlertService::error($exception->getMessage());

            return back();
        }

        if ($review === null) {
            return $this->staleResponse($request);
        }

        $moderation->reevaluatePendingProducts($store, 'Store was restored by an administrator; pending products were re-evaluated.');

        AlertService::updated('Store restored.');

        return back();
    }

    private function expectedVersion(Request $request): int
    {
        $validated = $request->validate([
            'moderation_version' => ['required', 'integer', 'min:0'],
        ]);

        return (int) $validated['moderation_version'];
    }

    private function staleResponse(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Store information changed while you were reviewing it. Please review the latest version before making a decision.',
            ], 409);
        }

        return back()
            ->withErrors([
                'moderation_version' => 'Store information changed while you were reviewing it. Please review the latest version before making a decision.',
            ])
            ->withInput();
    }

    private function requireAdmin(): void
    {
        abort_unless($this->admin() instanceof Admin, 401);
    }

    private function admin(): ?Admin
    {
        return Auth::guard('admin')->user();
    }
}
