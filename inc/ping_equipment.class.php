<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 addressing plugin for GLPI
 Copyright (C) 2009-2016 by the addressing Development Team.

 https://github.com/pluginsGLPI/addressing
 -------------------------------------------------------------------------

 LICENSE

 This file is part of addressing.

 addressing is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 addressing is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with addressing. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginAddressingPing_Equipment
 */
class PluginAddressingPing_Equipment {

   function showForm($ID, $options = []) {
      global $DB, $CFG_GLPI;

      $obj = $options['obj'];
      //Html::printCleanArray($obj);
      $dbu      = new DbUtils();
      $itemtype = $dbu->getItemTypeForTable($obj->getTable());

      $list_ip  = [];

      /*if ($itemtype == 'NetworkEquipment') {
         $query = "SELECT `ip`
                   FROM `glpi_networkequipments`
                   WHERE `id` = '".$obj->fields['id']."'";

         $res = $DB->query($query);
         while ($row = $DB->fetch_array($res)) {
            if ($row['ip'] != '') {
               $list_ip[$row['ip']] = $row['ip'];
            }
         }
      }
      $tmp = array_values($list_ip);
      if (count($tmp) > 0 && $tmp[0] == '') {
         array_pop($list_ip);
      }*/

      $query = "SELECT `glpi_networknames`.`name`, `glpi_ipaddresses`.`name` as ip, `glpi_networkports`.`items_id`
               FROM `glpi_networkports` 
               LEFT JOIN `" . $obj->getTable() . "` ON (`glpi_networkports`.`items_id` = `" . $obj->getTable() . "`.`id`
                              AND `glpi_networkports`.`itemtype` = '" . $itemtype . "')
               LEFT JOIN `glpi_networknames` ON (`glpi_networkports`.`id` =  `glpi_networknames`.`items_id`)
               LEFT JOIN `glpi_ipaddresses` ON (`glpi_ipaddresses`.`items_id` = `glpi_networknames`.`id`)
                WHERE `" . $obj->getTable() . "`.`id` = '".$obj->fields['id']."'";

      $res = $DB->query($query);
      while ($row = $DB->fetch_array($res)) {
         if ($row['ip'] != '') {
            $port = $row['ip'];
            if ($row['name'] != '') {
               $port = $row['name']." ($port)";
            }
            $list_ip[$row['ip']] = $port;
         }
      }
      echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2 left'>";
      echo "<tr><th colspan='4'>".__('IP ping', 'addressing')."</th></tr>";

      if (count($list_ip) > 0) {
         echo "<tr class='tab_bg_1'>";
         echo "<td>".__('IP')." : </td>";
         echo "<td colspan='3'>";
         echo "<select id='ip'>";
         echo "<option>".Dropdown::EMPTY_VALUE."</option>";
         foreach ($list_ip as $ip => $name) {
            echo "<option value='$ip'>$name</option>";
         }
         echo "</select>";
         echo "&nbsp;<input class='submit' type='button' value='".
               __s('Ping', 'addressing')."' id='ping_ip'>";
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>".__('Result', 'addressing')." : </td>";
         echo "<td colspan='3'>";
         echo "<div id='ping_response' class='plugin_addressing_ping_equipment'></div>";
         echo "</td></tr>";
      }
      echo "</table>";

      echo Html::scriptBlock("$(document).on('click', '#ping_ip', function(event) {
         $('#ping_response').load('".$CFG_GLPI["root_doc"]."/plugins/addressing/ajax/ping.php', {
            'ip': $('#ip').val()
         })
      });");

      if (count($list_ip) == 0) {
         echo __('No IP for this equipment', 'addressing');
      }
   }


   /**
    * @param $system
    * @param $ip
    *
    * @return array
    */
   function ping($system, $ip) {
      $error = 1;
      $list  ='';
      switch ($system) {
         case 0 :
            // linux ping
            exec("ping -c 1 -w 1 ".$ip, $list, $error);
            break;

         case 1 :
            //windows
            exec("ping.exe -n 1 -w 1 -i 4 ".$ip, $list, $error);
            break;

         case 2 :
            //linux fping
            exec("fping -r1 -c1 -t100 ".$ip, $list, $error);
            break;

         case 3 :
            // *BSD ping
            exec("ping -c 1 -W 1 ".$ip, $list, $error);
            break;

         case 4 :
            // MacOSX ping
            exec("ping -c 1 -t 1 ".$ip, $list, $error);
            break;
      }
      $list_str = implode('<br />', $list);

      return [$list_str, $error];
   }


   /**
    * @param \CommonGLPI $item
    * @param int         $tabnum
    * @param int         $withtemplate
    *
    * @return bool
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      $ping = Session::haveRight('plugin_addressing_use_ping_in_equipment', '1');

      if ($ping && in_array($item->getType(), PluginAddressingAddressing::getTypes())) {
         if ($item->getField('id')) {
            $options = ['obj' => $item];

            $pingE = new self();
            $pingE->showForm($item->getField('id'), $options);
         }
      }
      return true;
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      $ping = Session::haveRight('plugin_addressing_use_ping_in_equipment', '1');

      if ($ping && in_array($item->getType(), PluginAddressingAddressing::getTypes())) {
         if ($item->getField('id')) {
            return ['1' => __('IP ping', 'addressing')];
         }
      }
      return '';
   }


   static function postinit() {

      foreach (PluginAddressingAddressing::getTypes() as $type) {
         CommonGLPI::registerStandardTab($type, __CLASS__);
      }
   }

}
