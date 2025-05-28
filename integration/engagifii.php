<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
function bb_engagifii_form_submission() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bb_engagifii'])) {
		if (!current_user_can('manage_options')) {
            die('Insufficient permissions.');
        }
        $value = sanitize_text_or_array_field($_POST['bb_engagifii']);
        update_option('bb_engagifii', $value);
    }
}
add_action('init', 'bb_engagifii_form_submission');

function sanitize_text_or_array_field( $input ) {
    if ( is_string( $input ) ) {
        return sanitize_text_field( $input );
    } elseif ( is_array( $input ) ) {
        foreach ( $input as $key => &$value ) {
            $value = sanitize_text_or_array_field( $value );
        }
        return $input;
    }
    return '';
}

//custom cron time
function my_custom_cron_schedules($schedules) {
    // Add a new interval of 15 minutes
    $schedules['1_minutes'] = array(
        'interval' => 60, // 1 minutes
        'display'  => __('1 Minute')
    );

    return $schedules;
}
add_filter('cron_schedules', 'my_custom_cron_schedules');

//custom cron schedule
function my_custom_cron_schedule() {
    if (!wp_next_scheduled('my_custom_cron_hook')) {
        wp_schedule_event(time(), 'hourly', 'my_custom_cron_hook'); 
    }
}
add_action('init', 'my_custom_cron_schedule');

//custom cron function
function my_custom_cron_function() {
    //error_log("My custom cron job ran at: " . current_time('mysql'));
	update_option('engagifii_cron_last_execution', current_time('mysql'));
	$option = get_option('bb_engagifii', []);
	  if (!isset($option['api'])) {
		  $option['api'] = [];
	  }
	  if (!isset($option['api']['tenant'])) {
		  $option['api']['tenant'] = 0;
	  }
	  $option['api']['tenant']++;
	  $option['cron']['cron_logs'] = '<p>Tenant cron executed at <b>' . current_time('mysql') . '</b></p>' . $option['cron']['cron_logs'];
	  update_option('bb_engagifii', $option);
}
add_action('my_custom_cron_hook', 'my_custom_cron_function');

//custom cron manually run
 function run_custom_cron_event() {  
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized access' );
    }
    do_action( 'my_custom_cron_hook' );
    wp_send_json_success( 'Cron event executed successfully!' );
}
add_action( 'wp_ajax_run_custom_cron_event', 'run_custom_cron_event' );

/*function delete_my_cron_event() {
    $timestamp = wp_next_scheduled('my_custom_cron_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'my_custom_cron_hook');
    }
}
add_action('init', 'delete_my_cron_event');
do_action('my_custom_cron_hook'); */

/* if ( ! defined('BP_AVATAR_THUMB_WIDTH') ) {
        define('BP_AVATAR_THUMB_WIDTH', 125);
    }

    if ( ! defined('BP_AVATAR_THUMB_HEIGHT') ) {
        define('BP_AVATAR_THUMB_HEIGHT', 50);
    }*/

    if ( ! defined('BP_AVATAR_FULL_WIDTH') ) {
        define('BP_AVATAR_FULL_WIDTH', 125);
    }

    if ( ! defined('BP_AVATAR_FULL_HEIGHT') ) {
        define('BP_AVATAR_FULL_HEIGHT', 125);
    }

