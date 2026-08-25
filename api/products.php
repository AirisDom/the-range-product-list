<?php
/**
 * Headless product API.
 *
 * GET /products.php
 *   Returns the product catalogue as JSON.
 *
 * Query parameters
 *   ?delay=<ms>  Artificially delays the response (capped at 10s). Used to
 *                demonstrate the front end's loading state, per the brief's
 *                requirement to gracefully handle a slow response.
 *
 * Response shape
 *   { "count": 12, "products": [ Product, ... ] }
 *
 * The source JSON uses `false` to mean "this product has no value for this
 * field". That is normalised to `null` here so the client has a single,
 * unambiguous absence value to check rather than juggling a boolean that
 * masquerades as a number.
 *
 * Prices are held as integer pence throughout. Formatting to "£7.99" happens
 * on the client so the API stays presentation-agnostic.
 */

declare(strict_types=1);

const DATA_FILE   = __DIR__ . '/data/product.json';
const IMAGE_PATH  = '/img/';
const MAX_DELAY_MS = 10000;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// The React dev server runs on a different origin to `php -S`, so the API has
// to opt in to cross-origin reads. Vite is also configured to proxy /api,
// which avoids CORS entirely; these headers keep the endpoint usable when it
// is called directly (curl, Postman, a different host).
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respondWithError(405, 'Method not allowed. Use GET.');
}

applyRequestedDelay();

try {
    $products = loadProducts(DATA_FILE);
} catch (RuntimeException $e) {
    respondWithError(500, $e->getMessage());
}

echo json_encode(
    [
        'count'    => count($products),
        'products' => $products,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

/**
 * Reads the catalogue from disk and maps it into the API's response shape.
 *
 * @return array<int, array<string, mixed>>
 * @throws RuntimeException when the file is missing or not valid JSON.
 */
function loadProducts(string $path): array
{
    if (!is_readable($path)) {
        throw new RuntimeException('Product data is unavailable.');
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Product data could not be read.');
    }

    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Product data is malformed.');
    }

    if (!isset($decoded['product_arr']) || !is_array($decoded['product_arr'])) {
        throw new RuntimeException('Product data is missing the product_arr key.');
    }

    return array_values(array_map(
        'normaliseProduct',
        array_filter($decoded['product_arr'], 'is_array')
    ));
}

/**
 * Converts one raw record into the shape the client consumes.
 *
 * @param  array<string, mixed> $raw
 * @return array<string, mixed>
 */
function normaliseProduct(array $raw): array
{
    $price    = toPositiveInt($raw['price'] ?? null) ?? 0;
    $wasPrice = toPositiveInt($raw['was_price'] ?? null);
    $reviews  = toPositiveInt($raw['reviews'] ?? null);
    $image    = toPositiveInt($raw['img'] ?? null);

    // A "was price" that is not actually higher than the current price is not a
    // saving, so it is discarded rather than rendered as a £0.00 or negative
    // discount.
    if ($wasPrice !== null && $wasPrice <= $price) {
        $wasPrice = null;
    }

    return [
        'id'        => $image ?? 0,
        'name'      => trim((string) ($raw['name'] ?? '')),
        'price'     => $price,
        'was_price' => $wasPrice,
        'saving'    => $wasPrice === null ? 0 : $wasPrice - $price,
        'reviews'   => $reviews,
        'image'     => $image === null ? null : IMAGE_PATH . $image . '.jpg',
    ];
}

/**
 * Coerces a raw field to a positive integer, treating the source data's `false`
 * sentinel — along with null, empty strings and non-numerics — as absent.
 */
function toPositiveInt(mixed $value): ?int
{
    if ($value === false || $value === null || $value === '' || !is_numeric($value)) {
        return null;
    }

    $int = (int) $value;

    return $int > 0 ? $int : null;
}

/**
 * Honours ?delay=<ms> so the client's loading state can be demonstrated.
 */
function applyRequestedDelay(): void
{
    $requested = $_GET['delay'] ?? null;

    if ($requested === null || !is_numeric($requested)) {
        return;
    }

    $ms = min(max((int) $requested, 0), MAX_DELAY_MS);

    if ($ms > 0) {
        usleep($ms * 1000);
    }
}

/**
 * Emits a JSON error body and stops. Never leaks a stack trace to the client.
 */
function respondWithError(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_PRETTY_PRINT);
    exit;
}
