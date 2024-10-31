<?php
/**
 * @package ObfuscaTOR
 * @author Ryan Day
 * @version 1.2
 */
/*
Plugin Name: ObfuscaTOR
Plugin URI: http://www.ryanday.net/
Description: Retreive the bridge list from bridges.torproject.org or an RSS feed and provide the info in an obfuscated image
Author: Ryan Day
Version: 1.2
Author URI: http://www.ryanday.net/
*/

require_once('ObfuscaTOR/CaptchaFactory.php');

if( !class_exists('ObfuscaTOR') ) {
   class ObfuscaTOR {
	private $options;

	public function __construct() {}

	public function init() {
		$this->options = get_option('obfuscaTOR_opts');

		/* If this is the first run, set the initial cache_timeout to be 
		  over 1 day ago, so we are sure to query the site instead of using cache */
		if( !$this->options ) {
			$opts = array('cache_timeout'=>time()-86401,		// Last time cache was refreshed
					'cache_keepalive'=>86400,		// How often to refresh cache
					'image_timeout'=>time()-3601,		// Last time image was re-created
					'image_keepalive'=>3600,		// How often to re-create image
					'bridge_info'=>'',			// The ip:port of bridges
					'image_x'=>'300',			// Width
					'image_y'=>'100',			// Height
					'tempfile'=>'',				// Name of our image on the filesystem
					'hook'=>'widget');			// Function to hook for image display
			add_option('obfuscaTOR_opts', $opts);
			$this->options = $opts;
		}
	}

	public function cleanup() {
		delete_option('obfuscaTOR_opts');
	}

	public function getOptions() {
		if( !$this->options ) $this->init();
		return $this->options;
	}

	public function admin_page() {
		if( isset($_POST['update_ObfuscaTOR']) ) {
			if( isset($_POST['ObfuscaTOR_hook']) ) 
				$this->options['hook'] = $_POST['ObfuscaTOR_hook'];
			if( isset($_POST['ObfuscaTOR_cache_keepalive']) ) 
				$this->options['cache_keepalive'] = $_POST['ObfuscaTOR_cache_keepalive'];
			if( isset($_POST['ObfuscaTOR_image_keepalive']) )
				$this->options['image_keepalive'] = $_POST['ObfuscaTOR_image_keepalive'];
			if( isset($_POST['ObfuscaTOR_image_x']) )
				$this->options['image_x'] = $_POST['ObfuscaTOR_image_x'];
			if( isset($_POST['ObfuscaTOR_image_y']) )
				$this->options['image_y'] = $_POST['ObfuscaTOR_image_y'];
			if( isset($_POST['ObfuscaTOR_changeimage']) )
				$this->options['image_timeout'] -= $this->options['image_keepalive'];
			update_option('obfuscaTOR_opts', $this->options);
			?>
			<div class="updated"><p><strong><?php _e("Settings Updated.", "ObfuscaTOR");?></strong></p></div>
			<?php
		}
		?>
		<div class=wrap>
		<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
		<h2>ObfuscaTOR</h2>
		<h3>Wordpress area to place image</h3>
		<select name="ObfuscaTOR_hook">
			<option value="wp_list_categories" 
				<?php if( $this->options['hook'] == "wp_list_categories" ) echo "SELECTED"; ?>>
				Categories Area</option>
			<option value="wp_meta"
				<?php if( $this->options['hook'] == "wp_meta" ) echo "SELECTED"; ?>>
				Meta Area</option>
			<option value="widget"
				<?php if( $this->options['hook'] == "widget" ) echo "SELECTED"; ?>>
				As A Widget</option>
		</select>
		<h3>How Often To Check For New Bridge Information</h3>
		<select name="ObfuscaTOR_cache_keepalive">
			<option value="43200"
				<?php if( $this->options['cache_keepalive'] == 43200 ) echo "SELECTED"; ?>>
				6 Hours</option>
			<option value="86400"
				<?php if( $this->options['cache_keepalive'] == 86400 ) echo "SELECTED"; ?>>
				1 Day</option>
			<option value="172800"
				<?php if( $this->options['cache_keepalive'] == 172800 ) echo "SELECTED"; ?>>
				2 Days</option>
		</select>
		<h3>How Often To Recreate The Image</h3>
		<select name="ObfuscaTOR_image_keepalive">
			<option value="1800"
				<?php if( $this->options['image_keepalive'] == 1800 ) echo "SELECTED"; ?>>
				30 Minutes</option>
			<option value="3600"
				<?php if( $this->options['image_keepalive'] == 3600 ) echo "SELECTED"; ?>>
				1 Hour</option>
			<option value="7200"
				<?php if( $this->options['image_keepalive'] == 7200 ) echo  "SELECTED"; ?>>
				2 Hours</option>
		</select><input type="checkbox" name="ObfuscaTOR_changeimage">Recreate Now
		<h3>Configure Image Size</h3>
		Image Width: <input name="ObfuscaTOR_image_x" value="<?php echo $this->options['image_x'];?>"><br>
		Image Height: <input name="ObfuscaTOR_image_y" value="<?php echo $this->options['image_y'];?>"><br>
		<input type="submit" name="update_ObfuscaTOR" value="<?php _e('Update Settings', 'ObfuscaTOR') ?>">
		<?php
	}

	public function GetImage_shortcode($atts, $content = null) {
		extract( shortcode_atts( array(
			'width' => $this->options['image_x'],
			'height' => $this->options['image_y'],
			'align' => 'middle',
			), $atts ) );

		$output = $this->GetTorImage();

		// We always want to use what GetImage returns so we don't have any races and get broken images
		if( preg_match('/src=\'(.*?)\'/', $output, $match) > 0 ) {
			$file = $match[1];
			$output = "<img align='{$align}' width='{$width}' height='{$height}' src='$file'>";
		}
		
		return $output;
	}

	public function GetRssImage($rss, $width=0, $height=0) {
		// Check if we have to recreate the image
		$pathArray = wp_upload_dir();
		if( empty($pathArray) ) {
			echo "Please configure upload directory";
			return;
		}

		$baseurl = $pathArray['baseurl'];
		$basedir = $pathArray['basedir'];
		if( $width == 0 )
			$width = $this->options['image_x'];
		if( $height == 0 )
			$height = $this->options['image_y'];

		$r = rand(1,2);		// Letter captcha is still a little iffy

		// Grab random captcha class
		$captcha = CaptchaFactory::GetCaptcha($r);

		// Set some properties
		$captcha->SetHeight($height);
		$captcha->SetWidth($width);

		// Set our text to the bridge information, we use bridges.torproject.org as our source
		$captcha->SetText($this->getFeed($rss));

		$filename = tempnam($basedir, 'obfu');
		unlink($filename);	// we don't want that created file
		$urlname = basename($filename);
		$fp = fopen($filename.'.jpg', 'w');
		$image = $captcha->CreateJPEG();
		fwrite($fp, $image);
		fclose($fp);

		// If we get hooked to the header, this echoes correctly. It also works when used in a sidebar menu
		$return .= "<img align='middle' src='{$baseurl}/{$urlname}.jpg' width='{$width}' height='{$height}'>";

		return $return;
	}

	public function GetTorImage($input="", $width=0, $height=0) {
		// Check if we have to recreate the image
		$pathArray = wp_upload_dir();
		if( empty($pathArray) ) {
			echo "Please configure upload directory";
			return;
		}

		$baseurl = $pathArray['baseurl'];
		$basedir = $pathArray['basedir'];
		if( $width == 0 )
			$width = $this->options['image_x'];
		if( $height == 0 )
			$height = $this->options['image_y'];

		if( time() - $this->options['image_keepalive'] < $this->options['image_timeout'] ) {
			$input .= "<img align='middle' src='{$baseurl}/{$this->options['tempfile']}.jpg' width='{$width}' height='{$height}'>";
			return $input;
		}

		$this->options['image_timeout'] = time();

		//1: Wave Captcha
		//2: Line Captcha
		//3: Letter Captcha
		$r = rand(1,2);		// Letter captcha is still a little iffy

		// Grab random captcha class
		$captcha = CaptchaFactory::GetCaptcha($r);

		// Set some properties
		$captcha->SetHeight($height);
		$captcha->SetWidth($width);

		// Set our text to the bridge information, we use bridges.torproject.org as our source
		$captcha->SetText($this->getBridges());

		$filename = tempnam($basedir, 'obfu');
		unlink($filename);	// we don't want that created file
		$urlname = basename($filename);
		$fp = fopen($filename.'.jpg', 'w');
		$image = $captcha->CreateJPEG();
		fwrite($fp, $image);
		fclose($fp);

		// If we get hooked to the header, this echoes correctly. It also works when used in a sidebar menu
		$input .= "<img align='middle' src='{$baseurl}/{$urlname}.jpg' width='{$width}' height='{$height}'>";

		// Remove the previous image
		if( isset($this->options['tempfile']) && file_exists($basedir . '/' . $this->options['tempfile'] . '.jpg') )
			unlink($basedir . '/' . $this->options['tempfile'] . '.jpg');

		// This is the main method, so we always update our options here, not in any sub methods
		$this->options['tempfile'] = $urlname;
		update_option("obfuscaTOR_opts", $this->options);

		return $input;
	}
	/**
	 * This is a very basic way to cache bridge information from the Tor Project site.
	 * The Tor site will only give an IP address new bridge information every day or so,
	 * this means there is no need to hit the site more often then that.
	 */
	private function getBridges() {
		$expire = $this->options['cache_timeout'];

		// If our cache is older then the user wants, query the site
		if( (time() - $this->options['cache_keepalive']) > $this->options['cache_timeout'] ) {
		        $filestore = file_get_contents('https://bridges.torproject.org/');
		        preg_match_all('/^bridge (.*?)$/m', $filestore, $match);
	        	$text = "";
		        foreach($match[1] as $val) $text .= $val . "\n";
			$this->options['cache_timeout'] = time();
			$this->options['bridge_info'] = $text;
		} else {
			$text = $this->options['bridge_info'];
		}

	        return $text;
	}

	private function getFeed($rss) {
		include_once(ABSPATH . WPINC . '/rss.php');
		$feed = fetch_rss($rss);
		$text = "";
		foreach($feed->items as $item) {
			$text .= $item['description'] . "\n";
		}
		return $text;
	}
   }
}

