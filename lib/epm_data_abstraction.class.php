<?php
/**
 * Description of freepbx
 *
 * @author Andrew
 */

namespace FreePBX\modules;
 
class epm_data_abstraction {
    public $config;
    public $configmod;
    public $global_cfg;

    function __construct($config, $configmod) {

    	$this->config = $config;
    	$this->configmod = $configmod;
    }
	
    function all_products() {
        $temp = sql("SELECT * FROM endpointman_product_list WHERE id > 0",'getAll',DB_FETCHMODE_ASSOC);
        return($temp);
    }

    function all_devices() {
        $sql = 'SELECT endpointman_mac_list.id , endpointman_mac_list.mac , endpointman_model_list.model, endpointman_model_list.enabled , endpointman_brand_list.name, endpointman_mac_list.global_custom_cfg_data, endpointman_mac_list.template_id FROM endpointman_mac_list , endpointman_model_list , endpointman_brand_list WHERE ( endpointman_model_list.id = endpointman_mac_list.model ) AND ( endpointman_model_list.brand = endpointman_brand_list.id )';
        $temp = sql($sql,'getAll',DB_FETCHMODE_ASSOC);
        return($temp);
    }

    function escapeSimple($query) {
        global $db;
        return $db->escapeSimple($query);
    }

    /**
     *
     * @return <type>
     * @example
     * array(1) {
          [0]=>
          array(9) {
            ["id"]=>
            string(3) "213"
            ["brand"]=>
            string(1) "2"
            ["model"]=>
            string(7) "GXP2000"
            ["max_lines"]=>
            string(1) "4"
            ["template_list"]=>
            string(17) "template_data.xml"
            ["template_data"]=>
            string(7263) "a:42:{i:0;a:2:{s:11:"description";s:16:"General Settings";s:4:"type";s:5:"group";}i:1;a:4:{s:8:"variable";s:11:"$admin_pass";s:13:"default_value";s:5:"admin";s:11:"description";s:23:"Administration Password";s:4:"type";s:5:"input";}i:2;a:1:{s:4:"type";s:5:"break";}i:3;a:4:{s:8:"variable";s:18:"$firmware_srv_path";s:13:"default_value";a:0:{}s:11:"description";s:20:"Firmware Server Path";s:4:"type";s:5:"input";}i:4;a:5:{s:8:"variable";s:22:"$firmware_upgrade_type";s:13:"default_value";s:1:"0";s:11:"description";s:28:"Firmware Upgrade Server Type";s:4:"type";s:5:"radio";s:4:"data";a:2:{i:0;a:2:{s:4:"text";s:4:"TFTP";s:5:"value";s:1:"0";}i:1;a:2:{s:4:"text";s:4:"HTTP";s:5:"value";s:1:"1";}}}i:5;a:5:{s:8:"variable";s:8:"$auto_up";s:13:"default_value";s:1:"0";s:11:"description";s:17:"Automatic Upgrade";s:4:"type";s:5:"radio";s:4:"data";a:2:{i:0;a:2:{s:4:"text";s:3:"Yes";s:5:"value";s:1:"1";}i:1;a:2:{s:4:"text";s:2:"No";s:5:"value";s:1:"0";}}}i:6;a:1:{s:4:"type";s:5:"break";}i:7;a:5:{s:8:"variable";s:21:"$enable_xml_phonebook";s:13:"default_value";s:1:"0";s:11:"description";s:21:"Enable XML Phone Book";s:4:"type";s:5:"radio";s:4:"data";a:3:{i:0;a:2:{s:4:"text";s:9:"Yes(HTTP)";s:5:"value";s:1:"1";}i:1;a:2:{s:4:"text";s:9:"Yes(TFTP)";s:5:"value";s:1:"2";}i:2;a:2:{s:4:"text";s:2:"No";s:5:"value";s:1:"0";}}}i:8;a:4:{s:8:"variable";s:16:"$xml_server_path";s:13:"default_value";a:0:{}s:11:"description";s:26:"XML Phone Book Server Path";s:4:"type";s:5:"input";}i:9;a:1:{s:4:"type";s:5:"break";}i:10;a:5:{s:8:"variable";s:19:"$enable_idle_screen";s:13:"default_value";s:1:"0";s:11:"description";s:22:"Enable XML Idle Screen";s:4:"type";s:5:"radio";s:4:"data";a:3:{i:0;a:2:{s:4:"text";s:9:"Yes(HTTP)";s:5:"value";s:1:"1";}i:1;a:2:{s:4:"text";s:9:"Yes(TFTP)";s:5:"value";s:1:"2";}i:2;a:2:{s:4:"text";s:2:"No";s:5:"value";s:1:"0";}}}i:11;a:4:{s:8:"variable";s:24:"$idle_screen_server_path";s:13:"default_value";a:0:{}s:11:"description";s:23:"Idle Screen Server Path";s:4:"type";s:5:"input";}i:12;a:1:{s:4:"type";s:5:"break";}i:13;a:5:{s:8:"variable";s:21:"$enable_call_features";s:13:"default_value";s:1:"1";s:11:"description";s:20:"Enable Call Features";s:4:"type";s:5:"radio";s:4:"data";a:2:{i:0;a:2:{s:4:"text";s:3:"Yes";s:5:"value";s:1:"1";}i:1;a:2:{s:4:"text";s:2:"No";s:5:"value";s:1:"0";}}}i:14;a:2:{s:11:"description";s:19:"Multi Function Keys";s:4:"type";s:5:"group";}i:15;a:5:{s:8:"variable";s:12:"$key_mode_01";s:13:"default_value";s:1:"0";s:11:"description";s:10:"Key 1 Mode";s:4:"type";s:4:"list";s:4:"data";a:4:{i:0;a:2:{s:4:"text";s:10:"Speed Dial";s:5:"value";s:1:"0";}i:1;a:2:{s:4:"text";s:3:"BLF";s:5:"value";s:1:"1";}i:2;a:2:{s:4:"text";s:16:"Presence Watcher";s:5:"value";s:1:"2";}i:3;a:2:{s:4:"text";s:13:"Eventlist BLF";s:5:"value";s:1:"3";}}}i:16;a:4:{s:8:"variable";s:12:"$key_name_01";s:13:"default_value";s:12:"Speed Dial 1";s:11:"description";s:8:"Key Name";s:4:"type";s:5:"input";}i:17;a:4:{s:8:"variable";s:14:"$speed_dial_01";s:13:"default_value";s:3:"*01";s:11:"description";s:8:"Value #1";s:4:"type";s:5:"input";}i:18;a:1:{s:4:"type";s:5:"break";}i:19;a:5:{s:8:"variable";s:12:"$key_mode_02";s:13:"default_value";s:1:"0";s:11:"description";s:10:"Key 2 Mode";s:4:"type";s:4:"list";s:4:"data";a:4:{i:0;a:2:{s:4:"text";s:10:"Speed Dial";s:5:"value";s:1:"0";}i:1;a:2:{s:4:"text";s:3:"BLF";s:5:"value";s:1:"1";}i:2;a:2:{s:4:"text";s:16:"Presence Watcher";s:5:"value";s:1:"2";}i:3;a:2:{s:4:"text";s:13:"Eventlist BLF";s:5:"value";s:1:"3";}}}i:20;a:4:{s:8:"variable";s:12:"$key_name_02";s:13:"default_value";s:12:"Speed Dial 2";s:11:"description";s:8:"Key Name";s:4:"type";s:5:"input";}i:21;a:4:{s:8:"variable";s:14:"$speed_dial_02";s:13:"default_value";s:3:"*02";s:11:"description";s:8:"Value #2";s:4:"type";s:5:"input";}i:22;a:1:{s:4:"type";s:5:"break";}i:23;a:5:{s:8:"variable";s:12:"$key_mode_03";s:13:"default_value";s:1:"0";s:11:"description";s:10:"Key 3 Mode";s:4:"type";s:4:"list";s:4:"data";a:4:{i:0;a:2:{s:4:"text";s:10:"Speed Dial";s:5:"value";s:1:"0";}i:1;a:2:{s:4:"text";s:3:"BLF";s:5:"value";s:1:"1";}i:2;a:2:{s:4:"text";s:16:"Presence Watcher";s:5:"value";s:1:"2";}i:3;a:2:{s:4:"text";s:13:"Eventlist BLF";s:5:"value";s:1:"3";}}}i:24;a:4:{s:8:"variable";s:12:"$key_name_03";s:13:"default_value";s:12:"Speed Dial 3";s:11:"description";s:8:"Key Name";s:4:"type";s:5:"input";}i:25;a:4:{s:8:"variable";s:14:"$speed_dial_03";s:13:"default_value";s:3:"*03";s:11:"description";s:8:"Value #3";s:4:"type";s:5:"input";}i:26;a:1:{s:4:"type";s:5:"break";}i:27;a:5:{s:8:"variable";s:12:"$key_mode_04";s:13:"default_value";s:1:"0";s:11:"description";s:10:"Key 4 Mode";s:4:"type";s:4:"list";s:4:"data";a:4:{i:0;a:2:{s:4:"text";s:10:"Speed Dial";s:5:"value";s:1:"0";}i:1;a:2:{s:4:"text";s:3:"BLF";s:5:"value";s:1:"1";}i:2;a:2:{s:4:"text";s:16:"Presence Watcher";s:5:"value";s:1:"2";}i:3;a:2:{s:4:"text";s:13:"Eventlist BLF";s:5:"value";s:1:"3";}}}i:28;a:4:{s:8:"variable";s:12:"$key_name_04";s:13:"default_value";s:12:"Speed Dial 4";s:11:"description";s:8:"Key Name";s:4:"type";s:5:"input";}i:29;a:4:{s:8:"variable";s:14:"$speed_dial_04";s:13:"default_value";s:3:"*04";s:11:"description";s:8:"Value #4";s:4:"type";s:5:"input";}i:30;a:1:{s:4:"type";s:5:"break";}i:31;a:5:{s:8:"variable";s:12:"$key_mode_05";s:13:"default_value";s:1:"0";s:11:"description";s:10:"Key 5 Mode";s:4:"type";s:4:"list";s:4:"data";a:4:{i:0;a:2:{s:4:"text";s:10:"Speed Dial";s:5:"value";s:1:"0";}i:1;a:2:{s:4:"text";s:3:"BLF";s:5:"value";s:1:"1";}i:2;a:2:{s:4:"text";s:16:"Presence Watcher";s:5:"value";s:1:"2";}i:3;a:2:{s:4:"text";s:13:"Eventlist BLF";s:5:"value";s:1:"3";}}}i:32;a:4:{s:8:"variable";s:12:"$key_name_05";s:13:"default_value";s:12:"Speed Dial 5";s:11:"description";s:8:"Key Name";s:4:"type";s:5:"input";}i:33;a:4:{s:8:"variable";s:14:"$speed_dial_05";s:13:"default_value";s:3:"*05";s:11:"description";s:8:"Value #5";s:4:"type";s:5:"input";}i:34;a:1:{s:4:"type";s:5:"break";}i:35;a:5:{s:8:"variable";s:12:"$key_mode_06";s:13:"default_value";s:1:"0";s:11:"description";s:10:"Key 6 Mode";s:4:"type";s:4:"list";s:4:"data";a:4:{i:0;a:2:{s:4:"text";s:10:"Speed Dial";s:5:"value";s:1:"0";}i:1;a:2:{s:4:"text";s:3:"BLF";s:5:"value";s:1:"1";}i:2;a:2:{s:4:"text";s:16:"Presence Watcher";s:5:"value";s:1:"2";}i:3;a:2:{s:4:"text";s:13:"Eventlist BLF";s:5:"value";s:1:"3";}}}i:36;a:4:{s:8:"variable";s:12:"$key_name_06";s:13:"default_value";s:12:"Speed Dial 6";s:11:"description";s:8:"Key Name";s:4:"type";s:5:"input";}i:37;a:4:{s:8:"variable";s:14:"$speed_dial_06";s:13:"default_value";s:3:"*06";s:11:"description";s:8:"Value #6";s:4:"type";s:5:"input";}i:38;a:1:{s:4:"type";s:5:"break";}i:39;a:5:{s:8:"variable";s:12:"$key_mode_07";s:13:"default_value";s:1:"0";s:11:"description";s:10:"Key 7 Mode";s:4:"type";s:4:"list";s:4:"data";a:4:{i:0;a:2:{s:4:"text";s:10:"Speed Dial";s:5:"value";s:1:"0";}i:1;a:2:{s:4:"text";s:3:"BLF";s:5:"value";s:1:"1";}i:2;a:2:{s:4:"text";s:16:"Presence Watcher";s:5:"value";s:1:"2";}i:3;a:2:{s:4:"text";s:13:"Eventlist BLF";s:5:"value";s:1:"3";}}}i:40;a:4:{s:8:"variable";s:12:"$key_name_07";s:13:"default_value";s:12:"Speed Dial 7";s:11:"description";s:8:"Key Name";s:4:"type";s:5:"input";}i:41;a:4:{s:8:"variable";s:14:"$speed_dial_07";s:13:"default_value";s:3:"*07";s:11:"description";s:8:"Value #7";s:4:"type";s:5:"input";}}"
            ["product_id"]=>
            string(2) "21"
            ["enabled"]=>
            string(1) "1"
            ["hidden"]=>
            string(1) "0"
          }
        }
     */
    function all_models() {
        $sql="SELECT endpointman_model_list.* FROM endpointman_model_list, endpointman_product_list WHERE endpointman_model_list.product_id = endpointman_product_list.id AND endpointman_model_list.enabled = 1 AND endpointman_product_list.hidden = 0";
        $result1 = sql($sql, 'getAll',DB_FETCHMODE_ASSOC);
        return($result1);
    }

