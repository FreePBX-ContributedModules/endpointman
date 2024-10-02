<?php
/**
 * Endpoint Manager Object Module - Sec Advanced
 *
 * @author Javier Pastor
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */

namespace FreePBX\modules;
use FreePBX;

class Endpointman_Advanced
{
    public $MODULES_PATH;
	public $LOCAL_PATH;
	public $PHONE_MODULES_PATH;

	public function __construct($freepbx = null, $cfgmod = null, $epm_config)
	{
		$this->freepbx = $freepbx;
		$this->db = $freepbx->Database;
		$this->config = $freepbx->Config;
		$this->configmod = $cfgmod;
		$this->epm_config = $epm_config;

        $this->MODULES_PATH = $this->config->get('AMPWEBROOT') . '/admin/modules/';
        if (file_exists($this->MODULES_PATH . "endpointman/")) {
            $this->LOCAL_PATH = $this->MODULES_PATH . "endpointman/";
        } else {
            die("Can't Load Local Endpoint Manager Directory!");
        }
		if (file_exists($this->MODULES_PATH . "_ep_phone_modules/")) {
            $this->PHONE_MODULES_PATH = $this->MODULES_PATH . "_ep_phone_modules/";
        } else {
            $this->PHONE_MODULES_PATH = $this->MODULES_PATH . "_ep_phone_modules/";
            if (!file_exists($this->PHONE_MODULES_PATH)) {
                mkdir($this->PHONE_MODULES_PATH, 0775);
            }
            if (file_exists($this->PHONE_MODULES_PATH . "setup.php")) {
                unlink($this->PHONE_MODULES_PATH . "setup.php");
            }
            if (!file_exists($this->MODULES_PATH . "_ep_phone_modules/")) {
                die('Endpoint Manager can not create the modules folder!');
            }
        }
	}

	public function myShowPage(&$pagedata) {
		if(empty($pagedata))
		{
			$pagedata['settings'] = array(
				"name" => _("Settings"),
				"page" => 'views/epm_advanced_settings.page.php'
			);
			$pagedata['oui_manager'] = array(
				"name" => _("OUI Manager"),
				"page" => 'views/epm_advanced_oui_manager.page.php'
			);
			$pagedata['poce'] = array(
				"name" => _("Product Configuration Editor"),
				"page" => 'views/epm_advanced_poce.page.php'
			);
			$pagedata['iedl'] = array(
				"name" => _("Import/Export My Devices List"),
				"page" => 'views/epm_advanced_iedl.page.php'
			);
			$pagedata['manual_upload'] = array(
				"name" => _("Package Import/Export"),
				"page" => 'views/epm_advanced_manual_upload.page.php'
			);
		}
	}

	public function ajaxRequest($req, &$setting) {
		$arrVal = array("oui", "oui_add", "oui_del", "poce_list_brands","poce_select", "poce_select_file", "poce_save_file", "poce_save_as_file", "poce_sendid", "poce_delete_config_custom", "list_files_brands_export", "saveconfig");
		if (in_array($req, $arrVal)) {
			$setting['authenticate'] = true;
			$setting['allowremote'] = false;
			return true;
		}
		return false;
	}

    public function ajaxHandler($module_tab = "", $command = "")
	{
		$txt = array();
		$txt['settings'] = array(
			'error' => _("Error!"),
			'save_changes' => _("Saving Changes..."),
			'save_changes_ok' => _("Saving Changes... Ok!"),
			'opt_invalid' => _("Invalid Option!")
		);

		if ($module_tab == "settings")
		{
			switch ($command)
			{
				case "saveconfig":
					$retarr = $this->epm_advanced_settings_saveconfig();
					break;

				default:
					$retarr = array("status" => false, "message" => _("Command not found!") . " [" .$command. "]");
					break;
			}
			$retarr['txt'] = $txt['settings'];
		}
		elseif ($module_tab == "oui_manager") {
			switch ($command)
			{
				case "oui":
					//$sql = 'SELECT endpointman_oui_list.id, endpointman_oui_list.oui , endpointman_brand_list.name, endpointman_oui_list.custom FROM endpointman_oui_list , endpointman_brand_list WHERE endpointman_oui_list.brand = endpointman_brand_list.id ORDER BY endpointman_oui_list.oui ASC';
					$sql = 'SELECT T1.id, T1.oui, T2.name, T1.custom FROM endpointman_oui_list as T1 , endpointman_brand_list as T2 WHERE T1.brand = T2.id ORDER BY T1.oui ASC';
					$data = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
					$ret = array();
					foreach ($data as $item) {
						$ret[] = array('id' => $item['id'], 'oui' => $item['oui'], 'brand' => $item['name'], 'custom' => $item['custom']);
					}
					return $ret;
					break;

				case "oui_add":
					$retarr = $this->epm_advanced_oui_add();
					break;

				case "oui_del":
					$retarr = $this->epm_advanced_oui_remove();
					break;

				default:
					$retarr = array("status" => false, "message" => _("Command not found!") . " [" .$command. "]");
					break;
			}
			//$retarr['txt'] = $txt['settings'];
		}
		elseif ($module_tab == "iedl") {
			switch ($command)
			{
				default:
					$retarr = array("status" => false, "message" => _("Command not found!") . " [" .$command. "]");
					break;
			}
			//$retarr['txt'] = $txt['settings'];
		}
		elseif ($module_tab == "poce") {
			switch ($command)
			{
				case "poce_list_brands":
					$retarr = $this->epm_advanced_poce_list_brands();
					break;

				case "poce_select":
					$retarr = $this->epm_advanced_poce_select();
					break;

				case "poce_select_file":
					$retarr = $this->epm_advanced_poce_select_file();
					break;

				case "poce_save_file":
				case "poce_save_as_file":
					$retarr = $this->epm_advanced_poce_save_file();
					break;

				case "poce_sendid":
					$retarr = $this->epm_advanced_poce_sendid();
					break;

				case "poce_delete_config_custom":
					$retarr = $this->epm_advanced_poce_delete_config_custom();
					break;

				default:
					$retarr = array("status" => false, "message" => _("Command not found!") . " [" .$command. "]");
					break;
			}
			//$retarr['txt'] = $txt['settings'];
		}
		elseif ($module_tab == "manual_upload") {
			switch ($command)
			{
				case "list_files_brands_export":
					$retarr = $this->epm_advanced_manual_upload_list_files_brans_export();
					break;

				default:
					$retarr = array("status" => false, "message" => _("Command not found!") . " [" .$command. "]");
					break;
			}
		}
		else {
			$retarr = array("status" => false, "message" => _("Tab is not valid!") . " [" .$module_tab. "]");
		}
		return $retarr;
	}

