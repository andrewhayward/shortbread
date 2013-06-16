<?php

/*
Plugin Name: Shortbread
Plugin URI: http://labs.andrewhayward.net/wordpress/shortbread
Description: Adds secondary short URLs to content for those with a second shorter domain
Author: Andrew Hayward
Author URI: http://andrewhayward.net
Version: 1.0
*/


require_once(dirname(__FILE__).'/baseconverter.php');


class Shortbread {

	static $_defaults = array(
		'url' => array('url', '', 'Short URL domain'),
		'ga_tid' => array('string', '', 'Google Analytics ID', array(
			'placeholder' => 'UA-XXXX-Y'
		)),
		'redirect_links' => array('boolean', false, 'Redirect links'),
		'force_links_menu' => array('boolean', false, 'Force Links menu'),
	);

	static $_domain = 'shortbread';

	public function __construct ($converter) {
		$this->_converter = $converter;

		register_activation_hook(__FILE__, array(__CLASS__, 'install')); 
		register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall')); 
		add_action('init', array($this, 'init'));
		add_action('admin_init', array($this, 'admin_init'));
		add_action('admin_menu', array($this, 'admin_menu'));
	}

	public function __isset ($key) {
		return isset(self::$_defaults[$key]);
	}

	public function __get ($key) {
		if (isset($this->$key)) {
			$options = (array) get_option(self::$_domain.'_options', 'cheese');
			return isset($options[$key]) ? $options[$key] : self::$_defaults[$key][1];
		}
		return null;
	}

	public function __call ($method, $options) {
		@list($_, $input) = explode('input_', $method);
		$output = 'Field <code>'.htmlentities($input).'</code> not found.';

		if (!empty($input) && isset(self::$_defaults[$input])) {
			$type = self::$_defaults[$input][0];
			switch ($type) {
				case 'boolean':
					$options = array_merge(array(
						'value' => 1
					), (array) $option);
					if ($this->$input) $options['checked'] = 'checked';
					$output = $this->create_input($input, $options, 'checkbox');
					break;
				default:
					$output = $this->create_input($input, (array) $options);
					break;
			}

		}

		echo $output;
	}

	static function install () {
		update_option(self::$_domain.'_options', array());
	}

	static function uninstall () {
		delete_option(self::$_domain.'_options');
	}

	function init () {
		$base = $this->url;

		if (!empty($base)) {
			add_filter('pre_get_shortlink', array($this, '_get_shortlink'), 0, 4);
			add_action('template_redirect', array($this, 'handle_request'), 0);

			if ($this->redirect_links) {
				add_filter('manage_link-manager_columns', array($this, '_update_link_table_columns'));
				add_filter('manage_link_custom_column', array($this, '_update_link_table_row'), 10, 2);
			}
		}

		if ($this->force_links_menu)
			add_filter('pre_option_link_manager_enabled', '__return_true');
	}

	private function get_object ($type, $id) {
		if (is_callable('get_'.$type)) {
			$object = call_user_func('get_'.$type, $id);
			if (!is_wp_error($object)) return $object;
		}
		return null;
	}

	function _get_shortlink ($shortlink=null, $id=null, $context=null, $allow_slugs=null) {
		global $wp_query;
		if ($context !== 'query' && !is_404()) {
			if ($context == 'category' || is_category()) {
				return $this->get_short_url('category', $id ? $id : $wp_query->queried_object->term_id);
			} else if ($context == 'tag' || is_tag()) {
				return $this->get_short_url('tag', $id ? $id : $wp_query->queried_object->term_id);
			} else if ($context == 'attachment' || is_attachment()) {
				return $this->get_short_url('attachment', $id ? $id : $wp_query->post->ID);
			} else if ($context == 'page' || is_page()) {
				return $this->get_short_url('page', $id ? $id : $wp_query->post->ID);
			} else if ($context == 'post' || is_single()) {
				return $this->get_short_url('post', $id ? $id : $wp_query->post->ID);
			} else {
				return $shortlink;
			}
		}
	}

	function _update_link_table_columns ($column) {
	    $column['short_url'] = 'Short URL';

	    return $column;
	}

	function _update_link_table_row ($column, $id) {
		if ($column == 'short_url') {
			$link = $this->get_object('bookmark',$id);
			if ($link->link_visible == 'Y') {
				$url = $this->get_short_url('link', $id);
				$display = (strpos($url, 'http://') === 0) ? substr($url, 7) : $url;
				echo '<a href="'.$url.'">'.$display.'</a>';
			}
		}
	}

	static function is_linkable ($context=null) {
		if (empty($context)) {
			if (is_attachment()) {
				$context = 'attachment';
			} else if (is_page()) {
				$context = 'page';
			} else if (is_single()) {
				$context = 'post';
			} else if (is_category()) {
				$context = 'category';
			} else if (is_tag()) {
				$context = 'tag';
			}
		}

		if (!empty($context)) {
			switch ($context) {
				case 'post':
				case 'page':
				case 'attachment':
				case 'category':
				case 'tag':
				case 'link':
					return true;
				default:
					return !!apply_filters('short_url_context', $context);;
			}
		}

		return false;
	}

