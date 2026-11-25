<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPromotion;
use App\Models\PromotionPackage;
use App\Models\PromotionPaymentSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2026-11-25 12:00:00');
});

function showcaseCategory(bool $active = true, string $name = 'Showcase Category'): Category
{
    return Category::factory()->create([
        'name' => $name,
        'is_active' => $active,
    ]);
}

function showcaseProduct(User $seller, Category $category, string $title, array $attributes = []): Product
{
    return Product::factory()
        ->for($seller, 'seller')
        ->for($category, 'category')
        ->approved()
        ->create(array_merge(['title' => $title], $attributes));
}

function showcaseImage(Product $product, int $position = 0, array $attributes = []): ProductImage
{
    $suffix = $position === 0 ? '' : '-'.$position;
    $path = 'seller-products/'.$product->seller_id.'/'.$product->id.$suffix.'.jpg';

    $image = ProductImage::factory()
        ->for($product, 'product')
        ->create(array_merge(['path' => $path], $attributes));

    Storage::disk('local')->put($path, 'showcase-image');

    return $image;
}

function showcasePackage(string $code): PromotionPackage
{
    $factory = PromotionPackage::factory();

    return match ($code) {
        PromotionPackage::CODE_STANDARD => $factory->standard()->create(),
        PromotionPackage::CODE_PREMIUM => $factory->premium()->create(),
        default => $factory->basic()->create(),
    };
}

function showcasePromotion(
    User $seller,
    Product $product,
    PromotionPackage $package,
    array $attributes = [],
    array $updates = [],
): ProductPromotion {
    $promotion = ProductPromotion::factory()
        ->for($seller, 'seller')
        ->for($product, 'product')
        ->for($package, 'package')
        ->active()
        ->create(array_merge([
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
        ], $attributes));

    if ($updates !== []) {
        $promotion->update($updates);
    }

    return $promotion->refresh();
}

function showcaseBoard(string $content): string
{
    preg_match('/<div class="mx-auto w-full" data-hero-showcase-carousel.*?data-hero-showcase-ticker-end/s', $content, $matches);

    return $matches[0] ?? '';
}

function showcaseAds(string $board): array
{
    preg_match_all('/data-hero-showcase-product-id="(\d+)"(.*?)(?=data-hero-showcase-product-id="\d+"|data-hero-showcase-ticker-end)/s', $board, $matches, PREG_SET_ORDER);

    return array_map(
        fn (array $match): array => ['id' => (int) $match[1], 'html' => $match[2]],
        $matches,
    );
}

test('falls back to the latest approved listing when no paid promotion is active', function () {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $product = showcaseProduct($seller, $category, 'Fallback Listing');
    $image = showcaseImage($product);

    $response = $this->get(route('home'))
        ->assertOk();

    expect($response['heroShowcaseItems'])->toBe([]);

    $response
        ->assertSee('alt="Fallback Listing"', false)
        ->assertSee(route('product-images.show', [$product->id, $image->id]), false);
});

test('shows an active promoted listing in the hero without changing the catalog', function () {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $promotedProduct = showcaseProduct($seller, $category, 'Promoted Vintage Lamp');
    $promotedImage = showcaseImage($promotedProduct);

    showcasePromotion($seller, $promotedProduct, showcasePackage(PromotionPackage::CODE_BASIC));

    $newestProduct = showcaseProduct($seller, $category, 'Newest Ordinary Listing');
    showcaseImage($newestProduct);

    $response = $this->get(route('home'))
        ->assertOk();

    expect($response['heroShowcaseItems'])->toHaveCount(1)
        ->and($response['heroShowcaseItems'][0]['id'])->toBe($promotedProduct->id)
        ->and($response['heroShowcaseItems'][0]['title'])->toBe('Promoted Vintage Lamp');

    $response
        ->assertSee('alt="Promoted Vintage Lamp"', false)
        ->assertSee(route('product-images.show', [$promotedProduct->id, $promotedImage->id]), false)
        ->assertSee('All products')
        ->assertSee('Newest Ordinary Listing');
});

