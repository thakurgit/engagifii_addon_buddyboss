<?php
// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'engagifii_admin_enqueue_script' ) ) {
	function engagifii_admin_enqueue_script() {
		wp_enqueue_style( 'buddyboss-addon-admin', plugin_dir_url( __FILE__ ) . 'assets/css/style.css' );
		wp_enqueue_script( 'buddyboss-addon-admin', plugin_dir_url( __FILE__ ) . 'assets/js/admin-script.js',array('jquery'), '1.0',true); 
	}

	add_action( 'admin_enqueue_scripts', 'engagifii_admin_enqueue_script' );
}

if ( ! function_exists( 'engagifii_get_settings_sections' ) ) {
	function engagifii_get_settings_sections() {

		$settings = array(
			'engagifii_jwt_settings' => array(
				'page'  => 'bp-engagifii_settings',
				'title' => __( 'JWT Settings', 'engagifii-addon' ),
			),
			'engagifii_general_settings' => array(
				'page'  => 'bp-engagifii_settings',
				'title' => __( 'API Settings', 'engagifii-addon' ),
			),
			'engagifii_fields_settings' => array(
				'page'  => 'bp-engagifii_settings',
				'title' => __( 'Member Fields Settings', 'engagifii-addon' ),
			),
			'engagifii_hubs_settings' => array(
				'page'  => 'bp-engagifii_settings',
				'title' => __( 'Hubs Settings', 'engagifii-addon' ),
			),
			'engagifii_misc_settings' => array(
				'page'  => 'bp-engagifii_settings',
				'title' => __( 'Miscellaneous Settings', 'engagifii-addon' ),
			),
			'engagifii_cron_logs' => array(
				'page'  => 'bp-engagifii_settings',
				'title' => __( 'Engagifii Cron Logs', 'engagifii-addon' ),
			), 
			
			
		);
		

		return (array) apply_filters( 'engagifii_get_settings_sections', $settings );
	}
}

if ( ! function_exists( 'engagifii_get_settings_fields_for_section' ) ) {
	function engagifii_get_settings_fields_for_section( $section_id = '' ) {

		// Bail if section is empty
		if ( empty( $section_id ) ) {
			return false;
		}

		$fields = engagifii_get_settings_fields();
		$retval = isset( $fields[ $section_id ] ) ? $fields[ $section_id ] : false;

		return (array) apply_filters( 'engagifii_get_settings_fields_for_section', $retval, $section_id );
	}
}

