<?php

get_header('woocrl');

?>
<div id="woocrl-page">
	<?php
		$su = get_option( 'woocrl_su', '' );
	?>
	<div>
		<p>
			<label>Source URL:<br>
			<input type="text" id="woocrl_su" name="woocrl_su" value="<?=esc_url($su)?>">
			<input type="hidden" id="woocrl_ua" name="woocrl_ua" value="<?=esc_attr($_SERVER['HTTP_USER_AGENT'])?>">
			</label>
		</p>
		<p><button type="button" id="woocrl_do" class="woocrl-button">Get products</button> <button type="button" id="woocrl_stop" class="woocrl-button">Stop</button></p>
	</div>
	<div id="woocrl-message"></div>
	<table id="woocrl-results">
		
	</table>
</div>
<?php

get_footer('woocrl');
