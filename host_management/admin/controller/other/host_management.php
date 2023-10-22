<?php

namespace Opencart\Admin\Controller\Extension\HostManagement\Other;

use Opencart\System\Engine\Controller;
use Opencart\System\Engine\Registry;
use Opencart\Extension\HostManagement\Admin\File\Config;
use Opencart\Extension\HostManagement\Admin\Logging\Log;
use Opencart\Extension\HostManagement\Admin\Messaging\MessageBag;
use Opencart\Extension\HostManagement\Admin\Repository\DatabaseRepository;
use Opencart\Extension\HostManagement\Admin\Validation\Validator;


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
    protected static string $code = 'other_host_management';

    /**
     * Log messages prefix.
     *
     * @var string
     */
    protected static string $log_prefix = '[Extension: Host management] - ';

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
        'admin_dir' => 'other_host_management_admin_dir',
        'public_dir' => 'other_host_management_public_dir',
        'status' => 'other_host_management_status'
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
     * Repository instance.
     *
     * @var DatabaseRepository
     */
    protected DatabaseRepository $repository;

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

        $this->log = new Log(parent::__get('log'), static::$log_prefix);
        $this->file = new Config($this->log, new MessageBag($this->language));
        $this->messages = new MessageBag($this->language);
        $this->repository = new DatabaseRepository($registry);
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
        $this->log->write($message);
    }

    /**
     * Joins array of messages for displaying as alerts.
     *
     * @param string|array $messages
     * @param string $icon
     * @return string
     */
    protected function joinMessages(string|array $messages, string $icon): string
    {
        if (!is_array($messages)) return $messages;

        return implode('<br><i class="fa-solid ' .$icon . '"></i> ', $messages);
    }

    /**
     * Sends json response.
     *
     * @param array|null $messages
     * @return void
     */
    protected function jsonResponse(?array $messages = null): void
    {
        $messages ??= $this->messages->get();

        if (isset($messages['success'])){
            $messages['success'] = $this->joinMessages($messages['success'], 'fa-circle-check');
        }

        if (isset($messages['error']['warning']) && is_array($messages['error']['warning'])) {
            $messages['error']['warning'] =
                $this->joinMessages($messages['error']['warning'], 'fa-circle-exclamation');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($messages));
    }

    /**
     * Sends HTML response.
     *
     * @param array $data
     * @return void
     */
    protected function htmlResponse(array $data): void
    {
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $admin_path = str_replace(DIR_OPENCART, '', DIR_APPLICATION);
        $extension_path = str_replace(DIR_OPENCART, '', DIR_EXTENSION);

        $js = '<script src="' . preg_replace('/^[^\/]+\/$/', '../', $admin_path) . $extension_path;
        $js .= 'host_management/admin/view/javascript/common.js" type="text/javascript"></script>';

        $data['footer'] = str_replace('</body>', $js . '</body>', $data['footer']);

        $this->response->setOutput($this->load->view(static::$route, $data));
    }

    /**
     * Updates extension settings.
     *
     * @param string $admin_dir
     * @param string $public_dir
     * @param boolean $status
     * @return void
     */
    protected function updateSettings(
        string $admin_dir,
        string $public_dir,
        bool $status = false
    ): void
    {
        $this->load->model('setting/setting');

        $settings = [
            static::$settings['admin_dir'] => $admin_dir,
            static::$settings['public_dir'] => $public_dir
        ];

        if ($status) {
            $settings[static::$settings['status']] = $status;
        }

        $this->model_setting_setting->editSetting(static::$code, $settings);
    }

    /**
     * Saves default host, it's protocol, admin and catalog directories read from admin config file.
     *
     * @return array Config data.
     */
    protected function saveConfigFileData(): array
    {
        $config_data = $this->file->readConfig(static::$adminPath);

        if (!$config_data) {
            $this->messages->mergeErrors($this->file->getMessages()->getErrors());

            return [];
        }

        if (
            $config_data['server']['protocol'] !== $config_data['catalog']['protocol']
            || $config_data['server']['hostname'] !== $config_data['catalog']['hostname']
        ) {
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

        $this->updateSettings($config_data['server']['dir'], $config_data['catalog']['dir']);

        if (!empty($this->repository->getDefault())) {
            $this->repository->updateDefault($config_data['server']);
        } else {
            $this->repository->insert([
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
            if (isset($host['default']) && (bool)$host['default']) $default_hosts++;

            if (
                !isset($host['protocol'])
                || !$this->validator->isValidProtocol($host['protocol'])
            ) {
                $this->messages->error('error_protocol', 'protocol_' . $key);
            }

            if (
                !isset($host['hostname'])
                || !$this->validator->isValidHostname($host['hostname'])
            ) {
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
     * Enables the extension.
     *
     * @param array $hosts
     * @param array $dirs
     * @return bool
     */
    protected function enable(array $hosts, array $dirs): bool
    {
        if (
            $this->file->canEdit(static::$adminPath)
            && $this->file->canEdit(static::$publicPath)
            && $this->file->edit(static::$adminPath, $hosts, $dirs)
            && $this->file->edit(static::$publicPath, $hosts, $dirs)
        ) {
            return true;
        }

        $this->messages->mergeErrors($this->file->getMessages()->getErrors());

        return false;
    }

    /**
     * Updates config hosts.
     *
     * @param array $hosts
     * @return bool
     */
    protected function update(array $hosts): bool
    {
        if (
            $this->file->canUpdate(static::$adminPath)
            && $this->file->canUpdate(static::$publicPath)
            && $this->file->update(static::$adminPath, $hosts)
            && $this->file->update(static::$publicPath, $hosts)
        ) {
            return true;
        }

        $this->messages->mergeErrors($this->file->getMessages()->getErrors());

        return false;
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
        }

        if (
            $default_host
            && $this->file->canRestore(static::$adminPath)
            && $this->file->canRestore(static::$publicPath)
            && $this->file->restore(static::$adminPath, $default_host, $dirs)
            && $this->file->restore(static::$publicPath, $default_host, $dirs)
        ) {
            return true;
        }

        $this->messages->mergeErrors($this->file->getMessages()->getErrors());

        return false;
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

        $this->repository->install();

        $config_data = $this->saveConfigFileData();

        if (empty($config_data)) {
            $this->log($this->lanugage->get('error_install_data'));
        }
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

        if (
            !empty($this->config->get(static::$settings['status']))
            && $this->file->canRestore(static::$adminPath)
            && $this->file->canRestore(static::$publicPath)
        ) {
            $default_host = $this->repository->getDefault();
            $dirs = [
                'admin' => $this->config->get(static::$settings['admin_dir']),
                'public' => $this->config->get(static::$settings['public_dir'])
            ];

            $this->file->restore(static::$adminPath, $default_host, $dirs);
            $this->file->restore(static::$publicPath, $default_host, $dirs);
        }
        
        if ($this->file->getMessages()->hasErrors()) {
            foreach ($this->file->getMessages()->getErrors() as $error) {
                $this->log($error);
            }
        }

        $this->repository->uninstall();
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

        $data['hosts'] = $this->repository->getAll();
        $data['settings'] = static::$settings;

        foreach (static::$settings as $setting_db_key) {
            $data[$setting_db_key] = $this->config->get($setting_db_key);
        }

        if (
            $this->validator->isValidAdminDir($data[static::$settings['admin_dir']])
            && $this->validator->isValidPublicDir($data[static::$settings['public_dir']])
            && !empty($data['hosts'])
        ) {
            $this->htmlResponse($data);

            return;
        }

        $config_data = $this->saveConfigFileData();

        if (empty($config_data)) {
            $data['read_error'] = $this->messages->getFirstError();
        }

        $data['hosts'] = $this->repository->getAll();
        $data[static::$settings['admin_dir']] = $config_data['server']['dir'] ?? '';
        $data[static::$settings['public_dir']] = $config_data['catalog']['dir'] ?? '';

        $this->htmlResponse($data);
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

        array_walk($hosts, function(&$host) {
            $host['default'] ??= false;
        });

        $this->validateHosts($hosts);

        if ($this->messages->hasErrors()) {
            $this->jsonResponse();

            return;
        }

        $this->repository->truncate();
        $this->repository->insertMany($hosts);

        $this->messages->success('text_success_hosts');

        $dirs = [
            'admin' => $this->config->get(static::$settings['admin_dir']),
            'public' => $this->config->get(static::$settings['public_dir'])
        ];

        $result = $status = !empty($this->config->get(static::$settings['status']));
        $requested = !empty($this->request->post[static::$settings['status']]);

        if (!$status && !$requested) {
            $this->jsonResponse();

            return;
        } else if (!$status && $requested) {
            $result = $this->enable($hosts, $dirs);
        } else if ($status && !$requested) {
            $result = !$this->disable($hosts, $dirs);
        } else {
            $this->update($hosts);
        }

        if ($result !== $status) {
            $this->updateSettings($dirs['admin'], $dirs['public'], $result);
        }

        if ($result !== $requested) {
            $this->messages->error('error_status', 'status');
        }

        if (!$this->messages->hasErrors()) {
            $this->messages->success('text_success_files');
        }

        $this->jsonResponse();
    }

// #endregion PUBLIC INTERFACE
}