if ( ! function_exists( 'engagifii_get_settings_fields' ) ) {
	function engagifii_get_settings_fields() {

		$fields = array();

		$fields['engagifii_general_settings'] = array(
			'bb_engagifii_env' => array(
				'title'             => __( 'Select API Environment', 'engagifii-addon' ),
				'callback'          => 'engagifii_api_settings_callback',
				'sanitize_callback' => '',
				'args'              => array(
					  'key'         => 'api_env',
					  'group'         => 'api',
				  ),
			), 
			'bb_engagifii_tenant' => array(
				'title'             => __( 'Tenant ID', 'engagifii-addon' ),
				'callback'          => 'engagifii_api_settings_callback',
				'sanitize_callback' => 'sanitize_text_or_array_field',
				'args'              => array(
					  'key'         => 'tenant',
					  'group'         => 'api',
				  ),
			), 
		);
		$fields['engagifii_fields_settings'] = array(
			'bb_engagifii_fields' => array(
				'title'             => __( 'Select Fields', 'engagifii-addon' ),
				'callback'          => 'engagifii_fields_settings_callback',
				'sanitize_callback' => '',
				'args'              => array(
					  'key'         => 'engagifii_fields',
					  'group'         => 'user_fields',
				  ),
			), 
			'bb_engagifii_readonly_fields' => array(
				'title'             => __( 'Restrict  Fields', 'engagifii-addon' ),
				'callback'          => 'engagifii_fields_settings_callback',
				'sanitize_callback' => '',
				'args'              => array(
					  'key'         => 'engagifii_readonly_fields',
					  'group'         => 'user_fields',
				  ),
			), 
		);
		$fields['engagifii_hubs_settings'] = array(
			'bb_engagifii_hubs' => array(
				'title'             => __( 'Member Hubs Sync', 'engagifii-addon' ),
				'callback'          => 'engagifii_hubs_settings_callback',
				'sanitize_callback' => '',
				'args'              => array(
					  'key'         => 'engagifii_hubs',
					  'group'         => 'hubs',
				  ),
			), 
		);
		$fields['engagifii_jwt_settings'] = array(
		  'bb_engagifii_username' => array(
		  'title'             => __( 'Username', 'engagifii-addon' ),
		  'callback'          => 'engagifii_jwt_settings_callback',
		  'sanitize_callback' => 'sanitize_text_or_array_field',
		  'args'              => array(
			'key'         => 'username',
			'group'         => 'jwt',
		),
	),
	'bb_engagifii_app_password' => array(
		'title'             => __( 'Application Password', 'engagifii-addon' ),
		'callback'          => 'engagifii_jwt_settings_callback',
		'sanitize_callback' => 'sanitize_text_or_array_field',
		'args'              => array(
			'key'         => 'app_password',
			'group'         => 'jwt',
		),
	),
	);
	$fields['engagifii_misc_settings'] = array(
		  'bb_engagifii_menu_rename' => array(
		  'title'             => __( 'Rename Buddyboss Menu', 'engagifii-addon' ),
		  'callback'          => 'engagifii_misc_callback',
		  'sanitize_callback' => 'sanitize_text_or_array_field',
		  'args'              => array(),
	),
	);
	$fields['engagifii_cron_logs'] = array(
		  'bb_engagifii_cron_logs' => array(
		  'title'             => __( 'Cron Logs', 'engagifii-addon' ),
		  'callback'          => 'engagifii_cron_logs_callback',
		  'sanitize_callback' => 'sanitize_text_or_array_field',
		  'args'              => array(),
	),
	);
			 
		return (array) apply_filters( 'engagifii_get_settings_fields', $fields );
	}
}

