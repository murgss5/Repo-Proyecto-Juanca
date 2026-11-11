<?php

namespace Psr\Log;

/**
 * Describes a logger instance.
 * PSR-3 standard interface.
 *
 * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger.md
 */
interface LoggerInterface
{
    public function emergency($message, array $context = []);
    public function alert($message, array $context = []);
    public function critical($message, array $context = []);
    public function error($message, array $context = []);
    public function warning($message, array $context = []);
    public function notice($message, array $context = []);
    public function info($message, array $context = []);
    public function debug($message, array $context = []);
    public function log($level, $message, array $context = []);
}
