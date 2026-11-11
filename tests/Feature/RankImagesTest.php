<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rank_products_have_assigned_local_images_and_files_exist(): void
    {
        $this->seed();

        $expected = [
            'rank-knight'   => '/img/ranks/rank-knight.jpg',
            'rank-legend'   => '/img/ranks/rank-legend.jpg',
            'rank-master'   => '/img/ranks/rank-master.jpg',
            'rank-overlord' => '/img/ranks/rank-overlord.jpg',
            'rank-god'      => '/img/ranks/rank-god.jpg',
        ];

        $res = $this->getJson('/api/products?category=vip');
        $res->assertStatus(200);

        foreach ($expected as $slug => $imgPath) {
            $product = Product::where('slug', $slug)->first();
            $this->assertNotNull($product, "Missing product: {$slug}");
            $this->assertSame($imgPath, $product->image);
            $this->assertSame([$imgPath], $product->images);
            $this->assertFileExists(public_path(ltrim($imgPath, '/')));
        }
    }

    public function test_rank_image_migration_updates_existing_placeholder_rows(): void
    {
        $this->seed();

        Product::where('slug', 'rank-knight')->update(['image' => 'https://placehold.co/800x800/png?text=Rank+Knight']);
        Product::where('slug', 'rank-god')->update(['image' => 'https://placehold.co/800x800/png?text=Rank+God']);

        $migration = require database_path('migrations/2026_11_10_000012_update_rank_product_images.php');
        $migration->up();

        $this->assertSame('/img/ranks/rank-knight.jpg', Product::where('slug', 'rank-knight')->value('image'));
        $this->assertSame('/img/ranks/rank-god.jpg', Product::where('slug', 'rank-god')->value('image'));
    }

    public function test_api_replaces_legacy_placeholder_rank_images_but_preserves_custom_urls(): void
    {
        $this->seed();

        Product::where('slug', 'rank-knight')->update([
            'image' => 'https://placehold.co/800x800/png?text=Rank+Knight',
            'images' => ['https://placehold.co/800x800/png?text=Rank+Knight+-+1'],
        ]);
        Product::where('slug', 'rank-god')->update([
            'image' => 'https://cdn.example.com/custom-god.jpg',
            'images' => ['https://cdn.example.com/custom-god.jpg'],
        ]);

        $knightRes = $this->getJson('/api/products/rank-knight');
        $knightRes->assertStatus(200)
            ->assertJsonPath('data.image', '/img/ranks/rank-knight.jpg')
            ->assertJsonPath('data.images.0', '/img/ranks/rank-knight.jpg');

        $godRes = $this->getJson('/api/products/rank-god');
        $godRes->assertStatus(200)
            ->assertJsonPath('data.image', 'https://cdn.example.com/custom-god.jpg');
    }
}