    /**
     *
     * @return <type>
     * @example
     * array(1) {
          [0]=>
          array(2) {
            ["name"]=>
            string(11) "Grandstream"
            ["id"]=>
            string(1) "2"
          }
        }
     */
    function all_active_brands() {
        $sql="SELECT DISTINCT endpointman_brand_list.name, endpointman_brand_list.id FROM  endpointman_brand_list,endpointman_model_list WHERE endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.enabled = 1 AND endpointman_model_list.hidden = 0 AND endpointman_brand_list.installed = 1 AND endpointman_brand_list.hidden = 0";
        $data = sql($sql,'getAll', DB_FETCHMODE_ASSOC);
        return($data);
    }

    function all_models_by_product($product_id) {
        $sql="SELECT * FROM endpointman_model_list WHERE product_id = ".$product_id;
        $result1 = sql($sql, 'getAll',DB_FETCHMODE_ASSOC);
        return($result1);
    }

    function all_models_by_brand($brand_id) {
        $sql="SELECT endpointman_model_list.* FROM endpointman_model_list, endpointman_product_list WHERE endpointman_model_list.product_id = endpointman_product_list.id AND endpointman_model_list.enabled = 1 AND endpointman_product_list.hidden = 0 AND endpointman_model_list.brand = " . $brand_id;
        $result1 = sql($sql, 'getAll',DB_FETCHMODE_ASSOC);
        return($result1);
    }

