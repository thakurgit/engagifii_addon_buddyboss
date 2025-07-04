<?php
//create activity log file
function engagifii_init_activity_log() {
	//check if activity log enabled
$options = get_option( 'bb_engagifii' );
if ( !empty($options['misc']['activity_log']) ) {
	add_action('admin_menu', function () {
	  add_menu_page(
		  'Members Activity Log',
		  'Members Activity Log',
		  'manage_options',
		  'engagifii_activity_log',
		  'engagifii_render_activity_log_page',
		  'dashicons-list-view',
		  8
	  );
	  });
}else {
    return;
}
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
	add_action( 'groups_created_group', 'engagifii_log_group_created', 10, 2 );
	add_action( 'groups_join_group', 'engagifii_log_group_join', 10, 2 );
	add_action( 'user_register', 'engagifii_log_user_created', 10, 1 );
}
add_action('init', 'engagifii_init_activity_log');

//engagifii activity types
function engagifii_get_activity_types() {
    return [
		//'log_initialized'    => 'Activity Log Initialized',
		'created_hub'         => 'Created a Hub',
		'joined_hub'         => 'Joined a Hub',
		'member_registered'     => 'New member registered',
		'posted_status'      => 'Posted a Status Update',
		'changed_avatar'     => 'Member Changed Profile Photo',
		'edited_hub_details' => 'Hub Details Edited',
		'group_access'       => 'Accessed a Hub',
		'posted_discussion'  => 'Posted a Discussion',
		'commented'          => 'Commented on a Discussion',
		'reacted'            => 'Reacted to a Post',
		'uploaded_doc'       => 'Uploaded a Document',
		'downloaded_doc'     => 'Viewed/Downloaded a Document',
	 ];
}
//parse logs entry
function engagifii_parse_activity_entry( $entry, $activity_types = [] ) {
	if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $entry ) ) {
		return false;
	}

	$user_id   = $entry['user_id'] ?? '';
	$user_info = get_userdata( $user_id );
	$user_name = $user_info ? $user_info->display_name : 'User ID ' . $user_id;
	$user_email = $user_info ? $user_info->user_email : '';
	$user_link = esc_url( $user_info ? bp_core_get_user_domain( $user_id ) : '' );

	$activity_type  = $entry['activity_type'] ?? 'Unknown';
	$activity_label = $activity_types[$activity_type] ?? ucfirst(str_replace('_', ' ', $activity_type));

	// Build human-readable activity
	if ( in_array( $activity_type, [ 'joined_hub', 'created_hub' ] ) ) {
		$hub_id = $entry['log_meta']['hub_id'] ?? '';
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
	} else {
		$note = $entry['log_meta']['note'] ?? 'Unknown';
	}

	$raw_timestamp = $entry['timestamp'] ?? '';
	$timestamp = is_numeric( $raw_timestamp )
		? date_i18n( 'F j, Y \a\t g:i a', (int) $raw_timestamp )
		: '';
	$timestamp_date = is_numeric( $raw_timestamp )
		? date_i18n( 'F j, Y', (int) $raw_timestamp )
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
function engagifii_render_activity_log_page() {
	$upload_dir = wp_upload_dir();
	$log_file = trailingslashit( $upload_dir['basedir'] ) . 'engagifii-activity/activity.log';

    echo '<div class="wrap"><h1>Activity Log</h1>';

    if (!file_exists($log_file)) {
        echo '<p><strong>No log file found.</strong></p></div>';
        return;
    }
	$lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$lines = array_reverse($lines); // Newest first
    if (empty($lines)) {
        echo '<p>No activity logged yet.</p></div>';
        return;
    }
// Get filter values
$selected_member    = isset($_GET['member_id']) ? intval($_GET['member_id']) : '';
$activity_type  = isset($_GET['activity_type']) ? sanitize_text_field($_GET['activity_type']) : '';
$activity_date          = isset($_GET['activity_date']) ? $_GET['activity_date'] : '';
foreach ($lines as $line) {
    $entry = json_decode($line, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($entry)) continue;

    if ($selected_member && $entry['user_id'] != $selected_member) continue;
    if ($activity_type && $entry['activity_type'] !== $activity_type) continue;
    if ($activity_date) {
	  $entry_month = date('Ym', (int) $entry['timestamp']);
	  if ($entry_month != $activity_date) {
		  continue;
	  }
	}

    $filtered_entries[] = $entry;
}
	$total_items = count($filtered_entries);
	$per_page    = 20;
	$paged       = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
	$offset      = ($paged - 1) * $per_page;
	$logs_to_display = array_slice($filtered_entries, $offset, $per_page);
	$total_pages     = ceil($total_items / $per_page);
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

$activity_types = engagifii_get_activity_types();
echo '<label for="activity_type" class="screen-reader-text">Activity Type: </label>';
echo '<select name="activity_type">';
echo '<option value="">All Activity Types</option>';
foreach ($activity_types as $value => $label) {
    $selected = ($value === $activity_type) ? 'selected' : '';
    echo "<option value='{$value}' {$selected}>{$label}</option>";
}
echo '</select> ';

// Date fields
echo '<label for="activity_date" class="screen-reader-text">Filter by month</label>';
echo '<select name="activity_date"><option value="">All Dates</option>';
// Get the first line (oldest log)
$first_line = '';
$fh = fopen($log_file, 'r');
if ($fh) {
    while (($line = fgets($fh)) !== false) {
        if (!empty(trim($line))) {
            $first_line = $line;
            break;
        }
    }
    fclose($fh);
}

// Default: start from 12 months ago
$start = new DateTime('-12 months');
if ($first_line) {
    $entry = json_decode($first_line, true);
    if (isset($entry['timestamp']) && is_numeric($entry['timestamp'])) {
        $first_log_time = (int) $entry['timestamp'];
        $start = (new DateTime())->setTimestamp($first_log_time)->modify('first day of this month');
    }
}
$now = new DateTime('first day of this month');
$end = (clone $now)->modify('first day of this month');
$period = new DatePeriod($start, new DateInterval('P1M'), $end);
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
foreach ($months as $month) {
    echo "<option value='{$month['value']}' {$month['selected']}>{$month['label']}</option>";
}
echo '</select> ';
echo '<input type="submit" class="button button-primary" value="Filter" />';
if ($selected_member || $activity_type): ?>
  <a href="<?php echo admin_url('admin.php?page=engagifii_activity_log'); ?>" class="button reset_filter"><span class="dashicons dashicons-update"></span>Reset Filter</a>
<?php endif; 
echo '</form></div><div class="tablenav-pages activity-pagination"><span class="displaying-num">' . $total_items . ' Items</span>';
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
<button class="button button-outline-primary" class="clear-log"><span class="dashicons dashicons-trash"></span>Clear Log</button>
<form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
    <input type="hidden" name="action" value="engagifii_export_activity_csv">
    <input type="hidden" name="member_id" value="<?php echo esc_attr($_GET['member_id'] ?? ''); ?>">
    <input type="hidden" name="activity_type" value="<?php echo esc_attr($_GET['activity_type'] ?? ''); ?>">
    <input type="hidden" name="activity_date" value="<?php echo esc_attr($_GET['activity_date'] ?? ''); ?>">
    <button type="submit" class="button button-primary">
        <span class="dashicons dashicons-media-spreadsheet"></span> Export CSV
    </button>
</form></div>
<br class="clear"></div>
<?php
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>Member Name</th><th>Activity Type</th><th>Activity Detail</th><th>Date/Time</th></tr></thead>';
    echo '<tbody>';
	if (empty($filtered_entries)) {
	  echo '<div class="notice notice-error"><p><strong>No logs found.</strong> Please try with different filters.</p></div>';
	} else {
	  foreach ($logs_to_display as $entry) {
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
	}
    echo '</tbody></table></div>';
}
//append log entry
function engagifii_write_activity_log_entry( $entry ) {
	// Validate entry structure
	if ( ! is_array( $entry ) || empty( $entry['timestamp'] ) || empty( $entry['activity_type'] ) ) {
		return;
	}
    $upload_dir = wp_upload_dir(); // Gets wp-content/uploads path
    $log_dir    = trailingslashit($upload_dir['basedir']) . 'engagifii-activity/';
    $log_file   = $log_dir . 'activity.log';
	// Append entry
	$entry_line = json_encode( $entry ) . "\n";
	file_put_contents( $log_file, $entry_line, FILE_APPEND );
}

//join group log
function engagifii_log_group_join( $group_id, $user_id ) {
    $entry = [
        'timestamp'      => current_time( 'timestamp' ),
        'activity_type'  => 'joined_hub',
        'user_id'        => $user_id,
        'log_meta'       => [
            'hub_id' => $group_id,
        ]
    ];

   	engagifii_write_activity_log_entry( $entry );
}
//create group log
function engagifii_log_group_created( $group_id, $user_id ) {
	$entry = [
		'timestamp'     => current_time( 'timestamp' ),
		'activity_type' => 'created_hub',
		'user_id'       => $user_id->creator_id,
		'log_meta'      => [
			'hub_id'   => $group_id,
		],
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
			'note' => 'Member Profile Created (user_register)',
		],
	];

	engagifii_write_activity_log_entry( $entry );
}
//export CSV
function engagifii_export_activity_csv() {
    $upload_dir = wp_upload_dir(); // Gets wp-content/uploads path
    $log_dir    = trailingslashit($upload_dir['basedir']) . 'engagifii-activity/';
    $log_file   = $log_dir . 'activity.log';
    if ( ! file_exists($log_file) ) {
        wp_send_json_error('Activity Log file not found.');
    }
	if ( ob_get_length() ) {
        ob_end_clean();
    }
// Get filter values
$selected_member    = isset($_POST['member_id']) ? intval($_POST['member_id']) : '';
$activity_type  = isset($_POST['activity_type']) ? sanitize_text_field($_POST['activity_type']) : '';
$activity_date          = isset($_POST['activity_date']) ? $_POST['activity_date'] : '';
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
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$activity_types = engagifii_get_activity_types();
     foreach (array_reverse($lines) as $line) {
        $entry = json_decode( $line, true );
		if ($selected_member && $entry['user_id'] != $selected_member) continue;
		if ($activity_type && $entry['activity_type'] !== $activity_type) continue;
		if ($activity_date) {
		  $entry_month = date('Ym', (int) $entry['timestamp']);
		  if ($entry_month != $activity_date) {
			  continue;
		  }
		}
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