test('the promoted hero card shows the product price condition and promotional label', function () {
    $seller = User::factory()->create(['name' => 'Showcase Seller']);
    $category = showcaseCategory();
    $promotedProduct = showcaseProduct($seller, $category, 'Priced Promotion Find', [
        'price_minor' => 123450,
        'condition' => Product::CONDITION_LIKE_NEW,
    ]);
    showcaseImage($promotedProduct);

    showcasePromotion($seller, $promotedProduct, showcasePackage(PromotionPackage::CODE_BASIC));

    $response = $this->get(route('home'))->assertOk();

    expect($response['heroShowcaseItems'][0]['price'])->toBe(Product::formatMoney(123450))
        ->and($response['heroShowcaseItems'][0]['condition'])->toBe('Like New')
        ->and($response['heroShowcaseItems'][0]['url'])->toBe(route('products.show', $promotedProduct->slug));

    $content = (string) $response->getContent();

    expect(substr_count($content, 'Featured Promotion'))->toBe(1);

    $response
        ->assertSee('Featured Promotion')
        ->assertSee(__('homepage.hero.visual_tag'))
        ->assertSee(Product::formatMoney(123450))
        ->assertSee('Like New')
        ->assertSee('relative h-60 overflow-hidden rounded-3xl bg-sage-100 dark:bg-forest-900 sm:h-64 lg:h-64', false)
        ->assertSee('data-hero-showcase-carousel', false)
        ->assertDontSee('text-center text-xs font-medium text-forest-700 dark:text-mint-100/80', false);
});

test('ignores non displayable promotions even with approved payment proof', function (string $status, ?int $startsOffset, ?int $endsOffset) {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $promotedProduct = showcaseProduct($seller, $category, 'Ineligible Promotion Listing');
    showcaseImage($promotedProduct);

    $promotion = showcasePromotion($seller, $promotedProduct, showcasePackage(PromotionPackage::CODE_BASIC), [
        'status' => $status,
        'starts_at' => $startsOffset === null ? null : now()->addMinutes($startsOffset),
        'ends_at' => $endsOffset === null ? null : now()->addMinutes($endsOffset),
    ]);

    PromotionPaymentSubmission::factory()
        ->for($promotion, 'promotion')
        ->approved()
        ->create();

    $fallbackProduct = showcaseProduct($seller, $category, 'Visible Fallback Listing');
    showcaseImage($fallbackProduct);

    $response = $this->get(route('home'))
        ->assertOk();

    expect($response['heroShowcaseItems'])->toBe([]);

    $response
        ->assertSee('alt="Visible Fallback Listing"', false)
        ->assertDontSee('alt="Ineligible Promotion Listing"', false);
})->with([
    'payment pending' => [ProductPromotion::STATUS_PAYMENT_PENDING, null, null],
    'payment rejected' => [ProductPromotion::STATUS_PAYMENT_REJECTED, null, null],
    'paused' => [ProductPromotion::STATUS_PAUSED, -60, 1440],
    'expired status' => [ProductPromotion::STATUS_EXPIRED, -1440, -60],
    'stopped after sale' => [ProductPromotion::STATUS_STOPPED_SOLD, -60, 1440],
    'starts in the future' => [ProductPromotion::STATUS_ACTIVE, 60, 1440],
    'ends at the current second' => [ProductPromotion::STATUS_ACTIVE, -60, 0],
]);

test('ignores promoted products that are not publicly visible', function (string $productStatus, bool $categoryActive) {
    $seller = User::factory()->create();
    $hiddenCategory = showcaseCategory($categoryActive, 'Hidden Showcase Category');
    $hiddenProduct = showcaseProduct($seller, $hiddenCategory, 'Hidden Promotion Listing', [
        'status' => $productStatus,
    ]);
    showcaseImage($hiddenProduct);

    showcasePromotion($seller, $hiddenProduct, showcasePackage(PromotionPackage::CODE_BASIC));

    $visibleCategory = showcaseCategory(true, 'Visible Showcase Category');
    $visibleProduct = showcaseProduct($seller, $visibleCategory, 'Visible Fallback Listing');
    $visibleImage = showcaseImage($visibleProduct);

    $response = $this->get(route('home'))
        ->assertOk();

    expect($response['heroShowcaseItems'])->toBe([]);

    $response
        ->assertSee('alt="Visible Fallback Listing"', false)
        ->assertSee(route('product-images.show', [$visibleProduct->id, $visibleImage->id]), false)
        ->assertDontSee('alt="Hidden Promotion Listing"', false);
})->with([
    'pending product' => [Product::STATUS_PENDING, true],
    'inactive category' => [Product::STATUS_APPROVED, false],
]);