if( !class_exists('ObfuscaTOR_widget') ) {
	class ObfuscaTOR_widget extends WP_Widget {
		public function ObfuscaTOR_widget() {
			// Yea I know, but I'm moving everything into the widget code and don't know
			// exactly how multiple instances of the whole captch libraries will work
			// performance wise, so this way we only have one instance of the libraries
			// while still having multiple widgets.
			global $obfuscaTOR;
			$this->ObfuscaTOR = $obfuscaTOR;

			$widget_ops = array('classname' => 'ObfuscaTOR_widget', 
						'description' => __( "Obfuscate Tor bridge info") );
			$control_ops = array('height' => 300);
			$this->WP_Widget('ObfuscaTOR', __('ObfuscaTOR'), $widget_ops, $control_ops);
		}

	        public function widget($args, $instance) {
        	        extract($args);
			$title = empty($instance['title']) ? '&nbsp;' : apply_filters('widget_title', $instance['title']);
			$width = empty($instance['width']) ? '&nbsp;' : apply_filters('widget_width', $instance['width']);
			$height = empty($instance['height']) ? '&nbsp;' : apply_filters('widget_height', $instance['height']);
			$source = empty($instance['source']) ? '&nbsp;' : apply_filters('widget_source', $instance['source']);
			$rss = empty($instance['rss']) ? '&nbsp;' : apply_filters('widget_rss', $instance['rss']);

                	echo $before_widget;
                	if( !empty($title) ) {
                        	echo $before_title;
                        	echo $title;
                        	echo $after_title;
                	}
			if( $source == "rss" )
                		echo $this->ObfuscaTOR->GetRssImage($rss, $width, $height);
			else
                		echo $this->ObfuscaTOR->GetTorImage("", $width, $height);
				
                	echo $after_widget;
        	}

		public function update($nInstance, $oInstance) {
			$instance = $oInstance;
			$instance['title'] = strip_tags(stripslashes($nInstance['title']));
			$instance['width'] = strip_tags(stripslashes($nInstance['width']));
			$instance['height'] = strip_tags(stripslashes($nInstance['height']));
			$instance['source'] = strip_tags(stripslashes($nInstance['source']));
			$instance['rss'] = strip_tags(stripslashes($nInstance['rss']));

			return $instance;
		}

		public function form($instance) {
			$instance = wp_parse_args( (array)$instance, array('title'=>'','width'=>'','height'=>'') );
			$title = strip_tags($instance['title']);
			$source = strip_tags($instance['source']);
			$rss = strip_tags($instance['rss']);
			$width = strip_tags($instance['width']);
			$height = strip_tags($instance['height']);

			# Output the options
    			echo '<p style="text-align:left;"><label for="'.$this->get_field_name('title').'">' . __('Title:') . 
				' <input style="width: 200px;" id="' . $this->get_field_id('title') . '"' .
				' name="' . $this->get_field_name('title') . '" type="text" value="' . $title . '" />'.
				'</label>' .
				'<label for="'.$this->get_field_name('width').'">'. __('Width:').
				' <input style="width: 200px;" id="'.$this->get_field_id('width') . '"' .
				' name="'. $this->get_field_name('width'). '" type="text" value="' . $width . '" />'.
				'</label>' .
				'<label for="'.$this->get_field_name('height').'">'. __('Height:').
				' <input style="width: 200px;" id="'.$this->get_field_id('height') . '"' .
				' name="'. $this->get_field_name('height'). '" type="text" value="' . $height . '" />'.
				'</label>'.
				'<label for="'.$this->get_field_name('source').'">'. __('Source:').
				' <select name="'. $this->get_field_name('source'). '">'.
				'	<option value="tor" '; if( $source == 'tor' ) echo 'selected'; echo '>Tor Bridges</option>'.
				'	<option value="rss" '; if( $source == 'rss' ) echo 'selected'; echo '>RSS Feed</option>'.
				' </select>'.
				' <input style="width: 200px;" id="'.$this->get_field_id('rss') . '"' .
				' name="'. $this->get_field_name('rss'). '" type="text" value="' . $rss . '" />'.
				'</label>';
		}

	}
}

