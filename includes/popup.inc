<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
*/
require 'functions.inc';

$endpoint = new endpointmanager();

if($_REQUEST['pop_type'] == 'edit_specifics') {

    if(file_exists(PHONE_MODULES_PATH . 'endpoint/global_template_data.json')) {
        $template_list[0] = 'global_template_data.json';

        $phone_info = $endpoint->get_phone_info($_REQUEST['edit_id']);

        $sql = 'SELECT max_lines FROM endpointman_model_list WHERE id = '. $phone_info['model_id'];

        $lines = $endpoint->eda->sql($sql, 'getOne');
        $merged = $endpoint->merge_data(PHONE_MODULES_PATH . 'endpoint/', $template_list,$lines);

        //remove stuff that is meaningless in this context
        unset($merged['data']['globals']['globals']['option|mac']);
        unset($merged['data']['globals']['globals']['break|0']);
        unset($merged['data']['globals']['globals']['option|provision_protocol']);

        $html_array = $endpoint->generate_gui_html(serialize($merged), NULL, TRUE, NULL, $lines);

        $specific_data = array();
        if (isset($_REQUEST['button_save'])) {
            foreach ($_REQUEST as $key => $data) {
                $key = str_replace('freepbx_', '', $key);
                if (isset($_REQUEST['freepbx_' . $key])) {
                    $specific_data['freepbx'][$key] = TRUE;
                }
                if ($endpoint->arraysearchrecursive($key, $html_array, 'key')) {
                    if(!isset($specific_data['freepbx'][$key])) {
                        $specific_data['data'][$key] = $data;
                    }
                }
            }
            $specific_data['data']['use_dhcp'] = isset($specific_data['data']['use_dhcp']) ? TRUE : FALSE;

            $sql = "UPDATE endpointman_mac_list SET specific_settings = '" . serialize($specific_data) . "' WHERE id =" . $_REQUEST['edit_id'];
            $endpoint->eda->sql($sql);
        } else {
            $sql = 'SELECT specific_settings FROM endpointman_mac_list WHERE id ='. $_REQUEST['edit_id'];
            $specific_data = unserialize($endpoint->eda->sql($sql, 'getOne'));
        }

        foreach($phone_info['line'] as $data) {
                $lid = $data['line'];
                $specific_data['data']['line|'.$lid.'|displayname'] = !isset($specific_data['data']['line|'.$lid.'|displayname']) ? $data['description'] : $specific_data['data']['line|'.$lid.'|displayname'];
                $specific_data['data']['line|'.$lid.'|username'] = !isset($specific_data['data']['line|'.$lid.'|username']) ? $data['ext'] : $specific_data['data']['line|'.$lid.'|username'];
                $specific_data['data']['line|'.$lid.'|authname'] = !isset($specific_data['data']['line|'.$lid.'|authname']) ? $data['ext'] : $specific_data['data']['line|'.$lid.'|authname'];
                $specific_data['data']['line|'.$lid.'|secret'] = !isset($specific_data['data']['line|'.$lid.'|secret']) ? $data['secret'] : $specific_data['data']['line|'.$lid.'|secret'];
                $specific_data['data']['line|'.$lid.'|server_host'] = !isset($specific_data['data']['line|'.$lid.'|server_host']) ? $endpoint->global_cfg['srvip'] : $specific_data['data']['line|'.$lid.'|server_host'];
                $specific_data['data']['line|'.$lid.'|server_port'] = !isset($specific_data['data']['line|'.$lid.'|server_port']) ? 5060 : $specific_data['data']['line|'.$lid.'|server_port'];
                $specific_data['data']['line|'.$lid.'|line_enabled'] = !isset($specific_data['data']['line|'.$lid.'|line_enabled']) ? TRUE : $specific_data['data']['line|'.$lid.'|line_enabled'];
        }

        $specific_data['data']['ntp'] = isset($specific_data['data']['ntp']) ? $specific_data['data']['ntp'] : $endpoint->global_cfg['ntp'];
        $specific_data['data']['use_dhcp'] = isset($specific_data['data']['use_dhcp']) ? TRUE : FALSE;

        $html_array = $endpoint->generate_gui_html(serialize($merged), serialize($specific_data), TRUE, NULL, $lines);
    }

    $endpoint->prepare_message_box();
    $endpoint->tpl->assign("html_array", $html_array);
    echo $endpoint->tpl->draw( 'specifics_pop' );
}

