# Performance Monitoring

This project includes a simple profiling helper and a load testing script to track API performance.

## Profiler

`lib/Profiler.php` provides `Profiler::start($name)` and `Profiler::end($name, $extra = [])` methods. Each call records the duration in `logs/performance.log`.

Example usage:
```php
require_once __DIR__ . '/lib/autoload.php';
Profiler::start('image-upload');
// ... code ...
Profiler::end('image-upload');
```

## Load Testing

Run basic load tests with the provided Node script:
```bash
npm run loadtest -- https://example.com/api.php/resource 30 100
```
Arguments are `url`, `duration` (seconds) and `connections`.
