<?php

require_once __DIR__.'/vendor/autoload.php';

use AlibabaCloud\SDK\FCOpen\V20210406\FCOpen;
use AlibabaCloud\SDK\FCOpen\V20210406\Models\CreateLayerVersionRequest;
use Darabonba\OpenApi\Models\Config;
use OSS\OssClient;

$regions = [
    'cn-hangzhou',
    'cn-shanghai',
    'cn-qingdao',
    'cn-beijing',
    'cn-zhangjiakou',
    'cn-huhehaote',
    'cn-shenzhen',
    'cn-chengdu',
    'cn-hongkong',
    'ap-northeast-1',
    'ap-northeast-2',
    'ap-southeast-1',
    'ap-southeast-2',
    'ap-southeast-3',
    'ap-southeast-5',
    'ap-southeast-7',
    'ap-south-1',
    'eu-central-1',
    'eu-west-1',
    'us-west-1',
    'us-east-1',
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

print "# Process {$runtime} runtime\n";

foreach ($regions as $region) {
    $bucketName = $bucket.'-'.$region;

    $oss = new OssClient($accessKeyId, $accessKeySecret, sprintf('oss-%s.aliyuncs.com', $region));

    if ($oss->doesObjectExist($bucketName, $objectName)) {
        $object = $oss->getSimplifiedObjectMeta($bucketName, $objectName);

        $remoteEtag = strtolower(trim($object['etag'], '"'));
        $localEtag = md5_file($runtimePath);

        if ($remoteEtag === $localEtag) {
            print "! The runtime has been deployed to region {$region}\n";

            continue;
        }
    }

    print "- Upload layer to region {$region}\n";

    $oss->uploadFile($bucketName, $objectName, $runtimePath);

    print "- Release layer to region {$region}\n";

    $fc = new FCOpen(new Config([
        'accessKeyId' => $accessKeyId,
        'accessKeySecret' => $accessKeySecret,
        'endpoint' => sprintf('%s.%s.fc.aliyuncs.com', $accountId, $region),
    ]));

    $fc->createLayerVersion($runtime, new CreateLayerVersionRequest([
        'compatibleRuntime' => [
            str_contains($runtime, '-debian10') ? 'custom.debian10' : 'custom',
        ],
        'code' => [
            'ossBucketName' => $bucketName,
            'ossObjectName' => $objectName,
        ],
    ]));
}

print "- Publish done\n";
