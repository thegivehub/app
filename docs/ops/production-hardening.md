# Production Hardening Checklist

- Enforce HTTPS end-to-end (reverse proxy or TLS termination)
- Set `DB_ENCRYPTION_ENABLED=true` if at-rest encryption enabled
- Ensure `.env` permissions are `0600`
- Ensure logs/backups directories are not world-writable (0750)
- Sessions: `session.cookie_httponly=1`, `session.cookie_samesite=Lax|Strict`, `session.cookie_secure=1` in production
- Disable sensitive data logging (`LOG_SENSITIVE_DATA=false`)
- Attach contract audit report when available
- Consider enabling multi-signature for blockchain ops per risk profile

