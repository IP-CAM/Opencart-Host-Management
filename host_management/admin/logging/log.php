<?php

namespace Opencart\Extension\HostManagement\Admin\Logging;

use Opencart\System\Library\Log as OCLog;


/**
 * OpenCart Log wrapper.
 */
class Log
{
    /**
     * OpenCart log instance.
     *
     * @var OCLog
     */
    protected OCLog $oc_log;

    /**
     * Logging prefix.
     *
     * @var string
     */
    protected string $prefix;


    /**
     * Creates a new instance.
     *
     * @param OCLog $log
     * @param string $prefix
     */
    public function __construct(OCLog $log, string $prefix)
    {
        $this->oc_log = $log;
        $this->prefix = $prefix;
    }

    /**
     * Logs a message.
     *
     * @param string $message
     * @return void
     */
    public function write(string $message): void
    {
        $this->oc_log->write($this->prefix . $message);
    }
}