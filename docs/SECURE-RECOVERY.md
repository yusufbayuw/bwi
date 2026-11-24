# BWI secure recovery and deployment

## Release gate
Do not deploy this branch or the existing main to production until the Composer-generated lockfile is committed, composer validate and composer audit pass, PHP lint and both finance regression suites pass, and the restored database is reconciled. A patched Livewire alone does not establish that a previously compromised host is clean. The emergency modernization targets PHP 8.4, Laravel 12 and Filament 3 with patched Livewire 3.8.12 or newer. Laravel 12 receives security fixes until 2027-02-24; plan the next framework upgrade before that date.

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


## Attachment recovery (no schema change)

The historical disk name "public" and physical directory storage/app/public are
retained for compatibility with existing filenames, but the disk is private
and no storage symlink is configured. The application authorizes each read
through /bwi/attachments/{filename}. General Berkas has no branch column and
remains global for users with its existing Shield permission; a path shared
between records is accessible only when every reference is authorized.
The controller checks the actual MIME, extension and canonical local path;
unreferenced files, path traversal, symlinks and unsupported formats are denied.
PDFs download; images can be previewed in native Filament. New attachments
accept PDF/JPG/JPEG/PNG up to 10 MiB, with server-generated filenames.
Manual non-system reports now upload a file instead of accepting arbitrary
paths. The XLSX import remains separate and accepts only an uploaded temporary
file.

Before exposing any traffic:
- Keep the 17 already scanned files in storage/app/public with their existing
  names and database references; do NOT run storage:link. Back up and reconcile
  them against the DB. Do not restore cache, vendor or executable files.
- Inspect public/storage. If it is a symlink, remove the symlink only; if it is
  a real directory, quarantine and reconcile its contents before removal.
- In the backend nginx server add:
      location = /storage { return 404; }
      location ^~ /storage/ { return 404; }
  This is defense in depth. Nginx must not have access to the attachment root.
- Purge previously cached /storage URLs from the CDN. Configure CDN cache
  bypass for /bwi/attachments/* and /bwi/laporan/*; never cache authenticated
  responses or Livewire requests. Previously disclosed data cannot be recalled.
- Run migrate:status, bwi:audit-ledger, bwi:secure-reports (dry run only).
  No migration or change to transaction history is needed for this PR.
- Verify login, Livewire upload and edit with unchanged legacy attachments,
  image preview, PDF download and denied access from guest, wrong branch and
  missing-permission accounts. Check each of the 17 files through an authorized
  account; investigate orphan, shared, missing or unsupported files manually.
- The trusted proxy list assumes a loopback nginx reverse proxy as documented
  above. Do not accept spoofed forwarding headers. Limit origin HTTPS ingress
  to the actual CDN network; do not expose port 8007.
- Run all CI checks on the candidate commit; passing CI is not evidence that the
  previously compromised host or all historical financial records are clean.
