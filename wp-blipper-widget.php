<?php

/**
  *
  * @link              http://pandammonium.org/dev/wp-blipper-widget/
  * @since             0.0.2
  * @package           WP_Blipper_Widget
  *
  * @wordpress-plugin
  * Plugin Name:       WP Blipper Widget
  * Plugin URI:        http://pandammonium.org/dev/wp-blipper-widget/
  * Description:       Displays the latest entry on Polaroid|Blipfoto by a given user in a widget.
  * Version:           0.0.1
  * Author:            Caity Ross
  * Author URI:        http://pandammonium.org/
  * License:           GPL-2.0+
  * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
  * Text Domain:       wp-blipper-widget
  * Domain Path:       /languages
  */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use wpbw_Blipfoto\wpbw_Api\wpbw_Client;
use wpbw_Blipfoto\wpbw_Exceptions\wpbw_ApiResponseException;
use wpbw_Blipfoto\wpbw_Exceptions\wpbw_OAuthException;
use wpbw_Blipfoto\wpbw_Exceptions\wpbw_InvalidResponseException;
use wp_blipper_widget\wpbw_Settings;

// Register the WP Blipper widget
function register_wp_blipper_widget() {
  register_widget( 'WP_Blipper_Widget' );
}
add_action( 'widgets_init', 'register_wp_blipper_widget' ); // function to load WP Blipper

// Add a link to the WP Blipper Settings page from the plugins list.
function wp_blipper_widget_add_settings_link( $links ) {
  $links[] = '<a href="' .
    admin_url( 'options-general.php?page=options-wp-blipper-widget' ) .
    '">' . __('Settings') . '</a>';
  return $links;
}
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'wp_blipper_widget_add_settings_link');

// Error handling
function wp_blipper_exception( $e ) {
  echo '<p class="fatwide">An unexpected error has occurred.  ' . $e->getMessage() . '  Please try again later.</p>';
}
set_exception_handler('wp_blipper_exception');


