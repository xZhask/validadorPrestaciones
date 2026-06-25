<?php

declare(strict_types=1);

namespace Validador;

class Logger
{
    private static string $logFile = __DIR__ . '/../logs/app.log';

    public static function error(string $message, ?\Throwable $e = null): void
    {
        $entry = date('Y-m-d H:i:s') . ' [ERROR] ' . $message;
        if ($e !== null) {
            $entry .= PHP_EOL . '  Exception: ' . get_class($e);
            $entry .= PHP_EOL . '  Message: ' . $e->getMessage();
            $entry .= PHP_EOL . '  File: ' . $e->getFile() . ':' . $e->getLine();
            $entry .= PHP_EOL . '  Trace:' . PHP_EOL . '    '
                . str_replace(PHP_EOL, PHP_EOL . '    ', $e->getTraceAsString());
        }
        self::write($entry);
    }

    public static function info(string $message): void
    {
        self::write(date('Y-m-d H:i:s') . ' [INFO] ' . $message);
    }

    private static function write(string $entry): void
    {
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(self::$logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
