# BWI secure recovery and deployment

## Release gate
Do not deploy this branch or the existing main to production until the Composer-generated lockfile is committed, composer validate and composer audit pass, PHP lint and both finance regression suites pass, and the restored database is reconciled. A patched Livewire alone does not establish that a previously compromised host is clean. Laravel 10 and PHP 8.1 are legacy; migration to supported releases is a separate required production-readiness task.

## Recovery
Keep the old VPS isolated. Retain incident logs and samples separately from business backup. Verify backup checksums off-host and test restore to an isolated, disposable database. Review SQL routines, events, triggers, users and grants before restore. Scan documents for malicious content and never restore old executable source, vendor, cache, service files, or cron blindly. Reconcile transaction totals, ledger balances, loans, installments, infak, expenses, roles and users against independent records. Rotate DB, Redis, SMTP, SSH and other credentials from a clean device; invalidate sessions and review application-key rotation against encrypted data before changing it.

## Web server
Use a supported OS and PHP version compatible with the tested lockfile. Point the document root at public only. The following is an example for an internal Nginx backend with a separate TLS reverse proxy; adapt socket and paths. Preserve existing routes, including Livewire login and updates.

```nginx
server {
    listen 127.0.0.1:8007;
    server_name _;
    root /var/www/bwi/public;
    index index.php;
    autoindex off;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
    location ~* \\.(?:php[0-9]*|phtml|phar)(?:[./]|$) {
        return 404;
    }
    location ~ /\\.(?!well-known(?:/|$)) {
        deny all;
    }
}
```

Restrict public ingress to intended HTTPS proxy and administrator SSH sources. The proxy must overwrite forwarded headers from untrusted clients, and Laravel must trust only known proxies. Do not expose backend 8007. Verify nginx -t before reload. Test allowed application routes and denied executable extensions on staging, without installing a webshell.

## Filesystem and process isolation
Use a separate deployment user. The PHP-FPM worker should have read-only access to app, vendor, routes, config, public and repository root. Only designated runtime storage and, if required, bootstrap/cache may be writable. Never allow the PHP worker to modify .env, .git, application code or executable public assets. Store sensitive uploads privately and serve via authorized controller. Keep PHP execution disabled for storage paths. Use per-app PHP-FPM pool and least-privilege database grants. Do not run Composer or application scripts as root. Use an explicit scheduler and worker configuration, restrict unnecessary outbound connections, and verify logs and alerts.

## Before opening access
Verify composer audit --locked, composer check-platform-reqs, APP_DEBUG=false, HTTPS secure session cookies, rate limits, cross-branch authorization, import/upload validation, financial regression tests, backup/restore, no unexpected PHP files or persistence, and credential rotation. Confirm independent reconciliation and obtain release approval. Do not import malware archives into the new production environment.
