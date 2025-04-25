<?php
defined( 'ABSPATH' ) || exit;
//plugin update check
add_filter( 'plugins_api', 'bbEngagifii_plugin_info', 20, 3); 
function bbEngagifii_plugin_info( $res, $action, $args ){
	if( 'plugin_information' !== $action ) {
		return $res;
	}
	if( basename( dirname( plugin_dir_path( __FILE__ ) ) ) !== $args->slug ) {
		return $res;
	}
	$response = wp_remote_get( 
		'https://engagifiiweb.com/engagifii_plugins/engagifii_addon_buddyboss/plugin-update.json', 
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept' => 'application/json'
			) 
		)
	);
	if( 
		is_wp_error( $response )
		|| 200 !== wp_remote_retrieve_response_code( $response )
		|| empty( wp_remote_retrieve_body( $response ) )
	) {
		return $res;	
	}
	$remote = json_decode( wp_remote_retrieve_body( $response ) );
	$res = new stdClass();
	$res->name = $remote->name;
	$res->slug = $remote->slug;
	$res->author = $remote->author;
	$res->author_profile = $remote->author_profile;
	$res->version = $remote->version;
	$res->tested = $remote->tested;
	$res->requires = $remote->requires;
	$res->requires_php = $remote->requires_php;
	$res->download_link = $remote->download_url;
	$res->trunk = $remote->download_url;
	$res->last_updated = $remote->last_updated;
	$res->sections = array(
		'description' => $remote->sections->description,
		'installation' => $remote->sections->installation,
		'changelog' => $remote->sections->changelog
	);
	// in case you want the screenshots tab, use the following HTML format for its content:
	// <ol><li><a href="IMG_URL" target="_blank"><img src="IMG_URL" alt="CAPTION" /></a><p>CAPTION</p></li></ol>
	if( ! empty( $remote->sections->screenshots ) ) {
		$res->sections[ 'screenshots' ] = $remote->sections->screenshots;
	}

	$res->banners = array(
		'low' => $remote->banners->low,
		'high' => $remote->banners->high
	);
	
	return $res;

}
add_filter('pre_set_site_transient_update_plugins', 'bbEngagifii_plugin_update');
function bbEngagifii_plugin_update($transient) { 
    $response = wp_remote_get('https://engagifiiweb.com/engagifii_plugins/engagifii_addon_buddyboss/plugin-update.json');
    if (is_wp_error($response)) {
        return $transient;
    }
    $data = json_decode(wp_remote_retrieve_body($response)); 
    if (version_compare(BB_ENGAGIFII_VERSION, $data->version, '<')) {
        $transient->response[engagifii_BB_ADDON_PLUGIN_BASENAME] = (object) array(
            'slug' => 'engagifii_addon_buddyboss', //plugin folder name
            'plugin' => engagifii_BB_ADDON_PLUGIN_BASENAME,
            'new_version' => $data->version,
            'url' => 'https://engagifiiweb.com/engagifii_plugins/engagifii_addon_buddyboss',
            'package' => $data->download_url,
            'icons' => array(
				'1x' => $data->icons->{'1x'}
			),
			
        );
    }
    return $transient;
}

