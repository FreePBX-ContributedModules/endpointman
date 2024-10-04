<?php

/**
 * Endpoint Manager Functions File
 *
 * @author Andrew Nagy
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */
class endpointmanager {

    //Load this class upon construction of the class

    public $db; //Database from FreePBX
    public $amp_conf; //amp information from FreePBX
    public $global_cfg; //global configuration options for Endpoint Manager
    public $error; //error construct
    public $message; //message construct
    public $eda; //endpoint data abstraction layer
    public $tpl; //Template System Object (RAIN TPL)

    /**
     *
     * @global array $amp_conf Data taken about FreePBX from FreePBX as a global, we move this into a public variable
     * @global object $db Pear DB information, we move this into a public variable
     */

    function __construct() {
        global $amp_conf;
        require_once('abstraction/freepbx.inc');
        $this->eda = new epm_data_abstraction();

        require_once('system_functions.class');
        $this->system = new epm_system();

        require_once('json.inc');

        $this->global_cfg = $this->eda->get_stored_globals();

        $this->global_cfg['disable_epm'] = FALSE;

        $this->global_cfg['tz'] = !empty($this->global_cfg['tz']) ? $this->global_cfg['tz'] : $amp_conf['PHPTIMEZONE'];
        //date_default_timezone_set($this->global_cfg['tz']);

        //Generate empty array
        $this->error = array();
        $this->message = array();

        //Move all information from the global $amp_conf into our public space. This will help prevent security concerns
        if (isset($amp_conf) AND is_array($amp_conf)) {
            $this->amp_conf = $amp_conf;
        } else {
            $this->error['amp_conf'] = 'The Global Variable $amp_conf is empty or not set! Can\'t connect to the database.';
            $this->global_cfg['disable_epm'] = TRUE;
        }

				if(!defined("UPDATE_PATH")) {
					define("UPDATE_PATH", $this->global_cfg['update_server']);
				}
				if(!defined("MODULES_PATH")) {
					define("MODULES_PATH", dirname(dirname(dirname(__FILE__))) . '/');
				}


        //Determine if local path is correct!
        if (file_exists(MODULES_PATH . "endpointman/")) {
					if(!defined("LOCAL_PATH")) {
						define("LOCAL_PATH", MODULES_PATH . "endpointman/");
					}
        } else {
            die("Can't Load Local Endpoint Manager Directory!");
        }

        //Define the location of phone modules, keeping it outside of the module directory so that when the user updates endpointmanager they don't lose all of their phones
        if (file_exists(MODULES_PATH . "_ep_phone_modules/")) {
					if(!defined("PHONE_MODULES_PATH")) {
						define("PHONE_MODULES_PATH", MODULES_PATH . "_ep_phone_modules/");
					}
        } else {
					if(!defined("PHONE_MODULES_PATH")) {
						define("PHONE_MODULES_PATH", MODULES_PATH . "_ep_phone_modules/");
					}
            if (!file_exists(PHONE_MODULES_PATH)) {
                mkdir(PHONE_MODULES_PATH, 0775);
            }

            if (file_exists(PHONE_MODULES_PATH . "setup.php")) {
                unlink(PHONE_MODULES_PATH . "setup.php");
            }

            if (!file_exists(MODULES_PATH . "_ep_phone_modules/")) {
                die('Endpoint Manager can not create the modules folder!');
            }
        }

        //include the local template class
        if (!class_exists('RainTPL')) {
            if (file_exists(LOCAL_PATH . "includes/rain.tpl.class.inc")) {
                require(LOCAL_PATH . "includes/rain.tpl.class.inc");
            } else {
                die("Can't Load the Template Class");
            }
        }

        //Check if config location is writable and/or exists!
        if (isset($this->global_cfg['config_location'])) {
            if (is_dir($this->global_cfg['config_location'])) {
                if (!is_writeable($this->global_cfg['config_location'])) {
                    $user = exec('whoami');
                    $group = exec("groups");
                    $this->error['config_location'] = "Configuration Directory is not writable!" .
                            "<br />Please change the location: <a href='config.php?type=tool&display=epm_advanced'>Here</a>" .
                            "<br />Or run this command on SSH: 'chown -hR root:" . $group . " " . $this->global_cfg['config_location'] . "' then 'chmod g+w " . $this->global_cfg['config_location'] . "'";
                    $this->global_cfg['diable_epm'] = TRUE;
                }
            } else {
                $this->error['config_location'] = "Configuration Directory is not a directory or does not exist! Please change the location here: <a href='config.php?type=tool&display=epm_advanced'>Here</a>";
                $this->global_cfg['diable_epm'] = TRUE;
            }
        }

        $this->tpl = new RainTPL(LOCAL_PATH . 'templates/freepbx', LOCAL_PATH . 'templates/freepbx/compiled', '/admin/assets/endpointman/images');

        if ($this->global_cfg['disable_help']) {
            $this->tpl->assign("disable_help", 1);
        }
    }

    function tftp_check() {
        //create a simple block here incase people have strange issues going on as we will kill http
        //by running this if the server isn't really running!
        $sql = 'SELECT value FROM endpointman_global_vars WHERE var_name = \'tftp_check\'';
        if ($this->eda->sql($sql, 'getOne') != 1) {
            $sql = 'UPDATE endpointman_global_vars SET value = \'1\' WHERE var_name = \'tftp_check\'';
            $this->eda->sql($sql);
            $subject = shell_exec("netstat -luan --numeric-ports");
            if (preg_match('/:69\s/i', $subject)) {
                $rand = md5(rand(10, 2000));
                if (file_put_contents($this->global_cfg['config_location'] . 'TEST', $rand)) {
                    if ($this->system->tftp_fetch('127.0.0.1', 'TEST') != $rand) {
                        $this->error['tftp_check'] = 'Local TFTP Server is not correctly configured';
                    }
                    unlink($this->global_cfg['config_location'] . 'TEST');
                } else {
                    $this->error['tftp_check'] = 'Unable to write to ' . $this->global_cfg['config_location'];
                }
            } else {
                $dis = FALSE;
                if (file_exists('/etc/xinetd.d/tftp')) {
                    $contents = file_get_contents('/etc/xinetd.d/tftp');
                    if (preg_match('/disable.*=.*yes/i', $contents)) {
                        $this->error['tftp_check'] = 'Disabled is set to "yes" in /etc/xinetd.d/tftp. Please fix <br />Then restart your TFTP service';
                        $dis = TRUE;
                    }
                }
                if (!$dis) {
                    $this->error['tftp_check'] = 'TFTP Server is not running. <br />' .
                            'See here for instructions on how to install one: <a href="http://wiki.provisioner.net/index.php/Tftp" target="_blank">http://wiki.provisioner.net/index.php/Tftp</a>';
                }
            }
            $sql = 'UPDATE endpointman_global_vars SET value = \'0\' WHERE var_name = \'tftp_check\'';
            $this->eda->sql($sql);
        } else {
            $this->error['tftp_check'] = 'TFTP Server check failed on last past. Skipping';
        }
    }

    /**
     * Fixes the display are special strings so we can visible see them instead of them being transformed
     * @param string $contents a string of course
     * @return string fixed string
     */
    function display_htmlspecialchars($contents) {
        $contents = str_replace("&amp;", "&amp;amp;", $contents);
        $contents = str_replace("&lt;", "&amp;lt;", $contents);
        $contents = str_replace("&gt;", "&amp;gt;", $contents);
        $contents = str_replace("&quot;", "&amp;quot;", $contents);
        $contents = str_replace("&#039;", "&amp;#039;", $contents);
        return($contents);
    }

    /**
     * Used to send sample configurations to provisioner.net
     * NOTE: The user has to explicitly click a link that states they are sending the configuration to the project
     * We don't take configs on our own accord!!
     * @param <type> $brand Brand Directory
     * @param <type> $product Product Directory
     * @param <type> $orig_name The file's original name we are sending
     * @param <type> $data The config file's data
     */
    function submit_config($brand, $product, $orig_name, $data) {
        $posturl = 'http://www.provisioner.net/submit_config.php';

        $fp = fopen(LOCAL_PATH . 'data.txt', 'w');
        fwrite($fp, $data);
        fclose($fp);
        $file_name_with_full_path = LOCAL_PATH . "data.txt";

        $postvars = array('brand' => $brand, 'product' => $product, 'origname' => htmlentities(addslashes($orig_name)), 'file_contents' => '@' . $file_name_with_full_path);

        $ch = curl_init($posturl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postvars);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);  // DO NOT RETURN HTTP HEADERS
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  // RETURN THE CONTENTS OF THE CALL, probably not needed
        $Rec_Data = curl_exec($ch);

        ob_start();
        header("Content-Type: text/html");
        $Final_Out = ob_get_clean();
        curl_close($ch);
        unlink($file_name_with_full_path);

        return($Final_Out);
    }

    function prepare_message_box() {
        $error_message = NULL;
        foreach ($this->error as $key => $error) {
            $error_message .= $error;
            if ($this->global_cfg['debug']) {
                $error_message .= " Function: [" . $key . "]";
            }
            $error_message .= "<br />";
        }
        $message = NULL;
        foreach ($this->message as $key => $error) {
            if (is_array($error)) {
                foreach ($error as $sub_error) {
                    $message .= $sub_error;
                    if ($this->global_cfg['debug']) {
                        $message .= " Function: [" . $key . "]";
                    }
                    $message .= "<br />";
                }
            } else {
                $message .= $error;
                if ($this->global_cfg['debug']) {
                    $message .= " Function: [" . $key . "]";
                }
                $message .= "<br />";
            }
        }

        if (isset($message)) {
            $this->display_message_box($message, 0);
        }

        if (isset($error_message)) {
            $this->display_message_box($error_message, 1);
        }
    }

    /**
     * Displays the message box on any page if triggered.
     * @param string $message The text of the message
     * @param string $tpl Not used at this time
     * @param int $fatal_error Either 0 or 1, 1 would be red, 0 would be black
     */
    function display_message_box($message, $fatal_error=0) {
        $this->tpl->assign("show_error_box", 1);
        $this->tpl->assign("advanced_debug", $this->global_cfg['debug']);
        $this->tpl->assign("fatal_error", $fatal_error);
        $this->tpl->assign("error_message", $message);
    }

    /**
     * Taken from PHP.net. A list of errors returned when uploading files.
     * @param <type> $error_code
     * @return string
     */
    function file_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    /**
     * Get the brand from any mac sent to this function
     * @param string $mac
     * @return array
     */
    function get_brand_from_mac($mac) {
        //Check for valid mac address first
        if (!$this->mac_check_clean($mac)) {
            return(FALSE);
        }

        //Get the OUI only
        $oui = substr($this->mac_check_clean($mac), 0, 6);
        //Find the matching brand model to the oui
        $oui_sql = "SELECT endpointman_brand_list.name, endpointman_brand_list.id FROM endpointman_oui_list, endpointman_brand_list WHERE oui LIKE '%" . $oui . "%' AND endpointman_brand_list.id = endpointman_oui_list.brand AND endpointman_brand_list.installed = 1 LIMIT 1";
        $brand = $this->eda->sql($oui_sql, 'getRow', DB_FETCHMODE_ASSOC);

        $res = $this->eda->sql($oui_sql);
        $brand_count = count($res);

        if (!$brand_count) {
            //oui doesn't have a matching mysql reference, probably a PC/router/wap/printer of some sort.
            $phone_info['id'] = 0;
            $phone_info['name'] = _("Unknown");
        } else {
            $phone_info['id'] = $brand['id'];
            $phone_info['name'] = $brand['name'];
        }

        return($phone_info);
    }

    function add_device($mac, $model, $ext, $template=NULL, $line=NULL, $displayname=NULL) {
		$ipei = $_REQUEST['ipei'];
        $mac = $this->mac_check_clean($mac);
        if ($mac) {
            if (empty($model)) {
                $this->error['add_device'] = _("You Must Select A Model From the Drop Down") . "!";
                return(FALSE);
            } elseif (empty($ext)) {
                $this->error['add_device'] = _("You Must Select an Extension/Device From the Drop Down") . "!";
                return(FALSE);
            } else {
                if ($this->sync_model($model)) {
                    $sql = "SELECT id,template_id FROM endpointman_mac_list WHERE mac = '" . $mac . "'";
                    $dup = $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

                    if ($dup) {
                        if (!isset($template)) {
                            $template = $dup['template_id'];
                        }

                        $sql = "UPDATE endpointman_mac_list SET model = " . $model . ", template_id =  " . $template . " WHERE id = " . $dup['id'];
                        $this->eda->sql($sql);
                        $return = $this->add_line($dup['id'], $line, $ext);
                        if ($return) {
                            return($return);
                        } else {
                            return(FALSE);
                        }
                    } else {
                        if (!isset($template)) {
                            $template = 0;
                        }

                        $sql = "SELECT mac_id FROM endpointman_line_list WHERE ext = " . $ext;
                        $used = $this->eda->sql($sql, 'getOne');

                        if (($used) AND (!$this->global_cfg['show_all_registrations'])) {
                            $this->error['add_device'] = "You can't assign the same user to multiple devices!";
                            return(FALSE);
                        }

                        if (!isset($displayname)) {
                            $sql = 'SELECT description FROM devices WHERE id = ' . $ext;
                            $name = & $this->eda->sql($sql, 'getOne');
                        } else {
                            $name = $displayname;
                        }

                        $sql = 'SELECT endpointman_product_list. * , endpointman_model_list.template_data, endpointman_brand_list.directory FROM endpointman_model_list, endpointman_brand_list, endpointman_product_list WHERE endpointman_model_list.id =  \'' . $model . '\' AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id';

                        $row = & $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

                        $sql = "INSERT INTO `endpointman_mac_list` (`mac`, `model`, `template_id`) VALUES ('" . $mac . "', '" . $model . "', '" . $template . "')";
                        $this->eda->sql($sql);

                        $sql = 'SELECT last_insert_id()';
                        $ext_id = & $this->eda->sql($sql, 'getOne');

                        if (empty($line)) {
                            $line = 1;
                        }

                        $sql = "INSERT INTO `endpointman_line_list` (`mac_id`, `ipei`, `ext`, `line`, `description`) VALUES ('" . $ext_id . "', '" . $ipei . "', '" . $ext . "', '" . $line . "', '" . addslashes($name) . "')";
                        $this->eda->sql($sql);

                        $this->message['add_device'][] = "Added " . $name . " to line " . $line;
                        return($ext_id);
                    }
                } else {
                    $this->error['Sync_Model'] = _("Invalid Model Selected, Can't Sync System") . "!";
                    return(FALSE);
                }
            }
        } else {
            $this->error['add_device'] = _("Invalid MAC Address") . "!";
            return(FALSE);
        }
    }

    function add_line($mac_id, $line=NULL, $ext=NULL, $displayname=NULL) {
        if ((!isset($line)) AND (!isset($ext))) {
            if ($this->linesAvailable(NULL, $mac_id)) {
                if ($this->eda->all_unused_registrations()) {
                    $sql = 'SELECT * FROM endpointman_line_list WHERE mac_id = ' . $mac_id;
                    $lines_list = $this->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);

                    foreach ($lines_list as $row) {
                        $sql = "SELECT description FROM devices WHERE id = " . $row['ext'];
                        $name = $this->eda->sql($sql, 'getOne');

                        $sql = "UPDATE endpointman_line_list SET line = '" . $row['line'] . "', ext = '" . $row['ext'] . "', description = '" . $this->eda->escapeSimple($name) . "' WHERE luid =  " . $row['luid'];
                        $this->eda->sql($sql);
                    }

                    $reg = array_values($this->display_registration_list());
                    $lines = array_values($this->linesAvailable(NULL, $mac_id));

                    $sql = "SELECT description FROM devices WHERE id = " . $reg[0]['value'];
                    $name = $this->eda->sql($sql, 'getOne');

                    $sql = "INSERT INTO `endpointman_line_list` (`mac_id`, `ext`, `line`, `description`) VALUES ('" . $mac_id . "', '" . $reg[0]['value'] . "', '" . $lines[0]['value'] . "', '" . addslashes($name) . "')";
                    $this->eda->sql($sql);

                    $this->message['add_line'] = "Added '<i>" . $name . "</i>' to line '<i>" . $lines[0]['value'] . "</i>' on device '<i>" . $reg[0]['value'] . "</i>' <br/> Configuration Files will not be Generated until you click Save!";
                    return($mac_id);
                } else {
                    $this->error['add_line'] = _("No Devices/Extensions Left to Add") . "!";
                    return(FALSE);
                }
            } else {
                $this->error['add_line'] = _("No Lines Left to Add") . "!";
                return(FALSE);
            }
        } elseif ((!isset($line)) AND (isset($ext))) {
            if ($this->linesAvailable(NULL, $mac_id)) {
                if ($this->eda->all_unused_registrations()) {
                    $lines = array_values($this->linesAvailable(NULL, $mac_id));

                    $sql = "INSERT INTO `endpointman_line_list` (`mac_id`, `ext`, `line`, `description`) VALUES ('" . $mac_id . "', '" . $ext . "', '" . $lines[0]['value'] . "', '" . addslashes($displayname) . "')";
                    $this->eda->sql($sql);

                    $this->message['add_line'] = "Added '<i>" . $name . "</i>' to line '<i>" . $lines[0]['value'] . "</i>' on device '<i>" . $reg[0]['value'] . "</i>' <br/> Configuration Files will not be Generated until you click Save!";
                    return($mac_id);
                } else {
                    $this->error['add_line'] = _("No Devices/Extensions Left to Add") . "!";
                    return(FALSE);
                }
            } else {
                $this->error['add_line'] = _("No Lines Left to Add") . "!";
                return(FALSE);
            }
        } elseif ((isset($line)) AND (isset($ext))) {
            $sql = "SELECT luid FROM endpointman_line_list WHERE line = '" . $line . "' AND mac_id = " . $mac_id;
            $luid = $this->eda->sql($sql, 'getOne');
            if ($luid) {
                $this->error['add_line'] = "This line has already been assigned!";
                return(FALSE);
            } else {
                if (!isset($displayname)) {
                    $sql = 'SELECT description FROM devices WHERE id = ' . $ext;
                    $name = & $this->eda->sql($sql, 'getOne');
                } else {
                    $name = $displayname;
                }

                $sql = "INSERT INTO `endpointman_line_list` (`mac_id`, `ext`, `line`, `description`) VALUES ('" . $mac_id . "', '" . $ext . "', '" . $line . "', '" . addslashes($name) . "')";
                $this->eda->sql($sql);
                $this->message['add_line'] .= "Added " . $name . " to line " . $line . "<br/>";
                return($mac_id);
            }
        }
    }

    function update_device($macid, $model, $template, $luid=NULL, $name=NULL, $line=NULL, $update_lines=TRUE) {
        $sql = "UPDATE endpointman_mac_list SET model = " . $model . ", template_id =  " . $template . " WHERE id = " . $macid;
        $this->eda->sql($sql);

        if ($update_lines) {
            if (isset($luid)) {
                $this->update_line($luid, NULL, $name, $line);
                return(TRUE);
            } else {
                $this->update_line(NULL, $macid);
                return(TRUE);
            }
        }
    }

    function update_line($luid=NULL, $macid=NULL, $name=NULL, $line=NULL) {
        if (isset($luid)) {
            $sql = "SELECT * FROM endpointman_line_list WHERE luid = " . $luid;
            $row = $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

            if (!isset($name)) {
                $sql = "SELECT description FROM devices WHERE id = " . $row['ext'];
                $name = $this->eda->sql($sql, 'getOne');
            }

            if (!isset($line)) {
                $line = $row['line'];
            }
            $sql = "UPDATE endpointman_line_list SET line = '" . $line . "', ext = '" . $row['ext'] . "', description = '" . $this->eda->escapeSimple($name) . "' WHERE luid =  " . $row['luid'];
            $this->eda->sql($sql);
            return(TRUE);
        } else {
            $sql = "SELECT * FROM endpointman_line_list WHERE mac_id = " . $macid;
            $lines_info = $this->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
            foreach ($lines_info as $row) {
                $sql = "SELECT description FROM devices WHERE id = " . $row['ext'];
                $name = $this->eda->sql($sql, 'getOne');

                $sql = "UPDATE endpointman_line_list SET line = '" . $row['line'] . "', ext = '" . $row['ext'] . "', description = '" . $this->eda->escapeSimple($name) . "' WHERE luid =  " . $row['luid'];
                $this->eda->sql($sql);
            }
            return(TRUE);
        }
    }

    /**
     * This will either a. delete said line or b. delete said device from line
     * @param <type> $line
     * @return <type>
     */
    function delete_line($lineid, $allow_device_remove=FALSE) {
        $sql = 'SELECT mac_id FROM endpointman_line_list WHERE luid = ' . $lineid;
        $mac_id = $this->eda->sql($sql, 'getOne');
        $row = $this->get_phone_info($mac_id);

        $sql = 'SELECT COUNT(*) FROM endpointman_line_list WHERE mac_id = ' . $mac_id;
        $num_lines = $this->eda->sql($sql, 'getOne');
        if ($num_lines > 1) {
            $sql = "DELETE FROM endpointman_line_list WHERE luid=" . $lineid;
            $this->eda->sql($sql);
            $this->message['delete_line'] = "Deleted!";
            return(TRUE);
        } else {
            if ($allow_device_remove) {
                $sql = "DELETE FROM endpointman_line_list WHERE luid=" . $lineid;
                $this->eda->sql($sql);

                $sql = "DELETE FROM endpointman_mac_list WHERE id=" . $mac_id;
                $this->eda->sql($sql);
                $this->message['delete_line'] = "Deleted!";
                return(TRUE);
            } else {
                $this->error['delete_line'] = _("You can't remove the only line left") . "!";
                return(FALSE);
            }
        }
    }

    function delete_device($mac_id) {


        $sql = "DELETE FROM endpointman_line_list WHERE ext=" . $mac_id;
        $this->eda->sql($sql);
		
		$sql = "DELETE FROM endpointman_mac_list WHERE NOT EXISTS (SELECT * FROM endpointman_line_list WHERE mac_id = id)";
        $this->eda->sql($sql);
        $this->message['delete_device'] = "Deleted!";
        return(TRUE);
    }

    function delete_device_by_mac($mac) {
        $sql = 'SELECT id FROM endpointman_mac_list WHERE mac = \''.
          str_replace(':', '', $mac). '\'';
        $mac_id = $this->eda->sql($sql, 'getOne');

        if ($mac_id) {
          $this->delete_device($mac_id);
        }

        $this->message['delete_device'] = "Deleted!";
        return(TRUE);
    }

    function retrieve_device_by_mac($mac) {
        $sql = 'SELECT id FROM endpointman_mac_list WHERE mac = \''.
          str_replace(':', '', $mac). '\'';
        $mac_id = $this->eda->sql($sql, 'getOne');

        return($mac_id);
    }

    function retrieve_device_by_ext($ext) {
        $sql = 'SELECT DISTINCT mac_id FROM endpointman_line_list WHERE ext = \''. $ext. '\'';
        $mac_id = $this->eda->sql($sql, 'getOne');

        return($mac_id);
    }

    function get_message($function_name) {
        if (isset($this->message[$function_name])) {
            return($this->message[$function_name]);
        } else {
            return("Unknown Message");
        }
    }

    /**
     * Send this function an ID from the mac devices list table and you'll get all the information we have on that particular phone
     * @param integer $mac_id ID number reference from the MySQL database referencing the table endpointman_mac_list
     * @return array
     * @example
     * Final Output will look something similar to this
     *  Array
     *       (
     *            [config_files_override] =>
     *            [global_user_cfg_data] => N;
     *            [model_id] => 213
     *            [brand_id] => 2
     *            [name] => Grandstream
     *            [directory] => grandstream
     *            [model] => GXP2000
     *            [mac] => 000B820D0050
     *            [template_id] => 0
     *            [global_custom_cfg_data] => Serialized Data (Changed Template Values)
     *            [long_name] => GXP Enterprise IP series [280,1200,2000,2010,2020]
     *            [product_id] => 21
     *            [cfg_dir] => gxp
     *            [cfg_ver] => 1.5
     *            [template_data] => Serialized Data (The default Template Values)
     *            [enabled] => 1
     *            [line] => Array
     *                (
     *                    [1] => Array
     *                        (
     *                            [luid] => 2
     *                            [mac_id] => 2
     *                            [line] => 1
     *                            [ext] => 1000
     *                            [description] => Description
     *                            [custom_cfg_data] =>
     *                            [user_cfg_data] =>
     *                            [secret] => secret
     *                            [id] => 1000
     *                            [tech] => sip
     *                            [dial] => SIP/1000
     *                            [devicetype] => fixed
     *                            [user] => 1000
     *                            [emergency_cid] =>
     *                        )
     *                )
     *         )
     */
	 
	 
	 
	 
	 	//SIP EXTENSION SETTINGS get all Extensions and Settings
	public function getSipextensions(){
		
		$ret = array();
		
		$sql = "SELECT * FROM sip";
		$res = $this->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);

		if (count($res)) {
            

            if (!$res) {
                $this->error['getSipSettings1'] = "Error with SQL Statement";
            }

		foreach ($res as $row) {
			$id=$row['id'];
			$keyword=$row['keyword'];
			$ret[$id . '-' . $keyword] = array(
			'keyword' => $row['keyword'],
			'data' => $row['data'],
			'flags' => $row['flags']
			
			);

} 
	}
