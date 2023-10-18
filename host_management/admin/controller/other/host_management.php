<?php

namespace Opencart\Admin\Controller\Extension\HostManagement\Other;

use Opencart\System\Engine\Controller;
use Opencart\System\Engine\Registry;
use Opencart\Extension\HostManagement\Admin\File\Config;
use Opencart\Extension\HostManagement\Admin\Logging\Log;
use Opencart\Extension\HostManagement\Admin\Messaging\MessageBag;
use Opencart\Extension\HostManagement\Admin\Validation\Validator;
use Opencart\Admin\Model\Extension\HostManagement\Other\HostManagement as Model;


/**
 * Host management extension controller.
 */
class HostManagement extends Controller
{
    /**
     * Extension DB code.
     *
     * @var string
     */
    protected static string $code = 'host_management';

    /**
     * Log messages prefix.
     *
     * @var string
     */
    protected static string $log_prefix = '[Extension: Host management] - ';

    /**
     * Model property name.
     *
     * @var string
     */
    protected static string $model_name = 'model_extension_host_management_other_host_management';

    /**
     * Extension route.
     *
     * @var string
     */
    protected static string $route = 'extension/host_management/other/host_management';

    /**
     * Settings' names.
     *
     * @var array
     */
    protected static array $settings = [
        'admin_dir' => 'host_management_admin_dir',
        'public_dir' => 'host_management_public_dir',
        'status' => 'host_management_status'
    ];

    /**
     * Admin config file path.
     *
     * @var string
     */
    protected static string $adminPath = DIR_APPLICATION . 'config.php';

    /**
     * Public config file path.
     *
     * @var string
     */
    protected static string $publicPath = DIR_OPENCART . 'config.php';

    /**
     * Config file manager instance.
     *
     * @var Config
     */
    protected Config $file;

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
     * Model instance.
     *
     * @var Model
     */
    protected Model $model;

    /**
     * Validator instance.
     *
     * @var Validator
     */
    protected Validator $validator;


    /**
     * Creates a new instance.
     *
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);

        $this->load->language(static::$route);

        $this->model = new Model($registry);
        $this->log = new Log(parent::__get('log'), static::$log_prefix);
        $this->file = new Config($this->log, new MessageBag($this->language));
        $this->messages = new MessageBag($this->language);
        $this->validator = new Validator();
    }

// #region INTERNAL METHODS

    /**
     * Logs a message.
     *
     * @param string $message
     * @return void
     */
    protected function log(string $message): void
    {
        $this->log->write(static::$log_prefix . $message);
    }

    /**
     * Sends json response.
     *
     * @param array|null $messages
     * @return void
     */
    protected function jsonResponse(?array $messages = null): void
    {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($messages ?? $this->messages->get()));
    }

    /**
     * Saves protocol, default host, admin and catalog directories read from admin config file.
     *
     * @return array Config data.
     */
    protected function saveConfigFileData(): array
    {
        $config_data = $this->file->readConfig(static::$adminPath);

        if (!$config_data) {
            $this->messages->mergeErrors($this->file->getErrors());

            return [];
        }

        if (!$this->validator->hasSameHosts($config_data)) {
            $this->messages->error('error_same');

            return [];
        }

        if (!$this->validator->isValidProtocol($config_data['server']['protocol'])) {
            $this->messages->error('error_protocol');

            return [];
        }

        if (!$this->validator->isValidHostname($config_data['server']['hostname'])) {
            $this->messages->error('error_hostname');

            return [];
        }

        if (!$this->validator->isValidAdminDir($config_data['server']['dir'])) {
            $this->messages->error('error_dir', 'warning', 'text_admin');

            return [];
        }

        if (!$this->validator->isValidPublicDir($config_data['catalog']['dir'])) {
            $this->messages->error('error_dir', 'warning', 'text_public');

            return [];
        }

        $this->load->model('setting/setting');

        $this->model_setting_setting->editSetting(
            static::$code,
            [
                static::$settings['admin_dir'] => $config_data['server']['dir'],
                static::$settings['public_dir'] => $config_data['catalog']['dir']
            ]
        );
        
        if (!empty($this->model->getDefault())) {
            $this->model->updateDefault($config_data['server']);
        } else {
            $this->model->insert([
                'protocol' => $config_data['server']['protocol'],
                'hostname' => $config_data['server']['hostname'],
                'default' => true
            ]);
        }

        return $config_data;
    }

    /**
     * Validates posted hosts.
     *
     * @param array $hosts
     * @return void
     */
    protected function validateHosts(array $hosts): void
    {
        $default_hosts = 0;

        foreach ($hosts as $key => $host) { 
            if ((bool)$host['default']) $default_hosts++;

            if (!$this->validator->isValidProtocol($host['protocol'])) {
                $this->messages->error('error_protocol', 'protocol_' . $key);
            }

            if (!$this->validator->isValidHostname($host['hostname'])) {
                $this->messages->error('error_hostname', 'hostname_' . $key);
            }
        }

        if ($this->messages->hasErrors()) {
            $this->messages->error('error_warning');

            return;
        }

        if ($default_hosts !== 1) {
            $this->messages->error('error_default_count');
        }
    }

    /**
     * Asserts both config files are writable.
     *
     * @return boolean
     */
    protected function configFilesWritable(): bool
    {
        $file_paths = [ static::$adminPath, static::$publicPath ];

        foreach ($file_paths as $path) {
            if (!is_writable($path)) {
                $this->messages->error('error_write_access', 'warning', $path);
    
                return false;
            }
        }

        return true;
    }

    /**
     * Enables the extension.
     *
     * @param array $hosts
     * @param array $dirs
     * @return bool
     */
    protected function enable(array $hosts, array $dirs): bool
    {
        if (!$this->configFilesWritable()) return false;

        if (!$this->file->edit(static::$adminPath, $hosts, $dirs)) {
            $this->messages->mergeErrors($this->file->getErrors());

            return false;
        }

        if (!$this->file->edit(static::$publicPath, $hosts, $dirs)) {
            $this->messages->mergeErrors($this->file->getErrors());

            return false;
        }

        return true;
    }

    /**
     * Updates config hosts.
     *
     * @param array $hosts
     * @return bool
     */
    protected function update(array $hosts): bool
    {
        if (!$this->configFilesWritable()) return false;

        if (!$this->file->update(static::$adminPath, $hosts)) {
            $this->messages->mergeErrors($this->file->getErrors());

            return false;
        }

        if (!$this->file->update(static::$publicPath, $hosts)) {
            $this->messages->mergeErrors($this->file->getErrors());

            return false;
        }

        return true;
    }

    /**
     * Disables the extension.
     *
     * @param array $hosts
     * @param array $dirs
     * @return bool
     */
    protected function disable(array $hosts, array $dirs): bool
    {
        $default_host = null;

        foreach ($hosts as $host) {
            if ((bool)$host['default']) {
                $default_host = $host;

                break;
            }
        }

        if (!$default_host) {
            $this->messages->error('error_default_host');

            return false;
        }

        if (!$this->configFilesWritable()) return false;

        if (!$this->file->restore(static::$adminPath, $default_host, $dirs)) {
            $this->messages->mergeErrors($this->file->getErrors());

            return false;
        }

        if (!$this->file->restore(static::$publicPath, $default_host, $dirs)) {
            $this->messages->mergeErrors($this->file->getErrors());

            return false;
        }

        return true;
    }

