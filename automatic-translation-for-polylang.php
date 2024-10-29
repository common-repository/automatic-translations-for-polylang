<?php
/*
Plugin Name: Automatic Translations For Polylang
Plugin URI: https://coolplugins.net/
Version: 1.0.3
Author: Cool Plugins
Author URI: https://coolplugins.net/
Description: Streamline your Polylang experience with this plugin that not only duplicates content but also translates core and specific blocks across multiple languages.
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: automatic-translations-for-polylang
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'ATFP_V' ) ) {
	define( 'ATFP_V', '1.0.3' );
}
if ( ! defined( 'ATFP_DIR_PATH' ) ) {
	define( 'ATFP_DIR_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'ATFP_URL' ) ) {
	define( 'ATFP_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'ATFP_FILE' ) ) {
	define( 'ATFP_FILE', __FILE__ );
}



if ( ! class_exists( 'Automatic_Translations_For_Polylang' ) ) {
	final class Automatic_Translations_For_Polylang {

		/**
		 * Plugin instance.
		 *
		 * @var Automatic_Translations_For_Polylang
		 * @access private
		 */
		private static $instance = null;

		/**
		 * Get plugin instance.
		 *
		 * @return Automatic_Translations_For_Polylang
		 * @static
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}
		/**
		 * Constructor
		 */
		private function __construct() {
			add_action( 'plugins_loaded', array( $this, 'atfp_init' ) );
			register_activation_hook( ATFP_FILE, array( $this, 'atfp_activate' ) );
			register_deactivation_hook( ATFP_FILE, array( $this, 'atfp_deactivate' ) );
		}
		/**
		 * Initialize the Automatic Translation for Polylang plugin.
		 *
		 * @return void
		 */
		function atfp_init() {
			require_once ATFP_DIR_PATH . '/helper/class-atfp-ajax-handler.php';

			if ( class_exists( 'ATFP_Ajax_Handler' ) ) {
				ATFP_Ajax_Handler::get_instance();
			}

			// Check Polylang plugin is installed and active
			global $polylang;
			$atfp_polylang = $polylang;
			if ( isset( $atfp_polylang ) ) {
				add_action( 'add_meta_boxes', array( $this, 'atfp_shortcode_metabox' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'atfp_register_backend_assets' ) ); // registers js and css for frontend
			} else {
				add_action( 'admin_notices', array( self::$instance, 'atfp_plugin_required_admin_notice' ) );
			}

			if ( is_admin() ) {
				require_once ATFP_DIR_PATH . 'admin/feedback/atfp-users-feedback.php';
			}

			load_plugin_textdomain( 'automatic-translations-for-polylang', false, basename( dirname( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Display admin notice for required plugin activation.
		 *
		 * @return void
		 */
		function atfp_plugin_required_admin_notice() {
			if ( current_user_can( 'activate_plugins' ) ) {
				$url         = 'plugin-install.php?tab=plugin-information&plugin=polylang&TB_iframe=true';
				$title       = 'Polylang';
				$plugin_info = get_plugin_data( __FILE__, true, true );
				echo '<div class="error"><p>' .
				sprintf(
					// translators: 1: Plugin Name, 2: Plugin URL
					esc_html__(
						'In order to use %1$s plugin, please install and activate the latest version  of %2$s',
						'automatic-translations-for-polylang'
					),
					wp_kses( '<strong>' . esc_html( $plugin_info['Name'] ) . '</strong>', 'strong' ),
					wp_kses( '<a href="' . esc_url( $url ) . '" class="thickbox" title="' . esc_attr( $title ) . '">' . esc_html( $title ) . '</a>', 'a' )
				) . '.</p></div>';
			}
		}

		/**
		 * Register backend assets for Automatic Translation for Polylang plugin.
		 *
		 * @return void
		 */
		function atfp_register_backend_assets() {
			$current_screen = get_current_screen();

			if ( isset( $_GET['from_post'], $_GET['new_lang'], $_GET['_wpnonce'] ) &&
				 wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'new-post-translation' ) ) {
				if ( method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor() ) {
					$from_post_id = isset( $_GET['from_post'] ) ? absint( $_GET['from_post'] ) : 0;

					global $post;

					if ( null === $post || 0 === $from_post_id ) {
						return;
					}

					$editor = '';
					if ( 'builder' === get_post_meta( $from_post_id, '_elementor_edit_mode', true ) ) {
						$editor = 'Elementor';
					}
					if ( 'on' === get_post_meta( $from_post_id, '_et_pb_use_builder', true ) ) {
						$editor = 'Divi';
					}

					if ( in_array( $editor, array( 'Elementor', 'Divi' ), true ) ) {
						return;
					}

					$languages = PLL()->model->get_languages_list();

					$lang_object = array();
					foreach ( $languages as $lang ) {
						$lang_object[ $lang->slug ] = $lang->name;
					}

					$post_translate = PLL()->model->is_translated_post_type( $post->post_type );
					$lang           = isset( $_GET['new_lang'] ) ? sanitize_key( $_GET['new_lang'] ) : '';
					$post_type      = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';

					if ( $post_translate && $lang && $post_type ) {
						wp_register_style( 'atfp-automatic-translate', ATFP_URL . 'assets/css/atfp-custom.min.css', array(), ATFP_V );

						$editor_script_asset = require_once ATFP_DIR_PATH . 'assets/build/index.asset.php';
						wp_register_script( 'atfp-automatic-translate', ATFP_URL . 'assets/build/index.js', $editor_script_asset['dependencies'], $editor_script_asset['version'], true );

						wp_enqueue_style( 'atfp-automatic-translate' );
						wp_enqueue_script( 'atfp-automatic-translate' );
						wp_localize_script(
							'atfp-automatic-translate',
							'atfp_ajax_object',
							array(
								'ajax_url'           => admin_url( 'admin-ajax.php' ),
								'ajax_nonce'         => wp_create_nonce( 'atfp_translate_nonce' ),
								'atfp_url'           => esc_url( ATFP_URL ),
								'action_fetch'       => 'fetch_post_content',
								'action_block_rules' => 'block_parsing_rules',
								'source_lang'        => pll_get_post_language( $from_post_id, 'slug' ),
								'languageObject'     => $lang_object,
							)
						);
					}
				}
			}
		}

		/**
		 * Register and display the automatic translation metabox.
		 */
		function atfp_shortcode_metabox() {
			if ( isset( $_GET['from_post'], $_GET['new_lang'], $_GET['_wpnonce'] ) &&
				 wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'new-post-translation' ) ) {
				$post_id = isset( $_GET['from_post'] ) ? absint( $_GET['from_post'] ) : 0;

				if ( 0 === $post_id ) {
					return;
				}

				$editor = '';
				if ( 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
					$editor = 'Elementor';
				}
				if ( 'on' === get_post_meta( $post_id, '_et_pb_use_builder', true ) ) {
					$editor = 'Divi';
				}

				$current_screen = get_current_screen();
				if ( method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor() && ! in_array( $editor, array( 'Elementor', 'Divi' ), true ) ) {
					if ( 'post-new.php' === $GLOBALS['pagenow'] && isset( $_GET['from_post'], $_GET['new_lang'] ) ) {
						global $post;

						if ( ! ( $post instanceof WP_Post ) ) {
							return;
						}

						if ( ! function_exists( 'PLL' ) || ! PLL()->model->is_translated_post_type( $post->post_type ) ) {
							return;
						}
						add_meta_box( 'atfp-meta-box', __( 'Automatic Translate', 'automatic-translations-for-polylang' ), array( $this, 'atfp_shortcode_text' ), null, 'side', 'high' );
					}
				}
			}
		}

		/**
		 * Display the automatic translation metabox button.
		 */
		function atfp_shortcode_text() {
			if ( isset( $_GET['_wpnonce'] ) &&
				 wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'new-post-translation' ) ) {
				$target_language = '';
				if ( function_exists( 'PLL' ) ) {
					$target_code = isset( $_GET['new_lang'] ) ? sanitize_key( $_GET['new_lang'] ) : '';
					$languages   = PLL()->model->get_languages_list();
					foreach ( $languages as $lang ) {
						if ( $lang->slug === $target_code ) {
							$target_language = $lang->name;
						}
					}
				}
				?>
				<input type="button" class="button button-primary" name="atfp_meta_box_translate" id="atfp-translate-button" value="<?php echo esc_attr__( 'Translate Content (Beta)', 'automatic-translations-for-polylang' ); ?>" readonly/><br><br>
				<p style="margin-bottom: .5rem;"><?php echo esc_html( sprintf( __( 'Translate or duplicate content from Hindi to %s', 'automatic-translations-for-polylang' ), $target_language ) ); ?></p>
				<hr>
				<div class="atfp-review-meta-box">
					<p><?php echo esc_html__( 'We hope you liked our plugin created timelines. Please share your valuable feedback.', 'automatic-translations-for-polylang' ); ?>
					<br>
					<a href="<?php echo esc_url( 'https://wordpress.org/plugins/automatic-translations-for-polylang/reviews/#new-post' ); ?>" class="components-button is-primary is-small" target="_blank"><?php echo esc_html__( 'Rate Us', 'automatic-translations-for-polylang' ); ?><span> ★★★★★</span></a>
					</p>
				</div>
				<?php
			}
		}

		/*
		|----------------------------------------------------------------------------
		| Run when activate plugin.
		|----------------------------------------------------------------------------
		*/
		public static function atfp_activate() {
			update_option( 'atfp-v', ATFP_V );
			update_option( 'atfp-type', 'FREE' );
			update_option( 'atfp-installDate', gmdate( 'Y-m-d h:i:s' ) );
		}

		/*
		|----------------------------------------------------------------------------
		| Run when de-activate plugin.
		|----------------------------------------------------------------------------
		*/
		public static function atfp_deactivate() {
		}

	}

}

function Automatic_Translations_For_Polylang() {
	return Automatic_Translations_For_Polylang::get_instance();
}

$Automatic_Translations_For_Polylang = Automatic_Translations_For_Polylang();