test('uses the immutable priority snapshot instead of the current package priority', function () {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $premiumPackageProduct = showcaseProduct($seller, $category, 'Premium Package Basic Snapshot');
    $basicPackageProduct = showcaseProduct($seller, $category, 'Basic Package Premium Snapshot');

    foreach ([$premiumPackageProduct, $basicPackageProduct] as $product) {
        showcaseImage($product);
    }

    showcasePromotion(
        $seller,
        $premiumPackageProduct,
        showcasePackage(PromotionPackage::CODE_PREMIUM),
        [],
        ['priority_snapshot' => PromotionPackage::PRIORITY_BASIC],
    );
    showcasePromotion(
        $seller,
        $basicPackageProduct,
        showcasePackage(PromotionPackage::CODE_BASIC),
        [],
        ['priority_snapshot' => PromotionPackage::PRIORITY_PREMIUM],
    );

    $response = $this->get(route('home'))
        ->assertOk();

    $titles = array_column($response['heroShowcaseItems'], 'title');

    expect(array_slice($titles, 0, 2))->toBe(['Basic Package Premium Snapshot', 'Premium Package Basic Snapshot'])
        ->and(array_count_values($titles))->toBe([
            'Basic Package Premium Snapshot' => 3,
            'Premium Package Basic Snapshot' => 1,
        ]);
});

test('weights the repeating ticker sequence by immutable priority snapshot', function () {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $premiumProduct = showcaseProduct($seller, $category, 'Premium Find');
    $standardProduct = showcaseProduct($seller, $category, 'Standard Find');
    $basicProduct = showcaseProduct($seller, $category, 'Basic Find');

    foreach ([$premiumProduct, $standardProduct, $basicProduct] as $product) {
        showcaseImage($product);
    }

    showcasePromotion($seller, $premiumProduct, showcasePackage(PromotionPackage::CODE_PREMIUM));
    showcasePromotion($seller, $standardProduct, showcasePackage(PromotionPackage::CODE_STANDARD));
    showcasePromotion($seller, $basicProduct, showcasePackage(PromotionPackage::CODE_BASIC));

    $response = $this->get(route('home'))
        ->assertOk();

    $titles = array_column($response['heroShowcaseItems'], 'title');

    expect(array_slice($titles, 0, 3))->toBe(['Premium Find', 'Standard Find', 'Basic Find'])
        ->and(array_count_values($titles))->toBe([
            'Premium Find' => 3,
            'Standard Find' => 2,
            'Basic Find' => 1,
        ]);

    $response
        ->assertSee('data-hero-showcase-ticker', false)
        ->assertSee('hero-promotion-ticker__track', false);
});

test('removes carousel indicators and slide-by-slide behavior', function () {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $firstProduct = showcaseProduct($seller, $category, 'First Promoted Find');
    $secondProduct = showcaseProduct($seller, $category, 'Second Promoted Find');

    foreach ([$firstProduct, $secondProduct] as $product) {
        showcaseImage($product);
    }

    showcasePromotion($seller, $firstProduct, showcasePackage(PromotionPackage::CODE_PREMIUM));
    showcasePromotion($seller, $secondProduct, showcasePackage(PromotionPackage::CODE_BASIC));

    $response = $this->get(route('home'))->assertOk();

    $response
        ->assertSee('data-hero-showcase-carousel', false)
        ->assertSee('data-hero-showcase-ticker', false)
        ->assertSee('data-hero-showcase-ticker-sequence', false)
        ->assertSee('aria-roledescription="ticker"', false)
        ->assertDontSee('data-hero-showcase-previous', false)
        ->assertDontSee('data-hero-showcase-next', false)
        ->assertDontSee('data-hero-showcase-position', false)
        ->assertDontSee('data-hero-showcase-status', false)
        ->assertDontSee('data-hero-showcase-track', false)
        ->assertDontSee('data-hero-showcase-image-row', false)
        ->assertDontSee('data-hero-showcase-slide', false)
        ->assertDontSee('aria-live="polite"', false)
        ->assertDontSee('aria-roledescription="carousel"', false)
        ->assertDontSee('window.setInterval', false)
        ->assertDontSee('window.clearInterval', false)
        ->assertDontSee('hero-showcase:change', false)
        ->assertDontSee('track.style.transform', false)
        ->assertDontSee("direction: 'alternate'", false);
});

test('does not install carousel controls for a single promoted ad', function () {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $product = showcaseProduct($seller, $category, 'Only Promoted Item');
    showcaseImage($product);

    showcasePromotion($seller, $product, showcasePackage(PromotionPackage::CODE_BASIC));

    $response = $this->get(route('home'))
        ->assertOk();

    expect($response['heroShowcaseItems'])->toHaveCount(1);

    $response
        ->assertSee('alt="Only Promoted Item"', false)
        ->assertSee('data-hero-showcase-ticker', false)
        ->assertDontSee('data-hero-showcase-previous', false)
        ->assertDontSee('data-hero-showcase-next', false)
        ->assertDontSee('data-hero-showcase-position', false)
        ->assertDontSee('window.setInterval', false);
});

