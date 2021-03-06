<?php
class WPCustomerReviewsAdmin3 extends WPCustomerReviews3
{
	var $default_options = array();
	var $settings_sections = array();
	var $admin_init_ran = false;
	var $meta_box_posts = array();
	var $meta_box_reviews = array();
	
	function __construct() {
	}
	
	function start_admin($parentClass) {	
		$this->setSharedVars($parentClass);
	}
	
	function my_output_settings_section($id) {
		$submit_name = $this->prefix."_save_settings";
		
		$params = array($submit_name);
		$this->param($params);
		
		if ($this->p->$submit_name == $id) {
			$this->update_options($id);
		}
		?>
		<table class="form-table"><tbody>
			<?php
			foreach ($this->settings_sections[$id] as $settingObj) {
				$this->my_output_setting($settingObj);
			}
			?>
		</tbody></table>
		<input type="hidden" name="<?php echo $submit_name; ?>" value="<?php echo $id; ?>" />
		<?php
		submit_button();
	}
	
	function my_output_setting($settingObj) {
		$options = $settingObj->options;
		$options->hint = isset($options->hint) ? $options->hint : "";
		$options->class = isset($options->class) ? $options->class : "";
		$options->addmore = isset($options->addmore) && $options->addmore == "1" ? true : false;
		
		$name = $this->prefix.'_option_'.$settingObj->name;
		$value = $this->options[$settingObj->name];
		
		echo '
			<tr class="setting_'.$name.'">
				<th scope="row">
					<label title="'.$options->hint.'" for="'.$name.'">'.$settingObj->label.'</label>
					<div style="font-size:10px;font-weight:normal;">'.$options->hint.'</div>
				</th>
				<td>
		';
		
		if ($options->type === "text") {
			echo '	<input class="'.$options->class.'" type="text" name="'.$name.'" value="'.$value.'" />';
		} else if ($options->type === "select") {
			echo '	<select class="'.$options->class.'" name="'.$name.'">';
			foreach ($options->options as $opt) {
				$selected = ($value == $opt->value) ? 'selected="selected"' : '';
				echo '		<option '.$selected.' value="'.$opt->value.'">'.$opt->label.'</option>';
			}
			echo '	</select>';
		} else if ($options->type === "multi_input_checkbox") {
			echo '	<table class="table_multi_input_checkbox" style="border-right:1px solid #bbb;border-bottom:1px solid #ddd;">';
			foreach ($options->options as $valObj) {
				echo '		<tr><td>';
				if (isset($options->editable_label) && $options->editable_label == "1") {				
					echo '<input class="'.$options->class.'" name="'.$name.'['.$valObj->value.'][label]" value="'.$value[$valObj->value]['label'].'" />';
				} else {
					echo $value[$valObj->value]['label'];
				}
				echo '		</td>';
				
				foreach ($valObj->checkboxes as $cbObj) {
					if ($cbObj->default == "-1") {
						echo '<td></td>';
						continue;
					}
					$cbObj->class = isset($cbObj->class) ? $cbObj->class : "";
					$myValue = $value[$valObj->value][$cbObj->value];
					$checked = ($myValue == "1") ? 'checked="checked"' : '';
					echo '	<td><label><input class="'.$cbObj->class.'" '.$checked.' name="'.$name.'['.$valObj->value.']['.$cbObj->value.']" type="checkbox" value="1" /> '.$cbObj->label.'</label></td>';
				}
				echo '		<td style="font-size:10px;">'.$valObj->hint.'</td>';
				echo '		</tr>';
			}
			if ($options->addmore) {
				$need_pro = ($this->pro) ? "" : "need_pro";
				echo '		<tr><td colspan="'.(count($valObj->checkboxes) + 2).'"><a class="addmore '.$need_pro.'" href="#">Add Another</a></td></tr>';
			}
			echo '	</table>';
		}
		
		echo '
				</td>
			</tr>
		';
	}
	
	function real_admin_init() {
		$this->admin_init_ran = true;
		
		add_action('admin_head', array(&$this, 'admin_head'));
		add_action('admin_notices', array(&$this, 'admin_notices'));
		add_action('save_post', array(&$this, 'admin_save_post'), 10, 3); // 3 arguments
		add_action('manage_'.$this->prefix.'_review_posts_custom_column', array(&$this, 'admin_custom_review_column'), 10, 2); // 2 arguments
		add_action('restrict_manage_posts', array(&$this, 'review_filter_list'));
		add_action('load-edit.php', array(&$this, 'load_custom_filter'));
		
		add_filter('manage_edit-'.$this->prefix.'_review_columns', array(&$this, 'admin_filter_custom_review_columns') );
		add_filter('plugin_action_links_'.plugin_basename(__FILE__), array(&$this, 'plugin_settings_link'));
		add_filter('post_updated_messages', array(&$this, 'admin_post_updated_messages'));
		add_filter('manage_edit-wpcr3_review_sortable_columns', array(&$this, 'review_sortable_columns'));
		add_filter('request', array(&$this, 'review_sortable_columns_orderby'));
		
		$this->enqueue_admin_stuff();
		$this->my_add_meta_box();
		
		$params = array('action');
		$this->param($params);
		
		/* used for redirecting to settings page upon initial activation */
		if (get_option($this->prefix.'_gotosettings', false)) {
			delete_option($this->prefix.'_gotosettings');
			
			$this->post_activate();
			
			/* no auto redirect if upgrading */
			if ($this->p->action === 'activate-plugin') { return false; }

			$url = get_admin_url().'admin.php?page='.$this->prefix.'_options';
			$this->redirect($url);
		}
		
		if ($this->options === false) {
			$this->post_activate();
		}
		
		$this->create_settings();
		$this->notice_ignore(); /* admin notices */
	}
	
	function post_activate() {
		if ($this->pro !== true) {
			unset($this->options['templates']);			
			update_option($this->options_name, $this->options);
		}
		
		$this->create_settings();
		$this->check_migrate();
		
		$this->notify_activate(1); // notify on initial activation, reactivation, upgrade
	}
	
	// generates new "-generated" CSS file based on template version
	function generate_css() {
		$can_write_css = $this->can_write_css();
		if ($can_write_css["can_write"]) {
			$css = $this->template('wp-customer-reviews-css');
			file_put_contents($can_write_css["filename"], $css, LOCK_EX); // overwrites -generated.css
		}
	}
	
