# Security Monitoring

This project logs potential security events to `logs/security.log`. The `Security` class provides helper methods used by API endpoints.

## Features

- **HTTP Security Headers**: `Security::sendHeaders()` sets common security headers like HSTS and CSP.
- **Rate Limiting**: `Security::rateLimit($key, $max, $window)` returns `false` when the request count exceeds the limit.
- **Event Logging**: `Security::logEvent($type, $context = [])` writes structured JSON entries to `logs/security.log`.

See [procedures.md](procedures.md) for our response process when suspicious activity is detected.
