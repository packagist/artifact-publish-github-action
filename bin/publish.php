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

// The package and organization name are interpolated into the API request paths, so a value
// outside these patterns would silently address a different endpoint.
if (!preg_match('{^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]?|-{0,2})[a-z0-9]+)*$}iD', $packageName)) {
    throw new \InvalidArgumentException(sprintf('Invalid package name "%s", expected a Composer package name in the form "vendor/name".', $packageName));
}

if (!preg_match('{^[a-z0-9-]+$}D', $organizationUrlName)) {
    throw new \InvalidArgumentException(sprintf('Invalid organization URL name "%s", expected the URL name of your Private Packagist organization, which consists of lowercase letters, digits and dashes, e.g. "acme-org".', $organizationUrlName));
}

// An empty value keeps the API client's own default of https://packagist.com.
if ('' !== $privatePackagistUrl) {
    $url = parse_url($privatePackagistUrl);

    if (false === $url || !isset($url['scheme'], $url['host']) || !in_array(strtolower($url['scheme']), ['http', 'https'], true)) {
        throw new \InvalidArgumentException(sprintf('Invalid Private Packagist URL "%s", expected an absolute http or https URL, e.g. "https://packagist.com".', $privatePackagistUrl));
    }

    // The API client only applies scheme, host and port to its requests, everything else would
    // be dropped without notice.
    $hasPath = isset($url['path']) && '' !== $url['path'] && '/' !== $url['path'];
    if ($hasPath || isset($url['query']) || isset($url['fragment']) || isset($url['user']) || isset($url['pass'])) {
        throw new \InvalidArgumentException(sprintf('Invalid Private Packagist URL "%s", only the scheme, host and port are used, remove everything else.', $privatePackagistUrl));
    }
}

// Everything the artifact argument is checked for, cheapest first so a malformed value never
// reaches the filesystem.
if ('' === $fileNameWithPath) {
    throw new \InvalidArgumentException('No artifact given, expected the full path to the artifact file.');
}

// Keep this a plain filesystem path. A stream wrapper like phar:// passes the checks below but
// reads its content from somewhere else entirely.
if (preg_match('{^[a-z][a-z0-9+.-]*://}i', $fileNameWithPath)) {
    throw new \InvalidArgumentException(sprintf('Invalid artifact "%s", expected a filesystem path without a stream wrapper.', $fileNameWithPath));
}

// The file name is sent as a request header, which cannot carry control characters.
if (preg_match('{[\x00-\x1f\x7f]}', $fileName)) {
    throw new \InvalidArgumentException(sprintf('Invalid artifact file name "%s", it must not contain control characters.', $fileName));
}

if (!file_exists($fileNameWithPath)) {
    throw new \RuntimeException('File not found: ' . $fileNameWithPath);
}

if (!is_file($fileNameWithPath)) {
    throw new \RuntimeException('Not a file: ' . $fileNameWithPath);
}

if (!is_readable($fileNameWithPath)) {
    throw new \RuntimeException('File not readable: ' . $fileNameWithPath);
}

if (0 === filesize($fileNameWithPath)) {
    throw new \RuntimeException('File is empty: ' . $fileNameWithPath);
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
    if (false === $file) {
        throw new \RuntimeException('Failed to read file: ' . $fileNameWithPath);
    }

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
    if (404 === $e->getCode() && false !== strpos((string) $e->getRequestUri(), '/api/oidc/audience/')) {
        echo "Your Private Packagist installation does not support organization specific OIDC audiences. Upgrade to 2.0.36 or newer.\n";
        exit(1);
    }

    echo sprintf("Error when calling %s, status code: %s, message: %s\n", $e->getRequestUri(), $e->getCode(), $e->getMessage());
    exit(1);
}
