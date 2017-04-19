<?php
declare(strict_types=1);
namespace HaDb;

use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

/**
 * Write HADB logs into syslog. Implements PSR-3.
 * @see http://www.php-fig.org/psr/psr-3/
 */
class Logger extends AbstractLogger
{
    /**
     * Map Psr log levels and syslog log levels
     * @var int[]
     */
    private static $levelMap = [
        LogLevel::EMERGENCY => LOG_EMERG,
        LogLevel::ALERT => LOG_ALERT,
        LogLevel::CRITICAL => LOG_CRIT,
        LogLevel::ERROR => LOG_ERR,
        LogLevel::WARNING => LOG_WARNING,
        LogLevel::NOTICE => LOG_NOTICE,
        LogLevel::INFO => LOG_INFO,
        LogLevel::NOTICE => LOG_NOTICE,
        LogLevel::DEBUG => LOG_DEBUG
    ];
    /**
     * Constructor: opens syslog.
     * @param string $identity
     * @param int $options
     * @param int $facility
     */
    public function __construct(string $identity = 'HADB', int $options = LOG_PID | LOG_CONS, int $facility = LOG_LOCAL0)
    {
        openlog($identity, $options, $facility);
        $this->debug('Logger opened');
    }

    /**
     * Destructor: closes syslog
     */
    public function __destruct()
    {
        $this->debug('Logger closed');
        closelog();
    }

    /**
     * @inheritdoc
     */
    public function log($level, $message, array $context = [])
    {
        if (!isset(self::$levelMap[$level])) {
            throw new InvalidArgumentException('Invalid log level');
        }

        if (!empty($context)) {
            $message = $this->interpolate($message, $context);
        }

        syslog(self::$levelMap[$level], $message);
    }

    /**
     * Insert context into message
     * @param string $message
     * @param array $context
     * @return string
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            // check that the value can be casted to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }

        return strtr($message, $replace);
    }
}