//create JWT Token
function jwt_token(){
$bb_engagifii= get_option('bb_engagifii');
  $url = home_url("/wp-json/jwt-auth/v1/token");
  $data = ["username" => $bb_engagifii['jwt']['username'], "password" => $bb_engagifii['jwt']['app_password']];
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  $response = curl_exec($ch);
  if (curl_errno($ch)) echo "cURL Error: " . curl_error($ch);
  curl_close($ch);
  $data = json_decode($response, true);
  return $data['token'];	
}
function upload_avatar_from_remote_url( $user_id, $image_url, $type = 'profile' ) {
	if ( $type === 'group' ) {
        $api_url = site_url() . "/wp-json/buddyboss/v1/groups/{$user_id}/avatar";
    } else {
        $api_url = site_url() . "/wp-json/buddyboss/v1/members/{$user_id}/avatar";
    }
    $image_data = file_get_contents($image_url );
    if ( ! $image_data ) {
        error_log( 'Could not fetch image.' );
        return false;
    }
    $tmp_file = tmpfile();
    $meta = stream_get_meta_data( $tmp_file );
    file_put_contents( $meta['uri'], $image_data );
    $cfile = new CURLFile( $meta['uri'], 'image/png', 'avatar.png' ); // or image/jpeg
    $post_fields = [
        'action' => 'bp_avatar_upload',
        'file'   => $cfile,
    ];
	$ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $api_url );
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer ".jwt_token(),
    ] );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_fields );

    $response = curl_exec( $ch );

    if ( curl_errno( $ch ) ) {
        error_log( 'Curl error: ' . curl_error( $ch ) );
        return false;
    }

    curl_close( $ch );
    fclose( $tmp_file );
    return $response;

}
add_action('engagifii_sso_authenticated', 'fetch_userInfo_newUser', 10, 2);
function fetch_userInfo_newUser( $user_id, $token_data ) {
$bb_engagifii= get_option('bb_engagifii');
	$access_token = isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null;
    if (empty($access_token)) {
        $access_token = $token_data;
    }
  if ($access_token) {
	  $engagifii_member_id = get_the_author_meta('person_id', $user_id);
	  $engagifii_member_id = (!empty(trim($engagifii_member_id))) ? esc_attr($engagifii_member_id) : '';
	  $user_fields_raw = isset($bb_engagifii['user_fields']) ? $bb_engagifii['user_fields'] : '[]';
	  $user_fields_json = stripslashes($user_fields_raw);
	  $user_fields_array = json_decode($user_fields_json, true);
	  $user_fields = [];
	  $labelIds = [];
	  if (is_array($user_fields_array)) {
		foreach ($user_fields_array as $field) {
		  $user_fields[] = $field['id']; 
		  $labelIds[$field['label']] = $field['id']; 
		}
	  }
    $response = wp_remote_post($bb_engagifii['api']['crmUrl'].'/People/GetLoggedInUserDetailWithFields', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'accept'        => 'application/json',
            'tenant-code'   => get_option('engagifii_sso_settings')['client_id'],
            'Content-Type'  => 'application/json-patch+json'
        ],
        'body'    => json_encode([
            'id' => $engagifii_member_id,
            'fieldIds' => $user_fields
        ])
    ]);
    if (is_wp_error($response)) {
        echo 'Error: ' . $response->get_error_message();
    } else {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
		$user_id = isset($user_id) ? $user_id : get_current_user_id();
		$dp = $data['people']['imageThumbUrl'];
		$firstName = $data['people']['firstName'];
		$lastName = $data['people']['lastName'];
		$display_name = $firstName.' '.$lastName;
		$nickname = $firstName.'_'.$lastName;
		$dp = $data['people']['imageThumbUrl'];
		$pid = $data['people']['id'];
		 wp_update_user( [
			'ID'         => $user_id,
			'first_name' => $firstName, 
			'last_name' => $lastName, 
			'display_name' => $display_name, 
			'nickname' => $nickname, 
		] );
		update_user_meta( $user_id, 'person_id', $pid );
		$fieldMeta = [];
		foreach ($data['peopleFields'] as $key => $value) {
			if($value['id']==$labelIds['Birth Date'] && $value['selectedValue'] ){ 
				$dob = $value['selectedValue'];
				$dob_id = xprofile_get_field_id_from_name('Birth Date');
				if($dob_id && $dob){
					$dob = date( 'Y-m-d', strtotime( $dob ) );
					xprofile_set_field_data($dob_id, $user_id, $dob.' 00:00:00');
					 $fieldMeta[] = [
						'tabGroupFieldId' => $value['id'],
						'label' => 'Birth Date',
						'tabGroupId' => $value['tabGroupId'],
						'tabId' => $value['tabId']
					];
				}
			} 
			if($value['id']==$labelIds['Phone'] && $value['selectedValue'] ){ 
				$phoneNo = $value['selectedValue'];
				$phone_id = xprofile_get_field_id_from_name('Phone');
				if($phone_id && $phoneNo){
					xprofile_set_field_data($phone_id, $user_id, $phoneNo);
					 $fieldMeta[] = [
						'tabGroupFieldId' => $value['id'],
						'label' => 'Phone',
						'tabGroupId' => $value['tabGroupId'],
						'tabId' => $value['tabId']
					];
				}
			} 
			if($value['id']==$labelIds['Organization'] && $value['organizationValue'] ){ 
				$org = json_decode(stripslashes($value['organizationValue']));
				$org_id = xprofile_get_field_id_from_name('Organization');
				$pos_id = xprofile_get_field_id_from_name('Position');
				$dep_id = xprofile_get_field_id_from_name('Department');
				if (is_array($org)) {
					foreach ($org as $orgItem) {
						if (isset($orgItem->isPrimary) && $orgItem->isPrimary == 1) {
							$organizationName = $orgItem->name;
							if (isset($orgItem->positionHistory) && is_array($orgItem->positionHistory)) {
								foreach ($orgItem->positionHistory as $position) {
									if (isset($position->isCurrent) && $position->isCurrent == 1) {
										$positionName = $position->positionName;
										$departmentName = $position->departmentName;
										break; 
									}
								}
							}
							break;
						}
					}
				}
				if($org_id && $organizationName){
					xprofile_set_field_data($org_id, $user_id, $organizationName);
				}
				if($pos_id && $positionName){
					xprofile_set_field_data($pos_id, $user_id, $positionName);
				}
				if($dep_id && $departmentName){
					xprofile_set_field_data($dep_id, $user_id, $departmentName);
				}
			} 
		}
		if (empty($bb_engagifii['user_fields_metadata'])) {
			$bb_engagifii['user_fields_metadata'] = $fieldMeta;
			update_option('bb_engagifii', $bb_engagifii);
		}
    }
} else {
   // echo 'No access token found.';
}
	if (filter_var($dp, FILTER_VALIDATE_URL)) {
    	upload_avatar_from_remote_url( $user_id, $dp );
	}
} 