function engagifii_jwt_settings_callback( $args ) {
	$key     = $args['key'];
	$options = get_option( 'bb_engagifii' );
	$value   = isset( $options[$args['group']][ $key ] ) ? $options[$args['group']][ $key ] : '';
	echo '<input type="text" id="' . esc_attr( $key ) . '" name="bb_engagifii[jwt][' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" class="regular-text" />';
}
if ( ! function_exists( 'engagifii_api_settings_callback' ) ) {
	function engagifii_api_settings_callback($args ) {
		$key     = $args['key'];
	$options = get_option( 'bb_engagifii' );
	if($key=='api_env'){ ?>
		 <select name="bb_engagifii[api][environment]" class="select-env" >
                               <?php
                                  $envs = [
                                    '' => 'Production',
                                    '-qa' => 'QA',
                                    '-support' => 'Support',
                                    '-hotfix' => 'Hotfix',
                                    '-preview2' => 'Preview2',
                                    '-preview3' => 'Preview3',
                                    '-preview4' => 'Preview4',
                                    '-preview5' => 'Preview5',
                                    '-preview6' => 'Preview6',
                                    '-preview9' => 'Preview9',
                                    '-staging' => 'Staging'
                                  ];
                                
                                  foreach ($envs as $value => $label) {
                                    $selected = $options['api']['environment'] === $value ? ' selected' : '';
                                    echo "<option value='$value'$selected>$label</option>";
                                  }
                              ?>
                          </select>
        <span class="env-loading" style="display:none"><img style="max-width:100%" src="<?php echo engagifii_BB_ADDON_PLUGIN_URL.'assets/images/loader.gif';?>" alt=""></span><span class="env-loading-msg"></span>
        <?php
            $apiSettings = ['crmUrl','reportUrl','revenueUrl','doUrl', 'authUrl','tnaUrl','eventUrl','legisUrl','resourceUrl'];
            foreach ($apiSettings as $settingName) {
                $value = htmlspecialchars($options['api'][$settingName], ENT_QUOTES, 'UTF-8');
                $inputField = "<input name='bb_engagifii[api][$settingName]' class='$settingName' type='hidden' value='$value' />";
                echo $inputField;
            }
                              	
	} else {
		$value   = isset( $options[$args['group']][ $key ] ) ? $options[$args['group']][ $key ] : '';
		echo '<input type="text" id="' . esc_attr( $key ) . '" name="bb_engagifii[api][' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" class="regular-text" />';
	}
	if($key=='tenant'){
	 $last_execution = get_option('engagifii_cron_last_execution', 'No execution recorded yet.');
echo "<br>Last cron execution: " .date('F j, Y H:i:s', strtotime($last_execution)).'<br/>';
$timestamp = wp_next_scheduled('my_custom_cron_hook');
if ($timestamp) {
    $offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
    $local_time = $timestamp + $offset;
    echo "Next cron execution: " . date('F j, Y H:i:s', $local_time) . '<br>';
    echo "Current Time is: " . current_time('F j, Y H:i:s').'<br>';
    $remaining = $timestamp - time();
    if ($remaining > 0) {
        $hours = floor($remaining / 3600);
        $minutes = floor(($remaining % 3600) / 60);
        $seconds = $remaining % 60;
        echo "Remaining Time: {$hours}h {$minutes}m {$seconds}s<br>";
    } else {
        echo "Remaining Time: Less than a second<br>";
    }
} else {
    echo "Cron job is not scheduled.";
}  
?>
<button id="run-cron-event" class="button button-primary">Run Cron</button>
<p id="cron-status"></p>
<script type="text/javascript">
    jQuery(document).ready(function($) {
    $('#run-cron-event').click(function(event) {
        event.preventDefault(); // Prevent page reload
       $(this).prop('disabled', true).text('Running...'); // Disable button while running
        
        $.post(ajaxurl, {
            action: 'run_custom_cron_event'
        }, function(response) {
            $('#cron-status').text(response.success ? response.data : 'Error running cron event');
            $('#run-cron-event').prop('disabled', false).text('Run Cron'); // Re-enable button
        });
		
    });
});
</script>
        
		<?php
		}

	}
}
if ( ! function_exists( 'engagifii_hubs_settings_callback' ) ) {
  function engagifii_hubs_settings_callback( $args ) {
    $nonce = wp_create_nonce('fetch_hubs_nonce');
    $options = get_option('bb_engagifii');
    $timezone_string = get_option('timezone_string') ?: 'UTC';
    $wp_timezone = new DateTimeZone($timezone_string);
    $last_fetched = $options['misc']['hubs_last_fetched'] ?? null;

    $formatted_time = 'Never fetched';
    if ($last_fetched) {
      $dt = new DateTime("@$last_fetched"); // Use timestamp
      $dt->setTimezone($wp_timezone);
      $formatted_time = $dt->format('M d, Y h:i:s A');
    }
	 $access_token = $_COOKIE['access_token'] ?? null;
	 $response = wp_remote_post($options['api']['crmUrl'] . '/groups/list/count', [
    'headers' => [
      'Authorization' => 'Bearer ' . $access_token,
      'accept'        => 'application/json',
      'tenant-code'   => get_option('engagifii_sso_settings')['client_id'],
      'Content-Type'  => 'application/json',
    ],
    'body' => '{}'
  ]);
	$totalGroups = json_decode(wp_remote_retrieve_body($response), true) ?? 'N/A';
	$total_groups = groups_get_groups([
		'type'  => 'newest',
		'count' => true,
	]);
	echo '<div style="margin-bottom:8px"><label for="hub-fetch-limit" style="margin-right: 10px;">    Hubs per batch:  </label>
  <select id="hub-fetch-limit" >
    <option value="1">1</option>
    <option value="5" selected>5</option>
    <option value="10" >10</option>
    <option value="15">15</option>
    <option value="20">20</option>
  </select></div>';
	echo '<div style="margin-bottom:10px"><label for="hub-fetch-page" style="margin-right: 10px;">    Page Number:  </label>  <select id="hub-fetch-page" >';
    for ($i = 1; $i <= 20; $i++): ?>
      <option value="<?php echo $i ?>"><?php echo  $i ?></option> 
    <?php endfor; 
    echo '</select></div><button id="fetch-hubs" class="button button-primary">Fetch Hubs</button> <strong>('.$total_groups['total'].'/'.$totalGroups.'</strong> Hubs imported)<input type="hidden" id="last_fetched_time" name="bb_engagifii[misc][hubs_last_fetched]" value="'.$last_fetched.'">';
    echo '<p id="fetch-hub-status" class="cron-logs"><strong>Last fetched:</strong> ' . esc_html($formatted_time) . '</p>';
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
      const wpTimezone = '<?php echo esc_js($timezone_string); ?>';
      $('#fetch-hubs').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $status = $('#fetch-hub-status');
        var originalText = $button.text();

        $button.prop('disabled', true).text('Processing...');
		 $status.empty().prepend('<div>Fetching groups from API... (It may take upto few minutes)</div>');

        $.post(ajaxurl, {
          action: 'engagifii_hubs_fetch',
		  groupCount: jQuery('#hub-fetch-limit').val(),
		  groupPage: jQuery('#hub-fetch-page').val(),
          security: '<?php echo esc_js($nonce); ?>'
        }, function(response) {
          const now = new Date();
          const formatter = new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            timeZone: wpTimezone
          });
          const formattedTime = formatter.format(now);

          if (response.success) {
            // Show progress messages one by one with small delay for UX
            let statuses = response.data.statuses || [];
            let i = 0;
            function showNextStatus() {
              if (i < statuses.length) {
                $status.prepend('<div>' + statuses[i] + '</div>');
                i++;
                setTimeout(showNextStatus, 200);
              } else {
                $status.prepend('<div>✅ Completed.<br><strong>Last fetched:</strong> ' + formattedTime + '</div>');
                $button.prop('disabled', false).text(originalText);
              }
            }
            showNextStatus();
			$('#last_fetched_time').val(response.data.last_fetched);
          } else {
            $status.html('❌ Fetch failed: ' + response.data.error + '<br><strong>Last attempt:</strong> ' + formattedTime);
            $button.prop('disabled', false).text(originalText);
          }
        });
      });
    });
    </script>
    <?php
  }
}