test('clicking a promoted product opens its public detail page with buying information', function () {
    $seller = User::factory()->create([
        'name' => 'Detail Seller',
        'email' => 'private-detail-seller@example.com',
    ]);
    $category = showcaseCategory();
    $product = showcaseProduct($seller, $category, 'Detailed Promotion Find', [
        'description' => 'A carefully described promotion item.',
        'price_minor' => 987650,
        'condition' => Product::CONDITION_GOOD,
    ]);
    $image = showcaseImage($product);

    showcasePromotion($seller, $product, showcasePackage(PromotionPackage::CODE_BASIC));

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-hero-showcase-ad', false)
        ->assertSee('href="'.route('products.show', $product->slug).'"', false);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee($product->title)
        ->assertSee('A carefully described promotion item.')
        ->assertSee(route('product-images.show', [$product->id, $image->id]), false)
        ->assertSee($product->formattedPrice())
        ->assertSee('Good')
        ->assertSee('Detail Seller')
        ->assertSee(route('checkout.create', $product), false)
        ->assertSee('Log in to buy now')
        ->assertDontSee('private-detail-seller@example.com');
});

test('keeps different promoted ad tiles proportional inside the hero media area', function () {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $firstProduct = showcaseProduct($seller, $category, 'First Proportional Find');
    $secondProduct = showcaseProduct($seller, $category, 'Second Proportional Find');

    foreach ([$firstProduct, $secondProduct] as $product) {
        showcaseImage($product);
    }

    showcasePromotion($seller, $firstProduct, showcasePackage(PromotionPackage::CODE_BASIC));
    showcasePromotion($seller, $secondProduct, showcasePackage(PromotionPackage::CODE_STANDARD));

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('class="mx-auto w-full" data-hero-showcase-carousel', false)
        ->assertSee('relative h-60 overflow-hidden rounded-3xl bg-sage-100 dark:bg-forest-900 sm:h-64 lg:h-64', false)
        ->assertSee('data-hero-showcase-ticker', false)
        ->assertSee('class="hero-promotion-ticker__track flex h-full w-max"', false)
        ->assertSee('class="flex h-full w-max shrink-0 gap-2 pr-2"', false)
        ->assertSee('class="relative block h-full w-64 shrink-0 overflow-hidden focus-ring"', false)
        ->assertSee('class="max-h-full max-w-full object-contain"', false)
        ->assertDontSee('max-w-sm" data-hero-showcase-carousel', false)
        ->assertDontSee('class="size-full object-cover"', false);
});

test('exposes every promoted product image in sort order', function () {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $product = showcaseProduct($seller, $category, 'Multi Image Find');

    $lastInSortOrder = showcaseImage($product, 0, ['sort_order' => 2]);
    $firstInSortOrder = showcaseImage($product, 1, ['sort_order' => 0]);
    $middleInSortOrder = showcaseImage($product, 2, ['sort_order' => 1]);

    showcasePromotion($seller, $product, showcasePackage(PromotionPackage::CODE_BASIC));

    $response = $this->get(route('home'))
        ->assertOk();

    expect($response['heroShowcaseItems'][0]['imageUrls'])->toBe([
        route('product-images.show', [$product->id, $firstInSortOrder->id]),
        route('product-images.show', [$product->id, $middleInSortOrder->id]),
        route('product-images.show', [$product->id, $lastInSortOrder->id]),
    ]);
});

