<?php
global $active_modules;

if (!empty($active_modules['endpoint']['rawname'])) {
	if (FreePBX::Endpointman()->configmod->get("disable_endpoint_warning") !== "1") {
		include('page.epm_warning.php');  
	}
}
?>

<script>
 var $hwgrid = $('#hwgrid');
    var mydata = 
[
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$globaladminpassword')?>",
		"description": "<?php echo _('Global Admin Password')?>"
    },
	    {
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$globaladminpassword')?>",
		"description": "<?php echo _('Global Admin Password')?>"
    },
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$globaluserpassword')?>",
		"description": "<?php echo _('Global User Password')?>"
    },
    {
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$globaluserpassword')?>",
		"description": "<?php echo _('Global User Password')?>"
    },
    {
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$provisuser')?>",
		"description": "<?php echo _('Sysadmin Pro Provisioning HTTP User')?>"
    },
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$provisuser')?>",
		"description": "<?php echo _('Sysadmin Pro Provisioning HTTP User')?>"
    },
    {
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$provispass')?>",
		"description": "<?php echo _('Sysadmin Pro Provisioning HTTP Password')?>"
    },
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$provispass')?>",
		"description": "<?php echo _('Sysadmin Pro Provisioning HTTP Password')?>"
    },
    {
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$sslhpro')?>",
		"description": "<?php echo _('Sysadmin Pro Provisioning HTTPS Port')?>"
    },
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$sslhpro')?>",
		"description": "<?php echo _('Sysadmin Pro Provisioning HTTPS Port')?>"
    },
    {
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$hpro')?>",
		"description": "<?php echo _('Sysadmin Pro Provisioning HTTP Port')?>"
    },
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$hpro')?>",
		"description": "<?php echo _('Sysadmin Pro Provisioning HTTP Port')?>"
    },
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$line')?>",
		"description": "<?php echo _('Prints the line Number of the mapped extension')?>"
    },
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$username')?>",
		"description": "<?php echo _('Username for the Extension (most likely the endpoint extension number)')?>"
    },
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$authname')?>",
		"description": "<?php echo _('Auth name for the Extension (most likely the endpoint extension number)')?>"
    },
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Yealink')?>",
        "placeholder": "<?php echo _('$yealinktransport')?>",
		"description": "<?php echo _('Transport protocoll for Yealink (UDP,TCP,TLS)')?>"
    },
    {
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Yealink')?>",
        "placeholder": "<?php echo _('$accXyealinktransport')?>",
		"description": "<?php echo _('Transport protocoll for Yealink (UDP,TCP,TLS)')?>"
    },
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Yealink')?>",
        "placeholder": "<?php echo _('$yealinksrtp')?>",
		"description": "<?php echo _('SRTP Value for Yealink')?>"
    },
    {
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Yealink')?>",
        "placeholder": "<?php echo _('$accXyealinksrtp')?>",
		"description": "<?php echo _('SRTP Value for Yealink')?>"
    },
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$secret')?>",
		"description": "<?php echo _('Password for the mapped Extension')?>"
    },	
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$displayname')?>",
		"description": "<?php echo _('Display name for the Extension (The Name of the Extension')?>"
    },	
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$server_host')?>",
		"description": "<?php echo _('Server Hostname for the Extension (You can set your Hostname in your global settings)')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$server_port')?>",
		"description": "<?php echo _('The port your extension uses to connect. (The prefered Port you set in your extension will be used)')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$proto')?>",
		"description": "<?php echo _('Shows your Protocol your extension is using')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$user_extension')?>",
		"description": "<?php echo _('Shows your Extension number you are using')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$extension')?>",
		"description": "<?php echo _('Shows your Extension number you are using')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$allowedcodec')?>",
		"description": "<?php echo _('Prints you allowed codecs you set in your extension settings')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$forcerport')?>",
		"description": "<?php echo _('Shows you if you are using rport')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$media_encryption')?>",
		"description": "<?php echo _('Shows you if you have enabled media encryption')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$sipdriver')?>",
		"description": "<?php echo _('Shows you if you use SIP or PJSIP')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$transport')?>",
		"description": "<?php echo _('Shows you your prefered transport protocol')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$trustrpid')?>",
		"description": "<?php echo _('Trustrpid?')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$callerid')?>",
		"description": "<?php echo _('Prints your full callerid including name and number')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$encryption')?>",
		"description": "<?php echo _('Shows you if you have enabled encryption')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$tech')?>",
		"description": "<?php echo _('Shows you if you use SIP or PJSIP')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXsecret')?>",
		"description": "<?php echo _('Password for the mapped Extension (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXdisplayname')?>",
		"description": "<?php echo _('Display name for the Extension (The Name of the Extension (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXuser_extension')?>",
		"description": "<?php echo _('Shows your Extension number you are using (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXusername')?>",
		"description": "<?php echo _('Username for the Extension (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXauthname')?>",
		"description": "<?php echo _('Auth name for the Extension (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXextension')?>",
		"description": "<?php echo _('Shows your Extension number you are using (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXallowedcodec')?>",
		"description": "<?php echo _('Prints you allowed codecs you set in your extension settings (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXforcerport')?>",
		"description": "<?php echo _('Shows you if you are using rport (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXmedia_encryption')?>",
		"description": "<?php echo _('Shows you if you have enabled media encryption (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXsipdriver')?>",
		"description": "<?php echo _('Shows you if you use SIP or PJSIP (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXtransport')?>",
		"description": "<?php echo _('Shows you your prefered transport protocol (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXtrustrpid')?>",
		"description": "<?php echo _('Trustrpid? (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXcallerid')?>",
		"description": "<?php echo _('Prints your full callerid including name and number (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXencryption')?>",
		"description": "<?php echo _('Shows you if you have enabled encryption (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$accXserver_port')?>",
		"description": "<?php echo _('The port your extension uses to connect. The prefered Port you set in your extension will be used. (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Grandstream')?>",
        "placeholder": "<?php echo _('$accXgsSRTP')?>",
		"description": "<?php echo _('This is the SRTP Value (0/1) for Grandstream, tested with HT812 (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Grandstream')?>",
        "placeholder": "<?php echo _('$accXgsproto')?>",
		"description": "<?php echo _('Sets the Protocol you set in you extension (TCP,UDP,TLS - Values:0,1,2),tested with HT812 (change the X after acc with your line number)')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Grandstream')?>",
        "placeholder": "<?php echo _('$gsSRTP')?>",
		"description": "<?php echo _('This is the SRTP Value (0/1) for Grandstream, tested with HT812')?>"
	},
    {
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Grandstream')?>",
        "placeholder": "<?php echo _('$gsproto')?>",
		"description": "<?php echo _('Sets the Protocol you set in you extension (TCP,UDP,TLS - Values:0,1,2),tested with HT812')?>"
    },
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$primtimeserver')?>",
		"description": "<?php echo _('Hostname of the Primary NTP Server from Global Settings')?>"
	},
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$primtimeserver')?>",
		"description": "<?php echo _('Hostname of the Primary NTP Server from Global Settings')?>"
	},
	{
		"type": "<?php echo _('Line Loop')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$myvoicemail')?>",
		"description": "<?php echo _('Number of the Voicemail from Featurecodes')?>"
	},
	{
		"type": "<?php echo _('Static')?>",
        "brand": "<?php echo _('Global')?>",
        "placeholder": "<?php echo _('$myvoicemail')?>",
		"description": "<?php echo _('Number of the Voicemail from Featurecodes')?>"
	}
	
];

$(function () {
    $('#hwgrid').bootstrapTable({
        data: mydata
    });
});
</script>
<div class="container-fluid">
<h2><?php echo _('Config File Placeholder Values');?></h2>
<div class="fpbx-container">
<div class="display full-border">
<p>
This page helps you building your phone Packages. <br /> <br />
Create or modify your config files and replace the needed values with the Placeholders. <br />
With this information you can add new Phones to OSS EPM within minutes. <br /><br />
If you need a specific value to add your Phone you can make a feature request.
</p>

					<table id="hwgrid" data-pagination="true" data-show-columns="true" data-show-toggle="true" data-search="true"  class="table table-striped">
						<thead>
							<tr>
								<th data-field="type" data-sortable="true"><?php echo _("Type")?></th>
								<th data-field="brand" data-sortable="true"><?php echo _("Brand")?></th>
								<th data-field="placeholder" data-sortable="true"><?php echo _("Placeholder")?></th>
								<th data-field="description" data-sortable="true"><?php echo _("Description")?></th>
							</tr>
						</thead>
						<tbody>

								</td>
								<td>

								</td>
								<td>
								

								</td>
							</tr>
						</tbody>
					</table>
					
					
					
					
				</div>
			</div>
		</div>
	</div>
</div>