# Configuration

## Container runtime access

Testcontainers for PHP uses Docker APIs under the hood, so your test process must be able to reach a Docker-compatible daemon.

- Local socket: `unix:///var/run/docker.sock`
- Remote daemon: configure `DOCKER_HOST`

## Environment variables

### `DOCKER_HOST`

Defines where the Docker API is available.  
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
