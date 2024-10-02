<?php
/**
 * Endpoint Manager Object Module - Sec Templates
 *
 * @author Javier Pastor
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */

namespace FreePBX\modules;

class Endpointman_Templates
{
	public function __construct($freepbx = null, $cfgmod = null, $epm_config, $eda) 
	{
		$this->freepbx = $freepbx;
		$this->db = $freepbx->Database;
		$this->config = $freepbx->Config;
		$this->configmod = $cfgmod;
		$this->epm_config = $epm_config;
		$this->eda = $eda;
	}

	public function myShowPage(&$pagedata) {
		if(empty($pagedata))
		{
			$pagedata['manager'] = array(
				"name" => _("Current Templates"),
				"page" => 'views/epm_templates_manager.page.php'
			);
			$pagedata['editor'] = array(
					"name" => _("Template Editor"),
					"page" => 'views/epm_templates_editor.page.php'
			);
		}
	}

	public function ajaxRequest($req, &$setting) {
		$arrVal = array("model_clone", "list_current_template", "add_template", "del_template", "custom_config_get_gloabl", "custom_config_update_gloabl", "custom_config_reset_gloabl", "list_files_edit");
		if (in_array($req, $arrVal)) {
			$setting['authenticate'] = true;
			$setting['allowremote'] = false;
			return true;
		}
		else 
		{
			return false;
		}
	}
	
    public function ajaxHandler($module_tab = "", $command = "") 
	{
		$retarr = "";
		if ($module_tab == "manager")
		{
			switch ($command)
			{
				case "list_current_template":
					$retarr = $this->epm_templates_list_current_templates();
					break;
					
				case "model_clone":
					$retarr = $this->epm_templates_model_clone();
					break;
					
				case "add_template":
					$retarr = $this->epm_templates_add_template();
					break;
					
				case "del_template":
					$retarr = $this->epm_templates_del_template();
					break;

				default:
					$retarr = array("status" => false, "message" => _("Command not found!") . " [" .$command. "]");
					break;
			}
		}
		elseif ($module_tab == "editor")
		{
			switch ($command)
			{
				case "custom_config_get_gloabl":
					$retarr = $this->epm_template_custom_config_get_global();
					break;
				
				case "custom_config_update_gloabl":
					$retarr = $this->epm_template_custom_config_update_global();
					break;
				
				case "custom_config_reset_gloabl":
					$retarr = $this->epm_template_custom_config_reset_global();
					break;
					
				case "list_files_edit":
				/*
					$return = array();
					$return[] = array('value' => 'va11', 'txt' => 'txt1', 'select' => "OFF");
					$return[] = array('value' => 'va12', 'txt' => 'txt2', 'select' => "ON");
					$return[] = array('value' => 'va13', 'txt' => 'txt3', 'select' => "OFF");
				*/
					return $this->edit_template_display_files($_REQUEST['idsel'],$_REQUEST['custom'], $_REQUEST['namefile']);
					break;
					
				default:
					$retarr = array("status" => false, "message" => _("Command not found!") . " [" .$command. "]");
					break;
			}
		}
		else {
			$retarr = array("status" => false, "message" => _("Tab not found!") . " [" .$module_tab. "]");
		}
		return $retarr;
	}
	
	public function doConfigPageInit($module_tab = "", $command = "") {
		
	}
	
	public function getRightNav($request) {
		if(isset($request['subpage']) && $request['subpage'] == "editor") {
			return load_view(__DIR__."/views/epm_templates/editor.views.rnav.php",array());
		} else {
			return '';
		}
	}
	
