<?php

use Fermmon\Web\DataService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

require __DIR__ . '/../vendor/autoload.php';

$baseDir = dirname(__DIR__, 2);
$dataService = new DataService($baseDir);

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Static assets (for PHP built-in server; Apache/nginx serve these directly)
$app->get('/manifest.json', function (Request $request, Response $response) {
    $body = file_get_contents(__DIR__ . '/manifest.json');
    $response->getBody()->write($body);
    return $response->withHeader('Content-Type', 'application/json');
});
$app->get('/sw.js', function (Request $request, Response $response) {
    $body = file_get_contents(__DIR__ . '/sw.js');
    $response->getBody()->write($body);
    return $response->withHeader('Content-Type', 'application/javascript');
});

// API: latest reading (optional ?version= for specific brew)
$app->get('/api/latest', function (Request $request, Response $response) use ($dataService) {
    $version = $request->getQueryParams()['version'] ?? null;
    $latest = $dataService->getLatest($version);
    if (!$latest) {
        $response->getBody()->write(json_encode(['error' => 'No data']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }
    $response->getBody()->write(json_encode($latest));
    return $response->withHeader('Content-Type', 'application/json');
});

// API: readings for charts (?version= &limit= &max_co2= &max_tvoc= to filter outliers)
$app->get('/api/readings', function (Request $request, Response $response) use ($dataService) {
    $params = $request->getQueryParams();
    $version = $params['version'] ?? null;
    $limit = isset($params['limit']) ? (int)$params['limit'] : 0;
    $maxCo2 = isset($params['max_co2']) ? (int)$params['max_co2'] : null;
    $maxTvoc = isset($params['max_tvoc']) ? (int)$params['max_tvoc'] : null;
    $since = $params['since'] ?? null;
    $readings = $dataService->getReadings($version, $limit, $maxCo2, $maxTvoc, $since);
    $response->getBody()->write(json_encode($readings));
    return $response->withHeader('Content-Type', 'application/json');
});

// API: versions
$app->get('/api/versions', function (Request $request, Response $response) use ($dataService) {
    $versions = $dataService->getVersions();
    $response->getBody()->write(json_encode($versions));
    return $response->withHeader('Content-Type', 'application/json');
});

// API: config (GET, POST)
$app->get('/api/config', function (Request $request, Response $response) use ($dataService) {
    $config = $dataService->getConfig();
    $response->getBody()->write(json_encode($config));
    return $response->withHeader('Content-Type', 'application/json');
});
$app->post('/api/config', function (Request $request, Response $response) use ($dataService) {
    $body = $request->getParsedBody();
    foreach (['recording', 'sample_interval', 'write_interval'] as $key) {
        if (isset($body[$key])) {
            $dataService->setConfig($key, (string)$body[$key]);
        }
    }
    $config = $dataService->getConfig();
    $response->getBody()->write(json_encode($config));
    return $response->withHeader('Content-Type', 'application/json');
});

// API: add version (POST)
$app->post('/api/versions', function (Request $request, Response $response) use ($dataService) {
    $body = $request->getParsedBody();
    $version = $body['version'] ?? '';
    $brew = $body['brew'] ?? '';
    $url = $body['url'] ?? '';
    if (!$version || !$brew) {
        $response->getBody()->write(json_encode(['error' => 'version and brew required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $dataService->addVersion($version, $brew, $url);
    $response->getBody()->write(json_encode(['ok' => true]));
    return $response->withHeader('Content-Type', 'application/json');
});

// PWA: dashboard
$app->get('/', function (Request $request, Response $response) use ($dataService) {
    $renderer = new PhpRenderer(__DIR__ . '/../templates');
    $versions = $dataService->getVersions();
    $currentVersion = $versions[0]['version'] ?? null;
    $latest = $dataService->getLatest($currentVersion);
    return $renderer->render($response, 'dashboard.php', [
        'latest' => $latest,
        'versions' => $versions,
        'currentVersion' => $currentVersion,
        'navActive' => 'dashboard',
    ]);
});

// Control page
$app->get('/control', function (Request $request, Response $response) use ($dataService) {
    $renderer = new PhpRenderer(__DIR__ . '/../templates');
    $config = $dataService->getConfig();
    $versions = $dataService->getVersions();
    return $renderer->render($response, 'control.php', [
        'config' => $config,
        'versions' => $versions,
        'navActive' => 'control',
    ]);
});

$app->run();
