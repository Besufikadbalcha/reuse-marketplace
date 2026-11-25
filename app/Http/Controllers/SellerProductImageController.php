<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SellerProductImageController extends Controller
{
    public function show(Request $request, Product $product, ProductImage $image): BinaryFileResponse
    {
        abort_unless($product->seller_id === $request->user()->id, 404);
        abort_unless($image->product_id === $product->id, 404);
        abort_unless($image->isServableForSeller((int) $product->seller_id), 404);

        $disk = $image->servingDisk();

        abort_unless($disk !== null, 404);

        $response = response()->file(Storage::disk($disk)->path($image->path), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