if($_REQUEST['pop_type'] == 'edit_template') {
    if(empty($_REQUEST['edit_id'])) {
        $message = _("No Device Selected to Edit!")."!";
        if(isset($message)) {
            $endpoint->display_message_box($message,$endpoint->tpl,0);
        }
        if(isset($error_message)) {
            $endpoint->display_message_box($error_message,$endpoint->tpl,1);
        }
    } else {
        $template_editor = TRUE;
        $sql = "UPDATE  endpointman_mac_list SET  model =  '".$_REQUEST['model_list']."' WHERE  id =".$_REQUEST['edit_id'];
        $endpoint->eda->sql($sql);
        $endpoint->tpl->assign("silent_mode", 1);

        if ($_REQUEST['template_list'] == 0) {
            $endpoint->edit_template_display($_REQUEST['edit_id'],1);
        } else {
            $endpoint->edit_template_display($_REQUEST['template_list'],0);
        }
    }
}

if($_REQUEST['pop_type'] == 'global_over') {
    if(isset($_REQUEST['button_update_globals'])) {
        $_POST['srvip'] = trim($_POST['srvip']);  #trim whitespace from IP address

        $_POST['config_loc'] = trim($_POST['config_loc']);  #trim whitespace from Config Location

        //No trailing slash. Help the user out and add one :-)
        if($_POST['config_loc'][strlen($_POST['config_loc'])-1] != "/") {
            $_POST['config_loc'] = $_POST['config_loc'] ."/";
        }

        if((isset($_POST['config_loc'])) AND ($_POST['config_loc'] != "")) {
            if((file_exists($_POST['config_loc'])) AND (is_dir($_POST['config_loc']))) {
                if(is_writable($_POST['config_loc'])) {
                    $settings['config_location'] = $_POST['config_loc'];
                } else {
                    $endpoint->error['config_dir'] = "Directory Not Writable!";
                    $settings['config_location'] = $endpoint->global_cfg['config_location'];
                }
            } else {
                $endpoint->error['config_dir'] = "Not a Vaild Directory";
                $settings['config_location'] = $endpoint->global_cfg['config_location'];
            }
        } else {
            $endpoint->error['config_dir'] = "No Configuration Location Defined!";
            $settings['config_location'] = $endpoint->global_cfg['config_location'];
        }

        $settings['srvip'] = $_POST['srvip'];
        $settings['ntp'] = $_POST['ntp_server'];
        $settings['tz'] = $_POST['tz'];

        $settings_ser = serialize($settings);
        if($_REQUEST['custom'] == 0) {
            //This is a group template
            $sql = "UPDATE endpointman_template_list SET global_settings_override = '".addslashes($settings_ser)."' WHERE id = ".$_REQUEST['tid'];
            $endpoint->eda->sql($sql);
        } else {
            //This is an individual template
            $sql = "UPDATE endpointman_mac_list SET global_settings_override = '".addslashes($settings_ser)."' WHERE id = ".$_REQUEST['tid'];
            $endpoint->eda->sql($sql);
        }

        $endpoint->message['advanced_settings'] = "Updated!";
    }
    if(isset($_REQUEST['button_reset_globals'])) {
        if($_REQUEST['custom'] == 0) {
            //This is a group template
            $sql = "UPDATE endpointman_template_list SET global_settings_override = NULL WHERE id = ".$_REQUEST['tid'];
            $endpoint->eda->sql($sql);
        } else {
            //This is an individual template
            $sql = "UPDATE endpointman_mac_list SET global_settings_override = NULL WHERE id = ".$_REQUEST['tid'];
            $endpoint->eda->sql($sql);
        }
        $endpoint->message['advanced_settings'] = "Globals Reset to Default!";
    }
    if($_REQUEST['custom'] == 0) {
        //This is a group template
        $sql = 'SELECT global_settings_override FROM endpointman_template_list WHERE id = '.$_REQUEST['tid'];
        $settings = $endpoint->eda->sql($sql,'getOne');
    } else {
        //This is an individual template
        $sql = 'SELECT global_settings_override FROM endpointman_mac_list WHERE id = '.$_REQUEST['tid'];
        $settings = $endpoint->eda->sql($sql,'getOne');
    }
    if(isset($settings)) {
        $settings = unserialize($settings);
        $settings['tz'] = $endpoint->listTZ($settings['tz']);
    } else {
        $settings['srvip'] = $endpoint->global_cfg['srvip'];
        $settings['ntp'] = $endpoint->global_cfg['ntp'];
        $settings['config_location'] = $endpoint->global_cfg['config_location'];
        $settings['tz'] = $endpoint->listTZ($endpoint->global_cfg['tz']);
    }
    //Because we are working with global variables we probably updated them, so lets refresh those variables
    $endpoint->global_cfg = $endpoint->eda->sql("SELECT var_name, value FROM endpointman_global_vars",'getAssoc');

    $endpoint->tpl->assign("ip", $_SERVER["SERVER_ADDR"]);
    $endpoint->tpl->assign("srvip", $settings['srvip']);
    $endpoint->tpl->assign("ntp_server", $settings['ntp']);

    $endpoint->tpl->assign("config_location", $settings['config_location']);
    $endpoint->tpl->assign("list_tz", $settings['tz']);

    $endpoint->prepare_message_box();

    echo $endpoint->tpl->draw( 'advanced_settings_settings_pop' );

}

