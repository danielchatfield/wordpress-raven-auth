<?php
/*
    This file is called by RavenAuth_Admin
*/
?>
<div class="wrap">
  <h2>Raven Authentication Settings</h2>

  <form method="post" action="options.php">
  	<?php
  	settings_fields( 'ravenauth' );
    do_settings_sections( 'ravenauth' );
    submit_button(); 
  	?>
  </form>
</div>
