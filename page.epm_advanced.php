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
$epm = FreePBX::create()->Endpointman;

if ((! isset($_REQUEST['subpage'])) || ($_REQUEST['subpage'] == "")) {
	$_REQUEST['subpage'] = "settings";
}

?>

<div class="container-fluid" id="epm_advanced">
	<h1><?php echo _("End Point Configuration Manager")?></h1>
	<h2><?php echo _("Advanced Settings")?></h2>
	<div class="row">
		<div class="col-sm-12">
			<div class="fpbx-container">
				<div class="display no-border">
					<div class="nav-container">
						<div class="scroller scroller-left"><i class="glyphicon glyphicon-chevron-left"></i></div>
						<div class="scroller scroller-right"><i class="glyphicon glyphicon-chevron-right"></i></div>
						<div class="wrapper">
							<ul class="nav nav-tabs list" role="tablist" id="list-tabs-epm_advanced">
								<?php foreach($epm->myShowPage() as $key => $page) { ?>
									<li data-name="<?php echo $key?>" class="change-tab <?php echo $key == $_REQUEST['subpage'] ? 'active' : ''?>"><a href="#<?php echo $key?>" aria-controls="<?php echo $key?>" role="tab" data-toggle="tab"><?php echo $page['name']?></a></li>
								<?php } ?>
							</ul>
						</div>
					</div>
					<div class="tab-content display">
						<?php foreach($epm->myShowPage() as $key => $page) { ?>
							<div id="<?php echo $key?>" class="tab-pane <?php echo $key == $_REQUEST['subpage'] ? 'active' : ''?>">
								<?php echo $page['content']?>
							</div>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>