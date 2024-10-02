<?php
/**
 * Endpoint Manager Object Module
 *
 * @author Andrew Nagy
 * @author Javier Pastor
 * @license MPL / GPLv2 / LGPL
 * @package Provisioner
 */

namespace FreePBX\modules;

function format_txt($texto = "", $css_class = "", $remplace_txt = array())
{
	if (count($remplace_txt) > 0)
	{
		foreach ($remplace_txt as $clave => $valor) {
			$texto = str_replace($clave, $valor, $texto);
		}
	}
	return '<p ' . ($css_class != '' ? 'class="' . $css_class . '"' : '') . '>'.$texto.'</p>';
}

function generate_xml_from_array ($array, $node_name, &$tab = -1)
{
	$tab++;
	$xml ="";
	if (is_array($array) || is_object($array)) {
		foreach ($array as $key=>$value) {
			if (is_numeric($key)) {
				$key = $node_name;
			}

			$xml .= str_repeat("	", $tab). '<' . $key . '>' . "\n";
			$xml .= generate_xml_from_array($value, $node_name, $tab);
			$xml .= str_repeat("	", $tab). '</' . $key . '>' . "\n";

		}
	} else {
		$xml = str_repeat("	", $tab) . htmlspecialchars($array, ENT_QUOTES) . "\n";
	}
	$tab--;
	return $xml;
}


class Endpointman implements \BMO {

	//public $epm_config;


	public $db; //Database from FreePBX
	public $eda; //endpoint data abstraction layer
	public $tpl; //Template System Object (RAIN TPL)
	//public $system;

    public $error; //error construct
    public $message; //message construct

	public $UPDATE_PATH;
    public $MODULES_PATH;
	public $LOCAL_PATH;
	public $PHONE_MODULES_PATH;
	public $PROVISIONER_BASE;


	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Not given a FreePBX Object");
		}
		require_once('lib/json.class.php');
		require_once('lib/Config.class.php');
		require_once('lib/epm_system.class.php');
		require_once('lib/datetimezone.class.php');
		require_once('lib/epm_data_abstraction.class.php');
		//require_once("lib/RainTPL.class.php");

		$this->freepbx = $freepbx;
		$this->db = $freepbx->Database;
		$this->config = $freepbx->Config;
		$this->configmod = new Endpointman\Config();
		$this->system = new epm_system();
		$this->eda = new epm_data_abstraction($this->config, $this->configmod);


		$this->configmod->set('disable_epm', FALSE);
		$this->eda->global_cfg = $this->configmod->getall();

        //Generate empty array
        $this->error = array();
        $this->message = array();


		$this->configmod->set('tz', $this->config->get('PHPTIMEZONE'));
		date_default_timezone_set($this->configmod->get('tz'));

		$this->UPDATE_PATH = $this->configmod->get('update_server');
        $this->MODULES_PATH = $this->config->get('AMPWEBROOT') . '/admin/modules/';