class WP_Blipper_Widget extends WP_Widget {

/**
  * @since    0.0.1
  * @access   private
  * @var      array     $default_setting_values       The widget's default settings
  */
  private $default_setting_values = array (
    'title'         => 'My latest blip',
  );

/**
  * @since    0.0.1
  * @access   private
  * @var      wpbw_Blipfoto\wpbw_Api\wpbw_Client    $client    The Polaroid|Blipfoto client
  */
  private $client;


/**
  * Construct an instance of the widget.
  * 
  * @since     0.0.1
  * @access    public
  */
  public function __construct() {

    $params = array(
      'description' => __( 'The latest blip from your Polaroid|Blipfoto account.', 'wp-blipper-widget' ),
      'name'        => __( 'WP Blipper Widget', 'wp-blipper-widget' ),
    );
    parent::__construct( 'wp_blipper_widget', 'WP Blipper Widget', $params );

    if ( is_active_widget( false, false, $this->id_base, true ) ){
      $this->load_dependencies();
      $this->settings = new wpbw_Settings();
    }
  }

/**
  * Render the widget on the WP site.  This is the front-end of the widget.
  * 
  * @since     0.0.1
  * @access    public
  */
  public function widget( $args, $instance ) {

    echo $args['before_widget'];

    if ( ! empty( $instance['title'] ) ) {
      echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
    }

    if ( $this->wp_blipper_create_blipfoto_client( $instance ) ) {
      $this->wp_blipper_display_blip( $instance );
    }

    echo $args['after_widget'];

  }

/**
  * Render the form used in the widget admin settings panel or the WordPress customiser.  This is the back-end of the widget.
  *
  * @since     0.0.1
  * @access    public
  */
  public function form( $instance ) {

    $settings = $this->wp_blipper_get_display_values( $instance );
    $this->display_form( $settings );

  }

/**
  * Update the widget settings that were set using the form in the admin panel/customiser.
  * 
  * @since     0.0.1
  * @access    public
  * @param     array     $new_instance     The settings the user wants to save in the database
  * @param     array     $old_instance     The settings already saved in the database
  * @return    array     $instance         The validated settings based on the user's input to be saved in the database.
  */
  public function update( $new_instance, $old_instance ) {

    $instance['title']          = $this->wp_blipper_validate( $new_instance, $old_instance, 'title' );

    return $instance;

  }

/**
  * Validate the input.
  * Make sure the input comprises only printable/alphanumeric (depending on the field) characters; otherwise, return an empty string/the default value.
  *
  * This might become a loop at some point.
  *
  * @since     0.0.1
  * @access    private
  * @var       array       $new_settings         An array containing the settings that the user wants to set.
  * @var       array       $current_settings     An array containing the settings that are in place now.
  * @var       string      $setting_field        The setting to validate.
  * @return    string      $output               The validated setting.
  */
  private function wp_blipper_validate( $new_settings, $current_settings, $setting_field ) {

    $output = $this->default_setting_values[$setting_field];
    $new_settings[$setting_field] = esc_attr( $new_settings[$setting_field] );

    switch ( $setting_field ) {
      case 'title':
        if ( true == ctype_print( $new_settings[$setting_field] ) ) {
          $output = trim( $new_settings[$setting_field] );
        } else if ( empty($new_settings[$setting_field]) ) {
          $output = '';
        } else {
          $output = 'Please enter printable characters only';
        }
      break;
      default:
    }

    return $output;
  }

/**
  * Get the values to display.
  *
  * @since     0.0.1
  * @access    private
  * @var       array       $instance             The widget settings saved in the database.
  * @return    array                             The widget settings saved in the database, unless blank, in which case, the defaults are returned.  The title is returned regardless of whether it is empty or not.
  */
  private function wp_blipper_get_display_values( $instance ) {

    return array(
      'title'         => ! empty( $instance['title'] )          ? __( $instance['title'], 'wp-blipper-widget' )         : __( $this->default_setting_values['title'], 'wp-blipper-widget' ),
    );

  }

/**
  * Load the files this widget needs.
  *
  * @since 0.0.1
  * @access private
  */
  private function load_dependencies() {

    require( plugin_dir_path( __FILE__ ) . 'includes/class-settings.php' );

    $this->load_blipfoto_dependencies();

  }

/**
  * Load the Blipfoto API.
  *
  * @since 0.0.1
  * @access private
  */
  private function load_blipfoto_dependencies() {

    $folders = array(
        'Traits' => array(
          'Helper'
          ),
        'Exceptions' => array(
          'BaseException',
          'ApiResponseException',
          'InvalidResponseException',
          'NetworkException',
          'OAuthException',
          'FileException'
          ),
        'Api' => array(
          'Client',
          'OAuth',
          'Request',
          'Response',
          'File'
          )
        );

    $path = plugin_dir_path( __FILE__ ) . 'includes/Blipfoto/';

    foreach ( $folders as $folder => $files ) {
      foreach ( $files as $file ) {
        require( $path . $folder . '/' . $file . '.php' );
      }
    }
  }

/**
  * Construct an instance of the Polaroid|Blipfoto client and do something constructive with it..
  * 
  * @since     0.0.1
  * @access    private
  * @param     array         $instance       The settings just saved in the database.
  */
  private function wp_blipper_create_blipfoto_client( $instance ) {

    $client_created = false;
    $oauth_token = null;

    // Get the settings from the database
    $settings = get_option( $this->settings->wp_blipper_widget_settings_db_name() );
    if ( false === $settings ) {

    }

    // Create Polaroid|Blipfoto client if it hasn't already been done yet; otherwise just change the settings.
    if ( null != $this->client || isset( $this->client ) ) {
      unset( $this->client );
    }

    if ( ! empty( $settings['client-id'] ) && 
         ! empty( $settings['client-secret'] ) && 
         ! empty( $settings['access-token'] )
      ) {
      try {
        $this->client = new wpbw_Client (
         $settings['client-id'],
         $settings['client-secret'],
         $settings['access-token']
        );
        if ( $settings['access-token'] === $this->client->accessToken() ) {
          $client_created = true;
        } else {
          throw new wpbw_OAuthException( 'Error.  Can\'t connect to Polaroid|Blipfoto.  Please check your OAuth settings on <a href="' . esc_url( get_admin_url( null, 'options-wp-blipper-widget.php') ) . '">the WP Blipper Widget settings page</a> to continue.' );
        }
      } catch ( wpbw_OAuthException $e ) {
        echo '<p class="fatwide">' . $e->getMessage() . '</p>';
      } catch ( wpbw_ApiResponseException $e ) {
        echo '<p class="fatwide">Polaroid|Blipfoto error.  ' . $e->getMessage() . '  Please check your settings on <a href="' . esc_url( get_admin_url( null, 'options-wp-blipper-widget.php') ) . '">the WP Blipper Widget settings page</a> to continue.</p>';
      }
    } else {
      echo '<p class="fatwide">You need to set your Polaroid|Blipfoto credentials on <a href="' . esc_url( get_admin_url( null, 'options-wp-blipper-widget.php') ) . '">the WP Blipper Widget settings page</a> to continue.</p>';
    }

    return $client_created;

  } 

/**
  * Display the blip.
  *
  * @since     0.0.1
  * @access    private
  * @param     array         $instance       The settings saved in the database
  */
  private function wp_blipper_display_blip( $instance ) {

    $user_profile = null;
    $continue = false;
    try {
      $user_profile = $this->client->get( 'user/profile' );
      if ( $user_profile->error() ) {
        throw new wpbw_ApiResponseException( $user_profile->error() . '  Can\'t access your Polaroid|Blipfoto account.  Please check your settings on <a href="' . esc_url( get_admin_url( null, 'options-wp-blipper-widget.php') ) . '">the WP Blipper Widget settings page</a> to continue.' );
      } else {
        $continue = true;
      }
    } catch ( wpbw_ApiResponseException $e ) {
      echo '<p class="fatwide">Polaroid|Blipfoto error.  ' . $e->getMessage() . '</p>';
    }
    if ( $continue ) {
      $continue = false;
      try {
        $user_settings = $this->client->get( 'user/settings' );
        if ( $user_settings->error() ) {
          throw new wpbw_ApiResponseException( $user_settings->error() . '  Can\'t access your Polaroid|Blipfoto account.  Please check your settings on <a href="' . esc_url( get_admin_url( null, 'options-wp-blipper-widget.php') ) . '">the WP Blipper Widget settings page</a> to continue.' );
        } else {
          $continue = true;
        }
      } catch ( wpbw_ApiResponseException $e ) {
        echo '<p class="fatwide">Polaroid|Blipfoto error.  ' . $e->getMessage() . '</p>';
      }
    }
    if ( $continue ) {
      $continue = false;
      try {
        $user = $user_profile->data('user');
        if ( null == $user ) {
          throw new wpbw_ApiResponseException( 'Can\'t access your Polaroid|Blipfoto account.  Please check your settings on <a href="' . esc_url( get_admin_url( null, 'options-wp-blipper-widget.php') ) . '">the WP Blipper Widget settings page</a> to continue.');
        } else {
          $continue = true;
        }
      } catch ( wpbw_ApiResponseException $e ) {
        echo '<p class="fatwide">Polaroid|Blipfoto error.  ' . $e->getMessage() . '</p>';
      }
    }
    if ( $continue ) {
      $continue = false;
      $username = $user_settings->data( 'username' );
      try {
        $continue = $username == $user['username'];
        if ( !$continue ) {
          throw new ErrorException( 'Usernames don\'t match.  You entered: <i>' . $instance['username'] . '</i>; I got: <i>' . $username . '</i>.  Please check your settings on <a href="' . esc_url( get_admin_url( null, 'options-wp-blipper-widget.php') ) . '">the WP Blipper Widget settings page</a> to continue.' );
        }
      } catch ( ErrorException $e ) {
        echo '<p class="fatwide">Error.  ' . $e->getMessage() . '</p>';
      }
    }
    if ( $continue ) {
      $continue = false;
      try {
        // A page index of zero gives the most recent page of blips.
        // A page size of one means there will be only one blip on that page.
        // Together, these ensure that the most recent blip is obtained — which is exactly what we want to display.
        $journal = $this->client->get(
          'entries/journal',
          array(
            'page_index'  => 0,
            'page_size'   => 1
          )
        );
        if ( $journal->error() ) {
          throw new wpbw_ApiResponseException( $journal->error() . '  Can\'t access your journal.  Please check your settings on <a href="' . esc_url( get_admin_url( null, 'options-wp-blipper-widget.php') ) . '">the WP Blipper Widget settings page</a> to continue or try again later.');
        } else {
          $continue = true;
        }
      } catch ( wpbw_ApiResponseException $e ) {
        echo '<p class="fatwide">Polaroid|Blipfoto error.  ' . $e->getMessage() . '</p>';
      }
    }
    if ( $continue ) {
      $continue = false;
      try {
        $blips = $journal->data( 'entries' );
        if ( null === $blips ) {
          throw new ErrorException( 'Can\'t access your journal.  Please check your settings on <a href="' . esc_url( get_admin_url( null, 'options-wp-blipper-widget.php') ) . '">the WP Blipper Widget settings page</a> to continue or try again later.');
        } else {
          $continue = true;
        }
      } catch ( ErrorException $e ) {
        echo '<p class="fatwide">Error.  ' . $e->getMessage() . '</p>';
      }
    }
    // Assuming any blips have been retrieved, there should only be one.
    if ( $continue ) {
      $continue = false;
      try {
        if ( 0 == count( $blips ) ) {
          throw new ErrorException( 'No blips found.  Do you have <a href="https://www.polaroidblipfoto.com/' . $username . '" rel="nofollow">any Polaroid|Blipfoto entries</a>?');
        } else {
          $continue = true;
        }
      } catch ( ErrorException $e ) {
      echo '<p class="fatwide">Error.  ' . $e->getMessage() . '</p>';
      }
    }
    if ( $continue ) {
      $continue = false;
      $blip = $blips[0];
      try {
        $details = $this->client->get(
          'entry',
          array(
            'entry_id'          => $blip['entry_id_str'],
            'return_details'    => 1,
            'return_image_urls' => 1
          )
        );
        if ( $details->error() ) {
          throw new wpbw_ApiResponseException( $details->error() . '  Can\'t get the blip details.' );
       } else {
         $continue = true;
       }
      } catch ( wpbw_ApiResponseException $e ) {
        echo '<p class="fatwide">Polaroid| Blipfoto error.  ' . $e->getMessage() . '</p>';
      }
    }
    if ( $continue ) {
      $continue = false;
      // Polaroid|Blipfoto has different quality images, each with its own
      // URL.  Access is currently limited to standard, but I've
      // optimistically allowed for higher quality images to be selected
      // if they're present.  The lowest quality image is obtained if the
      // standard image isn't available.
      $image_url = null;
      try {
        if ( null !== $details->data( 'image_urls.original' ) ) {
          $image_url = $details->data( 'image_urls.original' );
        } else if ( null !== $details->data( 'image_urls.hires' ) ) {
          $image_url = $details->data( 'image_urls.hires' );
        } else if ( null !== $details->data( 'image_urls.stdres' ) ) {
          $image_url = $details->data( 'image_urls.stdres' );
        } else if ( null !== $details->data( 'image_urls.lores' ) ) {
          $image_url = $details->data( 'image_urls.lores' );
        } else {
          throw new ErrorException(' Unable to get URL of image.');
        }
      } catch ( ErrorException $e ) {
        echo '<p class="fatwide">Error.  ' . $e->getMessage() . '</p>';              
      }
      $continue = null != $image_url;
    }
    if ( $continue ) {
      $continue = false;
      $date = date( get_option( 'date_format' ), $blip['date_stamp'] );
      // In the following, the lines icluding links to Polaroid|Blipfoto
      // are commented out until I can make their being shown
      // customisable, in accordance with WP's guidelines
      // (https://wordpress.org/plugins/about/guidelines/).
      // echo '
      //   <a href="https://www.polaroidblipfoto.com/entry//' . $blip['entry_id_str'] . '" rel="nofollow">
      // ';
      echo '
        <figure class="fatwide" style="border-width:10;border-style:solid;border-color:#333333">
              <img 
              class="fatwide" 
              src="' . $image_url . '" 
              // alt="" 
              // height="" 
              // width="">
            <figcaption style="padding:5px">
              ' . $date . '<br>' . $blip['title'] . '
            </figcaption>
          </figure>
        ';
      // echo '
      //   </a>
      // ';
      // echo '<p class="fatwide" style="font-size:70%;margin-top:1ex">From <a href="https://www.polaroidblipfoto.com/' . $user_settings->data( 'username' ) . '" rel="nofollow">' . $user_settings->data( 'journal_title' ) . '</a> on <a href="https://www.polaroidblipfoto.com/" rel="nofollow">Polaroid|Blipfoto</a>.</p>';
    }
  }

/**
  * Display the back-end widget form.
  *
  * @since     0.0.1
  * @access    private
  * @param     array         $instance       The settings saved in the database
  */
  private function display_form( $settings ) {

    ?>
    <p><strong><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Widget title' ); ?></label></strong></p>
      <input 
        class="widefat" 
        id="<?php echo $this->get_field_id( 'title' ) ?>" 
        name="<?php echo $this->get_field_name( 'title' ); ?>" 
        type="text" 
        value="<?php echo esc_attr( $settings['title'] ); ?>">
    </p>

    <?php

  }

}

