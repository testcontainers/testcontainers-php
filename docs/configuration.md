# Configuration

## Container runtime access

Testcontainers for PHP talks to a Docker-compatible runtime through one of two exchangeable adapters:

| Adapter | `TESTCONTAINERS_CLIENT` | How it works | Use when |
|---------|-------------------------|--------------|----------|
| API (default) | `api` | Docker Engine HTTP API over the socket or TCP endpoint from `DOCKER_HOST` | Docker Desktop, Docker Engine, OrbStack, Colima, Podman with its API service enabled |
| CLI | `cli` | Runs a Docker-compatible binary (`docker`, `podman`, `nerdctl`, ...) as a subprocess | The daemon socket is not reachable from PHP, e.g. rootless Podman without `podman system service`, remote Docker contexts, or SSH-based setups |

Both adapters implement `Testcontainers\Docker\DockerClientInterface`, so containers, modules and wait strategies behave the same regardless of the adapter.

### Using Podman

Podman ships a Docker-compatible API socket. Either point the default adapter at it:

```bash
systemctl --user enable --now podman.socket
export DOCKER_HOST=unix://$XDG_RUNTIME_DIR/podman/podman.sock
```

or skip the socket entirely and drive the `podman` binary:

```bash
export TESTCONTAINERS_CLIENT=cli
export TESTCONTAINERS_CLI_BINARY=podman
```

### Selecting an adapter in code

The adapter can also be set programmatically before the first container is started:

```php
use Testcontainers\ContainerClient\DockerContainerClient;
use Testcontainers\Docker\Cli\CliDockerClient;

DockerContainerClient::setDockerClient(new CliDockerClient('podman'));
```

Any class implementing `DockerClientInterface` can be injected this way, which allows custom adapters.

## Environment variables

### `TESTCONTAINERS_CLIENT`

Selects the runtime adapter: `api` (default) or `cli`.

```bash
export TESTCONTAINERS_CLIENT=cli
```

### `TESTCONTAINERS_CLI_BINARY`

Binary used by the `cli` adapter. Defaults to `docker`. The subprocess inherits your environment, so `DOCKER_CONTEXT`, `DOCKER_HOST` or Podman's `CONTAINER_HOST` apply as usual.

```bash
export TESTCONTAINERS_CLI_BINARY=podman
```

### `DOCKER_HOST`

Defines where the Docker API is available for the `api` adapter.  
Examples:

```bash
export DOCKER_HOST=tcp://127.0.0.1:2375
export DOCKER_HOST=unix:///var/run/docker.sock
```

### `TESTCONTAINERS_HOST_OVERRIDE`

Overrides the host address returned by Testcontainers when your tests need a custom endpoint:

```bash
export TESTCONTAINERS_HOST_OVERRIDE=127.0.0.1
```

## Running inside containers

If your tests run inside another container:

- Mount the Docker socket.
- Ensure network routing from test container to started containers is valid.
- Set host overrides when needed for your CI/network topology.

For startup and connectivity failures, see [troubleshooting](troubleshooting.md).