	public function getActionBar($request) {
		$buttons = array();
        switch($request['subpage']) {
            case 'editor':
                $buttons = array(
					'delete' => array(
                        'name' => 'delete',
                        'id' => 'delete',
                        'value' => _('Delete'),
                        'hidden' => ''
                    ),
					'save' => array(
                        'name' => 'submit',
                        'id' => 'save',
                        'value' => _('Save'),
                        'hidden' => ''
                    )
                );
				
				if(empty($request['idsel']) && empty($request['custom'])){
					$buttons = NULL;
				}
            	break;
				
			default:
        }
        return $buttons;
	}
	
	
	
	
	
	
	public function epm_template_custom_config_get_global()
	{
		if (! isset($_REQUEST['custom'])) {
			$retarr = array("status" => false, "message" => _("No send Custom Value!"));
		}
		elseif (! isset($_REQUEST['tid'])) {
			$retarr = array("status" => false, "message" => _("No send TID!"));
		}
		elseif (! is_numeric($_REQUEST['tid'])) {
			$retarr = array("status" => false, "message" => _("TID is not number!"));
		}
		else 
		{
			$dget['custom'] = $_REQUEST['custom'];
			$dget['tid'] = $_REQUEST['tid'];
			
			if($dget['custom'] == 0) {
				//This is a group template
		        $sql = 'SELECT global_settings_override FROM endpointman_template_list WHERE id = '.$dget['tid'];
			} else {
				//This is an individual template
		        $sql = 'SELECT global_settings_override FROM endpointman_mac_list WHERE id = '.$dget['tid'];;
			}
			$settings = sql($sql, 'getOne');

			if ((isset($settings)) and (strlen($settings) > 0)) {
				$settings = unserialize($settings);
				//$settings['tz'] = FreePBX::Endpointman()->listTZ(FreePBX::Endpointman()->configmod->get("tz"));
			} 
			else {
				$settings['srvip'] = ""; //$this->configmod->get("srvip");
				$settings['ntp'] = ""; //$this->configmod->get("ntp");
				$settings['config_location'] = ""; //$this->configmod->get("config_location");
				$settings['tz'] = $this->configmod->get("tz");
				$settings['server_type'] = $this->configmod->get("server_type");
			}
    		
			$retarr = array("status" => true, "settings" => $settings, "message" => _("Global Config Read OK!"));
			unset($dget);
		}
		return $retarr;
	}
	
	
	public function epm_template_custom_config_update_global ()
	{
		if (! isset($_REQUEST['custom'])) {
			$retarr = array("status" => false, "message" => _("No send Custom Value!"));
		}
		elseif (! isset($_REQUEST['tid'])) {
			$retarr = array("status" => false, "message" => _("No send TID!"));
		}
		elseif (! is_numeric($_REQUEST['tid'])) {
			$retarr = array("status" => false, "message" => _("TID is not number!"));
		}
		else 
		{
			$dget['custom'] = $_REQUEST['custom'];
			$dget['tid'] = $_REQUEST['tid'];
			
			
			$_REQUEST['srvip'] = trim($_REQUEST['srvip']);  #trim whitespace from IP address
			$_REQUEST['config_loc'] = trim($_REQUEST['config_loc']);  #trim whitespace from Config Location
	
			$settings_warning = "";
			if (strlen($_REQUEST['config_loc']) > 0) {
				//No trailing slash. Help the user out and add one :-)
				if($_REQUEST['config_loc'][strlen($_REQUEST['config_loc'])-1] != "/") {
					$_REQUEST['config_loc'] = $_REQUEST['config_loc'] ."/";
				}
				
				if((isset($_REQUEST['config_loc'])) AND ($_REQUEST['config_loc'] != "")) {
					if((file_exists($_REQUEST['config_loc'])) AND (is_dir($_REQUEST['config_loc']))) {
						if(is_writable($_REQUEST['config_loc'])) {
							$_REQUEST['config_loc'] = $_REQUEST['config_loc'];
						} else {
							$settings_warning = _("Directory Not Writable!");
							$_REQUEST['config_loc'] = $this->configmod->get('config_location');
						}
					} else {
						$settings_warning = _("Not a Vaild Directory");
						$_REQUEST['config_loc'] = $this->configmod->get('config_location');
					}
				} else {
					$settings_warning = _("No Configuration Location Defined!");
					$_REQUEST['config_loc'] = $this->configmod->get('config_location');
				}
			}
			
			$settings['config_location'] = $_REQUEST['config_loc'];
			$settings['server_type'] = (isset($_REQUEST['server_type']) ? $_REQUEST['server_type'] : "");	//REVISAR NO ESTABA ANTES
			$settings['srvip'] = (isset($_REQUEST['srvip']) ? $_REQUEST['srvip'] : "");
			$settings['ntp'] = (isset($_REQUEST['ntp_server']) ? $_REQUEST['ntp_server'] : "");
			$settings['tz'] = (isset($_REQUEST['tz']) ? $_REQUEST['tz'] : "");
			$settings_ser = serialize($settings);
			unset($settings);
			
			if($dget['custom'] == 0) {
				//This is a group template
				$sql = "UPDATE endpointman_template_list SET global_settings_override = '".addslashes($settings_ser)."' WHERE id = ".$dget['tid'];
			} else {
				//This is an individual template
				$sql = "UPDATE endpointman_mac_list SET global_settings_override = '".addslashes($settings_ser)."' WHERE id = ".$dget['tid'];
			}
			unset($settings_ser);
			sql($sql);
			
			if (strlen($settings_warning) > 0) { $settings_warning = " ".$settings_warning; }
			$retarr = array("status" => true, "message" => _("Updated!").$settings_warning);
			unset($dget);
		}
		return $retarr;
	}
	
	
	public function epm_template_custom_config_reset_global()
	{
		if (! isset($_REQUEST['custom'])) {
			$retarr = array("status" => false, "message" => _("No send Custom Value!"));
		}
		elseif (! isset($_REQUEST['tid'])) {
			$retarr = array("status" => false, "message" => _("No send TID!"));
		}
		elseif (! is_numeric($_REQUEST['tid'])) {
			$retarr = array("status" => false, "message" => _("TID is not number!"));
		}
		else 
		{
			$dget['custom'] = $_REQUEST['custom'];
			$dget['tid'] = $_REQUEST['tid'];
			
			if($dget['custom'] == 0) {
				//This is a group template
				$sql = "UPDATE endpointman_template_list SET global_settings_override = NULL WHERE id = ".$dget['tid'];
			} else {
				//This is an individual template
				$sql = "UPDATE endpointman_mac_list SET global_settings_override = NULL WHERE id = ".$dget['tid'];
			}
			sql($sql);
			
			$retarr = array("status" => true, "message" => _("Globals Reset to Default!"));
			unset($dget);
		}
		return $retarr;
	}
	
	
	
	
	/**** FUNCIONES SEC MODULO "epm_template\manager" ****/
	public function epm_templates_del_template() 
	{
		if (! isset($_REQUEST['idsel'])) {
			$retarr = array("status" => false, "message" => _("No send ID!"));
		}
		elseif (! is_numeric($_REQUEST['idsel'])) {
			$retarr = array("status" => false, "message" => _("ID is not number!"));
		}
		elseif ($_REQUEST['idsel'] <= 0) {
			$retarr = array("status" => false, "message" => _("ID send is negative!"));
		}
		else {
			$dget['idsel'] = $_REQUEST['idsel'];
			
			$sql = "DELETE FROM endpointman_template_list WHERE id = ". $dget['idsel'];
			sql($sql);
			$sql = "UPDATE endpointman_mac_list SET template_id = 0 WHERE template_id = ".$dget['idsel'];
			sql($sql);
			
			$retarr = array("status" => true, "message" => _("Delete Template OK!"));
			unset($dget);
		}
		return $retarr;
	}
	