if ( ! function_exists( 'engagifii_fields_settings_callback' ) ) {
	function engagifii_fields_settings_callback($args ) {
		$key     = $args['key'];
	$options = get_option( 'bb_engagifii' );
	if($key=='engagifii_readonly_fields'){
		$fields = get_all_buddyboss_profile_fields_simple();
		$readonly_fields = isset( $options['misc']['readonly_fields'] ) ? $options['misc']['readonly_fields'] : array();
		echo '<p><strong>Select the Member profile fields that members should not be able to edit. It can be changed only at Engagifii</strong></p>';
		foreach ( $fields as $field_id => $field_label ) {
			$is_checked = in_array( $field_id, $readonly_fields ) ? 'checked' : '';
			echo "<label><input type='checkbox' name='bb_engagifii[misc][readonly_fields][]' value='{$field_id}' {$is_checked}> {$field_label}</label><br>";
		}
		return;
	}
		$response = wp_remote_get( $options['api']['crmUrl'].'/GetPersonProfileFields/'.get_option('engagifii_sso_settings')['client_id'], [
        'headers' => [
            'tenant-code'   => get_option('engagifii_sso_settings')['client_id'],
            'Content-Type'  => 'application/json'
        ],
    ]);
		if ( is_wp_error( $response ) ) {
		  echo 'Error: ' . $response->get_error_message();
		} else{
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body );
			if ( ! empty( $data ) && is_array( $data ) ) {
			  echo '<div class="field-choose"><p>Drag and drop desired fields to be synced across member hub and workspace.</p><ul id="sortable1" class="connectedSortable">';
			$user_fields_raw = isset($options['user_fields']) ? $options['user_fields'] : '[]';
			$user_fields_json = stripslashes($user_fields_raw);
			$user_fields = json_decode($user_fields_json, true);
			$selected_ids = [];
			if (is_array($user_fields)) {
			  foreach ($user_fields as $field) {
				$selected_ids[] = $field['id'];  
			  }
			}
			foreach ( $data as $field ) {
				$field_id = esc_attr( $field->fieldId );
				$field_name = esc_html( $field->fieldName );
				if (in_array($field_id, $selected_ids)) continue;
				?>
                <li class="ui-state-default" data-id="<?php echo $field_id; ?>"><?php echo $field_name; ?></li>
				<?php
			}
			  echo '</ul><ul id="sortable2" class="connectedSortable">';
				  if (is_array($user_fields)) {
					  foreach ($user_fields as $field) {
						  if (!isset($field['id'], $field['label'])) continue;
						  ?>
						  <li class="ui-state-default editable" data-id="<?php echo esc_attr($field['id']); ?>" data-original-label="<?php echo esc_attr($field['label']); ?>">
							  <input type="text" class="field-label" value="<?php echo esc_attr($field['label']); ?>" />
							  <span class="remove-field" style="cursor:pointer; margin-left:10px;">✖</span>
						  </li>
						  <?php
					  }
				  } 
			  echo "</ul><input type='hidden' name='bb_engagifii[user_fields]' value='".stripslashes($options['user_fields'])."' id='user_fields' /></div>";
		  }
		} 
	}
}

