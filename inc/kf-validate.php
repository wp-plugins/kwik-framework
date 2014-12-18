<?php


  Class KwikValidate extends KwikInputs {

    function __construct($settings){
      $this->settings = $settings;
    }

    public function validateFont($val) {
      $font = array(
        'color' => $this->color($val['color']),
        'weight' => wp_filter_nohtml_kses($val['weight']),
        'size' => wp_filter_nohtml_kses($val['size']),
        'line-height' => wp_filter_nohtml_kses($val['line-height']),
        'font-family' => wp_filter_nohtml_kses($val['font-family'])
      );
      return $font;
    }

    public function linkColor($val) {
      $link_color = array(
        'default' => $this->color($val['default']),
        'visited' => $this->color($val['visited']),
        'hover' => $this->color($val['hover']),
        'active' => $this->color($val['active'])
      );

      return $link_color;
    }

    public function validateHeaders($val) {
      $headers = array();
      $utils = new KwikUtils();
      $post_types = $utils->get_all_post_types();

      foreach ($post_types as $type) {
        $headers[$type['name']] = array(
          'color' => $this->color($val[$type['name']]['color']),
          'weight' => wp_filter_nohtml_kses($val[$type['name']]['weight']),
          'size' => wp_filter_nohtml_kses($val[$type['name']]['size']),
          'line-height' => wp_filter_nohtml_kses($val[$type['name']]['line-height']),
          'font-family' => wp_filter_nohtml_kses($val[$type['name']]['font-family']),
          'bg_color' => $this->color($val[$type['name']]['bg_color']),
          'img' => wp_filter_nohtml_kses($val[$type['name']]['img']),
          'position' => wp_filter_nohtml_kses($val[$type['name']]['position']),
          'repeat' => wp_filter_nohtml_kses($val[$type['name']]['repeat']),
          'bg_size' => wp_filter_nohtml_kses($val[$type['name']]['bg_size']),
          'attachment' => wp_filter_nohtml_kses($val[$type['name']]['img']),
          'text' => wp_filter_nohtml_kses($val[$type['name']]['text'])
        );
      }

      return $headers;
    }


    public function text($key, $val){
      if(preg_match("/<[^<]+>/",$val,$m) !== 0){
        add_settings_error( $key, 'text', __('HTML is not allowed in this field.', 'kwik'), 'kf_error' );
      }
      return wp_filter_nohtml_kses($val);
    }

    public function color($key, $val) {
      $colorCode = ltrim($val, '#');

      if (ctype_xdigit($colorCode) && (strlen($colorCode) == 6 || strlen($colorCode) == 3)){
        $color =  $val;
      } else {
        add_settings_error( $key, 'color', __('Invalid Color', 'kwik'), 'kf_error' );
        $color = '';
      }

      return $color;
    }

    public function font($key, $val){
      $font = array(
        'color' => self::color($key, $val['color']),
        'weight' => self::select($key, $val['weight']),
        'size' => is_numeric($val['size']) ? $val['size'] : '',
        'line-height' => is_numeric($val['line-height']) ? $val['line-height'] : '',
        'font-family' => wp_filter_nohtml_kses($val['font-family'])
      );
      return $val;
    }

    public function select($key, $val){
      return $val;
    }

    public function spinner($key, $val){
      if(!is_numeric($val['line-height'])){
        add_settings_error( $key, 'spinner', __('Only numbers are allowed', 'kwik'), 'kf_error' );
      }
      return $val;
    }

    public function img($key, $val){
      if(!is_numeric($val['line-height']) && !empty($val)){
        add_settings_error( $key, 'img', __('Invalid Selection', 'kwik'), 'kf_error' );
      }
      return $val;
    }

    public function multi($key, $val){
      return $val;
    }

    public function validateSettings($setting){
      $settings = $this->settings;
      foreach ($setting as $key => $val) {
        $path_segments = $this->find_key($key, $this->settings);
        $addr = &$settings;
        foreach($path_segments as $i => $path_segment){
          $addr = &$addr[$path_segment];
        }
        //validate field by type
        $setting[$key] = $this->$addr['type']($key, $val);
        unset($addr);
      }
      return $setting;
    }

    public function find_key( $name, $settings, $strict=false, $path=array() ){
      if( !is_array($settings) ) {
        return false;
      }

      foreach( $settings as $key => $val ) {
        if( is_array($val) && $subPath = $this->find_key($name, $val, $strict, $path) ) {
          $path = array_merge($path, array($key), $subPath);
          return $path;
        } elseif( (!$strict && $key == $name) || ($strict && $key === $name) ) {
          $path[] = $key;
          return $path;
        }
      }
      return false;
    }

  }