	public function epm_templates_add_template ()
	{
		$arrVal['VAR_REQUEST'] = array("newnametemplate", "newproductselec", "newclonemodel");
		foreach ($arrVal['VAR_REQUEST'] as $valor) {
			if (! array_key_exists($valor, $_REQUEST)) {
				return array("status" => false, "message" => _("No send value!")." [".$valor."]");
			}
		}
		
		$arrVal['VAR_IS_NUM'] = array("newproductselec", "newclonemodel");
		foreach ($arrVal['VAR_IS_NUM'] as $valor) {
			if (! is_numeric($_REQUEST[$valor])) {
				return array("status" => false, "message" => _("Value send is not number!")." [".$valor."]");
			}
		}
		
		if (empty($_REQUEST['newnametemplate'])) {
			$retarr = array("status" => false, "message" => _("Name is null!"));
		}
		elseif ($_REQUEST['newproductselec'] <= 0) {
			$retarr = array("status" => false, "message" => _("Product send is negative!"));
		}
		elseif ($_REQUEST['newclonemodel'] <= 0) {
			$retarr = array("status" => false, "message" => _("Clone Model send is negative!"));
		}
		else {
			$dget['newnametemplate'] = $_REQUEST['newnametemplate'];
			$dget['newproductselec'] = $_REQUEST['newproductselec'];
			$dget['newclonemodel'] = $_REQUEST['newclonemodel'];

			$db = $this->db;
			$sql = "INSERT INTO endpointman_template_list (product_id, name, model_id) VALUES (?,?,?)";
			$q = $db->prepare($sql);
			$ob = $q->execute(array($dget['newproductselec'], addslashes($dget['newnametemplate']), $dget['newclonemodel']));
			$newid = $db->lastInsertId();
			//$this->edit_template_display($newid,0);
			
			$retarr = array("status" => true, "message" => _("Add New Template OK!"), "newid" => $newid);
			unset($dget);
		}
		return $retarr;
	}
	