if ( ! function_exists( 'engagifii_is_addon_field_enabled' ) ) {
	function engagifii_is_addon_field_enabled( $default = 1 ) {
		return (bool) apply_filters( 'engagifii_is_addon_field_enabled', (bool) get_option( 'engagifii_field', $default ) );
	}
}
function engagifii_cron_logs_callback( $args ) {
	$logs = get_option( 'bb_engagifii' )['cron']['cron_logs'];
	echo '<div class="cron-logs">'.$logs.'</div>';
}
function engagifii_misc_callback( $args ) {
	$bb_dash_menu = get_option( 'bb_engagifii' )['misc']['dash_menu'];
	echo '<input type="text" id="dash_menu" name="bb_engagifii[misc][dash_menu]" value="' . esc_attr( $bb_dash_menu ) . '" class="regular-text" /><p><i>Leave blank for no change.</i></p>';
}
/***************************** Add section in current settings ***************************************/

/**
 * Register fields for settings hooks
 * bp_admin_setting_general_register_fields
 * bp_admin_setting_xprofile_register_fields
 * bp_admin_setting_groups_register_fields
 * bp_admin_setting_forums_register_fields
 * bp_admin_setting_activity_register_fields
 * bp_admin_setting_media_register_fields
 * bp_admin_setting_friends_register_fields
 * bp_admin_setting_invites_register_fields
 * bp_admin_setting_search_register_fields
 */
if ( ! function_exists( 'engagifii_bp_admin_setting_general_register_fields' ) ) {
    function engagifii_bp_admin_setting_general_register_fields( $setting ) {
	    // Main General Settings Section
	    $setting->add_section( 'engagifii_addon', __( 'Add-on Settings', 'engagifii-addon' ) );

	    $args          = array();
	    $setting->add_field( 'bp-enable-my-addon', __( 'My Field', 'engagifii-addon' ), 'engagifii_admin_general_setting_callback_my_addon', 'intval', $args );
    }

	add_action( 'bp_admin_setting_general_register_fields', 'engagifii_bp_admin_setting_general_register_fields' );
}

if ( ! function_exists( 'engagifii_admin_general_setting_callback_my_addon' ) ) {
	function engagifii_admin_general_setting_callback_my_addon() {
		?>
        <input id="bp-enable-my-addon" name="bp-enable-my-addon" type="checkbox"
               value="1" <?php checked( engagifii_enable_my_addon() ); ?> />
        <label for="bp-enable-my-addon"><?php _e( 'Enable my option', 'engagifii-addon' ); ?></label>
		<?php
	}
}

if ( ! function_exists( 'engagifii_enable_my_addon' ) ) {
	function engagifii_enable_my_addon( $default = false ) {
		return (bool) apply_filters( 'engagifii_enable_my_addon', (bool) bp_get_option( 'bp-enable-my-addon', $default ) );
	}
}


/**************************************** MY PLUGIN INTEGRATION ************************************/

/**
 * Set up the my plugin integration.
 */
function engagifii_register_integration() {
	require_once dirname( __FILE__ ) . '/integration/buddyboss-integration.php';
	buddypress()->integrations['addon'] = new engagifii_BuddyBoss_Integration();
}
add_action( 'bp_setup_integrations', 'engagifii_register_integration' );
