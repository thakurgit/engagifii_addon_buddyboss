<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

//Add user meta Person ID in dashboard and manually fetcg user details
function show_person_id_field($user) {
    $person_id = esc_attr(get_the_author_meta('person_id', $user->ID));
	$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : get_current_user_id();
    ?>
    <h3>Engagifii Member Settings</h3>
    <table class="form-table">
        <tr>
            <th><label for="person_id">Person ID</label></th>
            <td>
                <input type="text" name="person_id" id="person_id" value="<?php echo $person_id; ?>" class="regular-text" readonly />
                <p class="description">This ID is auto-assigned and cannot be edited.</p>
            </td>
        </tr>
        <tr>
        	<th><label for="">Get Member Details</label></th>
            <td>
            	<button id="fetch-member-details" class="button button-primary">Get Member Details</button>
                <b class="member-fetch-status"></b>
                <p class="description">Get Member details from Engagifii manually</p>
            </td>
        </tr>
    </table>
    <script type="text/javascript">
jQuery(document).ready(function($) {
	var user_id='<?php echo $user_id; ?>';
    $('#fetch-member-details').on('click', function(e) {
        e.preventDefault();
		var $button = $(this);
        var $status = $('.member-fetch-status');
        $button.prop('disabled', true).text('Processing...');
        $status.text('');
		    $.post(ajaxurl, {
            action: 'fetch_engagifii_member_details',
			user_id: user_id
        }).done(function(response) {
            if (response.success) {
                $status.text('Member details fetched successfully ✅');
            } else {
                alert(response.data || 'Error occurred');
            }
        }).fail(function() {
            alert('AJAX request failed');
        }).always(function() {
            $button.prop('disabled', false).text('Get Member Details');
        });
    });
});
</script>
    <?php
}
add_action('show_user_profile', 'show_person_id_field');
add_action('edit_user_profile', 'show_person_id_field'); 
function prevent_person_id_update($user_id) {
    unset($_POST['person_id']);
}
add_action('personal_options_update', 'prevent_person_id_update');
add_action('edit_user_profile_update', 'prevent_person_id_update');
add_action('wp_ajax_fetch_engagifii_member_details', 'handle_fetch_member_details');

function handle_fetch_member_details() {
  $user_id = $_POST['user_id'];
  $access_token = $_COOKIE['access_token'] ?? null;
  $person_id = esc_attr(get_the_author_meta('person_id', $user_id));
  if (
	  !current_user_can('edit_users') ||
	  !$access_token ||
	  empty($person_id)
  ) {
	  $error_message = !current_user_can('edit_users') ? 'Permission denied' :
					   !$access_token ? 'Access token missing' :
					   'Engagifii Person ID not found';
	  wp_send_json_error($error_message);
  }
	do_action('engagifii_sso_authenticated', $user_id, $access_token); 
    wp_send_json_success('Member details fetched');
}

