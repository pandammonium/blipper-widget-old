<?php

/**
  *
  * @link              http://pandammonium.org/dev/wp-blipper-widget/
  * @since             0.0.1
  * @package           Blipper_Widget
  *
  * @wordpress-plugin
  * Plugin Name:       Blipper Widget
  * Plugin URI:        http://pandammonium.org/dev/wp-blipper-widget/
  * Description:       Display your latest blip in a widget.  Requires a Polaroid|Blipfoto account.
  * Version:           0.0.2
  * Author:            Caity Ross
  * Author URI:        http://pandammonium.org/
  * License:           GPL-2.0+
  * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
  * Text Domain:       blipper-widget
  * Domain Path:       /languages
  */

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use blipper_widget_Blipfoto\blipper_widget_Api\blipper_widget_Client;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_ApiResponseException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_OAuthException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_InvalidResponseException;
use blipper_widget\blipper_widget_Settings;

// Register the WP Blipper widget
function register_blipper_widget() {
  register_widget( 'Blipper_Widget' );
}
add_action( 'widgets_init', 'register_blipper_widget' ); // function to load WP Blipper

// Add a link to the Blipper Widget Settings page from the plugins list.
function blipper_widget_add_settings_link( $links ) {
  $links[] = '<a href="' .
    admin_url( 'options-general.php?page=blipper-widget' ) .
    '">' . __('Settings', 'blipper-widget') . '</a>';

  return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'blipper_widget_add_settings_link' );

// Error handling
function blipper_exception( $e ) {
  echo '<p>An unexpected error has occurred.  ' . $e->getMessage() . '  Please try again later.</p>';
}
set_exception_handler('blipper_exception');


