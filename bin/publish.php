<?php

use PrivatePackagist\ApiClient\Client;
use PrivatePackagist\ApiClient\Exception\HttpTransportException;
use PrivatePackagist\ApiClient\Exception\ResourceNotFoundException;
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

$client = new Client(null, $privatePackagistUrl);

if (isset($_SERVER['PRIVATE_PACKAGIST_API_KEY']) && isset($_SERVER['PRIVATE_PACKAGIST_API_SECRET'])) {
    $client->authenticate($_SERVER['PRIVATE_PACKAGIST_API_KEY'], $_SERVER['PRIVATE_PACKAGIST_API_SECRET']);
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