    function all_unknown_devices() {
        $sql = 'SELECT * FROM  endpointman_mac_list WHERE model = 0';
        $unknown_list = sql($sql,'getAll',DB_FETCHMODE_ASSOC);
        return($unknown_list);
    }

    function all_unused_registrations() {
        if($this->configmod->get('show_all_registrations')) {
            $not_added="SELECT devices.id, devices.description FROM devices WHERE tech in ('sip','pjsip') ORDER BY devices.id";
        } else {
            $not_added="SELECT devices.id, devices.description FROM devices WHERE tech in('sip','pjsip') AND devices.id not in (SELECT devices.id FROM devices, endpointman_line_list WHERE tech in ('sip','pjsip') AND devices.id = endpointman_line_list.ext ) ORDER BY devices.id";
        }
        $result = sql($not_added,'getAll', DB_FETCHMODE_ASSOC);
        return($result);
    }

    function all_used_registrations() {
        $not_added="SELECT devices.id, devices.description FROM devices WHERE tech in ('sip','pjsip') AND devices.id in (SELECT devices.id FROM devices, endpointman_line_list WHERE tech in ('sip','pjsip') AND devices.id = endpointman_line_list.ext ) ORDER BY devices.id";
        $result = sql($not_added,'getAll', DB_FETCHMODE_ASSOC);
        return($result);
    }

    function get_lines_from_device($device_id) {
        $sql = 'SELECT * FROM endpointman_line_list WHERE mac_id = '.$device_id. ' ORDER BY  endpointman_line_list.line ASC';
        $line_list = sql($sql,'getAll',DB_FETCHMODE_ASSOC);
        return($line_list);
    }

    function get_line_information($line_id) {
        $sql = 'SELECT * FROM endpointman_line_list WHERE luid = '.$line_id;
        $line_list = sql($sql,'getRow',DB_FETCHMODE_ASSOC);
        return($line_list);
    }
}
?>