define("UPDATE_PATH", $this->UPDATE_PATH);
define("MODULES_PATH", $this->MODULES_PATH);


        //Determine if local path is correct!
        if (file_exists($this->MODULES_PATH . "endpointman/")) {
            $this->LOCAL_PATH = $this->MODULES_PATH . "endpointman/";
define("LOCAL_PATH", $this->LOCAL_PATH);
        } else {
            die("Can't Load Local Endpoint Manager Directory!");
        }

        //Define the location of phone modules, keeping it outside of the module directory so that when the user updates endpointmanager they don't lose all of their phones
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
define("PHONE_MODULES_PATH", $this->PHONE_MODULES_PATH);

        //Define error reporting
        if (($this->configmod->get('debug')) AND (!isset($_REQUEST['quietmode']))) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            ini_set('display_errors', 0);
        }

        //Check if config location is writable and/or exists!
        if ($this->configmod->isExiste('config_location')) {
			$config_location = $this->configmod->get('config_location');
            if (is_dir($config_location)) {
                if (!is_writeable($config_location)) {
                    $user = exec('whoami');
                    $group = exec("groups");
                    $this->error['config_location'] = _("Configuration Directory is not writable!") . "<br />" .
                            _("Please change the location:") . "<a href='config.php?display=epm_advanced'>" . _("Here") . "</a><br />" .
                            _("Or run this command on SSH:") . "<br />" .
							"'chown -hR root: " . $group . " " . $config_location . "'<br />" .
							"'chmod g+w " . $config_location . "'";
					$this->configmod->set('disable_epm', TRUE);
                }
            } else {
                $this->error['config_location'] = _("Configuration Directory is not a directory or does not exist! Please change the location here:") . "<a href='config.php?display=epm_advanced'>" . _("Here") . "</a>";
				$this->configmod->set('disable_epm', TRUE);
            }
        }

        //$this->tpl = new RainTPL(LOCAL_PATH . '_old/templates/freepbx', LOCAL_PATH . '_old/templates/freepbx/compiled', '/admin/assets/endpointman/images');
		//$this->tpl = new RainTPL('/admin/assets/endpointman/images');


		require_once('Endpointman_Config.class.php');
		$this->epm_config = new Endpointman_Config($freepbx, $this->configmod, $this->system);

		require_once('Endpointman_Advanced.class.php');
		$this->epm_advanced = new Endpointman_Advanced($freepbx, $this->configmod, $this->epm_config);

		require_once('Endpointman_Templates.class.php');
		$this->epm_templates = new Endpointman_Templates($freepbx, $this->configmod, $this->epm_config, $this->eda);

		require_once('Endpointman_Devices.class.php');
		$this->epm_devices = new Endpointman_Devices($freepbx, $this->configmod);

		require_once('Endpointman_Devices.class.php');
		$this->epm_oss = new Endpointman_Devices($freepbx, $this->configmod);

		//require_once('Endpointman_Devices.class.php');
		$this->epm_placeholders = new Endpointman_Devices($freepbx, $this->configmod);

	}

	public function chownFreepbx() {
		$webroot = $this->config->get('AMPWEBROOT');
		$modulesdir = $webroot . '/admin/modules/';
		$files = array();
		$files[] = array('type' => 'dir',
						'path' => $modulesdir . '/_ep_phone_modules/',
						'perms' => 0755);
		$files[] = array('type' => 'file',
						'path' => $modulesdir . '/_ep_phone_modules/setup.php',
						'perms' => 0755);
		$files[] = array('type' => 'dir',
						'path' => '/tftpboot',
						'perms' => 0755);
		return $files;
	}

	public function ajaxRequest($req, &$setting) {
		//AVISO!!!!!!!!!!!!!!!!!!!!!!!!!!
		//PERMITE TODO!!!!!!!!!!!!!!!!!!!
		$setting['authenticate'] = true;
		$setting['allowremote'] = true;
		return true;

		$module_sec = isset($_REQUEST['module_sec'])? trim($_REQUEST['module_sec']) : '';
		if ($module_sec == "") { return false; }

		switch($module_sec)
		{
			case "epm_devices":
				return $this->epm_devices->ajaxRequest(trim($req), $setting);
				break;

			case "epm_oss":
				return $this->epm_oss->ajaxRequest(trim($req), $setting);
				break;

			case "epm_placeholders":
				return $this->epm_placeholders->ajaxRequest(trim($req), $setting);
				break;

			case "epm_config":
				return $this->epm_config->ajaxRequest(trim($req), $setting);
				break;

			case "epm_advanced":
				return $this->epm_advanced->ajaxRequest(trim($req), $setting);
				break;

			case "epm_templates":
				return $this->epm_templates->ajaxRequest(trim($req), $setting);
				break;
		}
        return false;
    }

    public function ajaxHandler() {

		$module_sec = isset($_REQUEST['module_sec'])? trim($_REQUEST['module_sec']) : '';
		$module_tab = isset($_REQUEST['module_tab'])? trim($_REQUEST['module_tab']) : '';
		$command = isset($_REQUEST['command'])? trim($_REQUEST['command']) : '';

		if ($command == "") {
			return array("status" => false, "message" => _("No command was sent!"));
		}

		$arrVal['mod_sec'] = array("epm_devices", "epm_oss", "epm_placeholders", "epm_templates", "epm_config", "epm_advanced");
		if (! in_array($module_sec, $arrVal['mod_sec'])) {
			return array("status" => false, "message" => _("Invalid section module!"));
		}

		switch ($module_sec)
		{
			case "epm_devices":
				return $this->epm_devices->ajaxHandler($module_tab, $command);
				break;

			case "epm_oss":
				return $this->epm_oss->ajaxHandler($module_tab, $command);
				break;
			case "epm_placeholders":
				return $this->epm_placeholders->ajaxHandler($module_tab, $command);
				break;

			case "epm_templates":
				return $this->epm_templates->ajaxHandler($module_tab, $command);
				break;

			case "epm_config":
				return $this->epm_config->ajaxHandler($module_tab, $command);
				break;

			case "epm_advanced":
				return $this->epm_advanced->ajaxHandler($module_tab, $command);
				break;
		}
		return false;
    }

	public static function myDialplanHooks() {
		return true;
	}

	public function doConfigPageInit($page) {
		//TODO: Pendiente revisar y eliminar moule_tab.
		$module_tab = isset($_REQUEST['module_tab'])? trim($_REQUEST['module_tab']) : '';
		if ($module_tab == "") {
			$module_tab = isset($_REQUEST['subpage'])? trim($_REQUEST['subpage']) : '';
		}
		$command = isset($_REQUEST['command'])? trim($_REQUEST['command']) : '';


		$arrVal['mod_sec'] = array("epm_devices","epm_oss", "epm_placeholders", "epm_templates", "epm_config", "epm_advanced");
		if (! in_array($page, $arrVal['mod_sec'])) {
			die(_("Invalid section module!"));
		}

		switch ($page)
		{
			case "epm_devices":
				$this->epm_devices->doConfigPageInit($module_tab, $command);
				break;
			case "epm_oss":
				$this->epm_oss->doConfigPageInit($module_tab, $command);
				break;
			case "epm_placeholders":
				$this->epm_placeholders->doConfigPageInit($module_tab, $command);
				break;
			case "epm_templates":
				$this->epm_templates->doConfigPageInit($module_tab, $command);
				break;

			case "epm_config":
				$this->epm_config->doConfigPageInit($module_tab, $command);
				break;

			case "epm_advanced":
				$this->epm_advanced->doConfigPageInit($module_tab, $command);
				break;
		}
	}

	public function doGeneralPost() {
		if (!isset($_REQUEST['Submit'])) 	{ return; }
		if (!isset($_REQUEST['display'])) 	{ return; }

		needreload();
	}

	public function myShowPage() {
		if (! isset($_REQUEST['display']))
			return $this->pagedata;

		switch ($_REQUEST['display'])
		{
			case "epm_devices":
				$this->epm_devices->myShowPage($this->pagedata);
				break;
			case "epm_oss":
				return $this->epm_oss->myShowPage($this->pagedata);
				break;
			case "epm_placeholders":
				return $this->epm_placeholders->myShowPage($this->pagedata);
				break;
			case "epm_templates":
				$this->epm_templates->myShowPage($this->pagedata);
				return $this->pagedata;
				break;

			case "epm_config":
				$this->epm_config->myShowPage($this->pagedata);
				break;

			case "epm_advanced":
				$this->epm_advanced->myShowPage($this->pagedata);
				break;
		}

		if(! empty($this->pagedata)) {
			foreach($this->pagedata as &$page) {
				ob_start();
				include($page['page']);
				$page['content'] = ob_get_contents();
				ob_end_clean();
			}
			return $this->pagedata;
		}
	}

	public function getActiveModules() {

	}

	//http://wiki.freepbx.org/display/FOP/Adding+Floating+Right+Nav+to+Your+Module
	public function getRightNav($request) {
		if (! isset($_REQUEST['display']))
			return '';
		else {
			//return load_view(dirname(__FILE__).'/views/rnav.php',array());
			return load_view(dirname(__FILE__) . '/views/rnav.php', $var);
		}
		switch($_REQUEST['display'])
		{
			case "epm_devices":
				return load_view(dirname(__FILE__) . '/views/rnav.php', $var);
				break;
			case "epm_oss":
				return load_view(dirname(__FILE__) . '/views/rnav.php', $var);
				break;
			case "epm_placeholders":
				return load_view(dirname(__FILE__) . '/views/rnav.php', $var);
				break;

			case "epm_config":
				return load_view(dirname(__FILE__) . '/views/rnav.php', $var);
				break;

			case "epm_advanced":
				return load_view(dirname(__FILE__) . '/views/rnav.php', $var);
				break;

			case "epm_templates":
				return load_view(dirname(__FILE__) . '/views/rnav.php', $var);
				break;

			default:
		        return '';

		}
	}

	//http://wiki.freepbx.org/pages/viewpage.action?pageId=29753755
	public function getActionBar($request) {
			if (! isset($_REQUEST['display']))
			return '';

		switch($_REQUEST['display'])
		{
			case "epm_devices":
				return $this->epm_devices->getActionBar($request);
				break;

			case "epm_oss":
				return $this->epm_oss->getActionBar($request);
				break;
			case "epm_placeholders":
				return $this->epm_placeholders->getActionBar($request);
				break;
			case "epm_config":
				return $this->epm_config->getActionBar($request);
				break;

			case "epm_advanced":
				return $this->epm_advanced->getActionBar($request);
				break;

			case "epm_templates":
				return $this->epm_templates->getActionBar($request);
				break;

			default:
		        return '';

		}
	}

	public function install() {

	}

	public function uninstall() {
		if(file_exists($this->PHONE_MODULES_PATH)) {
			out(_("Removing Phone Modules Directory"));
			$this->system->rmrf($this->PHONE_MODULES_PATH);
			@exec("rm -R ". $this->PHONE_MODULES_PATH);
		}

		$provisioning_path = $this->config->get('AMPWEBROOT')."/provisioning";
		if(file_exists($provisioning_path) && is_link($provisioning_path)) {
			out(_('Removing symlink to web provisioner'));
			unlink($provisioning_path);
		}

		if(!is_link($this->config->get('AMPWEBROOT').'/admin/assets/endpointman')) {
			$this->system->rmrf($this->config->get('AMPWEBROOT').'/admin/assets/endpointman');
		}
		return true;
	}

	public function backup() {
	}

    public function restore($backup) {
	}


	private function epm_config_manual_install($install_type = "", $package ="")
	{
		if ($install_type == "") {
			throw new \Exception("Not send install_type!");
		}

		switch($install_type) {
			case "export_brand":

				break;

			case "upload_master_xml":
				if (file_exists($this->PHONE_MODULES_PATH."temp/master.xml")) {
					$handle = fopen($this->PHONE_MODULES_PATH."temp/master.xml", "rb");
					$contents = stream_get_contents($handle);
					fclose($handle);
					@$a = simplexml_load_string($contents);
					if($a===FALSE) {
						echo "Not a valid xml file";
						break;
					} else {
						rename($this->PHONE_MODULES_PATH."temp/master.xml", $this->PHONE_MODULES_PATH."master.xml");
						echo "Move Successful<br />";
						$this->update_check();
						echo "Updating Brands<br />";
					}
				} else {
				}
				break;

			case "upload_provisioner":

				break;

			case "upload_brand":

				break;
		}
	}






	/*****************************************
	****** CODIGO ANTIGUO -- REVISADO ********
	*****************************************/





	//TODO: DUPLICADO AQUI Y EN epm_advandec
	/**
     * This function takes a string and tries to determine if it's a valid mac addess, return FALSE if invalid
     * @param string $mac The full mac address
     * @return mixed The cleaned up MAC is it was a MAC or False if not a mac

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


     */













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

	function listTZ($selected) {
        require_once('lib/datetimezone.class.php');
        $data = \DateTimeZone::listIdentifiers();
        $i = 0;
        foreach ($data as $key => $row) {
            $temp[$i]['value'] = $row;
            $temp[$i]['text'] = $row;
            if (strtoupper ($temp[$i]['value']) == strtoupper($selected)) {
                $temp[$i]['selected'] = 1;
            } else {
                $temp[$i]['selected'] = 0;
            }
            $i++;
        }

        return($temp);
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

	function tftp_check() {
        //create a simple block here incase people have strange issues going on as we will kill http
        //by running this if the server isn't really running!
        $sql = 'SELECT value FROM endpointman_global_vars WHERE var_name = \'tftp_check\'';
        if (sql($sql, 'getOne') != 1) {
            $sql = 'UPDATE endpointman_global_vars SET value = \'1\' WHERE var_name = \'tftp_check\'';
            sql($sql);
            $subject = shell_exec("netstat -luan --numeric-ports");
            if (preg_match('/:69\s/i', $subject)) {
                $rand = md5(rand(10, 2000));
                if (file_put_contents($this->configmod->get('config_location') . 'TEST', $rand)) {
                    if ($this->system->tftp_fetch('127.0.0.1', 'TEST') != $rand) {
                        $this->error['tftp_check'] = 'Local TFTP Server is not correctly configured';
echo 'Local TFTP Server is not correctly configured';
                    }
                    unlink($this->configmod->get('config_location') . 'TEST');
                } else {
                    $this->error['tftp_check'] = 'Unable to write to ' . $this->configmod->get('config_location');
echo 'Unable to write to ' . $this->configmod->get('config_location');
                }
            } else {
                $dis = FALSE;
                if (file_exists('/etc/xinetd.d/tftp')) {
                    $contents = file_get_contents('/etc/xinetd.d/tftp');
                    if (preg_match('/disable.*=.*yes/i', $contents)) {
                        $this->error['tftp_check'] = 'Disabled is set to "yes" in /etc/xinetd.d/tftp. Please fix <br />Then restart your TFTP service';
echo 'Disabled is set to "yes" in /etc/xinetd.d/tftp. Please fix <br />Then restart your TFTP service';
                        $dis = TRUE;
                    }
                }
                if (!$dis) {
                    $this->error['tftp_check'] = 'TFTP Server is not running. <br />See here for instructions on how to install one: <a href="http://wiki.provisioner.net/index.php/Tftp" target="_blank">http://wiki.provisioner.net/index.php/Tftp</a>';
echo 'TFTP Server is not running. <br />See here for instructions on how to install one: <a href="http://wiki.provisioner.net/index.php/Tftp" target="_blank">http://wiki.provisioner.net/index.php/Tftp</a>';
                }
            }
            $sql = 'UPDATE endpointman_global_vars SET value = \'0\' WHERE var_name = \'tftp_check\'';
            sql($sql);
        } else {
            $this->error['tftp_check'] = 'TFTP Server check failed on last past. Skipping';
echo 'TFTP Server check failed on last past. Skipping';
        }
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

    	$fp = fopen($this->LOCAL_PATH . 'data.txt', 'w');
    	fwrite($fp, $data);
    	fclose($fp);
    	$file_name_with_full_path = $this->LOCAL_PATH . "data.txt";

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



    /**


    function add_device($mac, $model, $ext, $template=NULL, $line=NULL, $displayname=NULL) {
    	$mac = $this->mac_check_clean($mac);
    	if ($mac) {
    		if (empty($model)) {
//$this->error['add_device'] =
			out(_("You Must Select A Model From the Drop Down") . "!");
    			return(FALSE);
    		} elseif (empty($ext)) {
//$this->error['add_device'] =
			out(_("You Must Select an Extension/Device From the Drop Down") . "!");
    			return(FALSE);
    		} else {
    			if ($this->epm_config->sync_model($model)) {
    				$sql = "SELECT id,template_id FROM endpointman_mac_list WHERE mac = '" . $mac . "'";
    				$dup = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

    				if ($dup) {
    					if (!isset($template)) {
    						$template = $dup['template_id'];
    					}

    					$sql = "UPDATE endpointman_mac_list SET model = " . $model . ", template_id =  " . $template . " WHERE id = " . $dup['id'];
    					sql($sql);
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
    					$used = sql($sql, 'getOne');

					if (($used) AND (! $this->configmod->get('show_all_registrations'))) {
//$this->error['add_device'] =
						out(_("You can't assign the same user to multiple devices") . "!");
    						return(FALSE);
    					}

    					if (!isset($displayname)) {
    						$sql = 'SELECT description FROM devices WHERE id = ' . $ext;
    						$name = sql($sql, 'getOne');
							$name = "123";
							$displayname = "123";
    					} else {
    						$name = $displayname;
    					}

    					$sql = 'SELECT endpointman_product_list. * , endpointman_model_list.template_data, endpointman_brand_list.directory FROM endpointman_model_list, endpointman_brand_list, endpointman_product_list WHERE endpointman_model_list.id =  \'' . $model . '\' AND endpointman_model_list.brand = endpointman_brand_list.id AND endpointman_model_list.product_id = endpointman_product_list.id';
    					$row = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

    					$sql = "INSERT INTO `endpointman_mac_list` (`mac`, `model`, `template_id`) VALUES ('" . $mac . "', '" . $model . "', '" . $template . "')";
    					sql($sql);

    					$sql = 'SELECT last_insert_id()';
    					$ext_id = sql($sql, 'getOne');

    					if (empty($line)) {
    						$line = 1;
    					}

    					$sql = "INSERT INTO `endpointman_line_list` (`mac_id`, `ext`, `line`, `description`) VALUES ('" . $ext_id . "', '" . $ext . "', '" . $line . "', '" . addslashes($name) . "')";
    					sql($sql);

//$this->message['add_device'][] =
					out(_("Added ") . $name . _(" to line ") . $line);
    					return($ext_id);
    				}
    			} else {
//$this->error['Sync_Model'] =
				out(_("Invalid Model Selected, Can't Sync System") . "!");
    				return(FALSE);
    			}
    		}
    	} else {
//$this->error['add_device'] =
		out(_("Invalid MAC Address") . "!");
    		return(FALSE);
    	}
    }


    function add_line($mac_id, $line=NULL, $ext=NULL, $displayname=NULL) {
    	if ((!isset($line)) AND (!isset($ext))) {
    		if ($this->linesAvailable(NULL, $mac_id)) {
    			if ($this->eda->all_unused_registrations()) {
    				$sql = 'SELECT * FROM endpointman_line_list WHERE mac_id = ' . $mac_id;
    				$lines_list = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);

    				foreach ($lines_list as $row) {
    					$sql = "SELECT description FROM devices WHERE id = " . $row['ext'];
    					$name = sql($sql, 'getOne');

    					$sql = "UPDATE endpointman_line_list SET line = '" . $row['line'] . "', ext = '" . $row['ext'] . "', description = '" . $this->eda->escapeSimple($name) . "' WHERE luid =  " . $row['luid'];
    					sql($sql);
    				}

    				$reg = array_values($this->display_registration_list());
    				$lines = array_values($this->linesAvailable(NULL, $mac_id));

    				$sql = "SELECT description FROM devices WHERE id = " . $reg[0]['value'];
    				$name = sql($sql, 'getOne');

    				$sql = "INSERT INTO `endpointman_line_list` (`mac_id`, `ext`, `line`, `description`) VALUES ('" . $mac_id . "', '" . $reg[0]['value'] . "', '" . $lines[0]['value'] . "', '" . addslashes($name) . "')";
    				sql($sql);

//$this->message['add_line'] =
				out(_("Added '<i>") . $name . _("</i>' to line '<i>") . $lines[0]['value'] . _("</i>' on device '<i>") . $reg[0]['value'] . _("</i>' <br/> Configuration Files will not be Generated until you click Save!"));
    				return($mac_id);
    			} else {
//$this->error['add_line'] =
				out(_("No Devices/Extensions Left to Add") . "!");
    				return(FALSE);
    			}
    		} else {
//$this->error['add_line'] =
			out(_("No Lines Left to Add") . "!");
    			return(FALSE);
    		}
    	} elseif ((!isset($line)) AND (isset($ext))) {
    		if ($this->linesAvailable(NULL, $mac_id)) {
    			if ($this->eda->all_unused_registrations()) {
    				$lines = array_values($this->linesAvailable(NULL, $mac_id));

    				$sql = "INSERT INTO `endpointman_line_list` (`mac_id`, `ext`, `line`, `description`) VALUES ('" . $mac_id . "', '" . $ext . "', '" . $lines[0]['value'] . "', '" . addslashes($displayname) . "')";
    				sql($sql);

//$this->message['add_line'] =
				out(_("Added '<i>") . $name . _("</i>' to line '<i>") . $lines[0]['value'] . _("</i>' on device '<i>") . $reg[0]['value'] . _("</i>' <br/> Configuration Files will not be Generated until you click Save!"));
    				return($mac_id);
    			} else {
//$this->error['add_line'] =
				out(_("No Devices/Extensions Left to Add") . "!");
    				return(FALSE);
    			}
    		} else {
//$this->error['add_line'] =
			out(_("No Lines Left to Add") . "!");
    			return(FALSE);
    		}
    	} elseif ((isset($line)) AND (isset($ext))) {
    		$sql = "SELECT luid FROM endpointman_line_list WHERE line = '" . $line . "' AND mac_id = " . $mac_id;
    		$luid = sql($sql, 'getOne');
    		if ($luid) {
//$this->error['add_line'] =
			out(_("This line has already been assigned!"));
    			return(FALSE);
    		} else {
    			if (!isset($displayname)) {
    				$sql = 'SELECT description FROM devices WHERE id = ' . $ext;
    				$name = sql($sql, 'getOne');
    			} else {
    				$name = $displayname;
    			}

    			$sql = "INSERT INTO `endpointman_line_list` (`mac_id`, `ext`, `line`, `description`) VALUES ('" . $mac_id . "', '" . $ext . "', '" . $line . "', '" . addslashes($name) . "')";
    			sql($sql);
//$this->message['add_line'] =
			out(_("Added ") . $name . _(" to line ") . $line . "<br/>");
    			return($mac_id);
    		}
    	}
    }


    function linesAvailable($lineid=NULL, $macid=NULL) {
    	if (isset($lineid)) {
    		$sql = "SELECT max_lines FROM endpointman_model_list WHERE id = (SELECT endpointman_mac_list.model FROM endpointman_mac_list, endpointman_line_list WHERE endpointman_line_list.luid = " . $lineid . " AND endpointman_line_list.mac_id = endpointman_mac_list.id)";

    		$sql_l = "SELECT line, mac_id FROM `endpointman_line_list` WHERE luid = " . $lineid;
    		$line = sql($sql_l, 'getRow', DB_FETCHMODE_ASSOC);

    		$sql_lu = "SELECT line FROM endpointman_line_list WHERE mac_id = " . $line['mac_id'];
    	} elseif (isset($macid)) {
    		$sql = "SELECT max_lines FROM endpointman_model_list WHERE id = (SELECT model FROM endpointman_mac_list WHERE id =" . $macid . ")";
    		$sql_lu = "SELECT line FROM endpointman_line_list WHERE mac_id = " . $macid;

    		$line['line'] = 0;
    	}

    	$max_lines = sql($sql, 'getOne');
    	$lines_used = sql($sql_lu, 'getAll');

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




     * Display all unused registrations from whatever manager we are using!
     * @return <type>
     */
	     /**
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

    function get_phone_info($mac_id=NULL) {
    	//You could screw up a phone if the mac_id is blank
    	if (!isset($mac_id)) {
//$this->error['get_phone_info'] =
		out(_("Mac ID is not set"));
    		return(FALSE);
    	}
    	$sql = "SELECT id FROM endpointman_mac_list WHERE model > 0 AND id =" . $mac_id;

    	//$res = sql($sql);
		$res = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
    	if (count(array($res))) {
    		//Returns Brand Name, Brand Directory, Model Name, Mac Address, Extension (FreePBX), Custom Configuration Template, Custom Configuration Data, Product Name, Product ID, Product Configuration Directory, Product Configuration Version, Product XML name,
    		$sql = "SELECT endpointman_mac_list.specific_settings, endpointman_mac_list.config_files_override, endpointman_mac_list.global_user_cfg_data, endpointman_model_list.id as model_id, endpointman_brand_list.id as brand_id, endpointman_brand_list.name, endpointman_brand_list.directory, endpointman_model_list.model, endpointman_mac_list.mac, endpointman_mac_list.template_id, endpointman_mac_list.global_custom_cfg_data, endpointman_product_list.long_name, endpointman_product_list.id as product_id, endpointman_product_list.cfg_dir, endpointman_product_list.cfg_ver, endpointman_model_list.template_data, endpointman_model_list.enabled, endpointman_mac_list.global_settings_override FROM endpointman_line_list, endpointman_mac_list, endpointman_model_list, endpointman_brand_list, endpointman_product_list WHERE endpointman_mac_list.model = endpointman_model_list.id AND endpointman_brand_list.id = endpointman_model_list.brand AND endpointman_product_list.id = endpointman_model_list.product_id AND endpointman_mac_list.id = endpointman_line_list.mac_id AND endpointman_mac_list.id = " . $mac_id;
    		$phone_info = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

    		if (!$phone_info) {
//$this->error['get_phone_info'] =
			out(_("Error with SQL Statement"));
    		}

    		//If there is a template associated with this phone then pull that information and put it into the array
    		if ($phone_info['template_id'] > 0) {
    			$sql = "SELECT name, global_custom_cfg_data, config_files_override, global_settings_override FROM endpointman_template_list WHERE id = " . $phone_info['template_id'];
    			$phone_info['template_data_info'] = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
    		}

    		$sql = "SELECT endpointman_line_list.*, sip.data as secret, devices.*, endpointman_line_list.description AS epm_description FROM endpointman_line_list, sip, devices WHERE endpointman_line_list.ext = devices.id AND endpointman_line_list.ext = sip.id AND sip.keyword = 'secret' AND mac_id = " . $mac_id . " ORDER BY endpointman_line_list.line ASC";
    		$lines_info = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
    		foreach ($lines_info as $line) {
    			$phone_info['line'][$line['line']] = $line;
    			$phone_info['line'][$line['line']]['description'] = $line['epm_description'];
    			$phone_info['line'][$line['line']]['user_extension'] = $line['user'];
    		}
    	} else {
    		$sql = "SELECT id, mac FROM endpointman_mac_list WHERE id =" . $mac_id;
    		//Phone is unknown, we need to display this to the end user so that they can make corrections
    		$row = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

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
    		$lines_info = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
    		foreach ($lines_info as $line) {
    			$phone_info['line'][$line['line']] = $line;
    		}
    	}
		$phone_info = "test";
    	return $phone_info;
    }
*/
    /**
     * Get the brand from any mac sent to this function
     * @param string $mac
     * @return array

    function get_brand_from_mac($mac) {
    	//Check for valid mac address first
		if (!$this->mac_check_clean($mac)) {
    		return(FALSE);
    	}

    	//Get the OUI only
    	$oui = substr($this->mac_check_clean($mac), 0, 6);
    	//Find the matching brand model to the oui
    	$oui_sql = "SELECT endpointman_brand_list.name, endpointman_brand_list.id FROM endpointman_oui_list, endpointman_brand_list WHERE oui LIKE '%" . $oui . "%' AND endpointman_brand_list.id = endpointman_oui_list.brand AND endpointman_brand_list.installed = 1 LIMIT 1";
    	$brand = sql($oui_sql, 'getRow', DB_FETCHMODE_ASSOC);

    	$res = sql($oui_sql);
    	$brand_count = count(array($res));

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

*/

    /**
     * Prepare and then send the data that Provisioner expects, then take what provisioner gives us and do what it says
     * @param array $phone_info Everything from get_phone_info
     * @param bool  $reboot Reboot the Phone after write
     * @param bool  $write  Write out Directory structure.

    function prepare_configs($phone_info, $reboot=TRUE, $write=TRUE)
    {
    	$this->PROVISIONER_BASE = $this->PHONE_MODULES_PATH;
        define('PROVISIONER_BASE', $this->PROVISIONER_BASE);
    	if (file_exists($this->PHONE_MODULES_PATH . 'autoload.php')) {
    		if (!class_exists('ProvisionerConfig')) {
    			require($this->PHONE_MODULES_PATH . 'autoload.php');
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
    					$settings['srvip'] = $this->configmod->get('srvip');
    					$settings['ntp'] = $this->configmod->get('ntp');
    					$settings['config_location'] = $this->configmod->get('config_location');
    					$settings['tz'] = $this->configmod->get('tz');
    				}
    			} else {
    				if (isset($phone_info['global_settings_override'])) {
    					$settings = unserialize($phone_info['global_settings_override']);
    				} else {
    					$settings['srvip'] = $this->configmod->get('srvip');
    					$settings['ntp'] = $this->configmod->get('ntp');
    					$settings['config_location'] = $this->configmod->get('config_location');
    					$settings['tz'] = $this->configmod->get('tz');
    				}
    			}



    			//Tell the system who we are and were to find the data.
    			$provisioner_lib->root_dir = $this->PHONE_MODULES_PATH;
    			$provisioner_lib->engine = 'asterisk';
    			$provisioner_lib->engine_location = $this->configmod->get('asterisk_location','asterisk');
    			$provisioner_lib->system = 'unix';

    			//have to because of versions less than php5.3
    			$provisioner_lib->brand_name = $phone_info['directory'];
    			$provisioner_lib->family_line = $phone_info['cfg_dir'];



    			//Phone Model (Please reference family_data.xml in the family directory for a list of recognized models)
    			//This has to match word for word. I really need to fix this....
    			$provisioner_lib->model = $phone_info['model'];

    			//Timezone
    			try {
                                $provisioner_lib->DateTimeZone = new \DateTimeZone($settings['tz']);
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
    						//$res = sql($sql);
							$res = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
    						if (count(array($res))) {
    							$data = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
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
    						//$res = sql($sql);
							$res = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
    						if (count(array($res))) {
    							$data = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);
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
    							if (($this->configmod->get('enable_ari') == 1) AND (isset($global_custom_cfg_ari[$full_key])) AND (isset($global_user_cfg_data[$full_key]))) {
    								$new_template_data[$full_key] = $global_user_cfg_data[$full_key];
    							} else {
    								$new_template_data[$full_key] = $global_custom_cfg_data[$full_key];
    							}
    							break;
    						case 2:
    							$breaks = explode('_', $key[1]);
    							if (($this->configmod->get('enable_ari') == 1) AND (isset($global_custom_cfg_ari[$full_key])) AND (isset($global_user_cfg_data[$full_key]))) {
    								$new_template_data['loops'][$breaks[0]][$breaks[2]][$breaks[1]] = $global_user_cfg_data[$full_key];
    							} else {
    								$new_template_data['loops'][$breaks[0]][$breaks[2]][$breaks[1]] = $global_custom_cfg_data[$full_key];
    							}
    							break;
    						case 3:
    							if (($this->configmod->get('enable_ari') == 1) AND (isset($global_custom_cfg_ari[$full_key])) AND (isset($global_user_cfg_data[$full_key]))) {
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
    				$new_template_data['provision']['path'] =  rtrim($settings['srvip'] . dirname($_SERVER['REQUEST_URI']) . '/', '/');
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

    			//Loop through Lines!
    			$li = 0;
    			foreach ($phone_info['line'] as $line) {
    				$line_options = is_array($line_ops[$line['line']]) ? $line_ops[$line['line']] : array();
    				$line_statics = array('line' => $line['line'], 'username' => $line['ext'], 'authname' => $line['ext'], 'secret' => $line['secret'], 'displayname' => $line['description'], 'server_host' => $this->configmod->get('srvip'), 'server_port' => '5060', 'user_extension' => $line['user_extension']);
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
    						$loc = $this->system->arraysearchrecursive($line, $provisioner_lib->settings['line'], 'line');
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
    			$provisioner_lib->processor_info = "EndPoint Manager Version " . $this->configmod->get('version');

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

    function write_configs($provisioner_lib, $reboot, $write_path, $phone_info, $returned_data) {

    	//Create Directory Structure (If needed)
    	if (isset($provisioner_lib->directory_structure)) {
    		foreach ($provisioner_lib->directory_structure as $data) {
    			if (file_exists($this->PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data)) {
    				$dir_iterator = new \RecursiveDirectoryIterator($this->PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data . "/");
    				$iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);
    				// could use CHILD_FIRST if you so wish
    				foreach ($iterator as $file) {
    					$dir = $write_path . str_replace($this->PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/", "", dirname($file));
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
    			if (file_exists($this->PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data)) {
    				$file = $write_path . $data;
    				$orig = $this->PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data;
    				if (!file_exists($file)) {
    					if (!@copy($orig, $file)) {
$this->error['parse_configs'] = "Could Not Create File: " . $data;
    						return(FALSE);
    					}
    				} else {
    					if (file_exists($this->PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data)) {
    						if (!file_exists(dirname($write_path . $data))) {
    							!@mkdir(dirname($write_path . $data), 0775);
    						}
    						copy($this->PHONE_MODULES_PATH . "endpoint/" . $phone_info['directory'] . "/" . $phone_info['cfg_dir'] . "/" . $data, $write_path . $data);
    						chmod($write_path . $data, 0775);
    					}
    				}
    			}
    		}
    	}

    	foreach ($returned_data as $file => $data) {
    		if (((file_exists($write_path . $file)) AND (is_writable($write_path . $file)) AND (!in_array($file, $provisioner_lib->protected_files))) OR (!file_exists($write_path . $file))) {
    			//Move old file to backup
    			if (!$this->eda->global_cfg['backup_check']) {
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
    			file_put_contents($write_path . $file, $data);
    			chmod($write_path . $file, 0775);
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
*/



























































































	/*********************************************
	****** CODIGO ANTIGUO -- SIN REVISADO ********
	*********************************************/









/*

    function download_json($location, $directory=NULL) {
        $temp_directory = $this->sys_get_temp_dir() . "/epm_temp/";
        if (!isset($directory)) {
            $destination_file = $this->PHONE_MODULES_PATH . 'endpoint/master.json';
            $directory = "master";
        } else {
            if (!file_exists($this->PHONE_MODULES_PATH . '/' . $directory)) {
                mkdir($this->PHONE_MODULES_PATH . '/' . $directory, 0775, TRUE);
            }
            $destination_file = $this->PHONE_MODULES_PATH . '/' . $directory . '/brand_data.json';
        }
        $temp_file = $temp_directory . $directory . '.json';
        file_exists(dirname($temp_file)) ? '' : mkdir(dirname($temp_file));

        if ($this->system->download_file($location, $temp_file)) {
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


*/


    /**
    * Send process to run in background
    * @version 2.11
    * @param string $command the command to run
    * @param integer $Priority the Priority of the command to run
    * @return int $PID process id
    * @package epm_system

    function run_in_background($Command, $Priority = 0) {
        return($Priority ? shell_exec("nohup nice -n $Priority $Command 2> /dev/null & echo $!") : shell_exec("nohup $Command > /dev/null 2> /dev/null & echo $!"));
    }

    /**
    * Check if process is running in background
    * @version 2.11
    * @param string $PID proccess ID
    * @return bool true or false
    * @package epm_system

    function is_process_running($PID) {
        exec("ps $PID", $ProcessState);
        return(count($ProcessState) >= 2);
    }



    /**
    * Uses which to find executables that asterisk can run/use
    * @version 2.11
    * @param string $exec Executable to find
    * @package epm_system


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

    */
    /**
     * Only used once in all of Endpoint Manager to determine if a table exists
     * @param string $table Table to look for
     * @return bool

    function table_exists($table) {
        $sql = "SHOW TABLES FROM " . $this->config->get('AMPDBNAME');
        $result = $this->eda->sql($sql, 'getAll');
        foreach ($result as $row) {
            if ($row[0] == $table) {
                return TRUE;
            }
        }
        return FALSE;
    }
     */




    /**
     * Check for valid netmast to avoid security issues
     * @param string $mask the complete netmask, eg [1.1.1.1/24]
     * @return boolean True if valid, False if not
     * @version 2.11

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

    function discover_new($netmask, $use_nmap=TRUE) {
        if (($use_nmap) AND (file_exists($this->eda->global_cfg['nmap_location'])) AND ($this->validate_netmask($netmask))) {
            shell_exec($this->eda->global_cfg['nmap_location'] . ' -v -sP ' . $netmask);
        } elseif (!$this->validate_netmask($netmask)) {
            $this->error['discover_new'] = "Invalid Netmask";
            return(FALSE);
        } elseif (!file_exists($this->eda->global_cfg['nmap_location'])) {
            $this->error['discover_new'] = "Could Not Find NMAP, Using ARP Only";
            //return(FALSE);
        }

        //Get arp list
        $arp_list = shell_exec($this->eda->global_cfg['arp_location'] . " -an");

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
                $brand_count = count(array($res));

                if (!$brand_count) {
                    //oui doesn't have a matching mysql reference, probably a PC/router/wap/printer of some sort.
                    $brand['name'] = FALSE;
                    $brand['id'] = NULL;
                }

                //Find out if endpoint has already been configured for this mac address
                $epm_sql = "SELECT * FROM endpointman_mac_list WHERE mac LIKE  '%" . $mac_strip . "%'";
                $epm_row = $this->eda->sql($epm_sql, 'getRow', DB_FETCHMODE_ASSOC);

                $res = $this->eda->sql($epm_sql);

                $epm = count(array($res)) ? TRUE : FALSE;

                //Add into a final array
                $final[$z] = array("ip" => $ip, "mac" => $mac, "mac_strip" => $mac_strip, "oui" => $oui, "brand" => $brand['name'], "brand_id" => $brand['id'], "endpoint_managed" => $epm);
                $z++;
            }
        }
        return !is_array($final) ? FALSE : $final;
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






    function display_templates($product_id, $temp_select = NULL) {
        $i = 0;
        $sql = "SELECT id FROM  endpointman_product_list WHERE endpointman_product_list.id ='" . $product_id . "'";
        $id = sql($sql, 'getOne');

        $sql = "SELECT * FROM  endpointman_template_list WHERE  product_id = '" . $id . "'";
        $data = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);

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

    function validate_json($json) {
        return(TRUE);
    }

















    /**

    function update_device($macid, $model, $template, $luid=NULL, $name=NULL, $line=NULL, $update_lines=TRUE) {
        $sql = "UPDATE endpointman_mac_list SET model = " . $model . ", template_id =  " . $template . " WHERE id = " . $macid;
        sql($sql);

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
                $name = sql($sql, 'getOne');
            }

            if (!isset($line)) {
                $line = $row['line'];
            }
            $sql = "UPDATE endpointman_line_list SET line = '" . $line . "', ext = '" . $row['ext'] . "', description = '" . $this->eda->escapeSimple($name) . "' WHERE luid =  " . $row['luid'];
            sql($sql);
            return(TRUE);
        } else {
            $sql = "SELECT * FROM endpointman_line_list WHERE mac_id = " . $macid;
            $lines_info = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
            foreach ($lines_info as $row) {
                $sql = "SELECT description FROM devices WHERE id = " . $row['ext'];
                $name = sql($sql, 'getOne');

                $sql = "UPDATE endpointman_line_list SET line = '" . $row['line'] . "', ext = '" . $row['ext'] . "', description = '" . $this->eda->escapeSimple($name) . "' WHERE luid =  " . $row['luid'];
                sql($sql);
            }
            return(TRUE);
        }
    }


     * This will either a. delete said line or b. delete said device from line
     * @param <type> $line
     * @return <type>

    function delete_line($lineid, $allow_device_remove=FALSE) {
        $sql = 'SELECT mac_id FROM endpointman_line_list WHERE luid = ' . $lineid;
        $mac_id = sql($sql, 'getOne');
        $row = $this->get_phone_info($mac_id);

        $sql = 'SELECT COUNT(*) FROM endpointman_line_list WHERE mac_id = ' . $mac_id;
        $num_lines = sql($sql, 'getOne');
        if ($num_lines > 1) {
            $sql = "DELETE FROM endpointman_line_list WHERE luid=" . $lineid;
            sql($sql);
            $this->message['delete_line'] = "Deleted!";
            return(TRUE);
        } else {
            if ($allow_device_remove) {
                $sql = "DELETE FROM endpointman_line_list WHERE luid=" . $lineid;
                sql($sql);

                $sql = "DELETE FROM endpointman_mac_list WHERE id=" . $mac_id;
                sql($sql);
                $this->message['delete_line'] = "Deleted!";
                return(TRUE);
            } else {
                $this->error['delete_line'] = _("You can't remove the only line left") . "!";
                return(FALSE);
            }
        }
    }

    function delete_device($mac_id) {
        $sql = "DELETE FROM endpointman_mac_list WHERE id=" . $mac_id;
        sql($sql);

        $sql = "DELETE FROM endpointman_line_list WHERE mac_id=" . $mac_id;
        sql($sql);
        $this->message['delete_device'] = "Deleted!";
        return(TRUE);
    }
*/
 /*   function get_message($function_name) {
        if (isset($this->message[$function_name])) {
            return($this->message[$function_name]);
        } else {
            return("Unknown Message");
        }
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
        $row = sql($sql, 'getRow', DB_FETCHMODE_ASSOC);

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
                                if ((isset($item_data['loop_count'])) AND (isset($variables[$key]))) {
                                    $custom_cfg_data[$key] = $variables[$key];
                                    $ari_key = "ari_" . $key;
                                    if (isset($variables[$ari_key])) {
                                        if ($variables[$ari_key] == "on") {
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
                                if ((isset($item_data['line_count'])) AND (isset($variables[$key]))) {
                                    $custom_cfg_data[$key] = $variables[$key];
                                    $ari_key = "ari_" . $key;
                                    if (isset($variables[$ari_key])) {
                                        if ($variables[$ari_key] == "on") {
                                            $custom_cfg_data_ari[$key] = 1;
                                        }
                                    }
                                }
                            }
                            break;
                        case "option":
                            if (isset($variables[$key])) {
                                $custom_cfg_data[$key] = $variables[$key];
                                $ari_key = "ari_" . $key;
                                if (isset($variables[$ari_key])) {
                                    if ($variables[$ari_key] == "on") {
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

            if (isset($variables['config_files'][$i])) {

                $variables[$config_files[$i]] = explode("_", $variables['config_files'][$i], 2);

                $variables[$config_files[$i]] = $variables[$config_files[$i]][0];
                if ($variables[$config_files[$i]] > 0) {
                    $config_files_selected[$config_files[$i]] = $variables[$config_files[$i]];


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
			//print_r($sql);
        } else {
            $sql = 'UPDATE endpointman_mac_list SET config_files_override = \'' . addslashes($config_files_selected) . '\', template_id = 0, global_custom_cfg_data = \'' . addslashes($save) . '\' WHERE id =' . $id;
            $location = "devices_manager";
        }
        sql($sql);

        $phone_info = array();
/*
        if ($custom != 0) {
            $phone_info = $this->get_phone_info($id);
            if (isset($variables['epm_reboot'])) {
                $this->prepare_configs($phone_info);
            } else {
                $this->prepare_configs($phone_info, FALSE);
            }
        } else {
            $sql = 'SELECT id FROM endpointman_mac_list WHERE template_id = ' . $id;
            $phones = sql($sql, 'getAll', DB_FETCHMODE_ASSOC);
            foreach ($phones as $data) {
                $phone_info = $this->get_phone_info($data['id']);
                if (isset($variables['epm_reboot'])) {
                    $this->prepare_configs($phone_info);
                } else {
                    $this->prepare_configs($phone_info, FALSE);
                }
            }
        }
*/

        if (isset($variables['silent_mode'])) {
            echo '<script language="javascript" type="text/javascript">window.close();</script>';
        } else {
            return($location);
        }
    }



    function display_configs() {

    }












    //BORRAR!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
	//OBSOLETO, ANTIGUAMENTE VENTANAS EMERGENTES, AHORA SON DIALOGOS JQUERY.
 /*   function prepare_message_box() {
        $error_message = NULL;
        foreach ($this->error as $key => $error) {
            $error_message .= $error;
            if ($this->eda->global_cfg['debug']) {
                $error_message .= " Function: [" . $key . "]";
            }
            $error_message .= "<br />";
        }
        $message = NULL;
        foreach ($this->message as $key => $error) {
            if (is_array($error)) {
                foreach ($error as $sub_error) {
                    $message .= $sub_error;
                    if ($this->eda->global_cfg['debug']) {
                        $message .= " Function: [" . $key . "]";
                    }
                    $message .= "<br />";
                }
            } else {
                $message .= $error;
                if ($this->eda->global_cfg['debug']) {
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
*/




}