	function load_template($file, $ext, $force) {
		if ($this->options === false || !isset($this->options['templates'])) {
			$force = true;
		}
	
		if ($force === true || array_key_exists($file, $this->options['templates']) === false) {
			$rtn = file_get_contents($this->getplugindir().'include/templates/'.$file.'.'.$ext);
			$rtn = preg_replace('/%---.*?---%/ims', '', $rtn);
		} else {
			$rtn = $this->options['templates'][$file];
		}
		
		return $rtn;
	}
	
	function update_db_version($new_version) {
		$this->options['dbversion'] = $new_version;
		update_option($this->options_name, $this->options);
		return $this->options['dbversion'];
	}
	
	function merge_options() {
		$default_options = array();
			
		// begin: build options from settings_sections ( and get defaults )
		foreach ($this->settings_sections as $section_id => $sectionArr) {
			foreach ($sectionArr as $settingArr) {
				$name = $settingArr->name;
				$options = $settingArr->options;
				if ($options->type === "text") {
					$default_options[$name] = $options->default;
				} else if ($options->type === "select") {
					$default_options[$name] = $options->default;
				} else if ($options->type === "multi_input_checkbox") {
					$default_options[$name] = array();
					foreach ($options->options as $valObj) {
						$default_options[$name][$valObj->value] = array();
						$default_options[$name][$valObj->value]['label'] = $valObj->label;
						foreach ($valObj->checkboxes as $cbObj) {
							$default_options[$name][$valObj->value][$cbObj->value] = $cbObj->default;
						}
					}
				} else if ($options->type === "array") {
					$default_options[$name] = array();
					foreach ($options->options as $key => $val) {
						$default_options[$name][$key] = $val;
					}
				}
			}
		}
		// end: build options from settings_sections ( and get defaults )

		$this->default_options = $default_options; // save for later, migrations, etc

		// get current options from WP, if they do not exist yet, get $default_options
		$this->options = get_option($this->options_name, $default_options);

		// begin: magically easy options migrations to newer versions
		$has_new = false;
		foreach ($default_options as $col => $def_val) {
			if (!isset($this->options[$col])) {
				$this->options[$col] = $def_val;
				$has_new = true;
			}
			
			// allows for associative arrays up to 2 depth [ "standard_fields" => [ "fname" => [ "ask" : "1" ] ] ]
			if (is_array($def_val)) {
				foreach ($def_val as $acol => $aval) {
					if (!isset($this->options[$col][$acol])) {
						$this->options[$col][$acol] = $aval;
						$has_new = true;
					}
					
					if (is_array($aval)) {
						foreach ($aval as $acol2 => $aval2) {
							if (!isset($this->options[$col][$acol][$acol2])) {
								$this->options[$col][$acol][$acol2] = $aval2;
								$has_new = true;
							}
						}
					}
				}
			}
		}
		
		if ($has_new) {	
			update_option($this->options_name, $this->options);
		}
		// end: magically easy options migrations to newer versions
		
		$this->post_update_options();
	}
	
	function post_update_options() {
		$this->generate_css();
	}
	
	function get_cleaned_dbversion() {
		$current_dbversion = ($this->options !== false && isset($this->options['dbversion'])) ? $this->options['dbversion'] : 0;
        $current_dbversion = intval(str_replace('.', '', $current_dbversion));
		return $current_dbversion;
	}

    function check_migrate() {
		$current_dbversion = $this->get_cleaned_dbversion();
		$plugin_db_version = intval(str_replace('.', '', $this->plugin_version));

		$this->merge_options();
		
        if ($current_dbversion == $plugin_db_version) {
            return;
        }

        if ($current_dbversion == 0) {
			// check if we need to migrate from 2.x to 3.x
			include_once($this->getplugindir().'include/migrate/2x-3x.php');
			$migrate_ok = wpcr3_migrate_2x_3x($this, $current_dbversion);
        } else {
			// if we get here, we are upgrading 3.x to 3.x
			include_once($this->getplugindir().'include/migrate/3x-3x.php');
			$migrate_ok = wpcr3_migrate_3x_3x($this, $current_dbversion);
		}

		if ($migrate_ok === true) {
			// done with all migrations, push dbversion to current version
			$this->update_db_version($plugin_db_version);
		}
    }
	
	function my_add_settings_section($id, $label, $type) {
		$newSection = new stdClass();
		$newSection->id = $id;
		$newSection->label = $label;
		$newSection->type = $type;
		$this->settings_sections[$id] = array();
	}
	
	function my_add_setting($section_id, $name, $label, $options_json) {
		$newSetting = new stdClass();
		$newSetting->name = $name;
		$newSetting->label = $label;
		$newSetting->section_id = $section_id;
		$newSetting->options = json_decode($options_json);
		$this->settings_sections[$section_id][] = $newSetting;
	}
	
	function create_ask_require_show($label, $name, $ask, $require, $show, $rate, $has_rating, $hint) {
		// pass -1 to ask,require,show to disable this checkbox from being output
		
		$rtn = '
			{
				"label" : "'.$label.'",
				"value" : "'.$name.'",
				"hint" : "'.$hint.'",
				"checkboxes" : [
					 { "label" : "Ask", "value" : "ask", "default" : "'.$ask.'" }
					,{ "label" : "Require", "value" : "require", "default" : "'.$require.'" }
					,{ "label" : "Show", "value" : "show", "default" : "'.$show.'" }
			';
			if ($has_rating === 1) {
				$rtn .= '
					,{ "label" : "Rate", "value" : "rate", "default" : "'.$rate.'", "class" : "need_pro" }
				';
			}
			$rtn .= '
				]
			}
		';
		return $rtn;
	}
	
	function create_settings() {
		$this->settings_sections = array();
	
		$options_yesno = '[
			{ "label" : "Yes", "value" : "1" },
			{ "label" : "No", "value" : "0" }
		]';

