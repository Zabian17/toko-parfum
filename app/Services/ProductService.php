<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductTag;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class ProductService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Product::with(['category', 'images', 'tags', 'variants.tier'])
            ->withCount('images');

        $columnFilters = $this->normalizePayloadArray($filters['filters'] ?? []);
        $sorters       = $this->normalizePayloadArray($filters['sorters'] ?? []);

        if (!empty($filters['search'])) {
            $words = array_filter(explode(' ', trim($filters['search'])));
            $query->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $like = '%' . $word . '%';
                    $q->where(function ($inner) use ($like) {
                        $inner->where('name', 'like', $like)
                              ->orWhere('description', 'like', $like)
                              ->orWhereHas('category', fn($cat) => $cat->where('name', 'like', $like))
                              ->orWhereHas('tags', fn($tag) => $tag->where('name', 'like', $like));
                    });
                }
            });
        }

        $categoryIds = [];

        if (!empty($filters['category_id'])) {
            $incoming = $filters['category_id'];
            if (is_array($incoming)) {
                $categoryIds = array_merge($categoryIds, $incoming);
            } else {
                $categoryIds = array_merge($categoryIds, explode(',', (string) $incoming));
            }
        }

        if (!empty($filters['category_ids'])) {
            $incoming = $filters['category_ids'];
            if (is_array($incoming)) {
                $categoryIds = array_merge($categoryIds, $incoming);
            } else {
                $categoryIds = array_merge($categoryIds, explode(',', (string) $incoming));
            }
        }

        $categoryIds = array_values(array_filter(array_unique(array_map('intval', $categoryIds))));
        if (!empty($categoryIds)) {
            $query->whereIn('category_id', $categoryIds);
        }

        if (!empty($filters['grade'])) {
            $grade = (string) $filters['grade'];
            $query->whereHas('variants', function ($v) use ($grade) {
                $v->where('is_active', true)
                    ->whereHas('tier', fn($t) => $t->where('grade', $grade));
            });
        }

        $isActive = $this->toNullableBool($filters['is_active'] ?? null);
        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        $isFeatured = $this->toNullableBool($filters['is_featured'] ?? null);
        if ($isFeatured !== null) {
            $query->where('is_featured', $isFeatured);
        }

        // Only show products that have at least one variant with stock > 0
        if (!empty($filters['in_stock'])) {
            $query->whereHas('variants', fn($v) => $v->where('stock', '>', 0)->where('is_active', true));
        }

        $this->applyColumnFilters($query, $columnFilters);

        // Sort mapping
        $sortMap = [
            'latest'     => ['created_at', 'desc'],
            'oldest'     => ['created_at', 'asc'],
            'name_asc'   => ['name', 'asc'],
            'name_desc'  => ['name', 'desc'],
            'popular'    => ['sold_count', 'desc'],
            'price_asc'  => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
        ];

        $sortRaw = $filters['sort'] ?? 'latest';
        $order = $filters['order'] ?? 'desc';

        if (!empty($sorters) && is_array($sorters)) {
            $firstSorter = $sorters[0];
            $sortRaw = (string) ($firstSorter['field'] ?? $sortRaw);
            $order = (string) ($firstSorter['dir'] ?? $order);

            if ($sortRaw === 'category_name') {
                $sortRaw = 'category';
            }
        }

        if (isset($sortMap[$sortRaw])) {
            [$sort, $order] = $sortMap[$sortRaw];
        } else {
            $sort  = $sortRaw;
        }

        $order = strtolower((string) $order) === 'asc' ? 'asc' : 'desc';

        if ($sort === 'price') {
            $this->applyPriceSort($query, $order);
            return $query->paginate($filters['per_page'] ?? 15);
        }

        if ($sort === 'category') {
            $query->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->select('products.*')
                ->orderBy('categories.name', $order)
                ->orderBy('products.name');

            return $query->paginate($filters['per_page'] ?? 15);
        }

        $allowedSorts = ['created_at', 'name', 'sold_count', 'stock'];
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $order === 'asc' ? 'asc' : 'desc');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    private function normalizePayloadArray(mixed $payload): array
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }

    private function toNullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower((string) $value);
        return in_array($normalized, ['1', 'true', 'yes'], true);
    }

    private function applyColumnFilters(Builder $query, array $columnFilters): void
    {
        foreach ($columnFilters as $filter) {
            $field = $filter['field'] ?? null;
            $value = trim((string) ($filter['value'] ?? ''));

            if (!$field || $value === '') {
                continue;
            }

            if ($field === 'name') {
                $query->where('products.name', 'like', '%' . $value . '%');
                continue;
            }

            if ($field === 'category_name') {
                $query->whereHas('category', fn($c) => $c->where('name', 'like', '%' . $value . '%'));
                continue;
            }

            if ($field === 'is_active') {
                $bool = $this->toNullableBool($value);
                if ($bool !== null) {
                    $query->where('products.is_active', $bool);
                }
                continue;
            }

            if ($field === 'min_price' && is_numeric($value)) {
                $query->whereHas('variants.tier', fn($t) => $t->where('price', (float) $value));
            }
        }
    }

    private function applyPriceSort(Builder $query, string $order): void
    {
        $priceSub = ProductVariant::query()
            ->join('product_tiers', 'product_tiers.id', '=', 'product_variants.tier_id')
            ->where('product_variants.is_active', true)
            ->selectRaw('product_variants.product_id, MIN(product_tiers.price) as min_price_sort')
            ->groupBy('product_variants.product_id');

        $query->leftJoinSub($priceSub, 'price_sort', function ($join) {
            $join->on('price_sort.product_id', '=', 'products.id');
        })
            ->select('products.*')
            ->orderByRaw('COALESCE(price_sort.min_price_sort, 999999999) ' . strtoupper($order))
            ->orderBy('products.name');
    }

    public function create(array $data, ?UploadedFile $thumbnail = null, array $imageFiles = []): Product
    {
        return DB::transaction(function () use ($data, $thumbnail, $imageFiles) {
            $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

            if ($thumbnail) {
                $data['thumbnail'] = $this->compressAndStore($thumbnail, 'products/thumbnails', 600);
            }

            $tags     = $data['tags'] ?? [];
            $variants = $data['variants'] ?? [];
            unset($data['tags'], $data['variants']);

            $product = Product::create($data);

            $this->syncTags($product, $tags);
            $this->syncVariants($product, $variants);
            $this->storeImages($product, $imageFiles);

            return $product->load(['category', 'images', 'variants.tier', 'tags']);
        });
    }

    public function update(Product $product, array $data, ?UploadedFile $thumbnail = null, array $imageFiles = []): Product
    {
        return DB::transaction(function () use ($product, $data, $thumbnail, $imageFiles) {
            if ($thumbnail) {
                if ($product->thumbnail) {
                    Storage::disk('public')->delete($product->thumbnail);
                }
                $data['thumbnail'] = $this->compressAndStore($thumbnail, 'products/thumbnails', 600);
            } elseif (!empty($data['delete_thumbnail'])) {
                if ($product->thumbnail) {
                    Storage::disk('public')->delete($product->thumbnail);
                }
                $data['thumbnail'] = null;
            }
            unset($data['delete_thumbnail']);

            $tags     = $data['tags'] ?? null;
            $variants = $data['variants'] ?? null;
            unset($data['tags'], $data['variants']);

            $product->update($data);

            if ($tags !== null) {
                $this->syncTags($product, $tags);
            }

            if ($variants !== null) {
                $this->syncVariants($product, $variants);
            }

            if (!empty($imageFiles)) {
                $this->storeImages($product, $imageFiles);
            }

            return $product->fresh()->load(['category', 'images', 'variants.tier', 'tags']);
        });
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            // Delete stored files
            if ($product->thumbnail) {
                Storage::disk('public')->delete($product->thumbnail);
            }

            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->image_path);
            }

            $product->delete();
        });
    }

    public function deleteImage(ProductImage $image): void
    {
        Storage::disk('public')->delete($image->image_path);
        $image->delete();
    }

    /**
     * Compress and store an uploaded image as JPEG.
     * Scales down to $maxDimension on the longest side while preserving aspect ratio.
     */
    private function compressAndStore(UploadedFile $file, string $directory, int $maxDimension = 1200, int $quality = 80): string
    {
        $filename  = $directory . '/' . Str::uuid() . '.jpg';
        $directory = Storage::disk('public')->path($directory);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $fullPath = Storage::disk('public')->path($filename);

        (new ImageManager(new GdDriver()))
            ->read($file->getPathname())
            ->scaleDown($maxDimension, $maxDimension)
            ->toJpeg($quality)
            ->save($fullPath);

        return $filename;
    }

    private function syncTags(Product $product, array $tagNames): void
    {
        $tagIds = [];
        foreach ($tagNames as $name) {
            $slug = Str::slug($name);
            $tag = ProductTag::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'slug' => $slug]
            );
            $tagIds[] = $tag->id;
        }
        $product->tags()->sync($tagIds);
    }

    private function syncVariants(Product $product, array $variants): void
    {
        // $variants is an array of [ 'tier_id' => X, 'stock' => Y, 'is_active' => true/false ]
        // We upsert by tier_id — one variant per tier per product
        $keptTierIds = [];

        foreach ($variants as $variantData) {
            $tierId = (int) ($variantData['tier_id'] ?? 0);
            if (!$tierId) continue;

            $variant = $product->variants()->where('tier_id', $tierId)->first();
            if ($variant) {
                $variant->update([
                    'stock'     => (int) ($variantData['stock'] ?? 0),
                    'is_active' => (bool) ($variantData['is_active'] ?? true),
                ]);
            } else {
                $product->variants()->create([
                    'tier_id'   => $tierId,
                    'stock'     => (int) ($variantData['stock'] ?? 0),
                    'is_active' => (bool) ($variantData['is_active'] ?? true),
                ]);
            }
            $keptTierIds[] = $tierId;
        }

        // Remove variants for tiers not included in the update
        $product->variants()->whereNotIn('tier_id', $keptTierIds)->delete();

        // Update aggregate stock on product
        $product->stock = $product->variants()->sum('stock');
        $product->save();
    }

    private function storeImages(Product $product, array $imageFiles): void
    {
        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        $sortBase   = $product->images()->count();

        foreach ($imageFiles as $index => $file) {
            $path = $this->compressAndStore($file, 'products/images', 1200);
            $product->images()->create([
                'image_path' => $path,
                'sort_order' => $sortBase + $index,
                'is_primary' => !$hasPrimary && $index === 0,
            ]);
            if (!$hasPrimary && $index === 0) {
                $hasPrimary = true;
            }
        }
    }
}
