<?php
namespace WAR;

class NgTheme  {
  private $template_url;
  private $request_uri;

  public function __consruct(){
     $this->request_uri  = $_SERVER[ 'REQUEST_URI' ]; //This is used more than once, so it becomes a property
  }

  public function init(){
     /** Fire off the Actions **/
     $this->action_init();
     /** Remove WP Bloat **/
     $this->wp_cleanup();
  }

  /** Add Actions **/
  public function action_init(){
     //Scan and register /src directory
     add_action( 'wp_enqueue_scripts', [ $this, 'register_src_assets' ] );
     // Register a Main Menu option
     add_action( 'init', [ $this, 'register_main_menu' ] ); // Because why not

     /**
      * Remove WP Redict of "admin" and "login" slugs
      * This helps if you want to build your own "/admin" or "/login" section
      * but don't want to use "/wp-admin" or "/login.php"
      **/
      add_action( 'template_redirect', [ $this, 'wp_admin_login_redirect_remove' ] );
  }

  /**
  * List of Default WP Actions and Filters to de-register or turn off
  * In general, these either don't play well with Angular or aren't needed
  * Thank you *internet* for listing these all out
  **/
  public function wp_cleanup(){
     add_filter( 'embed_oembed_discover', '__return_false' );
     add_action( 'init', [ $this, 'deregister_wp_scripts' ] );
     remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
     remove_action( 'rest_api_init', 'wp_oembed_register_route' );
     remove_filter( 'template_redirect', 'redirect_canonical' );
     add_filter( 'script_loader_src', [ $this, 'remove_css_js_versions' ], 10, 2 );
     add_filter( 'style_loader_src', [ $this, 'remove_css_js_versions' ], 10, 2 );
     remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
     remove_action( 'wp_head', 'rsd_link' );
     remove_action( 'wp_head', 'wlwmanifest_link' );
     remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
     remove_action( 'wp_head', 'wp_oembed_add_host_js' );
     remove_action( 'wp_print_styles', 'print_emoji_styles' );
     add_filter( 'xmlrpc_enabled', '__return_false' );
  }

  public function deregister_wp_scripts(){
     /**
      * If not wp-admin, de-register some scripts
      **/
     if( ! is_admin() ){
         wp_deregister_script( 'wp-embed' );
         wp_deregister_script( 'jquery' ); //I mean, we are using Angular here
     }
  }

  public function register_src_assets(){
     if( ! isset( $this->template_url ) ) $this->template_url = get_template_directory_uri();
     $src_dir = new \DirectoryIterator( get_stylesheet_directory() . '/src' );
     foreach( $src_dir as $file){
         $full_name = basename( $file );
         $url = $this->template_url . '/src/' . $full_name;
         $slug = 'ng_' . substr( basename( $full_name ), 0, strpos( basename( $full_name ), '.' ) );

         if( pathinfo( $file, PATHINFO_EXTENSION ) === 'css' ){
             $this->wp_style_registry( $slug, $url );
             continue;
         }
         if( pathinfo( $file, PATHINFO_EXTENSION ) === 'js' ){
             switch( $slug ){
                 case 'ng_polyfills':
                     $dep = [ 'ng_inline' ];
                     break;
                 case 'ng_main':
                     $dep = [ 'ng_polyfills' ];
                     break;
                 default:
                     $dep = NULL;
                     break;
             }

             $this->wp_script_registry( $slug, $url, $dep );
             continue;
         }

     }
  }

  private function wp_style_registry( $name = false, $url = false ){
     wp_register_style( $name, $url );
     wp_enqueue_style( $name );
  }

  private function wp_script_registry( $name = false, $url = false, $dep = false ){
     wp_register_script( $name, $url, $dep, NULL, TRUE ); // TRUE at the end to ensure this is loaded in the footer
     wp_enqueue_script( $name, $name );
  }

  public function remove_css_js_versions( $source ){
     if( strpos( $source, '?ver=' ) ) $source = remove_query_arg( 'ver', $source );
     return $source;
  }

  /**
  * We need the $request early on in order to determine if we should properly remove the template_redirect action
  * Compare the $request against the wp-admin and login URL's
  * If we have a match, remove the redirect action
  **/
  public function wp_admin_login_redirect_remove(){
     $request    = untrailingslashit( $this->request_uri ); //format the current request
     $login      = site_url( 'login', 'relative' ); //Get the login URL
     $admin      = site_url( 'admin', 'relative' ); //Get the wp-admin URL

     if( $request === $login || $request === $admin )
         remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 ); // Set this as far up the stack as possible so nothing over writes it
  }

  public function register_main_menu(){
     register_nav_menu( 'header', __( 'Header Menu' ) );
  }
}
