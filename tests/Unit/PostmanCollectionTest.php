<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * v12: Postman kolleksiyasi (`docs/Edunova_CRM_API.postman_collection.json`) haqiqiy
 * `routes/api.php`dagi barcha endpoint'larni qamrab olganini tekshiradi - kelajakda yangi
 * endpoint qo'shilib, kolleksiya yangilanishi unutilsa, shu test buni ushlaydi.
 */
class PostmanCollectionTest extends TestCase
{
    public function test_collection_file_is_valid_json(): void
    {
        $path = base_path('docs/Edunova_CRM_API.postman_collection.json');
        $this->assertFileExists($path);

        $data = json_decode(file_get_contents($path), true);

        $this->assertIsArray($data);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertArrayHasKey('item', $data);
    }

    public function test_collection_covers_every_registered_api_route(): void
    {
        $path = base_path('docs/Edunova_CRM_API.postman_collection.json');
        $collection = json_decode(file_get_contents($path), true);

        $collectionPatterns = [];
        $this->collectPatterns($collection['item'], $collectionPatterns);

        $missing = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }

                $pattern = $method.' '.$this->normalize(substr($route->uri(), strlen('api/v1/')));

                if (! in_array($pattern, $collectionPatterns, true)) {
                    $missing[] = $pattern;
                }
            }
        }

        $this->assertSame([], $missing, "Postman kolleksiyasida yo'q endpoint(lar): ".implode(', ', $missing));
    }

    /** @param array<int,string> $out */
    private function collectPatterns(array $items, array &$out): void
    {
        foreach ($items as $item) {
            if (isset($item['item'])) {
                $this->collectPatterns($item['item'], $out);

                continue;
            }

            $request = $item['request'];
            $segments = $request['url']['path'] ?? [];
            $out[] = $request['method'].' '.$this->normalize(implode('/', $segments));
        }
    }

    /** `{param}` yoki raqamli segmentlarni bitta joker belgisiga tenglashtiradi (POST staff/1 == POST staff/{user}). */
    private function normalize(string $path): string
    {
        $segments = array_filter(explode('/', $path), fn ($s) => $s !== '');

        $segments = array_map(function ($segment) {
            return preg_match('/^\{.+\}$|^\d+$/', $segment) ? '*' : $segment;
        }, $segments);

        return implode('/', $segments);
    }
}
