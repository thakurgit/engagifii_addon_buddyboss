<?php
// EARLY EXIT: Skip everything if activity logging is not enabled
$options = get_option( 'bb_engagifii' );
if ( empty( $options['misc']['activity_log'] ) ) {
    return;
}
function engagifii_init_activity_log() {
	add_action('admin_menu', function () {
	  add_menu_page(
		  'Members Activity Log',
		  'Members Activity Log',
		  'manage_options',
		  'engagifii_activity_log',
		  'engagifii_render_activity_log',
		  'dashicons-list-view',
		  8
	  );
	  });
    $upload_dir = wp_upload_dir(); // Gets wp-content/uploads path
    $log_dir    = trailingslashit($upload_dir['basedir']) . 'engagifii-activity/';
    $log_file   = $log_dir . 'activity.log';

    // Create directory if it doesn't exist
    if ( ! file_exists($log_dir) ) {
        wp_mkdir_p($log_dir);
    }

    // Create the log file if it doesn't exist
    if ( ! file_exists($log_file) ) {
        $init_entry = [
        'timestamp' => current_time('timestamp'),
        'activity_type'    => 'log_initialized',
        'user_id'   => get_current_user_id(),
        'log_meta'      => ['note' => 'Activity log initialized']
    ];
    file_put_contents($log_file, json_encode($init_entry) . "\n");
    }
	// Register logging actions conditionally
	add_action( 'groups_created_group', 'engagifii_log_group_created_once', 10, 2 );
	add_action( 'groups_join_group', 'engagifii_log_group_join', 10, 2 );
	add_action( 'user_register', 'engagifii_log_user_created', 10, 1 );
	add_action('wp_login', 'engagifii_log_member_login', 10, 2); 
	add_action('wp_logout', 'engagifii_log_member_logout',10);
	add_action('bp_activity_after_save', 'engagifii_log_status_post', 10, 1);
	add_action('bp_activity_comment_posted', 'engagifii_log_status_reply', 10, 2);
	add_action('bbp_new_topic', 'engagifii_log_new_discussion', 10, 4);
	add_action('bbp_new_reply', 'engagifii_log_new_reply', 10, 5);
	add_action( 'wp_ajax_activity_mark_fav', 'engagifii_log_reaction_ajax' );
	$GLOBALS['engagifii_logged_in_user_id'] = get_current_user_id();
}
add_action('init', 'engagifii_init_activity_log');
add_action('engagifii_sso_authenticated', 'engagifii_log_user_created_sso', 10, 1); 
add_action('engagifii_sso_loggedIn', 'engagifii_log_member_login_sso', 10, 1); 

