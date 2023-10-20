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

    protected static array $patterns = [
        'admin' => '/^define\s*\(\s*[\"\']APPLICATION[\"\']\s*\,\s*[\"\']Admin[\"\']\s*\);\s*$\R/m',
        'server' => '/^define\s*\(\s*[\"\']HTTP_SERVER[\'\"]\s*\,\s*[\'\"][^\'\"]+[\'\"]\s*\);\s*$\R/m',
        'catalog' => '/^define\s*\(\s*[\"\']HTTP_CATALOG[\'\"]\s*\,\s*[\'\"][^\'\"]+[\'\"]\s*\);\s*$\R/m',
        'http' => '/^(\/\/\s*HTTP)\s*$/m',
        'php' => '/^(<\?(?:php)?)\s*$/m',
        'default_url' => '/^\$hm_default\s*\=\s*[\"\'][^\'\"]+[\'\"]/m',
        'urls' => '/^\$hm_urls\s*\=\s*\[[^\]]+\]/m'
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
     * Inserts error message related to specific file and optionally closes file pointer.
     *
     * @param string $error
     * @param string $path
     * @param SplFileObject|null $file
     * @return void
     */
    protected function fileError(string $error, string $path, ?SplFileObject $file = null): void
    {
        $this->messages->error($error, 'warning', $path);

        if ($file) $file = null;
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
            $this->fileError(static::$error_keys[$mode] ?? 'error_file_access', $path);
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
     * Checks if content belongs to admin config.php.
     *
     * @param string $content
     * @return bool
     */
    protected function isAdminConfig(string $content): bool
    {
        return (bool)preg_match(static::$patterns['admin'], $content);
    }

    /**
     * Generates URLs code block.
     *
     * @param array $hosts
     * @return array
     */
    protected function generateUrls(array $hosts): array
    {
        $result = [];
        $urls = [];

        foreach ($hosts as $host) {
            $url = '\'' . $host['protocol'] . '://' . $host['hostname'] . '/\'';

            if (isset($host['default']) && $host['default']) {
                $result['default'] = '$hm_default = ' . $url;
            }

            $urls[] = $url;
        }

        $result['all'] = '$hm_urls = [' . PHP_EOL . '    ';
        $result['all'] .= implode(',' . PHP_EOL . '    ', $urls);
        $result['all'] .= PHP_EOL . ']';

        return $result;
    }

    /**
     * Generates pattern to match within edited section of config file. 
     *
     * @param string $content
     * @return string
     */
    protected function generateRestorePattern(string $content): string
    {
        $pattern =
            preg_match(static::$patterns['http'], $content) ?
            static::$patterns['http']
            : static::$patterns['php'];
        $pattern = str_replace('/m', '\R^', $pattern);
        $pattern .= str_replace([' ', '/'], ['\s*', '\/'], preg_quote(static::$comments['before']));
        $pattern .= '.+';
        $pattern .= str_replace([' ', '/'], ['\s*', '\/'], preg_quote(static::$comments['after']));
        $pattern .= '$/ms';

        return $pattern;
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
     * Generates definitions code block.
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
     * Message bag getter.
     *
     * @return MessageBag
     */
    public function getMessages(): MessageBag
    {
        return $this->messages;
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

        $configStr = (string)$file->fread($file->getSize());
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
     * Checks if config file can be edited.
     *
     * @param string $path
     * @return boolean
     */
    public function canEdit(string $path): bool
    {
        $file = $this->getFileObj($path, 'r+');

        if (!$file) return false;

        $content = (string)$file->fread($file->getSize());
        $isAdmin = $this->isAdminConfig($content);

        if (!preg_match(static::$patterns['server'], $content)) {
            $this->fileError('error_http_server', $path, $file);

            return false;
        }

        if ($isAdmin && !preg_match(static::$patterns['catalog'], $content)) {
            $this->fileError('error_http_catalog', $path, $file);

            return false;
        }

        if (
            !preg_match(static::$patterns['http'], $content)
            && !preg_match(static::$patterns['php'], $content)
        ) {
            $this->fileError('error_php_tag', $path, $file);

            return false;
        }

        $file = null;

        return true;
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
        $isAdmin = $this->isAdminConfig($content);
        $count = 0;

        $content = preg_replace(static::$patterns['server'], '', $content, 1, $count);

        if (!$content || $count !== 1) {
            $this->fileError('error_http_server', $path, $file);

            return false;
        }

        if ($isAdmin) {
            $content = preg_replace(static::$patterns['catalog'], '', $content, 1, $count);

            if (!$content || $count !== 1) {
                $this->fileError('error_http_catalog', $path, $file);

                return false;
            }
        }

        $search =
            preg_match(static::$patterns['http'], $content) ?
            static::$patterns['http']
            : static::$patterns['php'];
        $replace = '$1' . PHP_EOL . $this->generateCodeBlock($hosts, $dirs, $isAdmin);

        $content = preg_replace($search, $replace, $content, 1, $count);

        if (!$content || $count !== 1) {
            $this->fileError('error_php_tag', $path, $file);

            return false;
        }

        return $this->overwrite($file, $content);
    }

    /**
     * Checks if config file can be updated.
     *
     * @param string $path
     * @return boolean
     */
    public function canUpdate(string $path): bool
    {
        $file = $this->getFileObj($path, 'r+');

        if (!$file) return false;

        $content = (string)$file->fread($file->getSize());

        if (
            !preg_match(static::$patterns['default_url'], $content)
            || !preg_match(static::$patterns['urls'], $content)
        ) {
            $this->fileError('error_update_urls', $path, $file);

            return false;
        }

        $file = null;

        return true;
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
            preg_replace(static::$patterns['default_url'], $urls['default'], $content, 1, $count);

        if ($count === 1) {
            $content = preg_replace(static::$patterns['urls'], $urls['all'], $content, 1, $count);
        }

        if (!$content || $count !== 1) {
            $this->fileError('error_update_urls', $path, $file);

            return false;
        }

        return $this->overwrite($file, $content);
    }


    /**
     * Checks if config file can be restored.
     *
     * @param string $path
     * @return boolean
     */
    public function canRestore(string $path): bool
    {
        $file = $this->getFileObj($path, 'r+');

        if (!$file) return false;

        $content = (string)$file->fread($file->getSize());

        if (!preg_match($this->generateRestorePattern($content), $content)) {
            $this->fileError('error_restore', $path, $file);

            return false;
        }

        $file = null;

        return true;
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
        $isAdmin = $this->isAdminConfig($content);
        $search = $this->generateRestorePattern($content);
        $replace = '$1' . PHP_EOL . $this->generateDefinitions($host, $dirs, $isAdmin);

        $count = 0;
        $content = preg_replace($search, $replace, $content, -1, $count);

        if (!$content || $count !== 1) {
            $this->fileError('error_restore', $path, $file);

            return false;
        }

        return $this->overwrite($file, $content);
    }

// #endregion PUBLIC INTERFACE
}