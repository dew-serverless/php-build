# PHP Custom Runtime on Alibaba Cloud Function Compute

This repository contains PHP build scripts tailored for _Function Compute_,
starting with PHP `8.0`.

_Function Compute_ supports three distinct custom runtimes: `custom`,
`custom.debian10`, and `custom.debian11`. As their names suggest, these
runtimes are based on Debian 9, Debian 10, and Debian 11, respectively.

## Build

Each PHP version is built using a specific builder aligned with its runtime
environment. Additionally, dedicated folders are provided to handle edge cases
and runtime-specific customizations.

To support future PHP versions, simply duplicate the folder containing the
latest supported PHP version and make necessary modifications until it
functions properly.

Let's say you're building PHP 8.4 with `custom.debian11` runtime environment
and want to test the build:

```bash
make build-php84-debian11
```

Under the hood, we use Docker to isolate the building environment, install all
the necessary dependencies PHP requires and compile it from source.

Furthermore, in order to optimize the build and reduce its size, we extract
only the essential files and strip away all symbols and debugging information
from the PHP executables, making it as compact as possible.

Before publishing new version of custom runtime as a layer for all the
supported PHP versions, there's a shortcut for building all the PHP versions
we currently support.

```bash
make build
```