//engagifii activity types
function engagifii_get_activity_types() {
    return [
		//'log_initialized'    => 'Activity Log Initialized',
		'member_registered'  => 'New member registered',
		'member_login'     	 => 'Member logged in',
		'member_logout'      => 'Member logged out',
		'posted_status'      => 'Posted a Status',
		'replied_status'      => 'Replied to a Status',
		'reacted_post'      => 'Reacted to a Post',
		'created_hub'         => 'Created a Hub',
		'joined_hub'         => 'Joined a Hub',
		'posted_in_group'     => 'Posted in Group',
		'posted_discussion'  => 'Started a Discussion',
		'replied_discussion'  => 'Replied to a Discussion',
	 ];
}
//parse logs entry
function engagifii_parse_activity_entry( $entry, $activity_types = [] ) {
	$user_id   = esc_html( $entry->user_id ) ?? '';
	$user_info = get_userdata( $user_id );
	$user_name = $user_info ? $user_info->display_name : 'User ID ' . $user_id;
	$user_email = $user_info ? $user_info->user_email : '';
	$user_link = esc_url( $user_info ? bp_core_get_user_domain( $user_id ) : '' );
	$activity_type  = esc_html( $entry->activity_type ) ?? 'Unknown';
	$activity_label = $activity_types[$activity_type] ?? ucfirst(str_replace('_', ' ', $activity_type));
	$log_meta = maybe_unserialize( $entry->log_meta );

	// Build human-readable activity
	if ( in_array( $activity_type, [ 'joined_hub', 'created_hub' ] ) ) {
		$hub_id = esc_html( $entry->hub_id ) ?? '';
		$group = groups_get_group( [ 'group_id' => $hub_id ] );
		$group_name = esc_html( $group->name ?? '' );
		$group_link = esc_url( bp_get_group_permalink( $group ) );
		$action_text = $activity_type === 'joined_hub' ? 'joined' : 'created';
		$group_activity = 'Member '.$action_text.' the hub - '.$group_name;
		if(!empty($group_link) && !empty($group_name)){
		  $note = 'Member '.$action_text.' the hub <a href=" '.$group_link.' " target="_blank">' .$group_name. '</a>';
		}else{
		  $note = 'Member '.$action_text.' the hub';
		}
	} else if ( $activity_type == 'posted_status') {
		$note = 'Posted a status update: <a href="'.esc_url(bp_activity_get_permalink(esc_html( $entry->post_id ))).'" target="_blank">View Post</a>';
	} else if ( $activity_type == 'posted_in_group') {
		$note = 'Posted a status in Group: <a href="'.esc_url(bp_activity_get_permalink(esc_html( $entry->post_id ))).'" target="_blank">View Post</a>';
	} else if ( $activity_type == 'replied_status') {
		$note = 'Replied to a status update: <a href="'.esc_url(bp_activity_get_permalink($log_meta['parent_id']).'#acomment-'.$log_meta['reply_id']).'" target="_blank">View Reply</a>';
	} else if ( $activity_type == 'reacted_post') {
		$note = 'Reacted to a post: <a href="'.esc_url(bp_activity_get_permalink(esc_html( $entry->post_id ))).'" target="_blank">View Post</a>';
	} else if ( $activity_type == 'posted_discussion') {
		$note = 'Started a new discussion: <a href="'.esc_url(get_permalink($log_meta['discussion_id'])).'" target="_blank">View Discussion</a>';
	} else if ( $activity_type == 'replied_discussion') {
		$note = 'Replied to a  discussion: <a href="'.esc_url(get_permalink($log_meta['reply_id'])).'" target="_blank">View Reply</a>';
	}else {
		$note = $log_meta['note'] ?? 'Unknown';
	}

	$timestamp = $entry->created_at ?? '';
	$timestamp_date = ! empty( $timestamp )
    ? date_i18n( 'F j, Y', strtotime( $timestamp ) )
    : '';
	return [
		'user_name'     => $user_name,
		'user_link'     => $user_link,
		'activity_type' => $activity_label,
		'note'          => $note,
		'timestamp'     => $timestamp,
		'timestamp_date'     => $timestamp_date,
		'group_link'     => $group_link,
		'group_activity'     => $group_activity,
	];
}

//list all activities
function engagifii_render_activity_log() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'engagifii_activity_log';
// Get filter values
$selected_member    = isset($_GET['member_id']) ? intval($_GET['member_id']) : '';
$activity_type  = isset($_GET['activity_type']) ? sanitize_text_field($_GET['activity_type']) : '';
$activity_date   = isset($_GET['activity_date']) ? $_GET['activity_date'] : '';
$per_page    = 20;
$paged       = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset      = ($paged - 1) * $per_page;
$params = [];
$count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE 1=1";
$data_sql  = "SELECT * FROM {$table_name} WHERE 1=1";

// Add filters
if ( $selected_member ) {
    $count_sql .= " AND user_id = %d";
    $data_sql  .= " AND user_id = %d";
    $params[] = $selected_member;
}

if ( $activity_type ) {
    $count_sql .= " AND activity_type = %s";
    $data_sql  .= " AND activity_type = %s";
    $params[] = $activity_type;
}

if ( $activity_date && preg_match( '/^\d{6}$/', $activity_date ) ) {
    $year = substr( $activity_date, 0, 4 );
    $month = substr( $activity_date, 4, 2 );
    $count_sql .= " AND YEAR(created_at) = %d AND MONTH(created_at) = %d";
    $data_sql .= " AND YEAR(created_at) = %d AND MONTH(created_at) = %d";
    $params[] = intval($year);
    $params[] = intval($month);
}

// Ordering and limiting for data
$data_sql .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
$params[] = $per_page;
$params[] = $offset;

// Prepare queries
$count_query = $wpdb->prepare($count_sql, ...$params);
$data_query  = $wpdb->prepare($data_sql, ...$params);

