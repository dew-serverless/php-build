# PHP Custom Runtime on Alibaba Cloud Function Compute

The custom runtime supports PHP versions `8.0`, `8.1`, `8.2`, and `8.3`, all
of which are based on the actively maintained PHP versions. Additionally,
Function Compute provides two options of host operating systems: `custom`,
which is built on Debian stretch (9), and `custom.debian10`, which is built
on Debian buster (10).

## Build

Each PHP version is built from a dedicated folder, and is constructed against
two Function Compute environments.

To support future PHP versions, simply duplicate the folder containing the
latest supported PHP version and make necessary modifications until it
functions properly.

Let's say you're building PHP 8.2 and want to test the build:

```bash
make build-php82
```

Under the hood, we use Docker to isolate the building environment, install all
the necessary dependencies PHP requires and compile it from source.

Furthermore, in order to optimize the build and reduce its size, we extract
only the essential files and strip away all symbols and debugging information
from the PHP executables, making it as compact as possible.

Or maybe you're tweaking the one in Debian Buster (10), aka. `custom.debian10`
runtime, append `-debian10` after it.

```bash
make build-php82-debian10
```

Before publishing new version of custom runtime as a layer for all the
supported PHP versions, there's a shortcut for building all the PHP versions
we currently support.

```bash
make build
```
