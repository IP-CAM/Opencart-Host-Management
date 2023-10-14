<?php

namespace Opencart\Admin\Controller\Extension\HostManagement\Other;

use SplFileObject;
use Opencart\System\Engine\Controller;
use Opencart\System\Engine\Registry;


/**
 * Host management extension controller.
 */
final class HostManagement extends Controller
{
    /**
     * Protocol detection code.
     *
     * @var string
     */
    private static $codeProtocol = <<<'EOT'
    if ((isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] == 'on') || ($_SERVER['HTTPS'] == '1'))) || $_SERVER['SERVER_PORT'] == 443) {
        $ngrt_protocol = 'https://';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
        $ngrt_protocol = 'https://';
    } else {
        $ngrt_protocol = 'http://';
    }
    EOT;

    /**
     * Admin host code.
     *
     * @var string
     */
    private static $codeHostAdmin = <<<'EOT'
    $ngrt_host = $ngrt_protocol . $_SERVER['HTTP_HOST'] . substr(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/.\\\'), 0, -*admin_length*) . '/';
    EOT;

    /**
     * Public host code.
     *
     * @var string
     */
    private static $codeHostPublic = <<<'EOT'
    $ngrt_host = $ngrt_protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/.\\\') . '/';
    EOT;

    /**
     * Admin config host definition.
     *
     * @var string
     */
    private static $codeAdmin = <<<'EOT'
    define('HTTP_SERVER', $ngrt_host . '*admin*');
    define('HTTP_CATALOG', $ngrt_host);
    EOT;

    /**
     * Public config host definition.
     *
     * @var string
     */
    private static $codePublic = <<<'EOT'
    define('HTTP_SERVER', $ngrt_host);
    EOT;

    /**
     * Config file comments.
     *
     * @var array
     */
    private static $comments = [
        'before' => '// *** Start Host Management Extension Edit ***',
        'after' => '// *** End Host Management Extension Edit ***'
    ];

    /**
     * Search patterns.
     *
     * @var array
     */
    private static $find = [
        'server' => '^(define\(\'HTTP_SERVER\'[^\r\n]+)$[\r\n]{1,2}',
        'catalog' => '^(define\(\'HTTP_CATALOG\'[^\r\n]+)$[\r\n]{1,2}'
    ];

    /**
     * Replacement patterns.
     *
     * @var array
     */
    private static $replace = [
        'server' => '// $1',
        'catalog' => '// $2'
    ];

    /**
     * Settings' names.
     *
     * @var array
     */
    protected static $settings = [
        'admin_dir' => 'host_management_admin_dir',
        'public_dir' => 'host_management_public_dir',
        'status' => 'host_management_status'
    ];

    /**
     * Model property name.
     *
     * @var string
     */
    protected static $model_name = 'model_extension_host_management_other_host_management';

    /**
     * Log messages prefix.
     *
     * @var string
     */
    protected static $log_prefix = '[Extension: Host management] - ';

    /**
     * Extension DB code.
     *
     * @var string
     */
    protected static $extension_code = 'host_management';

    /**
     * Extension route.
     *
     * @var string
     */
    protected static $route = 'extension/host_management/other/host_management';

    /**
     * Response error messages.
     *
     * @var array
     */
    private $error_msg = [];

    /**
     * Response success messages.
     *
     * @var array
     */
    private $success_msg = [];


    /**
     * Creates a new instance.
     *
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $this->load->language(static::$route);
    }

    /**
     * Installs the extension.
     *
     * @return void
     */
    public function install(): void
    {
        if (!$this->user->hasPermission('modify', 'extension/other')) return;

        $this->load->model(static::$route);

        $this->{static::$model_name}->install();

        $admin = [];
        $public = [];

        $this->readAdminConfig($admin, $public);
        $this->saveDefaults($admin, $public);
    }

    public function uninstall(): void
    {
        if (!$this->user->hasPermission('modify', 'extension/other')) return;

        $this->load->model(static::$route);

        // Check amdin/public status, and restore config files if necessary

        $this->{static::$model_name}->uninstall();
    }

    /**
     * Displays extension settings.
     *
     * @return void
     */
    public function index(): void
    {
        $this->document->setTitle($this->language->get('heading_title'));

        $separator = version_compare(VERSION, '4.0.2.0') >= 0 ? '.' : '|';
        $user_token = 'user_token=' . $this->session->data['user_token'];

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', $user_token)
            ],
            [
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', $user_token . '&type=other')
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link(static::$route, $user_token)
            ]
        ];

        $data['back'] = $this->url->link('marketplace/extension', $user_token . '&type=other');

        $data['save'] =
            $this->url->link(static::$route . $separator . 'save', $user_token);

        $data['settings'] = static::$settings;

        foreach (static::$settings as $setting_db_key) {
            $data[$setting_db_key] = $this->config->get($setting_db_key);
        }

        $this->load->model(static::$route);

        $data['hosts'] = $this->{static::$model_name}->all();

        if (empty($data[static::$settings['admin_dir']]) || empty($data['hosts'])) {
            $admin = [];
            $public = [];

            $this->readAdminConfig($admin, $public);

            if (!$this->saveDefaults($admin, $public)) {
                $data['read_error'] = true;
                $data['hosts'] = [];
                $data[static::$settings['admin_dir']] = '';
                $data[static::$settings['public_dir']] = '';
            }
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view(static::$route, $data));
    }

    /**
     * Saves extension settings.
     *
     * @return void
     */
    public function save(): void
    {
        $this->load->language(static::$route);

        if (!$this->user->hasPermission('modify', 'common/security')) {
            $message = 'Insufficient permissions to modify security settings.';

            $this->error_msg[] = $message;
            $this->log->write(static::$log_prefix . $message);

            $this->jsonResponse();

            return;
        }

        $adminPath = DIR_APPLICATION . 'config.php';
        $publicPath = DIR_OPENCART . 'config.php';

        $this->load->model('setting/setting');

        if (!empty($this->request->post['host_management_admin_status'])) {
            if (!$this->Activate($adminPath)) {
                unset($this->request->post['host_management_admin_status']);
            }
        } else {
            // This is bad because if it was disabled, and we can't get write access
            // we incorrectly set it to enabled
            if (!$this->Restore($adminPath)) {
                $this->request->post['host_management_admin_status'] = '1';
            }
        }

        if (!empty($this->request->post['host_management_catalog_status'])) {
            if (!$this->Activate($publicPath)) {
                unset($this->request->post['host_management_catalog_status']);
            }
        } else {
            if (!$this->Restore($publicPath)) {
                $this->request->post['host_management_catalog_status'] = '1';
            }
        }

        $this->model_setting_setting->editSetting(static::$extension_code, $this->request->post);

        $this->jsonResponse();
    }

    /**
     * Creates file object for given path.
     *
     * @param string $path
     * @param string $mode
     * @return SplFileObject|null
     */
    private function getFileObj(string $path, string $mode = 'r+'): ?SplFileObject
    {
        try {
            return new SplFileObject($path, $mode);
        } catch (\Throwable $th) {
            $message_template = 'Could not get write access for: %s file.';
            $error_template = '%s. Line: %s.';

            $this->error_msg[] = sprintf($message_template, $path);
            $this->log->write(
                static::$log_prefix . sprintf($error_template, $th->getMessage(), $th->getLine())
            );
        }

        return null;
    }

    /**
     * Reads host information from config file.
     *
     * @param array $admin
     * @param array $public
     * @return void
     */
    private function readAdminConfig(array &$admin, array &$public): void
    {
        $file = $this->getFileObj(DIR_APPLICATION . 'config.php', 'r');

        if (!$file) return;

        $adminPattern =<<<'EOT'
        /define\s*\(
        \s*[\"\']HTTP_SERVER[\'\"]\s*\,
        \s*[\'\"](?<protocol>http|https)\:\/\/(?<hostname>[\w\d\-\.]+)\/(?<dir>[^\'\"]+)[\'\"]\s*
        \)/mx
        EOT;

        $publicPattern =<<<'EOT'
        /define\s*\(
        \s*[\"\']HTTP_CATALOG[\'\"]\s*\,
        \s*[\'\"](?<protocol>http|https)\:\/\/(?<hostname>[\w\d\-\.]+)\/(?<dir>[^\'\"]+)?[\'\"]\s*
        \)/mx
        EOT;

        $configStr = $file->fread($file->getSize());

        preg_match($adminPattern, $configStr, $admin);
        preg_match($publicPattern, $configStr, $public);
    }

    /**
     * Saves default host, protocol and directories.
     *
     * @param array $admin
     * @param array $public
     * @return bool
     */
    private function saveDefaults(array $admin, array $public): bool
    {
        if (
            !isset(
                $admin['protocol'],
                $admin['hostname'],
                $admin['dir'],
                $public['protocol'],
                $public['hostname']
            )
        ) return false;

        if (
            $admin['protocol'] !== $public['protocol'] || $admin['hostname'] !== $public['hostname']
        ) return false;

        $this->load->model(static::$route);

        $this->{static::$model_name}->insert([
            'protocol' => $admin['protocol'],
            'hostname' => $admin['hostname'],
            'default' => true
        ]);

        $this->load->model('setting/setting');

        $this->model_setting_setting->editSetting(
            static::$extension_code,
            [
                static::$settings['admin_dir'] => $admin['dir'],
                static::$settings['public_dir'] => $public['dir'] ?? ''
            ]
        );

        return true;
    }

    /**
     * Replaces definitions within given file.
     *
     * @param string $filePath
     * @return bool
     */
    private function Activate(string $filePath): bool
    {
        $file = $this->getFileObj($filePath);

        if (!$file) return false;

        $configStr = $file->fread($file->getSize());

        if (str_contains($configStr, static::$comments['before'])) return true;

        $isAdmin = str_contains($configStr, 'define(\'APPLICATION\', \'Admin\')');

        if ($isAdmin) {
            $matches = [];
            $matched = preg_match(
                '/define\(\'HTTP_SERVER\'\,\s+\'.+\/([^\/]+)\/\'\)/m',
                $configStr,
                $matches
            );

            if (!$matched || !isset($matches[1]) || strlen($matches[1]) < 2) {
                $message = 'Could not match admin direcotry.';

                $this->error_msg[] = $message;
                $this->log->write(static::$log_prefix . $message);

                return false;
            }

            $adminPath = $matches[1];
        }

        $pattern = '/' . static::$find['server'];
        $pattern .= $isAdmin ? static::$find['catalog'] . '/m' : '/m';

        $replacement = static::$comments['before'] . PHP_EOL . static::$replace['server'];
        $replacement .= $isAdmin ? PHP_EOL . static::$replace['catalog'] : '';
        $replacement .= PHP_EOL . static::$codeProtocol . PHP_EOL . PHP_EOL;
        $replacement .=
            $isAdmin ?
            str_replace('*admin_length*', strval(strlen($adminPath)), static::$codeHostAdmin) :
            static::$codeHostPublic;
        $replacement .= PHP_EOL . PHP_EOL;
        $replacement .=
            $isAdmin ? str_replace('*admin*', $adminPath .'/', static::$codeAdmin) : static::$codePublic;
        $replacement .= PHP_EOL . static::$comments['after'] . PHP_EOL . PHP_EOL;

        $count = 0;
        $result = preg_replace($pattern, $replacement, $configStr, -1, $count);

        if ($count !== 1) {
            $this->error_msg[] =
                sprintf(
                    'Could not match definitions within %s config file.',
                    $isAdmin ? 'admin' : 'catalog'
                );

            $file = null;

            return false;
        }

        $file->rewind();
        $file->fwrite($result);
        $file = null;

        $this->success_msg[] =
            $this->language->get('text_success_' . ($isAdmin ? 'admin' : 'catalog'));

        return true;
    }

    /**
     * Restores original definitions.
     *
     * @param string $filePath
     * @return boolean
     */
    private function Restore(string $filePath): bool
    {
        $file = $this->getFileObj($filePath);

        if (!$file) return false;

        $configStr = $file->fread($file->getSize());

        if (!str_contains($configStr, static::$comments['before'])) return true;

        $isAdmin = str_contains($configStr, 'define(\'APPLICATION\', \'Admin\')');

        $pattern = '/^\/{2}\s\*{3}\sStart.+?\R';
        $pattern .= '^\/{2}\s(define\(\'HTTP_SERVER\'.+?)$';
        $pattern .= '(?:(\R)^\/{2}\s(define\(\'HTTP_CATALOG\'.+?)$)?';
        $pattern .= '.+Edit\s\*{3}$/ms';

        $count = 0;
        $result = preg_replace($pattern, '$1$2$3', $configStr, -1, $count);

        if ($count !== 1) {
            $this->error_msg[] =
                sprintf(
                    'Could not match extension modifications within %s config file.',
                    $isAdmin ? 'admin' : 'catalog'
                );

            $file = null;

            return false;
        }

        $file->rewind();
        $file->ftruncate(0);
        $file->fwrite($result);
        $file = null;

        $this->success_msg[] =
            $this->language->get('text_success_' . ($isAdmin ? 'admin' : 'catalog'));

        return true;
    }

    /**
     * Parses messages array into HTML.
     *
     * @param array $messages
     * @param string $prefix
     * @return string|null
     */
    private function parseMessages(array $messages, string $prefix): ?string
    {
        $count = count($messages);

        if ($count === 0) return null;

        if ($count === 1) return $prefix . ': ' . $messages[0];

        $list_itmes = '';

        foreach ($messages as $message) {
            $list_itmes .= '<li>' . $message . '</li>' . PHP_EOL;
        }

        return <<<EOT
        <p class="ps-4 mb-2" style="margin-top: -1.125rem;">$prefix:</p>
        <ul class="ps-5">
            $list_itmes
        </ul>
        EOT;
    }

    /**
     * Sends json response.
     *
     * @return void
     */
    private function jsonResponse(): void
    {
        $output = [];
        $errors = $this->parseMessages($this->error_msg, 'Error');
        $success = $this->parseMessages($this->success_msg, 'Success');

        if ($errors) {
            $output['error'] = $errors;
        }

        if ($success) {
            $output['success'] = $success;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($output));
    }
}