//update profile on workspace
add_action('xprofile_updated_profile', 'profile_update_api_execute', 10, 5);
function profile_update_api_execute($user_id, $posted_field_ids, $errors, $old_values, $new_values) {
	$bb_engagifii= get_option('bb_engagifii');
	$user_fields_metadata=$bb_engagifii['user_fields_metadata'];
	foreach ($user_fields_metadata as $field) {
    switch ($field['label']) {
        case 'Phone':
            $phoneTabId = $field['tabId'];
            $phoneTabGroupId = $field['tabGroupId'];
            $phoneTabGroupFieldId = $field['tabGroupFieldId'];
            break;

        case 'Birth Date':
            $dobTabId = $field['tabId'];
            $dobTabGroupId = $field['tabGroupId'];
            $dobTabGroupFieldId = $field['tabGroupFieldId'];
            break;

        case 'Gender':
            $genderTabId = $field['tabId'];
            $genderTabGroupId = $field['tabGroupId'];
            $genderTabGroupFieldId = $field['tabGroupFieldId'];
            break;

        case 'Organization':
            $orgTabId = $field['tabId'];
            $orgTabGroupId = $field['tabGroupId'];
            $orgTabGroupFieldId = $field['tabGroupFieldId'];
            break;
    }
}
	$access_token = isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null;
 if ($access_token) {
	 $user_id = get_current_user_id();
	 $user = get_userdata($user_id);
	 if(empty($user->person_id)) {
		return ;
	 }
	  $firstName = 'field_'.xprofile_get_field_id_from_name('First Name');
	  $lastName = 'field_'.xprofile_get_field_id_from_name('Last Name');
	  $phone = 'field_'.xprofile_get_field_id_from_name('Phone');
	  $dob = 'field_'.xprofile_get_field_id_from_name('Birth Date');
	 $fields_to_update = [
    [
        'headerFieldName' => 'firstName',
        'oldValue' => $user->first_name,
        'newValue' => $_POST[$firstName],
		'isHeader'  => true
    ],
    [
        'headerFieldName' => 'lastName',
        'oldValue' => $user->last_name,
        'newValue' => $_POST[$lastName],
		'isHeader'  => true
    ],
	[
        'oldValue' => xprofile_get_field_data(xprofile_get_field_id_from_name('Phone'), $user_id),
        'newValue' => $_POST[$phone],
		'tabId'    => $phoneTabId,
		'tabGroupId'    => $phoneTabGroupId,
		'tabGroupFieldId'    => $phoneTabGroupFieldId,
		'primary'  => true
    ],
	[
        'oldValue' => "",
        'newValue' => date('m/d/Y', strtotime($_POST[$dob.'_month'].' '.$_POST[$dob.'_day'].' '.$_POST[$dob.'_year'])),
		'tabId'    => $dobTabId,
		'tabGroupId'    => $dobTabGroupId,
		'tabGroupFieldId'    => $dobTabGroupFieldId,
    ],
];
$body = [];
foreach ($fields_to_update as $field) {
    if ($field['oldValue'] !== $field['newValue']) {
        $body[] = [
            'tabId'               => isset($field['tabId']) ? $field['tabId'] : null,
            'tabGroupId'          => isset($field['tabGroupId']) ? $field['tabGroupId'] : null,
            'tabGroupFieldId'     => isset($field['tabGroupFieldId']) ? $field['tabGroupFieldId'] : null,
            'loggedInUserId'      => $user->person_id,
            'profileUserId'       => $user->person_id,
            'isHeader'            => isset($field['isHeader']) ? $field['isHeader'] : false,
            'headerFieldName'     => isset($field['headerFieldName']) ? $field['headerFieldName'] : '',
            'smartDropDownRequest'=> '',
            'fieldChangeValues'   => [
                [
                    'oldValue' => $field['oldValue'],
                    'newValue' => $field['newValue'],
                    'primary'  => isset($field['primary']) ? $field['primary'] : false,
                ]
            ],
            'isValueChanged'      => false
        ];
    }
}
    $response = wp_remote_post($bb_engagifii['api']['doUrl'].'PeopleApproval/CreateRequest', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'accept'        => 'application/json',
            'tenant-code'   => get_option('engagifii_sso_settings')['client_id'],
            'Content-Type'  => 'application/json'
        ],
        'body' => json_encode($body),
    ]);
    if (is_wp_error($response)) {
        error_log('API request failed: ' . $response->get_error_message());
    } else {
		
	}
 }
}

//member avatar update
add_action( 'xprofile_avatar_uploaded', 'avatar_update_api_execute' );
function avatar_update_api_execute( $user_id ) {
	$bb_engagifii= get_option('bb_engagifii');
	$access_token = isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null;
	if (!$access_token){
		return;
	}
	 $user_id = get_current_user_id();
	 $user = get_userdata($user_id);
	 if(empty($user->person_id)) {
		return ;
	 }
	 $avatar = wp_remote_get(site_url().'/wp-json/buddyboss/v1/members/'.$user_id.'/avatar'.'?nocache=' . time()); //add nocache to bypass image cache
	 $avatar = wp_remote_retrieve_body($avatar);
	 $avatar_src = json_decode($avatar, true)['thumb'];
	 $imageData = file_get_contents($avatar_src);
	 if ($imageData === false) {
		  die("Failed to fetch image data.");
	  }
	  $base64Avatar = base64_encode($imageData);
    $response = wp_remote_post($bb_engagifii['api']['resourceUrl'], [
       'headers' => [
            'Content-Type'  => 'application/json'
        ],
        'body'    => json_encode([
			'ImageString' => $base64Avatar,
			'Module'      => 'crm',
        ])
    ]);
	  $avatarRemoteUrl = wp_remote_retrieve_body($response);
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Avatar API failed: ' . $response->get_error_message() );
    } else {
        $response = wp_remote_request($bb_engagifii['api']['crmUrl'].'/People/UpdatePersonHeader/'. $user->person_id, [
		  'method'    => 'PUT',
		  'headers'   => [
			  'Content-Type'  => 'application/json',
			  'Authorization' => 'Bearer ' . $access_token,
			  'tenant-code'   => get_option('engagifii_sso_settings')['client_id'],
		  ],
		  'body'      => json_encode([
			  'imageThumbUrl' => trim( stripslashes( $avatarRemoteUrl ), '"' )
		  ]),
	  ]);
    }
}