class Blipper_Widget extends WP_Widget {

/**
  * @since    0.0.1
  * @access   private
  * @var      array     $default_setting_values       The widget's default settings
  */
  private $default_setting_values = array (
    'title'                 => 'My latest blip',
    'display-journal-title' => 'hide',
    'add-link-to-blip'      => 'hide',
    'powered-by'            => 'hide',
  );

/**
  * @since    0.0.1
  * @access   private
  * @var      blipper_widget_Blipfoto\blipper_widget_Api\blipper_widget_Client    $client    The Polaroid|Blipfoto client
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
      'description' => __( 'The latest blip from your Polaroid|Blipfoto account.', 'blipper-widget' ),
      'name'        => __( 'Blipper Widget', 'blipper-widget' ),
    );
    parent::__construct( 'blipper_widget', 'Blipper Widget', $params );

    if ( is_active_widget( false, false, $this->id_base, true ) ){
      $this->load_dependencies();
      $this->settings = new blipper_widget_Settings();
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

    if ( $this->blipper_create_blipfoto_client( $instance ) ) {
      $this->blipper_display_blip( $instance );
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

    $settings = $this->blipper_get_display_values( $instance );
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

    $instance['title']                  = $this->blipper_validate( $new_instance, $old_instance, 'title' );
    $instance['display-journal-title']  = $this->blipper_validate( $new_instance, $old_instance, 'display-journal-title' );
    $instance['add-link-to-blip']       = $this->blipper_validate( $new_instance, $old_instance, 'add-link-to-blip' );
    $instance['powered-by']             = $this->blipper_validate( $new_instance, $old_instance, 'powered-by' );

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
  * @var       array       $new_instance      An array containing the settings that the user wants to set.
  * @var       array       $old_instance      An array containing the settings that are in place now.
  * @var       string      $setting_field     The setting to validate.
  * @return    string      $instance          The validated setting.
  */
  private function blipper_validate( $new_instance, $old_instance, $setting_field ) {

    $instance = $this->default_setting_values[$setting_field];
    $new_instance[$setting_field] = esc_attr( $new_instance[$setting_field] );

    switch ( $setting_field ) {
      case 'title':
        if ( true == ctype_print( $new_instance[$setting_field] ) ) {
          $instance = trim( $new_instance[$setting_field] );
        } else if ( empty($new_instance[$setting_field]) ) {
          $instance = '';
        } else {
          $instance = 'Please enter printable characters only';
        }
      break;
      case 'display-journal-title':
      case 'add-link-to-blip':
      case 'powered-by':
        $instance = $new_instance[$setting_field];
      break;
      default:
        $instance = null;
    }

    return $instance;
  }

/**
  * Get the values to display.
  *
  * @since     0.0.1
  * @access    private
  * @var       array       $instance             The widget settings saved in the database.
  * @return    array                             The widget settings saved in the database, unless blank, in which case, the defaults are returned.  The title is returned regardless of whether it is empty or not.
  */
  private function blipper_get_display_values( $instance ) {

    return array(
      'title'                 => ! empty( $instance['title'] ) ? __( $instance['title'], 'blipper-widget' ) : __( $this->default_setting_values['title'], 'blipper-widget' ),
      'display-journal-title' => $instance['display-journal-title'] ? 'show' : 'hide',
      'add-link-to-blip'      => $instance['add-link-to-blip'] ? 'show' : 'hide',
      'powered-by'            => $instance['powered-by'] ? 'show' : 'hide',
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
  private function blipper_create_blipfoto_client( $instance ) {

    $client_ok = false;

    // Create Polaroid|Blipfoto client if it hasn't already been done yet; otherwise just change the settings.
    if ( !empty( $this->client ) || isset( $this->client ) ) {

      try {

        // Get the settings from the database
        $oauth_settings = $this->settings->blipper_widget_get_settings();
        if ( empty( $oauth_settings['client-id'] ) || 
             empty( $oauth_settings['client-secret'] ) || 
             empty( $oauth_settings['access-token'] )
          ) {

          if ( current_user_can( 'manage_options' ) ) {
            throw new blipper_widget_OAuthException( 'You need to set your OAuth Polaroid|Blipfoto credentials on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.</p>' );
          }

        } else {

          $this->client->id( $oauth_settings['client-id'] );
          $this->client->secret( $oauth_settings['client-secret'] );
          $this->client->accessToken( $oauth_settings['access-token'] );

          $client_ok = true;

        }

      } catch ( blipper_widget_OAuthException $e ) {

        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>' . __( $e->getMessage(), 'blipper-widget' ) . '</p>';
        }

      } catch ( blipper_widget_ApiResponseException $e ) {

        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>' . __( 'Polaroid|Blipfoto error.  ' . $e->getMessage() . '  Please check your OAuth settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.', 'blipper-widget' ) . '</p>';
        }

      }

    }

    try {
      // Get the settings from the database
      $oauth_settings = $this->settings->blipper_widget_get_settings();
      if ( empty( $oauth_settings['client-id'] ) || 
           empty( $oauth_settings['client-secret'] ) || 
           empty( $oauth_settings['access-token'] )
        ) {

        if ( current_user_can( 'manage_options' ) ) {
          throw new blipper_widget_OAuthException( 'You need to set your OAuth Polaroid|Blipfoto credentials on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.</p>' );
        }

      } else {

        $this->client = new blipper_widget_Client (
         $oauth_settings['client-id'],
         $oauth_settings['client-secret'],
         $oauth_settings['access-token']
        );

        if ( $oauth_settings['access-token'] === $this->client->accessToken() ) {

          $client_created = true;

        } else {

          throw new blipper_widget_OAuthException( 'Can\'t connect to Polaroid|Blipfoto.  Please check you have copied and pasted <a href="https://www.polaroidblipfoto.com/developer/apps" rel="nofollow">your Polaroid|Blipfoto OAuth settings</a> correctly on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.' );

        }
      }

    } catch ( blipper_widget_OAuthException $e ) {

      if ( current_user_can( 'manage_options' ) ) {
        echo '<p>' . __( $e->getMessage(), 'blipper-widget' ) . '</p>';
      }

    } catch ( blipper_widget_ApiResponseException $e ) {

      if ( current_user_can( 'manage_options' ) ) {
        echo '<p>' . __( 'Polaroid|Blipfoto error.  ' . $e->getMessage() . '  Please check your OAuth settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.', 'blipper-widget' ) . '</p>';
      }

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
  private function blipper_display_blip( $instance ) {

    $user_profile = null;
    $continue = false;
    try {
      $user_profile = $this->client->get( 'user/profile' );
      if ( $user_profile->error() ) {
        throw new blipper_widget_ApiResponseException( $user_profile->error() . '  Can\'t access your Polaroid|Blipfoto account.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.' );
      } else {
        $continue = true;
      }
    } catch ( blipper_widget_ApiResponseException $e ) {
      if ( current_user_can( 'manage_options' ) ) {
        echo '<p>Polaroid|Blipfoto error.  ' . $e->getMessage() . '</p>';
      }
    }
    if ( $continue ) {
      $continue = false;
      try {
        $user_settings = $this->client->get( 'user/settings' );
        if ( $user_settings->error() ) {
          throw new blipper_widget_ApiResponseException( $user_settings->error() . '  Can\'t access your Polaroid|Blipfoto account.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.' );
        } else {
          $continue = true;
        }
      } catch ( blipper_widget_ApiResponseException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>Polaroid|Blipfoto error.  ' . $e->getMessage() . '</p>';
        }
      }
    }
    if ( $continue ) {
      $continue = false;
      try {
        $user = $user_profile->data('user');
        if ( null == $user ) {
          throw new blipper_widget_ApiResponseException( 'Can\'t access your Polaroid|Blipfoto account.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.');
        } else {
          $continue = true;
        }
      } catch ( blipper_widget_ApiResponseException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>Polaroid|Blipfoto error.  ' . $e->getMessage() . '</p>';
        }
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
          throw new blipper_widget_ApiResponseException( $journal->error() . '  Can\'t access your journal.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue or try again later.');
        } else {
          $continue = true;
        }
      } catch ( blipper_widget_ApiResponseException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>Polaroid|Blipfoto error.  ' . $e->getMessage() . '</p>';
        }
      }
    }
    if ( $continue ) {
      $continue = false;
      try {
        $blips = $journal->data( 'entries' );
        if ( null === $blips ) {
          throw new ErrorException( 'Can\'t access your journal.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue or try again later.');
        } else {
          $continue = true;
        }
      } catch ( ErrorException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>Error.  ' . $e->getMessage() . '</p>';
        }
      }
    }
    // Assuming any blips have been retrieved, there should only be one.
    if ( $continue ) {
      $continue = false;
      try {
        switch ( count( $blips ) ) {
          case 0:
            throw new Exception( 'No blips found.  Do you have <a href="https://www.polaroidblipfoto.com/' .$user['username'] . '" rel="nofollow">any Polaroid|Blipfoto entries</a>?');
          break;
          case 1:
            $continue = true;
          break;
          default:
            throw new ErrorException( count( $blips ) . ' blips found, but was only looking for 1.  Something has gone wrong.');
        }
      } catch ( ErrorException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>Error.  ' . $e->getMessage() . '</p>';
        }
      } catch ( Exception $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>Error.  ' . $e->getMessage() . '</p>';
        }
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
          throw new blipper_widget_ApiResponseException( $details->error() . '  Can\'t get the blip details.' );
       } else {
         $continue = true;
       }
      } catch ( blipper_widget_ApiResponseException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>Polaroid| Blipfoto error.  ' . $e->getMessage() . '</p>';
        }
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
        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>Error.  ' . $e->getMessage() . '</p>';
        }
      }
      $continue = null != $image_url;
    }
    if ( $continue ) {
      $continue = false;
      $date = date( get_option( 'date_format' ), $blip['date_stamp'] );
      if ( $instance['add-link-to-blip'] ) {
        echo '
          <a href="https://www.polaroidblipfoto.com/entry//' . $blip['entry_id_str'] . '" rel="nofollow">
        ';
      }
      echo '
        <figure style="border-width:10;border-style:solid;border-color:#333333">
              <img 
              
              src="' . $image_url . '" 
              // alt="" 
              // height="" 
              // width="">
            <figcaption style="padding:5px">
              ' . $date . '<br>' . $blip['title'] . '
            </figcaption>
          </figure>
        ';
      if ( $instance['add-link-to-blip'] ) {
        echo '
          </a>
        ';
      }
      if ( $instance['display-journal-title'] && $instance['powered-by'] ) {
        echo '<p style="font-size:70%;margin-top:1ex">From <a href="https://www.polaroidblipfoto.com/' . $user_settings->data( 'username' ) . '" rel="nofollow">' . $user_settings->data( 'journal_title' ) . '</a><br>Powered by <a href="https://www.polaroidblipfoto.com/" rel="nofollow">Polaroid|Blipfoto</a></p>';
      } else if ( $instance['display-journal-title'] ) {
        echo '<p style="font-size:70%;margin-top:1ex">From <a href="https://www.polaroidblipfoto.com/' . $user_settings->data( 'username' ) . '" rel="nofollow">' . $user_settings->data( 'journal_title' ) . '</a></p>';
      } else if ($instance['powered-by'] ) {
        echo '<p style="font-size:70%;margin-top:1ex">Powered by <a href="https://www.polaroidblipfoto.com/" rel="nofollow">Polaroid|Blipfoto</a></p>';
      }
    }
  }