	function get_short_url ($context, $id) {
		$type = null;

		switch ($context) {
			case 'post':
			case 'page':
			case 'attachment':
				$type = 'p';
				break;
			case 'category':
				$type = 'c';
				break;
			case 'tag':
				$type = 't';
				break;
			case 'link':
				$type = 'l';
				break;
			default:
				// unknown context - ignore it
		}

		$type = apply_filters('short_url_context', $type, $context);

		if ($type) {
			$code = $this->_converter->from_decimal($id);
			return implode('', array(rtrim($this->url, '/'), '/', substr($type, 0, 1), $code));
		}
	}

	function _send_analytics ($type='pageview', $options=array()) {
		$tid = $this->ga_tid;

		if (empty($tid))
			return false;

		switch ($type) {
			case 'pageview':
				$config = array(
					'dh' => $_SERVER['HTTP_HOST'],
					'dp' => $_SERVER['REQUEST_URI'],
					'dt' => '',
					'cd' => ''
				);
				break;
			case 'exception':
				$config = array(
					'exd' => 'Exception',
					'exf' => 0
				);
			default:
				// TO DO - add more analytics types
				return false;
		}

		foreach ($config as $key => $value) {
			if (isset($options[$key]))
				$config[$key] = $options[$key];
			if (empty($config[$key]) && $config[$key] != 0)
				unset($config[$key]);
		}

		$session_id = isset($_COOKIE['__cid']) ? $_COOKIE['__cid'] : sha1($_SERVER['REMOTE_ADDR']);
		@setCookie('__cid', $session_id, time()+60*60*24*7, '/', $_SERVER['HTTP_HOST'], false, true);

		$analytics = array_merge($config, array(
			'v' => 1,
			'tid' => $this->ga_tid,
			'cid' => $session_id,
			't' => $type
		));

		if (!empty($_SERVER['HTTP_REFERER']))
			$analytics['dr'] = $_SERVER['HTTP_REFERER'];

		$apiCall = curl_init('http://www.google-analytics.com/collect');
		curl_setopt_array($apiCall, array(
			CURLOPT_POST => True,
			CURLOPT_POSTFIELDS => http_build_query($analytics)
		));
		$status = @curl_exec($apiCall) && @curl_getinfo($apiCall, CURLINFO_HTTP_CODE);
		curl_close($apiCall);

		return ($status && ($status < 300));
	}

	function handle_request ($requested_url=null) {
		if ( !$requested_url ) {
			// build the URL in the address bar
			$requested_url  = is_ssl() ? 'https://' : 'http://';
			$requested_url .= $_SERVER['HTTP_HOST'];
			$requested_url .= $_SERVER['REQUEST_URI'];
		}

		$original = @parse_url($requested_url);

		if ($original !== false) {
			$base = rtrim($this->url, '/') . '/';

			if (strpos($requested_url, $base) === 0) {
				$request = substr($requested_url, strlen($base));

				if (empty($request)) {
					$this->_send_analytics();
					wp_redirect(home_url(), 301);
					exit;
				}

				$type = $request[0];
				$id = $this->_converter->to_decimal(substr($request, 1));
				$redirect = false;

				$analytics = array();

				switch ($type) {
					case 'p': // posts, pages and attachments
						if (($post = $this->get_object('post',$id)) && ($post->post_type !== 'revision')) {
							$analytics['dt'] = $post->post_title;
							$redirect = get_permalink($id);
						}
						break;
					case 'c': // categories
						if ($category = $this->get_object('category',$id)) {
							$analytics['dt'] = $category->name;
							$redirect = get_category_link($id);
						}
						break;
					case 't': // tags
						if ($tag = $this->get_object('tag',$id)) {
							$analytics['dt'] = $tag->name;
							$redirect = get_tag_link($id);
						}
						break;
					case 'l': // links
						if ($this->redirect_links) {
							if ($link = $this->get_object('bookmark',$id)) {
								if ($link->link_visible == 'Y') {
									if ($link->link_name)
										$analytics['dt'] = $link->link_name;
									$redirect = $link->link_url;
								}
							}
						}
						break;
					default:
						// unknown type - ignore it
				}

				$redirect = apply_filters('short_url_redirect', $redirect, $type, $id);

				if ($redirect) {
					$analytics['cd'] = $redirect;
					$this->_send_analytics('pageview', $analytics);
					wp_redirect($redirect, 301);
					exit;
				} else {
					global $wp_query;
					$wp_query->set_404();
				}
			}
		}
	}

