<?php
/**
 * Plugin Name:       Ultra Addons For WPForms Webhooks
 * Plugin URI:        https://themefic.com
 * Description:       Integrate WPForms with other external API that support them .
 * Author:            WPForms
 * Author URI:        https://themefic.com
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.2
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ultrawpf-webhooks
 * Domain Path:       /languages
 *
 */

// Exit if accessed directly.
use WPFormsWebhooks\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Themefic\UtrawpfWebhooks\Webhook;

final class Ultra_Addons_For_WPForms_Webhooks {


	const WEBHOOKS_VERSION = '1.0.0';

    public function __construct()
    {

        $this->define_constants();

        add_action('init', array( $this , 'ultrawpf_webhooks_plugin_init') );

    }

    /**
     * Initializes a singleton instance
     *
     * @return \Ultra_Addons_For_WPForms_Webhooks
     */
    public static function init(  ){

        static $instance = false;

        if( !$instance ){
            $instance = new self();
        }

        return $instance;

    }

    public function define_constants(){

        /**
		 * define all necessary constants
		 */

		define( 'ULTRAWPF_WEBHOOKS_PATH', plugin_dir_path( __FILE__ ) );
		define( 'ULTRAWPF_WEBHOOKS_URL', plugin_dir_url( __FILE__ ) );
		define( 'WEBHOOKS_VERSION', self::WEBHOOKS_VERSION );


    }

    public function ultrawpf_webhooks_plugin_init() {

        //require plugin update checker file
        // if( is_admin() ){
        //     require_once ULTRAWPF_WEBHOOKS_PATH . 'includes/update-checker.php';
        // }

        if ( is_plugin_active( 'ultra-addons-for-wpforms/ultra-addons-for-wpforms.php' ) ) {
    
            add_action( 'admin_enqueue_scripts', array($this ,'ultrawpf_webhooks_admin_scripts') );
			add_action( 'wpforms_loaded', array($this, 'ultrawpf_webhooks_load') );
        }else{
            add_action( 'admin_notices', array($this, 'ultrawpf_webhooks_addon_required') );
        }
    }

    public function ultrawpf_webhooks_admin_scripts($screen) {

		// admin scripts goes here
        
    }

	public function ultrawpf_webhooks_load() {
		
		return Webhook::get_instance();
	}
    
    /*
    * Admin notice: Plugin installation error
    */
    public function ultrawpf_webhooks_addon_required() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
				<?php 
					echo esc_html__( 'Ultra Addons For WPForms Webhooks requires', 'ultrawpf-webhooks' );
					echo '<a href="https://themefic.com/plugins/ultrawpf/pro/" target="_blank"> Ultra Addons For WPForms Pro </a>';
					echo esc_html__( 'to be installed and active.', 'ultrawpf-webhooks' ); 
				?>
            </p>
        </div>
        <?php
    }
}

/**
 * Initializes the main plugin
 * @return \Ultra_Addons_For_WPForms_Webhooks
 */
function ultrawpf_webhooks() {

    return Ultra_Addons_For_WPForms_Webhooks::init();
}

// kick-off the plugin
ultrawpf_webhooks();