//print_r($ret);
		return $ret;
		
	}	
		
		
		
	 //SIP  SETTINGS get all SIP Settings Part 1
	public function getSipSettings1(){

		$ret = array();
		
		$sql = "SELECT * FROM sipsettings";
		$res = $this->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);

		if (count($res)) {
            

            if (!$res) {
                $this->error['getSipSettings1'] = "Error with SQL Statement";
            }

		foreach ($res as $row) {
			$keyword=$row['keyword'];
			$ret[$keyword] = array(
			'data' => $row['data'],
			'seq' => $row['seq']
			
			);
		
	
	}	
	}
		
		return $ret;
	}	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	 //SIP  SETTINGS 2 get all SIP Settings Part 2
	public function getSipSettings2(){
		
		$ret = array();
		
		$sql = "SELECT * FROM kvstore_Sipsettings";
		$res = $this->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);

		if (count($res)) {
            

            if (!$res) {
                $this->error['getSipSettings2'] = "Error with SQL Statement";
            }

		foreach ($res as $row) {
			$key=$row['key'];
			$ret[$key] = array(
			'val' => $row['val'],
			'type' => $row['type']
			
			);
		
	
	}	
	}
		return $ret;
	}	
		
		
		
		

	
	//GET ALL FEATURE CODES
	public function getFeatureCodes(){
		
		$ret = array();
		
		$sql = "SELECT * FROM featurecodes";
		$res = $this->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
		
		
		if (count($res)) {
            
			
            $featurecodes = $res;

            if (!$featurecodes) {
                $this->error['featurecodes'] = "Error with SQL Statement";
            }

		foreach ($featurecodes as $row) {
			$featurename=$row['featurename'];
			$ret[$featurename] = array(
			'modulename' => $row['modulename'],
			'featurename' => $row['featurename'],
			'description' => $row['description'],
			'helptext' => $row['helptext'],
			'defaultcode' => $row['defaultcode'],
			'customcode' => $row['customcode'],
			'enabled' => $row['enabled'],
			'providedest' => $row['providedest']
			
			);






















			
			
		
	
	}	

		
}
		
		return $ret;
	}	
	 
	 
	 
	 
	 
	 
	 //SYSADMIN Settings
	public function getSysadminSettings(){
	//SYSADMIN IS A COMERCIAL MODULE - CHECK IF IT IS INSTALLED, ELSE THIS FEATURE WILL BE SKIPPED.
	global $active_modules;
	if (!empty($active_modules[sysadmin][rawname])) {	
		$ret = array();
		
		$sql = "SELECT * FROM sysadmin_options";
		$res = $this->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);

		if (count($res)) {
            

            if (!$res) {
                $this->error['sysadmin_options'] = "Error with SQL Statement";
            }

		foreach ($res as $row) {
			$key=$row['key'];
			$ret[$key] = array(
			'value' => $row['value']
		);	
	}	
}
		
}
		
		return $ret;
	}	
	 
	 
	 
	 
	 
	 
	 
	 
    function get_phone_info($mac_id=NULL) {
	
	
			$sipextensions=$this->getSipextensions();
			$SipSettings1=$this->getSipSettings1();
			$SipSettings2=$this->getSipSettings2();
			$featurecodes=$this->getFeatureCodes();
			$getSysadminSettings=$this->getSysadminSettings();
	
	
	
	
	
	
	
	
	
        //You could screw up a phone if the mac_id is blank
        if (!isset($mac_id)) {
            $this->error['get_phone_info'] = "Mac ID is not set";
            return(FALSE);
        }
        $sql = "SELECT id FROM endpointman_mac_list WHERE model > 0 AND id =" . $mac_id;

        $res = $this->eda->sql($sql);
        if (count($res)) {
            //Returns Brand Name, Brand Directory, Model Name, Mac Address, Extension (FreePBX), Custom Configuration Template, Custom Configuration Data, Product Name, Product ID, Product Configuration Directory, Product Configuration Version, Product XML name,
            $sql = "SELECT endpointman_mac_list.specific_settings, endpointman_mac_list.config_files_override, endpointman_mac_list.global_user_cfg_data, endpointman_model_list.id as model_id, endpointman_brand_list.id as brand_id, endpointman_brand_list.name, endpointman_brand_list.directory, endpointman_model_list.model, endpointman_mac_list.mac, endpointman_mac_list.template_id, endpointman_mac_list.global_custom_cfg_data, endpointman_product_list.long_name, endpointman_product_list.id as product_id, endpointman_product_list.cfg_dir, endpointman_product_list.cfg_ver, endpointman_model_list.template_data, endpointman_model_list.enabled, endpointman_mac_list.global_settings_override FROM endpointman_line_list, endpointman_mac_list, endpointman_model_list, endpointman_brand_list, endpointman_product_list WHERE endpointman_mac_list.model = endpointman_model_list.id AND endpointman_brand_list.id = endpointman_model_list.brand AND endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_mac_list.id = endpointman_line_list.mac_id AND endpointman_mac_list.id = " . $mac_id;

            $phone_info = $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

            if (!$phone_info) {
                $this->error['get_phone_info'] = "Error with SQL Statement";
            }

            //If there is a template associated with this phone then pull that information and put it into the array
            if ($phone_info['template_id'] > 0) {
                $sql = "SELECT name, global_custom_cfg_data, config_files_override, global_settings_override FROM endpointman_template_list WHERE id = " . $phone_info['template_id'];

                $phone_info['template_data_info'] = $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
            }

            $sql = "SELECT endpointman_line_list.*, sip.data as secret, devices.*, endpointman_line_list.description AS epm_description FROM endpointman_line_list, sip, devices WHERE endpointman_line_list.ext = devices.id AND endpointman_line_list.ext = sip.id AND sip.keyword = 'secret' AND mac_id = " . $mac_id . " ORDER BY endpointman_line_list.line ASC";
            $lines_info = $this->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
            foreach ($lines_info as $line) {
			
			
			
			


			
			
			
			
			
			
			
                $phone_info['line'][$line['line']] = $line;
                $phone_info['line'][$line['line']]['description'] = $line['epm_description'];
				$phone_info['line'][$line['line']]['extension'] = $line['ext'];
				$phone_info['line'][$line['line']]['user_extension'] = $line['user'];
				$phone_info['line'][$line['line']]['allowedcodec']=$sipextensions[$phone_info['line'][$line['line']]['extension'] . '-' . 'allow'][data];
				$phone_info['line'][$line['line']]['forcerport']=$sipextensions[$phone_info['line'][$line['line']]['extension'] . '-' . 'force_rport'][data];
				$phone_info['line'][$line['line']]['media_encryption']=$sipextensions[$phone_info['line'][$line['line']]['extension'] . '-' . 'media_encryption'][data];
				$phone_info['line'][$line['line']]['sipdriver']=$sipextensions[$phone_info['line'][$line['line']]['extension'] . '-' . 'sipdriver'][data];
				$phone_info['line'][$line['line']]['transport']=$sipextensions[$phone_info['line'][$line['line']]['extension'] . '-' . 'transport'][data];
				$phone_info['line'][$line['line']]['trustrpid']=$sipextensions[$phone_info['line'][$line['line']]['extension'] . '-' . 'trustrpid'][data];
				$phone_info['line'][$line['line']]['callerid']=$sipextensions[$phone_info['line'][$line['line']]['extension'] . '-' . 'callerid'][data];
				$phone_info['line'][$line['line']]['encryption']=$sipextensions[$phone_info['line'][$line['line']]['extension'] . '-' . 'encryption'][data];
				$phone_info['line'][$line['line']]['timestamp']=time();
				
				
//Calculate the right SIP PORT (SIP/PJSIP/TLS/TCP)
	//TLS
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_pjsip" and $phone_info['line'][$line['line']]['transport'] == '0.0.0.0-tls') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings2['tlsport-0.0.0.0'][val];
	$phone_info['line'][$line['line']]['proto'] = 'tls';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_pjsip" and $phone_info['line'][$line['line']]['transport'] == '127.0.0.1-tls') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings2['tlsport-127.0.0.1'][val];
	$phone_info['line'][$line['line']]['proto'] = 'tls';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_sip" and $phone_info['line'][$line['line']]['transport'] == 'tls,udp,tcp') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings1['tlsbindport'][data];
	$phone_info['line'][$line['line']]['proto'] = 'tls';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_sip" and $phone_info['line'][$line['line']]['transport'] == 'tls,udp') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings1['tlsbindport'][data];
	$phone_info['line'][$line['line']]['proto'] = 'tls';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_sip" and $phone_info['line'][$line['line']]['transport'] == 'tls,tcp') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings1['tlsbindport'][data];
	$phone_info['line'][$line['line']]['proto'] = 'tls';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_sip" and $phone_info['line'][$line['line']]['transport'] == 'tls') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings1['tlsbindport'][data];
	$phone_info['line'][$line['line']]['proto'] = 'tls';
	}
	//TCP
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_pjsip" and $phone_info['line'][$line['line']]['transport'] == '0.0.0.0-tcp') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings2['tcpport-0.0.0.0'][val];
	$phone_info['line'][$line['line']]['proto'] = 'tcp';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_pjsip" and $phone_info['line'][$line['line']]['transport'] == '127.0.0.1-tcp') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings2['tcpport-127.0.0.1'][val];
	$phone_info['line'][$line['line']]['proto'] = 'tcp';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_sip" and $phone_info['line'][$line['line']]['transport'] == 'tcp,udp,tls') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings1['bindport'][data];
	$phone_info['line'][$line['line']]['proto'] = 'tcp';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_sip" and $phone_info['line'][$line['line']]['transport'] == 'tcp,udp') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings1['bindport'][data];
	$phone_info['line'][$line['line']]['proto'] = 'tcp';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_sip" and $phone_info['line'][$line['line']]['transport'] == 'tcp,tls') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings1['bindport'][data];
	$phone_info['line'][$line['line']]['proto'] = 'tcp';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_sip" and $phone_info['line'][$line['line']]['transport'] == 'tcp') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings1['bindport'][data];
	$phone_info['line'][$line['line']]['proto'] = 'tcp';
	}
	//UDP
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_pjsip" and $phone_info['line'][$line['line']]['transport'] == '') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings2['udpport-0.0.0.0'][val];
	$phone_info['line'][$line['line']]['proto'] = 'udp';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_pjsip" and $phone_info['line'][$line['line']]['transport'] == '0.0.0.0-udp') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings2['udpport-0.0.0.0'][val];
	$phone_info['line'][$line['line']]['proto'] = 'udp';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_pjsip" and $phone_info['line'][$line['line']]['transport'] == '127.0.0.1-udp') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings2['udpport-127.0.0.1'][val];
	$phone_info['line'][$line['line']]['proto'] = 'udp';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_sip" and $phone_info['line'][$line['line']]['transport'] == 'udp,tcp,tls') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings1['bindport'][data];
	$phone_info['line'][$line['line']]['proto'] = 'udp';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_sip" and $phone_info['line'][$line['line']]['transport'] == 'udp,tcp') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings1['bindport'][data];
	$phone_info['line'][$line['line']]['proto'] = 'udp';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_sip" and $phone_info['line'][$line['line']]['transport'] == 'udp,tls') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings1['bindport'][data];
	$phone_info['line'][$line['line']]['proto'] = 'udp';
	}
	if ($phone_info['line'][$line['line']]['sipdriver'] == "chan_sip" and $phone_info['line'][$line['line']]['transport'] == 'udp') {
	$phone_info['line'][$line['line']]['server_port'] = $SipSettings1['bindport'][data];
	$phone_info['line'][$line['line']]['proto'] = 'udp';
	}				
				
				
				
                
            }
			//print_r ($phone_info);
        } else {
            $sql = "SELECT id, mac FROM endpointman_mac_list WHERE id =" . $mac_id;
            //Phone is unknown, we need to display this to the end user so that they can make corrections
            $row = $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

            $brand = $this->get_brand_from_mac($row['mac']);
            if ($brand) {
                $phone_info['brand_id'] = $brand['id'];
                $phone_info['name'] = $brand['name'];
            } else {
                $phone_info['brand_id'] = 0;
                $phone_info['name'] = 'Unknown';
            }

            $phone_info['id'] = $mac_id;
            $phone_info['model_id'] = 0;
            $phone_info['product_id'] = 0;
            $phone_info['custom_cfg_template'] = 0;
            $phone_info['mac'] = $row['mac'];
            $sql = "SELECT endpointman_line_list.*, sip.data as secret, devices.* FROM endpointman_line_list, sip, devices WHERE endpointman_line_list.ext = devices.id AND endpointman_line_list.ext = sip.id AND sip.keyword = 'secret' AND mac_id = " . $mac_id;
            $lines_info = $this->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
			
            foreach ($lines_info as $line) {
                $phone_info['line'][$line['line']] = $line;
				
            }
        }
		
        return $phone_info;
    }

    /**
     * Custom Means specific to that MAC
     * id is either the mac ID (not address) or the template ID
     * @param integer $id
     * @param integer $custom
     */
    function edit_template_display($id, $custom) {
        //endpointman_flush_buffers();


        $alt_configs = NULL;

        if ($custom == 0) {
            $sql = "SELECT model_id FROM endpointman_template_list WHERE id=" . $id;
        } else {
            $sql = "SELECT model FROM endpointman_mac_list WHERE id=" . $id;
        }

        $model_id = $this->eda->sql($sql, 'getOne');

        //Make sure the model data from the local confg files are stored in the database and vice-versa. Serious errors will occur if the database is not in sync with the local file
        if (!$this->sync_model($model_id)) {
            die("unable to sync local template files - TYPE:" . $custom);
        }


        //Determine if we are dealing with a general template or a specific [for that phone only] template (custom =0 means general)
        if ($custom == 0) {
            $sql = "SELECT endpointman_model_list.max_lines, endpointman_model_list.model as model_name, endpointman_template_list.global_custom_cfg_data,  endpointman_product_list.config_files, endpointman_product_list.short_name, endpointman_product_list.id as product_id, endpointman_model_list.template_data, endpointman_model_list.id as model_id, endpointman_template_list.* FROM endpointman_product_list, endpointman_model_list, endpointman_template_list WHERE endpointman_product_list.id = endpointman_template_list.product_id AND endpointman_template_list.model_id = endpointman_model_list.id AND endpointman_template_list.id = " . $id;
        } else {
            $sql = "SELECT endpointman_model_list.max_lines, endpointman_model_list.model as model_name, endpointman_mac_list.global_custom_cfg_data, endpointman_product_list.config_files, endpointman_mac_list.*, endpointman_line_list.*, endpointman_model_list.id as model_id, endpointman_model_list.template_data, endpointman_product_list.id as product_id, endpointman_product_list.short_name, endpointman_product_list.cfg_dir, endpointman_brand_list.directory FROM endpointman_brand_list, endpointman_mac_list, endpointman_model_list, endpointman_product_list, endpointman_line_list WHERE endpointman_mac_list.id=" . $id . " AND endpointman_mac_list.id = endpointman_line_list.mac_id AND endpointman_mac_list.model = endpointman_model_list.id AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id";
        }

        $row = $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

        $this->tpl->assign("template_editor_display", 1);
        echo $this->tpl->draw('global_header');
        //Let the template system know if we are working with a general template or a specific [for that phone only] template
        $this->tpl->assign("custom", $custom);
        if ($custom) {
            $this->tpl->assign("ext", $row['ext']);
        } else {
            $this->tpl->assign("template_name", $row['name']);
        }
        $this->tpl->assign("product", $row['short_name']);
        $this->tpl->assign("model", $row['model_name']);

        if ($ma = $this->models_available($row['model_id'], NULL, $row['product_id'])) {
            $this->tpl->assign("models_ava", $ma);
        }

        if (isset($_REQUEST['maxlines'])) {
            $areas = $this->areaAvailable($row['model_id'], $_REQUEST['maxlines']);
        } else {
            $areas = $this->areaAvailable($row['model_id'], 1);
        }

        $this->tpl->assign("area_ava", $areas);
        //Start the display of the html file in the product folder
        if ($row['config_files_override'] == "") {
            $config_files_saved = "";
        } else {
            $config_files_saved = unserialize($row['config_files_override']);
        }
        $config_files_list = explode(",", $row['config_files']);
        $i = 0;
        $alt = 0;

        $i = 0;
        $b = 0;
        $only_configs = array();
        foreach ($config_files_list as $files) {
            $sql = "SELECT * FROM  endpointman_custom_configs WHERE product_id = '" . $row['product_id'] . "' AND original_name = '" . $files . "'";
            $alt_configs_list_count = $this->eda->sql($sql);
            if (count($alt_configs_list_count) > 0) {
                $alt_configs_list = $this->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
                $alt_configs[$i]['name'] = $files;
                $files = str_replace(".", "_", $files);
                $h = 0;
                foreach ($alt_configs_list as $ccf) {
                    $alt_configs[$i]['list'][$h]['id'] = $ccf['id'];
                    $cf_key = $files;
                    if ((isset($config_files_saved[$cf_key])) AND (is_array($config_files_saved)) AND ($config_files_saved[$cf_key] == $ccf['id'])) {
                        $alt_configs[$i]['list'][$h]['selected'] = 'selected';
                    }
                    $alt_configs[$i]['list'][$h]['name'] = $ccf['name'];
                    $h++;
                }
                $alt = 1;
            } else {
                $only_configs[$b]['name'] = $files;
                $b++;
            }
            $i++;
        }

        $this->tpl->assign("only_configs", $only_configs);
        $this->tpl->assign("alt_configs", $alt_configs);
        $this->tpl->assign("alt", $alt);
        if (!isset($_REQUEST['maxlines'])) {
            $maxlines = 1;
        } else {
            $maxlines = $_REQUEST['maxlines'];
        }
        if ($row['template_data'] != "") {
            $out = $this->generate_gui_html($row['template_data'], $row['global_custom_cfg_data'], TRUE, NULL, $maxlines);
        } else {
            echo "No Template Data has been defined for this Product<br />";
        }

        $this->tpl->assign("template_editor", $out);
        $this->tpl->assign("hidden_id", $row['id']);
        $this->tpl->assign("hidden_custom", $custom);
        echo $this->tpl->draw('template_editor');

        $this->tpl->assign("debug", "");
    }

    /**
     * Generates the Visual Display for the end user
     * @param <type> $cfg_data
     * @param <type> $custom_cfg_data
     * @param <type> $admin
     * @param <type> $user_cfg_data
     * @return <type>
     */
    function generate_gui_html($cfg_data, $custom_cfg_data=NULL, $admin=FALSE, $user_cfg_data=NULL, $max_lines=3, $ext=NULL) {
        //take the data out of the database and turn it back into an array for use
        $cfg_data = unserialize($cfg_data);
        $template_type = 'GENERAL';
        //Check to see if there is a custom template for this phone already listed in the endpointman_mac_list database
        if (!empty($custom_cfg_data)) {
            $custom_cfg_data = unserialize($custom_cfg_data);
            if (array_key_exists('data', $custom_cfg_data)) {
                if (array_key_exists('ari', $custom_cfg_data)) {
                    $extra_data = $custom_cfg_data['ari'];
                } else {
                    $template_type = 'GLOBAL';
                    $extra_data = $custom_cfg_data['freepbx'];
                }
                $custom_cfg_data = $custom_cfg_data['data'];
            } else {
                $extra_data = array();
            }
        } else {
            $custom_cfg_data = array();
            $extra_data = array();
        }
        if (isset($user_cfg_data)) {
            $user_cfg_data = unserialize($user_cfg_data);
        }

        $template_variables_array = array();
        $group_count = 0;
        $variables_count = 0;

        foreach ($cfg_data['data'] as $cats_name => $cats) {
            if ($admin) {
                $group_count++;
                $template_variables_array[$group_count]['title'] = $cats_name;
            } else {
                //Group all ARI stuff into one tab
                $template_variables_array[$group_count]['title'] = "Your Phone Settings";
            }
            foreach ($cats as $subcat_name => $subcats) {
                foreach ($subcats as $item_var => $config_options) {
                    if (preg_match('/(.*)\|(.*)/i', $item_var, $matches)) {
                        $type = $matches[1];
                        $variable = $matches[2];
                    } else {
                        die('no matches!');
                    }
                    if ($admin) {
                        //Administration View Only
                        switch ($type) {
                            case "lineloop":
                                //line|1|display_name
                                foreach ($config_options as $var_name => $var_items) {
                                    $lcount = isset($var_items['line_count']) ? $var_items['line_count'] : $lcount;
                                    $key = "line|" . $lcount . "|" . $var_name;
                                    $items[$variables_count] = $items;
                                    $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count, $var_items, $key, $custom_cfg_data, $admin, $user_cfg_data, $extra_data, $template_type);
                                    $template_variables_array[$group_count]['data'][$variables_count]['looping'] = TRUE;
                                    $variables_count++;
                                }

                                if ($lcount <= $max_lines) {
                                    $template_variables_array[$group_count]['title'] = "Line Options for Line " . $lcount;
                                    $group_count++;
                                } else {
                                    unset($template_variables_array[$group_count]);
                                }

                                continue 2;
                            case "loop":
                                foreach ($config_options as $var_name => $var_items) {
                                    //loop|remotephonebook_url_0
                                    $tv = explode('_', $variable);
                                    $key = "loop|" . $tv[0] . "_" . $var_name . "_" . $var_items['loop_count'];
                                    $items[$variables_count] = $var_items;
                                    $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count, $var_items, $key, $custom_cfg_data, $admin, $user_cfg_data, $extra_data, $template_type);
                                    $template_variables_array[$group_count]['data'][$variables_count]['looping'] = TRUE;
                                    $variables_count++;
                                }
                                continue 2;
                        }
                    } else {
                        //ARI View Only
                        switch ($type) {
                            case "loop_line_options":
                                //$a is the line number
                                $sql = "SELECT line FROM endpointman_line_list WHERE  ext = " . $ext;
                                $a = $this->eda->sql($sql, 'getOne');
                                //TODO: fix this area
                                $template_variables_array[$group_count]['data'][$variables_count]['type'] = "break";
                                $variables_count++;
                                continue 2;
                            case "loop":
                                foreach ($config_options as $var_name => $var_items) {
                                    $tv = explode('_', $variable);
                                    $key = "loop|" . $tv[0] . "_" . $var_name . "_" . $var_items['loop_count'];
                                    if (isset($extra_data[$key])) {
                                        $items[$variables_count] = $var_items;
                                        $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count, $var_items, $key, $custom_cfg_data, $admin, $user_cfg_data, $extra_data, $template_type);
                                        $template_variables_array[$group_count]['data'][$variables_count]['looping'] = TRUE;
                                        $variables_count++;
                                    }
                                }
                                continue 2;
                        }
                    }
                    //Both Views
                    switch ($config_options['type']) {
                        case "break":
                            $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count, $config_options, $key, $custom_cfg_data, $admin, $user_cfg_data, $extra_data, $template_type);
                            $variables_count++;
                            break;
                        default:
                            if (array_key_exists('variable', $config_options)) {
                                $key = str_replace('$', '', $config_options['variable']);
                                //TODO: Move this into the sync function
                                //Checks to see if values are defined in the database, if not then we assume this is a new option and we need a default value here!
                                if (!isset($custom_cfg_data[$key])) {
                                    //xml2array will take values that have no data and turn them into arrays, we want to avoid the word 'array' as a default value, so we blank it out here if we are an array
                                    if ((array_key_exists('default_value', $config_options)) AND (is_array($config_options['default_value']))) {
                                        $custom_cfg_data[$key] = "";
                                    } elseif ((array_key_exists('default_value', $config_options)) AND (!is_array($config_options['default_value']))) {
                                        $custom_cfg_data[$key] = $config_options['default_value'];
                                    }
                                }
                                if ((!$admin) AND (isset($extra_data[$key]))) {
                                    $custom_cfg_data[$key] = $user_cfg_data[$key];
                                    $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count, $config_options, $key, $custom_cfg_data, $admin, $user_cfg_data, $extra_data, $template_type);
                                    $variables_count++;
                                } elseif ($admin) {
                                    $template_variables_array[$group_count]['data'][$variables_count] = $this->generate_form_data($variables_count, $config_options, $key, $custom_cfg_data, $admin, $user_cfg_data, $extra_data, $template_type);
                                    $variables_count++;
                                }
                            }
                            break;
                    }
                    continue;
                }
            }
        }

        return($template_variables_array);
    }

    /**
     * Generate an array that will get parsed as HTML from an array of values from XML
     * @param int $i
     * @param array $cfg_data
     * @param string $key
     * @param array $custom_cfg_data
     * @return array
     */
    function generate_form_data($i, $cfg_data, $key=NULL, $custom_cfg_data=NULL, $admin=FALSE, $user_cfg_data=NULL, $extra_data=NULL, $template_type='GENERAL') {
        switch ($cfg_data['type']) {
            case "input":
                if ((!$admin) && (isset($user_cfg_data[$key]))) {
                    $custom_cfg_data[$key] = $user_cfg_data[$key];
                }
                $template_variables_array['type'] = "input";
                if (isset($cfg_data['max_chars'])) {
                    $template_variables_array['max_chars'] = $cfg_data['max_chars'];
                }
                $template_variables_array['key'] = $key;
                $template_variables_array['value'] = isset($custom_cfg_data[$key]) ? $custom_cfg_data[$key] : $cfg_data['default_value'];
                $template_variables_array['description'] = $cfg_data['description'];
                break;
            case "radio":
                if ((!$admin) && (isset($user_cfg_data[$key]))) {
                    $custom_cfg_data[$key] = $user_cfg_data[$key];
                }
                $num = isset($custom_cfg_data[$key]) ? $custom_cfg_data[$key] : $cfg_data['default_value'];
                $template_variables_array['type'] = "radio";
                $template_variables_array['key'] = $key;
                $template_variables_array['description'] = $cfg_data['description'];
                $z = 0;
                while ($z < count($cfg_data['data'])) {
				//$key = $key . $z;
				
				
                    $template_variables_array['data'][$z]['key'] = $key;
                    $template_variables_array['data'][$z]['value'] = $cfg_data['data'][$z]['value'];
                    $template_variables_array['data'][$z]['description'] = $cfg_data['data'][$z]['text'];
					
                    if ($cfg_data['data'][$z]['value'] == $num) {
                        $template_variables_array['data'][$z]['checked'] = 'checked';
                    }
                    $z++;
                }
                break;
            case "list":
                if ((!$admin) && (isset($user_cfg_data[$key]))) {
                    $custom_cfg_data[$key] = $user_cfg_data[$key];
                }
                $num = isset($custom_cfg_data[$key]) ? $custom_cfg_data[$key] : $cfg_data['default_value'];
                $template_variables_array['type'] = "list";
                $template_variables_array['key'] = $key;
                $template_variables_array['description'] = $cfg_data['description'];
                $z = 0;
                while ($z < count($cfg_data['data'])) {
                    $template_variables_array['data'][$z]['value'] = $cfg_data['data'][$z]['value'];
                    $template_variables_array['data'][$z]['description'] = $cfg_data['data'][$z]['text'];
                    if (isset($cfg_data['data'][$z]['disable'])) {
                        $cfg_data['data'][$z]['disable'] = str_replace('{$count}', $z, $cfg_data['data'][$z]['disable']);
                        $template_variables_array['data'][$z]['disables'] = explode(",", $cfg_data['data'][$z]['disable']);
                    }
                    if (isset($cfg_data['data'][$z]['enable'])) {
                        $cfg_data['data'][$z]['enable'] = str_replace('{$count}', $z, $cfg_data['data'][$z]['enable']);
                        $template_variables_array['data'][$z]['enables'] = explode(",", $cfg_data['data'][$z]['enable']);
                    }
                    if ($cfg_data['data'][$z]['value'] == $num) {
                        $template_variables_array['data'][$z]['selected'] = 'selected';
                    }
                    $z++;
                }
                break;
            case "checkbox":
                if ((!$admin) && (isset($user_cfg_data[$key]))) {
                    $custom_cfg_data[$key] = $user_cfg_data[$key];
                }
                $num = isset($custom_cfg_data[$key]) ? $custom_cfg_data[$key] : $cfg_data['default_value'];
                $template_variables_array['type'] = "checkbox";
                $template_variables_array['key'] = $key;
                $template_variables_array['description'] = $cfg_data['description'];
                $template_variables_array['checked'] = $custom_cfg_data[$key] ? TRUE : NULL;
                $template_variables_array['value'] = $key;
                break;
            case "group";
                $template_variables_array['type'] = "group";
                $template_variables_array['description'] = $cfg_data['description'];
                break;
            case "header";
                $template_variables_array['type'] = "header";
                $template_variables_array['description'] = $cfg_data['description'];
                break;
            case "textarea":
                if ((!$admin) && (isset($user_cfg_data[$key]))) {
                    $custom_cfg_data[$key] = $user_cfg_data[$key];
                }
                $template_variables_array['type'] = "textarea";
                if (isset($cfg_data['rows'])) {
                    $template_variables_array['rows'] = $cfg_data['rows'];
                }
                if (isset($cfg_data['cols'])) {
                    $template_variables_array['cols'] = $cfg_data['cols'];
                }
                $template_variables_array['key'] = $key;
                $template_variables_array['value'] = isset($custom_cfg_data[$key]) ? $custom_cfg_data[$key] : $cfg_data['default_value'];
                $template_variables_array['description'] = $cfg_data['description'];
                break;
            case "break":
                if ($admin) {
                    $template_variables_array['type'] = "break";
                } else {
                    $template_variables_array['type'] = "NA";
                }
                break;
            default:
                $template_variables_array['type'] = "NA";
                break;
        }

        if (isset($cfg_data['tooltip'])) {
            $template_variables_array['tooltip'] = htmlentities($cfg_data['tooltip']);
        }

        if (($this->global_cfg['enable_ari']) AND ($admin) AND ($cfg_data['type'] != "break") AND ($cfg_data['type'] != "group") AND ($template_type == 'GENERAL')) {

            $template_variables_array['aried'] = 1;
            $template_variables_array['ari']['key'] = $key;

            if (isset($extra_data[$key])) {
                $template_variables_array['ari']['checked'] = "checked";
            }
        }

        if ($template_type == 'GLOBAL') {
            $template_variables_array['freepbxed'] = 1;
            $template_variables_array['freepbx']['key'] = $key;
            if (empty($extra_data)) {
                $template_variables_array['freepbx']['checked'] = TRUE;
            } elseif (isset($extra_data[$key])) {
                $template_variables_array['freepbx']['checked'] = TRUE;
            }
        }
        return($template_variables_array);
    }

    /**
     * Save template from the template view pain
     * @param int $id Either the MAC ID or Template ID
     * @param int $custom Either 0 or 1, it determines if $id is MAC ID or Template ID
     * @param array $variables The variables sent from the form. usually everything in $_REQUEST[]
     * @return string Location of area to return to in Endpoint Manager
     */
    function save_template($id, $custom, $variables) {
        //Custom Means specific to that MAC
        //This function is reversed. Not sure why
        if ($custom != "0") {
            $sql = "SELECT endpointman_model_list.max_lines, endpointman_product_list.config_files, endpointman_mac_list.*, endpointman_product_list.id as product_id, endpointman_product_list.long_name, endpointman_model_list.template_data, endpointman_product_list.cfg_dir, endpointman_brand_list.directory FROM endpointman_brand_list, endpointman_mac_list, endpointman_model_list, endpointman_product_list WHERE endpointman_mac_list.id=" . $id . " AND endpointman_mac_list.model = endpointman_model_list.id AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id";
        } else {
            $sql = "SELECT endpointman_model_list.max_lines, endpointman_brand_list.directory, endpointman_product_list.cfg_dir, endpointman_product_list.config_files, endpointman_product_list.long_name, endpointman_model_list.template_data, endpointman_model_list.id as model_id, endpointman_template_list.* FROM endpointman_brand_list, endpointman_product_list, endpointman_model_list, endpointman_template_list WHERE endpointman_product_list.id = endpointman_template_list.product_id AND endpointman_brand_list.id = endpointman_product_list.brand AND endpointman_template_list.model_id = endpointman_model_list.id AND endpointman_template_list.id = " . $id;
        }

        //Load template data
        $row = $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

        $cfg_data = unserialize($row['template_data']);
        $count = count($cfg_data);

        $custom_cfg_data_ari = array();

        foreach ($cfg_data['data'] as $cats) {
            foreach ($cats as $items) {
                foreach ($items as $key_name => $config_options) {
                    if (preg_match('/(.*)\|(.*)/i', $key_name, $matches)) {
                        $type = $matches[1];
                        $key = $matches[2];
                    } else {
                        die('invalid');
                    }
                    switch ($type) {
                        case "loop":
                            $stuffing = explode("_", $key);
                            $key2 = $stuffing[0];
                            foreach ($config_options as $item_key => $item_data) {
                                $lc = isset($item_data['loop_count']) ? $item_data['loop_count'] : '';
                                $key = 'loop|' . $key2 . '_' . $item_key . '_' . $lc;
                                if ((isset($item_data['loop_count'])) AND (isset($_REQUEST[$key]))) {
                                    $custom_cfg_data[$key] = $_REQUEST[$key];
                                    $ari_key = "ari_" . $key;
                                    if (isset($_REQUEST[$ari_key])) {
                                        if ($_REQUEST[$ari_key] == "on") {
                                            $custom_cfg_data_ari[$key] = 1;
                                        }
                                    }
                                }
                            }
                            break;
                        case "lineloop":
                            foreach ($config_options as $item_key => $item_data) {
                                $lc = isset($item_data['line_count']) ? $item_data['line_count'] : '';
                                $key = 'line|' . $lc . '|' . $item_key;
                                if ((isset($item_data['line_count'])) AND (isset($_REQUEST[$key]))) {
                                    $custom_cfg_data[$key] = $_REQUEST[$key];
                                    $ari_key = "ari_" . $key;
                                    if (isset($_REQUEST[$ari_key])) {
                                        if ($_REQUEST[$ari_key] == "on") {
                                            $custom_cfg_data_ari[$key] = 1;
                                        }
                                    }
                                }
                            }
                            break;
                        case "option":
                            if (isset($_REQUEST[$key])) {
                                $custom_cfg_data[$key] = $_REQUEST[$key];
                                $ari_key = "ari_" . $key;
                                if (isset($_REQUEST[$ari_key])) {
                                    if ($_REQUEST[$ari_key] == "on") {
                                        $custom_cfg_data_ari[$key] = 1;
                                    }
                                }
                            }
                            break;
                        default:
                            break;
                    }
                }
            }
        }

        $config_files = explode(",", $row['config_files']);

        $i = 0;
        while ($i < count($config_files)) {
            $config_files[$i] = str_replace(".", "_", $config_files[$i]);
            if (isset($_REQUEST[$config_files[$i]])) {
                $_REQUEST[$config_files[$i]] = explode("_", $_REQUEST[$config_files[$i]], 2);
                $_REQUEST[$config_files[$i]] = $_REQUEST[$config_files[$i]][0];
                if ($_REQUEST[$config_files[$i]] > 0) {
                    $config_files_selected[$config_files[$i]] = $_REQUEST[$config_files[$i]];
                }
            }
            $i++;
        }

        if (!isset($config_files_selected)) {
            $config_files_selected = "";
        } else {
            $config_files_selected = serialize($config_files_selected);
        }
        $custom_cfg_data_temp['data'] = $custom_cfg_data;
        $custom_cfg_data_temp['ari'] = $custom_cfg_data_ari;

        $save = serialize($custom_cfg_data_temp);

        if ($custom == "0") {
            $sql = 'UPDATE endpointman_template_list SET config_files_override = \'' . addslashes($config_files_selected) . '\', global_custom_cfg_data = \'' . addslashes($save) . '\' WHERE id =' . $id;
            $location = "template_manager";
        } else {
            $sql = 'UPDATE endpointman_mac_list SET config_files_override = \'' . addslashes($config_files_selected) . '\', template_id = 0, global_custom_cfg_data = \'' . addslashes($save) . '\' WHERE id =' . $id;
            $location = "devices_manager";
        }

        $this->eda->sql($sql);

        $phone_info = array();

        if ($custom != 0) {
            $phone_info = $this->get_phone_info($id);
            if (isset($_REQUEST['epm_reboot'])) {
                $this->prepare_configs($phone_info);
            } else {
                $this->prepare_configs($phone_info, FALSE);
            }
        } else {
            $sql = 'SELECT id FROM endpointman_mac_list WHERE template_id = ' . $id;
            $phones = $this->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
            foreach ($phones as $data) {
                $phone_info = $this->get_phone_info($data['id']);
                if (isset($_REQUEST['epm_reboot'])) {
                    $this->prepare_configs($phone_info);
                } else {
                    $this->prepare_configs($phone_info, FALSE);
                }
            }
        }

        if (isset($_REQUEST['silent_mode'])) {
            echo '<script language="javascript" type="text/javascript">window.close();</script>';
        } else {
            return($location);
        }
    }

    function write_configs($provisioner_lib, $reboot, $write_path, $phone_info, $returned_data) {
	
        //Create Directory Structure (If needed)
        if (isset($provisioner_lib->directory_structure)) {
            foreach ($provisioner_lib->directory_structure as $data) {
                if (file_exists(PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data)) {
                    $dir_iterator = new RecursiveDirectoryIterator(PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data . "/");
                    $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
                    // could use CHILD_FIRST if you so wish
                    foreach ($iterator as $file) {
                        $dir = $write_path . str_replace(PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/", "", dirname($file));
                        if (!file_exists($dir)) {
                            if (!@mkdir($dir, 0775, TRUE)) {
                                $this->error['parse_configs'] = "Could Not Create Directory: " . $data;
                                return(FALSE);
                            }
                        }
                    }
                } else {
                    $dir = $write_path . $data;
                    if (!file_exists($dir)) {
                        if (!@mkdir($dir, 0775)) {
                            $this->error['parse_configs'] = "Could Not Create Directory: " . $data;
                            return(FALSE);
                        }
                    }
                }
            }
        }

        //Copy Files (If needed)
        if (isset($provisioner_lib->copy_files)) {
            foreach ($provisioner_lib->copy_files as $data) {
                if (file_exists(PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data)) {
                    $file = $write_path . $data;
                    $orig = PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data;
                    if (!file_exists($file)) {
                        if (!@copy($orig, $file)) {
                            $this->error['parse_configs'] = "Could Not Create File: " . $data;
                            return(FALSE);
                        }
                    } else {
                        if (file_exists(PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data)) {
                            if (!file_exists(dirname($write_path . $data))) {
                                !@mkdir(dirname($write_path . $data), 0775);
                            }
                            copy(PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data, $write_path . $data);
                            chmod($write_path . $data, 0775);
                        }
                    }
                }
            }
        }

        foreach ($returned_data as $file => $data) {
            if (((file_exists($write_path . $file)) AND (is_writable($write_path . $file)) AND (!in_array($file, $provisioner_lib->protected_files))) OR (!file_exists($write_path . $file))) {
                //Move old file to backup
                if (!$this->global_cfg['backup_check']) {
                    if (!file_exists($write_path . 'config_bkup')) {
                        if (!@mkdir($write_path . 'config_bkup', 0775)) {
                            $this->error['parse_configs'] = "Could Not Create Backup Directory";
                            return(FALSE);
                        }
                    }
                    if (file_exists($write_path . $file)) {
                        copy($write_path . $file, $write_path . 'config_bkup/' . $file . '.' . time());
                    }
                }

			
				
				
				
				//print_r ($additionalmac);
				//print_r ($file2);
				file_put_contents($write_path . $file, $data);
				
                chmod($write_path . $file, 0775);
				
			
			
	//Add Additional MAC File Symlinks to enable autoprovisioning for Multicell Devices like Snom and much more devices
				//$additionalmacs should be added as Input field in the Template JSON File and MAC Adresses should be inserted comma "," separated.
				$globalcfgdata = unserialize($phone_info[global_custom_cfg_data]);
				$globalcfgdata_cs = unserialize($phone_info[template_data_info][global_custom_cfg_data]);
				
				
				$additionalmacs = $globalcfgdata[data][additionalmacs];
				$additionalmacs = explode(',', $additionalmacs);
				$additionalmacs_cs = $globalcfgdata_cs[data][additionalmacs];
				$additionalmacs_cs = explode(',', $additionalmacs_cs);
				
				$filelist = scandir($write_path);
				
			//Now we remove symlinks for your device and rebuild all symlinks you have entered in global Settings input area $additionalmacs in your template settings.
				foreach($filelist as $filesymlinkcheck) {
					if (is_link($write_path . $filesymlinkcheck)) {
						if (readlink($write_path . $filesymlinkcheck) == $write_path . $file){
							unlink($write_path . $filesymlinkcheck);
						}
			}
			}
			
				foreach($additionalmacs as $additionalmac) {
				if ($additionalmac !== ""){
						if (strpos($file, '_additional') !== false) {
					$filesymlink = str_replace($phone_info[mac],$additionalmac,$file);
						$filesymlink = str_replace("_additional", "", "$filesymlink");
						if (!file_exists($write_path . $filesymlink)) {
				
				
				symlink($write_path . $file, $write_path . $filesymlink);
						}
					}
				}
				}
				foreach($additionalmacs_cs as $additionalmac) {
				if ($additionalmac !== ""){
						if (strpos($file, '_additional') !== false) {
					$filesymlink = str_replace($phone_info[mac],$additionalmac,$file);
						$filesymlink = str_replace("_additional", "", "$filesymlink");
						if (!file_exists($write_path . $filesymlink)) {
				
				
				symlink($write_path . $file, $write_path . $filesymlink);
						}
					}
				}
				}
			
	//END OF SYMLINK SECTION		
				
				
                if (!file_exists($write_path . $file)) {
                    $this->error['parse_configs'] = "File (" . $file . ") not written to hard drive!";
                    return(FALSE);
                }
            } elseif (!in_array($file, $provisioner_lib->protected_files)) {
                $this->error['parse_configs'] = "File not written to hard drive!";
                return(FALSE);
            }
        }

        if ($reboot) {
            $provisioner_lib->reboot();
        }
    }

    function display_configs() {

    }
	
	
	
	
	
	

	

	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	

    /**
     * Prepare and then send the data that Provisioner expects, then take what provisioner gives us and do what it says
     * @param array $phone_info Everything from get_phone_info
     * @param bool  $reboot Reboot the Phone after write
     * @param bool  $write  Write out Directory structure.
     */
    function prepare_configs($phone_info, $reboot=TRUE, $write=TRUE) {

        define('PROVISIONER_BASE', PHONE_MODULES_PATH);
        if (file_exists(PHONE_MODULES_PATH . 'autoload.php')) {
            if (!class_exists('ProvisionerConfig')) {
                require(PHONE_MODULES_PATH . 'autoload.php');
            }

            //Load Provisioner
            $class = "endpoint_" . $phone_info['directory'] . "_" . $phone_info['cfg_dir'] . '_phone';
            $base_class = "endpoint_" . $phone_info['directory'] . '_base';
            $master_class = "endpoint_base";
            if (!class_exists($master_class)) {
                ProvisionerConfig::endpointsAutoload($master_class);
            }
            if (!class_exists($base_class)) {
                ProvisionerConfig::endpointsAutoload($base_class);
            }
            if (!class_exists($class)) {
                ProvisionerConfig::endpointsAutoload($class);
            }

            if (class_exists($class)) {
                $provisioner_lib = new $class();

                //Determine if global settings have been overridden
                if ($phone_info['template_id'] > 0) {
                    if (isset($phone_info['template_data_info']['global_settings_override'])) {
                        $settings = unserialize($phone_info['template_data_info']['global_settings_override']);
                    } else {
                        $settings['srvip'] = $this->global_cfg['srvip'];
                        $settings['ntp'] = $this->global_cfg['ntp'];
                        $settings['config_location'] = $this->global_cfg['config_location'];
                        $settings['tz'] = $this->global_cfg['tz'];
                    }
                } else {
                    if (isset($phone_info['global_settings_override'])) {
                        $settings = unserialize($phone_info['global_settings_override']);
                    } else {
                        $settings['srvip'] = $this->global_cfg['srvip'];
                        $settings['ntp'] = $this->global_cfg['ntp'];
                        $settings['config_location'] = $this->global_cfg['config_location'];
                        $settings['tz'] = $this->global_cfg['tz'];
                    }
                }



                //Tell the system who we are and were to find the data.
                $provisioner_lib->root_dir = PHONE_MODULES_PATH;
                $provisioner_lib->engine = 'asterisk';
                $provisioner_lib->engine_location = !empty($this->global_cfg['asterisk_location']) ? $this->global_cfg['asterisk_location'] : 'asterisk';
                $provisioner_lib->system = 'unix';

                //have to because of versions less than php5.3
                $provisioner_lib->brand_name = $phone_info['directory'];
                $provisioner_lib->family_line = $phone_info['cfg_dir'];



                //Phone Model (Please reference family_data.xml in the family directory for a list of recognized models)
                //This has to match word for word. I really need to fix this....
                $provisioner_lib->model = $phone_info['model'];

                //Timezone
                require('timezone.inc');
                try {
                    $provisioner_lib->DateTimeZone = new DateTimeZone($settings['tz']);
                } catch (Exception $e) {
                    $this->error['parse_configs'] = 'Error Returned From Timezone Library: ' . $e->getMessage();
                    return(FALSE);
                }

                $temp = "";
                $template_data = unserialize($phone_info['template_data']);
                $global_user_cfg_data = unserialize($phone_info['global_user_cfg_data']);
                if ($phone_info['template_id'] > 0) {
                    $global_custom_cfg_data = unserialize($phone_info['template_data_info']['global_custom_cfg_data']);
                    //Provide alternate Configuration file instead of the one from the hard drive
                    if (!empty($phone_info['template_data_info']['config_files_override'])) {
                        $temp = unserialize($phone_info['template_data_info']['config_files_override']);
                        foreach ($temp as $list) {
                            $sql = "SELECT original_name,data FROM endpointman_custom_configs WHERE id = " . $list;
                            $res = $this->eda->sql($sql);
                            if (count($res)) {
                                $data = $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
                                $provisioner_lib->config_files_override[$data['original_name']] = $data['data'];
                            }
                        }
                    }
                } else {
                    $global_custom_cfg_data = unserialize($phone_info['global_custom_cfg_data']);
                    //Provide alternate Configuration file instead of the one from the hard drive
                    if (!empty($phone_info['config_files_override'])) {
                        $temp = unserialize($phone_info['config_files_override']);
                        foreach ($temp as $list) {
                            $sql = "SELECT original_name,data FROM endpointman_custom_configs WHERE id = " . $list;
                            $res = $this->eda->sql($sql);
                            if (count($res)) {
                                $data = $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
                                $provisioner_lib->config_files_override[$data['original_name']] = $data['data'];
                            }
                        }
                    }
                }

                if (!empty($global_custom_cfg_data)) {
                    if (array_key_exists('data', $global_custom_cfg_data)) {
                        $global_custom_cfg_ari = $global_custom_cfg_data['ari'];
                        $global_custom_cfg_data = $global_custom_cfg_data['data'];
                    } else {
                        $global_custom_cfg_data = array();
                        $global_custom_cfg_ari = array();
                    }
                }

                $new_template_data = array();
                $line_ops = array();
                if (is_array($global_custom_cfg_data)) {
                    foreach ($global_custom_cfg_data as $key => $data) {
                        //TODO: clean up with reg-exp
                        $full_key = $key;
                        $key = explode('|', $key);
                        $count = count($key);
                        switch ($count) {
                            case 1:
                                if (($this->global_cfg['enable_ari'] == 1) AND (isset($global_custom_cfg_ari[$full_key])) AND (isset($global_user_cfg_data[$full_key]))) {
                                    $new_template_data[$full_key] = $global_user_cfg_data[$full_key];
                                } else {
                                    $new_template_data[$full_key] = $global_custom_cfg_data[$full_key];
                                }
                                break;
                            case 2:
                                $breaks = explode('_', $key[1]);
                                if (($this->global_cfg['enable_ari'] == 1) AND (isset($global_custom_cfg_ari[$full_key])) AND (isset($global_user_cfg_data[$full_key]))) {
                                    $new_template_data['loops'][$breaks[0]][$breaks[2]][$breaks[1]] = $global_user_cfg_data[$full_key];
                                } else {
                                    $new_template_data['loops'][$breaks[0]][$breaks[2]][$breaks[1]] = $global_custom_cfg_data[$full_key];
                                }
                                break;
                            case 3:
                                if (($this->global_cfg['enable_ari'] == 1) AND (isset($global_custom_cfg_ari[$full_key])) AND (isset($global_user_cfg_data[$full_key]))) {
                                    $line_ops[$key[1]][$key[2]] = $global_user_cfg_data[$full_key];
                                } else {
                                    $line_ops[$key[1]][$key[2]] = $global_custom_cfg_data[$full_key];
                                }
                                break;
                        }
                    }
                }

                if (!$write) {
                    $new_template_data['provision']['type'] = 'dynamic';
                    $new_template_data['provision']['protocol'] = 'http';
                    $new_template_data['provision']['path'] = rtrim($settings['srvip'] . dirname($_SERVER['REQUEST_URI']) . '/', '/');
                    $new_template_data['provision']['encryption'] = FALSE;
                } else {
                    $new_template_data['provision']['type'] = 'file';
                    $new_template_data['provision']['protocol'] = 'tftp';
                    $new_template_data['provision']['path'] = $settings['srvip'];
                    $new_template_data['provision']['encryption'] = FALSE;
                }

                $new_template_data['ntp'] = $settings['ntp'];

                //Overwrite all specific settings variables now
                if (!empty($phone_info['specific_settings'])) {
                    $specific_settings = unserialize($phone_info['specific_settings']);
                    $specific_settings = is_array($specific_settings) ? $specific_settings : array();
                } else {
                    $specific_settings = array();
                }

                //Set Variables according to the template_data files included. We can include different template.xml files within family_data.xml also one can create
                //template_data_custom.xml which will get included or template_data_<model_name>_custom.xml which will also get included
                //line 'global' will set variables that aren't line dependant


                $provisioner_lib->settings = $new_template_data;

				
				
//LOAD FEATURECODES					
	$featurecodes=$this->getFeatureCodes();
	$getSysadminSettings=$this->getSysadminSettings();


	

//Calculate Voicemail Number
	if (!$featurecodes[myvoicemail][customcode]) {
		$provisioner_lib->settings['myvoicemail'] = $featurecodes[myvoicemail][defaultcode];
	}
	else  {
		$provisioner_lib->settings['myvoicemail'] = $featurecodes[myvoicemail][customcode];
	}
//Calculate DND_ON_NUMBER
	if (!$featurecodes[dnd_on][customcode]) {
		$provisioner_lib->settings['dnd_on'] = $featurecodes[dnd_on][defaultcode];
	}
	else  {
		$provisioner_lib->settings['dnd_on'] = $featurecodes[dnd_on][customcode];
	}	
//Calculate DND_OFF_NUMBER
	if (!$featurecodes[dnd_off][customcode]) {
		$provisioner_lib->settings['dnd_off'] = $featurecodes[dnd_off][defaultcode];
	}
	else  {
		$provisioner_lib->settings['dnd_off'] = $featurecodes[dnd_off][customcode];
	}
//Calculate DND_TOGGLE_NUMBER
	if (!$featurecodes[dnd_toggle][customcode]) {
		$provisioner_lib->settings['dnd_toggle'] = $featurecodes[dnd_toggle][defaultcode];
	}
	else  {
		$provisioner_lib->settings['dnd_toggle'] = $featurecodes[dnd_toggle][customcode];
	}		
				
				
				
				
				
                //Loop through Lines!
                $li = 0;
                foreach ($phone_info['line'] as $line) {
                    $line_options = is_array($line_ops[$line['line']]) ? $line_ops[$line['line']] : array();
                    $line_statics = array(
					'line' => $line['line'],
					'username' => $line['ext'],
					'authname' => $line['ext'],
					'secret' => $line['secret'],
					'displayname' => $line['description'],
					'server_host' => $this->global_cfg['srvip'],
					'server_port' => $line['server_port'],
					'proto' => $line['proto'],
					'user_extension' => $line['user_extension'],
					'extension' => $line['ext'],
					'allowedcodec' => $line['allowedcodec'],
					'forcerport' => $line['forcerport'],
					'media_encryption' => $line['media_encryption'],
					'sipdriver' => $line['sipdriver'],
					'transport' => $line['transport'],
					'trustrpid' => $line['trustrpid'],
					'callerid' => $line['callerid'],
					'encryption' => $line['encryption'],							
					'tech' => $line['tech'],
					'ipei' => $line['ipei'],
					'myvoicemail' => $provisioner_lib->settings['myvoicemail'],
					'dnd_on' => $provisioner_lib->settings['dnd_on'],
					'dnd_off' => $provisioner_lib->settings['dnd_off'],
					'dnd_toggle' => $provisioner_lib->settings['dnd_toggle'],
					'primtimeserver' => $this->global_cfg['ntp'],
					'globaladminpassword' => $this->global_cfg['adminpass'],
					'globaluserpassword' => $this->global_cfg['userpass'],
					'provisuser' => $getSysadminSettings[provisuser][value],
					'provispass' => $getSysadminSettings[provispass][value],
					'sslhpro' => $getSysadminSettings[sslhpro][value],
					'hpro' => $getSysadminSettings[hpro][value]
					);
	

					

					//STATIC LINE SETTINGS WITHOUT LOOP (accXSetting)

					$provisioner_lib->settings['acc' . $line[line] . 'secret'] = $line['secret'];
					$provisioner_lib->settings['acc' . $line[line] . 'displayname'] = $line['description'];
					$provisioner_lib->settings['acc' . $line[line] . 'user_extension'] = $line['user_extension'];
					$provisioner_lib->settings['acc' . $line[line] . 'username'] = $line['ext'];
					$provisioner_lib->settings['acc' . $line[line] . 'authname'] = $line['ext'];
					$provisioner_lib->settings['acc' . $line[line] . 'extension'] = $line['extension'];
					$provisioner_lib->settings['acc' . $line[line] . 'allowedcodec'] = $line['allowedcodec'];
					$provisioner_lib->settings['acc' . $line[line] . 'forcerport'] = $line['forcerport'];
					$provisioner_lib->settings['acc' . $line[line] . 'media_encryption'] = $line['media_encryption'];
					$provisioner_lib->settings['acc' . $line[line] . 'sipdriver'] = $line['sipdriver'];
					$provisioner_lib->settings['acc' . $line[line] . 'transport'] = $line['transport'];
					$provisioner_lib->settings['acc' . $line[line] . 'trustrpid'] = $line['trustrpid'];
					$provisioner_lib->settings['acc' . $line[line] . 'callerid'] = $line['callerid'];
					$provisioner_lib->settings['acc' . $line[line] . 'encryption'] = $line['encryption'];
					$provisioner_lib->settings['acc' . $line[line] . 'server_port'] = $line['server_port'];
					$provisioner_lib->settings['acc' . $line[line] . 'ipei'] = $line['ipei'];
					$provisioner_lib->settings['server_host'] = $this->global_cfg['srvip'];
					$provisioner_lib->settings['primtimeserver'] = $this->global_cfg['ntp'];
					$provisioner_lib->settings['globaladminpassword'] = $this->global_cfg['adminpass'];
					$provisioner_lib->settings['globaluserpassword'] = $this->global_cfg['userpass'];
					$provisioner_lib->settings['provisuser'] = $getSysadminSettings[provisuser][value];
					$provisioner_lib->settings['provispass'] = $getSysadminSettings[provispass][value];
					$provisioner_lib->settings['sslhpro'] = $getSysadminSettings[sslhpro][value];
					$provisioner_lib->settings['hpro'] = $getSysadminSettings[hpro][value];
					
					$provisioner_lib->settings['timestamp'] = time();

		
		
				//THIS ARE EXTRA VALUES FOR GRANDSTREAM
					
					//Add loop Values for Grandstream (Tested with Analog HT8XX Adapter)
						
						//PROTO UPD/TCP/TLS
						if ($line['proto'] == "tcp") {
							$line_statics[gsproto] = '1';
						}
						if ($line['proto'] == "udp") {
							$line_statics[gsproto] = '0';
						}					
						if ($line['proto'] == "tls") {
							$line_statics[gsproto] = '2';
						}
						
						
						//GRANDSTREAM SRTP YES/NO
	
						if ($line['encryption'] == "yes" || $line['media_encryption'] == "sdes" ) {
							$line_statics[gsSRTP] = '1';
						}
						if ($line['encryption'] == "no" || $line['media_encryption'] == "no" ) {
							$line_statics[gsSRTP] = '0';
						}
						
		
					//Add static Values for Grandstream (Tested with Analog HT8XX Adapter)
					
						$provisioner_lib->settings['acc' . $line[line] . 'gsproto'] = $line_statics[gsproto];
						$provisioner_lib->settings['acc' . $line[line] . 'gsSRTP'] = $line_statics[gsSRTP];
					
				//THIS ARE EXTRA VALUES FOR YEALINK
					
					//Add loop Values for Yealink
						
						//PROTO UPD/TCP/TLS
						if ($line['proto'] == "tcp") {
							$line_statics[yealinktransport] = '1';
						}
						if ($line['proto'] == "udp") {
							$line_statics[yealinktransport] = '0';
						}					
						if ($line['proto'] == "tls") {
							$line_statics[yealinktransport] = '2';
						}
						
						
						//Yealink SRTP YES/NO
	
						if ($line['encryption'] == "yes" || $line['media_encryption'] == "sdes" ) {
							$line_statics[yealinksrtp] = '2';
						}
						if ($line['encryption'] == "no" || $line['media_encryption'] == "no" ) {
							$line_statics[yealinksrtp] = '0';
						}
						
		
					//Add static Values for Grandstream (Tested with Analog HT8XX Adapter)
					
						$provisioner_lib->settings['acc' . $line[line] . 'yealinktransport'] = $line_statics[gsproto];
						$provisioner_lib->settings['acc' . $line[line] . 'yealinksrtp'] = $line_statics[gsSRTP];
					
					
					
			//MERGE ARRAYS
					
					
					
                    $provisioner_lib->settings['line'][$li] = array_merge($line_options, $line_statics);
                    $li++;
                }

                if (array_key_exists('data', $specific_settings)) {
                   foreach ($specific_settings['data'] as $key => $data) {
                       $default_exp = preg_split("/\|/i", $key);
                        if (isset($default_exp[2])) {
                            //lineloop
                            $var = $default_exp[2];
                            $line = $default_exp[1];
                            $loc = $this->arraysearchrecursive($line, $provisioner_lib->settings['line'], 'line');
                            if ($loc !== FALSE) {
                                $k = $loc[0];
                                $provisioner_lib->settings['line'][$k][$var] = $data;
                            } else {
                                //Adding a new line-ish type options
                                if (isset($specific_settings['data']['line|' . $line . '|line_enabled'])) {
                                    $lastkey = array_pop(array_keys($provisioner_lib->settings['line']));
                                    $lastkey++;
                                    $provisioner_lib->settings['line'][$lastkey]['line'] = $line;
                                    $provisioner_lib->settings['line'][$lastkey][$var] = $data;
                                }
                            }
                        } else {
                            switch ($key) {
                                case "connection_type":
                                    $provisioner_lib->settings['network'][$key] = $data;
                                    break;
                                case "ip4_address":
                                    $provisioner_lib->settings['network']['ipv4'] = $data;
                                    break;
                                case "ip6_address":
                                    $provisioner_lib->settings['network']['ipv6'] = $data;
                                    break;
                                case "subnet_mask":
                                    $provisioner_lib->settings['network']['subnet'] = $data;
                                    break;
                                case "gateway_address":
                                    $provisioner_lib->settings['network']['gateway'] = $data;
                                    break;
                                case "primary_dns":
                                    $provisioner_lib->settings['network'][$key] = $data;
                                    break;
                                default:
                                    $provisioner_lib->settings[$key] = $data;
                                    break;
                            }
                        }
                    }
                }

                $provisioner_lib->settings['mac'] = $phone_info['mac'];
                $provisioner_lib->mac = $phone_info['mac'];

                //Setting a line variable here...these aren't defined in the template_data.xml file yet. however they will still be parsed
                //and if they have defaults assigned in a future template_data.xml or in the config file using pipes (|) those will be used, pipes take precedence
                $provisioner_lib->processor_info = "EndPoint Manager Version " . $this->global_cfg['version'];
				

                // Because every brand is an extension (eventually) of endpoint, you know this function will exist regardless of who it is
                //Start timer
                $time_start = microtime(true);

                $provisioner_lib->debug = TRUE;

                try {
                    $returned_data = $provisioner_lib->generate_all_files();
                } catch (Exception $e) {
                    $this->error['prepare_configs'] = 'Error Returned From Provisioner Library: ' . $e->getMessage();
                    return(FALSE);
                }
                //print_r($provisioner_lib->debug_return);
                //End timer
                $time_end = microtime(true);
                $time = $time_end - $time_start;
                if ($time > 360) {
                    $this->error['generate_time'] = "It took an awfully long time to generate configs...(" . round($time, 2) . " seconds)";
                }
                if ($write) {
                    $this->write_configs($provisioner_lib, $reboot, $settings['config_location'], $phone_info, $returned_data);
                } else {
                    return ($returned_data);
                }
                return(TRUE);
            } else {
                $this->error['parse_configs'] = "Can't Load \"" . $class . "\" Class!";
                return(FALSE);
            }
        } else {
            $this->error['parse_configs'] = "Can't Load the Autoloader!";
            return(FALSE);
        }
    }

    /**
     * Check for new firmware on the servers
     * @param int $id Product ID
     * @return bool True on yes False on no
     */
    function firmware_update_check($id=NULL) {
        $sql = "SELECT * FROM  endpointman_product_list WHERE  id ='" . $id . "'";
        $row = $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

        $sql = "SELECT directory FROM  endpointman_brand_list WHERE id =" . $row['brand'];
        $brand_directory = $this->eda->sql($sql, 'getOne');

        //config drive unknown!
        if ($row['cfg_dir'] == "") {
            return FALSE;
        } else {
            $temp = $this->file2json(PHONE_MODULES_PATH . "endpoint/" . $brand_directory . "/" . $row['cfg_dir'] . "/family_data.json");
            if ((array_key_exists('data', $temp)) AND (!is_array($temp['data']['firmware_ver']))) {
                if ($row['firmware_vers'] < $temp['data']['firmware_ver']) {
                    return $temp;
                } else {
                    return FALSE;
                }
            } else {
                return FALSE;
            }
        }
    }

    /**
     * Check to see the status of the firmware locally (installed or not)
     * @param int $id
     * @return string
     */
    function firmware_local_check($id=NULL) {
        $sql = "SELECT * FROM  endpointman_product_list WHERE hidden = 0 AND id ='" . $id . "'";
        $res = $this->eda->sql($sql);

        if (count($res)) {
            $row = $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

            $sql = "SELECT directory FROM  endpointman_brand_list WHERE hidden = 0 AND id =" . $row['brand'];
            $brand_directory = $this->eda->sql($sql, 'getOne');

            //config drive unknown!
            if ($row['cfg_dir'] == "") {
                return("nothing");
            } else {
                $temp = $this->file2json(PHONE_MODULES_PATH . "endpoint/" . $brand_directory . "/" . $row['cfg_dir'] . "/family_data.json");

                if ($temp['data']['firmware_ver'] != NULL) {
                    if ($row['firmware_vers'] == "") {
                        return("install");
                    } else {
                        return("remove");
                    }
                } else {
                    return("nothing");
                }
            }
        } else {
            return("nothing");
        }
    }

    /**
     * Remove firmware from the Hard Drive
     * @param int $id Product ID
     */
    function remove_firmware($id) {
        $sql = "SELECT firmware_files FROM  endpointman_product_list WHERE  id =" . $id;
        $files = $this->eda->sql($sql, 'getOne');

        $file_list = explode(",", $files);
        $i = 0;
        foreach ($file_list as $file) {
            if (file_exists($this->global_cfg['config_location'] . $file)) {
                unlink($this->global_cfg['config_location'] . $file);
            }
        }
        $sql = 'UPDATE endpointman_product_list SET firmware_files = "", firmware_vers = "" WHERE id = ' . $id;
        $this->eda->sql($sql);
    }

    function sys_get_temp_dir() {
        if (!empty($_ENV['TMP'])) {
            return realpath($_ENV['TMP']);
        }
        if (!empty($_ENV['TMPDIR'])) {
            return realpath($_ENV['TMPDIR']);
        }
        if (!empty($_ENV['TEMP'])) {
            return realpath($_ENV['TEMP']);
        }
        $tempfile = tempnam(uniqid(rand(), TRUE), '');
        if (file_exists($tempfile)) {
            unlink($tempfile);
            return realpath(dirname($tempfile));
        }
    }

    /**
     * Install Firmware for the specified Product Line
     * @param <type> $product_id Product ID
     */
    function install_firmware($product_id) {
        $temp_directory = $this->sys_get_temp_dir() . "/epm_temp/";
        $sql = 'SELECT endpointman_product_list.*, endpointman_brand_list.directory FROM endpointman_product_list, endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id = ' . $product_id;
        $row = $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
        $json_data = $this->file2json(PHONE_MODULES_PATH . "endpoint/" . $row['directory'] . "/" . $row['cfg_dir'] . "/family_data.json");
        if ($json_data['data']['firmware_ver'] > $row['firmware_vers']) {
            if (!file_exists($temp_directory)) {
                mkdir($temp_directory);
            }
            $md5_xml = $json_data['data']['firmware_md5sum'];
            $firmware_pkg = $json_data['data']['firmware_pkg'];
            if (file_exists($temp_directory . $firmware_pkg)) {
                $md5_pkg = md5_file($temp_directory . $firmware_pkg);
                if ($md5_xml = $md5_pkg) {
                    echo "Skipping download....<br />";
                } else {
                    echo "<div style='float: left'>Downloading firmware...</div><div style='float: left' id=\"DivExample\">.</div>";
                    $this->download_file_with_progress_bar(UPDATE_PATH . $row['directory'] . "/" . $firmware_pkg, $temp_directory . $firmware_pkg);
                    echo "<script type='text/javascript'>document.getElementById('DivExample').innerHTML='Done!';</script><br />";
                    $md5_pkg = md5_file($temp_directory . $firmware_pkg);
                }
            } else {
                echo "<div style='float: left'>Downloading firmware...</div><div style='float: left' id=\"DivExample\">.</div>";
                $this->download_file_with_progress_bar(UPDATE_PATH . $row['directory'] . "/" . $firmware_pkg, $temp_directory . $firmware_pkg);
                echo "<script type='text/javascript'>document.getElementById('DivExample').innerHTML='Done!';</script><br />";
                $md5_pkg = md5_file($temp_directory . $firmware_pkg);
            }

            echo "Checking MD5sum of Package...";
            if ($md5_xml == $md5_pkg) {
                echo "Matches!<br />";
                if (file_exists($temp_directory . $row['directory'] . "/" . $row['cfg_dir'] . "/firmware")) {
                    $this->rmrf($temp_directory . $row['directory'] . "/" . $row['cfg_dir'] . "/firmware");
                }
                mkdir($temp_directory . $row['directory'] . "/" . $row['cfg_dir'] . "/firmware", 0777, TRUE);
                echo "Installing Firmware...";
                exec("tar -xvf " . $temp_directory . $firmware_pkg . " -C " . $temp_directory . $row['directory'] . "/" . $row['cfg_dir']);
                $i = 0;
                foreach (glob($temp_directory . $row['directory'] . "/" . $row['cfg_dir'] . "/firmware/*") as $filename) {
                    $file = basename($filename);
                    $list[$i] = $file;
                    if (!@copy($filename, $this->global_cfg['config_location'] . $file)) {
                        echo "<br />--Failed To Copy $file...";
                        $copy_error = TRUE;
                    } elseif ($this->global_cfg['debug']) {
                        echo"<br />--Copied " . $file . " to " . $this->global_cfg['config_location'];
                    }
                    $i++;
                }

                $this->rmrf($temp_directory . $row['directory']);
                $list = implode(",", $list);
                $sql = "UPDATE endpointman_product_list SET firmware_vers = '" . $json_data['data']['firmware_ver'] . "', firmware_files = '" . $list . "' WHERE id = " . $row['id'];
                $this->eda->sql($sql);
                if (isset($copy_error)) {
                    echo "<BR />Copy Error Detected! Aborting Install!";
                    $this->remove_firmware($product_id);
                    echo "<BR />Please Check Directory/Permissions";
                } else {
                    echo "<BR />Done!";
                }
            } else {
                echo "<BR />Firmware MD5 didn't match<br />";
            }
        } else {
            echo "<BR />Your Firmware is already up to date";
        }
    }

    function download_json($location, $directory=NULL) {
        $temp_directory = $this->sys_get_temp_dir() . "/epm_temp/";
        if (!isset($directory)) {
            $destination_file = PHONE_MODULES_PATH . 'endpoint/master.json';
            $directory = "master";
        } else {
            if (!file_exists(PHONE_MODULES_PATH . '/' . $directory)) {
                mkdir(PHONE_MODULES_PATH . '/' . $directory, 0775, TRUE);
            }
            $destination_file = PHONE_MODULES_PATH . '/' . $directory . '/brand_data.json';
        }
        $temp_file = $temp_directory . $directory . '.json';
        file_exists(dirname($temp_file)) ? '' : mkdir(dirname($temp_file));

        if ($this->download_file($location, $temp_file)) {
            $handle = fopen($temp_file, "rb");
            $contents = fread($handle, filesize($temp_file));
            fclose($handle);

            $a = $this->validate_json($contents);
            if ($a === FALSE) {
                //Error with the internet....ABORRRTTTT THEEEEE DOWNLOAAAAADDDDDDDD! SCOTTYYYY!;
                unlink($temp_file);
                return(FALSE);
            } else {
                rename($temp_file, $destination_file);
                chmod($destination_file, 0775);
                return(TRUE);
            }
        } else {
            return(FALSE);
        }
    }

    /**
     * Check for new packges for brands. These packages will include phone models and such which the user can remove if they want
     * This function will alos auto-update the provisioner.net library incase anything has changed
     * @return array An array of all the brands/products/models and information about what's  enabled, installed or otherwise
     */
    function update_check() {
        $temp_location = $this->sys_get_temp_dir() . "/epm_temp/";
        if (!$this->global_cfg['use_repo']) {
            $master_result = $this->download_file(UPDATE_PATH . "master.json", PHONE_MODULES_PATH . "endpoint/master.json");

            if (!$master_result) {
                $this->error['brand_update_check_master'] = "Not able to connect to repository. Using local master file instead.";
            }

            $temp = $this->file2json(PHONE_MODULES_PATH . 'endpoint/master.json');
            $endpoint_package = $temp['data']['package'];
            $endpoint_last_mod = $temp['data']['last_modified'];

            $sql = "SELECT value FROM endpointman_global_vars WHERE var_name LIKE 'endpoint_vers'";
            $data = $this->eda->sql($sql, 'getOne');

            $contents = file_get_contents(UPDATE_PATH . "/update_status");


            if ($contents != '1') {
                if (($data == "") OR ($data <= $endpoint_last_mod)) {
                    if ((!$master_result) OR (!$this->download_file(UPDATE_PATH . '/' . $endpoint_package, $temp_location . $endpoint_package))) {
                        $this->error['brand_update_check_json'] = "<br/>Not able to connect to repository. Using local Provisioner.net Package";
                    } else {
                        exec("tar -xvf " . $temp_location . $endpoint_package . " -C " . $temp_location);

                        if (!file_exists(PHONE_MODULES_PATH . "endpoint")) {
                            mkdir(PHONE_MODULES_PATH . "endpoint");
                        }

                        //TODO: Automate this somehow...

                        rename($temp_location . "setup.php", PHONE_MODULES_PATH . "autoload.php");
                        rename($temp_location . "endpoint/base.php", PHONE_MODULES_PATH . "endpoint/base.php");
                        rename($temp_location . "endpoint/global_template_data.json", PHONE_MODULES_PATH . "endpoint/global_template_data.json");

                        $sql = "UPDATE endpointman_global_vars SET value = '" . $endpoint_last_mod . "' WHERE var_name = 'endpoint_vers'";
                        $this->eda->sql($sql);
                    }
                }


                $out = $temp['data']['brands'];

                //Assume that if we can't connect and find the master.xml file then why should we try to find every other file.
                if ($master_result) {
                    $row = $this->eda->sql('SELECT * FROM  endpointman_brand_list WHERE id > 0', 'getAll', DB_FETCHMODE_ASSOC);

                    foreach ($out as $data) {

                        $local = $this->eda->sql("SELECT local FROM endpointman_brand_list WHERE  directory =  '" . $data['directory'] . "'", 'getOne');

                        if (!$local) {
                            $result = $this->download_file(UPDATE_PATH . $data['directory'] . "/" . $data['directory'] . ".json", PHONE_MODULES_PATH . "endpoint/" . $data['directory'] . "/brand_data.json");
                            if (!$result) {
                                $this->error['brand_update_check'] = "<br/>Not able to connect to repository. Using local brand [" . $data['name'] . "] file instead.";
                            }
                        }

                        if (file_exists(PHONE_MODULES_PATH . "endpoint/" . $data['directory'] . "/brand_data.json")) {
                            $temp = $this->file2json(PHONE_MODULES_PATH . "endpoint/" . $data['directory'] . "/brand_data.json");

                            $temp = $temp['data']['brands'];

														if (array_key_exists('oui_list', $temp)) {
                            	foreach ($temp['oui_list'] as $oui) {
                              	  $sql = "REPLACE INTO endpointman_oui_list (`oui`, `brand`, `custom`) VALUES ('" . $oui . "', '" . $temp['brand_id'] . "', '0')";
                               		$this->eda->sql($sql);
															}
														}

                            $brand_name = $temp['directory'];
                            $version[$brand_name] = $temp['last_modified'];

                            $last_mod = "";

                            foreach ($temp['family_list'] as $list) {
                                $last_mod = max($last_mod, $list['last_modified']);
                            }
                            $last_mod = max($last_mod, $version[$brand_name]);

                            $version[$brand_name] = $last_mod;

                            if (!($this->arraysearchrecursive($brand_name, $row, 'directory'))) {
                                //insert row
                                $sql = "INSERT INTO endpointman_brand_list (id, name, directory, cfg_ver) VALUES ('" . $temp['brand_id'] . "', '" . $temp['name'] . "', '" . $temp['directory'] . "', '" . $version[$brand_name] . "')";
                                $this->eda->sql($sql);
                            } else {
                                //in database already!
                            }
                        } else {
                            $this->error['brand_update_check_local_file'] = "<br/>Error: No Local File for " . $data['name'] . "!<br /><br />Learn how to manually upload packages here (it's easy!): <a href='http://wiki.provisioner.net/index.php/Endpoint_manager_manual_upload' target='_blank'>Click Here!</a>";
                        }
                    }

                    foreach ($row as $ava_brands) {
                        $key = $this->arraysearchrecursive($ava_brands['directory'], $out, 'directory');
                        if ($key === FALSE) {
                            $this->remove_brand($ava_brands['id']);
                        } else {
                            $key = $key[0];

                            $brand_name = $ava_brands['directory'];

                            //TODO: This seems old
                            if ($ava_brands['cfg_ver'] < $version[$brand_name]) {
                                $out[$key]['update'] = 1;
                                $out[$key]['update_vers'] = $version[$brand_name];
                            } else {
                                $out[$key]['update'] = NULL;
                            }
                        }
                    }
                } else {
                    $this->error['brand_update_check_master_file'] = "<br/>Aborting Brand Downloads. Can't Get Master File, Assuming Timeout Issues!<br /><br />Learn how to manually upload packages here (it's easy!): <a href='http://wiki.provisioner.net/index.php/Endpoint_manager_manual_upload' target='_blank'>Click Here!</a>";
                }

                return $out;
            } else {
                $this->error['remote_server'] = "The Remote Server Is Currently Syncing With the Master Server, Please try again later";
            }
        } else {
            $o = getcwd();
            chdir(dirname(PHONE_MODULES_PATH));
            $path = $this->has_git();
            exec($path . ' git pull', $output);
            //exec($path . ' git checkout master', $output); //Why am I doing this?
            chdir($o);
            $temp = $this->file2json(PHONE_MODULES_PATH . 'endpoint/master.json');
            $endpoint_package = $temp['data']['package'];
            $endpoint_last_mod = $temp['data']['last_modified'];

            $sql = "UPDATE endpointman_global_vars SET value = '" . $endpoint_last_mod . "' WHERE var_name = 'endpoint_vers'";
            $this->eda->sql($sql);

            $out = $temp['data']['brands'];

            $row = $this->eda->sql('SELECT * FROM  endpointman_brand_list WHERE id > 0', 'getAll', DB_FETCHMODE_ASSOC);

            foreach ($out as $data) {
                $temp = $this->file2json(PHONE_MODULES_PATH . 'endpoint/' . $data['directory'] . '/brand_data.json');
                if (key_exists('directory', $temp['data']['brands'])) {

                    //Pull in all variables
                    $directory = $temp['data']['brands']['directory'];
                    $brand_name = $temp['data']['brands']['name'];
                    $brand_id = $temp['data']['brands']['brand_id'];
                    $brand_version = $temp['data']['brands']['last_modified'];

                    $b_data = $this->eda->sql("SELECT id FROM endpointman_brand_list WHERE id = '" . $brand_id . "'", 'getOne');
                    if ($b_data) {
                        $sql = "UPDATE endpointman_brand_list SET local = '1', name = '" . $brand_name . "', cfg_ver = '" . $brand_version . "', installed = 1, hidden = 0 WHERE id = " . $brand_id;
                        $this->eda->sql($sql);
                    } else {
                        $sql = "INSERT INTO endpointman_brand_list (id, name, directory, cfg_ver, local, installed) VALUES ('" . $brand_id . "', '" . $brand_name . "', '" . $directory . "', '" . $brand_version . "', '1', '1')";
                        $this->eda->sql($sql);
                    }

                    $last_mod = "";
                    foreach ($temp['data']['brands']['family_list'] as $family_list) {
                        $last_mod = max($last_mod, $family_list['last_modified']);

                        $family_line_xml = $this->file2json(PHONE_MODULES_PATH . '/endpoint/' . $directory . '/' . $family_list['directory'] . '/family_data.json');

                        $family_line_xml['data']['last_modified'] = isset($family_line_xml['data']['last_modified']) ? $family_line_xml['data']['last_modified'] : '';

                        /* DONT DO THIS YET
                          $require_firmware = NULL;
                          if ((key_exists('require_firmware', $family_line_xml['data'])) && ($remote) && ($family_line_xml['data']['require_firmware'] == "TRUE")) {
                          echo "Firmware Requirment Detected!..........<br/>";
                          $this->install_firmware($family_line_xml['data']['id']);
                          }
                         *
                         */

                        $data = $this->eda->sql("SELECT id FROM endpointman_product_list WHERE id='" . $brand_id . $family_line_xml['data']['id'] . "'", 'getOne');
                        $short_name = preg_replace("/\[(.*?)\]/si", "", $family_line_xml['data']['name']);
                        if ($data) {
                            $sql = "UPDATE endpointman_product_list SET short_name = '" . $short_name . "', long_name = '" . $family_line_xml['data']['name'] . "', cfg_ver = '" . $family_line_xml['data']['version'] . "', config_files='" . $family_line_xml['data']['configuration_files'] . "' WHERE id = '" . $brand_id . $family_line_xml['data']['id'] . "'";
                        } else {
                            $sql = "INSERT INTO endpointman_product_list (`id`, `brand`, `short_name`, `long_name`, `cfg_dir`, `cfg_ver`, `config_files`, `hidden`) VALUES ('" . $brand_id . $family_line_xml['data']['id'] . "', '" . $brand_id . "', '" . $short_name . "', '" . $family_line_xml['data']['name'] . "', '" . $family_line_xml['data']['directory'] . "', '" . $family_line_xml['data']['last_modified'] . "','" . $family_line_xml['data']['configuration_files'] . "', '0')";
                        }
                        $this->eda->sql($sql);

                        foreach ($family_line_xml['data']['model_list'] as $model_list) {
                            $template_list = implode(",", $model_list['template_data']);

                            $m_data = $this->eda->sql("SELECT id FROM endpointman_model_list WHERE id='" . $brand_id . $family_line_xml['data']['id'] . $model_list['id'] . "'", 'getone');
                            if ($m_data) {
                                $sql = "UPDATE endpointman_model_list SET max_lines = '" . $model_list['lines'] . "', model = '" . $model_list['model'] . "', template_list = '" . $template_list . "' WHERE id = '" . $brand_id . $family_line_xml['data']['id'] . $model_list['id'] . "'";
                            } else {
                                $sql = "INSERT INTO endpointman_model_list (`id`, `brand`, `model`, `max_lines`, `product_id`, `template_list`, `enabled`, `hidden`) VALUES ('" . $brand_id . $family_line_xml['data']['id'] . $model_list['id'] . "', '" . $brand_id . "', '" . $model_list['model'] . "', '" . $model_list['lines'] . "', '" . $brand_id . $family_line_xml['data']['id'] . "', '" . $template_list . "', '0', '0')";
                            }
                            $this->eda->sql($sql);

                            if (!$this->sync_model($brand_id . $family_line_xml['data']['id'] . $model_list['id'])) {
                                echo "System Error in Sync Model Function, Load Failure!" . $model_list['model'];
                            }
                        }
                        //Phone Models Move Here
                        $family_id = $brand_id . $family_line_xml['data']['id'];
                        $sql = "SELECT * FROM endpointman_model_list WHERE product_id = " . $family_id;
                        $products = $this->eda->sql($sql, 'getall', DB_FETCHMODE_ASSOC);
                        foreach ($products as $data) {
                            if (!$this->arraysearchrecursive($data['model'], $family_line_xml['data']['model_list'], 'model')) {
                                echo "Moving/Removing Model '" . $data['model'] . "' not present in JSON file......<br />";
                                $model_name = $data['model'];
                                $sql = 'DELETE FROM endpointman_model_list WHERE id = ' . $data['id'];
                                $this->eda->sql($sql);
                                $sql = "SELECT id FROM endpointman_model_list WHERE model LIKE '" . $model_name . "'";
                                $new_model_id = $this->eda->sql($sql, 'getOne');
                                if ($new_model_id) {
                                    $sql = "UPDATE  endpointman_mac_list SET  model =  '" . $new_model_id . "' WHERE  model = '" . $data['id'] . "'";
                                } else {
                                    $sql = "UPDATE  endpointman_mac_list SET  model =  '0' WHERE  model = '" . $data['id'] . "'";
                                }
                                $this->eda->sql($sql);
                            }
                        }
                    }
                    foreach ($temp['data']['brands']['oui_list'] as $oui) {
                        $sql = "REPLACE INTO endpointman_oui_list (`oui`, `brand`, `custom`) VALUES ('" . $oui . "', '" . $brand_id . "', '0')";
                        $this->eda->sql($sql);
                    }
                }
            }
        }
    }

    /**
     * This will download the xml & brand package remotely
     * @param integer $id Brand ID
     */
    function download_brand($id) {
        if (!$this->global_cfg['use_repo']) {
            $temp_directory = $this->sys_get_temp_dir() . "/epm_temp/";
	    if (!file_exists($temp_directory)) {
		echo "Creating EPM temp directory <br />";
		mkdir($temp_directory);
	    }
            $row = $this->eda->sql('SELECT * FROM  endpointman_brand_list WHERE id =' . $id, 'getAll', DB_FETCHMODE_ASSOC);
            echo "Downloading Brand JSON.....";
            $result = $this->download_file(UPDATE_PATH . $row[0]['directory'] . "/" . $row[0]['directory'] . ".json", PHONE_MODULES_PATH . "endpoint/" . $row[0]['directory'] . "/brand_data.json");

            if ($result) {
                echo "Done!<br/>";

                $temp = $this->file2json(PHONE_MODULES_PATH . 'endpoint/' . $row[0]['directory'] . '/brand_data.json');
                $package = $temp['data']['brands']['package'];

                echo "<div style='float: left'>Downloading Brand Package...</div><div style='float: left' id=\"DivExample\">.</div>";
                $this->download_file_with_progress_bar(UPDATE_PATH . $row[0]['directory'] . '/' . $package, $temp_directory . $package);
                echo "<script type='text/javascript'>document.getElementById('DivExample').innerHTML='Done!';</script><br />";

                if (file_exists($temp_directory . $package)) {
                    $md5_xml = $temp['data']['brands']['md5sum'];
                    $md5_pkg = md5_file($temp_directory . $package);

                    echo "Checking MD5sum of Package....";
                    if ($md5_xml == $md5_pkg) {
                        echo "Done!<br/>";
                        echo "Extracting Tarball........";
                        exec("tar -xvf " . $temp_directory . $package . " -C " . $temp_directory);
                        echo "Done!<br />";

                        //Update File in the temp directory
                        copy(PHONE_MODULES_PATH . 'endpoint/' . $row[0]['directory'] . '/brand_data.json', $temp_directory . $row[0]['directory'] . '/brand_data.json');

                        $this->update_brand($row[0]['directory'], TRUE);
                    } else {
                        echo "MD5 Did not match!";
                    }
                } else {
                    echo "Can't Find Downloaded File!";
                }
            } else {
                echo "<br/>Error Connecting to the Package Repository. Module not installed. Please Try again later.<br/>You Can Also Manually Update The Repository By Downloading Files here: <a href='http://www.provisioner.net/releases3' target='_blank'>Release Repo</a><br />Then Use Manual Upload in Advanced Settings";
            }
        } else {
            echo "Installing brands is disabled while in repo mode!";
        }
    }

    /**
     * This will install or updated a brand package (which is the same thing to this)
     * Still needs way to determine when models move...perhaps another function?
     */
    function update_brand($package, $remote=TRUE) {
	if ($this->global_cfg['debug']) echo "update_brand(): Debug is set to ".$this->global_cfg['debug']." <br />";
	$temp_directory = $this->sys_get_temp_dir() . "/epm_temp/";
        if ($this->global_cfg['debug']) echo "Processing ".$temp_directory.$package."/brand_data.json... <br />";
        if (file_exists($temp_directory . $package . '/brand_data.json')) {
            $temp = $this->file2json($temp_directory . $package . '/brand_data.json');
            if (key_exists('directory', $temp['data']['brands'])) {
                echo "Appears to be a valid Provisioner.net JSON file.....Continuing<br />";

                //Pull in all variables
                $directory = $temp['data']['brands']['directory'];
                $brand_name = $temp['data']['brands']['name'];
                $brand_id = $temp['data']['brands']['brand_id'];
                $brand_version = $temp['data']['brands']['last_modified'];

                //create directory structure and move files
                echo "Creating Directory Structure for Brand '".$brand_name."' and Moving Files ..";

                if (!file_exists(PHONE_MODULES_PATH . "endpoint/" . $directory)) {
                    mkdir(PHONE_MODULES_PATH . "endpoint/" . $directory);
                }

                $dir_iterator = new RecursiveDirectoryIterator($temp_directory . $directory . "/");
                $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
                foreach ($iterator as $file) {
                    if (is_dir($file)) {
                        $dir = str_replace($temp_directory . $directory . "/", "", $file);
                        if (!file_exists(PHONE_MODULES_PATH . "endpoint/" . $directory . "/" . $dir)) {
                            mkdir(PHONE_MODULES_PATH . "endpoint/" . $directory . "/" . $dir, 0775, TRUE);
                            echo ".";
                        }
                    } else {
                        if ((basename($file) != "brand_data.json") OR (!$remote)) {
                            $dir = str_replace($temp_directory . $directory . "/", "", $file);
                            $stats = rename($file, PHONE_MODULES_PATH . "endpoint/" . $directory . "/" . $dir);
                            if ($stats === FALSE) {
                                echo "Error Moving " . basename($file);
                            }
                            chmod(PHONE_MODULES_PATH . "endpoint/" . $directory . "/" . $dir, 0775);
                            echo ".";
                        }
                    }
                }
                echo " Done!<br />";

                if ($remote) {
                    $local = 0;
                } else {
                    $local = 1;
                }

                $b_data = $this->eda->sql("SELECT id FROM endpointman_brand_list WHERE id = '" . $brand_id . "'", 'getOne');
                if ($b_data) {
                    echo "Updating $brand_name brand data..........<br/>";
                    $sql = "UPDATE endpointman_brand_list SET local = '" . $local . "', name = '" . $brand_name . "', cfg_ver = '" . $brand_version . "', installed = 1, hidden = 0 WHERE id = " . $brand_id;
                    $this->eda->sql($sql);
                } else {
                    echo "Inserting $brand_name brand data..........<br/>";
		    $sql = "INSERT INTO endpointman_brand_list (id, name, directory, cfg_ver, local, installed) VALUES ('" . $brand_id . "', '" . $brand_name . "', '" . $directory . "', '" . $brand_version . "', '" . $local . "', '1')";
                    $this->eda->sql($sql);
                }

                $last_mod = "";
                foreach ($temp['data']['brands']['family_list'] as $family_list) {
                    echo "Updating Family Lines.................<br/>";

                    $last_mod = max($last_mod, $family_list['last_modified']);

                    $family_line_xml = $this->file2json(PHONE_MODULES_PATH . '/endpoint/' . $directory . '/' . $family_list['directory'] . '/family_data.json');
                    $family_line_xml['data']['last_modified'] = isset($family_line_xml['data']['last_modified']) ? $family_line_xml['data']['last_modified'] : '';

                    $require_firmware = NULL;
                    if ((key_exists('require_firmware', $family_line_xml['data'])) && ($remote) && ($family_line_xml['data']['require_firmware'] == "TRUE")) {
                        echo "Firmware Requirment Detected!..........<br/>";
                        $this->install_firmware($family_line_xml['data']['id']);
                    }

                    $data = $this->eda->sql("SELECT id FROM endpointman_product_list WHERE id='" . $brand_id . $family_line_xml['data']['id'] . "'", 'getOne');
                    $short_name = preg_replace("/\[(.*?)\]/si", "", $family_line_xml['data']['name']);
		    if ($data) {
			if ($this->global_cfg['debug']) echo "-Updating Family ".$short_name."<br/>";
                        $sql = "UPDATE endpointman_product_list SET short_name = '" . $short_name . "', long_name = '" . $family_line_xml['data']['name'] . "', cfg_ver = '" . $family_line_xml['data']['version'] . "', config_files='" . $family_line_xml['data']['configuration_files'] . "' WHERE id = '" . $brand_id . $family_line_xml['data']['id'] . "'";
                    } else {
			if ($this->global_cfg['debug']) echo "-Inserting Family ".$short_name."<br/>";
                        $sql = "INSERT INTO endpointman_product_list (`id`, `brand`, `short_name`, `long_name`, `cfg_dir`, `cfg_ver`, `config_files`, `hidden`) VALUES ('" . $brand_id . $family_line_xml['data']['id'] . "', '" . $brand_id . "', '" . $short_name . "', '" . $family_line_xml['data']['name'] . "', '" . $family_line_xml['data']['directory'] . "', '" . $family_line_xml['data']['last_modified'] . "','" . $family_line_xml['data']['configuration_files'] . "', '0')";
                    }

                    $this->eda->sql($sql);
                    echo "--Updating Model Lines................<br/>";
                    foreach ($family_line_xml['data']['model_list'] as $model_list) {
                        $template_list = implode(",", $model_list['template_data']);

                        $model_final_id = $brand_id . $family_line_xml['data']['id'] . $model_list['id'];
                        $sql = 'SELECT id, global_custom_cfg_data, global_user_cfg_data FROM endpointman_mac_list WHERE model = ' . $model_final_id;
                        $old_data = NULL;
                        $old_data = $this->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
                        foreach ($old_data as $data) {
                            $global_custom_cfg_data = unserialize($data['global_custom_cfg_data']);
                            if ((is_array($global_custom_cfg_data)) AND (!array_key_exists('data', $global_custom_cfg_data))) {
                                echo "----Old Data Detected! Migrating......";
                                $new_data = array();
                                $new_ari = array();
                                foreach ($global_custom_cfg_data as $key => $old_keys) {
                                    if (array_key_exists('value', $old_keys)) {
                                        $new_data[$key] = $old_keys['value'];
                                    } else {
                                        $breaks = explode("_", $key);
                                        $new_data["loop|" . $key] = $old_keys[$breaks[2]];
                                    }
                                    if (array_key_exists('ari', $old_keys)) {
                                        $new_ari[$key] = 1;
                                    }
                                }
                                $final_data = array();
                                $final_data['data'] = $new_data;
                                $final_data['ari'] = $new_ari;
                                $final_data = serialize($final_data);
                                $sql = "UPDATE endpointman_mac_list SET  global_custom_cfg_data =  '" . $final_data . "' WHERE  id =" . $data['id'];
                                $this->eda->sql($sql);
                                echo "Done!<br/>";
                            }

                            $global_user_cfg_data = unserialize($data['global_user_cfg_data']);
                            $old_check = FALSE;
                            if (is_array($global_user_cfg_data)) {
                                foreach ($global_user_cfg_data as $stuff) {
                                    if (is_array($stuff)) {
                                        if (array_key_exists('value', $stuff)) {
                                            $old_check = TRUE;
                                            break;
                                        } else {
                                            break;
                                        }
                                    } else {
                                        break;
                                    }
                                }
                            }
                            if ((is_array($global_user_cfg_data)) AND ($old_check)) {
                                echo "Old Data Detected! Migrating......";
                                $new_data = array();
                                foreach ($global_user_cfg_data as $key => $old_keys) {
                                    if (array_key_exists('value', $old_keys)) {
                                        $exploded = explode("_", $key);
                                        $counted = count($exploded);
                                        $counted = $counted - 1;
                                        if (is_numeric($exploded[$counted])) {
                                            $key = "loop|" . $key;
                                        }
                                        $new_data[$key] = $old_keys['value'];
                                    }
                                }
                                $final_data = serialize($new_data);
                                $sql = "UPDATE endpointman_mac_list SET  global_user_cfg_data =  '" . $final_data . "' WHERE  id =" . $data['id'];
                                $this->eda->sql($sql);
                                echo "Done!<br/>";
                            }
                        }
                        $old_data = NULL;
                        $sql = 'SELECT id, global_custom_cfg_data FROM endpointman_template_list WHERE model_id = ' . $model_final_id;
                        $old_data = $this->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
                        foreach ($old_data as $data) {
                            $global_custom_cfg_data = unserialize($data['global_custom_cfg_data']);
                            if ((is_array($global_custom_cfg_data)) AND (!array_key_exists('data', $global_custom_cfg_data))) {
                                echo "Old Data Detected! Migrating......";
                                $new_data = array();
                                $new_ari = array();
                                foreach ($global_custom_cfg_data as $key => $old_keys) {
                                    if (array_key_exists('value', $old_keys)) {
                                        $new_data[$key] = $old_keys['value'];
                                    } else {
                                        $breaks = explode("_", $key);
                                        $new_data["loop|" . $key] = $old_keys[$breaks[2]];
                                    }
                                    if (array_key_exists('ari', $old_keys)) {
                                        $new_ari[$key] = 1;
                                    }
                                }
                                $final_data = array();
                                $final_data['data'] = $new_data;
                                $final_data['ari'] = $new_ari;
                                $final_data = serialize($final_data);
                                $sql = "UPDATE endpointman_template_list SET  global_custom_cfg_data =  '" . $final_data . "' WHERE  id =" . $data['id'];
                                $this->eda->sql($sql);
                                echo "Done!<br/>";
                            }
                        }

                        $m_data = $this->eda->sql("SELECT id FROM endpointman_model_list WHERE id='" . $brand_id . $family_line_xml['data']['id'] . $model_list['id'] . "'", 'getone');
                        if ($m_data) {
			    if ($this->global_cfg['debug']) echo "---Updating Model ".$model_list['model']." <br />";
                            $sql = "UPDATE endpointman_model_list SET max_lines = '" . $model_list['lines'] . "', model = '" . $model_list['model'] . "', template_list = '" . $template_list . "' WHERE id = '" . $brand_id . $family_line_xml['data']['id'] . $model_list['id'] . "'";
                        } else {
			    if ($this->global_cfg['debug']) echo "---Inserting Model ".$model_list['model']." <br />";
                            $sql = "INSERT INTO endpointman_model_list (`id`, `brand`, `model`, `max_lines`, `product_id`, `template_list`, `enabled`, `hidden`) VALUES ('" . $brand_id . $family_line_xml['data']['id'] . $model_list['id'] . "', '" . $brand_id . "', '" . $model_list['model'] . "', '" . $model_list['lines'] . "', '" . $brand_id . $family_line_xml['data']['id'] . "', '" . $template_list . "', '0', '0')";
                        }
                        $this->eda->sql($sql);


                        if (!$this->sync_model($brand_id . $family_line_xml['data']['id'] . $model_list['id'])) {
                            echo "System Error in Sync Model Function, Load Failure!<br />";
                        }
                    }
                    //Phone Models Move Here
                    $family_id = $brand_id . $family_line_xml['data']['id'];
                    $sql = "SELECT * FROM endpointman_model_list WHERE product_id = " . $family_id;
                    $products = $this->eda->sql($sql, 'getall', DB_FETCHMODE_ASSOC);
                    foreach ($products as $data) {
                        if (!$this->arraysearchrecursive($data['model'], $family_line_xml['data']['model_list'], 'model')) {
                            echo "Moving/Removing Model '" . $data['model'] . "' not present in JSON file......<br />";
                            $model_name = $data['model'];
                            $sql = 'DELETE FROM endpointman_model_list WHERE id = ' . $data['id'];
                            $this->eda->sql($sql);
                            $sql = "SELECT id FROM endpointman_model_list WHERE model LIKE '" . $model_name . "'";
                            $new_model_id = $this->eda->sql($sql, 'getOne');
                            if ($new_model_id) {
                                $sql = "UPDATE  endpointman_mac_list SET  model =  '" . $new_model_id . "' WHERE  model = '" . $data['id'] . "'";
                            } else {
                                $sql = "UPDATE  endpointman_mac_list SET  model =  '0' WHERE  model = '" . $data['id'] . "'";
                            }
                            $this->eda->sql($sql);
                        }
                    }
                }

		if ($this->global_cfg['debug']) {
		    echo "Done!<br/>";
		    echo "Updating OUI list in DB <br />";
		}
                foreach ($temp['data']['brands']['oui_list'] as $oui) {
                    $sql = "REPLACE INTO endpointman_oui_list (`oui`, `brand`, `custom`) VALUES ('" . $oui . "', '" . $brand_id . "', '0')";
                    $this->eda->sql($sql);
                }

                echo "Done!<br/>";
            } else {
                echo "Invalid JSON Structure in $temp_directory$package/brand_data.json <br />";
            }
        } else {
            echo "No 'brand_data.xml' file exists!<br />";
        }

        echo "Removing Temporary Files..............";
        $this->rmrf($temp_directory . $package);
        echo "Done!<br />";
    }

    /**
     * Remove the brand
     * @param int $id Brand ID
     */
    function remove_brand($id=NULL, $remove_configs=FALSE, $force=FALSE) {
        if (!$this->global_cfg['use_repo']) {
            $sql = 'SELECT id, firmware_vers FROM endpointman_product_list WHERE brand = ' . $id;
            $products = $this->eda->sql($sql, 'getall', DB_FETCHMODE_ASSOC);
            foreach ($products as $data) {
                if ($data['firmware_vers'] != "") {
                    $this->remove_firmware($data['id']);
                }
            }

            $brand_dir = $this->eda->sql("SELECT directory FROM endpointman_brand_list WHERE id=" . $id, 'getone');
            $this->rmrf(PHONE_MODULES_PATH . "endpoint/" . $brand_dir);

            $sql = "DELETE FROM endpointman_model_list WHERE brand = '" . $id . "'";
            $this->eda->sql($sql);

            $sql = "DELETE FROM endpointman_product_list WHERE brand = '" . $id . "'";
            $this->eda->sql($sql);

            $sql = "DELETE FROM endpointman_oui_list WHERE brand = '" . $id . "'";
            $this->eda->sql($sql);

            $this->rmrf(PHONE_MODULES_PATH . $brand_dir);
            $sql = "DELETE FROM endpointman_brand_list WHERE id = " . $id;

            $this->eda->sql($sql);
        } elseif ($force) {
            $brand_dir = $this->eda->sql("SELECT directory FROM endpointman_brand_list WHERE id=" . $id, 'getone');

            $sql = "DELETE FROM endpointman_model_list WHERE brand = '" . $id . "'";
            $this->eda->sql($sql);

            $sql = "DELETE FROM endpointman_product_list WHERE brand = '" . $id . "'";
            $this->eda->sql($sql);

            $sql = "DELETE FROM endpointman_oui_list WHERE brand = '" . $id . "'";
            $this->eda->sql($sql);

            $sql = "DELETE FROM endpointman_brand_list WHERE id = " . $id;

            $this->eda->sql($sql);
        } else {
            $this->error['remove_brand'] = "Not allowed in repo mode";
        }
    }

    function merge_data($path, $template_list, $maxlines = 12) {
        //TODO: fix
        foreach ($template_list as $files_data) {
            $full_path = $path . $files_data;
            if (file_exists($full_path)) {
                $temp_files_data = $this->file2json($full_path);
                foreach ($temp_files_data['template_data']['category'] as $category) {
                    $category_name = $category['name'];
                    foreach ($category['subcategory'] as $subcategory) {
                        $subcategory_name = $subcategory['name'];
                        $items_fin = array();
                        $items_loop = array();
                        $break_count = 0;
                        foreach ($subcategory['item'] as $item) {
                            switch ($item['type']) {
                                case 'loop_line_options':
                                    for ($i = 1; $i <= $maxlines; $i++) {
                                        $var_nam = "lineloop|line_" . $i;
                                        foreach ($item['data']['item'] as $item_loop) {
                                            if ($item_loop['type'] != 'break') {
                                                $z = str_replace("\$", "", $item_loop['variable']);
                                                $items_loop[$var_nam][$z] = $item_loop;
                                                $items_loop[$var_nam][$z]['description'] = str_replace('{$count}', $i, $items_loop[$var_nam][$z]['description']);
                                                $items_loop[$var_nam][$z]['default_value'] = $items_loop[$var_nam][$z]['default_value'];
                                                $items_loop[$var_nam][$z]['default_value'] = str_replace('{$count}', $i, $items_loop[$var_nam][$z]['default_value']);
                                                $items_loop[$var_nam][$z]['line_loop'] = TRUE;
                                                $items_loop[$var_nam][$z]['line_count'] = $i;
                                            } elseif ($item_loop['type'] == 'break') {
                                                $items_loop[$var_nam]['break_' . $break_count]['type'] = 'break';
                                                $break_count++;
                                            }
                                        }
                                    }
                                    $items_fin = array_merge($items_fin, $items_loop);
                                    break;
                                case 'loop':
                                    for ($i = $item['loop_start']; $i <= $item['loop_end']; $i++) {
                                        $name = explode("_", $item['data']['item'][0]['variable']);
                                        $var_nam = "loop|" . str_replace("\$", "", $name[0]) . "_" . $i;
                                        foreach ($item['data']['item'] as $item_loop) {
                                            if ($item_loop['type'] != 'break') {
                                                $z_tmp = explode("_", $item_loop['variable']);
                                                $z = $z_tmp[1];
                                                $items_loop[$var_nam][$z] = $item_loop;
                                                $items_loop[$var_nam][$z]['description'] = str_replace('{$count}', $i, $items_loop[$var_nam][$z]['description']);
                                                $items_loop[$var_nam][$z]['variable'] = str_replace('_', '_' . $i . '_', $items_loop[$var_nam][$z]['variable']);
                                                $items_loop[$var_nam][$z]['default_value'] = isset($items_loop[$var_nam][$z]['default_value']) ? $items_loop[$var_nam][$z]['default_value'] : '';
                                                $items_loop[$var_nam][$z]['loop'] = TRUE;
                                                $items_loop[$var_nam][$z]['loop_count'] = $i;
                                            } elseif ($item_loop['type'] == 'break') {
                                                $items_loop[$var_nam]['break_' . $break_count]['type'] = 'break';
                                                $break_count++;
                                            }
                                        }
                                    }
                                    $items_fin = array_merge($items_fin, $items_loop);
                                    break;
                                case 'break':
                                    $items_fin['break|' . $break_count]['type'] = 'break';
                                    $break_count++;
                                    break;
                                default:
                                    $var_nam = "option|" . str_replace("\$", "", $item['variable']);
                                    $items_fin[$var_nam] = $item;
                                    break;
                            }
                        }
                        if (isset($data['data'][$category_name][$subcategory_name])) {
                            $old_sc = $data['data'][$category_name][$subcategory_name];
                            $sub_cat_data[$category_name][$subcategory_name] = array();
                            $sub_cat_data[$category_name][$subcategory_name] = array_merge($old_sc, $items_fin);
                        } else {
                            $sub_cat_data[$category_name][$subcategory_name] = $items_fin;
                        }
                    }
                    if (isset($data['data'][$category_name])) {
                        $old_c = $data['data'][$category_name];
                        $new_c = $sub_cat_data[$category_name];
                        $sub_cat_data[$category_name] = array();
                        $data['data'][$category_name] = array_merge($old_c, $new_c);
                    } else {
                        $data['data'][$category_name] = $sub_cat_data[$category_name];
                    }
                }
            }
        }
        return($data);
    }

    /**
     * Sync the XML files (incuding all template files) from the hard drive with the database
     * @param int $model Model ID
     * @return boolean True on sync completed. False on sync failed
     */
    function sync_model($model) {
        if ((!empty($model)) OR ($model > 0)) {
            $sql = "SELECT * FROM  endpointman_model_list WHERE id='" . $model . "'";

            $model_row = $this->eda->sql($sql, 'getrow', DB_FETCHMODE_ASSOC);

            $sql = "SELECT * FROM  endpointman_product_list WHERE id='" . $model_row['product_id'] . "'";

            $product_row = $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

            $sql = "SELECT * FROM  endpointman_brand_list WHERE id=" . $model_row['brand'];

            $brand_row = $this->eda->sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

            if (!file_exists(PHONE_MODULES_PATH . '/endpoint/' . $brand_row['directory'])) {
                $this->error['sync_model'] = "Brand Directory '" . $brand_row['directory'] . "' Doesn't Exist! (" . PHONE_MODULES_PATH . '/endpoint/' . $brand_row['directory'] . ")";
                return(FALSE);
            }

            if (!file_exists(PHONE_MODULES_PATH . '/endpoint/' . $brand_row['directory'] . '/' . $product_row['cfg_dir'])) {
                $this->error['sync_model'] = "Product Directory '" . $product_row['cfg_dir'] . "' Doesn't Exist! (" . PHONE_MODULES_PATH . '/endpoint/' . $brand_row['directory'] . '/' . $product_row['cfg_dir'] . ")";
                return(FALSE);
            }

            if (!file_exists(PHONE_MODULES_PATH . '/endpoint/' . $brand_row['directory'] . '/' . $product_row['cfg_dir'] . '/family_data.json')) {
                $this->error['sync_model'] = "File 'family_data.json Doesn't exist in directory: " . PHONE_MODULES_PATH . '/endpoint/' . $brand_row['directory'] . '/' . $product_row['cfg_dir'];
                return(FALSE);
            }

            $family_line_json = $this->file2json(PHONE_MODULES_PATH . '/endpoint/' . $brand_row['directory'] . '/' . $product_row['cfg_dir'] . '/family_data.json');

            //TODO: Add local file checks to avoid slow reloading on PHP < 5.3
            $key = $this->arraysearchrecursive($model_row['model'], $family_line_json['data']['model_list'], 'model');
            if ($key === FALSE) {
                $this->error['sync_model'] = "Can't locate model in family JSON file";
                return(FALSE);
            } else {
                $template_list = implode(",", $family_line_json['data']['model_list'][$key[0]]['template_data']);
                $template_list_array = $family_line_json['data']['model_list'][$key[0]]['template_data'];
            }
            $maxlines = $family_line_json['data']['model_list'][$key[0]]['lines'];

            $sql = "UPDATE endpointman_model_list SET max_lines = '" . $maxlines . "', template_list = '" . $template_list . "' WHERE id = '" . $model . "'";
            $this->eda->sql($sql);

            $version = isset($family_line_json['data']['last_modified']) ? $family_line_json['data']['last_modified'] : '';
            $long_name = $family_line_json['data']['name'];
            $short_name = preg_replace("/\[(.*?)\]/si", "", $family_line_json['data']['name']);
            $configuration_files = $family_line_json['data']['configuration_files'];

            $sql = "UPDATE endpointman_product_list SET long_name = '" . $long_name . "', short_name = '" . $short_name . "' , cfg_ver = '" . $version . "' WHERE id = '" . $product_row['id'] . "'";
            $this->eda->sql($sql);

            $template_data_array = array();

            $template_data_array = $this->merge_data(PHONE_MODULES_PATH . '/endpoint/' . $brand_row['directory'] . '/' . $product_row['cfg_dir'] . '/', $template_list_array);

            $sql = "UPDATE endpointman_model_list SET template_data = '" . serialize($template_data_array) . "' WHERE id = '" . $model . "'";
            $this->eda->sql($sql);
            return(TRUE);
        } else {
            return(FALSE);
        }
    }

    /**
     * Taken from http://www.php.net/manual/en/function.array-search.php#69232
     * search haystack for needle and return an array of the key path, FALSE otherwise.
     * if NeedleKey is given, return only for this key mixed ArraySearchRecursive(mixed Needle,array Haystack[,NeedleKey[,bool Strict[,array Path]]])
     * @author ob (at) babcom (dot) biz
     * @param mixed $Needle
     * @param array $Haystack
     * @param mixed $NeedleKey
     * @param bool $Strict
     * @param array $Path
     * @return array
     * @package epm_system
     */
    function arraysearchrecursive($Needle, $Haystack, $NeedleKey="", $Strict=false, $Path=array()) {
        if (!is_array($Haystack))
            return false;
        foreach ($Haystack as $Key => $Val) {
            if (is_array($Val) &&
                    $SubPath = $this->arraysearchrecursive($Needle, $Val, $NeedleKey, $Strict, $Path)) {
                $Path = array_merge($Path, Array($Key), $SubPath);
                return $Path;
            } elseif ((!$Strict && $Val == $Needle &&
                    $Key == (strlen($NeedleKey) > 0 ? $NeedleKey : $Key)) ||
                    ($Strict && $Val === $Needle &&
                    $Key == (strlen($NeedleKey) > 0 ? $NeedleKey : $Key))) {
                $Path[] = $Key;
                return $Path;
            }
        }
        return false;
    }

    /**
    * Send process to run in background
    * @version 2.11
    * @param string $command the command to run
    * @param integer $Priority the Priority of the command to run
    * @return int $PID process id
    * @package epm_system
    */
    function run_in_background($Command, $Priority = 0) {
        return($Priority ? shell_exec("nohup nice -n $Priority $Command 2> /dev/null & echo $!") : shell_exec("nohup $Command > /dev/null 2> /dev/null & echo $!"));
    }

    /**
    * Check if process is running in background
    * @version 2.11
    * @param string $PID proccess ID
    * @return bool true or false
    * @package epm_system
    */
    function is_process_running($PID) {
        exec("ps $PID", $ProcessState);
        return(count($ProcessState) >= 2);
    }

    /**
    * Downloads a file and places it in the destination defined with progress
    * @version 2.11
    * @param string $url_file URL of File
    * @param string $destination_file Destination of file
    * @package epm_system
    */
    function download_file_with_progress_bar($url_file, $destination_file) {
        set_time_limit(0);
        $headers = get_headers($url_file, 1);
        $size = $headers['Content-Length'];

        if (preg_match('/200/', $headers[0])) {
            $pid = $this->run_in_background("wget " . $url_file . " -O " . $destination_file);

            while ($this->is_process_running($pid)) {

                $out = 100 * round(filesize($destination_file) / $size, 2);
                echo '<script type="text/javascript">document.getElementById(\'DivExample\').innerHTML="%' . $out . '";</script>';

                usleep('500');
                endpointman_flush_buffers();

                clearstatcache(); // make sure PHP actually checks dest. file size
            }
            return true;
        } else {
            return false;
        }
    }

    /**
    * Downloads a file and places it in the destination defined
    * @version 2.11
    * @param string $url_file URL of File
    * @param string $destination_file Destination of file
    * @package epm_system
    */
    function download_file($url_file, $destination_file) {
        //Determine if file_get_contents_url exists which is the default FreePBX Standard for downloading straight files
        if(function_exists('file_get_contents_url')) {
            $contents = file_get_contents_url($url_file);
        } else {
            //I really hope we NEVER get here.
            $contents = file_get_contents($url_file);
            if (!preg_match('/200/', $http_response_header[0])) {
                $this->error['download_file'] = "Unknown Error in Download_file";
                return false;
            }
        }
        //If contents are emtpy then we failed. Or something is wrong
        if(!empty($contents)) {
            $dirname = dirname($destination_file);
            if (!file_exists($dirname)) {
                mkdir($dirname);
            }
            if (!is_writable($dirname)) {
                $this->error['download_file'] = "Directory '" . $dirname . "' is not writable! Unable to download files";
                return false;
            }
            file_put_contents($destination_file, $contents);
            //check file placement
            if (!file_exists($destination_file)) {
                $this->error['download_file'] = "File Doesn't Exist in '" . $dirname . "'. Unable to download files";
                return false;
            }
            return true;
        } else {
            $this->error['download_file'] = "Contents of Remote file are blank! URL:".$url_file;
            return false;
        }
    }

    /**
    * Uses which to find executables that asterisk can run/use
    * @version 2.11
    * @param string $exec Executable to find
    * @package epm_system
    */
    function find_exec($exec) {
        $o = exec('which '.$exec);
        if($o) {
            if(file_exists($o) && is_executable($o)) {
                return($o);
            } else {
                return('');
            }
        } else {
            return('');
        }
    }

    /**
     * The RecursiveIteratorIterator must be told to provide children (files and subdirectories) before parents with its CHILD_FIRST constant.
     * Using RecursiveIteratorIterator is the only way PHP is able to see hidden files.
     * @author http://www.webcheatsheet.com/PHP/working_with_directories.php
     * @param string $dir Full Directory path to delete
     * @version 2.11
     * @package epm_system
     */
    function rmrf($dir) {
        if (file_exists($dir)) {
            $iterator = new RecursiveDirectoryIterator($dir);
            foreach (new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getPathname());
                } else {
                    @unlink($file->getPathname());
                }
            }
            //Remove parent path as the last step
            @rmdir($dir);
        }
    }

    /**
     * Only used once in all of Endpoint Manager to determine if a table exists
     * @param string $table Table to look for
     * @return bool
     */
    function table_exists($table) {
        global $amp_conf;
        $sql = "SHOW TABLES FROM " . $amp_conf['AMPDBNAME'];
        $result = $this->eda->sql($sql, 'getAll');
        foreach ($result as $row) {
            if ($row[0] == $table) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * Reads a file. Json decodes it and will report any errors back
     * @param string $file location of file
     * @return mixed false on error, array on success
     * @version 2.11
     */
    function file2json($file) {
        if (file_exists($file)) {
            $json = file_get_contents($file);
            $data = json_decode($json, TRUE);
            if(function_exists('json_last_error')) {
                switch (json_last_error()) {
                    case JSON_ERROR_NONE:
                        return($data);
                        break;
                    case JSON_ERROR_DEPTH:
                        $this->error['file2json'] = 'Maximum stack depth exceeded';
                        break;
                    case JSON_ERROR_STATE_MISMATCH:
                        $this->error['file2json'] = 'Underflow or the modes mismatch';
                        break;
                    case JSON_ERROR_CTRL_CHAR:
                        $this->error['file2json'] = 'Unexpected control character found';
                        break;
                    case JSON_ERROR_SYNTAX:
                        $this->error['file2json'] = 'Syntax error, malformed JSON';
                        break;
                    case JSON_ERROR_UTF8:
                        $this->error['file2json'] = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                        break;
                    default:
                        $this->error['file2json'] = 'Unknown error';
                        break;
                }
                return(false);
            } else {
                //Probably an older version of PHP. That's ok though
                return($data);
            }
        } else {
            $this->error['file2json'] = 'Cant find file: '.$file ;
            return(false);
        }
    }

    /**
     * This function takes a string and tries to determine if it's a valid mac addess, return FALSE if invalid
     * @param string $mac The full mac address
     * @return mixed The cleaned up MAC is it was a MAC or False if not a mac
     */
    function mac_check_clean($mac) {
        if ((strlen($mac) == "17") OR (strlen($mac) == "12")) {
            //It might be better to use switch here instead of these IF statements...
            //Is the mac separated by colons(:) or dashes(-)?
            if (preg_match("/[0-9a-f][0-9a-f][:-]" .
                            "[0-9a-f][0-9a-f][:-]" .
                            "[0-9a-f][0-9a-f][:-]" .
                            "[0-9a-f][0-9a-f][:-]" .
                            "[0-9a-f][0-9a-f][:-]" .
                            "[0-9a-f][0-9a-f]/i", $mac)) {
                return(strtoupper(str_replace(":", "", str_replace("-", "", $mac))));
            //Is the string exactly 12 characters?
            } elseif (strlen($mac) == "12") {
                //Now is the string a valid HEX mac address?
                if (preg_match("/[0-9a-f][0-9a-f]" .
                                "[0-9a-f][0-9a-f]" .
                                "[0-9a-f][0-9a-f]" .
                                "[0-9a-f][0-9a-f]" .
                                "[0-9a-f][0-9a-f]" .
                                "[0-9a-f][0-9a-f]/i", $mac)) {
                    return(strtoupper($mac));
                } else {
                    return(FALSE);
                }
            //Is the mac separated by whitespaces?
            } elseif (preg_match("/[0-9a-f][0-9a-f][\s]" .
                            "[0-9a-f][0-9a-f][\s]" .
                            "[0-9a-f][0-9a-f][\s]" .
                            "[0-9a-f][0-9a-f][\s]" .
                            "[0-9a-f][0-9a-f][\s]" .
                            "[0-9a-f][0-9a-f]/i", $mac)) {
                return(strtoupper(str_replace(" ", "", $mac)));
            } else {
                return(FALSE);
            }
        } else {
            return(FALSE);
        }
    }

    /**
     * Check for valid netmast to avoid security issues
     * @param string $mask the complete netmask, eg [1.1.1.1/24]
     * @return boolean True if valid, False if not
     * @version 2.11
     */
    function validate_netmask($mask) {
        return preg_match("/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\/(\d{1,2})$/", $mask) ? TRUE : FALSE;
    }

    /**
     * Discover New Device/Hardware
     * nmap will actually discover 'unseen' devices that the VoIP server hasn't heard from
     * If the user just wishes to use the local arp cache they can tell the function to not use nmap
     * This results in a speed increase from 60 seconds to less than one second.
     *
     * This is the original function that started it all
     * http://www.pbxinaflash.com/community/index.php?threads/end-point-configuration-manager-module-for-freepbx-part-1.4514/page-4#post-37671
     *
     * @version 2.11
     * @param mixed $netmask The netmask, eg [1.1.1.1/24]
     * @param boolean $use_nmap True use nmap, false don't use it
     * @return array List of devices found on the network
     */
    function discover_new($netmask, $use_nmap=TRUE) {
        if (($use_nmap) AND (file_exists($this->global_cfg['nmap_location'])) AND ($this->validate_netmask($netmask))) {
            shell_exec($this->global_cfg['nmap_location'] . ' -v -sP ' . $netmask);
        } elseif (!$this->validate_netmask($netmask)) {
            $this->error['discover_new'] = "Invalid Netmask";
            return(FALSE);
        } elseif (!file_exists($this->global_cfg['nmap_location'])) {
            $this->error['discover_new'] = "Could Not Find NMAP, Using ARP Only";
            //return(FALSE);
        }
        //Get arp list
        $arp_list = shell_exec($this->global_cfg['arp_location'] . " -an");

        //Throw arp list into an array, break by new lines
        $arp_array = explode("\n", $arp_list);

        //Find all references to active computers by searching out mac addresses.
        $temp = array_values(array_unique(preg_grep("/[0-9a-f][0-9a-f][:-]" .
                                "[0-9a-f][0-9a-f][:-]" .
                                "[0-9a-f][0-9a-f][:-]" .
                                "[0-9a-f][0-9a-f][:-]" .
                                "[0-9a-f][0-9a-f][:-]" .
                                "[0-9a-f][0-9a-f]/i", $arp_array)));

        //Go through each row of valid arp entries and pull out the information and add it into a nice array!
        $z = 0;
        foreach ($temp as $key => &$value) {

            //Pull out the IP address from row. It's always the first entry in the row and it can only be a max of 15 characters with the delimiters
            preg_match_all("/\((.*?)\)/", $value, $matches);
            $ip = $matches[1];
            $ip = $ip[0];

            //Pull out the mac address by looking for the delimiter
            $mac = substr($value, (strpos($value, ":") - 2), 17);

            //Get rid of the delimiter
            $mac_strip = strtoupper(str_replace(":", "", $mac));

            //arp -n will return a MAC address of 000000000000 if no hardware was found, so we need to ignore it
            if ($mac_strip != "000000000000") {
                //only use the first 6 characters for the oui: http://en.wikipedia.org/wiki/Organizationally_Unique_Identifier
                $oui = substr($mac_strip, 0, 6);

                //Find the matching brand model to the oui
                $oui_sql = "SELECT endpointman_brand_list.name, endpointman_brand_list.id FROM endpointman_oui_list, endpointman_brand_list WHERE oui LIKE '%" . $oui . "%' AND endpointman_brand_list.id = endpointman_oui_list.brand AND endpointman_brand_list.installed = 1 LIMIT 1";

                $brand = $this->eda->sql($oui_sql, 'getRow', DB_FETCHMODE_ASSOC);

                $res = $this->eda->sql($oui_sql);
                $brand_count = count($res);

                if (!$brand_count) {
                    //oui doesn't have a matching mysql reference, probably a PC/router/wap/printer of some sort.
                    $brand['name'] = FALSE;
                    $brand['id'] = NULL;
                }

                //Find out if endpoint has already been configured for this mac address
                $count_sql = "SELECT count(*) FROM endpointman_mac_list WHERE mac LIKE  '%" . $mac_strip . "%'";
                $epm =  $this->eda->sql($count_sql, 'getOne');

                //Add into a final array
                $final[$z] = array("ip" => $ip, "mac" => $mac, "mac_strip" => $mac_strip, "oui" => $oui, "brand" => $brand['name'], "brand_id" => $brand['id'], "endpoint_managed" => $epm);
                $z++;
            }
        }
        return !is_array($final) ? FALSE : $final;
    }

    function areaAvailable($model, $area=NULL) {
        $sql = "SELECT max_lines FROM endpointman_model_list WHERE id = '" . $model . "'";
        $count = $this->eda->sql($sql, 'getOne');

        for ($z = 0; $z < $count; $z++) {
            $result[$z]['id'] = $z + 1;
            $result[$z]['model'] = $z + 1;
        }

        $i = 1;
        foreach ($result as $row) {
            if ($row['id'] == $area) {
                $temp[$i]['value'] = $row['id'];
                $temp[$i]['text'] = $row['model'];
                $temp[$i]['selected'] = 'selected';
            } else {
                $temp[$i]['value'] = $row['id'];
                $temp[$i]['text'] = $row['model'];
                $temp[$i]['selected'] = 0;
            }
            $i++;
        }

        return($temp);
    }

    /**
     *
     * @param integer $model model ID
     * @param integer $brand brand ID
     * @param integer $product product ID
     * @return array
     */
    function models_available($model=NULL, $brand=NULL, $product=NULL) {

        if ((!isset($oui)) && (!isset($brand)) && (!isset($model))) {
            $result1 = $this->eda->all_models();
        } elseif ((isset($brand)) && ($brand != 0)) {
            $result1 = $this->eda->all_models_by_brand($brand);
        } elseif ((isset($product)) && ($product != 0)) {
            $result1 = $this->eda->all_models_by_product($product);
        } else {
            $result1 = $this->eda->all_models();
        }

        $i = 1;
        foreach ($result1 as $row) {
            if ($row['id'] == $model) {
                $temp[$i]['value'] = $row['id'];
                $temp[$i]['text'] = $row['model'];
                $temp[$i]['selected'] = 'selected';
            } else {
                $temp[$i]['value'] = $row['id'];
                $temp[$i]['text'] = $row['model'];
                $temp[$i]['selected'] = 0;
            }
            $i++;
        }

        if (!isset($temp)) {
            if (!isset($this->global_cfg['new'])) {
                $this->error['modelsAvailable'] = "You need to enable at least ONE model";
            }
            return(FALSE);
        } else {
            return($temp);
        }
    }

    function in_array_recursive($needle, $haystack) {

        $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($haystack));

        foreach ($it AS $element) {
            if ($element == $needle) {
                return TRUE;
            }
        }
        return FALSE;
    }

    function linesAvailable($lineid=NULL, $macid=NULL) {
        if (isset($lineid)) {
            $sql = "SELECT max_lines FROM endpointman_model_list WHERE id = (SELECT endpointman_mac_list.model FROM endpointman_mac_list, endpointman_line_list WHERE endpointman_line_list.luid = " . $lineid . " AND endpointman_line_list.mac_id = endpointman_mac_list.id)";

            $sql_l = "SELECT line, mac_id FROM `endpointman_line_list` WHERE luid = " . $lineid;
            $line = $this->eda->sql($sql_l, 'getRow', DB_FETCHMODE_ASSOC);

            $sql_lu = "SELECT line FROM endpointman_line_list WHERE mac_id = " . $line['mac_id'];
        } elseif (isset($macid)) {
            $sql = "SELECT max_lines FROM endpointman_model_list WHERE id = (SELECT model FROM endpointman_mac_list WHERE id =" . $macid . ")";
            $sql_lu = "SELECT line FROM endpointman_line_list WHERE mac_id = " . $macid;

            $line['line'] = 0;
        }

        $max_lines = $this->eda->sql($sql, 'getOne');
        $lines_used = $this->eda->sql($sql_lu, 'getAll');

        for ($i = 1; $i <= $max_lines; $i++) {
            if ($i == $line['line']) {
                $temp[$i]['value'] = $i;
                $temp[$i]['text'] = $i;
                $temp[$i]['selected'] = "selected";
            } else {
                if (!$this->in_array_recursive($i, $lines_used)) {
                    $temp[$i]['value'] = $i;
                    $temp[$i]['text'] = $i;
                }
            }
        }
        if (isset($temp)) {
            return($temp);
        } else {
            return FALSE;
        }
    }

    /**
     * Display all unused registrations from whatever manager we are using!
     * @return <type>
     */
    function display_registration_list($line_id=NULL) {

        if (isset($line_id)) {
            $result = $this->eda->all_unused_registrations();
            $line_data = $this->eda->get_line_information($line_id);
        } else {
            $result = $this->eda->all_unused_registrations();
            $line_data = NULL;
        }

        $i = 1;
        $temp = array();
        foreach ($result as $row) {
            $temp[$i]['value'] = $row['id'];
            $temp[$i]['text'] = $row['id'] . " --- " . $row['description'];
            $i++;
        }

        if (isset($line_data)) {
            $temp[$i]['value'] = $line_data['ext'];
            $temp[$i]['text'] = $line_data['ext'] . " --- " . $line_data['description'];
            $temp[$i]['selected'] = "selected";
        }

        return($temp);
    }

    /**
     * Returns list of Brands that are installed and not hidden and that have at least one model enabled under them
     * @param integer $selected ID Number of the brand that is supposed to be selected in a drop-down list box
     * @return array Number array used to generate a select box
     */
    function brands_available($selected = NULL, $show_blank=TRUE) {
        $data = $this->eda->all_active_brands();
        if ($show_blank) {
            $temp[0]['value'] = "";
            $temp[0]['text'] = "";
            $i = 1;
        } else {
            $i = 0;
        }
        foreach ($data as $row) {
            $temp[$i]['value'] = $row['id'];
            $temp[$i]['text'] = $row['name'];
            if ($row['id'] == $selected) {
                $temp[$i]['selected'] = TRUE;
            } else {
                $temp[$i]['selected'] = NULL;
            }
            $i++;
        }
        return($temp);
    }

    function display_templates($product_id, $temp_select = NULL) {
        $i = 0;
        $sql = "SELECT id FROM  endpointman_product_list WHERE endpointman_product_list.id ='" . $product_id . "'";

        $id = $this->eda->sql($sql, 'getOne');

        $sql = "SELECT * FROM  endpointman_template_list WHERE  product_id = '" . $id . "'";

        $data = $this->eda->sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
        foreach ($data as $row) {
            $temp[$i]['value'] = $row['id'];
            $temp[$i]['text'] = $row['name'];
            if ($row['id'] == $temp_select) {
                $temp[$i]['selected'] = "selected";
            }
            $i++;
        }
        $temp[$i]['value'] = 0;
        if ($temp_select == 0) {
            $temp[$i]['text'] = "Custom...";
            $temp[$i]['selected'] = "selected";
        } else {
            $temp[$i]['text'] = "Custom...";
        }

        return($temp);
    }

    function listTZ($selected) {
        require('timezone.inc');
        $data = DateTimeZone::listIdentifiers();
        $i = 0;
        foreach ($data as $key => $row) {
            $temp[$i]['value'] = $row;
            $temp[$i]['text'] = $row;
            if ($temp[$i]['value'] == $selected) {
                $temp[$i]['selected'] = 1;
            } else {
                $temp[$i]['selected'] = 0;
            }
            $i++;
        }

        return($temp);
    }

    function validate_json($json) {
        return(TRUE);
    }

    function has_git() {
        exec('which git', $output);

        $git = file_exists($line = trim(current($output))) ? $line : 'git';

        unset($output);

        exec($git . ' --version', $output);

        preg_match('#^(git version)#', current($output), $matches);

        return!empty($matches[0]) ? $git : false;
        echo!empty($matches[0]) ? 'installed' : 'nope';
    }

}