// Execute
$total_items = $wpdb->get_var($count_query);
$lines     = $wpdb->get_results($data_query);
$activity_types = engagifii_get_activity_types();
echo '<div class="wrap"><h1 class="wp-heading-inline">Activity Log';
$has_filter = !empty($_GET['member_id']) || !empty($_GET['activity_type']) || !empty($_GET['activity_date']);
if ( $has_filter ) {
    echo '<span class="subtitle">Filtered results for <span class="filter-log-params">';
    $search_parts = [];
    if ( !empty($_GET['member_id']) ) {
        $search_parts[] = 'Member: <strong>' . esc_html(($user = get_user_by('ID', $_GET['member_id']))->display_name).'</strong>';
    }
    if ( !empty($_GET['activity_type']) ) {
        $search_parts[] = 'Activity: <b>' . esc_html($activity_types[$_GET['activity_type']]).'</b>';
    }
    if ( !empty($_GET['activity_date']) ) {
        $date_raw = esc_html($_GET['activity_date']);
        $date_obj = DateTime::createFromFormat('Ym', $date_raw);
        if ( $date_obj ) {
            $search_parts[] = 'Month: <b>' . $date_obj->format('F Y').'</b>';
        }
    }
    echo ' "' . implode(', ', $search_parts) . '"</span></span>';
}

echo '</h1>';
// Filter UI
echo '<div class="tablenav top"><div class="alignleft actions"><form method="get" action=""><input type="hidden" name="page" value="engagifii_activity_log"><label for="user_id" class="screen-reader-text">User: </label>';
echo '<select name="member_id">';
echo '<option value="">All Members</option>';
$users = get_users(['fields' => ['ID', 'display_name']]);
foreach ($users as $user) {
    $selected = $user->ID == $selected_member ? 'selected' : '';
    echo "<option value='{$user->ID}' {$selected}>{$user->display_name}</option>";
}
echo '</select> ';

echo '<label for="activity_type" class="screen-reader-text">Activity Type: </label>';
echo '<select name="activity_type">';
echo '<option value="">All Activity Types</option>';
foreach ($activity_types as $value => $label) {
    $selected = ($value === $activity_type) ? 'selected' : '';
    echo "<option value='{$value}' {$selected}>{$label}</option>";
}
echo '</select> ';

// Date fields
$oldest_date_str = $wpdb->get_var( "SELECT MIN(created_at) FROM $table_name" );
$start = new DateTime('-12 months'); // Default: 12 months ago
if ( $oldest_date_str ) {
    try {
        $first_log_time = new DateTime( $oldest_date_str );
        $start = $first_log_time->modify('first day of this month');
    } catch ( Exception $e ) {
        // fallback if date is invalid
    }
}
$now = new DateTime('first day of this month');
$period = new DatePeriod($start, new DateInterval('P1M'), $now);
foreach ($period as $date) {
    $val = $date->format('Ym');
    $label = $date->format('F Y');
	 $months[] = [
        'value' => $val,
        'label' => $label,
        'selected' => ($val === $activity_date) ? 'selected' : ''
    ];
}
$months = array_reverse($months);
echo '<label for="activity_date" class="screen-reader-text">Filter by month</label>';
echo '<select name="activity_date"><option value="">All Dates</option>';
foreach ($months as $month) {
    echo "<option value='{$month['value']}' {$month['selected']}>{$month['label']}</option>";
}
echo '</select> ';
echo '<input type="submit" class="button button-primary" value="Filter" />';
if ($selected_member || $activity_type || $activity_date): ?>
  <a href="<?php echo admin_url('admin.php?page=engagifii_activity_log'); ?>" class="button reset_filter"><span class="dashicons dashicons-update"></span>Reset Filter</a>
<?php endif; 
echo '</form></div><div class="tablenav-pages activity-pagination"><span class="displaying-num">' . $total_items . ' Logs</span>';
$total_pages     = ceil($total_items / $per_page);
if ($total_pages > 1) {
    $base_url = remove_query_arg('paged');
    echo paginate_links([
        'base'      => add_query_arg('paged', '%#%'),
        //'format'    => '',
		//'type'    => 'list',
        'prev_text' => __('«'),
        'next_text' => __('»'),
        'total'     => $total_pages,
        'current'   => $paged,
    ]);
}

echo '</div>'; ?>
<div class="activity-modal">
    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
        <input type="hidden" name="member_id" value="<?php echo esc_attr($_GET['member_id'] ?? ''); ?>">
        <input type="hidden" name="activity_type" value="<?php echo esc_attr($_GET['activity_type'] ?? ''); ?>">
        <input type="hidden" name="activity_date" value="<?php echo esc_attr($_GET['activity_date'] ?? ''); ?>">

        <button type="submit" name="action" id="delete-log-btn" value="engagifii_delete_activity_logs" class="button button-outline-primary">
            <span class="dashicons dashicons-trash"></span> Clear Logs
        </button>
        <button type="submit" name="action" value="engagifii_export_activity_csv" class="button button-primary">
            <span class="dashicons dashicons-media-spreadsheet"></span> Export CSV
        </button>

    </form>