test('renders different promoted ads side by side with their own details', function () {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $firstProduct = showcaseProduct($seller, $category, 'First Side By Side Find', [
        'price_minor' => 11100,
        'condition' => Product::CONDITION_LIKE_NEW,
    ]);
    $secondProduct = showcaseProduct($seller, $category, 'Second Side By Side Find', [
        'price_minor' => 22200,
        'condition' => Product::CONDITION_GOOD,
    ]);
    $thirdProduct = showcaseProduct($seller, $category, 'Third Side By Side Find', [
        'price_minor' => 33300,
        'condition' => Product::CONDITION_FAIR,
    ]);

    $products = [$firstProduct, $secondProduct, $thirdProduct];
    $productById = collect($products)->keyBy('id');
    $images = [];

    foreach ($products as $product) {
        $images[$product->id] = showcaseImage($product);
    }

    showcasePromotion($seller, $firstProduct, showcasePackage(PromotionPackage::CODE_PREMIUM));
    showcasePromotion($seller, $secondProduct, showcasePackage(PromotionPackage::CODE_STANDARD));
    showcasePromotion($seller, $thirdProduct, showcasePackage(PromotionPackage::CODE_BASIC));

    $response = $this->get(route('home'))
        ->assertOk();

    $board = showcaseBoard((string) $response->getContent());
    $ads = showcaseAds($board);
    $ids = array_column($ads, 'id');
    $sequenceLength = intdiv(count($ids), 2);
    $firstSequence = array_slice($ids, 0, $sequenceLength);

    expect($board)->not->toBeEmpty()
        ->and($ads)->toHaveCount(12)
        ->and($sequenceLength)->toBe(6)
        ->and(array_slice($firstSequence, 0, 3))->toBe([$firstProduct->id, $secondProduct->id, $thirdProduct->id])
        ->and(array_count_values($firstSequence))->toBe([
            $firstProduct->id => 3,
            $secondProduct->id => 2,
            $thirdProduct->id => 1,
        ])
        ->and(substr_count($board, 'data-hero-showcase-photo'))->toBe(12)
        ->and(substr_count($board, 'Sponsored'))->toBe(12);

    foreach ($ads as $ad) {
        $product = $productById->get($ad['id']);
        $imageUrl = route('product-images.show', [$product->id, $images[$product->id]->id]);

        expect($ad['html'])->toContain('src="'.$imageUrl.'"')
            ->and($ad['html'])->toContain($product->title)
            ->and($ad['html'])->toContain($product->formattedPrice())
            ->and($ad['html'])->toContain($product->conditionLabel())
            ->and($ad['html'])->toContain('Sponsored')
            ->and($ad['html'])->toContain('href="'.route('products.show', $product->slug).'"');

        foreach ($images as $otherProductId => $otherImage) {
            if ($otherProductId === $product->id) {
                continue;
            }

            expect($ad['html'])->not->toContain('src="'.route('product-images.show', [$otherProductId, $otherImage->id]).'"');
        }
    }

    $response
        ->assertSee('class="flex h-full w-max shrink-0 gap-2 pr-2"', false)
        ->assertSee('class="relative block h-full w-64 shrink-0 overflow-hidden focus-ring"', false)
        ->assertSee('class="max-h-full max-w-full object-contain"', false);
});

test('moves all ads continuously in one direction and loops seamlessly', function () {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $products = [];

    foreach (['First Ticketing Find', 'Second Ticketing Find', 'Third Ticketing Find'] as $title) {
        $product = showcaseProduct($seller, $category, $title);
        showcaseImage($product);
        $products[] = $product;
    }

    showcasePromotion($seller, $products[0], showcasePackage(PromotionPackage::CODE_PREMIUM));
    showcasePromotion($seller, $products[1], showcasePackage(PromotionPackage::CODE_STANDARD));
    showcasePromotion($seller, $products[2], showcasePackage(PromotionPackage::CODE_BASIC));

    $response = $this->get(route('home'))
        ->assertOk();

    $ids = array_column(showcaseAds(showcaseBoard((string) $response->getContent())), 'id');
    $sequenceLength = intdiv(count($ids), 2);

    expect($ids)->toHaveCount(12)
        ->and(array_slice($ids, 0, $sequenceLength))->toBe(array_slice($ids, $sequenceLength));

    $response
        ->assertSee('class="hero-promotion-ticker__track flex h-full w-max"', false)
        ->assertSee('data-hero-showcase-ticker-sequence', false)
        ->assertSee('style="animation-duration: 54s"', false)
        ->assertDontSee("direction: 'alternate'", false)
        ->assertDontSee('row.animate(', false)
        ->assertDontSee('hero-showcase:change', false);

    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('@keyframes hero-promotion-ticker')
        ->toContain('transform: translateX(0);')
        ->toContain('transform: translateX(-50%);')
        ->toContain('animation: hero-promotion-ticker 60s linear infinite;');
});

test('stops the continuous ticker animation when reduced motion is preferred', function () {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $product = showcaseProduct($seller, $category, 'Reduced Motion Find');
    showcaseImage($product);

    showcasePromotion($seller, $product, showcasePackage(PromotionPackage::CODE_BASIC));

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-hero-showcase-ticker', false);

    $css = (string) file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('@media (prefers-reduced-motion: reduce)')
        ->toContain('.hero-promotion-ticker__track')
        ->toContain('animation: none;');

    $response->assertDontSee('row.animate(', false);
});

