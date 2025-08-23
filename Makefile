OBJECTS = php81 php82 php83 php84
VARIANTS = $(addsuffix -debian11,$(OBJECTS)) $(addsuffix -debian12,$(OBJECTS))
DOCKER_REGISTRY ?= ghcr.io
DOCKER_IMAGE ?= dew-serverless/php
DOCKER_BUILD_EXTRA ?=

.PHONY: build export publish clean

build-%:
	BUILD_ARGS="$(shell awk '/^[a-zA-Z0-9]+ *=/ { printf "--build-arg %s_VERSION=%s ", toupper($$1), $$3 }' "$*/dependencies.ini" | xargs)" && \
	docker buildx build $$BUILD_ARGS $(DOCKER_BUILD_EXTRA) \
		--load \
		-t $(DOCKER_BUILD_EXTRA)/$(DOCKER_IMAGE):$(subst php,,$*) \
		.

build: $(addprefix build-,$(VARIANTS))

test-setup:
	cd tests; \
	composer install --prefer-dist

test-%:
	cd tests; \
	DEW_PHP_VERSION="$*" composer run test

test: test-setup $(addprefix test-,$(VARIANTS))

export/%:
	CID=$$(docker create $(DOCKER_REGISTRY)/$(DOCKER_IMAGE):$(subst php,,$*)) && \
	docker cp $$CID:/opt ./export/$* && \
	docker rm $$CID && \
	cd export/$* && zip -r ../$*.zip .

export: $(addprefix export/,$(VARIANTS))

publish-%: export/%
	php publish/publish.php $* $(RELEASE)

publish: $(addprefix publish-,$(VARIANTS))

push-%: build-%
	docker push $(DOCKER_REGISTRY)/$(DOCKER_IMAGE):$(subst php,,$*)

push: $(addprefix push-,$(VARIANTS))

clean:
	rm -rf ./export/php*
