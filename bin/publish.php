<?php

use Http\Client\Common\Plugin\LoggerPlugin;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PrivatePackagist\ApiClient\Client;
use PrivatePackagist\ApiClient\Exception\HttpTransportException;
use PrivatePackagist\ApiClient\Exception\ResourceNotFoundException;
use PrivatePackagist\ApiClient\HttpClient\HttpPluginClientBuilder;
use Symfony\Component\Mime\MimeTypes;

require_once __DIR__ . '/../vendor/autoload.php';

if (5 !== $argc) {
    throw new \InvalidArgumentException('Command requires four arguments!');
}

$packageName = $argv[1];
$fileNameWithPath = $argv[2];
$organizationUrlName = $argv[3];
$privatePackagistUrl = $argv[4];
$fileName = basename($fileNameWithPath);

if (!file_exists($fileNameWithPath)) {
    throw new \RuntimeException('File not found: ' . $fileNameWithPath);
}

$logger = new Logger('trusted-publishing');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
$httpClientBuilder = new HttpPluginClientBuilder();
$httpClientBuilder->addPlugin(new LoggerPlugin($logger));
$client = new Client(null, $privatePackagistUrl, null, $logger);

if (isset($_SERVER['PRIVATE_PACKAGIST_API_KEY']) && isset($_SERVER['PRIVATE_PACKAGIST_API_SECRET'])) {
    $client->authenticate($_SERVER['PRIVATE_PACKAGIST_API_KEY'], $_SERVER['PRIVATE_PACKAGIST_API_SECRET']);
} else {
    $client->authenticateWithTrustedPublishing($organizationUrlName, $packageName);
}

try {
    $file = file_get_contents($fileNameWithPath);
    $contentType = MimeTypes::getDefault()->guessMimeType($fileNameWithPath);

    try {
        $client->packages()->artifacts()->add($packageName, $file, $contentType, $fileName);

        return;
    } catch (ResourceNotFoundException $e) {
        echo "Package doesn't exist yet. Creating it\n";
    }

    $response = $client->packages()->artifacts()->create($file, $contentType, $fileName);
    $client->packages()->createArtifactPackage([$response['id']]);
} catch (HttpTransportException $e) {
    echo sprintf("Error when calling %s, status code: %s, message: %s\n", $e->getRequestUri(), $e->getCode(), $e->getMessage());
}
