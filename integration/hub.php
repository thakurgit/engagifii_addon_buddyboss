<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

//add hub id input on hub detail page
add_action( 'bp_groups_admin_meta_boxes', 'bb_engagifii_hubId_metabox' );
function bb_engagifii_hubId_metabox() {	
	add_meta_box( 
		'bb_engagifii_hub_id',
		'Hub Settings', 
		'hubID_render_admin_metabox', 
		get_current_screen()->id, 
		'normal', 
		'core'
	);
}  
function hubID_render_admin_metabox() {
	if ( ! isset( $_GET['gid'] ) ) {
		echo 'Group ID not found.';
		return;
	}
	$group_id = intval( $_GET['gid'] );
	$hub_id = groups_get_groupmeta( $group_id, 'hub_id', true );
	/*if ( empty( $hub_id ) ) {
		$hub_id = 'HUB-' . strtoupper( wp_generate_password( 8, false, false ) );
		groups_update_groupmeta( $group_id, 'hub_id', $hub_id );
	}*/
	?> 
	<div class="bp-groups-settings-section" id="bp-groups-settings-section-hub-id">
		<fieldset>
			<legend>Hub ID</legend>
			<input type="text" readonly value="<?php echo esc_attr( $hub_id ); ?>" style="width:300px" />
			<p class="description">This Hub ID is auto-generated and cannot be changed.</p>
		</fieldset>
        <fieldset>
			<legend>Get Hub Details</legend>
			<button id="fetch-hub-details" class="button button-primary">Get Hub Details</button>
                <b class="hub-fetch-status"></b>
			<p class="description">Get Hub details from Engagifii manually</p>
		</fieldset>
    <script type="text/javascript">
jQuery(document).ready(function($) {
	var group_id='<?php echo $group_id; ?>';
	var hub_id='<?php echo $hub_id; ?>';
    $('#fetch-hub-details').on('click', function(e) {
        e.preventDefault();
		var $button = $(this);
        var $status = $('.hub-fetch-status');
        $button.prop('disabled', true).text('Processing...');
        $status.text('');
		    $.post(ajaxurl, {
            action: 'fetch_engagifii_hub_details',
			group_id: group_id,
			hub_id: hub_id
        }).done(function(response) {
            if (response.success) {
                $status.text('Hub details fetched successfully ✅');
            } else {
                alert(response.data || 'Error occurred');
            }
        }).fail(function() {
            alert('AJAX request failed');
        }).always(function() {
            $button.prop('disabled', false).text('Get Hub Details');
        });
    });
});
</script>
	</div>
	<?php
}

//fetch hub button details 
add_action('wp_ajax_fetch_engagifii_hub_details', 'handle_fetch_hub_details');
function handle_fetch_hub_details() {
  $group_id = $_POST['group_id'];
  $hub_id = $_POST['hub_id'];
  $access_token = $_COOKIE['access_token'] ?? null;
  if (
	  !current_user_can('edit_users') ||
	  !$access_token ||
	  empty($hub_id)
  ) {
	  $error_message = !current_user_can('edit_users') ? 'Permission denied' :
					   !$access_token ? 'Access token missing' :
					   'Engagifii Hub ID not found';
	  wp_send_json_error($error_message);
  }
    $options = get_option('bb_engagifii');
   $response = wp_remote_get("{$options['api']['crmUrl']}/groups/get/{$hub_id}", [
        'headers' => [
          'Authorization' => 'Bearer ' . $access_token,
          'accept'        => 'application/json',
          'tenant-code'   => get_option('engagifii_sso_settings')['client_id'],
        ]
      ]);
   if (is_wp_error($response)) {
    wp_send_json_error(['error' => $response->get_error_message()]);
  }
  $body = stripslashes(wp_remote_retrieve_body($response));
  $data  = json_decode($body, true) ?? [];
  if (!empty($data)) {
	  //$groupTitle = groups_get_group( $group_id )->name;
		$response = wp_remote_request(site_url().'/wp-json/buddyboss/v1/groups/'.$group_id, [
			'method'    => 'PATCH',
			'headers'   => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . jwt_token(),
			],
			'body'      => json_encode([
				'name' => sanitize_text_field($data['name']),
				'description' => sanitize_text_field($data['description'])
			]),
		]);
	  if (filter_var($data['imageThumbUrl'], FILTER_VALIDATE_URL)) {
		  upload_avatar_from_remote_url($group_id, $data['imageThumbUrl'], "group");
	} else {
			$response = wp_remote_request(site_url().'/wp-json/buddyboss/v1/groups/'.$group_id.'/avatar', [
			'method'    => 'DELETE',
			'headers'   => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . jwt_token(),
			]
		  ]);
	}
  } 
	fetch_group_owners($options, $access_token, $hub_id, $group_id);
   fetch_group_members($options, $access_token,  $statuses, $hub_id, $group_id);
    wp_send_json_success('Hub Details Fetched');
}