	public function doConfigPageInit($module_tab = "", $command = "") {
		switch ($module_tab)
		{
			case "oui_manager":
				break;

			case "iedl":
				switch ($command) {
					case "export":
						$this->epm_advanced_iedl_export();
						break;

					case "import":
						$this->epm_advanced_iedl_import();
						echo "<br /><hr><br />";
						exit;
						break;
				}
				break;

			case "manual_upload":
				switch ($command) {
					case "export_brands_availables":
						$this->epm_advanced_manual_upload_export_brans_available();
						echo "<br /><hr><br />";
						exit;
						break;

					case "export_brands_availables_file":
						$this->epm_advanced_manual_upload_export_brans_available_file();
						exit;
						break;

					case "upload_brand":
						$this->epm_advanced_manual_upload_brand();
						echo "<br /><hr><br />";
						exit;
						break;

					case "upload_provisioner":
						$this->epm_advanced_manual_upload_provisioner();
						echo "<br /><hr><br />";
						exit;
						break;
				}
				break;
		}
	}

	public function getRightNav($request) {
		return "";
	}

	public function getActionBar($request) {
		return "";
	}


	/**** FUNCIONES SEC MODULO "epm_advanced\settings" ****/
	public function epm_advanced_config_loc_is_writable()
	{
		$config_loc = $this->configmod->get("config_loc");
		$tftp_writable = FALSE;
		if ((isset($config_loc)) AND ($config_loc != "")) {
			if ((file_exists($config_loc)) AND (is_dir($config_loc))) {
				if (is_writable($config_loc)) {
					$tftp_writable = TRUE;
				}
			}
		}
		return $tftp_writable;
	}

	private function epm_advanced_settings_saveconfig ()
	{
		$arrVal['VAR_REQUEST'] = array("name", "value");
		foreach ($arrVal['VAR_REQUEST'] as $valor) {
			if (! array_key_exists($valor, $_REQUEST)) {
				return array("status" => false, "message" => _("No send value!")." [".$valor."]");
			}
		}

		$dget['name'] = strtolower($_REQUEST['name']);
		$dget['value'] = $_REQUEST['value'];

		switch($dget['name']) {
			case "enable_ari":
				$dget['value'] = strtolower($dget['value']);
				$sql = "UPDATE endpointman_global_vars SET value='" . ($dget['value'] == "yes" ? "1": "0") . "' WHERE var_name='enable_ari'";
				break;

			case "enable_debug":
				$dget['value'] = strtolower($dget['value']);
				$sql = "UPDATE endpointman_global_vars SET value='" . ($dget['value'] == "yes" ? "1": "0") . "' WHERE var_name='debug'";
				break;

			case "disable_help":
				$dget['value'] = strtolower($dget['value']);
				$sql = "UPDATE endpointman_global_vars SET value='" . ($dget['value'] == "yes" ? "1": "0") . "' WHERE var_name='disable_help'";
				break;
	
			case "disable_endpoint_warning":
				$dget['value'] = strtolower($dget['value']);
				$sql = "UPDATE endpointman_global_vars SET value='" . ($dget['value'] == "yes" ? "1": "0") . "' WHERE var_name='disable_endpoint_warning'";
				break;

			case "allow_dupext":
				$dget['value'] = strtolower($dget['value']);
				$sql = "UPDATE endpointman_global_vars SET value='" . ($dget['value'] == "yes" ? "1": "0") . "' WHERE var_name='show_all_registrations'";
				break;

			case "allow_hdfiles":
				$dget['value'] = strtolower($dget['value']);
				$sql = "UPDATE endpointman_global_vars SET value='" . ($dget['value'] == "yes" ? "1": "0") . "' WHERE var_name='allow_hdfiles'";
				break;

			case "tftp_check":
				$dget['value'] = strtolower($dget['value']);
				$sql = "UPDATE endpointman_global_vars SET value='" . ($dget['value'] == "yes" ? "1": "0") . "' WHERE var_name='tftp_check'";
				break;

			case "backup_check":
				$dget['value'] = strtolower($dget['value']);
				$sql = "UPDATE endpointman_global_vars SET value='" . ($dget['value'] == "yes" ? "1": "0") . "' WHERE var_name='backup_check'";
				break;

			case "use_repo":
				$dget['value'] = strtolower($dget['value']);
				if (($dget['value'] == "yes") and (! $this->has_git())) {
					$retarr = array("status" => false, "message" => _("Git not installed!"));
				}
				else {
					$sql = "UPDATE endpointman_global_vars SET value='" . ($dget['value'] == "yes" ? "1": "0") . "' WHERE var_name='use_repo'";
				}
				break;

			case "config_loc":
				$dget['value'] = trim($dget['value']);
				//No trailing slash. Help the user out and add one :-)
				if ($dget['value'][strlen($dget['value']) - 1] != "/") {
					$dget['value'] = $dget['value'] . "/";
				}
				if ($dget['value'] != "") {
					if ((file_exists($dget['value'] = $dget['value'])) AND (is_dir($dget['value'] = $dget['value']))) {
						if (is_writable($dget['value'] = $dget['value'])) {
							$sql = "UPDATE endpointman_global_vars SET value='" . $dget['value'] . "' WHERE var_name='config_location'";
						} else {
							$retarr = array("status" => false, "message" => _("Directory Not Writable!"));
						}
					} else {
						$retarr = array("status" => false, "message" => _("Not a Vaild Directory.<br /> Try to run 'mkdir " . $_POST['config_loc'] . "' as root."));
					}
				} else {
					$retarr = array("status" => false, "message" => _("No Configuration Location Defined!"));
				}
				break;

			case "srvip":
				$dget['value'] = trim($dget['value']);
				$sql = "UPDATE endpointman_global_vars SET value='" . $dget['value'] . "' WHERE var_name='srvip'";
				break;
			case "intsrvip":
				$dget['value'] = trim($dget['value']);
				$sql = "UPDATE endpointman_global_vars SET value='" . $dget['value'] . "' WHERE var_name='intsrvip'";
				break;
			case "tz":
				$sql = "UPDATE endpointman_global_vars SET value='" . $dget['value'] . "' WHERE var_name='tz'";
				break;

			case "adminpass":
				$dget['value'] = trim($dget['value']);
				$sql = "UPDATE endpointman_global_vars SET value='" . $dget['value'] . "' WHERE var_name='adminpass'";
				break;
			case "userpass":
				$dget['value'] = trim($dget['value']);
				$sql = "UPDATE endpointman_global_vars SET value='" . $dget['value'] . "' WHERE var_name='userpass'";
				break;
				
			case "ntp_server":
				$dget['value'] = trim($dget['value']);
				$sql = "UPDATE endpointman_global_vars SET value='" . $dget['value'] . "' WHERE var_name='ntp'";
				break;

			case "nmap_loc":
				$sql = "UPDATE endpointman_global_vars SET value='" . $dget['value'] . "' WHERE var_name='nmap_location'";
				break;

			case "arp_loc":
				$sql = "UPDATE endpointman_global_vars SET value='" . $dget['value'] . "' WHERE var_name='arp_location'";
				break;

			case "asterisk_loc":
				$sql = "UPDATE endpointman_global_vars SET value='" . $dget['value'] . "' WHERE var_name='asterisk_location'";
				break;

			case "package_server":
				$sql = "UPDATE endpointman_global_vars SET value='" . $dget['value'] . "' WHERE var_name='update_server'";
				break;

			case "cfg_type":
				if ($dget['value'] == 'http' or $dget['value'] == 'https' ) {
					$symlink = $this->config->get('AMPWEBROOT') . "/provisioning";
					$reallink = $this->LOCAL_PATH . "provisioning";
					if ((!is_link($symlink)) OR (!readlink($symlink) == $reallink)) {
						if (!symlink($reallink, $symlink)) {
							$retarr = array("status" => false, "message" => _("Your permissions are wrong on " . $this->config->get('AMPWEBROOT') . ", web provisioning link not created!"));
							//$dget['value'] = 'file';
							break;
						} //else {
							//$dget['value'] = 'http';
						//}
					}// else {
						//$dget['value'] = 'http';
					//}
				} else {
					$dget['value'] = 'file';
				}
				$sql = "UPDATE endpointman_global_vars SET value='" . $dget['value'] . "' WHERE var_name='server_type'";
				break;

			default:
				$retarr = array("status" => false, "message" => _("Name invalid: ") . $dget['name'] );
		}

		if (isset($sql)) {
			sql($sql);
			$retarr = array("status" => true, "message" => "OK", "name" => $dget['name'], "value" => $dget['value']);
			unset($sql);
		}

		unset($dget);
		return $retarr;
	}


