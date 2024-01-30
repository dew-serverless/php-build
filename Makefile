OBJECTS = php80 php81 php82 php83
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
	php publish/publish.php $*

publish: $(addprefix publish-,$(VARIANTS))

clean:
	rm -rf ./export/php*
