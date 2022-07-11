<?php

namespace Ainsys\Connector\Master;

try {
	$status = Core::is_ainsys_integration_active( 'check' );
} catch ( \Exception $e ) {
	echo esc_html( $e->getMessage() );
}

?>
<div class="wrap ainsys_settings_wrap">
	<h1><img src="<?= AINSYS_CONNECTOR_URL; ?>/assets/img/logo.png" alt="Ainsys logo" class="ainsys-logo"></h1>

	<div class="nav-tab-wrapper ainsys-nav-tab-wrapper">
		<a class="nav-tab nav-tab-active" href="#setting_section_general" data-target="setting_section_general"><?php
			_e( 'General', AINSYS_CONNECTOR_TEXTDOMAIN ) ?></a>
		<a class="nav-tab" href="#setting_section_log" data-target="setting_section_log"><?php
			_e( 'Transfer log', AINSYS_CONNECTOR_TEXTDOMAIN ) ?></a>
		<a class="nav-tab" href="#setting_entities_section" data-target="setting_entities_section"><?php
			_e( 'Entities export settings', AINSYS_CONNECTOR_TEXTDOMAIN ) ?></a>
	</div>

	<div id="setting_section_general" class="tab-target nav-tab-active tab-target-active">
		<form method="post" action="options.php">
			<?php
			settings_fields( Settings::get_option_name( 'group' ) ); ?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php
						_e( 'AINSYS handshake url for the connector. You can find it in your ', AINSYS_CONNECTOR_TEXTDOMAIN ) ?>
						<a href="https://app.ainsys.com/en/settings/workspaces" target="_blank"><?php
							_e( 'dashboard', AINSYS_CONNECTOR_TEXTDOMAIN ) ?></a>.
					</th>
					<td><input type="text" size="50" name="<?php
						echo esc_html( Settings::get_option_name( 'ansys_api_key' ) ); ?>"
					           placeholder="XXXXXXXXXXXXXXXXXXXXX" value="<?php
						echo esc_html( Settings::get_option( 'ansys_api_key' ) ); ?>"/>
						<?php
						if ( ! empty( $status ) && $status['status'] === 'success' ): ?>
							<a id="remove_ainsys_integration" class="button"> <?php
								_e( 'Disconect integration', AINSYS_CONNECTOR_TEXTDOMAIN ) ?> </a>
						<?php
						endif; ?>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php
						_e( 'Server hook_url', AINSYS_CONNECTOR_TEXTDOMAIN ) ?>
					</th>
					<td><input type="text" size="50" name="<?php
						echo( Settings::get_option_name( 'hook_url' ) ); ?>" value="<?php
						echo Settings::get_option( 'hook_url' ); ?>" disabled="1"/></td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<?php
						_e( 'Резервный e-mail', AINSYS_CONNECTOR_TEXTDOMAIN ) ?>
						<span style="font-weight: normal;color:#666;"><?php
							_e( 'Used for error reports', AINSYS_CONNECTOR_TEXTDOMAIN )
							?></span>
					</th>
					<td><input type="text" name="<?php
						echo esc_html( Settings::get_option_name( 'backup_email' ) );
						?>" placeholder="backup@email.com" value="<?php
						echo esc_html( Settings::get_backup_email() );
						?>"/></td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" class="button-primary" value="<?php
				_e( 'Save', AINSYS_CONNECTOR_TEXTDOMAIN ) ?>"/>
			</p>
			<?php
			$ainsys_status_items = array(
				'woocommerce' => array(
					'title'  => __( 'WordPress WooCommerce activated -', AINSYS_CONNECTOR_TEXTDOMAIN ),
					'active' => Settings::is_plugin_active( 'woocommerce/woocommerce.php' ),
				),

			);

			$ainsys_status_items = apply_filters( 'ainsys_status_list', $ainsys_status_items );

			/* append those to be the last */
			$ainsys_status_items['curl'] = array(
				'title'  => 'CURL -',
				'active' => extension_loaded( 'curl' )
			);
			$ainsys_status_items['ssl']  = array(
				'title'  => 'SSL -',
				'active' => \is_ssl()
			);

			?>
			<div class="ainsys_status_panel">
				<div class="ainsys_status_panel__content">
					<h2><?php
						_e( 'Status', AINSYS_CONNECTOR_TEXTDOMAIN ) ?></h2>
					<ul>
						<li>
							<?php
							_e( 'Conection', AINSYS_CONNECTOR_TEXTDOMAIN ) ?> -
							<?php
							if ( ! empty( $status ) && $status['status'] === 'success' ): ?>
								<span style="color: #46b450;"><?php
									_e( 'Working', AINSYS_CONNECTOR_TEXTDOMAIN ) ?></span>
							<?php
							else: ?>
								<span style="color: #dc3232;"><?php
									_e( 'No AINSYS integration', AINSYS_CONNECTOR_TEXTDOMAIN ) ?></span>
							<?php
							endif; ?>
						</li>

						<?php foreach ( $ainsys_status_items as $status_key => &$status_item ): ?>
							<li>
								<?php echo esc_html( $status_item['title'] ); ?>
								<?php if ( $status_item['active'] ): ?>
									<span style="color: #46b450;">
										<?php _e( 'Enable', AINSYS_CONNECTOR_TEXTDOMAIN ) ?>
									</span>
								<?php else: ?>
									<span style="color: #dc3232;">
										<?php _e( 'Disabled', AINSYS_CONNECTOR_TEXTDOMAIN ) ?>
									</span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>

						<li>
							<?php
							_e( 'PHP version 7.2+ -', AINSYS_CONNECTOR_TEXTDOMAIN ) ?>
							<?php
							if ( version_compare( PHP_VERSION, '7.2.0' ) > 0 ): ?>
								<span style="color: #46b450;">PHP <?php
									echo esc_html( PHP_VERSION ); ?></span>
							<?php
							else: ?>
								<span style="color: #dc3232;"><?php
									_e( 'Bad PHP version ', AINSYS_CONNECTOR_TEXTDOMAIN ) ?>(<?php
									echo esc_html( PHP_VERSION ); ?>). <?php
									_e( 'Update on your hosting', AINSYS_CONNECTOR_TEXTDOMAIN ) ?></span>
							<?php
							endif; ?>
						</li>
						<li>
							<?php
							_e( 'Backup email -', AINSYS_CONNECTOR_TEXTDOMAIN ) ?>
							<?php
							if ( ! empty( Settings::get_backup_email() ) && filter_var( Settings::get_backup_email(), FILTER_VALIDATE_EMAIL ) ): ?>
								<span style="color: #46b450;"><?php
									_e( 'Valid', AINSYS_CONNECTOR_TEXTDOMAIN ) ?></span>
							<?php
							else: ?>
								<span style="color: #dc3232;"><?php
									_e( 'Invalid', AINSYS_CONNECTOR_TEXTDOMAIN ) ?></span>
							<?php
							endif; ?>
						</li>
					</ul>
				</div>
			</div>

		</form>

	</div>

	<div id="setting_section_log" class="tab-target">
		<?php
		$start = Settings::$do_log_transactions ? ' disabled' : '';
		$stop  = Settings::$do_log_transactions ? '' : ' disabled';

		$controls = '<div class="controls">';
		$controls .= '<a id="start_loging" class="button button-primary loging_controll' . $start . '">' . __( 'Start loging', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</a>';
		$controls .= '<select id="start_loging_timeinterval" class="' . $start . '" name="loging_timeinterval">
                            <option value="1">' . __( '1 hour', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</option>
                            <option value="5">' . __( '5 hours', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</option>
                            <option value="12">' . __( '12 hours', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</option>
                            <option value="24">' . __( '24 hours', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</option>
                            <option value="-1" selected="selected">' . __( 'unlimited', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</option>
                    </select>';
		$controls .= '<a id="stop_loging" class="button button-primary loging_controll' . $stop . '">' . __( 'Stop loging', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</a>';
		$controls .= '<a id="reload_log" class="button button-primary">' . __( 'Reload', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</a>';
		$controls .= '<a id="clear_log" class="button button-primary">' . __( 'Clear log', AINSYS_CONNECTOR_TEXTDOMAIN ) . '</a>
                    </div>';

		echo '<div class="log_block">' . $controls . HTML::generate_log_html() . '</div>';

		?>
	</div>

	<div id="setting_entities_section" class="tab-target">
		<?php
		echo HTML::generate_entities_html() ?>

		<p>  <?php
			_e( 'Detailed', AINSYS_CONNECTOR_TEXTDOMAIN ) ?><a
				href="https://gitlab.ainsys.com/dev06/ainsys_connector"><?php
				_e( ' API integration', AINSYS_CONNECTOR_TEXTDOMAIN ) ?> </a> <?php
			_e( ' documentation.', AINSYS_CONNECTOR_TEXTDOMAIN ) ?></p>
	</div>
</div>
<script>
	jQuery( document ).ready( function ( $ ) {
		$( '#full_uninstall_checkbox' ).on( 'click', function () {
			let val = $( this ).val() == 1 ? 0 : 1
			$( this ).attr( 'value', val )
			$( this ).prop( 'checked', val )
		} )
		$( '#display_debug' ).on( 'click', function () {
			let val = $( this ).val() == 1 ? 0 : 1
			$( this ).attr( 'value', val )
			$( this ).prop( 'checked', val )
		} )
	} )
</script>

<!--        !!Debug  BLOCK !!           -->
<?php
echo HTML::generate_debug_log() ?>
