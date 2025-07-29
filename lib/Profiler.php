<?php
class Profiler {
    private static $times = [];
    private static $logFile = __DIR__ . '/../logs/performance.log';

    public static function start($name) {
        self::$times[$name] = microtime(true);
    }

    public static function end($name, array $extra = []) {
        if (!isset(self::$times[$name])) return;
        $duration = microtime(true) - self::$times[$name];
        $entry = array_merge([
            'timestamp' => date('c'),
            'name' => $name,
            'duration_ms' => round($duration * 1000, 2)
        ], $extra);
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        file_put_contents(self::$logFile, json_encode($entry) . PHP_EOL, FILE_APPEND);
        unset(self::$times[$name]);
    }
}
