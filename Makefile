OSS_BUCKET=

.PHONY: build export zip upload publish clean

build:
	docker build -t dew/php82 -f php82/Dockerfile .
	docker build -t dew/php82-debian10 -f php82-debian10/Dockerfile .
	docker build -t dew/php81 -f php81/Dockerfile .
	docker build -t dew/php81-debian10 -f php81-debian10/Dockerfile .

export: build
	CID=$$(docker create dew/php82) && docker cp $$CID:/opt/dew ./export/php82 && docker rm $$CID
	CID=$$(docker create dew/php82-debian10) && docker cp $$CID:/opt ./export/php82-debian10 && docker rm $$CID
	CID=$$(docker create dew/php81) && docker cp $$CID:/opt ./export/php81 && docker rm $$CID
	CID=$$(docker create dew/php81-debian10) && docker cp $$CID:/opt ./export/php81-debian10 && docker rm $$CID

zip: export/php82 export/81-debian10
	cd export/php82; zip -r ../php82.zip .
	cd export/php82-debian10; zip -r ../php82-debian10.zip .
	cd export/php81; zip -r ../php81.zip .
	cd export/php81-debian10; zip -r ../php81-debian10.zip .

upload: export/php82.zip export/php82-debian10.zip export/php81.zip export/81-debian10.zip
	aliyun oss cp export/php82.zip oss://$(OSS_BUCKET)/php82.zip
	aliyun oss cp export/php82-debian10.zip oss://$(OSS_BUCKET)/php82-debian10.zip
	aliyun oss cp export/php81.zip oss://$(OSS_BUCKET)/php81.zip
	aliyun oss cp export/php81-debian10.zip oss://$(OSS_BUCKET)/php81-debian10.zip

publish:
	aliyun fc-open CreateLayerVersion --layerName php82 --body "{\"Code\":{\"ossBucketName\":\"$(OSS_BUCKET)\",\"ossObjectName\":\"php82.zip\"},\"compatibleRuntime\":[\"custom\"]}"
	aliyun fc-open CreateLayerVersion --layerName php82-debian10 --body "{\"Code\":{\"ossBucketName\":\"$(OSS_BUCKET)\",\"ossObjectName\":\"php82-debian10.zip\"},\"compatibleRuntime\":[\"custom.debian10\"]}"
	aliyun fc-open CreateLayerVersion --layerName php81 --body "{\"Code\":{\"ossBucketName\":\"$(OSS_BUCKET)\",\"ossObjectName\":\"php81.zip\"},\"compatibleRuntime\":[\"custom\"]}"
	aliyun fc-open CreateLayerVersion --layerName php81-debian10 --body "{\"Code\":{\"ossBucketName\":\"$(OSS_BUCKET)\",\"ossObjectName\":\"php81-debian10.zip\"},\"compatibleRuntime\":[\"custom.debian10\"]}"

clean:
	rm -rf ./export/php82*
	rm -rf ./export/php81*