	public function epm_templates_model_clone () 
	{
		if (! isset($_REQUEST['id'])) {
			$retarr = array("status" => false, "message" => _("No send ID!"));
		}
		elseif (! is_numeric($_REQUEST['id'])) {
			$retarr = array("status" => false, "message" => _("ID send is not number!"));
		}
		elseif ($_REQUEST['id'] <= 0) {
			$retarr = array("status" => false, "message" => _("ID send is number not valid!"));
		}
		else
		{
			$dget['id'] = $_REQUEST['id'];
			
			$i=0;
			$out = array();
			$sql = "SELECT endpointman_model_list.id, endpointman_model_list.model as model FROM endpointman_model_list, endpointman_product_list WHERE endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_model_list.enabled = 1 AND endpointman_model_list.hidden = 0 AND product_id = '". $dget['id']."'";
			$result = sql($sql,'getAll', DB_FETCHMODE_ASSOC);
			foreach($result as $row) {
				$out[$i]['optionValue'] = $row['id'];
				$out[$i]['optionDisplay'] = $row['model'];
				$i++;
			}
			$retarr = array("status" => true, "message" => _("Generate list Ok!"), "listopt" => $out);
			
			unset($dget);
		}
		return $retarr;
	}
	
	public function epm_templates_list_current_templates ()
	{
	
		$sql = 'SELECT endpointman_template_list.*, endpointman_product_list.short_name as model_class, endpointman_model_list.model as model_clone, endpointman_model_list.enabled FROM endpointman_template_list, endpointman_model_list, endpointman_product_list WHERE endpointman_model_list.hidden = 0 AND endpointman_template_list.model_id = endpointman_model_list.id AND endpointman_template_list.product_id = endpointman_product_list.id';
		$template_list = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
		$i = 0;
		$row_out = array();
		foreach($template_list as $row) {
			$row_out[$i] = $row;
			$row_out[$i]['custom'] = 0;
			if(!$row['enabled']) {
				$row_out[$i]['model_clone'] = $row_out[$i]['model_clone'];
			}
			$i++;
		}
		
		$sql = 'SELECT endpointman_mac_list.mac, endpointman_mac_list.id, endpointman_mac_list.model, endpointman_model_list.model as model_clone, endpointman_product_list.short_name as model_class FROM endpointman_mac_list, endpointman_model_list, endpointman_product_list WHERE  endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_mac_list.global_custom_cfg_data IS NOT NULL AND endpointman_model_list.id = endpointman_mac_list.model AND endpointman_mac_list.template_id = 0';
		$template_list = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
		foreach($template_list as $row) {
			$sql = 'SELECT  description , line FROM  endpointman_line_list WHERE  mac_id ='. $row['id'].' ORDER BY line ASC';
			$line_list = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
			$description = "";
			$c = 0;
			foreach($line_list as $line_row) {
				if($c > 0) {
					$description .= ", ";
				}
				$description .= $line_row['description'];
				$c++;
			}
			$row_out[$i] = $row;
			$row_out[$i]['custom'] = 1;
			$row_out[$i]['name'] = $row['mac'];
			$row_out[$i]['description'] = $description;
			$i++;
		}
		
	/*
		//$sql = 'SELECT endpointman_oui_list.id, endpointman_oui_list.oui , endpointman_brand_list.name, endpointman_oui_list.custom FROM endpointman_oui_list , endpointman_brand_list WHERE endpointman_oui_list.brand = endpointman_brand_list.id ORDER BY endpointman_oui_list.oui ASC';
		$sql = 'SELECT T1.id, T1.oui, T2.name, T1.custom FROM endpointman_oui_list as T1 , endpointman_brand_list as T2 WHERE T1.brand = T2.id ORDER BY T1.oui ASC';
		$data = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
		$ret = array();
		foreach ($data as $item) {
			$ret[] = array('id' => $item['id'], 'oui' => $item['oui'], 'brand' => $item['name'], 'custom' => $item['custom']);
		}
		*/
		return $row_out;
	}
	
	
	
	
	
	
	
	
	
