# Security Procedures

This document summarizes the key security practices for API development and deployment.

## CSRF Protection

* All state-changing API endpoints should validate the `X-CSRF-Token` header against the session token.
* Tokens can be obtained from `/csrf_token.php` and must be included with authenticated POST requests.

## Authorization

* API requests must include a valid JWT in the `Authorization` header.
* Administrative endpoints verify that the authenticated user has the `admin` role.

## Dependency Updates

* Composer dependencies should be updated regularly and audited with `composer audit`.
* Use `npm audit` for JavaScript packages in `package.json`.

## Incident Response

1. Immediately rotate any compromised credentials.
2. Review application logs for suspicious activity.
3. Notify the security team at `security@thegivehub.com` within 24 hours.

