<?php
class Cache {
    private static $dir = __DIR__ . '/../cache/';

    private static function filePath($key) {
        return self::$dir . md5($key) . '.cache';
    }

    public static function set($key, $value, $ttl = 60) {
        if (!is_dir(self::$dir)) {
            mkdir(self::$dir, 0777, true);
        }
        $data = ['expires' => time() + $ttl, 'value' => $value];
        file_put_contents(self::filePath($key), serialize($data));
    }

    public static function get($key) {
        $file = self::filePath($key);
        if (!file_exists($file)) return null;
        $data = @unserialize(file_get_contents($file));
        if (!$data || $data['expires'] < time()) {
            @unlink($file);
            return null;
        }
        return $data['value'];
    }

    public static function delete($key) {
        $file = self::filePath($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
