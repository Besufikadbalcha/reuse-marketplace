<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductIndexRequest;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPromotion;
use App\Models\PromotionPackage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProductController extends Controller
{
    private const CONDITIONS = [
        Product::CONDITION_NEW => 'New',
        Product::CONDITION_LIKE_NEW => 'Like New',
        Product::CONDITION_GOOD => 'Good',
        Product::CONDITION_FAIR => 'Fair',
    ];

    private const HERO_PROMOTION_WEIGHTS = [
        PromotionPackage::PRIORITY_PREMIUM => 3,
        PromotionPackage::PRIORITY_STANDARD => 2,
        PromotionPackage::PRIORITY_BASIC => 1,
    ];

    public function index(ProductIndexRequest $request): View
    {
        return $this->catalog($request);
    }

    public function category(ProductIndexRequest $request, Category $category): View
    {
        abort_unless($category->is_active, 404);

        return $this->catalog($request, $category);
    }

    public function show(string $slug): View
    {
        $product = Product::query()
            ->publiclyVisible()
            ->with([
                'category',
                'location',
                'seller',
                'images' => fn (HasMany $query) => $query->orderBy('sort_order'),
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        $similarProducts = Product::query()
            ->availableForPurchase()
            ->whereKeyNot($product->id)
            ->with([
                'category',
                'seller',
                'images' => fn (HasMany $query) => $query->orderBy('sort_order'),
            ])
            ->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$product->category_id])
            ->orderByRaw('CASE WHEN condition = ? THEN 0 WHEN condition = ? THEN 1 WHEN condition = ? THEN 2 WHEN condition = ? THEN 3 ELSE 4 END', [
                Product::CONDITION_NEW,
                Product::CONDITION_LIKE_NEW,
                Product::CONDITION_GOOD,
                Product::CONDITION_FAIR,
            ])
            ->orderByRaw('ABS(price_minor - ?)', [$product->price_minor])
            ->orderBy('id')
            ->limit(4)
            ->get();

        $isOwnProduct = $product->isOwnedBy(auth()->user());

        return view('products.show', [
            'product' => $product,
            'canPurchase' => ! $isOwnProduct && Product::query()->availableForPurchase()->whereKey($product->id)->exists(),
            'isOwnProduct' => $isOwnProduct,
            'similarProducts' => $similarProducts,
        ]);
    }

    private function heroShowcaseItems(): array
    {
        $promotions = ProductPromotion::query()
            ->currentlyActive()
            ->with([
                'product' => fn (BelongsTo $query): Builder => $query->getQuery()->availableForPurchase(),
                'product.images' => fn (HasMany $query) => $query->orderBy('sort_order'),
            ])
            ->whereHas('product', function (Builder $query): void {
                $query->availableForPurchase()->whereHas('images');
            })
            ->orderByDesc('priority_snapshot')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();

        $items = $promotions
            ->map(function (ProductPromotion $promotion): array {
                $product = $promotion->product;

                return [
                    'id' => (int) $product->id,
                    'slug' => (string) $product->slug,
                    'title' => (string) $product->title,
                    'imageUrl' => (string) $product->images->first()?->url(),
                    'imageUrls' => $product->images
                        ->map(fn (ProductImage $image): string => (string) $image->url())
                        ->all(),
                    'price' => $product->formattedPrice(),
                    'condition' => $product->conditionLabel(),
                    'url' => route('products.show', $product->slug),
                    'priority' => (int) $promotion->priority_snapshot,
                ];
            })
            ->unique('id')
            ->values()
            ->all();

        return $this->weightedHeroRotation($items);
    }

    private function weightedHeroRotation(array $items): array
    {
        if (count($items) < 2) {
            return $items;
        }

        $weights = array_map(
            fn (array $item): int => self::HERO_PROMOTION_WEIGHTS[$item['priority']]
                ?? self::HERO_PROMOTION_WEIGHTS[PromotionPackage::PRIORITY_BASIC],
            $items,
        );
        $rotation = $items;

        for ($occurrence = 2; $occurrence <= max($weights); $occurrence++) {
            foreach ($items as $index => $item) {
                if ($weights[$index] >= $occurrence) {
                    $rotation[] = $item;
                }
            }
        }

        return $rotation;
    }

    private function facetCounts(array $filters, bool $locationFilterAvailable, Collection $categories, Collection $locations, ?Category $category): array
    {
        $rawCategoryCounts = $category === null
            ? $this->facetCountByColumn('category_id', 'category', $filters, $locationFilterAvailable)
            : collect();

        $rawConditionCounts = $this->facetCountByColumn('condition', 'condition', $filters, $locationFilterAvailable);
        $rawLocationCounts = $this->facetCountByColumn('location_id', 'location', $filters, $locationFilterAvailable);

        return [
            'categories' => $category === null
                ? $categories->mapWithKeys(fn (Category $categoryOption): array => [
                    $categoryOption->id => (int) $rawCategoryCounts->get($categoryOption->id, 0),
                ])
                : collect(),
            'conditions' => collect(self::CONDITIONS)->mapWithKeys(fn (string $label, string $value): array => [
                $value => (int) $rawConditionCounts->get($value, 0),
            ]),
            'locations' => $locations->mapWithKeys(fn (Location $locationOption): array => [
                $locationOption->id => (int) $rawLocationCounts->get($locationOption->id, 0),
            ]),
        ];
    }

    private function facetCountByColumn(string $column, string $excludedFacet, array $filters, bool $locationFilterAvailable): Collection
    {
        $facetFilters = $filters;
        unset($facetFilters[$excludedFacet]);

        $productsTable = (new Product)->getTable();
        $groupColumn = $productsTable.'.'.$column;

        return Product::query()
            ->availableForPurchase()
            ->when(($filters['q'] ?? '') !== '', fn (Builder $query): Builder => $query->searchTerms((string) $filters['q']))
            ->catalogFilters($facetFilters, $locationFilterAvailable)
            ->toBase()
            ->selectRaw($groupColumn.', count(*) as facet_count')
            ->groupBy($groupColumn)
            ->pluck('facet_count', $column);
    }

    private function effectiveSort(array $filters): string
    {
        $sort = (string) ($filters['sort'] ?? '');

        if ($sort === '') {
            return ($filters['q'] ?? '') !== '' ? ProductIndexRequest::SORT_RELEVANCE : ProductIndexRequest::SORT_LATEST;
        }

        if ($sort === ProductIndexRequest::SORT_RELEVANCE && ($filters['q'] ?? '') === '') {
            return ProductIndexRequest::SORT_LATEST;
        }

        return $sort;
    }

    private function applyTitleSort(Builder $query): Builder
    {
        $productsTable = $query->getModel()->getTable();
        $titleColumn = $query->getQuery()->getGrammar()->wrap($productsTable.'.title');

        return $query
            ->orderByRaw('lower('.$titleColumn.') asc')
            ->orderByDesc($productsTable.'.created_at')
            ->orderByDesc($productsTable.'.id');
    }

    private function applySort(Builder $query, array $filters, string $effectiveSort): Builder
    {
        if ($effectiveSort === ProductIndexRequest::SORT_RELEVANCE) {
            return $query->rankBySearchTerms((string) $filters['q']);
        }

        return match ($effectiveSort) {
            ProductIndexRequest::SORT_TITLE_ASC => $this->applyTitleSort($query),
            ProductIndexRequest::SORT_PRICE_ASC => $query->orderBy('price_minor')->orderByDesc('created_at')->orderByDesc('id'),
            ProductIndexRequest::SORT_PRICE_DESC => $query->orderByDesc('price_minor')->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->latest()->orderByDesc('id'),
        };
    }

    private function catalog(ProductIndexRequest $request, ?Category $category = null): View
    {
        $locationFilterAvailable = $request->locationFilterIsAvailable();
        $validatedFilters = $request->safe()->only(['q', 'category', 'location', 'condition', 'min_price', 'max_price', 'sort']);

        $filters = [
            'q' => $validatedFilters['q'] ?? '',
            'category' => $category?->id ?? $validatedFilters['category'] ?? '',
            'location' => $validatedFilters['location'] ?? '',
            'condition' => $validatedFilters['condition'] ?? '',
            'min_price' => $validatedFilters['min_price'] ?? '',
            'max_price' => $validatedFilters['max_price'] ?? '',
            'sort' => $validatedFilters['sort'] ?? '',
        ];

        $effectiveSort = $this->effectiveSort($filters);

        $isLandingPage = $category === null
            && $filters['q'] === ''
            && $filters['category'] === ''
            && $filters['location'] === ''
            && $filters['condition'] === ''
            && $filters['min_price'] === ''
            && $filters['max_price'] === '';

        $productsQuery = Product::query()
            ->availableForPurchase()
            ->with([
                'category',
                'seller',
                'images' => fn (HasMany $query) => $query->orderBy('sort_order'),
            ])
            ->when($filters['q'] !== '', fn (Builder $query): Builder => $query->searchTerms($filters['q']))
            ->catalogFilters($filters, $locationFilterAvailable);

        $productsQuery = $this->applySort($productsQuery, $filters, $effectiveSort);

        $products = $productsQuery->paginate(12)->withQueryString();

        $categories = Category::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $locations = $locationFilterAvailable
            ? Location::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $facetCounts = $this->facetCounts($filters, $locationFilterAvailable, $categories, $locations, $category);

        $filterQuery = array_filter([
            'q' => $filters['q'] !== '' ? $filters['q'] : null,
            'category' => $category === null && $filters['category'] !== '' ? $filters['category'] : null,
            'location' => $filters['location'] !== '' ? $filters['location'] : null,
            'condition' => $filters['condition'] !== '' ? $filters['condition'] : null,
            'min_price' => $filters['min_price'] !== '' ? $filters['min_price'] : null,
            'max_price' => $filters['max_price'] !== '' ? $filters['max_price'] : null,
            'sort' => $filters['sort'] !== '' && $effectiveSort !== ProductIndexRequest::SORT_LATEST ? $effectiveSort : null,
        ], fn ($value): bool => $value !== null && $value !== '');

        $activeFilters = collect($filterQuery)
            ->except('sort')
            ->map(function (mixed $value, string $key) use ($filterQuery, $categories, $category): array {
                $remainingFilters = $filterQuery;
                unset($remainingFilters[$key]);

                return [
                    'label' => match ($key) {
                        'q' => __('Search: :value', ['value' => $value]),
                        'category' => __('Category: :value', ['value' => $categories->firstWhere('id', $value)?->name ?? __('Unknown')]),
                        'location' => __('Location: :value', ['value' => $value]),
                        'condition' => __('Condition: :value', ['value' => __(self::CONDITIONS[$value] ?? (string) $value)]),
                        'min_price' => __('Min price: :value', ['value' => $value]),
                        'max_price' => __('Max price: :value', ['value' => $value]),
                    },
                    'url' => $category === null
                        ? route('home', $remainingFilters)
                        : route('categories.show', array_merge([$category], $remainingFilters)),
                ];
            })
            ->values();

        if ($category !== null) {
            $activeFilters->prepend([
                'label' => __('Category: :value', ['value' => $category->name]),
                'url' => route('home', $filterQuery),
            ]);
        }

        $clearFiltersUrl = $category !== null
            ? route('categories.show', $category)
            : route('home');

        $priceRangeQuery = $filterQuery;
        unset($priceRangeQuery['min_price'], $priceRangeQuery['max_price']);

        $clearPriceRangeUrl = $category !== null
            ? route('categories.show', array_merge([$category], $priceRangeQuery))
            : route('home', $priceRangeQuery);

        return view('products.index', [
            'products' => $products,
            'categories' => $categories,
            'locations' => $locations,
            'conditions' => array_map(fn (string $label): string => __($label), self::CONDITIONS),
            'categoryCounts' => $facetCounts['categories'],
            'conditionCounts' => $facetCounts['conditions'],
            'locationCounts' => $facetCounts['locations'],
            'filters' => [
                'q' => $filters['q'],
                'category' => $filters['category'],
                'location' => $filters['location'],
                'condition' => $filters['condition'],
                'min_price' => $filters['min_price'],
                'max_price' => $filters['max_price'],
                'sort' => $effectiveSort,
            ],
            'category' => $category,
            'formAction' => $clearFiltersUrl,
            'clearFiltersUrl' => $clearFiltersUrl,
            'clearPriceRangeUrl' => $clearPriceRangeUrl,
            'activeFilters' => $activeFilters,
            'isLandingPage' => $isLandingPage,
            'heroShowcaseItems' => $isLandingPage ? $this->heroShowcaseItems() : [],
        ]);
    }
}
