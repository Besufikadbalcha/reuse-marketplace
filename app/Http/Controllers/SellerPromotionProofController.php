<?php

namespace App\Http\Controllers;

use App\Models\ProductPromotion;
use App\Models\PromotionPaymentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SellerPromotionProofController extends Controller
{
    public function show(Request $request, ProductPromotion $promotion, PromotionPaymentSubmission $paymentSubmission): BinaryFileResponse
    {
        abort_unless($promotion->seller_id === $request->user()->id, 404);
        abort_unless($paymentSubmission->promotion_id === $promotion->id, 404);

        $path = $paymentSubmission->proof_path;
        $pattern = sprintf(
            '/^promotion-payment-proofs\/%d\/%d\/attempt-%d\/[0-9a-f-]{36}\.(jpeg|jpg|png|webp|pdf)$/',
            (int) $request->user()->id,
            (int) $promotion->id,
            (int) $paymentSubmission->attempt
        );

        if (! is_string($path) || preg_match($pattern, $path) !== 1) {
            abort(404);
        }

        $disk = Storage::disk('payment_proofs');

        if (! $disk->exists($path)) {
            abort(404);
        }

        $absolutePath = $disk->path($path);
        $checksum = hash_file('sha256', $absolutePath);

        if (! is_string($checksum) || ! hash_equals((string) $paymentSubmission->proof_checksum, $checksum)) {
            abort(404);
        }

        $fileName = 'payment-proof-attempt-'.$paymentSubmission->attempt.'.'.pathinfo($path, PATHINFO_EXTENSION);
        $response = response()->download($absolutePath, $fileName, ['X-Content-Type-Options' => 'nosniff']);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
