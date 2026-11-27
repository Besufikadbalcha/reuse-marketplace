<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResubmitPromotionPaymentRequest;
use App\Http\Requests\StorePromotionRequest;
use App\Models\Product;
use App\Models\ProductPromotion;
use App\Models\PromotionPackage;
use App\Models\PromotionPaymentSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SellerPromotionController extends Controller
{
    public function create(Request $request, Product $product): View|RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        if ($this->hasOpenPromotion((int) $product->id)) {
            return redirect()->route('seller.products.index')
                ->with('error', __('This listing already has an open promotion request.'));
        }

        return view('seller.promotions.create', [
            'product' => $product,
            'packages' => PromotionPackage::query()
                ->active()
                ->whereIn('code', array_values(PromotionPackage::CODES))
                ->ordered()
                ->get(),
        ]);
    }

    public function store(StorePromotionRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        if ($this->hasOpenPromotion((int) $product->id)) {
            return back()->withInput()->with('error', __('This listing already has an open promotion request.'));
        }

        $package = $this->activePackage($request->integer('package_id'));
        $storedPath = null;

        try {
            $promotion = DB::transaction(function () use ($request, $product, $package, &$storedPath): ProductPromotion {
                $promotion = ProductPromotion::create([
                    'seller_id' => $request->user()->id,
                    'product_id' => $product->id,
                    'package_id' => $package->id,
                    'funding_type' => ProductPromotion::FUNDING_PAYMENT,
                    'status' => ProductPromotion::STATUS_PAYMENT_PENDING,
                    'open_product_id' => $product->id,
                    'package_code_snapshot' => $package->code,
                    'price_minor_snapshot' => $package->price_minor,
                    'duration_days_snapshot' => $package->duration_days,
                    'priority_snapshot' => PromotionPackage::priorityFor($package->code),
                ]);

                $proof = $this->storeProof(
                    $request->file('proof'),
                    (int) $request->user()->id,
                    (int) $promotion->id,
                    1
                );
                $storedPath = $proof['path'];

                PromotionPaymentSubmission::create([
                    'promotion_id' => $promotion->id,
                    'attempt' => 1,
                    'method' => $request->string('method')->toString(),
                    'transaction_reference' => $this->nullableString($request->input('transaction_reference')),
                    'transaction_details' => $request->string('transaction_details')->toString(),
                    'proof_path' => $proof['path'],
                    'proof_checksum' => $proof['checksum'],
                    'status' => PromotionPaymentSubmission::STATUS_PENDING,
                    'pending_promotion_id' => $promotion->id,
                    'submitted_at' => now(),
                ]);

                return $promotion;
            });
        } catch (QueryException $exception) {
            $this->deleteProof($storedPath);

            if ($this->isUniqueConstraintViolation($exception)) {
                return back()->withInput()->with('error', __('This promotion could not be submitted because it was updated elsewhere. Please try again.'));
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->deleteProof($storedPath);

            throw $exception;
        }

        return redirect()->route('seller.promotions.show', $promotion)
            ->with('status', __('Payment proof submitted for review.'))
            ->with('status_key', 'promotion_submitted');
    }

    public function index(Request $request): View
    {
        $promotions = ProductPromotion::query()
            ->forSeller((int) $request->user()->id)
            ->with([
                'product:id,title,slug,seller_id',
                'package:id,code,name',
                'paymentSubmissions' => fn (HasMany $query) => $query->latestFirst(),
            ])
            ->latest()
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('seller.promotions.index', ['promotions' => $promotions]);
    }

    public function show(Request $request, ProductPromotion $promotion): View
    {
        $this->authorizePromotion($request, $promotion);

        $promotion->load([
            'product:id,title,slug,seller_id,status,archived_at,seller_archived_at',
            'package:id,code,name',
            'paymentSubmissions' => fn (HasMany $query) => $query->latestFirst(),
        ]);

        return view('seller.promotions.show', [
            'promotion' => $promotion,
            'latestSubmission' => $promotion->paymentSubmissions->first(),
        ]);
    }

    public function resubmit(Request $request, ProductPromotion $promotion): View|RedirectResponse
    {
        $this->authorizePromotion($request, $promotion);

        if (! $this->canResubmit($request, $promotion)) {
            return redirect()->route('seller.promotions.show', $promotion)
                ->with('error', __('This payment submission can no longer be resubmitted.'));
        }

        return view('seller.promotions.resubmit', [
            'promotion' => $promotion,
            'latestSubmission' => $this->latestSubmission($promotion),
        ]);
    }

    public function storeResubmission(ResubmitPromotionPaymentRequest $request, ProductPromotion $promotion): RedirectResponse
    {
        $this->authorizePromotion($request, $promotion);

        if (! $this->canResubmit($request, $promotion)) {
            return redirect()->route('seller.promotions.show', $promotion)
                ->with('error', __('This payment submission can no longer be resubmitted.'));
        }

        $attempt = $this->nextAttempt($promotion);
        $storedPath = null;

        try {
            DB::transaction(function () use ($request, $promotion, $attempt, &$storedPath): void {
                $proof = $this->storeProof(
                    $request->file('proof'),
                    (int) $request->user()->id,
                    (int) $promotion->id,
                    $attempt
                );
                $storedPath = $proof['path'];

                PromotionPaymentSubmission::create([
                    'promotion_id' => $promotion->id,
                    'attempt' => $attempt,
                    'method' => $request->string('method')->toString(),
                    'transaction_reference' => $this->nullableString($request->input('transaction_reference')),
                    'transaction_details' => $request->string('transaction_details')->toString(),
                    'proof_path' => $proof['path'],
                    'proof_checksum' => $proof['checksum'],
                    'status' => PromotionPaymentSubmission::STATUS_PENDING,
                    'pending_promotion_id' => $promotion->id,
                    'submitted_at' => now(),
                ]);

                $promotion->update(['status' => ProductPromotion::STATUS_PAYMENT_PENDING]);
            });
        } catch (QueryException $exception) {
            $this->deleteProof($storedPath);

            if ($this->isUniqueConstraintViolation($exception)) {
                return back()->withInput()->with('error', __('This payment submission was updated elsewhere. Please review it and try again.'));
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->deleteProof($storedPath);

            throw $exception;
        }

        return redirect()->route('seller.promotions.show', $promotion)
            ->with('status', __('Corrected payment proof submitted for review.'))
            ->with('status_key', 'promotion_resubmitted');
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless(
            $product->seller_id === $request->user()->id
                && $product->status === Product::STATUS_APPROVED
                && Product::query()->whereKey($product->id)->notArchived()->exists(),
            404
        );
    }

    private function authorizePromotion(Request $request, ProductPromotion $promotion): void
    {
        abort_unless($promotion->seller_id === $request->user()->id, 404);

        abort_unless(
            Product::query()
                ->whereKey($promotion->product_id)
                ->where('seller_id', $request->user()->id)
                ->exists(),
            404
        );
    }

    private function hasOpenPromotion(int $productId): bool
    {
        return ProductPromotion::query()
            ->forProduct($productId)
            ->open()
            ->exists();
    }

    private function activePackage(int $packageId): PromotionPackage
    {
        return PromotionPackage::query()
            ->active()
            ->whereKey($packageId)
            ->whereIn('code', array_values(PromotionPackage::CODES))
            ->firstOrFail();
    }

    private function canResubmit(Request $request, ProductPromotion $promotion): bool
    {
        return $promotion->status === ProductPromotion::STATUS_PAYMENT_REJECTED
            && $this->latestSubmission($promotion)?->isRejected() === true
            && Product::query()
                ->whereKey($promotion->product_id)
                ->where('seller_id', $request->user()->id)
                ->where('status', Product::STATUS_APPROVED)
                ->notArchived()
                ->exists();
    }

    private function latestSubmission(ProductPromotion $promotion): ?PromotionPaymentSubmission
    {
        return PromotionPaymentSubmission::query()
            ->forPromotion((int) $promotion->id)
            ->latestFirst()
            ->first();
    }

    private function nextAttempt(ProductPromotion $promotion): int
    {
        return (int) PromotionPaymentSubmission::query()
            ->forPromotion((int) $promotion->id)
            ->max('attempt') + 1;
    }

    private function storeProof(UploadedFile $proof, int $sellerId, int $promotionId, int $attempt): array
    {
        $extension = $this->proofExtension($proof);
        $directory = sprintf('promotion-payment-proofs/%d/%d/attempt-%d', $sellerId, $promotionId, $attempt);
        $path = Storage::disk('payment_proofs')->putFileAs($directory, $proof, Str::uuid().'.'.$extension);

        if (! is_string($path)) {
            throw new RuntimeException('Unable to store the payment proof.');
        }

        $checksum = hash_file('sha256', Storage::disk('payment_proofs')->path($path));

        if (! is_string($checksum)) {
            Storage::disk('payment_proofs')->delete($path);

            throw new RuntimeException('Unable to checksum the payment proof.');
        }

        return [
            'path' => $path,
            'checksum' => $checksum,
        ];
    }

    private function proofExtension(UploadedFile $proof): string
    {
        return match ($proof->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => throw new RuntimeException('Unsupported payment proof MIME type.'),
        };
    }

    private function deleteProof(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk('payment_proofs')->delete($path);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23000';
    }
}
