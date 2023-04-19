.PHONY: build export clean

build:
	docker build -t dew/php82 -f php82/Dockerfile .

export: build
	CID=$$(docker create dew/php82) && docker cp $$CID:/opt/dew ./export/php82 && docker rm $$CID

clean:
	rm -rf ./export/php82