//Fetch Group owners
function fetch_group_owners($options, $access_token, $hub_id, $group_id){
      $owners_response = wp_remote_get("{$options['api']['crmUrl']}/groups/GetGroupOwner/list/{$hub_id}", [
        'headers' => [
          'Authorization' => 'Bearer ' . $access_token,
          'accept'        => 'application/json',
          'tenant-code'   => get_option('engagifii_sso_settings')['client_id'],
        ]
      ]);

      if (is_wp_error($owners_response)) {
        return;
      } 
        $owners = json_decode(wp_remote_retrieve_body($owners_response), true)['people'];
        if (is_array($owners)) {
		  $valid_emails = [];
          foreach ($owners as $person) {
		//wp_send_json_success($entry['fullName']); 
            //$person = $entry['people'] ?? null;
            if (empty($person['primaryEmail']['value'])) continue;

            $owner_email = $person['primaryEmail']['value'];
		    $valid_emails[] = sanitize_email($owner_email);
			$user = get_user_by('email', $owner_email);
            if (!$user) {
              $username = generate_unique_username($person['firstName'] = '', $person['lastName'] = '', $owner_email);
              $user_id = wp_insert_user([
                'user_login' => $username,
                'user_email' => sanitize_email($owner_email),
                'user_pass'  => wp_generate_password(),
              ]);

              if (is_wp_error($user_id)) {
                continue;
              }

              if (!empty($person['peopleId'])) {
                update_user_meta($user_id, 'person_id', sanitize_text_field($person['peopleId']));
              }
			  // show member in members page
			  if (function_exists('bp_update_user_last_activity')) {
				  bp_update_user_last_activity($user_id, current_time('mysql'));
			  }

              do_action('engagifii_sso_authenticated', $user_id, $access_token );
              $user = get_user_by('ID', $user_id);
            }

           if ($user) {
			  if (!groups_is_user_member($user->ID, $group_id)) {
				  groups_join_group($group_id, $user->ID);
			  }
			  // Promote to admin if not already
			  $member_data = new BP_Groups_Member($user->ID, $group_id);
			  $role = $member_data->is_admin ? 'admin' : ($member_data->is_mod ? 'mod' : 'member');
			  if ($role !== 'admin') {
				  groups_promote_member($user->ID, $group_id, 'admin');
			  }
		  }
		  }
		  // ?? Now remove owners who are not in the API response
		  $group_admins = BP_Groups_Member::get_group_administrator_ids($group_id);
			  //wp_send_json_success($group_admins);
		  foreach ($group_admins as $admin) {
			  $user = get_userdata($admin->user_id);
				  if (!in_array($user->user_email, $valid_emails)) {
					  groups_demote_member($user->ID, $group_id);
				  }
			  }
        }  
}

