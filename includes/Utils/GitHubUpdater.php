<?php
namespace WPForceRepair\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GitHubUpdater {

	private $slug;
	private $plugin_file;
	private $github_repo;
	private $transient_key;

	public function __construct( $plugin_file, $github_repo ) {
		$this->plugin_file = $plugin_file;
		$this->github_repo = $github_repo;
		$this->slug = dirname( plugin_basename( $plugin_file ) );
		$this->transient_key = 'wfr_github_update_' . md5( $this->slug );

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api', [ $this, 'check_info' ], 10, 3 );
	}

	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Get the remote version
		$remote_version = $this->get_remote_version();

		if ( $remote_version && version_compare( WFR_VERSION, $remote_version->new_version, '<' ) ) {
			$res = new \stdClass();
			$res->slug = $this->slug;
			$res->plugin = plugin_basename( $this->plugin_file );
			$res->new_version = $remote_version->new_version;
			$res->tested = $remote_version->tested;
			$res->package = $remote_version->package;
            $res->url = $remote_version->url;
            $res->icons = [
                '1x' => 'https://raw.githubusercontent.com/' . $this->github_repo . '/master/assets/icon-128x128.png',
                '2x' => 'https://raw.githubusercontent.com/' . $this->github_repo . '/master/assets/icon-256x256.png'
            ];
            $res->banners = [
                'low' => 'https://raw.githubusercontent.com/' . $this->github_repo . '/master/assets/banner-772x250.png',
                'high' => 'https://raw.githubusercontent.com/' . $this->github_repo . '/master/assets/banner-1544x500.png'
            ];

			$transient->response[ $res->plugin ] = $res;
		}

		return $transient;
	}

	public function check_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $res;
		}

		if ( $this->slug !== $args->slug ) {
			return $res;
		}

		$remote_version = $this->get_remote_version();

		if ( ! $remote_version ) {
			return $res;
		}

		$res = new \stdClass();
		$res->name = 'WP Force Repair';
		$res->slug = $this->slug;
		$res->version = $remote_version->new_version;
		$res->tested = $remote_version->tested;
		$res->requires = '5.6';
		$res->author = '<a href="https://ajithrn.com">Ajith R N</a>';
		$res->author_profile = 'https://github.com/ajithrn';
		$res->download_link = $remote_version->package;
		$res->trunk = $remote_version->package;
		$res->last_updated = $remote_version->last_updated;
		$res->sections = [
			'description' => 'The ultimate recovery tool. Force install plugins/themes, repair core files, and clean up malware/hacks.',
			'changelog' => $remote_version->body,
		];
		$res->banners = [
			'low' => 'https://raw.githubusercontent.com/' . $this->github_repo . '/master/assets/banner-772x250.png',
			'high' => 'https://raw.githubusercontent.com/' . $this->github_repo . '/master/assets/banner-1544x500.png'
		];

		return $res;
	}

	private function get_remote_version() {
		// return get_site_transient( $this->transient_key ); // Uncomment to enable caching
		$remote = get_site_transient( $this->transient_key );

		if ( false === $remote ) {
			$remote = $this->fetch_github_release();
			set_site_transient( $this->transient_key, $remote, 12 * HOUR_IN_SECONDS );
		}

		return $remote;
	}

	private function fetch_github_release() {
		$url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
		$response = wp_remote_get( $url, [
            'timeout' => 10,
            'headers' => [ 'Accept' => 'application/vnd.github.v3+json' ] 
        ] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! isset( $body->tag_name ) ) return false;

		$version = ltrim( $body->tag_name, 'v' );
        
        // Find zip asset
        $package = $body->zipball_url;
        if ( ! empty( $body->assets ) ) {
            foreach ( $body->assets as $asset ) {
                if ( $asset->name === 'wp-force-repair.zip' ) {
                    $package = $asset->browser_download_url;
                    break;
                }
            }
        }

		$obj = new \stdClass();
		$obj->new_version = $version;
		$obj->url = $body->html_url;
		$obj->package = $package;
        $obj->body = $this->parse_markdown($body->body);
        $obj->last_updated = $body->published_at;
        $obj->tested = '6.7'; // Assume compatibility with latest for now

		return $obj;
	}
    
    private function parse_markdown( $text ) {
        // Simple markdown parser for changelog (bold, list, link)
        $text = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text );
        $text = preg_replace( '/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $text );
        $text = preg_replace( '/^\s*-\s+(.*)/m', '<li>$1</li>', $text );
        $text = preg_replace( '/((<li>.*<\/li>\s*)+)/s', '<ul>$1</ul>', $text );
        return nl2br( $text );
    }
}
