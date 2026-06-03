# Environment Management

This guide describes how to configure staging and production environments.

Templates
- `.env.staging.example`: default variables for staging
- `.env.production.example`: default variables for production

Switch environment
```bash
scripts/switch-env.sh staging   # or: production
```

Compose overrides
Use overrides per environment to set `APP_ENV` and ports:
```bash
# Staging
docker compose -f docker-compose.yaml -f docker-compose.staging.yaml up -d --build

# Production
docker compose -f docker-compose.yaml -f docker-compose.prod.yaml up -d --build
```

Key variables
- `APP_ENV`: `staging` or `production`
- `BASE_URL`: canonical app URL for the environment
- `JWT_SECRET`: strong 64+ char hex string
- `MONGODB_*`: DB connection settings
- `DEFAULT_DONATION_CURRENCY`: XLM/ETH/BTC, etc.
- `ENABLE_MULTIPLE_CRYPTOCURRENCIES`: `true|false`
- `CRON_API_KEY`: key for cron-protected endpoints
- `SLACK_WEBHOOK_URL` / `NOTIFICATION_EMAIL`: optional notifications

Notes
- Do not commit real secrets. Use `.env` only locally or in server.
- Ensure `logs/` is writable by the web user.

