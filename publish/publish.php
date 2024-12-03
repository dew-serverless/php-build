<?php

require_once __DIR__.'/vendor/autoload.php';

use AlibabaCloud\SDK\FC\V20230330\FC;
use AlibabaCloud\SDK\FC\V20230330\Models\CreateLayerVersionRequest;
use AlibabaCloud\Tea\Model;
use Darabonba\OpenApi\Models\Config;
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
$accountId = getenv('ACS_ACCOUNT_ID');

$runtime = $argv[1] ?? null;
$runtimePath = __DIR__.'/../export/'.$runtime.'.zip';
$objectName = $runtime.'.zip';

if (! $bucket) {
    print "Expect OSS bucket.\n";
    exit(1);
}

if (! ($accessKeyId && $accessKeySecret && $accountId)) {
    print "Expect ACS credentials.\n";
    exit(1);
}

if (! $runtime) {
    print "Expect runtime name.\n";
    exit(1);
}

if (! file_exists($runtimePath)) {
    print "Missing runtime layer file.\n";
    exit(1);
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

print "# Process {$runtime} runtime\n";

foreach ($regions as $region) {
    $bucketName = $bucket.'-'.$region;

    $oss = createOssClient($accessKeyId, $accessKeySecret, $region);

    if (fileExists($oss, $bucketName, $objectName, $runtimePath)) {
        print "- Layer uploaded to region {$region}\n";
    } else {
        print "- Upload layer to region {$region}\n";
        fileUpload($oss, $bucketName, $objectName, $runtimePath);
    }

    // Instead of partially uploading the layer package one step at a time,
    // we load the full contents of the file into memory, since each one
    // is about 50MiB, we force garbage collection and free up memory.
    gc_collect_cycles();

    $fc = new FC(new Config([
        'accessKeyId' => $accessKeyId,
        'accessKeySecret' => $accessKeySecret,
        'endpoint' => sprintf('%s.%s.fc.aliyuncs.com', $accountId, $region),
    ]));

    print "- Release layer to region {$region}\n";

    $fc->createLayerVersion($runtime, new CreateLayerVersionRequest([
        'body' => [
            'code' => [
                'ossBucketName' => $bucketName,
                'ossObjectName' => $objectName,
            ],
            'compatibleRuntime' => [
                str_contains($runtime, '-debian10') ? 'custom.debian10' : 'custom',
            ],
            'license' => 'MIT',
        ],
    ]));

    print "- Ensure layer is public in {$region}\n";

    $fc->putLayerACL($runtime, new class extends Model {
        public string $public = 'true';

        // intentionally set because of the SDK bug
        public string $public_ = 'true';
    });
}

print "- Publish done\n";
