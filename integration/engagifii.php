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

function jwt_token(){
  $url = home_url("/wp-json/jwt-auth/v1/token");
  $data = ["username" => "crescommunity", "password" => "uVi5 2ctv uxFp UcwB tan2 j0ky"];
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
function upload_avatar_from_remote_url( $user_id, $image_url ) {
	 $api_url = site_url() . "/wp-json/buddyboss/v1/members/{$user_id}/avatar";
    $image_data = file_get_contents($image_url );//https://engagifii.engagifii.com/assets/images/welcome-screen.png
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
//add_action('plugins_loaded', function() {
	add_action('engagifii_sso_authenticated', 'auto_upload_avatar_for_new_user', 10, 2);
//});
function auto_upload_avatar_for_new_user( $user_id, $token_data ) {
	$access_token = isset($_COOKIE['access_token']) ? $_COOKIE['access_token'] : null;
    if (empty($access_token)) {
        $access_token = $token_data;
    }
  if ($access_token) {
    $response = wp_remote_post('https://builtin-crm.azurewebsites.net/api/v1/People/GetLoggedInUserDetailWithFields', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'accept'        => 'application/json',
            'tenant-code'   => 'engagifii',
            'Content-Type'  => 'application/json-patch+json'
        ],
        'body'    => json_encode([
            'id' => '',
            'fieldIds' => [] 
        ])
    ]);

    if (is_wp_error($response)) {
        echo 'Error: ' . $response->get_error_message();
    } else {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
		$dp = $data['people']['imageThumbUrl'];
		$user_id = isset($user_id) ? $user_id : get_current_user_id();
		/* wp_update_user( [
			'ID'         => $user_id,
			'first_name' => $dp 
		] );*/
		//echo $user_id;
    }
} else {
   // echo 'No access token found.';
}

    upload_avatar_from_remote_url( $user_id, $dp );
} 
?>
<?php /*?><script>
		fetch('<?php echo home_url("wp-json/buddyboss/v1/signup"); ?>', {
    method: 'POST',
    headers: {
		 'Content-Type': 'application/json',
        'Authorization': 'Bearer <?php echo $data['token'];?>'
    },
	 body: JSON.stringify({
    signup_email: "prakash_@Outlook.in",
    signup_password: "techadmin",
	field_3:'thakurx',
field_1:"prakash",
field_2:"thakur"
  })
})
.then(response => {
    console.log('Response Status:', response.status); // Log the status code
    return response.json();
})
.then(data => console.log(data))
.catch(error => console.error('Error:', error));
		</script><?php */?>

