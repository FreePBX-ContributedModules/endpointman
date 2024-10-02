<?php
global $active_modules;

if (!empty($active_modules['endpoint']['rawname'])) {
	if (FreePBX::Endpointman()->configmod->get("disable_endpoint_warning") !== "1") {
		include('page.epm_warning.php');  
	}
}
?>

<?PHP
/**
 * Endpoint Manager FreePBX File
 *
 * @author Javier Pastor
 * @license MPL / GPLv2 / LGPL
 * @package Endpoint Manager
 */
 
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

$epm = FreePBX::create()->Endpointman;
?>

<div class="container-fluid" id="epm_config">
	<h1><?php echo _("End Point Configuration Manager")?></h1>
	<h2><?php echo _("Package Manager")?></h2>
    <div class = "display full-border">
        <div class="row">
            <div class="col-sm-12">
                <div class="fpbx-container">
                <?php
					echo load_view(__DIR__.'/views/epm_config_manager.page.php', array('epm' => $epm));
				?>
                </div>
            </div>
        </div>
    </div>
</div>