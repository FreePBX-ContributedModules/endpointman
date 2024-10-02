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

if ((! isset($_REQUEST['subpage'])) || ($_REQUEST['subpage'] == "")) {
	$_REQUEST['subpage'] = "manager";
}

?>
<div class="container-fluid" id="epm_templates">
	<h1><?php echo _("End Point Configuration Manager")?></h1>
	<?php 
	foreach($epm->myShowPage() as $key => $page) {
		if (strtolower($_REQUEST['subpage']) == $key) 
		{
		?>
		<h2><?php echo $page['name']; ?></h2>
		<div class = "display">
			<div class="row">
				<div class="col-sm-12">
					<div class="fpbx-container">
						<div class="display <?php echo ($key == "editor") ? "full" : "no"?>-border">
							<?php include($page['page']); ?>			
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		}
	}
	?>
</div>

<?php

if ($_REQUEST['subpage'] == "editor")  {
	echo "<br /><br /><br />";
}

if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'save_template') {
   $epm->save_template($_REQUEST['id'],$_REQUEST['custom'],$_REQUEST);
    if(empty($epm->error)) {
        $epm->message['general'] = _('Saved');
    }
}

?>
