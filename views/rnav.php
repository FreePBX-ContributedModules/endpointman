<?php
$list = array(
			'epm_oss' 			=> _('About OSS Endpoint Manager'),
			'epm_advanced'	=> _('Settings'),
			'epm_devices'	=> _('Extension Mapping'),
			'epm_config'	=>  _('Package Manager'),
			'epm_templates'	=>  _('Template Manager'),
			'epm_placeholders'	=>  _('Config File Placeholders')
		);
$li = array();
		
foreach ($list as $k => $v) {
	// If current user does not have access to this sub-menu then don't display it
	//

	if (is_object($_SESSION["AMP_user"]) && !$_SESSION["AMP_user"]->checkSection($k)) {
		continue;
	}
	$li[$k] = $v;

	
}
$li[] = '<hr />';

echo '<div class="list-group">';


if (array_key_exists('epm_oss', $li)){
echo '<span class="list-group-item"><h3>' . _("Open Source Information") . '</h3></span>';
echo '<a class="list-group-item" href="?display=epm_oss">' . $li['epm_oss'] . '</a>';
}


if (
array_key_exists('epm_advanced', $li) or 
array_key_exists('epm_devices', $li)
)
{
echo '<span class="list-group-item"><h3>' . _("Endpoint Manager") . '</h3></span>';
}

if (array_key_exists('epm_advanced', $li)){
echo '<a class="list-group-item" href="?display=epm_advanced">' . $li['epm_advanced'] . '</a>';
}
if (array_key_exists('epm_devices', $li)){
echo '<a class="list-group-item" href="?display=epm_devices">' . $li['epm_devices'] . '</a>';
}
if (array_key_exists('epm_config', $li)){
echo '<span class="list-group-item"><h3>' . _("Brands") . '</h3></span>';
echo '<a class="list-group-item" href="?display=epm_config">' . $li['epm_config'] . '</a>';
}
if (
array_key_exists('epm_templates', $li) or 
array_key_exists('epm_placeholders', $li)
)
{
echo '<span class="list-group-item"><h3>' . _("Advanced") . '</h3></span>';
}
if (array_key_exists('epm_templates', $li)){
echo '<a class="list-group-item" href="?display=epm_templates">' . $li['epm_templates'] . '</a>';
}
if (array_key_exists('epm_placeholders', $li)){
echo '<a class="list-group-item" href="?display=epm_placeholders">' . $li['epm_placeholders'] . '</a>';
}
echo '</div>';
?>