	function edit_template_display_files($id, $custom, $namefile = "")
	{
    	if ($custom == 0) {
    		$sql = "SELECT model_id FROM endpointman_template_list WHERE id=" . $id;
    	} else {
    		$sql = "SELECT model FROM endpointman_mac_list WHERE id=" . $id;
    	}
    	$model_id = sql($sql, 'getOne');
    	if (!$this->epm_config->sync_model($model_id)) {
    		die("unable to sync local template files - TYPE:" . $custom);
    	}
		
    	$dReturn = array();
		if ($custom == 0) {
			$sql = "SELECT endpointman_model_list.max_lines, endpointman_model_list.model as model_name, endpointman_template_list.global_custom_cfg_data,  endpointman_product_list.config_files, endpointman_product_list.short_name, endpointman_product_list.id as product_id, endpointman_model_list.template_data, endpointman_model_list.id as model_id, endpointman_template_list.* FROM endpointman_product_list, endpointman_model_list, endpointman_template_list WHERE endpointman_product_list.id = endpointman_template_list.product_id AND endpointman_template_list.model_id = endpointman_model_list.id AND endpointman_template_list.id = " . $id;
		} else {
			$sql = "SELECT endpointman_model_list.max_lines, endpointman_model_list.model as model_name, endpointman_mac_list.global_custom_cfg_data, endpointman_product_list.config_files, endpointman_mac_list.*, endpointman_line_list.*, endpointman_model_list.id as model_id, endpointman_model_list.template_data, endpointman_product_list.id as product_id, endpointman_product_list.short_name, endpointman_product_list.cfg_dir, endpointman_brand_list.directory FROM endpointman_brand_list, endpointman_mac_list, endpointman_model_list, endpointman_product_list, endpointman_line_list WHERE endpointman_mac_list.id=" . $id . " AND endpointman_mac_list.id = endpointman_line_list.mac_id AND endpointman_mac_list.model = endpointman_model_list.id AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id";
		}
		$row = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
		
		
		if ($row['config_files_override'] == "") {
			$config_files_saved = "";
		} else {
			$config_files_saved = unserialize($row['config_files_override']);
		}
		$config_files_list = explode(",", $row['config_files']);
		asort($config_files_list);
		
		$i = 0;
		$b = 0;
		$alt_configs = array();
		$only_configs = array();
		foreach ($config_files_list as $files) 
		{
			if ($namefile != $files)  { continue; }
			
			$only_configs[$b]['id'] = $b;
			$only_configs[$b]['id_d'] = $id;
			$only_configs[$b]['id_p'] = $row['product_id'];
			$only_configs[$b]['name'] = $files;
			$only_configs[$b]['select'] = "ON";
			
			$sql = "SELECT * FROM  endpointman_custom_configs WHERE product_id = '" . $row['product_id'] . "' AND original_name = '" . $files . "'";
			$alt_configs_list = sql($sql, 'getAll', DB_FETCHMODE_ASSOC );
			
			if ( count(array($alt_configs_list)) > 0) 
			{
				$files = str_replace(".", "_", $files);
				foreach ($alt_configs_list as $ccf) 
				{
					$cf_key = $files;
					if ((isset($config_files_saved[$cf_key])) AND (is_array($config_files_saved)) AND ($config_files_saved[$cf_key] == $ccf['id'])) {
						$alt_configs[$i]['select'] = 'ON';
						$only_configs[$b]['select'] = "OFF";
					}
					else {
						$alt_configs[$i]['select'] = 'OFF';
					}
					$alt_configs[$i]['id'] = $ccf['id'];
					$alt_configs[$i]['id_p'] = $row['product_id'];
					$alt_configs[$i]['name'] = $ccf['name'];
					$alt_configs[$i]['name_original'] = $files;
					
					$i++;
				}
			}
		}
		
		$dReturn['only_configs'] = $only_configs;
		$dReturn['alt_configs'] = $alt_configs;
		
    	return $dReturn;		
	}
	
