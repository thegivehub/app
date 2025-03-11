<?php
// Database connection settings from environment variables
define('MONGODB_HOST', getenv('MONGODB_HOST') ?: 'localhost');
define('MONGODB_PORT', getenv('MONGODB_PORT') ?: '27017');
define('MONGODB_DATABASE', getenv('MONGODB_DATABASE') ?: 'givehub');
define('MONGODB_USERNAME', getenv('MONGODB_USERNAME') ?: '');
define('MONGODB_PASSWORD', getenv('MONGODB_PASSWORD') ?: '');

// JWT configuration
define('JWT_SECRET', getenv('JWT_SECRET') ?: '6ABD1CF21B5743C99A283D9184AB6F1A15E8FC1F141C749E39B49B6FD3E9D705');
define('JWT_EXPIRE', getenv('JWT_EXPIRE') ?: 3600 * 24); // 24 hours

// Application settings
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', getenv('APP_DEBUG') ?: true);
define('UPLOAD_DIR', __DIR__ . '/../img/avatars');

// API URLs
define('API_DOCS_URL', 'https://docs.thegivehub.com');
define('DEV_PORTAL_URL', 'https://developers.thegivehub.com');
