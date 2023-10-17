<?php

namespace Opencart\Extension\HostManagement\Admin\File;

use SplFileObject;
use Opencart\Extension\HostManagement\Admin\Logging\Log;
use Opencart\Extension\HostManagement\Admin\Messaging\MessageBag;


/**
 * Config file manager.
 */
class Config
{
    /**
     * Config file comments.
     *
     * @var array
     */
    protected static array $comments = [
        'before' => '// Start Host Management Extension',
        'after' => '// End Host Management Extension'
    ];

    /**
     * Log instance.
     *
     * @var Log
     */
    protected Log $log;

    /**
     * MessageBag instance.
     *
     * @var MessageBag
     */
    protected MessageBag $messages;


    /**
     * Creates a new instance.
     *
     * @param Log $log
     * @param MessageBag $messages
     */
    public function __construct(Log $log, MessageBag $messages)
    {
        $this->log = $log;
        $this->messages = $messages;
    }

    /**
     * Creates file object for given path.
     *
     * @param string $path
     * @param string $mode
     * @return SplFileObject|null
     */
    protected function getFileObj(string $path, string $mode = 'r'): ?SplFileObject
    {
        try {
            return new SplFileObject($path, $mode);
        } catch (\Throwable $th) {
            $this->log->write(sprintf('%s. Line: %s.', $th->getMessage(), $th->getLine()));
        }

        return null;
    }

    /**
     * Generates URLs code block.
     *
     * @param array $hosts
     * @return array
     */
    protected function generateUrls(array $hosts): array
    {
        $default = '';
        $all = '$hm_urls = [' . PHP_EOL;

        foreach ($hosts as $host) {
            $url = $host['protocol'] . '://' . $host['hostname'] . '/';

            $all .= '    \'' . $url . '\',' . PHP_EOL;

            if (isset($host['default']) && $host['default']) {
                $default = '$hm_default = \'' . $url . '\'';
            }
        }

        $all .= PHP_EOL . ']';

        return [ 'default' => $default, 'all' => $all ];
    }

    /**
     * Generates code block.
     *
     * @param array $hosts
     * @param array $dirs
     * @param bool $isAdmin
     * @return string
     */
    protected function generateCodeBlock(array $hosts, array $dirs, bool $isAdmin): string
    {
        $urls = $this->generateUrls($hosts);

        $code = static::$comments['before'] . PHP_EOL;
        $code .= <<<'EOT'
        if ((isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) || $_SERVER['SERVER_PORT'] == 443) {
            $hm_protocol = 'https://';
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
            $hm_protocol = 'https://';
        } else {
            $hm_protocol = 'http://';
        }

        $hm_requested = $hm_protocol . $_SERVER['HTTP_HOST'] . '/';
        *default*;
        *urls*;

        $hm_url = in_array($hm_requested, $hm_urls) ? $hm_requested : $hm_default;
        EOT;

        $code = str_replace([ '*default*', '*urls*' ], [ $urls['default'], $urls['all'] ], $code);
        $code .= PHP_EOL . PHP_EOL . '// HTTP' . PHP_EOL;
        $code .= 'define(\'HTTP_SERVER\', $hm_url';

        if ($isAdmin) {
            $code .= ' . \'' . $dirs['admin'] . '\');' . PHP_EOL;
            $code .= 'define(\'HTTP_CATALOG\', $hm_url';
        }

        if ($dirs['public'] !== '') {
            $code .= ' . \'' . $dirs['public'] . '\'';
        }

        $code .= ');' . PHP_EOL . static::$comments['after'] . PHP_EOL;

        return $code;
    }

    /**
     * Updates URLs within config file contents.
     *
     * @param string $configStr
     * @param array $hosts
     * @return string|null
     */
    protected function updateConfigUrls(string $configStr, array $hosts): ?string
    {
        $urls = $this->generateUrls($hosts);

        $count = 0;

        $configStr =
            preg_replace(
                '/^\$hm_default\s*\=\s*[\"\'][^\'\"]+[\'\"]\s*;\s*$\R/m',
                $urls['default'],
                $configStr,
                1,
                $count
            );

        if ($count === 1) {
            $configStr =
                preg_replace(
                    '/^\$hm_urls\s*\=\s*\[[^\]]+\]\s*;\s*$\R/m',
                    $urls['all'],
                    $configStr,
                    1,
                    $count
                );
        }

        if (!$configStr || $count !== 1) {
            $this->messages->error('error_update_urls');

            return null;
        }

        return $configStr;
    }