		// Hidden Settings
		$section_id = 'hidden_settings';
		$this->my_add_settings_section($section_id, 'Hidden Settings', 'hidden');
		$this->my_add_setting($section_id, 'act_email', 'Activation Email', '{ "type" : "text", "default" : "" }');
		$this->my_add_setting($section_id, 'act_uniq', 'Activation ID', '{ "type" : "text", "default" : "" }');
		$this->my_add_setting($section_id, 'activated', 'Activated', '{ "type" : "text", "default" : "0" }');
		$this->my_add_setting($section_id, 'dbversion', 'Database Version', '{ "type" : "text", "default" : "0" }');
		
		$templates = array();
		foreach ($this->all_templates as $file => $ext) {
			$templates[$file] = $this->load_template($file, $ext, false);
		}
		$templates = json_encode($templates);
		$this->my_add_setting($section_id, 'templates', 'Templates', '{ "type" : "array", "options" : '.$templates.' }');

		// Form Settings
		$section_id = 'form_settings';
		$this->my_add_settings_section($section_id, 'Form Settings', 'h3');

		$this->my_add_setting($section_id, 'standard_fields', 'Standard fields on reviews', '
			{ 	"type": "multi_input_checkbox", 
				"hint" : "Choose which fields you want to ask for, require, and display on submitted reviews.",
				"editable_label" : "1",
				"options" : [
					'.$this->create_ask_require_show( "Name", "fname", 1, 1, 1, 0, 0, '(English: Name)' ).',
					'.$this->create_ask_require_show( "Email", "femail", 1, 1, -1, 0, 0, '(English: Email)' ).',
					'.$this->create_ask_require_show( "Website", "fwebsite", 1, 0, 0, 0, 0, '(English: Website)' ).',
					'.$this->create_ask_require_show( "Review Title", "ftitle", 1, 0, 1, 0, 0, '(English: Review Title)' ).'
				]
			}'
		);

		$this->my_add_setting($section_id, 'custom_fields', 'Custom fields on reviews', '
			{ 	"hint" : "You can type in the names of any additional fields you would like here. <br /><br />Warning: If you change the field names, it will also change them on past reviews.",
				"type": "multi_input_checkbox", 
				"addmore" : "1",
				"editable_label" : "1",
				"options" : [
					'.$this->create_ask_require_show( "Field 1", "f1", 0, 0, 0, 0, 1, '' ).',
					'.$this->create_ask_require_show( "Field 2", "f2", 0, 0, 0, 0, 1, '' ).',
					'.$this->create_ask_require_show( "Field 3", "f3", 0, 0, 0, 0, 1, '' ).'
				]
			}'
		);

