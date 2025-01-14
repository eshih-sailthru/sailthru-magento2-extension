<?php

namespace Sailthru\MageSail;

class Logger extends \Monolog\Logger
{
    /**
     * Adds a log record.
     *
     * @param  int     $level   The logging level
     * @param  string  $message The log message
     * @param  array   $context The log context
     *
     * @return bool Whether the record has been processed
     */
    public function addRecord($level, $message, array $context = [])
    {
        return parent::addRecord(
            $level,
            !is_string($message) ? var_export($message, true) : $message,
            $context
        );
    }
}