	function admin_init () {
		$page = self::$_domain; //'general'; //'permalink';
		$section = self::$_domain.'_section';

		add_settings_section($section, 'Options', array($this, 'admin_description'), $page); 

		foreach (self::$_defaults as $field => $config) {
			$id = self::$_domain.'_'.$field;
			$label = '<label for="'.$id.'">'.__($config[2], self::$_domain).'</label>';
			$options = isset($config[3]) ? (array) $config[3] : Null;
			add_settings_field($id, $label, array($this, 'input_'.$field), $page, $section, $options);
		}

		register_setting($page, self::$_domain.'_options', array($this, 'validate'));

		if ($this->redirect_links) {
			add_meta_box(
				self::$_domain.'_link_meta_box',
				__('Short URL', self::$_domain),
				array($this, 'admin_link_box'),
				'link',
				'side',
				'low'
			);
		}
	}

	function admin_menu () {
		add_options_page('Short URL Configuration', 'Short URLs', 'edit_plugins', self::$_domain, array($this, 'admin_layout'));
	}

	function admin_layout () {
		?>
			<div class="wrap">
				<div class="icon32" id="icon-link-manager"><br></div>
				<h2>Short URL Configuration</h2>
				<p>You would think this would fit quite nicely in the <a href="options-permalink.php">Permalinks</a> admin screen, but <a href="http://core.trac.wordpress.org/ticket/9296">a bug</a> makes that nigh on impossible.</p>
				<form action="options.php" method="post">
					<?php settings_fields(self::$_domain); ?>
					<?php do_settings_sections(self::$_domain); ?>
					<p class="submit">
						<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
					</p>
				</form>
			</div>
		<?php
	}

	function admin_description () {
		// No description
	}

	function admin_link_box ($link) {
		if ($link->link_id) {
			$url = $this->get_short_url('link', $link->link_id);
			echo '<input type="text" value="'.$url.'" readonly="readonly" style="width: 100%;">';
?><script>
(function($) {
	var $box = $('#<?php echo self::$_domain; ?>_link_meta_box'),
	    $toggle = $('#link_private');

	if ($box && $toggle) {
		var refresh = function () {
			$box[$toggle.is(':checked')?'hide':'show']();
		}

		refresh();
		$toggle.change(refresh);
	}
})(jQuery);
</script><?php
		} else {
			echo '<p>Short URL will be generated on creation.</p>';
		}
	}

	protected function create_input ($key, $attributes=array(), $type='text', $description='') {
		$attributes = array_merge(
			array('name' => self::$_domain.'_options['.$key.']', 'value' => $this->$key,
				'type' => $type, 'id' => self::$_domain.'_'.$key),
			(array) $attributes
		);

		$field = array('<input');
		foreach ($attributes as $attribute => $value) {
			$field[] = ' '.esc_attr($attribute).'="'.esc_attr($value).'"';
		}
		$field[] = '> '.$description;

		return implode('', $field);
	}

	function input_url ($options = array()) {
		$options = array_merge(array(
			'placeholder'=>'http://exm.pl',
			'class'=>'regular-text code'
		), (array) $options);

		echo $this->create_input('url', $options, 'url');
	}

	function validate ($options) {
		$valid = array();

		foreach (self::$_defaults as $option => $config) {
			list($type, $default, $label) = $config;
			switch ($type) {
				case 'array':
					$valid[$option] = isset($options[$option]) ? array_map('esc_attr', $options[$option]) : $default;
					break;
				case 'string':
					$valid[$option] = isset($options[$option]) ? esc_attr($options[$option]) : $default;
					break;
				case 'boolean':
					$valid[$option] = isset($options[$option]) ? (bool) $options[$option] : $default;
					break;
				default:
					$callable = array(self, 'validate_'.$type);
					if (is_callable($callable)) {
						try {
							$valid[$option] = isset($options[$option]) ? call_user_func($callable, $options[$option]) : $default;
						} catch (Exception $e) {
							$valid[$option] = $this->$option;
							add_settings_error(
								self::$_domain.'_'.$option,
								self::$_domain.'_'.$option.'_error',
								$e->getMessage(),
								'error'
							);
						}
					}
			}
		}

		return $valid;
	}

	static function validate_url ($url) {
		$schemes = array('', 'http', 'https');
		$url = trim($url);
		if (!empty($url)) {
			$parts = parse_url($url);
			if ($parts === false) {
				throw new Exception('Invalid URL');
			} else if (!in_array(@$parts['scheme'], $schemes)) {
				throw new Exception('URL scheme <code>'.$parts['scheme'].'</code> invalid - must be <code>http(s)</code>');
			} else {
				$scheme = @$parts['scheme'];
				if (empty($scheme))
					$url = 'http://'.$url;
				if (rtrim($url,'/') == rtrim(get_site_url(),'/')) {
					// Results in an infinite loop of redirects if you do this
					throw new Exception('You really don\'t want to set your short URL to be the same as your site URL!');
				}
				$url = esc_url_raw($url, $schemes);
			}
		}
		return $url;
	}

}

function has_shortlink ($context=null) {
	if (is_404()) return false;
	return Shortbread::is_linkable($context);
}

$converter = new BaseConverter('abcdefghijklmnopqrstuvwxyz0123456789');
$shortbread = new Shortbread($converter);