	/**** FUNCIONES SEC MODULO "epm_advanced\poce" ****/
	public function epm_advanced_poce_list_brands()
	{
		//$sql = 'SELECT * FROM endpointman_product_list WHERE hidden = 0 AND id > 0 ORDER BY long_name ASC';
		//$sql = 'SELECT * FROM endpointman_product_list WHERE hidden = 0 AND id > 0 AND brand IN (SELECT id FROM asterisk.endpointman_brand_list where hidden = 0) ORDER BY long_name ASC';
		$sql = 'SELECT * FROM endpointman_product_list WHERE hidden = 0 AND id IN (SELECT DISTINCT product_id FROM asterisk.endpointman_model_list where enabled = 1) AND brand IN (SELECT id FROM asterisk.endpointman_brand_list where hidden = 0) ORDER BY long_name ASC';
		$product_list = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
		$i = 0;
		$temp = array();
		foreach ($product_list as $srow)
		{
			$temp[$i]['id'] = $srow['id'];
			$temp[$i]['name'] = $srow['long_name'];
			$temp[$i]['name_mini'] = substr($srow['long_name'], 0, 40).(strlen($srow['long_name']) > 40 ? "..." : "");
			$i++;
		}
		return array("status" => true, "message" => _("Ok!"), "ldatos" => $temp);
	}

	public function epm_advanced_poce_select()
	{
			if (! isset($_REQUEST['product_select'])) {
			$retarr = array("status" => false, "message" => _("No send Product Select!"));
		}
		elseif (! is_numeric($_REQUEST['product_select'])) {
			$retarr = array("status" => false, "message" => _("Product Select send is not number!"));
		}
		elseif ($_REQUEST['product_select'] < 0) {
			$retarr = array("status" => false, "message" => _("Product Select send is number not valid!"));
		}
		else
		{
			$dget['product_select'] = $_REQUEST['product_select'];

			$sql = 'SELECT * FROM `endpointman_product_list` WHERE `hidden` = 0 AND `id` = '.$dget['product_select'];
			$product_select_info = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

			$sql = "SELECT cfg_dir,directory,config_files FROM endpointman_product_list,endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id ='" . $dget['product_select'] . "'";
			$row =  sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
			$config_files = explode(",", $row['config_files']);
			$i = 0;
			if (count($config_files)) {
				foreach ($config_files as $config_files_data) {
					//$file_list[$i]['value'] = $i;
					$file_list[$i]['value'] = $dget['product_select'];
					$file_list[$i]['text'] = $config_files_data;
					$i++;
				}
			} else { $file_list = NULL; }

			$sql = "SELECT * FROM endpointman_custom_configs WHERE product_id = '" . $dget['product_select'] . "'";
			$data = sql($sql,'getAll', DB_FETCHMODE_ASSOC);
			$i = 0;
			if (count($data)) {
				//$data = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
				foreach ($data as $row2) {
					$sql_file_list[$i]['value'] = $row2['id'];
					$sql_file_list[$i]['text'] = $row2['name'];
					$sql_file_list[$i]['ref'] = $row2['original_name'];
					$i++;
				}
			} else { $sql_file_list = NULL; }


			require_once($this->PHONE_MODULES_PATH . 'setup.php');
			$class = "endpoint_" . $row['directory'] . "_" . $row['cfg_dir'] . '_phone';
			$base_class = "endpoint_" . $row['directory'] . '_base';
			$master_class = "endpoint_base";

			/*********************************************************************************
			 *** Quick Fix for FreePBX Distro
			 *** I seriously want to figure out why ONLY the FreePBX Distro can't do autoloads.
			 **********************************************************************************/
			if (!class_exists($master_class)) {
				ProvisionerConfig::endpointsAutoload($master_class);
			}
			if (!class_exists($base_class)) {
				ProvisionerConfig::endpointsAutoload($base_class);
			}
			if (!class_exists($class)) {
				ProvisionerConfig::endpointsAutoload($class);
			}
			//end quick fix
			$phone_config = new $class();

			//TODO: remove
			$template_file_list[0]['value'] = "template_data_custom.xml";
			$template_file_list[0]['text'] = "template_data_custom.xml";

			$sql = "SELECT id, model FROM endpointman_model_list WHERE product_id = '" . $dget['product_select'] . "' AND enabled = 1 AND hidden = 0";
			$data = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
			$i = 1;
			foreach ($data as $list) {
				//$template_file_list[$i]['value'] = "template_data_" . $list['model'] . "_custom.xml";
				$template_file_list[$i]['value'] = $list['id'];
				$template_file_list[$i]['text'] = "template_data_" . $list['model'] . "_custom.xml";
			}

			$retarr = array("status" => true,
							"message" => "OK",
							"product_select" => $dget['product_select'],
							"product_select_info" => $product_select_info,
							"file_list" => $file_list,
							"template_file_list" => $template_file_list,
							"sql_file_list" => $sql_file_list);
			unset($dget);
		}
		return $retarr;
	}

