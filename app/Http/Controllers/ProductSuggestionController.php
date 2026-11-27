<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductSuggestionRequest;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ProductSuggestionController extends Controller
{
    public function __invoke(ProductSuggestionRequest $request): JsonResponse
    {
        $filters = $request->safe()->only(['q', 'category', 'location', 'condition', 'min_price', 'max_price']);

        $products = Product::query()
            ->availableForPurchase()
            ->select(['products.id', 'products.title', 'products.slug', 'products.price_minor', 'products.category_id'])
            ->with('category:id,name')
            ->when($filters['q'] !== '', fn (Builder $query): Builder => $query->searchTerms((string) $filters['q']))
            ->catalogFilters($filters, $request->locationFilterIsAvailable())
            ->rankBySearchTerms((string) $filters['q'])
            ->limit(6)
            ->get();

        return response()->json([
            'data' => $products->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'title' => (string) $product->title,
                'url' => route('products.show', $product->slug),
                'price' => $product->formattedPrice(),
                'category' => $product->category?->name,
            ])->all(),
        ]);
    }
}
