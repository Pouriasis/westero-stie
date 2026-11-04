<?php
if ( isset( $_POST['submit-bpu'] ) ) {
	if ( isset( $_POST['bspu_nonce'] ) && wp_verify_nonce( $_POST['bspu_nonce'], 'bspu_save_settings' ) && current_user_can( 'manage_options' ) ) {
		$block_plugin_updates = isset( $_POST['block_plugin_updates'] ) && is_array( $_POST['block_plugin_updates'] ) ? $_POST['block_plugin_updates'] : array();
		$blocked_plugins      = implode( '###', array_map( 'sanitize_text_field', $block_plugin_updates ) );
		update_option( 'bpu_update_blocked_plugins', $blocked_plugins );
		
		// Clear plugin updates cache and force recheck immediately
		wp_clean_plugins_cache();
		wp_update_plugins();
		
		$bspu_save_MSG                     = 'Updated Successfully. The update cache has been cleared and updates have been rechecked.';
		$bpu_update_blocked_plugins_array = ! empty( $blocked_plugins ) ? explode( '###', $blocked_plugins ) : array();
	} else {
		$bspu_save_MSG = 'Sorry, your nonce did not verify or you do not have sufficient permissions. Please try again.';
	}
}
?>

<?php if ( ! empty( $bspu_save_MSG ) ) : ?>
	<div class="updated" id="message"><p><?php echo esc_html( $bspu_save_MSG ); ?></p></div>
<?php endif; ?>

<table class="wp-list-table widefat fixed bookmarks">
	<thead>
		<tr>
			<th>Select plugin you want to disable from updates</th>
		</tr>
	</thead>
	<tbody>
	<tr>
		<td>
			<form method="post" action="">
			
				<?php 
				if ( ! empty( $plugins ) ) {
					foreach ( $plugins as $plugin_key_name => $plugin ) : 
						$is_checked = in_array( $plugin_key_name, $bpu_update_blocked_plugins_array, true ) ? 'checked="checked"' : '';
						?>
						<p>
							<label>
								<input type="checkbox" class="space-right" name="block_plugin_updates[]" <?php echo $is_checked; ?> value="<?php echo esc_attr( $plugin_key_name ); ?>" />
								&nbsp;&nbsp;&nbsp;<?php echo esc_html( $plugin['Name'] ); ?>
							</label>
						</p>
					<?php 
					endforeach; 
				} else { 
					?>
					<p>No Plugin Found</p>
				<?php } ?>
			
			<p class="submit">
				<?php wp_nonce_field( 'bspu_save_settings', 'bspu_nonce' ); ?>
				<input type="submit" name="submit-bpu" class="button-primary" value="<?php echo esc_attr__( 'Save Changes' ); ?>" />
			</p>
		</form>
			
</td>
</tr>
</tbody>
</table><br/>