	public function epm_advanced_poce_select_file()
	{
		$arrVal['VAR_REQUEST'] = array("product_select", "file_id", "file_name", "type_file");
		foreach ($arrVal['VAR_REQUEST'] as $valor) {
			if (! array_key_exists($valor, $_REQUEST)) {
				return array("status" => false, "message" => _("No send value!")." [".$valor."]");
			}
		}
		if (! is_numeric($_REQUEST['product_select'])) {
			return array("status" => false, "message" => _("Product Select send is not number!"));
		}
		elseif ($_REQUEST['product_select'] < 0) {
			return array("status" => false, "message" => _("Product Select send is number not valid!"));
		}

		$dget['product_select'] = $_REQUEST['product_select'];
		$dget['file_name'] = $_REQUEST['file_name'];
		$dget['file_id'] = $_REQUEST['file_id'];
		$dget['type_file'] = $_REQUEST['type_file'];

		if ($dget['type_file'] == "sql") {
			$sql = 'SELECT * FROM endpointman_custom_configs WHERE id =' . $dget['file_id'];
			$row = sql($sql, 'getrow', DB_FETCHMODE_ASSOC);

			$type = $dget['type_file'];
			$sendidt = $row['id'];
			$product_select = $row['product_id'];
			$save_as_name_value = $row['name'];
			$original_name = $row['original_name'];
			$filename =  $row['name'];
			$location = "SQL: ". $row['name'];
			$config_data = $this->display_htmlspecialchars($row['data']);

		}
		elseif ($dget['type_file'] == "file") {
			$sql = "SELECT cfg_dir,directory,config_files FROM endpointman_product_list,endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id = '" . $dget['product_select'] . "'";
			$row = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

			$config_files = explode(",", $row['config_files']);
			//TODO: Añadir validacion para ver si $dget['file_name'] esta en el array $config_files

			$filename = $dget['file_name'];
			$pathfile = $this->PHONE_MODULES_PATH . 'endpoint/' . $row['directory'] . "/" . $row['cfg_dir'] . "/" . $filename;


			if (is_readable($pathfile)) {
				if(filesize($pathfile)>0) {
					$handle = fopen($pathfile, "rb");
					$contents = fread($handle, filesize($pathfile));
					fclose($handle);
					$contents = $this->display_htmlspecialchars($contents);
				}
				else {
					$contents = "";
				}

				$type = $dget['type_file'];
				$sendidt = $dget['file_id'];
				$product_select = $dget['product_select'];
				$save_as_name_value = $filename;
				$original_name = $filename;
				$filename =  $filename;
				$location = $pathfile;
				$config_data = $contents;
			}
			else {
				$retarr = array("status" => false, "message" => _("File not readable, check the permission! ").$filename);
			}
		}
		elseif ($dget['type_file'] == "tfile")
		{
			if ($dget['file_id'] == "template_data_custom.xml")
			{
				$sendidt = "";
				$original_name = $dget['file_name'];
				$config_data = "";
			}
			else {

				$sql = "SELECT * FROM endpointman_model_list WHERE id = '" . $dget['file_id'] . "'";
				$data = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

				$sendidt = $data['id'];
				$original_name = $dget['file_name'];
				$config_data = unserialize($data['template_data']);
				$config_data = generate_xml_from_array ($config_data, 'node');
			}

			$type = $dget['type_file'];
			$product_select = $dget['product_select'];
			$save_as_name_value = $dget['file_name'];
			$filename = $dget['file_name'];
			$location = $dget['file_name'];
		}

		$retarr = array("status" => true,
						"message" => "OK",
						"type" => $type,
						"sendidt" => $sendidt,
						"product_select" => $product_select,
						"save_as_name_value" => $save_as_name_value,
						"original_name" => $original_name,
						"filename" => $filename,
						"location" => $location,
						"config_data" => $config_data);

		unset($dget);
		return $retarr;
	}







	//TODO: PENDIENTE REVISAR
	function epm_advanced_poce_sendid()
	{
		if (! isset($_REQUEST['product_select'])) {
			$retarr = array("status" => false, "message" => _("No send Product Select!"));
		}
		elseif (! isset($_REQUEST['type_file'])) {
			$retarr = array("status" => false, "message" => _("No send Type File!"));
		}
		elseif (! isset($_REQUEST['sendid'])) {
			$retarr = array("status" => false, "message" => _("No send SendID!"));
		}
		else {
			$dget['product_select'] = $_REQUEST['product_select'];
			$dget['type_file'] = $_REQUEST['type_file'];
			$dget['sendid'] = $_REQUEST['sendid'];
			$dget['original_name'] = $_REQUEST['original_name'];
			$dget['config_text'] = $_REQUEST['config_text'];



			//DEBUGGGGGGGGGGGGG
			return;
			if ($dget['type_file'] == "sql") {
				$sql = "SELECT cfg_dir,directory,config_files FROM endpointman_product_list,endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id = '" . $dget['product_select'] . "'";
				$row = sql($sql, 'getrow', DB_FETCHMODE_ASSOC);
				$this->submit_config($row['directory'], $row['cfg_dir'], $dget['original_name'], $dget['config_text']);
				$retarr = array("status" => true, "message" => "Sent! Thanks :-)");
			}
			elseif ($dget['type_file'] == "file") {
				$sql = "SELECT cfg_dir,directory,config_files FROM endpointman_product_list,endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id = '" . $dget['product_select'] . "'";
				$row = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
				$error = $this->submit_config($row['directory'], $row['cfg_dir'], $dget['original_name'], $dget['config_text']);
				$retarr = array("status" => true, "message" => "Sent! Thanks :-)");
			}
			else {
				$retarr = array("status" => false, "message" => "Type not valid!");
			}
			unset ($dget);
		}
		return $retarr;
	}







