<?php
/**
 * Plugin Name: Yet Another Export Plugin
 * Description: Yet another export/import plugin for WordPress
 * Version: 1.0
 * Author: Krzysztof Grabania
 */

define('YAEP_DIR', plugin_dir_path(__FILE__));
define('YAEP_URL', plugin_dir_url(__FILE__));

class YAEP {
	public function __construct() {
		add_action('admin_menu', array($this, 'add_export_submenu_page'));
		add_action('wp_ajax_yaep_analyse_post_type', array($this, 'analyse_post_type_ajax'));
		add_filter('yaep_post_type_name', array($this, 'woocommerce_post_type_names'), 10, 2);
	}
	
	public function add_export_submenu_page() {
		add_submenu_page('tools.php', 'Yet Another Export', 'Yet Another Export', 'manage_options', 'yaep_export', array($this, 'export_submenu_callback'));
	}
	
	public function export_submenu_callback() {
		$post_types = get_post_types(array(), 'objects');
		
		$exclude = apply_filters('yaep_exclude_post_types', array('revision', 'customize_changeset'));
		
		foreach ($exclude as $slug) {
			unset($post_types[$slug]);
		}
		?>
		<div class="wrap">
			<h1>Yet Another Export</h1>
			<select id="type" name="type">
				<?php foreach ($post_types as $post_type): ?>
					<option value="<?php echo $post_type->name; ?>"><?php echo apply_filters('yaep_post_type_name', $post_type->label, $post_type); ?></option>
				<?php endforeach; ?>
			</select>
			<button id="analyse">Analyse</button>
		</div>
		<script>
			(function($) {
				$('#analyse').click(function() {
					$.get(
						ajaxurl,
						{
							action: 'yaep_analyse_post_type',
							post_type: $('#type').val(),
						},
						function(response) {
							console.log(response);
						}
					);
				});
			})(jQuery);
		</script>
	<?php }
	
	public function analyse_post_type_ajax() {
		$post_type = $_GET['post_type'];
		$posts = get_posts(array(
			'post_type' => $post_type,
			'post_status' => 'any',
			'posts_per_page' => -1,
			'fields' => 'ids',
		));
		
		$public_meta = array();
		// meta is considered as private if name begins with underscore "_"
		$private_meta = array();
		
		if (!empty($posts)) {
			// do direct call to db to increase speed of generating unique meta keys
			global $wpdb;
			
			$public_meta_query = $wpdb->prepare(
				'SELECT DISTINCT `meta_key` FROM `' . $wpdb->prefix . 'postmeta` WHERE `post_id` IN (%s) AND `meta_key` NOT LIKE "\_%%"',
				implode(',', $posts)
			);
			$private_meta_query = $wpdb->prepare(
				'SELECT DISTINCT `meta_key` FROM `' . $wpdb->prefix . 'postmeta` WHERE `post_id` IN (%s) AND `meta_key` LIKE "\_%%"',
				implode(',', $posts)
			);
			
			$public_meta = $wpdb->get_results($public_meta_query);
			$private_meta = $wpdb->get_results($private_meta_query);
		}
		
		$all_meta = array(
			'public' => wp_list_pluck($public_meta, 'meta_key'),
			'private' => wp_list_pluck($private_meta, 'meta_key'),
		);
	
		wp_send_json($all_meta);
		exit;
	}
	
	public function woocommerce_post_type_names($label, $post_type) {
		if (in_array($post_type->name, array('product', 'product_variation', 'shop_order', 'shop_order_refund', 'shop_coupon', 'shop_webhook'))) {
			return '[WooCommerce] ' . $label;
		}

		return $label;
	}
}

new YAEP();