</div>
<br class="clear"></div>
<?php
	if (empty($lines)) {
	  echo '<div class="notice notice-error"><p><strong>No logs found.</strong> Please try with different filters.</p></div>';
	  return;
  }
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>Member Name</th><th>Activity Type</th><th>Activity Detail</th><th>Date/Time</th></tr></thead>';
    echo '<tbody>';
	  foreach ($lines as $entry) {
		 // print_r($entry);
		  $data = engagifii_parse_activity_entry( $entry, $activity_types );
		  if ( ! $data ) continue;
		
		  $found_logs = true;
		  echo '<tr>';
		  echo '<td>' .  '<a href="'.$data['user_link'].'" target="_blank">'.$data['user_name'].'</a>'. '</td>';
		  echo '<td>' . esc_html( $data['activity_type'] ) . '</td>';
		  echo '<td>' .  $data['note']  . '</td>';
		  echo '<td>' . esc_html( $data['timestamp'] ) . '</td>';
		  echo '</tr>';
	}
    echo '</tbody></table></div>';
}

//append log entry
function engagifii_write_activity_log_entry( $entry ) {
	// Validate entry structure
	if ( ! is_array( $entry ) || empty( $entry['timestamp'] ) || empty( $entry['activity_type'] ) ) {
		return;
	}
    $upload_dir = wp_upload_dir();
    $log_dir    = trailingslashit($upload_dir['basedir']) . 'engagifii-activity/';
    $log_file   = $log_dir . 'activity.log';
	// Append entry
	$entry_line = json_encode( $entry ) . "\n";
	file_put_contents( $log_file, $entry_line, FILE_APPEND );
	// ==== 2. Insert into CUSTOM TABLE ====
	global $wpdb;
	$table_name = $wpdb->prefix . 'engagifii_activity_log';
	$entry['log_meta']['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	$data = [
		'user_id'       => isset( $entry['user_id'] ) ? intval( $entry['user_id'] ) : 0,
		'activity_type' => sanitize_text_field( $entry['activity_type'] ),
		'created_at'    => date( 'Y-m-d H:i:s', intval( $entry['timestamp'] ) ),
		'post_id'       => isset( $entry['post_id'] ) ? intval( $entry['post_id'] ) : null,
		'hub_id'        => isset( $entry['hub_id'] ) ? intval( $entry['hub_id'] ) : null,
		'log_meta'      => maybe_serialize( $entry['log_meta'] ),
	];

	$wpdb->insert( $table_name, $data );
}