	function epm_advanced_poce_save_file()
	{
		$arrVal['VAR_REQUEST'] = array("product_select", "sendid", "type_file", "config_text", "save_as_name", "file_name", "original_name");
		foreach ($arrVal['VAR_REQUEST'] as $valor) {
			if (! array_key_exists($valor, $_REQUEST)) {
				return array("status" => false, "message" => _("No send value!")." [".$valor."]");
			}
		}

		$dget['command'] = $_REQUEST['command'];
		$dget['type_file'] = $_REQUEST['type_file'];
		$dget['sendid'] = $_REQUEST['sendid'];
		$dget['product_select'] = $_REQUEST['product_select'];
		$dget['save_as_name'] = $_REQUEST['save_as_name'];
		$dget['original_name'] = $_REQUEST['original_name'];
		$dget['file_name'] = $_REQUEST['file_name'];
		$dget['config_text'] = $_REQUEST['config_text'];

		if ($dget['type_file'] == "file") {
			if ($dget['command'] == "poce_save_file")
			{
				$sql = "SELECT cfg_dir,directory,config_files FROM endpointman_product_list,endpointman_brand_list WHERE endpointman_product_list.brand = endpointman_brand_list.id AND endpointman_product_list.id = '" . $dget['product_select'] . "'";
				$row = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
				$config_files = explode(",", $row['config_files']);

				if ((is_array($config_files)) AND (in_array($dget['file_name'], $config_files)))
				{
					$pathdir = $this->PHONE_MODULES_PATH . 'endpoint/' . $row['directory'] . "/" . $row['cfg_dir'] . "/";
					$pathfile = $pathdir . $dget['file_name'];
					if ((! file_exists($pathfile)) AND (! is_writable($pathdir))) {
						$retarr = array("status" => false, "message" => "Directory is not Writable (".$pathdir.")!");
					}
					elseif (! is_writable($pathfile)) {
						$retarr = array("status" => false, "message" => "File is not Writable (".$pathfile.")!");
					}
					else
					{
						$wfh = fopen($pathfile, 'w');
						fwrite($wfh, $dget['config_text']);
						fclose($wfh);
						$retarr = array("status" => true, "message" => "Saved to Hard Drive!");
					}
				}
				else {
					$retarr = array("status" => false, "message" => "The File no existe in the DataBase!");
				}
			}
			elseif ($dget['command'] == "poce_save_as_file")
			{
				$db = $this->db;
				$sql = 'INSERT INTO endpointman_custom_configs (name, original_name, product_id, data) VALUES (?,?,?,?)';
				$q = $db->prepare($sql);
				$ob = $q->execute(array(addslashes($dget['save_as_name']), addslashes($dget['original_name']), $dget['product_select'], addslashes($dget['config_text'])));
				$newidinsert = $db->lastInsertId();
				$retarr = array("status" => true, "message" => "Saved to Database!");

				$retarr['type_file'] = "sql";
				$retarr['location'] = "SQL: ". $dget['save_as_name'];
				$retarr['sendidt'] = $newidinsert;
			}
			else {
				$retarr = array("status" => false, "message" => "Command not valid!");
			}
		}
		elseif ($dget['type_file'] == "sql")
		{
			if ($dget['command'] == "poce_save_file")
			{
				$sql = "UPDATE endpointman_custom_configs SET data = '" . addslashes($dget['config_text']) . "' WHERE id = " . $dget['sendid'];
				sql($sql);
				$retarr = array("status" => true, "message" => "Saved to Database!");
			}
			elseif ($dget['command'] == "poce_save_as_file")
			{
				$db = $this->db;
				$sql = 'INSERT INTO endpointman_custom_configs (name, original_name, product_id, data) VALUES (?,?,?,?)';
				$q = $db->prepare($sql);
				$ob = $q->execute(array(addslashes($dget['save_as_name']), addslashes($dget['original_name']), $dget['product_select'], addslashes($dget['config_text'])));
				$newidinsert = $db->lastInsertId();
				$retarr = array("status" => true, "message" => "Saved to Database!");

				$retarr['type_file'] = "sql";
				$retarr['location'] = "SQL: ". $dget['save_as_name'];
				$retarr['sendidt'] = $newidinsert;
			}
			else {
				$retarr = array("status" => false, "message" => "Command not valid!");
			}
		}
		elseif ($dget['type_file'] == "tfile")
		{
			/*
			
			$db = $this->db;
			$sql = 'INSERT INTO endpointman_custom_configs (name, original_name, product_id, data) VALUES (?,?,?,?)';
			$q = $db->prepare($sql);
@ -790,7 +790,7 @@ class Endpointman_Advanced
			$retarr['type_file'] = "sql";
			$retarr['location'] = "SQL: ". $dget['save_as_name'];
			$retarr['sendidt'] = $newidinsert;
			*/
		}
		else {
			$retarr = array("status" => false, "message" => "Type not valid!");
		}

		$retarr['original_name'] = $dget['original_name'];
		$retarr['file_name'] = $dget['file_name'];
		$retarr['save_as_name'] = $dget['save_as_name'];

		unset($dget);
		return $retarr;
	}
	
	function epm_advanced_poce_delete_config_custom()
	{
		$arrVal['VAR_REQUEST'] = array("product_select", "type_file", "sql_select");
		foreach ($arrVal['VAR_REQUEST'] as $valor) {
			if (! array_key_exists($valor, $_REQUEST)) {
				return array("status" => false, "message" => _("No send value!")." [".$valor."]");
			}
		}

		$dget['type_file'] = $_REQUEST['type_file'];
		$dget['product_select'] = $_REQUEST['product_select'];
		$dget['sql_select'] = $_REQUEST['sql_select'];

		if ($dget['type_file'] == "sql") {
			$sql = "DELETE FROM endpointman_custom_configs WHERE id =" . $dget['sql_select'];
			sql($sql);
			unset ($sql);
			$retarr = array("status" => true, "message" => "File delete ok!");
		}
		else { $retarr = array("status" => false, "message" => _("Type File not valid!")); }

		unset($dget);
		return $retarr;
	}