    /**
     * Edits config file contents.
     *
     * @param string $configStr
     * @param array $hosts
     * @param array $dirs
     * @return string|null
     */
    protected function editConfig(string $configStr, array $hosts, array $dirs): ?string
    {
        $isAdmin =
            preg_match(
                '/^define\s*\(\s*[\"\']APPLICATION[\"\']\s*\,\s*[\"\']Admin[\"\']\s*\);\s*$\R/m',
                $configStr
            );

        $count = 0;

        $configStr =
            preg_replace(
                '/^define\s*\(\s*[\"\']HTTP_SERVER[\'\"]\s*\,\s*[\'\"][^\'\"]+[\'\"]\s*\);\s*$\R/m',
                '',
                $configStr,
                1,
                $count
            );

        if (!$configStr || $count !== 1) {
            $this->messages->error('error_http_server');

            return null;
        }

        if ($isAdmin) {
            $configStr = preg_replace(
                '/^define\s*\(\s*[\"\']HTTP_CATALOG[\'\"]\s*\,\s*[\'\"][^\'\"]+[\'\"]\s*\);\s*$\R/m',
                '',
                $configStr,
                1,
                $count
            );

            if (!$configStr || $count !== 1) {
                $this->messages->error('error_http_catalog');

                return null;
            }
        }

        $search =
            preg_match('/^\/\/\s*HTTP\s*$/m', $configStr) ?
            '/^(\/\/\s*HTTP\s*)$/m'
            : '/^(<\?(?:php)?)\s*$/m';
        $replace = '$1' . PHP_EOL . $this->generateCodeBlock($hosts, $dirs, $isAdmin);

        $configStr = preg_replace($search, $replace, $configStr, 1, $count);

        if (!$configStr || $count !== 1) {
            $this->messages->error('error_php_tag');

            return null;
        }

        return $configStr;
    }

    /**
     * Gets error messages.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->messages->getErrors();
    }

    /**
     * Reads host information from config file.
     *
     * @param string $path
     * @return array|null
     */
    public function readConfig(string $path): ?array
    {
        $file = $this->getFileObj($path);

        if (!$file) {
            $this->messages->error('error_read_access', 'warning', $path);

            return null;
        }

        $serverPattern =<<<'EOT'
        /define\s*\(
        \s*[\"\']HTTP_SERVER[\'\"]\s*\,
        \s*[\'\"](?<protocol>http|https)\:\/\/(?<hostname>[\w\d\-\.]+)\/(?<dir>[^\'\"]+)[\'\"]\s*
        \)/mx
        EOT;

        $catalogPattern =<<<'EOT'
        /define\s*\(
        \s*[\"\']HTTP_CATALOG[\'\"]\s*\,
        \s*[\'\"](?<protocol>http|https)\:\/\/(?<hostname>[\w\d\-\.]+)\/(?<dir>[^\'\"]+)?[\'\"]\s*
        \)/mx
        EOT;

        $configStr = $file->fread($file->getSize());
        $file = null;
        $hosts = [];
        $server = [];
        $catalog = [];

        if (
            !preg_match($serverPattern, $configStr, $server)
            || !preg_match($catalogPattern, $configStr, $catalog)
        ) {
            $this->messages->error('error_read');

            return null;
        }
        
        $hosts['server'] = $server;
        $hosts['catalog'] = $catalog;
        $hosts['catalog']['dir'] ??= '';

        return $hosts;
    }

    /**
     * Modifies config file.
     *
     * @param string $path
     * @param array $hosts
     * @param array $dirs
     * @return bool
     */
    public function modifyConfig(string $path, array $hosts, array $dirs): bool
    {
        $file = $this->getFileObj($path,'r+');

        if (!$file) {
            $this->messages->error('error_write_access', 'warning', $path);

            return false;
        }

        $configStr = $file->fread($file->getSize());
        $configStr =
            str_contains($configStr, static::$comments['before']) ?
            $this->updateConfigUrls($configStr, $hosts) :
            $this->editConfig($configStr, $hosts, $dirs);

        if (!$configStr) {
            return false;
        }

        $file->rewind();
        $file->ftruncate(0);
        $file->fwrite($configStr);
        $file = null;

        return true;
    }
}