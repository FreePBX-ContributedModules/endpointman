<?php
/**
 * Endpoint Manager Object Module - Sec Devices
 *
 * @author Javier Pastor
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */

namespace FreePBX\modules;

class Endpointman_Devices
{
	public $freepbx;
	public $db;
	public $config;
	public $configmod;

	public function __construct($freepbx, $cfgmod) 
	{
		$this->freepbx = $freepbx;
		$this->db = $freepbx->Database;
		$this->config = $freepbx->Config;
		$this->configmod = $cfgmod;			
	}

	public function myShowPage(&$pagedata) {
		if(empty($pagedata))
		{
			$pagedata['main'] = array(
					"name" => _("Devices"),
					"page" => 'views/epm_devices_main.page.php'
			);
		}
	}

	public function ajaxRequest($req, &$setting) {
		/*
		$arrVal = array("");
		if (in_array($req, $arrVal)) {
			$setting['authenticate'] = true;
			$setting['allowremote'] = false;
			return true;
		}
		*/
		return false;
	}
	
    public function ajaxHandler($module_tab = "", $command = "") 
	{
		$retarr = "";
		if ($module_tab == "manager")
		{
			switch ($command)
			{
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
		return "";
	}
	
	public function getActionBar($request) {
		return "";
	}
	
}