/**
  * Display the back-end widget form.
  *
  * @since     0.0.1
  * @access    private
  * @param     array         $instance       The settings saved in the database
  */
  private function display_form( $instance ) {

    $oauth_settings = $this->settings->blipper_widget_get_settings();

    if ( empty( $oauth_settings['client-id'] ) || 
         empty( $oauth_settings['client-secret'] ) || 
         empty( $oauth_settings['access-token'] )

      ) {

      echo '<p>You need to set your Polaroid|Blipfoto OAuth credentials on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.</p>';

    } else {

      ?>
      <p>
        <label for="<?php echo $this->get_field_id( 'title' ); ?>">
          <?php _e( 'Widget title:', 'blipper-widget' ); ?>
        </label>
        <input 
          class="widefat"
          id="<?php echo $this->get_field_id( 'title' ) ?>" 
          name="<?php echo $this->get_field_name( 'title' ); ?>" 
          type="text" 
          value="<?php echo esc_attr( $instance['title'] ); ?>"
        >
      </p>
      <p class="description">Leave the title field blank if you don't want to display a title.</p>

      <p>
        <input
          class="fatwide"
          id="<?php echo $this->get_field_id( 'add-link-to-blip' ); ?>"
          name="<?php echo $this->get_field_name( 'add-link-to-blip' ); ?>"
          type="checkbox"
          value="1"
          <?php echo esc_attr( $instance['add-link-to-blip'] ) == 'show' ? 'checked="checked"' : ''; ?>
        >
        <label for="<?php echo $this->get_field_id( 'add-link-to-blip' ); ?>">
          <?php _e( 'Include link to your latest blip', 'add-link-to-blip' ) ?>
        </label>
      </p>
      <p class="description">Tick the box to make the widget link back to the corresponding blip in your journal.  Leave it unticked if you do want to include a link back to your latest blip.</p>

      <p>
        <input
          class="fatwide"
          id="<?php echo $this->get_field_id( 'display-journal-title' ); ?>"
          name="<?php echo $this->get_field_name( 'display-journal-title' ); ?>"
          type="checkbox"
          value="1"
          <?php echo esc_attr( $instance['display-journal-title'] ) == 'show' ? 'checked="checked"' : ''; ?>
        >
        <label for="<?php echo $this->get_field_id( 'display-journal-title' ); ?>">
          <?php _e( 'Display journal title and link', 'display-journal-title' ) ?>
        </label>
      </p>
      <p class="description">Tick the box to show the name of your journal with a link back to your Polaroid|Blipfoto journal.  Leave it unticked if you do want to show the name of your journal or have a link back to your journal.</p>

      <p>
        <input
          class="fatwide"
          id="<?php echo $this->get_field_id( 'powered-by' ); ?>"
          name="<?php echo $this->get_field_name( 'powered-by' ); ?>"
          type="checkbox"
          value="1"
          <?php echo esc_attr( $instance['powered-by'] ) == 'show' ? 'checked="checked"' : ''; ?>
        >
        <label for="<?php echo $this->get_field_id( 'powered-by' ); ?>">
          <?php _e( 'Include a \'powered by\' link', 'powered-by' ) ?>
        </label>
      </p>
      <p class="description">Tick the box to include a 'powered by' link back to Polaroid|Blipfoto.  Leave it unticked if you do want to include a 'powered by'-style link.</p>

      <?php
    }

  }

}