	/**** FUNCIONES SEC MODULO "epm_advanced\manual_upload" ****/
	public function epm_advanced_manual_upload_list_files_brans_export()
	{
		$path_tmp_dir = $this->PHONE_MODULES_PATH."temp/export/";
		$array_list_files = array();
		$array_count_brand = array();


		$array_list_exception= array(".", "..", ".htaccess");
		if(file_exists($path_tmp_dir))
		{
			if(is_dir($path_tmp_dir))
			{
				$l_files = scandir($path_tmp_dir, 1);
				$i = 0;
				foreach ($l_files as $archivo) {
					if (in_array($archivo, $array_list_exception)) { continue; }

					$pathandfile = $path_tmp_dir.$archivo;
					$brand = substr(pathinfo($archivo, PATHINFO_FILENAME), 0, -11);
					$ftime = substr(pathinfo($archivo, PATHINFO_FILENAME), -10);

					$array_count_brand[] = $brand;
					$array_list_files[$i] = array("brand" => $brand,
							"pathall" => $pathandfile,
							"path" => $path_tmp_dir,
							"file" => $archivo,
							"filename" => pathinfo($archivo, PATHINFO_FILENAME),
							"extension" => pathinfo($archivo, PATHINFO_EXTENSION),
							"timer" => $ftime,
							"timestamp" => strftime("[%Y-%m-%d %H:%M:%S]", $ftime),
							"mime_type" => mime_content_type($pathandfile),
							"is_dir" => is_dir($pathandfile),
							"is_file" => is_file($pathandfile),
							"is_link" => is_link($pathandfile),
							"readlink" => (is_link($pathandfile) == true ? readlink ($pathandfile) : NULL));

					$i++;
				}
				unset ($l_files);

				$array_count_brand = array_count_values($array_count_brand);
				ksort ($array_count_brand);
				$array_count_brand_end = array();

				foreach($array_count_brand as $key => $value) {
					$array_count_brand_end[] = array('name' => $key , 'num' => $value);
				}

				$retarr = array("status" => true, "message" => _("List Done!"), "countlist" => count($array_list_files), "list_files" => $array_list_files, "list_brands" => $array_count_brand_end );
				unset ($array_count_brand_end);
				unset ($array_count_brand);
				unset ($array_list_files);
			}
			else {
				$retarr = array("status" => false, "message" => _("Not is directory: ") . $path_tmp_dir);
			}
		} else {
			$retarr = array("status" => false, "message" => _("Directory no exists: ") . $path_tmp_dir);
		}
		return $retarr;
	}

	public function epm_advanced_manual_upload_brand()
	{
		if (count($_FILES["files"]["error"]) == 0) {
			out(_("Error: Can Not Find Uploaded Files!"));
		}
		else {
			foreach ($_FILES["files"]["error"] as $key => $error) {
				out(sprintf(_("Importing brand file %s..."), $_FILES["files"]["name"][$key]));

				if ($error != UPLOAD_ERR_OK) {
					out(sprintf(_("Error: %s"), $this->file_upload_error_message($error)));
				}
				else {
					$uploads_dir = $this->PHONE_MODULES_PATH . "temp";
					$name = $_FILES["files"]["name"][$key];
					$extension = pathinfo($name, PATHINFO_EXTENSION);
					if ($extension == "tgz") {
						$tmp_name = $_FILES["files"]["tmp_name"][$key];
						$uploads_dir_file = $uploads_dir."/".$name;
						move_uploaded_file($tmp_name, $uploads_dir_file);

						if (file_exists($uploads_dir_file))
						{
							$temp_directory = sys_get_temp_dir() . "/epm_temp/";
							if (!file_exists($temp_directory)) {
								outn(_("Creating EPM temp directory..."));
								if (mkdir($temp_directory) == true) {
									out(_("Done!"));
								}
								else {
									out(_("Error!"));
								}
							}
							if (file_exists($temp_directory))
							{
								if ($this->configmod->get('debug')) {
									outn(sprintf(_("Extracting Tarball %s to %s... "), $uploads_dir_file, $temp_directory));
								} else {
									outn(_("Extracting Tarball... "));
								}
								//TODO: PENDIENTE VALIDAR SI EL EXEC NO DA ERROR!!!!!
								exec("tar -xvf ".$uploads_dir_file." -C ".$temp_directory);
								out(_("Done!"));

								$package = basename($name, ".tgz");
								$package = explode("-",$package);

								if ($this->configmod->get('debug')) {
									out(sprintf(_("Looking for file %s to pass on to update_brand() ... "), $temp_directory.$package[0]));
								} else {
									out(_("Looking file and update brand's ... "));
								}
								if(file_exists($temp_directory.$package[0])) {
									$this->epm_config->update_brand($package[0],FALSE);
									//Note: no need to delete/unlink/rmdir as this is handled in update_brand()
								} else {
									out(_("Please name the Package the same name as your brand!"));
								}
							}
						}
						else {
							out(_("Error: No File Provided!"));
							//echo "File ".$this->PHONE_MODULES_PATH."temp/".$_REQUEST['package']." not found. <br />";
						}
					}
					else {
						out(_("Error: Invalid File Extension!"));
					}
		 		}
			}
		}
	}