//rename buddyboss menu
function rename_buddyboss_menu_item() {
    global $menu;
    $options = get_option('bb_engagifii');
    if (empty($options['misc']['dash_menu'])) { return; }
    $bb_dash_menu = $options['misc']['dash_menu'];
    foreach ($menu as $key => $value) {
        if ($value[0] == 'BuddyBoss') {
            $menu[$key][0] = $bb_dash_menu;
        }
    }
} 
add_action('admin_menu', 'rename_buddyboss_menu_item');

//get all member profile fields
function get_all_buddyboss_profile_fields_simple() {
    $fields = array();

    $groups = bp_xprofile_get_groups( array( 'fetch_fields' => true ) );

    foreach ( $groups as $group ) {
        foreach ( $group->fields as $field ) {
            $fields[ $field->id ] = $field->name;
        }
    }

    return $fields;
}

/* function update_all_user_display_names() {
    $users = get_users();

    foreach ( $users as $user ) {
        $first_name = get_user_meta( $user->ID, 'first_name', true );
        $last_name  = get_user_meta( $user->ID, 'last_name', true );

        if ( $first_name && $last_name ) {
            $display_name = $first_name . ' ' . $last_name;

            wp_update_user( [
                'ID'           => $user->ID,
                'display_name' => $display_name,
            ] );
        }
    }
}
add_action('init', 'update_all_user_display_names');*/
?>

        
        
        