// Fetch group members
function fetch_group_members($options, $access_token, &$statuses, $hub_id, $group_id){
      $members_response = wp_remote_get("{$options['api']['crmUrl']}/groups/get/peoples/lite/{$hub_id}", [
        'headers' => [
          'Authorization' => 'Bearer ' . $access_token,
          'accept'        => 'application/json',
          'tenant-code'   => get_option('engagifii_sso_settings')['client_id'],
        ]
      ]);

      if (is_wp_error($members_response)) {
        $statuses[] = "⚠️ Failed to fetch members for group {$group_name}: " . $members_response->get_error_message();
      } else {
        $members = json_decode(wp_remote_retrieve_body($members_response), true);
        if (is_array($members)) {
		  $valid_emails = [];
          foreach ($members as $entry) {
            $person = $entry['people'] ?? null;
            if (empty($person['email'])) continue;

            $valid_emails[] = sanitize_email($person['email']);
			$user = get_user_by('email', $person['email']);
            if (!$user) {
              $username = generate_unique_username($person['firstName'] = '', $person['lastName'] = '', $person['email']);
              $user_id = wp_insert_user([
                'user_login' => $username,
                'user_email' => sanitize_email($person['email']),
                'user_pass'  => wp_generate_password(),
              ]);

              if (is_wp_error($user_id)) {
                $statuses[] = '❌ Failed to create member user: ' . esc_html($person['email']) . ' — ' . $user_id->get_error_message();
                continue;
              }

              if (!empty($person['id'])) {
                update_user_meta($user_id, 'person_id', sanitize_text_field($person['id']));
              }
			  // show member in members page
			  if (function_exists('bp_update_user_last_activity')) {
				  bp_update_user_last_activity($user_id, current_time('mysql'));
			  }

              do_action('engagifii_sso_authenticated', $user_id, $access_token );
              $user = get_user_by('ID', $user_id);
              $statuses[] = '✅ Created new member user: ' . esc_html($person['email']);
            }

            if ($user) {
              if (!groups_is_user_member($user->ID, $group_id)) {
                groups_join_group($group_id, $user->ID);
                $statuses[] = '👤 Added member to group: ' . esc_html($user->user_email);
              }
            }
          }
		      // ?? Now remove members who are not in the API response
			  $current_members = groups_get_group_members([
				  'group_id' => $group_id,
			  ]);
			  foreach ($current_members['members'] as $member) {
				  $user = get_userdata($member->ID);
				  if ($user && !in_array($user->user_email, $valid_emails)) {
					  groups_remove_member($user->ID, $group_id);
					  $statuses[] = '??? Removed member from group: ' . esc_html($user->user_email);
				  }
			  }

        } else {
          $statuses[] = "⚠️ Invalid members response for group {$group_name}";
        }
      }	
}

