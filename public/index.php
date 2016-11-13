<?php
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Katapoka\Ahgora\Adapters\GuzzleAdapter;
use Katapoka\Ahgora\ApiFactory;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

require __DIR__ . '/../vendor/autoload.php';

$app = new Application();

$cookieJar = new CookieJar();
$guzzleClient = new Client([
    'cookies' => $cookieJar,
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36',
    ],
]);
$guzzleAdapter = new GuzzleAdapter($guzzleClient);
$api = ApiFactory::create($guzzleAdapter, 'Http');

$app->before(function (Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }
});

// Check for the necessary headers
$app->before(function (Request $request) use ($app) {
    $neededHeaders = ['X-CompanyId', 'X-Username', 'X-Password'];
    foreach ($neededHeaders as $header) {
        if ($request->headers->get($header) === null) {
            throw new InvalidArgumentException('Missing argument ' . $header);
        }
    }
});

$app->get('/', function () use ($app) {
    return $app->json(['message' => 'Hello World']);
});

$punchesCallback = function (Request $request, $month = null, $year = null) use ($app, $api) {
    $api
        ->setDateTimeFormat('d/m/Y H:i')
        ->setCompanyId($request->headers->get('X-CompanyId'))
        ->setUsername($request->headers->get('X-Username'))
        ->setPassword($request->headers->get('X-Password'))
        ->doLogin();

    $punchs = $api->getPunches((int) $month, (int) $year);

    return $app->json($punchs);
};

$app->get('/punches', $punchesCallback);
$app->get('/punches/{month}/{year}', $punchesCallback);

$app->error(function (\Exception $e) use ($app) {
    return $app->json(['error_message' => $e->getMessage()], 400);
});

$app->run();
