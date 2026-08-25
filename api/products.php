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
 * Built on Symfony's HttpFoundation component rather than raw superglobals and
 * header() calls. See README.md for why that component alone, and not the full
 * framework.
 *
 * The source JSON uses `false` to mean "this product has no value for this
 * field". That is normalised to `null` here so the client has a single,
 * unambiguous absence value, rather than a boolean that coerces to 0 in
 * arithmetic and sits alongside genuinely absent values.
 *
 * Prices are held as integer pence throughout, which keeps floating point
 * rounding out of transit. Formatting to "£7.99" happens on the client so the
 * API stays presentation-agnostic.
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__ . '/vendor/autoload.php';

const DATA_FILE    = __DIR__ . '/data/product.json';
const IMAGE_PATH   = '/img/';
const MAX_DELAY_MS = 10_000;

$request  = Request::createFromGlobals();
$response = handle($request);

// HttpFoundation is responsible for emitting status line, headers and body,
// which is the point of using it: no manual header() bookkeeping, and the
// response is a value that can be built up and returned rather than side
// effects scattered through the script.
$response->prepare($request);
$response->send();

/**
 * Front controller: routes one request to one response.
 *
 * Returning a Response rather than echoing keeps every exit path uniform —
 * success and failure are the same kind of thing.
 */
function handle(Request $request): Response
{
    // The React dev server runs on a different origin to `php -S`. Vite is
    // configured to proxy /api, which avoids CORS in development entirely;
    // these headers keep the endpoint usable when called directly, from curl,
    // Postman or another host.
    $cors = [
        'Access-Control-Allow-Origin'  => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type',
    ];

    if ($request->isMethod(Request::METHOD_OPTIONS)) {
        return new Response('', Response::HTTP_NO_CONTENT, $cors);
    }

    if (!$request->isMethod(Request::METHOD_GET)) {
        return new JsonResponse(
            ['error' => 'Method not allowed. Use GET.'],
            Response::HTTP_METHOD_NOT_ALLOWED,
            $cors + ['Allow' => 'GET, OPTIONS'],
        );
    }

    applyRequestedDelay($request);

    try {
        $products = loadProducts(DATA_FILE);
    } catch (RuntimeException $e) {
        // The message is deliberately generic. Nothing about the filesystem or
        // a stack trace should reach the client.
        return new JsonResponse(
            ['error' => $e->getMessage()],
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $cors,
        );
    }

    return new JsonResponse(
        [
            'count'    => count($products),
            'products' => $products,
        ],
        Response::HTTP_OK,
        // The catalogue is read fresh each time so the loading state is always
        // demonstrable; a real deployment would cache this aggressively.
        $cors + ['Cache-Control' => 'no-store'],
    );
}

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
    } catch (JsonException) {
        throw new RuntimeException('Product data is malformed.');
    }

    if (!isset($decoded['product_arr']) || !is_array($decoded['product_arr'])) {
        throw new RuntimeException('Product data is missing the product_arr key.');
    }

    return array_values(array_map(
        normaliseProduct(...),
        array_filter($decoded['product_arr'], is_array(...)),
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

    // A "was price" that is not actually higher than the current price is not
    // a saving, so it is discarded rather than rendered as a £0.00 or negative
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
 *
 * Read through HttpFoundation's ParameterBag, which validates and casts the
 * input rather than trusting $_GET.
 */
function applyRequestedDelay(Request $request): void
{
    $ms = $request->query->getInt('delay');
    $ms = min(max($ms, 0), MAX_DELAY_MS);

    if ($ms > 0) {
        usleep($ms * 1000);
    }
}
