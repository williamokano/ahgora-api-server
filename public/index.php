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

$app->get('/', function () use ($app) {
    return $app->json(['message' => 'Hello World']);
});

$getParams = function (Request $request, array $extra = []) {
    $parameters = array_merge([
        'X-CompanyId' => 'company_id',
        'X-Username'  => 'username',
        'X-Password'  => 'password',
    ], $extra);
    $values = [];

    foreach ($parameters as $header => $get) {
        $param = $request->headers->get($header, $request->query->get($get));

        if (null === $param) {
            throw new InvalidArgumentException('Missing parameter ' . $header);
        }

        $values[$header] = $param;
    }

    return $values;
};

$punchesCallback = function (Request $request, $month = null, $year = null) use ($app, $api, $getParams) {

    $values = $getParams($request);

    $api
        ->setDateTimeFormat('d/m/Y H:i')
        ->setCompanyId($values['X-CompanyId'])
        ->setUsername($values['X-Username'])
        ->setPassword($values['X-Password'])
        ->doLogin();

    $month = is_string($month) ? (int) $month : $month;
    $year = is_string($year) ? (int) $year : $year;

    $punchs = $api->getPunches($month, $year);

    return $app->json($punchs);
};

$app->get('/punches', $punchesCallback);
$app->get('/punches/{month}/{year}', $punchesCallback);

$app->get('/punches/day', function (Request $request) use ($app, $api, $getParams) {
    $values = $getParams($request, [
        'day'    => 'day',
        'month'  => 'month',
        'year'   => 'year',
        'format' => 'format',
    ]);

    $api
        ->setDateTimeFormat('d/m/Y H:i')
        ->setCompanyId($values['X-CompanyId'])
        ->setUsername($values['X-Username'])
        ->setPassword($values['X-Password'])
        ->doLogin();

    $punchesDay = array_values($api->getPunchesFromDay($values['day'], $values['month'], $values['year']));
    if ($values['format'] !== 'json') {
        usort($punchesDay, function ($a, $b) {
            /* Use spaceship operator maybe? */
            if ($a == $b) {
                return 0;
            }

            return $a < $b ? -1 : 1;
        });

        return implode('|', array_map(function (\DateTime $punch) {
            return $punch->format("H:i");
        }, $punchesDay));
    } else {
        return $app->json($punchesDay);
    }
});

$app->error(function (\Exception $e) use ($app) {
    return $app->json(['error_message' => $e->getMessage()], 400);
});

$app->run();
