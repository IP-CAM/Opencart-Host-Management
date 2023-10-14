<?php

namespace Opencart\Admin\Model\Extension\HostManagement\Other;

use Opencart\System\Engine\Model;


class HostManagement extends Model
{
    /**
     * DB table name.
     *
     * @var string
     */
    protected static $table_name = 'host_management';


    /**
     * Creates DB table.
     *
     * @return void
     */
    public function install(): void {
        $query = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . static::$table_name . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `protocol` varchar(5) NOT NULL,
                `hostname` varchar(255) NOT NULL,
                `default` tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

        $this->db->query($query);
    }

    /**
     * Drops DB table.
     *
     * @return void
     */
    public function uninstall(): void
    {
        $query = "DROP TABLE IF EXISTS `" . DB_PREFIX . static::$table_name . "`";

        $this->db->query($query);
    }

    /**
     * Inserts host data into DB.
     *
     * @param array $data
     * @return void
     */
    public function insert(array $data): void
    {
        $data['default'] ??= false;

        $query = "INSERT INTO `" . DB_PREFIX . static::$table_name . "` SET
                `protocol` = '" . $this->db->escape($data['protocol']) . "',
                `hostname` = '" . $this->db->escape($data['hostname']) . "',
                `default` = '" . (bool)$data['default'] . "'";

        $this->db->query($query);
    }

    /**
     * Gets all hosts from DB.
     *
     * @return array|bool
     */
    public function all(): array|bool
    {
        $query = "SELECT * FROM `" . DB_PREFIX . static::$table_name . "`";

        $result = $this->db->query($query);

        return is_object($result) ? $result->rows : $result;
    }
}