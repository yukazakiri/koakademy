# Script Notes

## `fix-ssl.sh`

`scripts/fix-ssl.sh` rebuilds the local Traefik certificates used by the KoAkademy development domains. Use it when the browser reports certificate trust errors for the local HTTPS hosts.

### Run

```bash
./scripts/fix-ssl.sh
```

### What It Does

1. Verifies `mkcert` is installed and trusted.
2. Backs up the current certificate directory.
3. Regenerates local certificates for the KoAkademy and supporting local domains.
4. Restarts Traefik so the new certificates are picked up.
5. Performs a quick connectivity check.

### Managed Domains

- `admin.koakademy.test`
- `portal.koakademy.test`
- `*.koakademy.test`
- `mailpit.local.test`
- `minio.local.test`
- `minio-console.local.test`
- `*.local.test`

### Common Issues

- `mkcert: command not found`

  Install `mkcert` and run `mkcert -install`.

- Browser still warns after regeneration

  Hard-refresh the page and restart the browser if needed.

- Traefik does not come back cleanly

  Inspect logs:

  ```bash
  docker compose logs -f traefik
  ```

### Relevant Paths

- Script: `scripts/fix-ssl.sh`
- Certificates: `docker/traefik/certs/`
- Backups: `docker/traefik/certs/backup-*/`
