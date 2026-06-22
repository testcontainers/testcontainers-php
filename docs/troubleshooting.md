# Troubleshooting

For runtime endpoint settings, see [configuration](configuration.md).

## Docker daemon is not reachable

Symptoms:

- container start fails immediately
- Docker API errors from runtime client

Checks:

- Ensure Docker is running.
- Verify socket/endpoint access:

```bash
docker version
docker info
```

- Set `DOCKER_HOST` when not using the default local socket:

```bash
export DOCKER_HOST=unix:///var/run/docker.sock
```

## Container starts but app is not ready

`GenericContainer` uses a running-state wait strategy by default.  
For real readiness, add an explicit wait strategy (`WaitForLog`, `WaitForExec`, `WaitForHttp`, `WaitForHostPort`, `WaitForHealthCheck`).

See [wait strategies](features/wait-strategies.md) for examples.

## Wrong host/port in CI or Docker-in-Docker

If mapped ports are reachable but host resolution is wrong, set:

```bash
export TESTCONTAINERS_HOST_OVERRIDE=127.0.0.1
```

Use a value that is reachable from your test process.

## Private registry pull failures

If image pull fails for private registries, provide Docker auth config through:

```bash
export DOCKER_AUTH_CONFIG='{"auths":{"registry.example.com":{"auth":"<base64-user-pass>"}}}'
```

or configure credentials in Docker config files (`~/.docker/config.json`).

## Podman-specific networking issues

When using Podman with Docker-compatible APIs, host/network behavior can differ from Docker.  
If startup succeeds but connections fail, check `DOCKER_HOST`, network mode, and host override settings.
