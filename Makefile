OSS_BUCKET=

OBJECTS = php81 php82
VARIANTS = $(OBJECTS) $(addsuffix -debian10,$(OBJECTS))

.PHONY: build export publish clean

build-%:
	docker build -t dew/$* -f $*/Dockerfile .

build: $(addprefix build-,$(VARIANTS))

export/%:
	CID=$$(docker create dew/$*) && docker cp $$CID:/opt ./export/$* && docker rm $$CID
	cd export/$*; zip -r ../$*.zip .

export: $(addprefix export/,$(VARIANTS))

publish-%: export/%
	aliyun oss cp export/$*.zip oss://$(OSS_BUCKET)/$*.zip
	aliyun fc-open CreateLayerVersion \
		--layerName $* \
		--body "{\"Code\":{\"ossBucketName\":\"$(OSS_BUCKET)\",\"ossObjectName\":\"$*.zip\"},\"compatibleRuntime\":[\"$(if $(findstring -debian10,$*),custom.debian10,custom)\"]}"

publish: $(addprefix publish-,$(VARIANTS))

clean:
	rm -rf ./export/php*