//member avatar delete
add_action( 'bp_core_delete_existing_avatar', 'avatar_remove_api_execute');
function avatar_remove_api_execute( $args) {
	// Avoid running when avatar is not for user
    if (!isset($args['object']) || $args['object'] !== 'user') {
        return;
    }
    // Avoid running if no user ID
    $user_id = isset($args['item_id']) ? intval($args['item_id']) : 0;
    if (!$user_id) {
        return;
    }
    // Check if avatar is being deleted as part of user deletion
    if (defined('WP_UNINSTALL_PLUGIN') || did_action('delete_user') || did_action('wpmu_delete_user')) {
        return;
    }
    // You can also check for admin pages like `users.php` + user deletion action
    if (is_admin() && isset($_GET['action']) && $_GET['action'] === 'delete') {
        return;
    }
	
	$access_token = isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null;
	if (!$access_token){
		return;
	}
	$bb_engagifii= get_option('bb_engagifii');
    $user_id = isset( $args['item_id'] ) ? intval( $args['item_id'] ) : 0;
	$user = get_userdata($user_id);
	 if(empty($user->person_id)) {
		return ;
	 }
    // Proceed only if it's a user avatar (not group or blog)
    if ( isset( $args['object'] ) && $args['object'] === 'user' && $user_id ) {
        $response = wp_remote_request($bb_engagifii['api']['crmUrl'].'/People/UpdatePersonHeader/'. $user->person_id, [
		  'method'    => 'PUT',
		  'headers'   => [
			  'Content-Type'  => 'application/json',
			  'Authorization' => 'Bearer ' . $access_token,
			  'tenant-code'   => get_option('engagifii_sso_settings')['client_id'],
		  ],
		  'body'      => json_encode([
			  'imageThumbUrl' => ''
		  ]),
	  ]);
    }
}

//restrict member fields from editing
function prevent_edit_of_protected_profile_fields( $field ) {
	$bb_engagifii= get_option('bb_engagifii');
	$protected_fields = isset( $bb_engagifii['misc']['readonly_fields'] ) ? (array) $bb_engagifii['misc']['readonly_fields'] : array();

    if ( empty( $protected_fields ) || ! is_array( $protected_fields ) ) {
        return;
    }

    if ( in_array( $field->field_id, $protected_fields ) ) {
        $original_value = xprofile_get_field_data( $field->field_id, $field->user_id );
        $field->value = $original_value; // Prevent saving new value
    }
}
add_action( 'xprofile_data_before_save', 'prevent_edit_of_protected_profile_fields' );
function inject_readonly_profile_field_css() {
    if ( ! bp_is_user_profile_edit() ) {
        return;
    }

	$bb_engagifii= get_option('bb_engagifii');
	$protected_fields = isset( $bb_engagifii['misc']['readonly_fields'] ) ? (array) $bb_engagifii['misc']['readonly_fields'] : array();

    if ( empty( $protected_fields ) || ! is_array( $protected_fields ) ) {
        return;
    }

    // Build a list of field IDs
    $selectors = array_map( function( $id ) {
        return "#field_{$id}";
    }, $protected_fields );

    // Combine all into one CSS rule
    $combined = implode( ', ', $selectors );

    echo '<style>';
    echo $combined . " {\n";
    echo "    background-color:  #f4f4f4 !important;\n";
    echo "    pointer-events: none;\n";
    echo "}";
    echo '</style>';
}
add_action( 'wp_head', 'inject_readonly_profile_field_css' );

//rest api initialize foe member update
 add_action('rest_api_init', function () {
    register_rest_route('engagifii/v1', '/sync_member/', array(
    array(
        'methods' => 'POST',
        'callback' => 'handle_sync_member',
        'permission_callback' => '__return_true',
    ),
    array(
        'methods' => 'GET',
        'callback' => function() {
            return new WP_REST_Response(['message' => 'Sync Member Endpoint is active. Use POST to send data.'], 200);
        },
        'permission_callback' => '__return_true',
    ),
));
});

function handle_sync_member($request) { 

    $params = $request->get_json_params();

    // Example data (customize based on what the API sends)
    $email = sanitize_email($params['email']);
    $new_name = sanitize_text_field($params['name']);
   // $phone = sanitize_text_field($params['phone']);

    $user = get_user_by('email', $email);

    if ($user) {
        // Update user core fields
        wp_update_user(array(
            'ID' => $user->ID,
            'first_name' => $new_name,
        ));

        // Update user meta fields
       // update_user_meta($user->ID, 'phone', $phone);

        return new WP_REST_Response(['status' => 'updated'], 200);
    }

    return new WP_REST_Response(['status' => 'user not found'], 404);
}