// Hubs import callback
add_action('wp_ajax_engagifii_hubs_fetch', 'handle_engagifii_hubs_fetch');
function handle_engagifii_hubs_fetch() {
  check_ajax_referer('fetch_hubs_nonce', 'security');

  $statuses = ['Fetching groups from API...'];
  $options = get_option('bb_engagifii');
  $access_token = $_COOKIE['access_token'] ?? null;

  if (!$access_token) {
    wp_send_json_error(['error' => 'Access token missing.']);
  }

  $response = wp_remote_post($options['api']['crmUrl'] . '/groups/list', [
    'headers' => [
      'Authorization' => 'Bearer ' . $access_token,
      'accept'        => 'application/json',
      'tenant-code'   => get_option('engagifii_sso_settings')['client_id'],
      'Content-Type'  => 'application/json',
    ],
    'body' => json_encode([
      "itemCount"     => $_POST['groupCount'],
      "sortBy"        => "",
      "sortDirection" => "",
      "pageNumber"    => $_POST['groupPage'],
      "filterBody"    => [
        "searchText"    => "",
        "selectedDate"  => "",
        "pageNumber"    => "",
        "pageSize"      => ""
      ]
    ])
  ]);

  if (is_wp_error($response)) {
    wp_send_json_error(['error' => $response->get_error_message()]);
  }

  $statuses[] = 'Groups fetched. Creating hubs in WordPress...';

  $inserted_groups = [];
  $body = stripslashes(wp_remote_retrieve_body($response));
  $items = json_decode($body, true)['result'] ?? [];

  foreach ($items as $item) {
    $group = $item['groupView'] ?? [];
    if (empty($group['title']) || empty($group['id'])) continue;

    $group_name = sanitize_text_field($group['title']);
    $group_desc = sanitize_text_field($group['description'] ?? 'Hub Description goes here');
    $hub_id = sanitize_text_field($group['id']);
    $hub_thumbnail = sanitize_text_field($group['imageThumbUrl']);

    $existing = groups_get_groups([
      'meta_query' => [[
        'key' => 'hub_id',
        'value' => $hub_id,
        'compare' => '='
      ]]
    ]);
    if (!empty($existing['groups'])) continue;

    $statuses[] = 'Creating group: ' . esc_html($group_name);

    $group_owner_ids = [];
    $creator_id = null;

    if (!empty($group['groupOwners']) && is_array($group['groupOwners'])) {
      foreach ($group['groupOwners'] as $owner) {
        if (empty($owner['email'])) continue;

        $user = get_user_by('email', $owner['email']);

        if (!$user) {
          $username = generate_unique_username($owner['firstName'] = '', $owner['lastName'] = '', $owner['email']);
          $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => sanitize_email($owner['email']),
            'user_pass'  => wp_generate_password(),
          ]);

          if (is_wp_error($user_id)) {
            $statuses[] = '❌ Failed to create user for email ' . esc_html($owner['email']) . ': ' . $user_id->get_error_message();
            continue;
          }

          $user = get_user_by('ID', $user_id);
          if (!empty($owner['id'])) {
            update_user_meta($user_id, 'person_id', sanitize_text_field($owner['id']));
          }
		  // show member in members page
		  if (function_exists('bp_update_user_last_activity')) {
			  bp_update_user_last_activity($user_id, current_time('mysql'));
		  }

          do_action('engagifii_sso_authenticated', $user_id, $access_token );
          $statuses[] = '✅ Created new user for owner: ' . esc_html($owner['email']);
        }

        if ($user) {
          $group_owner_ids[] = $user->ID;
          if (!$creator_id) $creator_id = $user->ID;
        }
      }
    }

    if (!$creator_id) {
      $admin_user = get_user_by('email', 'admin@crescerance.com');
      $creator_id = $admin_user ? $admin_user->ID : 1;
    }

    $payload = [
      'name'        => $group_name,
      'description' => $group_desc,
      'status'      => 'public',
      'creator_id'  => $creator_id,
    ];

    $bb_response = wp_remote_post(site_url('/wp-json/buddyboss/v1/groups'), [
      'headers' => [
        'Authorization' => 'Bearer ' . jwt_token(),
        'Content-Type' => 'application/json',
      ],
      'body' => json_encode($payload),
    ]);

    if (is_wp_error($bb_response)) {
      $statuses[] = '❌ Failed to create group: ' . esc_html($group_name) . ' — ' . $bb_response->get_error_message();
      continue;
    }

    $group_result = json_decode(wp_remote_retrieve_body($bb_response), true);
    if (!empty($group_result['id'])) {
      $group_id = $group_result['id'];
      $inserted_groups[] = $group_id;
      groups_update_groupmeta($group_id, 'hub_id', $hub_id);

      foreach ($group_owner_ids as $owner_id) {
        if (!groups_is_user_member($owner_id, $group_id)) {
          groups_join_group($group_id, $owner_id);
        }
        groups_promote_member($owner_id, $group_id, 'admin');
      }

      $statuses[] = '✅ Group created and owners added: ' . esc_html($group_name);
	  
	  // 🖼️ Set group thumbnail if valid URL
		if (filter_var($hub_thumbnail, FILTER_VALIDATE_URL)) {
		  upload_avatar_from_remote_url($group_id, $hub_thumbnail, "group");
		  $statuses[] = '🖼️ Thumbnail set for hub: ' . esc_html($group_name);
		}

      // Fetch group members
	  fetch_group_members($options, $access_token,  $statuses, $hub_id, $group_id);

    } else {
      $statuses[] = '❌ Group response invalid for: ' . esc_html($group_name);
    }
  }

  $options['misc']['hubs_last_fetched'] = time();
  update_option('bb_engagifii', $options);

   wp_send_json_success([
	  'statuses' => $statuses,
	  'last_fetched' => time(),
  ]);
}

