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
     * Error message keys for file modes.
     *
     * @var array
     */
    protected static array $error_keys = [
        'r' => 'error_read_access',
        'r+' => 'error_write_access'
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

// #region INTERNAL METHODS

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
            $this->messages->error(
                static::$error_keys[$mode] ?? 'error_file_access',
                'warning',
                $path
            );
        }

        return null;
    }

    /**
     * Overwrites file contents with given contents.
     *
     * @param SplFileObject $file
     * @param string $content
     * @return bool
     */
    protected function overwrite(SplFileObject $file, string $content): bool
    {
        $file->rewind();
        $file->ftruncate(0);

        $result = $file->fwrite($content);

        $file = null;

        return (bool)$result;
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
        $all = '$hm_urls = [';

        for ($i = 0; $i < count($hosts); $i++) { 
            $host = $hosts[$i];
            $url = $host['protocol'] . '://' . $host['hostname'] . '/';

            if (isset($host['default']) && $host['default']) {
                $default = '$hm_default = \'' . $url . '\'';
            }

            $all .= ($i !== 0) ? ',' . PHP_EOL : PHP_EOL;
            $all .= '    \'' . $url . '\'';
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
        if (
            (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on')
            || ($_SERVER['HTTPS'] == '1')))
            || $_SERVER['SERVER_PORT'] == 443
        ) {
            $hm_protocol = 'https://';
        } elseif (
            !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'
            || !empty($_SERVER['HTTP_X_FORWARDED_SSL'])
            && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on'
        ) {
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
        $code .= PHP_EOL . PHP_EOL;
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
     * Generates definitions code block
     *
     * @param array $host
     * @param array $dirs
     * @param boolean $isAdmin
     * @return string
     */
    protected function generateDefinitions(array $host, array $dirs, bool $isAdmin): string
    {
        $definitions = 'define(\'HTTP_SERVER\', \'' . $host['protocol'] . '://';
        $definitions .= $host['hostname'] . '/' . ($isAdmin ? $dirs['admin'] : $dirs['public']) . '\');';

        if ($isAdmin) {
            $definitions .= PHP_EOL . 'define(\'HTTP_CATALOG\', \'' . $host['protocol'] . '://';
            $definitions .= $host['hostname'] . '/' . $dirs['public'] . '\');';
        }

        return $definitions;
    }

// #endregion INTERNAL METHODS

// #region PUBLIC INTERFACE

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

        if (!$file) return null;

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
     * Edits config file.
     *
     * @param string $path
     * @param array $hosts
     * @param array $dirs
     * @return bool
     */
    public function edit(string $path, array $hosts, array $dirs): bool
    {
        $file = $this->getFileObj($path,'r+');

        if (!$file) return false;

        $content = (string)$file->fread($file->getSize());
        $count = 0;

        $isAdmin =
            preg_match(
                '/^define\s*\(\s*[\"\']APPLICATION[\"\']\s*\,\s*[\"\']Admin[\"\']\s*\);\s*$\R/m',
                $content
            );

        $content =
            preg_replace(
                '/^define\s*\(\s*[\"\']HTTP_SERVER[\'\"]\s*\,\s*[\'\"][^\'\"]+[\'\"]\s*\);\s*$\R/m',
                '',
                $content,
                1,
                $count
            );

        if (!$content || $count !== 1) {
            $this->messages->error('error_http_server');
            $file = null;

            return false;
        }

        if ($isAdmin) {
            $content = preg_replace(
                '/^define\s*\(\s*[\"\']HTTP_CATALOG[\'\"]\s*\,\s*[\'\"][^\'\"]+[\'\"]\s*\);\s*$\R/m',
                '',
                $content,
                1,
                $count
            );

            if (!$content || $count !== 1) {
                $this->messages->error('error_http_catalog');
                $file = null;

                return false;
            }
        }

        $search =
            preg_match('/^\/\/\s*HTTP\s*$/m', $content) ?
            '/^(\/\/\s*HTTP)\s*$/m'
            : '/^(<\?(?:php)?)\s*$/m';
        $replace = '$1' . PHP_EOL . $this->generateCodeBlock($hosts, $dirs, $isAdmin);

        $content = preg_replace($search, $replace, $content, 1, $count);

        if (!$content || $count !== 1) {
            $this->messages->error('error_php_tag');
            $file = null;

            return false;
        }

        return $this->overwrite($file, $content);
    }

    /**
     * Updates config file.
     *
     * @param string $path
     * @param array $hosts
     * @return boolean
     */
    public function update(string $path, array $hosts): bool
    {
        $file = $this->getFileObj($path,'r+');

        if (!$file) return false;

        $content = (string)$file->fread($file->getSize());
        $urls = $this->generateUrls($hosts);
        $count = 0;

        $content =
            preg_replace(
                '/^\$hm_default\s*\=\s*[\"\'][^\'\"]+[\'\"]/m',
                $urls['default'],
                $content,
                1,
                $count
            );

        if ($count === 1) {
            $content =
                preg_replace(
                    '/^\$hm_urls\s*\=\s*\[[^\]]+\]/m',
                    $urls['all'],
                    $content,
                    1,
                    $count
                );
        }

        if (!$content || $count !== 1) {
            $this->messages->error('error_update_urls');
            $file = null;

            return false;
        }

        return $this->overwrite($file, $content);
    }

    /**
     * Restores config file.
     *
     * @param string $path
     * @param array $host
     * @param array $dirs
     * @return bool
     */
    public function restore(string $path, array $host, array $dirs): bool
    {
        $file = $this->getFileObj($path, 'r+');

        if (!$file) return false;

        $content = (string)$file->fread($file->getSize());

        $isAdmin =
            preg_match(
                '/^define\s*\(\s*[\"\']APPLICATION[\"\']\s*\,\s*[\"\']Admin[\"\']\s*\);\s*$\R/m',
                $content
            );

        $search =
            preg_match('/^\/\/\s*HTTP\s*$/m', $content) ?
            '/^(\/\/\s*HTTP)'
            : '/^(<\?(?:php)?)';
        $search .= '\s*$\R^';
        $search .= str_replace([' ', '/'], ['\s*', '\/'], preg_quote(static::$comments['before']));
        $search .= '.+';
        $search .= str_replace([' ', '/'], ['\s*', '\/'], preg_quote(static::$comments['after']));
        $search .= '$/ms';

        $replace = '$1' . PHP_EOL . $this->generateDefinitions($host, $dirs, $isAdmin);

        $count = 0;
        $content = preg_replace($search, $replace, $content, -1, $count);

        if (!$content || $count !== 1) {
            $this->messages->error('error_restore');
            $file = null;

            return false;
        }

        return $this->overwrite($file, $content);
    }

// #endregion PUBLIC INTERFACE
}