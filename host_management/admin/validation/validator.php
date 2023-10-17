<?php

namespace Opencart\Extension\HostManagement\Admin\Validation;


/**
 * Validator class.
 */
class Validator
{
    /**
     * Validates hostname.
     *
     * @param string $hostname
     * @return bool
     */
    public function isValidHostname(string $hostname): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9\-\.]{0,253}[a-z0-9]$/', $hostname);
    }

    /**
     * Validates protocol.
     *
     * @param string $protocol
     * @return bool
     */
    public function isValidProtocol(string $protocol): bool
    {
        return in_array($protocol, [ 'http', 'https' ]);
    }

    /**
     * Validates admin directory path.
     *
     * @param string $dir
     * @return bool
     */
    public function isValidAdminDir(string $dir): bool
    {
        return strlen($dir) < 256 && preg_match('/^(?:[a-zA-Z0-9\-\_]+\/)+$/', $dir);
    }

    /**
     * Validates public directory path.
     *
     * @param string $dir
     * @return bool
     */
    public function isValidPublicDir(string $dir): bool
    {
        return $dir === '' || $this->isValidAdminDir($dir);
    }

    /**
     * Checks if server and catalog have same protcol and hostname.
     *
     * @param array $hosts
     * @return bool
     */
    public function hasSameHosts(array $hosts): bool
    {
        return $hosts['server']['protocol'] === $hosts['catalog']['protocol']
            && $hosts['server']['hostname'] === $hosts['catalog']['hostname'];
    }
}