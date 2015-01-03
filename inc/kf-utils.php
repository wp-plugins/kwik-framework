<?php


/**
 * Description: Collection of utilities for common PHP and WordPress tasks for themes and plugins
 */
Class KwikUtils {

  // static $settings_sections;

  /* returns a result form url */
  private function curl_get_result($url) {
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
  }

  /**
   * fetch a resource using cURL then cache for next use.
   * @param  [String] $url    - url of the resource to be fetched
   * @param  [String] $type   - type of resource to be fetched (fonts, tweets, etc)
   * @return [JSON]
   */
  private function fetchCachedResource($url, $type, $expire) {
    $cache_file = KF_CACHE . '/' . $type;
    $last = file_exists($cache_file) ? filemtime($cache_file) : false;
    $now = time();

    // check the cache file
    if (!$last || (($now - $last) || !file_exists($cache_file) > $expire)) {

      $cache_rss = $this->curl_get_result($url);

      if ($cache_rss) {
        $cache_static = fopen($cache_file, 'wb');
        fwrite($cache_static, $cache_rss);
        fclose($cache_static);
      }
    }

    return file_get_contents($cache_file);
  }

  public function get_google_fonts() {
    $kf_options = get_option(KF_FUNC);
    $api_key = $kf_options['fonts_key'];
    $defaults_fonts = KwikInputs::defaultFonts();

    if($api_key){
      $feed = "https://www.googleapis.com/webfonts/v1/webfonts?sort=popularity&fields=items(category%2Cfamily%2Cvariants)&key=" . $api_key;
      $fonts = json_decode($this->fetchCachedResource($feed, 'fonts', 1200));

      if ($fonts) {           // are there any results?
        return $fonts->items;
      } else {                // There are no fonts... somehow
        return $defaults_fonts;
      }
    } else {
      return $defaults_fonts;
    }

  }

  public function getDomain($url) {
    $pieces = parse_url($url);
    $domain = isset($pieces['host']) ? $pieces['host'] : '';
    if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
      return $regs['domain'];
    }
    return false;
  }

  public function currentPageURL() {
    $pageURL = 'http';
    if (isset($_SERVER["HTTPS"]) && strtolower($_SERVER["HTTPS"]) == "on") {$pageURL .= "s";}
    $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80") {
      $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
    } else {
      $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
    }
    return $pageURL;
  }

  public function widget_count($sidebar_id, $echo = true) {
    $the_sidebars = wp_get_sidebars_widgets();
    if (!isset($the_sidebars[$sidebar_id])) {
      return __('Invalid sidebar ID');
    }

    if ($echo) {
      echo count($the_sidebars[$sidebar_id]);
    } else {

      return count($the_sidebars[$sidebar_id]);
    }
  }

  public function getRealIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {//check ip from share internet
      $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {//to check ip is pass from proxy
      $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
      $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
  }


  public function get_taxonomy_parents($id, $taxonomy, $link = false, $separator = '/', $nicename = false, $visited = array()) {
    $chain = '';
    $parent = &get_term($id, $taxonomy);

    if (is_wp_error($parent)) {
      return $parent;
    }

    if ($nicename) {
      $name = $parent->slug;
    } else {
      $name = $parent->name;
    }

    if ($parent->parent && ($parent->parent != $parent->term_id) && !in_array($parent->parent, $visited)) {
      $visited[] = $parent->parent;
      $chain .= get_taxonomy_parents($parent->parent, $taxonomy, $link, $separator, $nicename, $visited);
    }

    if ($link) {
      // nothing, can't get this working :(
    } else {
      $chain .= $name . $separator;
    }

    return $chain;
  }


  /**
   * Attempts to get the featured image for a given post
   * if no featured image is set, one will be chosen from
   * images attached to post, if none are attached it will
   * randomly choose an image form the media library
   *
   * @param  [int]  $post_id  - post->ID to get image for
   * @param  boolean $echo    - echo the output?
   * @return [String]         - <img> tag
   */
  public function featured_image($post_id, $echo = true) {
    if (has_post_thumbnail()) {
      $thumb = get_the_post_thumbnail($post_id, 'thumbnail');
    } else {
      $attached_image = get_children("post_parent=" . $post_id . "&post_type=attachment&post_mime_type=image&numberposts=1");
      if ($attached_image) {
        var_dump($attached_image[0]);
        // foreach ($attached_image as $attachment_id => $attachment) {
        //   set_post_thumbnail($post_id, $attachment_id);
        //   $thumb = wp_get_attachment_image($attachment_id, 'thumbnail');
        // }
      } else {
        $args = array(
          'post_type' => 'attachment',
          'post_mime_type' => 'image',
          'post_status' => 'inherit',
          'posts_per_page' => 1,
          'orderby' => 'rand',
        );

        $query_images = new WP_Query($args);
        $thumb = wp_get_attachment_image($query_images->posts[0]->ID, 'thumbnail');
      }
    }
    if (!$echo) {return $thumb;} else {echo $thumb;}
  }

  public function __update_meta($post_id, $field_name, $value = ''){
    if (empty($value) OR !$value) {
      delete_post_meta($post_id, $field_name);
    } elseif (!get_post_meta($post_id, $field_name)) {
      add_post_meta($post_id, $field_name, $value);
    } else {
      update_post_meta($post_id, $field_name, $value);
    }
  }

  public function get_all_post_types() {
    $all_post_types = array();
    $args = array(
      'public' => true,
      '_builtin' => true
    );
    $output = 'objects';// names or objects, note names is the default
    $operator = 'and';// 'and' or 'or'

    $default_post_types = get_post_types($args, $output, $operator);

    foreach ($default_post_types as $k => $v) {
      $all_post_types[$k]['label'] = $v->labels->name;
      $all_post_types[$k]['name'] = $v->name;
    }

    $args = array(
      'public' => true,
      '_builtin' => false
    );

    $custom_post_types = get_post_types($args, $output, $operator);

    foreach ($custom_post_types as $k => $v) {
      $all_post_types[$k]['label'] = $v->labels->name;
      $all_post_types[$k]['name'] = $v->name;
    }

    array_push($all_post_types, array('name' => '404', 'label' => __('404 Not Found', 'kwik')));

    return $all_post_types;
  }

  public function number_to_string($num, $echo = FALSE){
    $numbers = array('zero','one','two','three','four','five','six','seven', 'eight', 'nine', 'ten');
    if($echo){
      echo $numbers[$num];
    } else {
      return $numbers[$num];
    }
  }

  public function number_to_class($num, $echo = FALSE){
    $numbers = array('','one','halves','thirds','fourths','fifths','sixths','sevenths');
    if($echo){
      echo $numbers[$num];
    } else {
      return $numbers[$num];
    }
  }

  public function neat_trim($str, $n, $delim = '&hellip;', $neat = true) {
    $len = strlen($str);
    if ($len > $n) {
      if ($neat) {
        preg_match('/(.{' . $n . '}.*?)\b/', $str, $matches);
        return rtrim($matches[1]) . $delim;
      } else {
        return substr($str, 0, $n) . $delim;

      }
    } else {
      return $str;
    }
  }

  public function settings_init($name, $page, $settings) {
    $validate = new KwikValidate($settings);
    wp_enqueue_script('jquery-ui-tabs');
    $options = get_option($page);
    foreach ($settings as $section => $val) {
      register_setting($page, $page, array($validate,'validateSettings'));
      add_settings_section(
        $section, // section id
        $val['section_title'],
        $val['section_desc'], // callback for section
        $page
        );
      $this->add_kf_fields($val['settings'], $section, $page, $settings);
    }
  }

  private function add_kf_fields($fields, $section, $page, $settings, $multi = NULL){
    foreach ($fields as $k => $v) {
      if(!$v['type'] || $v['type']  === 'multi'){
        $args = array(
          'fields' => $settings[$section]['settings'][$k]['fields'],
          'desc' => $settings[$section]['settings'][$k]['desc']
          );
        $callback = 'multi';
      } else{
        $args = array(
          'value' => $settings[$section]['settings'][$k]['value'],
          'options' => $settings[$section]['settings'][$k]['options'],
          'attrs' => $settings[$section]['settings'][$k]['attrs'],
          'desc' => $settings[$section]['settings'][$k]['desc']
        );
        $callback = $v['type'];
      }
      add_settings_field(
        $k, // id
        $v['title'], // title
        $callback, //callback, type or multi to insert multiple fields in single settings
        $page,
        $section, // section
        $args
      );
    }
  }

  private function section_callback($section){
    $inputs = new KwikInputs();
    return $inputs->markup('p', $section['callback']);
  }


  public static function settings_sections($page, $settings){
    $inputs = new KwikInputs();
    global $wp_settings_sections, $wp_settings_fields;

    if (!isset($wp_settings_sections) || !isset($wp_settings_sections[$page])) {
      return;
    }

    $output = '';
    foreach ((array) $wp_settings_sections[$page] as $section) {
      $section_nav_li .= $inputs->markup('li', '<a href="#' .KF_PREFIX. $section['id'] . '">' . $section['title'] . '</a>');
    }
    $save_btn = $inputs->markup('li', get_submit_button(__('Save', 'kwik')), array("class" => 'kf_submit'));
    $output .= $inputs->markup('ul', $section_nav_li.$save_btn, array("class" => KF_PREFIX.'settings_index'));

    foreach ((array) $wp_settings_sections[$page] as $section) {
      $cur_section = !empty($section['title']) ? $inputs->markup('h3', $section['title']) : "";
      $cur_section .= self::section_callback($section);

      if (!isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section['id']])) {
        continue;
      }
      $settings_fields = self::settings_fields($page, $section['id'], $settings);
      $cur_section .= $inputs->markup('table', $settings_fields, array("class" => "form-table"));
      $output .= $inputs->markup('div', $cur_section, array("class" => KF_PREFIX."options_panel", "id" => KF_PREFIX. $section['id']));

    }

    $output = $inputs->markup('div', $output, array("class" => KF_PREFIX."settings", "id" => KF_PREFIX. $section['id']));

    return $output;
  }


  private function settings_fields($page, $section, $settings) {
    $inputs = new KwikInputs();
    $errors = get_settings_errors();

    global $wp_settings_fields;
    if (!isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section])) {
      return;
    }

    $sectionFields = (array) $wp_settings_fields[$page][$section];

    foreach ($sectionFields as $field) {
      $error_class = '';
      $id = esc_attr($field['id']);
      $type = $field['callback'];

      if($field['args']['desc']){
        $desc = $inputs->markup('span', '', array('class'=>'dashicons ks_info_tip', 'tooltip' => $field['args']['desc']));
      }

      $title = $field['title'].' '.$desc;

      $setting_error = get_settings_errors($id);

      if($setting_error[0]){
        $error_icon = $inputs->markup('span', '!', array('class'=>'error_icon', 'tooltip' => $setting_error[0]['message']));
        $title = $title.$error_icon;
        $error_class = 'error';
      }

      if (!empty($field['args']['label_for'])) {
        $field['title'] = $inputs->markup('label', $title, array('for'=>$field['args']['label_for']));
      }

      $th = $inputs->markup('th', $title, array('scope'=>'row'));
      $value = $settings[$field['id']] ? $settings[$field['id']] : $field['args']['value'];

      if($field['callback'] === 'multi'){
        $field = $inputs->$field['callback'](
          $page.'['.$id.']', // name
          $value, // value`
          $field['args']
          );
      } else {
        $field = $inputs->$field['callback'](
          $page.'['.$id.']', // name
          $value, // value
          NULL, // label
          $field['args']['attrs'],
          $field['args']['options'] // options
          );
      }

      $td = $inputs->markup('td', $field);
      $output .= $inputs->markup('tr', $th.$td, array('valign'=>'top', 'class' => array($id, KF_PREFIX.'option', 'type-'.$type, $error_class)));

    }
      return $output;
  }


  /**
   * Adds a custom post to to the admin dashboard `At a Glance`
   * @param  [String] $cpt  name of the custom post type to be added
   * @return [String]       markup for custom at a glance dashboard widgets
   */
  public function cpt_at_a_glance($cpt) {
    $post_type = get_post_type_object( $cpt );
    $num_posts = wp_count_posts( $post_type->name );
    $num = number_format_i18n( $num_posts->publish );
    $text = _n( $post_type->labels->singular_name, $post_type->labels->name , intval( $num_posts->publish ) );
    echo '<li class="'.$cpt.'-count"><tr><a href="edit.php?post_type='.$cpt.'"><td class="first b b-' . $cpt . '"></td>' . $num . ' <td class="t ' . $cpt . '">' . $text . '</td></a></tr></li>';
  }

}//---------/ Class KwikUtils