		// Display Settings
		$section_id = 'display_settings';
		$this->my_add_settings_section($section_id, 'Display Settings', 'h3');
		$this->my_add_setting($section_id, 'reviews_per_page', 'Reviews shown per page', '{ "type" : "text", "class" : "w40px", "default" : "10" }');
		$this->my_add_setting($section_id, 'support_us', 'Support us', '{ "hint" : "Please support the developer! If yes, a \"Powered by WP Customer Reviews\" link will display below reviews.", "type" : "select", "options" : '.$options_yesno.', "default" : "0" }');
	}
	
	// adding menu items to admin must be done in admin_menu which gets executed BEFORE admin_init
	function real_admin_menu() {
		add_menu_page('Reviews', 'Reviews', 'edit_others_posts', $this->prefix.'_view_reviews', '', $this->getpluginurl() . 'css/star.png', '50.92'); // try to resolve issues with other plugins
		add_submenu_page($this->prefix.'_view_reviews', 'WP Customer Reviews - Settings', 'Plugin Settings', 'manage_options', $this->options_url_slug, array(&$this, 'admin_options'));
	}
	
	function plugin_settings_link($links) {
        $url = get_admin_url().'admin.php?page='.$this->options_url_slug;
        array_unshift($links, "<a href='$url'>Settings</a>");
        return $links;
    }
	
	function admin_head() {
		global $post;
		if ($post != '' && $post->post_type == $this->prefix.'_review') {
			remove_action('media_buttons', 'media_buttons'); // do not allow media uploads for reviews
		}
	}
	
	function redirect($url, $cookie = array()) {
        $headers_sent = headers_sent();
        
        if ($headers_sent == true) {
            // use JS redirect and add cookie before redirect
            // we do not html comment script blocks here - to prevent any issues with other plugins adding content to newlines, etc
            $out = "<html><head><title>Redirecting...</title></head><body><div style='clear:both;text-align:center;padding:10px;'>" .
                    "Processing... Please wait..." .
                    "<script type='text/javascript'>";
            foreach ($cookie as $col => $val) {
                $val = preg_replace("/\r?\n/", "\\n", addslashes($val));
                $out .= "document.cookie=\"$col=$val\";";
            }
            $out .= "window.location='$url';";
            $out .= "</script>";
            $out .= "</div></body></html>";
            echo $out;
        } else {
            foreach ($cookie as $col => $val) {
                setcookie($col, $val); // add cookie via headers
            }
			if (ob_get_level() > 0) {
				@ob_end_clean();
			}
            wp_redirect($url); // a real redirect
        }
        
        exit();
    }
	
	/* begin - admin notices */
	function admin_notices() {
		$url = $_SERVER['REQUEST_URI'] . (strstr($_SERVER['REQUEST_URI'], "?") === false ? "?" : "&");
		$notices = array(
			"1" => array(
				"text" => "WP Customer Reviews | Updated to v3 | <a href='admin.php?page=wpcr3_options&tab=tools'>Missing / Duplicate Reviews?</a>",
				"enabled" => (get_option("wpcr_options") !== false) // only show if upgraded from 2x to 3x
			)
		);
		
		foreach ($notices as $noticeKey => $notice) {
			$preNoticeKey = $this->prefix."_admin_notice_".$noticeKey;
			if (!isset($this->options[$preNoticeKey]) && $notice["enabled"] === true) {
				echo "<div class='updated'><p>{$notice["text"]}&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;<a href='{$url}{$preNoticeKey}=dismiss'>Dismiss</a></p></div>";
			}
		}
	}
	
	function notice_ignore() {
		/* If user clicks to dismiss the notice, add to plugin settings */
		foreach($this->p as $key => $val) {
			if (strpos($key, $this->prefix."_admin_notice_") !== false) {
				if ($val === 'dismiss') {
					$this->options[$key] = "dismiss";
					update_option($this->options_name, $this->options);
				}
			}
		}
	}
	/* end - admin notices */
	
	function admin_post_updated_messages($messages) {
		global $post;
		if ($post == '') { return $messages; }
		if ($post->post_type === $this->prefix."_review") {
			// remove "view post" links when adding/editing review post type
			foreach ($messages["post"] as $i => $v) {
				$messages["post"][$i] = trim(preg_replace("/<a.+?href.+?>.+?<\/a>/is","",$v));
			}
		}
		return $messages;
	}
	
	function my_add_meta_box() {		
		$this->meta_box_posts = array(
			'id' => $this->prefix.'-meta-box',
			'title' => '<img src="'.$this->getpluginurl().'css/star.png" />&nbsp;WP Customer Reviews',
			'context' => 'normal',
			'priority' => 'high',
			'fields' => array(
				array(
					'name' => '<span style="font-weight:bold;">Enable WP Customer Reviews</span> for this page',
					'desc' => 'Reviews will be displayed below your page content by default. To insert reviews in the middle of your post, add [WPCR_INSERT] in the contents where you would like the reviews to be displayed.',
					'id' => $this->prefix.'_enable',
					'type' => 'checkbox'
				),
				array(
					'name' => 'Hide review form',
					'desc' => 'If this option is checked, users will NOT be able to submit reviews.',
					'id' => $this->prefix.'_hideform',
					'type' => 'checkbox'
				),
				array(
					'name' => 'Review Format',
					'desc' => 'Will visitors be reviewing a business or a product?',
					'id' => $this->prefix.'_format',
					'type' => 'select',
					'options' => array(
						'business' => 'Business',
						'product' => 'Product'
					)
				),
				
				array(
					'name' => 'Business Name',
					'desc' => '',
					'id' => $this->prefix.'_business_name',
					'type' => 'text'
				),
				array(
					'name' => 'Street Address 1',
					'desc' => '',
					'id' => $this->prefix.'_business_street1',
					'type' => 'text'
				),
				array(
					'name' => 'Street Address 2',
					'desc' => '(optional) Leave blank if this does not apply.',
					'id' => $this->prefix.'_business_street2',
					'type' => 'text'
				),
				array(
					'name' => 'City / Locality',
					'desc' => '',
					'id' => $this->prefix.'_business_city',
					'type' => 'text'
				),
				array(
					'name' => 'State / Region',
					'desc' => 'For USA, use 2 letters such as "CA"',
					'id' => $this->prefix.'_business_state',
					'type' => 'text'
				),
				array(
					'name' => 'Postal Code',
					'desc' => '',
					'id' => $this->prefix.'_business_zip',
					'type' => 'text'
				),
				array(
					'name' => 'Country',
					'desc' => 'Use the 2-letter or 3-letter country code such as "USA"',
					'id' => $this->prefix.'_business_country',
					'type' => 'text'
				),
				array(
					'name' => 'Telephone',
					'desc' => 'Example: 520-555-5555 or (520) 555-5555',
					'id' => $this->prefix.'_business_phone',
					'type' => 'text'
				),
				array(
					'name' => 'Website URL',
					'desc' => 'Example: http://www.gowebsolutions.com/',
					'id' => $this->prefix.'_business_url',
					'type' => 'text'
				),
				
				array(
					'name' => 'Product Name',
					'desc' => '',
					'id' => $this->prefix.'_product_name',
					'type' => 'text'
				),
				array(
					'name' => 'Manufacturer/Brand of Product',
					'desc' => '',
					'id' => $this->prefix.'_product_brand',
					'type' => 'text'
				),
				array(
					'name' => 'Product ID',
					'desc' => 'ONE of the following formats: <b>asin:1234567890</b>, <b>isbn:1234567890</b>, <b>upc:1234567890123</b> , <b>sku:ABC123</b>, <b>mpn:ABC123</b>',
					'id' => $this->prefix.'_product_id',
					'type' => 'text'
				)
			)
		);
	
		$args = array('public' => true);
		if (!is_array($post_types = get_post_types($args))) {
			$post_types = array();
		}

		$my_post_type = $this->prefix.'_review';
		unset($post_types['attachment']);
		unset($post_types[$my_post_type]);
		
		foreach ($post_types as $post_type) {
			add_meta_box( $this->meta_box_posts['id'], $this->meta_box_posts['title'], array(&$this, 'show_meta_box_fields'), $post_type, $this->meta_box_posts['context'], $this->meta_box_posts['priority'], array('type' => 'meta_box_posts') );
		}
		
		$tmpPosts = $this->get_all_posts_pages(true);
		$postArr = array('' => '-- Select Post --');
		foreach ($tmpPosts->posts as $p) {
			if ($p->post_type === "attachment") { continue; }
			$postArr[$p->ID] = $p->post_title;
		}
		
		$this->meta_box_reviews = array(
			'id' => $this->prefix.'-meta-box-reviews',
			'title' => '<img src="'.$this->getpluginurl().'css/star.png" />&nbsp;Review Details',
			'context' => 'normal',
			'priority' => 'high',
			'fields' => array(
				array(
					'name' => 'Reviewed Post',
					'desc' => '',
					'id' => $this->prefix.'_review_post',
					'type' => 'select',
					'options' => $postArr
				),
				array(
					'name' => 'Reviewer Name',
					'desc' => '',
					'id' => $this->prefix.'_review_name',
					'type' => 'text'
				),
				array(
					'name' => 'Email Address',
					'desc' => '',
					'id' => $this->prefix.'_review_email',
					'type' => 'text'
				),
				array(
					'name' => 'Website',
					'desc' => '',
					'id' => $this->prefix.'_review_website',
					'type' => 'text'
				),
				array(
					'name' => 'Review Title',
					'desc' => '',
					'id' => $this->prefix.'_review_title',
					'type' => 'text'
				),
				array(
					'name' => 'Rating',
					'desc' => '',
					'id' => $this->prefix.'_review_rating',
					'type' => 'select',
					'options' => array('1' => '1 star','2' => '2 stars','3' => '3 stars','4' => '4 stars','5' => '5 stars')
				),
				array(
					'name' => 'Admin Response to Review',
					'desc' => '',
					'id' => $this->prefix.'_review_admin_response',
					'type' => 'textarea'
				)
			)
		);
		
		if (isset($this->options['custom_fields'])) {
			$i = 0;
			foreach ($this->options['custom_fields'] as $name => $fieldArr) {
				$i++;			
				if ($fieldArr['ask'] == 1 || $fieldArr['show'] == 1) {
					$this->meta_box_reviews['fields'][] = array(
						'name' => $fieldArr['label'],
						'desc' => 'Custom Field #'.$i,
						'id' => $this->prefix.'_'.$name,
						'type' => 'text'
					);
				}
			}
		}
		
		// add for reviews
		add_meta_box( $this->meta_box_reviews['id'], $this->meta_box_reviews['title'], array(&$this, 'show_meta_box_fields'), $this->prefix.'_review', $this->meta_box_reviews['context'], $this->meta_box_reviews['priority'], array('type' => 'meta_box_reviews') );
	}
	
	function get_all_posts_pages($only_plugin_enabled_posts) {
		$args = array(
			'post_type' => 'any',
			'orderby' => 'post_title',
			'order' => 'asc',
			'post_status' => 'publish,pending,draft,future,private,trash',
			'suppress_filters' => false,
			'nopaging' => true
		);
		
		if ($only_plugin_enabled_posts) {
			$args['meta_key'] = $this->prefix.'_enable';
			$args['meta_value'] = '1';
		}
		
		$tmp = new WP_Query($args);		
		return $tmp;
	}
	
	function admin_save_post($post_id, $post, $update) {
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return $post_id; } // do nothing special if autosaving
		if (defined('DOING_AJAX') && DOING_AJAX) { return $post_id; } // do nothing special if ajax
		if (defined('DOING_CRON') && DOING_CRON) { return $post_id; } // do nothing special if cron
		if (!current_user_can('edit_post', $post_id)) { return $post_id; } // do nothing special if user does not have permissions
		
		$params = array('_wpnonce', 'bulk_edit');
		$this->param($params);
		
		if ($this->p->_wpnonce !== '' && $this->p->bulk_edit === '') {
			// update meta if changed, delete it if not set or blank
			$types = array('meta_box_posts','meta_box_reviews'); // $this->meta_box_posts, $this->meta_box_reviews
			foreach ($types as $type) {
				$my_type = $this->$type; // $this->meta_box_posts, $this->meta_box_reviews				
				foreach ($my_type['fields'] as $field) {
					$old = get_post_meta($post_id, $field['id'], true);
					if (isset($this->p->$field['id'])) {
						$new = $this->p->$field['id'];
						if ($new && $new != $old) {
							update_post_meta($post_id, $field['id'], $new);
						} elseif ($new == '' && $old) {
							delete_post_meta($post_id, $field['id'], $old);
						}
					} else {
						delete_post_meta($post_id, $field['id'], $old);
					}
				}
			}
		}

		return $post_id;
	}
	
	/* start custom columns filters */
	function admin_custom_review_column($column, $post_id) {
		switch ($column) {
			case $this->prefix.'_review_post':
				$reviewed_post_id = get_post_meta($post_id, $this->prefix.'_review_post' ,true);
				if ($reviewed_post_id !== "") {
					$reviewed_post = get_post($reviewed_post_id);
					$permalink = get_permalink($reviewed_post_id);
					$not_published = ($reviewed_post->post_status !== "publish") ? "(Not Published)" : "";
					echo "<a target='_blank' href='{$permalink}'>{$reviewed_post->post_title}</a> {$not_published}";
				} else {
					echo "Not Assigned";
				}
				break;
			case $this->prefix.'_review_rating':
				$stars = get_post_meta($post_id, $this->prefix.'_review_rating' ,true);
				echo "{$stars} ";
				echo (intval($stars) === 1) ? "star" : "stars";
				break;
		}
	}
	
	function admin_filter_custom_review_columns($columns) {
		$columns[$this->prefix.'_review_post'] = 'Reviewed Post';
		$columns[$this->prefix.'_review_rating'] = 'Rating';
		return $columns;
	}
	
	function review_sortable_columns($columns) {
		//$columns[$this->prefix.'_review_post'] = $this->prefix.'_review_post';
		$columns[$this->prefix.'_review_rating'] = $this->prefix.'_review_rating';
		return $columns;
	}
	
	function review_sortable_columns_orderby($request) {
		if ($request['post_type'] !== $this->prefix.'_review') {
			return $request;
		}
		
		if (isset($request['orderby'])) {
			$name = $this->prefix.'_review_rating';
			if ($request['orderby'] === $name) {
				$request = array_merge($request, array('meta_key' => $name, 'orderby' => 'meta_value_num'));
			}
		}
		
		return $request;
	}
	
	// used for filtering on "All Reviews" page - step 1
	function load_custom_filter() {
		$screen = get_current_screen();
		if ($screen->post_type !== $this->prefix.'_review') { return; }
		
		$filterName = $name = $this->prefix."_reviews_post_filter";
		if (!isset($_GET[$filterName]) || intval($_GET[$filterName]) === 0) { return; }
		
		add_filter('posts_where' ,array(&$this, 'load_custom_filter_2'));
	}
	
	// used for filtering on "All Reviews" page - step 2
	function load_custom_filter_2($where) {
		global $wpdb;
		$filterName = $name = $this->prefix."_reviews_post_filter";
		$filterVal = intval($_GET[$filterName]);
        $where .= " AND ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='{$this->prefix}_review_post' AND meta_value = {$filterVal})";		
		return $where;
	}
	
	function review_filter_list() {
		$screen = get_current_screen();
		if ($screen->post_type !== $this->prefix.'_review') { return; }
		
		$name = $this->prefix."_reviews_post_filter";
		$getName = (isset($_GET[$name])) ? intval($_GET[$name]) : 0;
		?>
		<select name="<?php echo $name; ?>" id="<?php echo $name;?>">
			<option>All Posts / Pages</option>
			<?php
			$tmpPosts = $this->get_all_posts_pages(true);
			foreach ($tmpPosts->posts as $p) : ?>
				<option <?php if ($p->ID === $getName) { echo "selected"; } ?> value="<?php echo $p->ID; ?>"><?php echo $p->post_title; ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}
	/* end custom columns filters */
	
	function show_meta_box_fields($post, $args) {
		$my_args = $args['args'];
		$my_type = $this->$my_args['type']; // $this->meta_box_posts, $this->meta_box_reviews
		
		echo '<table class="form-table">';

		foreach ($my_type['fields'] as $field) {
			$params = array('default');
			$this->param($params, $field);
			
			// get current post meta data
			$meta = get_post_meta($post->ID, $field['id'], true);
			
			echo '<tr>',
				 '<th style="width:30%"><label for="', $field['id'], '">', $field['name'], '</label></th>',
				 '<td>';
			switch ($field['type']) {
				case 'text':
					echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['default'], '" size="30" style="width:97%" />';
					break;
				case 'textarea':
					echo '<textarea name="', $field['id'], '" id="', $field['id'], '" cols="60" rows="4" style="width:97%">', $meta ? $meta : $field['default'], '</textarea>';
					break;
				case 'select':
					echo '<select name="', $field['id'], '" id="', $field['id'], '">';
					foreach ($field['options'] as $value => $label) {
						echo '<option value="'.$value.'" ', $meta == $value ? ' selected="selected"' : '', '>', $label, '</option>';
					}
					echo '</select>';
					break;
				case 'checkbox':
					echo '<input value="1" type="checkbox" name="', $field['id'], '" id="', $field['id'], '"', $meta ? ' checked="checked"' : '', ' />';
					break;
			}
			echo '<div style="padding-top:5px;"><small>'.$field['desc'].'</small></div>';
			echo '<td></tr>';
		}
		
		echo '</table>';
	}

	/* some admin styles can override normal styles for inplace edits */
	function enqueue_admin_stuff() {
		$pluginurl = $this->getpluginurl();

		$params = array('page','post','post_type');
		$this->param($params);
		
		if ($this->p->post !== '' || $this->p->post_type !== '' || $this->p->page == $this->options_url_slug) {
			wp_register_script('wp-customer-reviews-admin',$pluginurl.'js/wp-customer-reviews-admin.js',array('jquery'),$this->plugin_version);
			wp_register_style('wp-customer-reviews-admin',$pluginurl.'css/wp-customer-reviews-admin.css',array(),$this->plugin_version);  
			wp_enqueue_script('wp-customer-reviews-admin');
			wp_enqueue_style('wp-customer-reviews-admin');
		}
	}
	
	/* v4 uuid */
	function gen_uuid() {
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}
	
    // this is used for notification of new releases and will not be shared with any third party.
    function notify_activate($act_flag) {
		// $act flag [ 1 = activation (includes reactivation/upgrade) , 2 = deactivation ]
        
		if (!isset($this->options['act_uniq']) || $this->options['act_uniq'] == '') {
            $this->options['act_uniq'] = $this->gen_uuid();
            update_option($this->options_name, $this->options);
        }
		
		if (function_exists('fsockopen') === false && function_exists('pfsockopen') === false && function_exists('stream_socket_client') === false) {
			return;
		}
		
        /* TO DISABLE THIS FUNCTION, UNCOMMENT THE FOLLOWING LINE */
        /* return; */
        
		global $wp_version;
        $request = 'plugin='.$this->prefix.'&doact='.$act_flag.'&email='.urlencode(stripslashes($this->options['act_email'])).'&version='.$this->plugin_version.'&support='.$this->options['support_us'].'&uuid='.$this->options['act_uniq'];
        $host = "www.gowebsolutions.com"; $port = 80; $wpurl = get_bloginfo('wpurl');
        
        $http_request  = "POST /plugin-activation/activate.php HTTP/1.0\r\n";
        $http_request .= "Host: www.gowebsolutions.com\r\n";
        $http_request .= "Content-Type: application/x-www-form-urlencoded; charset=utf-8\r\n";
        $http_request .= "Content-Length: ".strlen($request)."\r\n";
        $http_request .= "Referer: $wpurl\r\n";
        $http_request .= "User-Agent: WordPress/$wp_version\r\n\r\n";
        $http_request .= $request;

        $response = '';
		$ret = false;
		
		if (function_exists('fsockopen')) {
			$fs = @fsockopen($host, $port, $errno, $errstr, 10);
		} else if (function_exists('pfsockopen')) {
			$fs = @pfsockopen($host, $port, $errno, $errstr, 10);
		} else if (function_exists('stream_socket_client')) {
			$fs = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10);
		}
		
        if ($fs !== false) {
			stream_set_timeout($fs, 10);
            fwrite($fs, $http_request);
            while (!feof($fs)) {
                $response .= fgets($fs, 1160);
            }
            fclose($fs);
            $response = explode("\r\n\r\n", $response, 2);
        }
		
		// var_dump($response);exit();
    }
	
    function update_options($id) {
		$this->security();
    	
		$default_options = $this->options;
		
		foreach ($this->settings_sections[$id] as $settingArr) {
			$name = $settingArr->name;
			$options = $settingArr->options;
			$postName = $this->prefix."_option_".$name;
			$postVal = $this->p->$postName;
			
			if ($options->type === "text") {
				$default_options[$name] = $postVal;
			} else if ($options->type === "select") {
				$default_options[$name] = $postVal;
			} else if ($options->type === "multi_input_checkbox") {
				$default_options[$name] = array();
				foreach ($options->options as $valObj) {
					$default_options[$name][$valObj->value] = array();
					if (isset($postVal[$valObj->value]['label'])) {
						$default_options[$name][$valObj->value]['label'] = $postVal[$valObj->value]['label'];
					}
					foreach ($valObj->checkboxes as $cbObj) {
						$postVal_cb = isset($postVal[$valObj->value][$cbObj->value]) ? "1" : "0";
						$default_options[$name][$valObj->value][$cbObj->value] = $postVal_cb;
					}
				}
			} else if ($options->type === "array") {
				$default_options[$name] = array();
				foreach ($options->options as $key => $val) {
					$default_options[$name][$key] = $postVal[$name][$key];
				}
			}
		}
		
		// simple validation
		if (intval($default_options['reviews_per_page']) < 1) { $default_options['reviews_per_page'] = 10; }
		
		$this->options = $default_options;
		update_option($this->options_name, $this->options);
		
		$this->post_update_options();

		add_settings_error($this->prefix.'_updateoptions', $this->prefix.'_updateoptions', 'Your settings have been saved.', 'updated');
		settings_errors($this->prefix.'_updateoptions');
    }
	
    function my_get_pages() { /* gets pages, even if hidden using a plugin */
        global $wpdb;
        
        $res = $wpdb->get_results("SELECT `ID`,`post_title` FROM `$wpdb->posts` WHERE `post_status` = 'publish' AND `post_type` = 'page' ORDER BY `ID`");
        return $res;
    }
    
    function security() {
        if (!current_user_can('manage_options')) {
            wp_die( __('You do not have sufficient permissions to access this page.') );
        }
    }
	
	function tab_about() {
		?>
		<div class="metabox-holder">
			<div class="postbox">
				<h3>About WP Customer Reviews</h3>
				<div class="inside">
					<p>
						<?php if ($this->pro) : ?>
							Version: <strong><?php echo $this->plugin_version; ?> <span style="color:#00f;">PRO</span></strong>
						<?php else: ?>
							Version: <strong><?php echo $this->plugin_version; ?> Lite <!--(<a class="boldBlue" target="_blank" href="<?php echo $this->prolink; ?>?from=about_upgrade">Upgrade to Pro</a>)--> </strong>
						<?php endif; ?>
					</p>
					<p>
						WP Customer Reviews allows your visitors to leave business and product reviews. Reviews are Microformat enabled and can help search engines index these reviews.
					</p>
				</div>
				<div class="inside bgblue">
					Plugin Homepage: <a target="_blank" href="<?php echo $this->url; ?>?from=about"><?php echo $this->url; ?></a><br /><br />
					Bug Report / Feature Request: <a target="_blank" href="https://competelab.fogbugz.com/default.asp?pg=pgPublicEdit">https://competelab.fogbugz.com/</a><br /><br />
					Community Support Forum: <a target="_blank" href="<?php echo $this->support_link; ?>"><?php echo $this->support_link; ?></a><br /><br />
					<div style="color:#BE5409;font-weight:bold;">
						If you like this plugin, please <a target="_blank" href="http://wordpress.org/support/view/plugin-reviews/wp-customer-reviews?rate=5#postform">login and rate it 5 stars here</a>
						<?php if (!$this->pro) : ?>
							<!-- and consider purchasing the Pro version. -->
						<?php endif; ?>
					</div>
				</div>
			</div>
				
			<?php if ($this->options['activated'] != 1) : ?>
				<?php
					add_settings_error($this->prefix.'_activation', $this->prefix.'_activation', 'Click "Yes" or "No" at the bottom of this screen to access the full plugin settings.', 'error');
					settings_errors($this->prefix.'_activation');
				?>
				<div class="postbox">
					<h3>Plugin Activation</h3>
					<div class="inside">
						<p style="color:#060;">
							If you would like to be notified of any major updates, please enter your email 
							address below. We do not share your information with any third party.
						</p>
						<label for="email">Email Address: </label><input type="text" size="32" id="act_email" name="act_email" />
						<p>
							Please support the developer! Can we display a small "Powered by WP Customer Reviews" link below reviews?
						</p>
						<input type="submit" class="button-primary" value="Yes" name="activate" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<input type="submit" class="button-primary" value="No" name="activate" />
					</div>
				</div>
			<?php endif; ?>							
		</div>
		<?php
	}
	
	function tab_how_to_use() {
		?>
		<style>
			.how-to-use .inside span { color:#00c;font-weight:bold; }
			.boldRed { color:#d00;font-weight:bold; }
		</style>
		<div class="metabox-holder how-to-use">
			<div class="postbox">
				<h3>How to use</h3>
				<div class="inside">
					<p class="boldBlue">
						When editing any post or page, scroll down to the setting block for WP Customer Reviews and enable it.<br /> 
						If you do not see this section, click on "Screen Options" (top-right) when editing a post and turn it on.
					</p>
				</div>
				<h3>Shortcodes ( use inside of the WordPress page editor )</h3>
				<div class="inside">
					<p>
						The following shortcodes can be used in the content of any page.
					</p>
					<p>
						[WPCR_SHOW 
							POSTID="<span>ALL</span>" 
							NUM="<span>5</span>" 
							PAGINATE="<span>1</span>" 
							PERPAGE="<span>5</span>"
							SHOWFORM="<span>1</span>" 
							HIDEREVIEWS="<span>0</span>" 
							HIDERESPONSE="<span>0</span>" 
							SNIPPET="<span></span>" 
							MORE="<span></span>" 
							HIDECUSTOM="<span>0</span>" 
						] 
						<br /><small>is available to show the latest reviews. Explanation below: <br /> 
						POSTID="<span>ALL</span>" to show recent reviews from ALL posts/pages or POSTID="123" to show recent reviews from post/page ID #123<br />
						NUM="<span>5</span>" will show a maximum of 5 reviews.<br />
						PAGINATE="<span>0</span>" will disable pagination of reviews.<br />
						PERPAGE="<span>10</span>" will show 10 reviews at a time.<br />
						SHOWFORM="<span>1</span>" will show the form to add a new review. This only works if POSTID is not set to "ALL".<br />
						<!--HIDEREVIEWS="<span>1</span>" will hide the review output and just display the average rating stars. This only works if POSTID is not set to "ALL". <span class="boldRed">Available in Pro Version Only.</span><br />-->
						HIDERESPONSE="<span>1</span>" will hide the admin response to all reviews.<br />
						SNIPPET="<span>140</span>" will only show the first 140 characters of a review.<br />
						MORE="<span>view more</span>" will show "... view more" with a link to the review. Only displayed when the review has been trimmed using SNIPPET.<br />
						HIDECUSTOM="<span>1</span>" will hide all custom fields in the shortcode output.<br />
						</small>
					</p>
				</div>
				<h3>PHP Functions ( use in your theme/template files )</h3>
				<div class="inside">
					<p>
						To create a more advanced implementation, you can use any shortcode in your 
						theme/template files. Any developer familiar with customizing WordPress themes/templates 
						can assist.
					</p>
					<p>
						Example: &lt;?php echo do_shortcode('[WPCR_SHOW POSTID="ALL" NUM="3"]'); ?&gt;
					</p>
				</div>
			</div>
		</div>
		<?php
	}
	
	function tab_form_settings() {
		$this->my_output_settings_section('form_settings');
	}
	
	function tab_display_settings() {
		echo '<br /><strong>Tip: You can completely customize the display of the review form and reviews on the Customize Templates / CSS tab.</strong>';
		$this->my_output_settings_section('display_settings');
	}
	
	function tab_templates() {
		echo '<br /><strong>This feature is available in the Pro version which will be announced soon.</strong>';
	}
	
	function tab_tools() {
		$params = array("wpcr3_debug_code");
		$this->param($params);
		
		?>
		<div style="color:#c00;">
			<br />
			You should ALWAYS make a backup of your files and database before performing an action.<br />
			Using any of these codes is at your own risk and we cannot be held liable for unintended results or lost data.<br />
		</div>
		
		<?php
		if ($this->p->wpcr3_debug_code !== "") {
			echo "<br />Running: <strong>{$this->p->wpcr3_debug_code}</strong><br /><br />";
			include($this->getplugindir().'include/admin/tools/'.$this->p->wpcr3_debug_code.'.php');
			print "<br /><strong>{$this->p->wpcr3_debug_code} DONE!</strong><br />";
			echo "<hr />";
		}
		?>
		
		<br />
		If you are having issues, we have provided fixes for some common scenarios, listed below.<br />
		<br />
		<table class="wp-list-table widefat fixed striped posts">
			<thead>
				<tr><th>Problem</th><th>Solution</th></tr>
			</thead>
			<tbody>
				<tr>
					<td>I upgraded from an earlier version and all/some of my reviews are missing!</td>
					<td>The 2x -> 3x migration script did not work properly. Enter this code below: <strong>reimport-2x</strong></td>
				</tr>
				<tr>
					<td>I have a lot of duplicate reviews that appeared after I upgraded!</td>
					<td>Enter this code below: <strong>remove-duplicates</strong></td>
				</tr>
				<tr>
					<td>For some reason, I need to permanently delete ALL of my reviews.</td>
					<td>Enter this code below: <strong>delete-all-reviews</strong></td>
				</tr>
			</tbody>
		</table>
		<br />
		If you have been given a code by support, enter it here.<br />
		<br />
		Support Code: <input name="wpcr3_debug_code" type="text" value="" />&nbsp;&nbsp;
		<input type="submit" name="submit" id="submit" class="button button-primary" value="Submit" />
		<?php
	}
	
    function admin_options() {
        $this->security();
		
		$params = array('activate','act_email','clearopts');
		$this->param($params);
		
		// begin: clear options for debugging, reset to defaults
		if ($this->p->clearopts == 1) {
			delete_option($this->options_name);
			$this->redirect("admin.php?page=".$this->options_url_slug);
		}
		// end: clear options for debugging, reset to defaults
		
		// begin: activation
		if ($this->p->activate !== '') {
			$this->options['support_us'] = ($this->p->activate === 'Yes') ? 1 : 0;
			$this->options['act_email'] = $this->p->act_email;
			$this->options['activated'] = 1;
			update_option($this->options_name, $this->options);
			$this->notify_activate(1);
			add_settings_error($this->prefix.'_activation', $this->prefix.'_activation', 'Thank you! You may now configure the plugin using the tabs below.', 'updated');
			settings_errors($this->prefix.'_activation');
		}
		// end: activation
		
		$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'about';
		$func_name = 'tab_'.$active_tab;
		$slug = '?page='.$this->options_url_slug;
		?>
		<style>
			.<?php echo $this->prefix; ?>_myplugin_options .metabox-holder .postbox {
				width:auto;
			}
			.<?php echo $this->prefix; ?>_myplugin_options .metabox-holder .postbox h3 {
				cursor:default;
			}
			.<?php echo $this->prefix; ?>_myplugin_options .metabox-holder .postbox .inside {
				margin:0;
				padding:10px;
			}
			.<?php echo $this->prefix; ?>_myplugin_options .metabox-holder .postbox .inside.bgblue {
				background:#eaf2fa;
			}
			.<?php echo $this->prefix; ?>_myplugin_options .metabox-holder .postbox .inside > p:first-child {
				margin-top:0;
			}
		</style>
		
		<div class="wrap <?php echo $this->prefix; ?>_myplugin_options">
			<?php if ($this->pro) : ?>
				<h2>WP Customer Reviews <span style="color:#00f;">Pro</span> - Settings</h2>
			<?php else : ?>
				<h2>WP Customer Reviews <span style="color:#00f;">Lite</span> - Settings <!--<div class="need_pro"><a class="boldBlue" target="_blank" href="<?php echo $this->prolink; ?>?from=h2_upgrade">Upgrade to Pro</a></div>--> </h2>
			<?php endif; ?>
			
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo $slug;?>&tab=about" class="nav-tab <?php echo $active_tab == 'about' ? 'nav-tab-active' : ''; ?>">About</a>
				<?php if ($this->options['activated'] == 1) : ?>
					<a href="<?php echo $slug;?>&tab=how_to_use" class="nav-tab <?php echo $active_tab == 'how_to_use' ? 'nav-tab-active' : ''; ?>">How to use</a>
					<a href="<?php echo $slug;?>&tab=form_settings" class="nav-tab <?php echo $active_tab == 'form_settings' ? 'nav-tab-active' : ''; ?>">Review Form Settings</a>
					<a href="<?php echo $slug;?>&tab=display_settings" class="nav-tab <?php echo $active_tab == 'display_settings' ? 'nav-tab-active' : ''; ?>">Display Settings</a>
					<a href="<?php echo $slug;?>&tab=templates" class="nav-tab <?php echo $active_tab == 'templates' ? 'nav-tab-active' : ''; ?>">Customize Templates / CSS</a>
					<a href="<?php echo $slug;?>&tab=tools" class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>">Tools</a>
				<?php endif; ?>
			</h2>
			
			<form method="POST" action="">
				<?php
					if (method_exists($this, $func_name)) {
						$this->$func_name();
					}
				?>
			</form>
			
		</div>
		<?php
    }
}
?>