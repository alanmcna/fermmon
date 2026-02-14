<?php

use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

beforeEach(function () {
    $this->app = require __DIR__ . '/../../app.php';
    $this->requestFactory = new ServerRequestFactory();
});

test('config: GET returns hide_outliers', function () {
    $request = $this->requestFactory->createServerRequest('GET', '/api/config');
    $response = $this->app->handle($request);

    expect($response->getStatusCode())->toBe(200);
    $body = (string) $response->getBody();
    $config = json_decode($body, true);
    expect($config)->toHaveKey('hide_outliers');
});

test('config: POST hide_outliers to 0 persists', function () {
    $body = (new StreamFactory())->createStream(json_encode(['hide_outliers' => '0']));
    $request = $this->requestFactory->createServerRequest('POST', '/api/config')
        ->withBody($body)
        ->withHeader('Content-Type', 'application/json');

    $response = $this->app->handle($request);
    expect($response->getStatusCode())->toBe(200);

    $config = json_decode((string) $response->getBody(), true);
    expect($config['hide_outliers'])->toBe('0');

    // Verify via GET
    $getRequest = $this->requestFactory->createServerRequest('GET', '/api/config');
    $getResponse = $this->app->handle($getRequest);
    $config = json_decode((string) $getResponse->getBody(), true);
    expect($config['hide_outliers'])->toBe('0');
});

test('config: POST hide_outliers to 1 persists', function () {
    $body = (new StreamFactory())->createStream(json_encode(['hide_outliers' => '1']));
    $request = $this->requestFactory->createServerRequest('POST', '/api/config')
        ->withBody($body)
        ->withHeader('Content-Type', 'application/json');

    $response = $this->app->handle($request);
    expect($response->getStatusCode())->toBe(200);

    $config = json_decode((string) $response->getBody(), true);
    expect($config['hide_outliers'])->toBe('1');
});

test('readings: without max_co2/max_tvoc returns all readings', function () {
    $request = $this->requestFactory->createServerRequest('GET', '/api/readings?limit=0');
    $response = $this->app->handle($request);

    expect($response->getStatusCode())->toBe(200);
    $readings = json_decode((string) $response->getBody(), true);
    expect($readings)->toBeArray();
});

test('readings: with max_co2 and max_tvoc filters outliers', function () {
    $request = $this->requestFactory->createServerRequest(
        'GET',
        '/api/readings?limit=0&max_co2=6000&max_tvoc=6000'
    );
    $response = $this->app->handle($request);

    expect($response->getStatusCode())->toBe(200);
    $readings = json_decode((string) $response->getBody(), true);
    expect($readings)->toBeArray();
    // When filtered, no reading should have co2 > 6000 or tvoc > 6000
    foreach ($readings as $r) {
        if (isset($r['co2'])) {
            expect($r['co2'])->toBeLessThanOrEqual(6000);
        }
        if (isset($r['tvoc'])) {
            expect($r['tvoc'])->toBeLessThanOrEqual(6000);
        }
    }
});

test('readings: POST stores a reading (single fermenter)', function () {
    // Ensure we have a current version
    $versionBody = (new StreamFactory())->createStream(json_encode(['version' => '14', 'brew' => 'Test Brew']));
    $this->app->handle(
        $this->requestFactory->createServerRequest('POST', '/api/versions')
            ->withBody($versionBody)
            ->withHeader('Content-Type', 'application/json')
    );

    $body = (new StreamFactory())->createStream(json_encode([
        'date_time' => '2025-02-14 12:00:00',
        'co2' => 1200,
        'tvoc' => 450,
        'temp' => 19.2,
        'rtemp' => 18.5,
        'rhumi' => 65,
        'relay' => 0,
    ]));
    $request = $this->requestFactory->createServerRequest('POST', '/api/readings')
        ->withBody($body)
        ->withHeader('Content-Type', 'application/json');

    $response = $this->app->handle($request);
    expect($response->getStatusCode())->toBe(201);
    $json = json_decode((string) $response->getBody(), true);
    expect($json)->toHaveKey('ok');
    expect($json['ok'])->toBeTrue();

    // Verify via GET
    $getRequest = $this->requestFactory->createServerRequest('GET', '/api/readings?limit=1');
    $getResponse = $this->app->handle($getRequest);
    $readings = json_decode((string) $getResponse->getBody(), true);
    expect($readings)->toBeArray();
    expect($readings)->not->toBeEmpty();
    expect($readings[0])->toMatchArray(['co2' => 1200.0, 'tvoc' => 450.0, 'temp' => 19.2, 'version' => '14']);
});

test('readings: POST requires co2, tvoc, temp', function () {
    $body = (new StreamFactory())->createStream(json_encode(['co2' => 100]));  // missing tvoc, temp
    $request = $this->requestFactory->createServerRequest('POST', '/api/readings')
        ->withBody($body)
        ->withHeader('Content-Type', 'application/json');

    $response = $this->app->handle($request);
    expect($response->getStatusCode())->toBe(400);
    $json = json_decode((string) $response->getBody(), true);
    expect($json)->toHaveKey('error');
});

test('control page: toggle hide_outliers and save persists to API', function () {
    // Set initial state
    $body = (new StreamFactory())->createStream(json_encode(['hide_outliers' => '1']));
    $setRequest = $this->requestFactory->createServerRequest('POST', '/api/config')
        ->withBody($body)
        ->withHeader('Content-Type', 'application/json');
    $this->app->handle($setRequest);

    // Get initial config
    $getRequest = $this->requestFactory->createServerRequest('GET', '/api/config');
    $getResponse = $this->app->handle($getRequest);
    $initial = json_decode((string) $getResponse->getBody(), true);
    $initialValue = $initial['hide_outliers'] ?? '1';

    // Simulate toggle: opposite value
    $newValue = $initialValue === '1' ? '0' : '1';
    $postBody = (new StreamFactory())->createStream(json_encode(['hide_outliers' => $newValue]));
    $postRequest = $this->requestFactory->createServerRequest('POST', '/api/config')
        ->withBody($postBody)
        ->withHeader('Content-Type', 'application/json');
    $postResponse = $this->app->handle($postRequest);

    expect($postResponse->getStatusCode())->toBe(200);
    $after = json_decode((string) $postResponse->getBody(), true);
    expect($after['hide_outliers'])->toBe($newValue);
});
