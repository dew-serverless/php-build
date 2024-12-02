OBJECTS = php80 php81 php82 php83 php84
VARIANTS = $(OBJECTS) $(addsuffix -debian10,$(OBJECTS))

.PHONY: build export publish clean

build-%:
	VARIANT="$(shell echo $* | sed -E 's/^php[0-9]+//')" && \
	BUILD_ARGS="$(shell awk '/^[a-zA-Z0-9]+ *=/ { printf "--build-arg %s_VERSION=%s ", toupper($$1), $$3 }' "$*/dependencies.ini" | xargs)" && \
	docker build $$BUILD_ARGS -t dew/custom$$VARIANT-builder -f custom$$VARIANT-builder/Dockerfile . && \
	docker build $$BUILD_ARGS -t dew/$* -t ghcr.io/dew-serverless/php:$(subst php,,$*) -f $*/Dockerfile .

build: $(addprefix build-,$(VARIANTS))

test-setup:
	cd tests; \
	composer install

test-%:
	cd tests; \
	DEW_PHP_VERSION="$*" composer run test

test: test-setup $(addprefix test-,$(VARIANTS))

export/%:
	CID=$$(docker create dew/$*) && docker cp $$CID:/opt ./export/$* && docker rm $$CID
	cd export/$*; zip -r ../$*.zip .

export: $(addprefix export/,$(VARIANTS))

publish-%: export/%
	php publish/publish.php $*

publish: $(addprefix publish-,$(VARIANTS))

push-%: build-%
	docker push ghcr.io/dew-serverless/php:$(subst php,,$*)

push: $(addprefix push-,$(VARIANTS))

clean:
	rm -rf ./export/php*