	public function epm_advanced_manual_upload_provisioner ()
	{
		if (count($_FILES["files"]["error"]) == 0) {
			out(_("Error: Can Not Find Uploaded Files!"));
		}
		else
		{
			foreach ($_FILES["files"]["error"] as $key => $error) {
				out(sprintf(_("Importing Provisioner file %s..."), $_FILES["files"]["name"][$key]));

				if ($error != UPLOAD_ERR_OK) {
					out(sprintf(_("Error: %s"), $this->file_upload_error_message($error)));
				}
				else {
					$uploads_dir = $this->PHONE_MODULES_PATH . "temp/export";
					$name = $_FILES["files"]["name"][$key];
					$extension = pathinfo($name, PATHINFO_EXTENSION);
					if ($extension == "tgz")
					{
						$tmp_name = $_FILES["files"]["tmp_name"][$key];
						$uploads_dir_file = $uploads_dir."/".$name;
						move_uploaded_file($tmp_name, $uploads_dir_file);

						if (file_exists($uploads_dir_file)) {
							outn(_("Extracting Provisioner Package... "));
							//TODO: Pendiente añadir validacion si exec no da error!!!!
							exec("tar -xvf ".$uploads_dir_file." -C ".$uploads_dir."/");
							out(_("Done!"));

							if(!file_exists($this->PHONE_MODULES_PATH."endpoint")) {
								outn(_("Creating Provisioner Directory... "));
								if (mkdir($this->PHONE_MODULES_PATH."endpoint") == true) {
									out(_("Done!"));
								}
								else {
									out(_("Error!"));
								}
							}

							if(file_exists($this->PHONE_MODULES_PATH."endpoint"))
							{
								$endpoint_last_mod = filemtime($this->PHONE_MODULES_PATH."temp/endpoint/base.php");
								rename($this->PHONE_MODULES_PATH."temp/endpoint/base.php", $this->PHONE_MODULES_PATH."endpoint/base.php");

								outn(_("Updating Last Modified... "));
								$sql = "UPDATE endpointman_global_vars SET value = '".$endpoint_last_mod."' WHERE var_name = 'endpoint_vers'";
								sql($sql);
								out(_("Done!"));
							}

						} else {
							out(_("Error: File Temp no Exists!"));
						}
					} else {
						out(_("Error: Invalid File Extension!"));
					}
				}
			}
		}
	}

	public function epm_advanced_manual_upload_export_brans_available_file()
	{
		if ((! isset($_REQUEST['file_package'])) OR ($_REQUEST['file_package'] == "")) {
			header('HTTP/1.0 404 Not Found', true, 404);
			echo "<h1>Error 404 Not Found</h1>";
			echo "No send name file!";
			die();
		}
		else if(! mb_ereg_match('^[a-z]+-[0-9]+.tgz$', basename($_REQUEST['file_package']))) {
			/* File package name format: brandname-1234567890.tgz */
			header('HTTP/1.0 418 I\'m a teapot', true, 418);
			echo "<h1>Error 418 I'm a teapot</h1>";
			echo "Poor file name!";
			die();
		}
		else {
			$dget['file_package'] = basename($_REQUEST['file_package']);
			$path_tmp_file = $this->PHONE_MODULES_PATH."/temp/export/".$dget['file_package'];

			if (! file_exists($path_tmp_file)) {
				header('HTTP/1.0 404 Not Found', true, 404);
				echo "<h1>Error 404 Not Found</h1>";
				echo "File no exist!";
				die();
			}
			else {
				header('Content-Description: File Transfer');
				header('Content-Type: application/octet-stream');
				header('Content-Disposition: attachment; filename="'.basename($dget['file_package']).'"');
				header('Expires: 0');
				header('Cache-Control: must-revalidate');
				header('Pragma: public');
				header('Content-Length: ' . filesize($path_tmp_file));
				readfile($path_tmp_file);
				exit;
			}
			unset ($path_tmp_file);
			unset ($dget);
		}
		exit;
	}

	public function epm_advanced_manual_upload_export_brans_available()
	{
		if ((! isset($_REQUEST['package'])) OR ($_REQUEST['package'] == "")) {
			out(_("Error: No package set!"));
		}
		elseif ((! is_numeric($_REQUEST['package'])) OR ($_REQUEST['package'] < 0)) {
			out(_("Error: Package not valid!"));
		}
		else {
			$dget['package'] = $_REQUEST['package'];

			$sql = 'SELECT `name`, `directory` FROM `endpointman_brand_list` WHERE `id` = '.$dget['package'].'';
			$row = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

			if ($row == "") {
				out(_("Error: ID Package send not valid, brand not exist!"));
			}
			else {
				outn(sprintf(_("Exporting %s ... "), $row['name']));

				//TODO:Añadir validacion de si se ha creado el dire correctamente!!!!
				if(!file_exists($this->PHONE_MODULES_PATH."/temp/export/")) {
					mkdir($this->PHONE_MODULES_PATH."/temp/export/");
				}
				$time = time();
				//TODO: Pendiente validar si exec no retorna error!!!!!
				exec("tar zcf ".$this->PHONE_MODULES_PATH."temp/export/".$row['directory']."-".$time.".tgz --exclude .svn --exclude firmware -C ".$this->PHONE_MODULES_PATH."/endpoint ".$row['directory']);
				out(_("Done!") . "<br />");


				out(_("Click this link to download:"). "<br />");
				out("<a href='config.php?display=epm_advanced&subpage=manual_upload&command=export_brands_availables_file&file_package=".$row['directory']."-".$time.".tgz' class='btn btn-success btn-lg active btn-block' role='button' target='_blank'>" . _("Here")."</a>");
				//echo "Done! Click this link to download:<a href='modules/_ep_phone_modules/temp/export/".$row['directory']."-".$time.".tgz' target='_blank'>Here</a>";
			}
			unset ($dget);
		}
	}


	/**** FUNCIONES SEC MODULO "epm_advanced\iedl" ****/
	public function epm_advanced_iedl_export($sFileName = "devices_list.csv")
	{
		header("Content-type: text/csv");
		header('Content-Disposition: attachment; filename="'.$sFileName.'"');
		$outstream = fopen("php://output",'w');
		$sql = 'SELECT endpointman_mac_list.mac, endpointman_line_list.ipei, endpointman_brand_list.name, endpointman_model_list.model, endpointman_line_list.ext,endpointman_line_list.line FROM endpointman_mac_list, endpointman_model_list, endpointman_brand_list, endpointman_line_list WHERE endpointman_line_list.mac_id = endpointman_mac_list.id AND endpointman_model_list.id = endpointman_mac_list.model AND endpointman_model_list.brand = endpointman_brand_list.id';
		$result = sql($sql,'getAll',DB_FETCHMODE_ASSOC);
		foreach($result as $row) {
			fputcsv($outstream, $row);
		}
		fclose($outstream);
		exit;
	}