if( class_exists('ObfuscaTOR') ) {
	$obfuscaTOR = new ObfuscaTOR();
}

if( !function_exists('ObfuscaTOR_admin') ) {
	function ObfuscaTOR_admin() {
		global $obfuscaTOR;
		
		if( !isset($obfuscaTOR) ) return;
		if( function_exists('add_options_page') ) {
			add_options_page('ObfuscaTOR', 'ObfuscaTOR', 9, basename(__FILE__), array(&$obfuscaTOR, 'admin_page'));
		}
	}
}

if( !function_exists('ObfuscaTOR_addwidget') ) {
	function ObfuscaTOR_addwidget() {
		global $obfuscaTOR;
		//register_sidebar_widget('obfuscaTOR', array(&$obfuscaTOR, 'GetImage_widget'));
		register_widget('ObfuscaTOR_widget');
	}
}

if( isset($obfuscaTOR) ) {
	$opts = $obfuscaTOR->getOptions();
	if( $opts['hook'] == "widget" )
		add_action('widgets_init', 'ObfuscaTOR_addwidget');
	else 
		add_action($opts['hook'], array(&$obfuscaTOR, 'GetTorImage'));

	add_action('admin_menu', 'ObfuscaTOR_admin');
	add_shortcode('obfuscaTOR', array(&$obfuscaTOR, 'GetImage_shortcode'));
	register_activation_hook( __FILE__, array(&$obfuscaTOR, 'init') );
	register_deactivation_hook( __FILE__, array(&$obfuscaTOR, 'cleanup') );
}