// #endregion

// #region PUBLIC INTERFACE

    /**
     * Installs the extension.
     *
     * @return void
     */
    public function install(): void
    {
        if (!$this->user->hasPermission('modify', 'extension/other')) {
            $this->log($this->language->get('error_perm_other'));

            return;
        }

        $this->model->install();

        $this->saveConfigFileData();
    }

    /**
     * Uninstalls the extenison.
     *
     * @return void
     */
    public function uninstall(): void
    {
        if (!$this->user->hasPermission('modify', 'extension/other')) {
            $this->log($this->language->get('error_perm_other'));

            return;
        }

        // We need to check files, OC deletes extension settings before running controller uninstall
        if (!empty($this->config->get(static::$settings['status']))) {
            $hosts = $this->model->getAll();
            $dirs = [
                'admin' => $this->config->get(static::$settings['admin_dir']),
                'public' => $this->config->get(static::$settings['public_dir'])
            ];

            $this->disable($hosts, $dirs);

            if ($this->messages->hasErrors()) {
                $this->log($this->messages->getFirstError());
            }
        }

        $this->model->uninstall();
    }

    /**
     * Displays extension settings page.
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

        $data['hosts'] = $this->model->getAll();
        $data['settings'] = static::$settings;

        foreach (static::$settings as $setting_db_key) {
            $data[$setting_db_key] = $this->config->get($setting_db_key);
        }

        if (
            !isset($data[static::$settings['admin_dir']], $data[static::$settings['public_dir']])
            || empty($data['hosts'])
        ) {
            $config_data = $this->saveConfigFileData();

            if (empty($config_data)) {
                $data['read_error'] = $this->messages->getFirstError();
            }

            $data['hosts'] = $this->model->getAll();
            $data[static::$settings['admin_dir']] = $config_data['server']['dir'] ?? '';
            $data[static::$settings['public_dir']] = $config_data['catalog']['dir'] ?? '';
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
        if (!$this->user->hasPermission('modify', 'common/security')) {
            $this->messages->error('error_perm_security');
            $this->log($this->messages->getFirstError());
            $this->jsonResponse();

            return;
        }

        $hosts = $this->request->post['hosts'];

        $this->validateHosts($hosts);

        if ($this->messages->hasErrors()) {
            $this->jsonResponse();

            return;
        }

        $this->model->truncate();
        $this->model->insertMany($hosts);

        $this->messages->success('text_success_hosts');

        $dirs = [
            'admin' => $this->config->get(static::$settings['admin_dir']),
            'public' => $this->config->get(static::$settings['public_dir'])
        ];

        $was_enabled = empty($this->config->get(static::$settings['status']));
        $requested = empty($this->request->post[static::$settings['status']]);
        $is_enabled = $was_enabled;
        
        if (!$was_enabled && $requested) {
            $is_enabled = $this->enable($hosts, $dirs);
        } else if ($was_enabled && $requested) {
            $this->update($hosts);
        } else if ($was_enabled && !$requested) {
            $is_enabled = !$this->disable($hosts, $dirs);
        }

        if ($is_enabled !== $was_enabled) {
            $this->load->model('setting/setting');

            $settings = [
                static::$settings['admin_dir'] => $dirs['admin'],
                static::$settings['public_dir'] => $dirs['public']
            ];

            if ($is_enabled) $settings['status'] = true;

            $this->model_setting_setting->editSetting(static::$code, $settings);
        }

        if ($is_enabled !== $requested) {
            $this->messages->error('error_status', 'status');
        }

        if (!$this->messages->hasErrors()) {
            $this->messages->success('text_success');
        }

        $this->jsonResponse();
    }

// #endregion PUBLIC INTERFACE
}