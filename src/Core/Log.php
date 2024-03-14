<?php

namespace App\Core;

class Log {
    const LOG_FILE = __DIR__ . '/../../storage/logs/log.log';

    public static function custom($message, $level = 'CUSTOM') {
        self::writeLog($level, $message);
    }

    public static function info($message) {
        self::writeLog('INFO', $message);
    }

    public static function error($message) {
        self::writeLog('ERROR', $message);
    }

    private static function writeLog($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        
        // Create the directory if it doesn't exist
        if (!file_exists(dirname(self::LOG_FILE))) {
            mkdir(dirname(self::LOG_FILE), 0700, true);
        }

        // Create the file if it doesn't exist
        if (!file_exists(self::LOG_FILE)) {
            file_put_contents(self::LOG_FILE, ''); 
        }

        file_put_contents(self::LOG_FILE, $formattedMessage, FILE_APPEND);
    }
}