if($_REQUEST['pop_type'] == "alt_cfg_edit") {
    $value = isset($_POST['value']) ? $_POST['value'] : $_REQUEST['value'];
    preg_match('/^(\d*)\_(.*)/i', $value, $matches);

    $cfg_id = isset($matches[1]) ? $matches[1] : null;
    $cfg_filename = isset($matches[2]) ? $matches[2] : null;

    if($_REQUEST['custom'] == 0) {
        if($cfg_id != 0) {
            //SQL Config Files
            if(isset($_REQUEST['button_save'])) {
                $sql = "UPDATE endpointman_custom_configs SET data = '".addslashes($_REQUEST['config_text'])."' WHERE id = ".$cfg_id;
                $endpoint->eda->sql($sql);
                $endpoint->message['alt_config'] = "Saved to Database!";
            }
            $sql = 'SELECT * FROM endpointman_custom_configs WHERE id =' . $cfg_id;
            $row = $endpoint->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
            $endpoint->tpl->assign("save_as_name_value", $row['name']);
            $endpoint->tpl->assign("filename", $row['original_name']);
            $row['data'] = $endpoint->display_htmlspecialchars($row['data']);
            $endpoint->tpl->assign("config_data", $row['data']);
            $endpoint->tpl->assign("allow_hdfiles",$endpoint->global_cfg['allow_hdfiles']);
            $endpoint->tpl->assign("value", $value);

        } else {
            //HD Config Files
            $sql = "SELECT endpointman_brand_list.directory, endpointman_product_list.cfg_dir FROM endpointman_brand_list, endpointman_product_list WHERE endpointman_brand_list.id = endpointman_product_list.brand AND endpointman_product_list.id = (SELECT product_id FROM endpointman_template_list WHERE id = ".$_REQUEST['tid'].")";
            $row = $endpoint->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
            $file=PHONE_MODULES_PATH.'endpoint/'.$row['directory']."/".$row['cfg_dir']."/".$cfg_filename;

            if((isset($_REQUEST['button_save'])) && ($endpoint->global_cfg['allow_hdfiles'])) {
                file_put_contents($file, $_REQUEST['config_text']);
                $endpoint->message['alt_config'] = "Saved to Hard Drive!";
                $contents = file_get_contents($file);
            } elseif((isset($_REQUEST['button_save'])) && (!$endpoint->global_cfg['allow_hdfiles'])) {
                $time = time();
                $sql = 'SELECT endpointman_template_list.name, endpointman_template_list.config_files_override, endpointman_template_list.product_id FROM endpointman_template_list WHERE endpointman_template_list.id = '.$_REQUEST['tid'];
                $row = $endpoint->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
                $config_fs = unserialize($row['config_files_override']);

                $sql = 'INSERT INTO endpointman_custom_configs (name, original_name, product_id, data) VALUES ("'.$row['name'].'_'.$time.'","'.addslashes($cfg_filename).'","'.$row['product_id'].'","'.addslashes($_REQUEST['config_text']).'")';
                $endpoint->eda->sql($sql);
                $endpoint->message['alt_config'] = "Saved to Database!";
                $new_id = $endpoint->eda->sql('SELECT last_insert_id()','getOne');
                $sql = 'SELECT * FROM endpointman_custom_configs WHERE id =' . $new_id;
                $row = $endpoint->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

                $contents = $row['data'];

                $value = $new_id."_".$row['mac'].'_'.$time;

                $config_fs = is_array($config_fs) ? $config_fs : array();

                $file = $row['original_name'];
                $row['original_name'] = str_replace(".","_",$row['original_name']);
                $config_fs[$row['original_name']] = $new_id;
                $config_files = serialize($config_fs);
                $sql = "UPDATE endpointman_template_list SET config_files_override = '".$config_files."' WHERE id = ".$_REQUEST['tid'];
                $endpoint->eda->sql($sql);
            } else {
                $contents = file_get_contents($file);
            }

            $contents = $endpoint->display_htmlspecialchars($contents);
            $endpoint->tpl->assign("config_data", $contents);
            $endpoint->tpl->assign("location", $file);
            $endpoint->tpl->assign("file", basename($file));
            $endpoint->tpl->assign("allow_hd", $endpoint->global_cfg['allow_hdfiles']);
            $endpoint->tpl->assign("value", $value);

        }
    } else {
        if($cfg_id != 0) {
            if(isset($_REQUEST['button_save'])) {
                $sql = "UPDATE endpointman_custom_configs SET data = '".addslashes($_REQUEST['config_text'])."' WHERE id = ".$cfg_id;
                $endpoint->eda->sql($sql);
                $endpoint->message['alt_config'] = "Saved to Database!";
            }
            $sql = 'SELECT * FROM endpointman_custom_configs WHERE id =' . $cfg_id;
            $row = $endpoint->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
            $file = "SQL/".$row['original_name'];
            $endpoint->tpl->assign("file", basename($file));
            $endpoint->tpl->assign("save_as_name_value", $row['name']);
            $endpoint->tpl->assign("filename", $row['original_name']);
            $row['data'] = $endpoint->display_htmlspecialchars($row['data']);
            $endpoint->tpl->assign("config_data", $row['data']);
            $endpoint->tpl->assign("allow_hdfiles",$endpoint->global_cfg['allow_hdfiles']);
            $endpoint->tpl->assign("value", $value);
        } else {
            $sql = "SELECT endpointman_brand_list.directory, endpointman_product_list.cfg_dir FROM endpointman_brand_list, endpointman_product_list WHERE endpointman_brand_list.id = endpointman_product_list.brand AND endpointman_product_list.id = (SELECT endpointman_model_list.product_id FROM endpointman_model_list, endpointman_mac_list WHERE endpointman_mac_list.model = endpointman_model_list.id AND endpointman_mac_list.id = ".$_REQUEST['tid'].")";
            $row = $endpoint->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
            $file=PHONE_MODULES_PATH.'endpoint/'.$row['directory']."/".$row['cfg_dir']."/".$cfg_filename;

            if((isset($_REQUEST['button_save'])) && ($endpoint->global_cfg['allow_hdfiles'])) {
                file_put_contents($file, $_REQUEST['config_text']);
                $endpoint->message['alt_config'] = "Saved to Hard Drive!";
                $contents = file_get_contents($file);
            } elseif((isset($_REQUEST['button_save'])) && (!$endpoint->global_cfg['allow_hdfiles'])) {
                $time = time();
                $sql = 'SELECT endpointman_mac_list.mac, endpointman_mac_list.config_files_override, endpointman_model_list.product_id FROM endpointman_mac_list, endpointman_model_list WHERE endpointman_mac_list.model = endpointman_model_list.id AND endpointman_mac_list.id = '.$_REQUEST['tid'];
                $row = $endpoint->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
                $config_fs = unserialize($row['config_files_override']);

                $sql = 'INSERT INTO endpointman_custom_configs (name, original_name, product_id, data) VALUES ("'.$row['mac'].'_'.$time.'","'.addslashes($cfg_filename).'","'.$row['product_id'].'","'.addslashes($_REQUEST['config_text']).'")';
                $endpoint->eda->sql($sql);
                $endpoint->message['alt_config'] = "Saved to Database!";
                $new_id = $endpoint->eda->sql('SELECT last_insert_id()','getOne');
                $sql = 'SELECT * FROM endpointman_custom_configs WHERE id =' . $new_id;
                $row = $endpoint->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

                $contents = $row['data'];

                $value = $new_id."_".$row['mac'].'_'.$time;

                $config_fs = is_array($config_fs) ? $config_fs : array();

                $file = $row['original_name'];
                $row['original_name'] = str_replace(".","_",$row['original_name']);
                $config_fs[$row['original_name']] = $new_id;
                $config_files = serialize($config_fs);
                $sql = "UPDATE endpointman_mac_list SET config_files_override = '".$config_files."' WHERE id = ".$_REQUEST['tid'];
                $endpoint->eda->sql($sql);
            } else {
                $contents = file_get_contents($file);
            }

            $contents = $endpoint->display_htmlspecialchars($contents);
            $endpoint->tpl->assign("config_data", $contents);
            $endpoint->tpl->assign("location", $file);
            $endpoint->tpl->assign("file", basename($file));
            $endpoint->tpl->assign("allow_hd", $endpoint->global_cfg['allow_hdfiles']);
            $endpoint->tpl->assign("value", $value);


        }
    }

    if(isset($_REQUEST['cfg_file'])) {
        $sql = "SELECT cfg_dir,directory,config_files FROM endpointman_product_list,endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id = '". $_REQUEST['product_select'] ."'";
        $row = $endpoint->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

        $config_files = explode(",",$row['config_files']);
        $file=PHONE_MODULES_PATH.'endpoint/'.$row['directory']."/".$row['cfg_dir']."/".$config_files[$_REQUEST['cfg_file']];
        if(isset($_REQUEST['config_text'])) {
            if(isset($_REQUEST['button_save'])) {
                file_put_contents($file, $_REQUEST['config_text']);
                $endpoint->message['alt_config'] = "Saved to Hard Drive!";
            }elseif(isset($_REQUEST['button_save_as'])) {
                $sql = 'INSERT INTO endpointman_custom_configs (name, original_name, product_id, data) VALUES ("'.addslashes($_REQUEST['save_as_name']).'","'.addslashes($config_files[$_REQUEST['cfg_file']]).'","'.$_REQUEST['product_select'].'","'.addslashes($_REQUEST['config_text']).'")';
                $endpoint->eda->sql($sql);
                $endpoint->message['alt_config'] = "Saved to Database!";
            }
        }

        $contents = file_get_contents($file);

        if(isset($_REQUEST['sendid'])) {
            if($endpoint->submit_config($row['directory'],$row['cfg_dir'],$config_files[$_REQUEST['cfg_file']],$contents)) {
                $endpoint->message['alt_config'] = 'Sent! Thanks :-)';
            } else {
                $endpoint->message['alt_config'] = 'Could not send at this time';
            }
        }
        $endpoint->tpl->assign("save_as_name_value", $config_files[$_REQUEST['cfg_file']]);
        $endpoint->tpl->assign("config_data", $contents);
        $endpoint->tpl->assign("filename", $config_files[$_REQUEST['cfg_file']]);
        $endpoint->tpl->assign('sendid', $_REQUEST['cfg_file']);
        $endpoint->tpl->assign("type", 'file');
        $endpoint->tpl->assign("location", $file);


    } elseif(isset($_REQUEST['sql'])) {
        if(isset($_REQUEST['config_text'])) {
            if(isset($_REQUEST['button_save'])) {
                $sql = "UPDATE endpointman_custom_configs SET data = '".addslashes($_REQUEST['config_text'])."' WHERE id = ".$_REQUEST['sql'];
                $endpoint->eda->sql($sql);
                $endpoint->message['alt_config'] = "Saved to Database!";
            }elseif(isset($_REQUEST['button_save_as'])) {
                $sql = 'SELECT original_name FROM endpointman_custom_configs WHERE id = '.$_REQUEST['sql'];
                $file_name = $endpoint->eda->sql($sql,'getOne');

                $sql = "INSERT INTO endpointman_custom_configs (name, original_name, product_id, data) VALUES ('".addslashes($_REQUEST['save_as_name'])."','".addslashes($file_name)."','".$_REQUEST['product_select']."','".addslashes($_REQUEST['config_text'])."')";
                $endpoint->eda->sql($sql);
                $endpoint->message['alt_config'] = "Saved to Database!";
            }
        }
        if(isset($_REQUEST['sendid'])) {
            $sql = "SELECT cfg_dir,directory,config_files FROM endpointman_product_list,endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id = '". $_REQUEST['product_select'] ."'";
            $row22 = $endpoint->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
            if($endpoint->submit_config($row22['directory'],$row22['cfg_dir'],$config_files[$_REQUEST['cfg_file']],$contents)) {
                $endpoint->message['alt_config'] = 'Sent! Thanks! :-)';
            } else {
                $endpoint->message['alt_config'] = 'Could not send at this time';
            }

        }
        $sql = 'SELECT * FROM endpointman_custom_configs WHERE id =' . $_REQUEST['sql'];
        $row = $endpoint->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
        $endpoint->tpl->assign("save_as_name_value", $row['name']);
        $endpoint->tpl->assign("filename", $row['original_name']);
        $endpoint->tpl->assign('sendid', $_REQUEST['sql']);
        $endpoint->tpl->assign("type", 'sql');
        $endpoint->tpl->assign("config_data", $row['data']);
    }
    if(isset($_REQUEST['product_select'])) {
        $sql = "SELECT cfg_dir,directory,config_files FROM endpointman_product_list,endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id ='" . $_REQUEST['product_select'] . "'";

        $row = $endpoint->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
        $config_files = explode(",",$row['config_files']);
        $i = 0;
        foreach($config_files as $config_files_data) {
            $file_list[$i]['value'] = $i;
            $file_list[$i]['text'] = $config_files_data;
            $i++;
        }
        $sql = "SELECT * FROM endpointman_custom_configs WHERE product_id = '". $_REQUEST['product_select'] . "'";
        $res = $endpoint->eda->sql($sql);
        $i = 0;
        if(count(array($res))) {
            $data = $endpoint->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
            foreach($data as $row2) {
                $sql_file_list[$i]['value'] = $row2['id'];
                $sql_file_list[$i]['text'] = $row2['name'];
                $sql_file_list[$i]['ref'] = $row2['original_name'];
                $i++;
            }
        } else {
            $sql_file_list = NULL;
        }

        require(PHONE_MODULES_PATH.'setup.php');

        $class = "endpoint_" . $row['directory'] . "_" . $row['cfg_dir'] . '_phone';

        $phone_config = new $class();

        if((method_exists($phone_config,'display_options')) AND (method_exists($phone_config,'process_options'))) {
            if(isset($_REQUEST['phone_options'])) {
                $endpoint->tpl->assign("options", $phone_config->process_options());
            } else {
                $endpoint->tpl->assign("options", $phone_config->display_options());
            }
        }

        $template_file_list[0]['value'] = "template_data_custom.xml";
        $template_file_list[0]['text'] = "template_data_custom.xml";

        $sql = "SELECT model FROM `endpointman_model_list` WHERE `product_id` LIKE '1-2' AND `enabled` = 1 AND `hidden` = 0";
        $data = $endpoint->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
        $i = 1;
        foreach($data as $list) {
            $template_file_list[$i]['value'] = "template_data_" . $list['model'] . "_custom.xml";
            $template_file_list[$i]['text'] = "template_data_" . $list['model'] . "_custom.xml";
        }

        $endpoint->tpl->assign("template_file_list",$template_file_list);
        if(isset($_REQUEST['temp_file'])) {
            $endpoint->tpl->assign("temp_file",1);
        } else {
            $endpoint->tpl->assign("temp_file",NULL);
        }

        $endpoint->tpl->assign("file_list", $file_list);
        $endpoint->tpl->assign("sql_file_list", $sql_file_list);
        $endpoint->tpl->assign("product_selected", $_REQUEST['product_select']);
    }

    $endpoint->prepare_message_box();

    echo $endpoint->tpl->draw( 'alt_config_popup' );
}