function endpointman_flush_buffers() {
    ob_end_flush();
    //ob_flush();
    flush();
    ob_start();
}

function endpointman_update_progress_bar($out) {
    echo '<script type="text/javascript">document.getElementById(\'DivExample\').innerHTML="%' . $out . '";</script>';
}

function endpointmanager_read_header($ch, $string) {
    global $file_size, $fout;
    $length = strlen($string);
    $regs = "";
    preg_match("/(Content-Length:) (.*)/i", $string, $regs);
    if ((isset($regs[2])) AND ($regs[2] <> "")) {
        $file_size = intval($regs[2]);
    }
    //ob_flush();
    endpointman_flush_buffers();
    return $length;
}

function endpointmanager_read_body($ch, $string) {
    global $fout, $file_size, $downloaded, $lastseen, $progress_bar;
    $length = strlen($string);
    $downloaded += intval($length);
    $downloadProgress = round(100 * (1 - $downloaded / $file_size), 0);
    $downloadProgress = 100 - $downloadProgress;
    if ($lastseen <> $downloadProgress and $downloadProgress < 101) {
        if ($progress_bar) {
            endpointman_update_progress_bar($downloadProgress);
        }
        $lastseen = $downloadProgress;
    }
    if ($fout)
        fwrite($fout, $string);
    //ob_flush();
    endpointman_flush_buffers();
    return $length;
}