	//Dave B's Q&D file upload security code (http://us2.php.net/manual/en/features.file-upload.php)
	public function epm_advanced_iedl_import()
	{
		if (count($_FILES["files"]["error"]) == 0) {
			out(_("Error: Can Not Find Uploaded Files!"));
		}
		else
		{
			//$allowedExtensions = array("application/csv", "text/plain", "text/csv", "application/vnd.ms-excel");
			$allowedExtensions = array("csv", "txt");
			foreach ($_FILES["files"]["error"] as $key => $error) {
				outn(sprintf(_("Importing CSV file %s ...<br />"), $_FILES["files"]["name"][$key]));

				if ($error != UPLOAD_ERR_OK) {
					out(sprintf(_("Error: %s"), $this->file_upload_error_message($error)));
				}
				else
				{
					//if (!in_array($_FILES["files"]["type"][$key], $allowedExtensions)) {
					if (!in_array(substr(strrchr($_FILES["files"]["name"][$key], "."), 1), $allowedExtensions)) {
						out(sprintf(_("Error: We support only CSV and TXT files, type file %s no support!"), $_FILES["files"]["name"][$key]));
					}
					elseif ($_FILES["files"]["size"][$key] == 0) {
						out(sprintf(_("Error: File %s size is 0!"), $_FILES["files"]["name"][$key]));
					}
					else {
						$uploadfile = $this->LOCAL_PATH . basename($_FILES["files"]["name"][$key]);
						$uploadtemp = $_FILES["files"]["tmp_name"][$key];

						if (move_uploaded_file($uploadtemp, $uploadfile)) {
							//Parse the uploaded file
							$handle = fopen($uploadfile, "r");
							$i = 1;
							while (($device = fgetcsv($handle, filesize($uploadfile))) !== FALSE) {
								if ($device[0] != "") {
									if ($mac = $this->mac_check_clean($device[0])) {
										$sql = "SELECT id FROM endpointman_brand_list WHERE name LIKE '%" . $device[1] . "%' LIMIT 1";
										//$res = sql($sql);
										$res = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);

										if (count(array($res)) > 0) {
											$brand_id = sql($sql, 'getOne');
										//	$brand_id = $brand_id[0];

											$sql_model = "SELECT id FROM endpointman_model_list WHERE brand = " . $brand_id . " AND model LIKE '%" . $device[2] . "%' LIMIT 1";
											$sql_ext = "SELECT extension, name FROM users WHERE extension LIKE '%" . $device[3] . "%' LIMIT 1";

											$line_id = isset($device[4]) ? $device[4] : 1;

											$res_model = sql($sql_model);
											if (count(array($res_model))) {
												$model_id = sql($sql_model, 'getRow', DB_FETCHMODE_ASSOC);
												$model_id = $model_id['id'];

												$res_ext = sql($sql_ext);
												if (count(array($res_ext))) {
													$ext = sql($sql_ext, 'getRow', DB_FETCHMODE_ASSOC);
													$description = $ext['name'];
													$ext = $ext['extension'];
//TODO: PENDIENTE ASIGNAR OBJ
FreePBX::Endpointman()->add_device($mac, $model_id, $ext, 0, $line_id, $description);

													//out(_("Done!"));
												} else {
													out(sprintf(_("Error: Invalid Extension Specified on line %d!"), $i));
												}
											} else {
												out(sprintf(_("Error: Invalid Model Specified on line %d!"), $i));
											}
										} else {
											out(sprintf(_("Error: Invalid Brand Specified on line %d!"), $i));
										}
									} else {
										out(sprintf(_("Error: Invalid Mac on line %d!"), $i));
									}
								}
								$i++;

							}
							fclose($handle);
							unlink($uploadfile);
							out(_("<font color='#FF0000'><b>Please reboot & rebuild all imported phones</b></font>"));
						} else {
							out(_("Error: Possible file upload attack!"));
						}
					}
				}
			}
		}
	}


	/**** FUNCIONES SEC MODULO "epm_advanced\oui_manager" ****/
	private function epm_advanced_oui_remove()
	{
		//TODO: Añadir validacion de si es custom o no
		if ((! isset($_REQUEST['id_del'])) OR ($_REQUEST['id_del'] == "")) {
			$retarr = array("status" => false, "message" => _("No ID set!"));
		}
		elseif ((! is_numeric($_REQUEST['id_del'])) OR ($_REQUEST['id_del'] < 0)) {
			$retarr = array("status" => false, "message" => _("ID  not valid!"), "id" => $_REQUEST['id']);
		}
		else
		{
			$dget['id'] = $_REQUEST['id_del'];

			$sql = "DELETE FROM endpointman_oui_list WHERE id = " . $dget['id'];
			sql($sql);

			$retarr = array("status" => true, "message" => "OK", "id" => $dget['id']);
			unset($dget);
		}
		return $retarr;
	}

	private function epm_advanced_oui_add()
	{
		//TODO: Pendiente añadir isExiste datos.
		if ((! isset($_REQUEST['number_new_oui'])) OR ($_REQUEST['number_new_oui'] == "")) {
			$retarr = array("status" => false, "message" => _("No OUI set!"));
		}
		elseif ((! isset($_REQUEST['brand_new_oui'])) OR ($_REQUEST['brand_new_oui'] == "")) {
			$retarr = array("status" => false, "message" => _("No Brand set!"));
		}
		else {
			$dget['oui'] = $_REQUEST['number_new_oui'];
			$dget['brand'] = $_REQUEST['brand_new_oui'];

			$sql = "INSERT INTO  endpointman_oui_list (oui, brand, custom) VALUES ('" . $dget['oui'] . "',  '" . $dget['brand'] . "',  '1')";
			sql($sql);

			$retarr = array("status" => true, "message" => "OK", "oui" => $dget['oui'], "brand" => $dget['brand']);
			unset($dget);
		}
		return $retarr;
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
     * Taken from PHP.net. A list of errors returned when uploading files.
     * @param <type> $error_code
     * @return string
     */
    function file_upload_error_message($error_code) {
    	switch ($error_code) {
    		case UPLOAD_ERR_INI_SIZE:
    			return _('The uploaded file exceeds the upload_max_filesize directive in php.ini');
    		case UPLOAD_ERR_FORM_SIZE:
    			return _('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form');
    		case UPLOAD_ERR_PARTIAL:
    			return _('The uploaded file was only partially uploaded');
    		case UPLOAD_ERR_NO_FILE:
    			return _('No file was uploaded');
    		case UPLOAD_ERR_NO_TMP_DIR:
    			return _('Missing a temporary folder');
    		case UPLOAD_ERR_CANT_WRITE:
    			return _('Failed to write file to disk');
    		case UPLOAD_ERR_EXTENSION:
    			return _('File upload stopped by extension');
    		default:
    			return _('Unknown upload error');
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



}
