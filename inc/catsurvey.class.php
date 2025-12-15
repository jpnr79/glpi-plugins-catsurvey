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
 * Main class for the catsurvey plugin.
 */
class PluginCatsurveyCatsurvey extends CommonDBTM
{
    /**
     * Check if the user can create.
     */
    public static function canCreate(): bool
    {
        return Session::haveRight("entity", CREATE);
    }

    /**
     * Check if the user can view.
     */
    public static function canView(): bool
    {
        return Session::haveRight("entity", UPDATE);
    }

    /**
     * Check if the user can delete.
     */
    public static function canDelete(): bool
    {
        return Session::haveRight("entity", UPDATE);
    }

    /**
     * Get cron info.
     *
     * @param string $name
     * @return array
     */
    public static function cronInfo(string $name): array
    {
        switch ($name) {
            case 'createinquestbycat':
                return ['description' => __('Generating satisfaction surveys by categories', 'catsurvey')];
        }
        return [];
    }

    /**
     * Cron task to create inquest by category.
     *
     * @param mixed $task
     * @return void
     */
    public static function cronCreateInquestByCat($task): void
    {
        global $DB;

        $conf        = new self();
        $inquest     = new TicketSatisfaction();
        $tot         = 0;
        $tabcategories = [];

        foreach ($DB->request('glpi_itilcategories') as $cat) {
            $rate = self::getUsedConfig($cat['id'], 'inquest_rate');
            if ($rate > 0) {
                $tabcategories[$cat['id']] = $rate;
            }
        }

        foreach ($tabcategories as $cat => $rate) {
            $delay         = self::getUsedConfig($cat, 'inquest_delay');
            $type          = self::getUsedConfig($cat, 'inquest_config');
            $max_closedate = self::getUsedConfig($cat, 'max_closedate');

            $query = [
                'SELECT' => ['glpi_tickets.id', 'glpi_tickets.closedate', 'glpi_tickets.itilcategories_id'],
                'FROM' => 'glpi_tickets',
                'LEFT JOIN' => [
                    'glpi_ticketsatisfactions' => [
                        'ON' => [
                            'glpi_ticketsatisfactions.tickets_id' => 'glpi_tickets.id'
                        ]
                    ]
                ],
                'WHERE' => [
                    'glpi_tickets.itilcategories_id' => $cat,
                    'glpi_tickets.is_deleted' => 0,
                    'glpi_tickets.status' => 6,
                    ['>', 'glpi_tickets.closedate', $max_closedate],
                    ['IS NULL', 'glpi_ticketsatisfactions.id']
                ],
                'ORDER' => 'glpi_tickets.closedate ASC'
            ];

            // Add the date condition separately since it's complex
            $query['WHERE'][] = [
                'RAW' => "ADDDATE(`glpi_tickets`.`closedate`, INTERVAL $delay DAY) <= NOW()"
            ];

         $nb            = 0;
         $max_closedate = '';

         foreach ($DB->request($query) as $tick) {
            $max_closedate = $tick['closedate'];
            if (mt_rand(1, 100) <= $rate) {
               if ($inquest->add(['tickets_id'  => $tick['id'],
                                       'date_begin'  => $_SESSION["glpi_currenttime"],
                                       'itilcategories_id' => $tick['itilcategories_id'],
                                       'type'        => $type])) {
                  $nb++;
               }
            }
         }

         if ($nb) {
            $tot += $nb;
            $task->addVolume($nb);
            $task->log(sprintf(__('%1$s: %2$s'),
                               Dropdown::getDropdownName('glpi_itilcategories', $cat), $nb));
         }
      }

    // Do not return a value from a void method
    // $tot > 0 can be used for logging or other purposes if needed
    // but must not be returned from a void method
    // Removed return statement for PHP 8.4 compatibility
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == 'ITILCategory') {
           return __("Satisfaction survey");
      }
       return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item->getType() == 'ITILCategory') {
           $cat = new self();
           $ID = $item->getField('id');
         if (!$cat->getfromDB($ID)) {
            $cat->addCat($ID);
         }
           $cat->showForm($ID);
      }
       return true;
   }

   function addCat($ID) {

       $this->add(['id' => $ID]);
   }

   static function getUsedConfig($id, $fieldval = '') {
       global $DB;

       $cat = new self();

      if ($cat->getFromDB($id)) {
          return $cat->fields[$fieldval];
      }
   }


   function showForm($id, $options = []) {
       global $CFG_GLPI, $DB;

       $cat = new self();
       $ID = $this->getField('id');

      if (Session::haveRight("entity", UPDATE)) {
          $canedit = true;
      } else {
          $canedit = false;
      }

       echo "<div class='spaced'>";
      if ($canedit) {
          echo "<form method='post' name=form action='".Toolbox::getItemTypeFormURL(__CLASS__)."'>";
      }

       echo "<table class='tab_cadre_fixe'>";
       echo "<tr><th colspan='4'>".__('Configuring the satisfaction survey')."</th></tr>";

       echo "<div id='inquestconfig'>";

       echo "<tr class='tab_bg_1'>".
            "<td width='50%'>".__('Configuring the satisfaction survey')."</td>";
       echo "<td>";

       $typeinquest = [
           1 => __('Internal survey'),
       ];

       $rand = Dropdown::showFromArray('inquest_config', $typeinquest,
                                     $options = ['value' => self::getUsedConfig($ID, 'inquest_config')]);
       echo "</td></tr>\n";

       $inquestconfig = $cat->getfromDB('inquest_config');
       $inquestrate   = $cat->getfromDB('inquest_rate');
       $max_closedate = $cat->getfromDB('max_closedate');

       $_POST  = ['inquest_config' => $cat->getfromDB('inquest_config'),
                       'id' => $ID];
       $params = ['inquest_config' => '__VALUE__',
                       'id' => $ID];
       if (isset($_POST['inquest_config']) && isset($_POST['id'])) {
          if ($cat->getFromDB($_POST['id'])) {
              $inquest_config = $cat->getfield('inquest_config');
              $inquest_delay  = $cat->getfield('inquest_delay');
              $inquest_rate   = $cat->getfield('inquest_rate');
              $max_closedate  = $cat->getfield('max_closedate');
          } else {
              $inquest_config = $_POST['inquest_config'];
              $inquest_delay  = -1;
              $inquest_rate   = -1;
              $max_closedate  = $_SESSION["glpi_currenttime"];
          }

           echo "<tr class='tab_bg_1'><td width='50%'>".__('Create survey after')."</td>";
           echo "<td>";

           Dropdown::showNumber('inquest_delay', [
             'value' => $inquest_delay,
             'min'   => 1,
             'max' => 90,
             'step' => 1,
             'unit' => 'day',
             'toadd' => [0 => __('As soon as possible')]
           ]);
           echo "</td></tr>";

           echo "<tr class='tab_bg_1'>".
                "<td>".__('Rate to trigger survey')."</td>";
           echo "<td>";

           Dropdown::showNumber('inquest_rate', [
             'value' => $inquest_rate,
             'min'   => 10,
             'max' => 100,
             'step' => 10,
             'unit' => '%',
             'toadd' => [0 => __('Disabled')]
           ]);
           echo "</td></tr>";

               echo "<tr class='tab_bg_1'><td>". __('For tickets closed after')."</td>";
               echo "<td>";
               Html::showDateTimeField("max_closedate", [
                 'value'=> $max_closedate,
                 'timestep' => 1,
                 'maybeempty' => true,
                 'canedit' => true,
               ]);
               echo "</td></tr>";

       }

       echo "</td></tr>";

       if ($canedit) {
           echo "<tr class='tab_bg_2'>";
           echo "<td class='center' colspan='4'>";
           echo "<input type='hidden' name='id' value='".$ID."'>";
           echo "<input type='submit' name='update' value=\""._sx('button', 'Save')."\"
                         class='submit'>";

           echo "</td></tr>";
           echo "</table>";
           Html::closeForm();

       } else {
           echo "</table>";
       }

       echo "</div>";
   }

}
