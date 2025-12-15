<?php

declare(strict_types=1);
/**
 * ---------------------------------------------------------------------
 *  catsurvey is a plugin to manage inquests by ITIL categories
 *  ---------------------------------------------------------------------
 *  LICENSE
 *
 *  This file is part of catsurvey.
 *
 *  catsurvey is free software;
if (!defined('GLPI_ROOT')) { define('GLPI_ROOT', realpath(__DIR__ . '/../..')); }
 you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  catsurvey is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with Formcreator. If not, see <http://www.gnu.org/licenses/>.
 *  ---------------------------------------------------------------------
 *  @copyright Copyright © 2022-2024 probeSys'
 *  @license   http://www.gnu.org/licenses/agpl.txt AGPLv3+
 *  @link      https://github.com/Probesys/glpi-plugins-catsurvey
 *  @link      https://plugins.glpi-project.org/#/plugin/catsurvey
 *  ---------------------------------------------------------------------
 */


/**
 * Install the catsurvey plugin.
 *
 * @return bool
 */
function plugin_catsurvey_install(): bool {
    global $DB;

    if (!$DB->tableExists("glpi_plugin_catsurvey_catsurveys")) {
        $migration = new \Migration(0);
        $migration->displayMessage(false);
        $table = 'glpi_plugin_catsurvey_catsurveys';
        $fields = [
            'id' => "INT UNSIGNED NOT NULL DEFAULT '0' COMMENT 'RELATION to glpi_itilcategories (id)' PRIMARY KEY",
            'max_closedate' => "TIMESTAMP DEFAULT NULL",
            'inquest_config' => "INT(11) NOT NULL DEFAULT '1'",
            'inquest_rate' => "INT(11) NOT NULL DEFAULT '0'",
            'inquest_delay' => "INT(11) NOT NULL DEFAULT '-10'",
            'inquest_URL' => "VARCHAR(255) DEFAULT NULL"
        ];
        $migration->migrationOneTable($table, $fields);
        $migration->executeMigration();
    }

    CronTask::Register('PluginCatsurveyCatsurvey', 'createinquestbycat', '86400');
    return true;
}

function plugin_catsurvey_uninstall() {
    $migration = new \Migration(0);
    $migration->displayMessage(false);
    $migration->dropTable('glpi_plugin_catsurvey_catsurveys');
    $migration->executeMigration();
    return true;
}