test('pauses ticker animation while the board or browser tab is hidden', function () {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $product = showcaseProduct($seller, $category, 'Hidden Board Find');
    showcaseImage($product);

    showcasePromotion($seller, $product, showcasePackage(PromotionPackage::CODE_BASIC));

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('ticker.getAnimations()[0]', false)
        ->assertSee("document.addEventListener('visibilitychange', syncTicker);", false)
        ->assertSee("'IntersectionObserver' in window", false)
        ->assertSee('animation.pause();', false)
        ->assertSee('animation.play();', false)
        ->assertSee('observer.observe(carousel);', false);
});

test('repeats a single ad seamlessly in both ticker sequences', function () {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $product = showcaseProduct($seller, $category, 'Single Ad Find');
    $firstImage = showcaseImage($product);
    $secondImage = showcaseImage($product, 1, ['sort_order' => 1]);

    showcasePromotion($seller, $product, showcasePackage(PromotionPackage::CODE_BASIC));

    $response = $this->get(route('home'))
        ->assertOk();

    $board = showcaseBoard((string) $response->getContent());
    $ads = showcaseAds($board);
    $ids = array_column($ads, 'id');
    $firstImageUrl = route('product-images.show', [$product->id, $firstImage->id]);
    $secondImageUrl = route('product-images.show', [$product->id, $secondImage->id]);

    expect($board)->not->toBeEmpty()
        ->and($response['heroShowcaseItems'][0]['imageUrls'])->toHaveCount(2)
        ->and($ads)->toHaveCount(6)
        ->and($ids)->toBe(array_fill(0, 6, $product->id))
        ->and(substr_count($board, 'data-hero-showcase-ticker-sequence'))->toBe(2)
        ->and(substr_count($board, 'data-hero-showcase-photo'))->toBe(6)
        ->and(substr_count($board, 'src="'.$firstImageUrl.'"'))->toBe(6)
        ->and(substr_count($board, 'Sponsored'))->toBe(6)
        ->and($board)->not->toContain('src="'.$secondImageUrl.'"');

    foreach ($ads as $ad) {
        expect($ad['html'])->toContain($product->title)
            ->and($ad['html'])->toContain($product->formattedPrice())
            ->and($ad['html'])->toContain($product->conditionLabel())
            ->and($ad['html'])->toContain('Sponsored');
    }

    $response
        ->assertSee('style="animation-duration: 35s"', false)
        ->assertDontSee('array_fill(', false);
});

test('never renders an empty promotion ad', function () {
    $seller = User::factory()->create();
    $category = showcaseCategory();
    $firstProduct = showcaseProduct($seller, $category, 'First Complete Ad');
    $secondProduct = showcaseProduct($seller, $category, 'Second Complete Ad');
    $products = [$firstProduct, $secondProduct];
    $productById = collect($products)->keyBy('id');
    $images = [];

    foreach ($products as $product) {
        $images[$product->id] = showcaseImage($product);
    }

    showcasePromotion($seller, $firstProduct, showcasePackage(PromotionPackage::CODE_PREMIUM));
    showcasePromotion($seller, $secondProduct, showcasePackage(PromotionPackage::CODE_BASIC));

    $response = $this->get(route('home'))
        ->assertOk();

    $board = showcaseBoard((string) $response->getContent());
    $ads = showcaseAds($board);

    expect($ads)->not->toBeEmpty()
        ->and(count($ads))->toBe(substr_count($board, 'data-hero-showcase-ad'))
        ->and(count($ads))->toBe(substr_count($board, 'data-hero-showcase-photo'))
        ->and(count($ads))->toBe(substr_count($board, 'Sponsored'));

    foreach ($ads as $ad) {
        $product = $productById->get($ad['id']);

        expect($ad['html'])->not->toBeEmpty()
            ->and($ad['html'])->toContain('src="'.route('product-images.show', [$product->id, $images[$product->id]->id]).'"')
            ->and($ad['html'])->toContain($product->title)
            ->and($ad['html'])->toContain($product->formattedPrice())
            ->and($ad['html'])->toContain($product->conditionLabel())
            ->and($ad['html'])->toContain('Sponsored')
            ->and($ad['html'])->toContain('href="'.route('products.show', $product->slug).'"');
    }

    $response->assertDontSee('data-hero-showcase-slide', false);
});