//join group log
function engagifii_log_group_join( $group_id, $user_id ) {
    $entry = [
        'timestamp'      => current_time( 'timestamp' ),
        'activity_type'  => 'joined_hub',
        'user_id'        => $user_id,
        'hub_id' => $group_id,
    ];
   	engagifii_write_activity_log_entry( $entry );
}
//create group log
function engagifii_log_group_created_once( $group_id, $group ) {
	if ( ! $group_id || empty( $group->creator_id ) ) {
		return;
	}
	// Avoid duplicate logging
	$already_logged = get_transient( "engagifii_logged_group_{$group_id}" );
	if ( $already_logged ) {
		return;
	}
	// Mark as logged for 10 minutes
	set_transient( "engagifii_logged_group_{$group_id}", true, 10 * MINUTE_IN_SECONDS );
	$entry = [
		'timestamp'     => current_time( 'timestamp' ),
		'activity_type' => 'created_hub',
		'user_id'       => $group->creator_id,
		'hub_id' => $group_id,
	];
	engagifii_write_activity_log_entry( $entry );
}
//new member registered
function engagifii_log_user_created( $user_id ) {
	$entry = [
		'timestamp'     => current_time( 'timestamp' ),
		'activity_type' => 'member_registered',
		'user_id'       => $user_id,
		'log_meta'      => [
			'note' => 'user_register',
		],
	];
	engagifii_write_activity_log_entry( $entry );
}
//new member registered via SSO
function engagifii_log_user_created_sso( $user_id ) {
	$entry = [
		'timestamp'     => current_time( 'timestamp' ),
		'activity_type' => 'member_registered',
		'user_id'       => $user_id,
		'log_meta'      => [
			'note' => 'SSO',
		],
	];
	engagifii_write_activity_log_entry( $entry );
}
//member logged in 
function engagifii_log_member_login($user_login, $user) {
	$entry = [
        'timestamp'     => current_time( 'timestamp' ),
        'activity_type' => 'member_login',
        'user_id'       => $user->ID,
		'log_meta'      => [
			'note' => 'wp_login',
		],
    ];
	engagifii_write_activity_log_entry( $entry );
}
//member logged in via SSO 
function engagifii_log_member_login_sso($user_id) {
	$entry = [
        'timestamp'     => current_time( 'timestamp' ),
        'activity_type' => 'member_login',
        'user_id'       => $user_id,
		'log_meta'      => [
			'note' => 'SSO',
		],
    ];
	engagifii_write_activity_log_entry( $entry );
}
//member logged out 
function engagifii_log_member_logout() {
	$user_id = isset($GLOBALS['engagifii_logged_in_user_id']) ? $GLOBALS['engagifii_logged_in_user_id'] : '';
	if (!$user_id) return;
	
	$entry = [
        'timestamp'     => current_time( 'timestamp' ),
        'activity_type' => 'member_logout',
        'user_id'       => $user_id,
		'log_meta'      => [
                'note' => 'Member Logged out',
            ],
    ];

	engagifii_write_activity_log_entry( $entry );
}
//member posted a status/post
function engagifii_log_status_post($activity) {
     // Skip edits
    if ($_POST['edit_activity'] !== 'false' || $activity->type !== 'activity_update' ) {
        return;
    }
    $user_id = $activity->user_id;
    $post_id = $activity->id;
    $timestamp = current_time('timestamp');
    $entry = [
        'timestamp'     => $timestamp,
        'user_id'       => $user_id,
        'activity_type' => '',
    ];
    // Timeline update (not in group)
    if (empty($activity->item_id) && $activity->component === 'activity') {
        $entry['activity_type'] = 'posted_status';
        $entry['post_id'] = $post_id;
    }
    // Group update
    if (!empty($activity->item_id) && $activity->component === 'groups') {
        $entry['activity_type'] = 'posted_in_group';
        $entry['post_id'] = $post_id;
    }
        engagifii_write_activity_log_entry($entry);
}
//member replied to a status update
function engagifii_log_status_reply($comment_id, $params) {
    $comment = new BP_Activity_Activity($comment_id);
    if ($comment->type === 'activity_comment') {
        $entry = [
            'timestamp'     => current_time('timestamp'),
            'activity_type' => 'replied_status',
            'user_id'       => $comment->user_id,
            'log_meta'      => [
                'reply_id' => $comment_id,
                'parent_id' => $comment->item_id,
            ],
        ];
        engagifii_write_activity_log_entry($entry);
    }
}
//member started a discussion
function engagifii_log_new_discussion($topic_id, $forum_id, $anonymous_data, $topic_author_id) {
    if (!$topic_author_id) {
        return;
    }
	$entry = [
			'timestamp'     => current_time( 'timestamp' ),
            'activity_type' => 'posted_discussion',
            'user_id' => $topic_author_id,
			'log_meta'      => [
			  'discussion_id' => $topic_id,
			],
        ];
	engagifii_write_activity_log_entry( $entry );
}
//member replied to a discussion
function engagifii_log_new_reply($reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author_id) {
    if (!$reply_author_id) return;

    $entry = [
			'timestamp'     => current_time( 'timestamp' ),
            'activity_type' => 'replied_discussion',
            'user_id' => $reply_author_id,
			'log_meta'      => [
			  'reply_id' => $reply_id,
			],
        ];
	engagifii_write_activity_log_entry( $entry );
}
//member reacted to a post/reply
function engagifii_log_reaction_ajax() {
    if ( ! is_user_logged_in() || empty($_POST['reaction_id'])) {
        return;
    }
    $user_id      = get_current_user_id();
    $reaction_id  = intval( $_POST['reaction_id'] );
    $activity_id  = intval( $_POST['item_id'] );
    $entry = [
        'timestamp'     => current_time('timestamp'),
        'activity_type' => 'reacted_post',
        'user_id'       => $user_id,
        'post_id'       => $activity_id,
        'log_meta'      => [
            'reaction_id' => $reaction_id,
        ],
    ];

    engagifii_write_activity_log_entry( $entry );
}
//export CSV
function engagifii_export_activity_csv() {
    global $wpdb;
	$table_name = $wpdb->prefix . 'engagifii_activity_log';
// Get filter values
$selected_member    = isset($_POST['member_id']) ? intval($_POST['member_id']) : null;
$activity_type  = isset($_POST['activity_type']) ? sanitize_text_field($_POST['activity_type']) : null;
$activity_date   = isset($_POST['activity_date']) ? $_POST['activity_date'] : null;
$params = [];
$data_sql  = "SELECT * FROM {$table_name} WHERE 1=1";
if ( $selected_member ) {
    $data_sql  .= " AND user_id = %d";
    $params[] = $selected_member;
}
if ( $activity_type ) {
    $data_sql  .= " AND activity_type = %s";
    $params[] = $activity_type;
}
if ( $activity_date && preg_match( '/^\d{6}$/', $activity_date ) ) {
    $year = substr( $activity_date, 0, 4 );
    $month = substr( $activity_date, 4, 2 );
    $data_sql .= " AND YEAR(created_at) = %d AND MONTH(created_at) = %d";
    $params[] = intval($year);
    $params[] = intval($month);
}
$data_sql .= " ORDER BY created_at DESC";
$data_query  = $wpdb->prepare($data_sql, ...$params);
$lines     = $wpdb->get_results($data_query);
	if ( ob_get_length() ) {
        ob_end_clean();
    }
	$site_title = sanitize_title( get_bloginfo('name') ); 
	$current_time = current_time( 'timestamp' ); 
	$timestamp = date( 'dmyHi', $current_time );
	$filename   = "{$site_title}-activity-export-{$timestamp}.csv";
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Pragma: no-cache');
    header('Exires: 0');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Member Name', 'Activity Type', 'Activity Detail', 'Date']);
	$activity_types = engagifii_get_activity_types();
     foreach ($lines as $entry) {
		$data = engagifii_parse_activity_entry( $entry, $activity_types );
		if ( ! $data ) continue;
		
		$username = $data['user_name'];
		$user_link = $data['user_link'];
		$hyperlinked_name = '=HYPERLINK("' . $user_link . '", "' . $username . '")';
		if($data['group_link']){
		  $group_link = $data['group_link'];
		  $group_activity = $data['group_activity'];
			$hyperlinked_activity = '=HYPERLINK("' . $group_link . '", "' . $group_activity . '")';
		}else{
			$hyperlinked_activity = $data['note'];
		}
		fputcsv( $output, [ $hyperlinked_name, $data['activity_type'], $hyperlinked_activity, $data['timestamp_date'] ] );
    }
    fclose($output);
    exit;
}
add_action( 'admin_post_engagifii_export_activity_csv', 'engagifii_export_activity_csv' );
//delete logs
add_action('admin_post_engagifii_delete_activity_logs', 'engagifii_delete_activity_logs');
function engagifii_delete_activity_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'engagifii_activity_log';

    $where = '1=1';
    $params = [];

    if ( ! empty($_POST['member_id']) ) {
        $where .= ' AND user_id = %d';
        $params[] = intval($_POST['member_id']);
    }
   if ( ! empty($_POST['activity_type']) ) {
    $where .= ' AND activity_type = %s';
    $params[] = sanitize_text_field($_POST['activity_type']);
	} else {
		// If no activity_type filter, prevent deleting 'log_initialized'
		$where .= " AND activity_type != %s";
		$params[] = 'log_initialized';
	}
    if ( ! empty($_POST['activity_date']) ) {
        $year  = substr($_POST['activity_date'], 0, 4);
        $month = substr($_POST['activity_date'], 4, 2);
        $where .= ' AND YEAR(created_at) = %d AND MONTH(created_at) = %d';
        $params[] = intval($year);
        $params[] = intval($month);
    }

    $sql = "DELETE FROM {$table_name} WHERE $where";
    $query = $wpdb->prepare($sql, ...$params);
    $wpdb->query($query);

    wp_redirect( admin_url('admin.php?page=engagifii_activity_log&deleted=1') );
    exit;
}
add_action( 'admin_notices', function() {
    if ( isset($_GET['deleted']) ) {
        echo '<div class="notice notice-success is-dismissible"><p>Filtered logs deleted successfully.</p></div>';
    }
});