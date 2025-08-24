<?php

require_once __DIR__.'/vendor/autoload.php';

use Dew\Acs\Fc\FcClient;
use Dew\Acs\Oss\OssClient;

// Function Compute available regions
//
// See: https://www.alibabacloud.com/help/zh/functioncompute/fc-3-0/product-overview/supported-regions
// See: https://www.alibabacloud.com/help/en/functioncompute/fc-3-0/product-overview/supported-regions
$regions = [
    'cn-hangzhou',
    'cn-shanghai',
    'cn-qingdao',
    'cn-beijing',
    'cn-zhangjiakou',
    'cn-huhehaote',
    'cn-wulanchabu',
    'cn-shenzhen',
    'cn-chengdu',
    'cn-hongkong',
    'ap-southeast-1',
    'ap-southeast-3',
    'ap-southeast-5',
    'ap-southeast-7',
    'ap-northeast-1',
    'ap-northeast-2',
    'eu-central-1',
    'eu-west-1',
    'us-east-1',
    'us-west-1',
    'me-central-1',
];

$bucket = getenv('OSS_BUCKET');

$accessKeyId = getenv('ACS_ACCESS_KEY_ID');
$accessKeySecret = getenv('ACS_ACCESS_KEY_SECRET');

$variant = $argv[1] ?? '';
$release = $argv[2] ?? '';
$filename = __DIR__.'/../export/'.$variant.'.zip';
$objectName = sprintf('%s-%s.zip', $variant, normalizeRelease($release));

if (! $bucket) {
    fatal('Expect the base name of OSS bucket');
}

if (! ($accessKeyId && $accessKeySecret)) {
    fatal('Expect ACS credentials');
}

if ($variant === '') {
    fatal('Expect a variant name, e.g. php84-debian11');
}

if ($release === '') {
    fatal('Expect a release version, e.g. v2025.1');
}

if (! file_exists($filename)) {
    fatal('The runtime package is missing');
}

function fatal(string $message): void
{
    printf('[!] %s'.PHP_EOL, $message);

    exit(1);
}

function step(string $message): void
{
    printf('[-] %s'.PHP_EOL, $message);
}

function normalizeRelease(string $release): string
{
    // Ensure the release has the leading 'v'
    $normalized = str_starts_with($release, 'v') ? $release : 'v'.$release;

    // Then, we get rid of the leading 'v'
    $normalized = substr($normalized, 1);

    // Replace the dot with an underscore
    $normalized = str_replace('.', '_', $normalized);

    return $normalized;
}

function createOssClient(string $key, string $secret, string $region): OssClient
{
    return new OssClient([
        'credentials' => [
            'key' => $key,
            'secret' => $secret,
        ],
        'region' => $region,
        'endpoint' => sprintf('oss-%s.aliyuncs.com', $region),
    ]);
}

function createFcClient(string $key, string $secret, string $region): FcClient
{
    return new FcClient([
        'credentials' => [
            'key' => $key,
            'secret' => $secret,
        ],
        'region' => $region,
        'endpoint' => sprintf('fcv3.%s.aliyuncs.com', $region),
    ]);
}

function fileExists(OssClient $client, string $bucket, string $object, string $filename, string $md5): bool
{
    try {
        $client->headObject([
            'bucket' => $bucket,
            'key' => $object,
            'If-Match' => strtoupper($md5),
        ]);

        return true;
    } catch (Throwable) {
        return false;
    }
}

function fileUpload(OssClient $client, string $bucket, string $object, string $filename, string $md5): void
{
    $client->putObject([
        'bucket' => $bucket,
        'key' => $object,
        'body' => file_get_contents($filename),
        '@headers' => [
            'Content-MD5' => $md5,
        ],
    ]);
}

function fileChecksum(string $filename): string
{
    $contents = file_get_contents($filename);

    return Crc64::make($contents);
}

function layerExists(FcClient $client, string $variant, string $checksum): bool
{
    $data = $client->listLayers([
        'prefix' => $variant,
        'limit' => 1,
        'official' => 'false', // A type of string by API definition
    ])->getDecodedData();

    if ($data['layers'] === []) {
        return false;
    }

    if ($data['layers'][0]['codeChecksum'] !== $checksum) {
        return false;
    }

    return true;
}

function layerPublish(FcClient $client, string $variant, string $bucket, string $object, string $checksum): void
{
    $client->createLayerVersion([
        'layerName' => $variant,
        'body' => [
            'code' => [
                'ossBucketName' => $bucket,
                'ossObjectName' => $object,
                'checksum' => $checksum,
            ],
            'compatibleRuntime' => [
                getRuntimeFromLayerName($variant),
            ],
            'license' => 'MIT',
        ],
    ]);
}

function layerEnsureIsPublic(FcClient $client, string $variant): void
{
    $client->putLayerACL([
        'layerName' => $variant,

        // Allowed values:
        // '0': private (default)
        // '1': public
        'acl' => '1',
    ]);
}

function getRuntimeFromLayerName(string $layerName): string
{
    return match (true) {
        str_ends_with($layerName, '-debian12') => 'custom.debian12',
        str_ends_with($layerName, '-debian11') => 'custom.debian11',
        str_ends_with($layerName, '-debian10') => 'custom.debian10',
        default => 'custom',
    };
}

step("Process {$variant} runtime");

$crc64 = fileChecksum($filename);
$md5 = md5_file($filename);
$md5Base64 = base64_encode(md5_file($filename, true));

foreach ($regions as $region) {
    $bucketName = $bucket.'-'.$region;

    $oss = createOssClient($accessKeyId, $accessKeySecret, $region);
    $fc = createFcClient($accessKeyId, $accessKeySecret, $region);

    if (fileExists($oss, $bucketName, $objectName, $filename, $md5)) {
        step("Upload layer to region {$region} (exists)");
    } else {
        step("Upload layer to region {$region}");
        fileUpload($oss, $bucketName, $objectName, $filename, $md5Base64);
    }

    // Instead of partially uploading the layer package one step at a time,
    // we load the full contents of the file into memory, since each one
    // is about 50MiB, we force garbage collection and free up memory.
    gc_collect_cycles();

    if (layerExists($fc, $variant, $crc64)) {
        step("Release layer to region {$region} (exists)");
    } else {
        step("Release layer to region {$region}");
        layerPublish($fc, $variant, $bucketName, $objectName, $crc64);
    }

    step("Ensure layer is public in {$region}");
    layerEnsureIsPublic($fc, $variant);
}

step('Publish is done');
