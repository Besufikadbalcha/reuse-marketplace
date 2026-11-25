<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectPromotionPaymentRequest;
use App\Models\Product;
use App\Models\ProductPromotion;
use App\Models\PromotionPaymentSubmission;
use App\Notifications\PromotionPaymentApprovedNotification;
use App\Notifications\PromotionPaymentRejectedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPromotionController extends Controller
{
    public function index(): View
    {
        $promotions = ProductPromotion::query()
            ->awaitingPayment()
            ->whereHas('paymentSubmissions', fn ($query) => $query->pending())
            ->with([
                'seller:id,name',
                'product:id,title,slug,status,seller_id',
                'package:id,code,name',
                'paymentSubmissions' => fn (HasMany $query) => $query->pending()->latestFirst(),
            ])
            ->latest()
            ->orderByDesc('id')
            ->paginate(12);

        return view('admin.promotions.index', ['promotions' => $promotions]);
    }

    public function show(ProductPromotion $promotion): View
    {
        $promotion->load([
            'seller:id,name',
            'product:id,title,slug,status,seller_id',
            'package:id,code,name',
            'paymentSubmissions' => fn (HasMany $query) => $query
                ->with('reviewer:id,name')
                ->latestFirst(),
        ]);

        return view('admin.promotions.show', [
            'promotion' => $promotion,
            'pendingSubmission' => $promotion->paymentSubmissions->firstWhere(
                'status',
                PromotionPaymentSubmission::STATUS_PENDING,
            ),
        ]);
    }

    public function approve(Request $request, ProductPromotion $promotion, PromotionPaymentSubmission $paymentSubmission): RedirectResponse
    {
        if (! $this->isReviewable($promotion, $paymentSubmission)) {
            return back()->with('error', __('Only pending payments can be approved.'));
        }

        if (! $this->productCanBeActivated($promotion)) {
            return back()->with('error', __('This promotion cannot be activated because its listing is no longer approved.'));
        }

        try {
            $approved = DB::transaction(function () use ($request, $promotion, $paymentSubmission): array {
                $lockedPromotion = ProductPromotion::query()
                    ->whereKey($promotion->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedSubmission = PromotionPaymentSubmission::query()
                    ->whereKey($paymentSubmission->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($this->isReviewable($lockedPromotion, $lockedSubmission), 409, __('This payment submission was updated elsewhere. Please review it and try again.'));

                $lockedProduct = Product::query()
                    ->whereKey($lockedPromotion->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($this->productMatchesPromotion($lockedProduct, $lockedPromotion), 409, __('This payment submission was updated elsewhere. Please review it and try again.'));
                abort_unless($this->productIsApproved($lockedProduct), 409, __('This promotion cannot be activated because its listing is no longer approved.'));

                $now = now();
                $endsAt = $now->copy()->addSeconds((int) $lockedPromotion->duration_days_snapshot * 86400);

                $lockedSubmission->update([
                    'status' => PromotionPaymentSubmission::STATUS_APPROVED,
                    'pending_promotion_id' => null,
                    'approved_promotion_id' => $lockedPromotion->id,
                    'rejection_reason' => null,
                    'reviewed_by' => (int) $request->user()->id,
                    'reviewed_at' => $now,
                ]);

                $lockedPromotion->update([
                    'status' => ProductPromotion::STATUS_ACTIVE,
                    'starts_at' => $now,
                    'ends_at' => $endsAt,
                    'paused_at' => null,
                    'remaining_seconds' => null,
                    'stopped_at' => null,
                ]);

                $lockedPromotion->setRelation('product', $lockedProduct);

                return [$lockedPromotion, $lockedSubmission];
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                return back()->with('error', __('This payment submission was updated elsewhere. Please review it and try again.'));
            }

            throw $exception;
        }

        [$approvedPromotion, $approvedSubmission] = $approved;

        $approvedPromotion->seller->notify(new PromotionPaymentApprovedNotification($approvedPromotion, $approvedSubmission));

        return redirect()->route('admin.promotions.index')
            ->with('status', __('Payment approved and promotion activated for :title.', [
                'title' => $approvedPromotion->product?->title ?? __('Promotion request'),
            ]))
            ->with('status_key', 'promotion_payment_approved');
    }

    public function reject(RejectPromotionPaymentRequest $request, ProductPromotion $promotion, PromotionPaymentSubmission $paymentSubmission): RedirectResponse
    {
        if (! $this->isReviewable($promotion, $paymentSubmission)) {
            return back()->with('error', __('Only pending payments can be rejected.'));
        }

        $reason = $request->string('rejection_reason')->toString();

        try {
            $rejected = DB::transaction(function () use ($reason, $request, $promotion, $paymentSubmission): array {
                $lockedPromotion = ProductPromotion::query()
                    ->whereKey($promotion->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedSubmission = PromotionPaymentSubmission::query()
                    ->whereKey($paymentSubmission->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($this->isReviewable($lockedPromotion, $lockedSubmission), 409, __('This payment submission was updated elsewhere. Please review it and try again.'));

                $lockedProduct = Product::query()
                    ->whereKey($lockedPromotion->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($this->productMatchesPromotion($lockedProduct, $lockedPromotion), 409, __('This payment submission was updated elsewhere. Please review it and try again.'));

                $now = now();

                $lockedSubmission->update([
                    'status' => PromotionPaymentSubmission::STATUS_REJECTED,
                    'pending_promotion_id' => null,
                    'approved_promotion_id' => null,
                    'rejection_reason' => $reason,
                    'reviewed_by' => (int) $request->user()->id,
                    'reviewed_at' => $now,
                ]);

                $lockedPromotion->update([
                    'status' => ProductPromotion::STATUS_PAYMENT_REJECTED,
                    'starts_at' => null,
                    'ends_at' => null,
                    'paused_at' => null,
                    'remaining_seconds' => null,
                    'stopped_at' => null,
                ]);

                $lockedPromotion->setRelation('product', $lockedProduct);

                return [$lockedPromotion, $lockedSubmission];
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                return back()->with('error', __('This payment submission was updated elsewhere. Please review it and try again.'));
            }

            throw $exception;
        }

        [$rejectedPromotion, $rejectedSubmission] = $rejected;

        $rejectedPromotion->seller->notify(new PromotionPaymentRejectedNotification($rejectedPromotion, $rejectedSubmission));

        return redirect()->route('admin.promotions.index')
            ->with('status', __('Payment rejected for :title.', [
                'title' => $rejectedPromotion->product?->title ?? __('Promotion request'),
            ]))
            ->with('status_key', 'promotion_payment_rejected');
    }

    private function isReviewable(ProductPromotion $promotion, PromotionPaymentSubmission $submission): bool
    {
        return $promotion->status === ProductPromotion::STATUS_PAYMENT_PENDING
            && $submission->isPending()
            && $submission->promotion_id === $promotion->id;
    }

    private function productCanBeActivated(ProductPromotion $promotion): bool
    {
        return Product::query()
            ->whereKey($promotion->product_id)
            ->where('seller_id', $promotion->seller_id)
            ->where('status', Product::STATUS_APPROVED)
            ->exists();
    }

    private function productMatchesPromotion(Product $product, ProductPromotion $promotion): bool
    {
        return $product->id === $promotion->product_id
            && $product->seller_id === $promotion->seller_id;
    }

    private function productIsApproved(Product $product): bool
    {
        return $product->status === Product::STATUS_APPROVED;
    }
}
