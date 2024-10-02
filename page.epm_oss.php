<?php
global $active_modules;

if (!empty($active_modules['endpoint']['rawname'])) {
	if (FreePBX::Endpointman()->configmod->get("disable_endpoint_warning") !== "1") {
		include('page.epm_warning.php');  
	}
}
?>
  <h3>Open Source Information</h3>
 
<p>OSS PBX End Point Manager is the community supported PBX Endpoint Manager for FreePBX.<br>The front end WebUI is hosted at: <a href="https://github.com/FreePBX/endpointman" class="external-link" rel="nofollow">https://github.com/FreePBX/endpointman</a><br>The back end configurator is hosted at: <a href="https://github.com/provisioner/Provisioner" class="external-link" rel="nofollow">https://github.com/provisioner/Provisioner</a><br>Pull Requests can be made to either of these and are encouraged.</p>

<div><span class="aui-icon aui-icon-small aui-iconfont-info confluence-information-macro-icon"> </span><div class="confluence-information-macro-body"><p>This is not the same at the commercial EPM and It is <strong>NOT</strong> supported by FreePBX or Sangoma Technologies inc. If you are looking for a Commercially supported endpoint manager please look into the Commercial Endpoint Manager by <span>Sangoma Technologies inc</span></p></div></div>

<br>