	function edit_template_display_files_list($id, $custom)
	{
    	if ($custom == 0) {
    		$sql = "SELECT model_id FROM endpointman_template_list WHERE id=" . $id;
    	} else {
    		$sql = "SELECT model FROM endpointman_mac_list WHERE id=" . $id;
    	}
    	$model_id = sql($sql, 'getOne');
    	if (!$this->epm_config->sync_model($model_id)) {
    		die("unable to sync local template files - TYPE:" . $custom);
    	}
		

		if ($custom == 0) {
			$sql = "SELECT endpointman_model_list.max_lines, endpointman_model_list.model as model_name, endpointman_template_list.global_custom_cfg_data,  endpointman_product_list.config_files, endpointman_product_list.short_name, endpointman_product_list.id as product_id, endpointman_model_list.template_data, endpointman_model_list.id as model_id, endpointman_template_list.* FROM endpointman_product_list, endpointman_model_list, endpointman_template_list WHERE endpointman_product_list.id = endpointman_template_list.product_id AND endpointman_template_list.model_id = endpointman_model_list.id AND endpointman_template_list.id = " . $id;
		} else {
			$sql = "SELECT endpointman_model_list.max_lines, endpointman_model_list.model as model_name, endpointman_mac_list.global_custom_cfg_data, endpointman_product_list.config_files, endpointman_mac_list.*, endpointman_line_list.*, endpointman_model_list.id as model_id, endpointman_model_list.template_data, endpointman_product_list.id as product_id, endpointman_product_list.short_name, endpointman_product_list.cfg_dir, endpointman_brand_list.directory FROM endpointman_brand_list, endpointman_mac_list, endpointman_model_list, endpointman_product_list, endpointman_line_list WHERE endpointman_mac_list.id=" . $id . " AND endpointman_mac_list.id = endpointman_line_list.mac_id AND endpointman_mac_list.model = endpointman_model_list.id AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id";
		}
		$row = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
		$config_files_list = explode(",", $row['config_files']);
		asort($config_files_list);
		
		$i = 0;
		$b = 0;
		$dReturn = array();
		foreach ($config_files_list as $files) 
		{
			$dReturn[$b]['id'] = $b;
			$dReturn[$b]['id_d'] = $id;
			$dReturn[$b]['id_p'] = $row['product_id'];
			$dReturn[$b]['name'] = $files;
			$b++;
		}
		unset($config_files_list);

    	return $dReturn;		
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
    
    	$model_id = sql($sql, 'getOne');
    
    	//Make sure the model data from the local confg files are stored in the database and vice-versa. Serious errors will occur if the database is not in sync with the local file
    	if (!$this->epm_config->sync_model($model_id)) {
    		die("unable to sync local template files - TYPE:" . $custom);
    	}
   
    	$dReturn = array();

    	
		//Determine if we are dealing with a general template or a specific [for that phone only] template (custom =0 means general)
		if ($custom == 0) {
			$sql = "SELECT endpointman_model_list.max_lines, endpointman_model_list.model as model_name, endpointman_template_list.global_custom_cfg_data,  endpointman_product_list.config_files, endpointman_product_list.short_name, endpointman_product_list.id as product_id, endpointman_model_list.template_data, endpointman_model_list.id as model_id, endpointman_template_list.* FROM endpointman_product_list, endpointman_model_list, endpointman_template_list WHERE endpointman_product_list.id = endpointman_template_list.product_id AND endpointman_template_list.model_id = endpointman_model_list.id AND endpointman_template_list.id = " . $id;
		} else {
			$sql = "SELECT endpointman_model_list.max_lines, endpointman_model_list.model as model_name, endpointman_mac_list.global_custom_cfg_data, endpointman_product_list.config_files, endpointman_mac_list.*, endpointman_line_list.*, endpointman_model_list.id as model_id, endpointman_model_list.template_data, endpointman_product_list.id as product_id, endpointman_product_list.short_name, endpointman_product_list.cfg_dir, endpointman_brand_list.directory FROM endpointman_brand_list, endpointman_mac_list, endpointman_model_list, endpointman_product_list, endpointman_line_list WHERE endpointman_mac_list.id=" . $id . " AND endpointman_mac_list.id = endpointman_line_list.mac_id AND endpointman_mac_list.model = endpointman_model_list.id AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id";
		}
		$row = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
		
		
		$dReturn['template_editor_display'] = 1;
		
		//Let the template system know if we are working with a general template or a specific [for that phone only] template
		$dReturn['custom'] = $custom;
    	 if ($custom) 
    	 {
			$dReturn['ext'] = $row['ext'];
    	 } 
    	 else 
    	 {
    	 	$dReturn['template_name'] = $row['name'];
    	 }
		$dReturn['product'] = $row['short_name'];
		$dReturn['model'] = $row['model_name'];

		if ($ma = $this->models_available($row['model_id'], NULL, $row['product_id'])) {
			$dReturn['models_ava'] = $ma;
		}

		if (isset($_REQUEST['maxlines'])) {
			$areas = $this->areaAvailable($row['model_id'], $_REQUEST['maxlines']);
		} else {
			$areas = $this->areaAvailable($row['model_id'], 1);
		}
		$dReturn['area_ava'] = $areas;
    	
		//Start the display of the html file in the product folder
		if ($row['config_files_override'] == "") {
			$config_files_saved = "";
		} else {
			$config_files_saved = unserialize($row['config_files_override']);
		}
		$config_files_list = explode(",", $row['config_files']);
		asort($config_files_list);
		
		$alt = 0;
		$i = 0;
		$b = 0;
		$only_configs = array();
		foreach ($config_files_list as $files) {
			$sql = "SELECT * FROM  endpointman_custom_configs WHERE product_id = '" . $row['product_id'] . "' AND original_name = '" . $files . "'";
			$alt_configs_list_count = sql($sql, 'getAll', DB_FETCHMODE_ASSOC );
			if (! empty($alt_configs_list_count)) {
				$alt_configs_list = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
				$alt_configs[$i]['name'] = $files;
				$alt_configs[$i]['id_p'] = $row['product_id'];
				$files = str_replace(".", "_", $files);
				$h = 0;
				foreach ($alt_configs_list as $ccf) {
					$alt_configs[$i]['list'][$h]['id'] = $ccf['id'];
					$cf_key = $files;
					if ((isset($config_files_saved[$cf_key])) AND (is_array($config_files_saved)) AND ($config_files_saved[$cf_key] == $ccf['id'])) {
						$alt_configs[$i]['list'][$h]['selected'] = 'selected';
					}
					$alt_configs[$i]['list'][$h]['id'] = $h;
					$alt_configs[$i]['list'][$h]['name'] = $ccf['name'];
					$h++;
				}
				$alt = 1;
			} 
			else {
				$only_configs[$b]['id'] = $b;
				$only_configs[$b]['id_d'] = $id;
				$only_configs[$b]['id_p'] = $row['product_id'];
				$only_configs[$b]['name'] = $files;
				$b++;
			}
			$i++;
		}
		
		$dReturn['only_configs'] = $only_configs;
		$dReturn['alt_configs'] = $alt_configs;
		$dReturn['alt'] = $alt;
		
		if (!isset($_REQUEST['maxlines'])) {
			$maxlines = 1;
		} else {
			$maxlines = $_REQUEST['maxlines'];
		}
		if ($row['template_data'] != "") {
			$out = $this->generate_gui_html($row['template_data'], $row['global_custom_cfg_data'], TRUE, NULL, $maxlines);
		} else {
			$out = "No Template Data has been defined for this Product<br />";
		}
		
		$dReturn['template_editor'] = $out;
		$dReturn['hidden_id'] = $row['id'];
		$dReturn['hidden_custom'] = $custom;

    	return $dReturn;
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
    		if (! $this->configmod->isExiste('new')) {
    			$this->error['modelsAvailable'] = "You need to enable at least ONE model";
    		}
    		return(FALSE);
    	} else {
    		return($temp);
    	}
    }
	
	
	
	function areaAvailable($model, $area=NULL) {
    	$sql = "SELECT max_lines FROM endpointman_model_list WHERE id = '" . $model . "'";
    	$count = sql($sql, 'getOne');
    
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
    								$key = "loop|" . $tv[0] . "_" . $var_name . (isset($var_items['loop_count']) ? "_" . $var_items['loop_count'] : '');
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
    
    	if (($this->configmod->get('enable_ari')) AND ($admin) AND ($cfg_data['type'] != "break") AND ($cfg_data['type'] != "group") AND ($template_type == 'GENERAL')) {
    
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
	
	
	
	
	
	
	
	
}