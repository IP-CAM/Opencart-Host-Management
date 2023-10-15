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
     * Checks if server and catalog data arrays are set.
     *
     * @param array|null $hosts
     * @return bool
     */
    public function hasConfigData(?array $hosts): bool
    {
        return is_array($hosts)
            && isset($hosts['server'], $hosts['catalog'])
            && is_array($hosts['server'])
            && is_array($hosts['catalog']);
    }

    /**
     * Checks if server and catalog have same protcol and hostname.
     *
     * @param array $hosts
     * @return boolean
     */
    public function hasSameHosts(array $hosts): bool
    {
        return $hosts['server']['protocol'] === $hosts['catalog']['protocol']
            && $hosts['server']['hostname'] === $hosts['catalog']['hostname'];
    }

    /**
     * Validates data read from admin config file.
     *
     * @param array|null $hosts
     * @return boolean
     */
    public function isValidConfigRead(?array $hosts): bool
    {
        if (!$this->hasConfigData($hosts)) return false;

        if (
            !$this->isValidProtocol($hosts['server']['protocol'])
            || !$this->isValidHostname($hosts['server']['hostname'])
            || !$this->isValidAdminDir($hosts['server']['dir'])
            || !$this->isValidProtocol($hosts['catalog']['protocol'])
            || !$this->isValidHostname($hosts['catalog']['hostname'])
            || !$this->isValidPublicDir($hosts['catalog']['dir'])
        ) return false;

        if (!$this->hasSameHosts($hosts)) return false;
    }
}