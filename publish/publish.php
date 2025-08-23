<?php

require_once __DIR__.'/vendor/autoload.php';

use Dew\Acs\Fc\FcClient;
use Dew\Acs\Oss\OssClient;

// Function Compute available regions
//
// See: https://www.alibabacloud.com/help/zh/functioncompute/fc-3-0/product-overview/supported-regions
// See: https://www.alibabacloud.com/help/en/functioncompute/fc-3-0/product-overview/supported-regions
$regions = [
    'ap-northeast-1',
    'ap-northeast-2',
    'ap-southeast-1',
    'ap-southeast-3',
    'ap-southeast-5',
    'ap-southeast-7',
    'cn-beijing',
    'cn-chengdu',
    'cn-hangzhou',
    'cn-hongkong',
    'cn-huhehaote',
    'cn-qingdao',
    'cn-shanghai',
    'cn-shenzhen',
    'cn-zhangjiakou',
    'eu-central-1',
    'eu-west-1',
    'us-east-1',
    'us-west-1',
];

$bucket = getenv('OSS_BUCKET');

$accessKeyId = getenv('ACS_ACCESS_KEY_ID');
$accessKeySecret = getenv('ACS_ACCESS_KEY_SECRET');

$runtime = $argv[1] ?? null;
$runtimePath = __DIR__.'/../export/'.$runtime.'.zip';
$objectName = $runtime.'.zip';

if (! $bucket) {
    fatal('Expect the base name of OSS bucket');
}

if (! ($accessKeyId && $accessKeySecret)) {
    fatal('Expect ACS credentials');
}

if (! $runtime) {
    fatal('Expect runtime name, e.g. php84-debian11');
}

if (! file_exists($runtimePath)) {
    fatal('The runtime layer package is missing');
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

function createOssClient(string $key, string $secret, string $region): OssClient
{
    return new OssClient([
        'credentials' => [
            'key' => $key,
            'secret' => $secret,
        ],
        'region' => $region,
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

function fileExists(OssClient $client, string $bucket, string $object, string $filename): bool
{
    try {
        $client->headObject([
            'bucket' => $bucket,
            'key' => $object,
            'If-Match' => strtoupper(md5_file($filename)),
        ]);

        return true;
    } catch (Throwable) {
        return false;
    }
}

function fileUpload(OssClient $client, string $bucket, string $object, string $filename): void
{
    $client->putObject([
        'bucket' => $bucket,
        'key' => $object,
        'body' => file_get_contents($filename),
    ]);
}

function fileChecksum(string $filename): int
{
    $contents = file_get_contents($filename);

    return Crc64::make($contents);
}

function layerExists(FcClient $client, string $runtime, string $checksum): bool
{
    $data = $client->listLayers([
        'prefix' => $runtime,
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

function layerPublish(FcClient $client, string $runtime, string $bucket, string $object, string $checksum): void
{
    $client->createLayerVersion([
        'layerName' => $runtime,
        'body' => [
            'code' => [
                'ossBucketName' => $bucket,
                'ossObjectName' => $object,
                'checksum' => $checksum,
            ],
            'compatibleRuntime' => [
                getRuntimeFromLayerName($runtime),
            ],
            'license' => 'MIT',
        ],
    ]);
}

function layerEnsureIsPublic(FcClient $client, string $runtime): void
{
    $client->putLayerACL([
        'layerName' => $runtime,

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

step("Process {$runtime} runtime");

$crc64 = fileChecksum($runtimePath);

foreach ($regions as $region) {
    $bucketName = $bucket.'-'.$region;

    $oss = createOssClient($accessKeyId, $accessKeySecret, $region);
    $fc = createFcClient($accessKeyId, $accessKeySecret, $region);

    if (fileExists($oss, $bucketName, $objectName, $runtimePath)) {
        step("Upload layer to region {$region} (exists)");
    } else {
        step("Upload layer to region {$region}");
        fileUpload($oss, $bucketName, $objectName, $runtimePath);
    }

    // Instead of partially uploading the layer package one step at a time,
    // we load the full contents of the file into memory, since each one
    // is about 50MiB, we force garbage collection and free up memory.
    gc_collect_cycles();

    if (layerExists($fc, $runtime, $crc64)) {
        step("Release layer to region {$region} (exists)");
    } else {
        step("Release layer to region {$region}");
        layerPublish($fc, $runtime, $bucketName, $objectName, $crc64);
    }

    step("Ensure layer is public in {$region}");
    layerEnsureIsPublic($fc, $runtime);
}

step('Publish is done');
