<?php

use Fermmon\Web\DataService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

require __DIR__ . '/vendor/autoload.php';

$baseDir = getenv('FERMMON_BASE_DIR') ?: dirname(__DIR__);
$dataService = new DataService($baseDir);

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->addErrorMiddleware(true, true, true);

// Static assets
$app->get('/manifest.json', function (Request $request, Response $response) {
    $body = file_get_contents(__DIR__ . '/public/manifest.json');
    $response->getBody()->write($body);
    return $response->withHeader('Content-Type', 'application/json');
});
$app->get('/sw.js', function (Request $request, Response $response) {
    $body = file_get_contents(__DIR__ . '/public/sw.js');
    $response->getBody()->write($body);
    return $response->withHeader('Content-Type', 'application/javascript');
});

// API: latest reading
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

// API: readings
$app->post('/api/readings', function (Request $request, Response $response) use ($dataService) {
    $body = $request->getParsedBody() ?? [];
    $dateTime = $body['date_time'] ?? date('Y-m-d H:i:s');
    $co2 = isset($body['co2']) ? (float)$body['co2'] : null;
    $tvoc = isset($body['tvoc']) ? (float)$body['tvoc'] : null;
    $temp = isset($body['temp']) ? (float)$body['temp'] : null;
    if ($co2 === null || $tvoc === null || $temp === null) {
        $response->getBody()->write(json_encode(['error' => 'co2, tvoc and temp required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $version = $body['version'] ?? null;
    if ($dataService->versionIsFinished($version)) {
        $response->getBody()->write(json_encode(['error' => 'Brew is finished; readings declined']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
    }
    $rtemp = isset($body['rtemp']) ? (float)$body['rtemp'] : null;
    $rhumi = isset($body['rhumi']) ? (float)$body['rhumi'] : null;
    $relay = isset($body['relay']) ? (int)$body['relay'] : null;
    $ok = $dataService->addReading($dateTime, $co2, $tvoc, $temp, $version, $rtemp, $rhumi, $relay);
    $response->getBody()->write(json_encode($ok ? ['ok' => true] : ['error' => 'Failed to store']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($ok ? 201 : 500);
});
$app->get('/api/readings', function (Request $request, Response $response) use ($dataService) {
    $params = $request->getQueryParams();
    $version = $params['version'] ?? null;
    $limit = isset($params['limit']) ? (int)$params['limit'] : 0;
    $maxCo2 = isset($params['max_co2']) ? (int)$params['max_co2'] : null;
    $maxTvoc = isset($params['max_tvoc']) ? (int)$params['max_tvoc'] : null;
    $since = $params['since'] ?? null;
    $from = $params['from'] ?? null;
    if ($from === null && isset($params['hours'])) {
        $hours = (int)$params['hours'];
        if ($hours > 0) {
            $from = date('Y-m-d H:i:s', time() - $hours * 3600);
        }
    }
    $readings = $dataService->getReadings($version, $limit, $maxCo2, $maxTvoc, $since, $from);
    $response->getBody()->write(json_encode($readings));
    return $response->withHeader('Content-Type', 'application/json');
});

// API: versions
$app->get('/api/versions', function (Request $request, Response $response) use ($dataService) {
    $versions = $dataService->getVersions();
    $response->getBody()->write(json_encode($versions));
    return $response->withHeader('Content-Type', 'application/json');
});
$app->get('/api/versions/{version}/reading-range', function (Request $request, Response $response, array $args) use ($dataService) {
    $version = $args['version'] ?? '';
    $range = $dataService->getReadingRange($version);
    if (!$range) {
        $response->getBody()->write(json_encode(['error' => 'No readings']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }
    $response->getBody()->write(json_encode($range));
    return $response->withHeader('Content-Type', 'application/json');
});

// API: config
$app->get('/api/config', function (Request $request, Response $response) use ($dataService) {
    $config = $dataService->getConfig();
    $response->getBody()->write(json_encode($config));
    return $response->withHeader('Content-Type', 'application/json');
});
$app->post('/api/config', function (Request $request, Response $response) use ($dataService) {
    $body = $request->getParsedBody();
    foreach (['recording', 'sample_interval', 'write_interval', 'summary_refresh_interval', 'chart_update_interval', 'target_temp', 'temp_warning_threshold', 'hide_outliers', 'cache_apis'] as $key) {
        if (isset($body[$key])) {
            $dataService->setConfig($key, (string)$body[$key]);
        }
    }
    $config = $dataService->getConfig();
    $response->getBody()->write(json_encode($config));
    return $response->withHeader('Content-Type', 'application/json');
});

// API: add version
$app->post('/api/versions', function (Request $request, Response $response) use ($dataService) {
    $body = $request->getParsedBody();
    $version = $body['version'] ?? '';
    $brew = $body['brew'] ?? '';
    $url = $body['url'] ?? '';
    $description = $body['description'] ?? '';
    if (!$version || !$brew) {
        $response->getBody()->write(json_encode(['error' => 'version and brew required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $dataService->addVersion($version, $brew, $url, $description);
    $response->getBody()->write(json_encode(['ok' => true]));
    return $response->withHeader('Content-Type', 'application/json');
});

// API: brew logs
$app->get('/api/versions/{version}/brew-logs', function (Request $request, Response $response, array $args) use ($dataService) {
    $version = $args['version'] ?? '';
    $logs = $dataService->getBrewLogs($version);
    $response->getBody()->write(json_encode($logs));
    return $response->withHeader('Content-Type', 'application/json');
});
$app->post('/api/versions/{version}/brew-logs', function (Request $request, Response $response, array $args) use ($dataService) {
    $version = $args['version'] ?? '';
    $body = $request->getParsedBody();
    $dateTime = $body['date_time'] ?? gmdate('Y-m-d H:i:s');
    $note = $body['note'] ?? '';
    if (trim($note) === '') {
        $response->getBody()->write(json_encode(['error' => 'note required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $ok = $dataService->addBrewLog($version, $dateTime, $note);
    $response->getBody()->write(json_encode($ok ? ['ok' => true] : ['error' => 'Failed to store']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($ok ? 201 : 500);
});
$app->delete('/api/versions/{version}/brew-logs/{id}', function (Request $request, Response $response, array $args) use ($dataService) {
    $version = $args['version'] ?? '';
    $id = (int) ($args['id'] ?? 0);
    if ($id <= 0) {
        $response->getBody()->write(json_encode(['error' => 'invalid id']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $ok = $dataService->deleteBrewLog($id, $version);
    $response->getBody()->write(json_encode($ok ? ['ok' => true] : ['error' => 'Not found']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($ok ? 200 : 404);
});

// API: update version
$app->put('/api/versions/{version}', function (Request $request, Response $response, array $args) use ($dataService) {
    $version = $args['version'] ?? '';
    $body = $request->getParsedBody();
    $brew = $body['brew'] ?? '';
    $url = $body['url'] ?? '';
    $description = $body['description'] ?? '';
    $endDate = isset($body['end_date']) ? (trim($body['end_date']) ?: null) : null;
    if (!$version || !$brew) {
        $response->getBody()->write(json_encode(['error' => 'version and brew required']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    $ok = $dataService->updateVersion($version, $brew, $url, $description, $endDate);
    if (!$ok) {
        $response->getBody()->write(json_encode(['error' => 'Version not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }
    $response->getBody()->write(json_encode(['ok' => true]));
    return $response->withHeader('Content-Type', 'application/json');
});

// PWA: dashboard
$app->get('/', function (Request $request, Response $response) use ($dataService) {
    $renderer = new PhpRenderer(__DIR__ . '/templates');
    $versions = $dataService->getVersions();
    $currentVersion = $versions[0]['version'] ?? null;
    $latest = $dataService->getLatest($currentVersion);
    $config = $dataService->getConfig();
    return $renderer->render($response, 'dashboard.php', [
        'latest' => $latest,
        'versions' => $versions,
        'currentVersion' => $currentVersion,
        'config' => $config,
        'navActive' => 'dashboard',
    ]);
});

// Brews page
$app->get('/brews', function (Request $request, Response $response) use ($dataService) {
    $renderer = new PhpRenderer(__DIR__ . '/templates');
    return $renderer->render($response, 'brews.php', [
        'navActive' => 'brews',
    ]);
});

// Log page
$app->get('/log', function (Request $request, Response $response) use ($dataService) {
    $renderer = new PhpRenderer(__DIR__ . '/templates');
    return $renderer->render($response, 'log.php', [
        'navActive' => 'log',
    ]);
});

// Control page
$app->get('/control', function (Request $request, Response $response) use ($dataService) {
    $renderer = new PhpRenderer(__DIR__ . '/templates');
    $config = $dataService->getConfig();
    $versions = $dataService->getVersions();
    return $renderer->render($response, 'control.php', [
        'config' => $config,
        'versions' => $versions,
        'navActive' => 'control',
    ]);
});

return $app;
