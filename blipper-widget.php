<?php

/**
  *
  * @link               http://pandammonium.org/wordpress-dev/blipper-widget/
  * @since              0.0.1
  * @package            Blipper_Widget
  *
  * @wordpress-plugin
  * Plugin Name:        Blipper Widget
  * Plugin URI:         http://pandammonium.org/wordpress-dev/blipper-widget/
  * Description:        Display your latest blip in a widget.  Requires a Polaroid|Blipfoto account.
  * Version:            0.0.5
  * Author:             Caity Ross
  * Author URI:         http://pandammonium.org/
  * License:            GPL-2.0+
  * License URI:        http://www.gnu.org/licenses/gpl-2.0.txt
  * Text Domain:        blipper-widget
  * Domain Path:        /languages
  */

/**  Copyright 2015 Caity Ross

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use blipper_widget_Blipfoto\blipper_widget_Api\blipper_widget_Client;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_BaseException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_ApiResponseException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_OAuthException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_InvalidResponseException;
use blipper_widget\blipper_widget_settings;

// Register the WP Blipper widget
function register_blipper_widget() {
  register_widget( 'Blipper_Widget' );
}
// function to load WP Blipper:
add_action( 'widgets_init', 'register_blipper_widget' );

// Add a link to the Blipper Widget Settings page from the installed plugins
// list.
function blipper_widget_add_settings_link( $links ) {
  $links[] = '<a href="' .
    esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) .
    '">' . __('Settings', 'blipper-widget') . '</a>';

  return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'blipper_widget_add_settings_link' );

// Check the Polaroid|Blipfoto OAuth settings have been set, otherwise display a
// message to the user.
function blipper_widget_settings_check() {
  $api = get_option('blipper-widget-settings-oauth');
  if ( !empty( $api ) ) {
    $apistring = implode( '', $api );
  }
  if ( empty( $apistring ) ) {
    $optionslink = 'options-general.php?page=blipper-widget';
    $msgString = __('Please update <a href="%1$s">your settings for Blipper Widget</a>.','blipper-widget');
    echo "<html><body><div class='error'><p>" . sprintf( $msgString, $optionslink ) . "</p></div></body></html>";
  }
};
// add_action( 'admin_notices', 'blipper_widget_settings_check' );

// Generic error handling
function blipper_widget_exception( $e ) {
  echo '<p>An unexpected error has occurred.  ' . $e->getMessage() . '  Sorry about that.  Please check your settings or try again later.</p>';
}
set_exception_handler('blipper_widget_exception');

// -- Blipper Widget -------------------------------------------------------- //

/**
 * Widget settings.
 *
 * @since 0.0.2
 */
class Blipper_Widget extends WP_Widget {

/**
  * @since    0.0.1
  * @access   private
  * @var      array     $default_setting_values   The widget's default settings
  */
  private $default_setting_values = array (
    'title'                 => 'My latest blip',
    'display-date'          => 'show',
    'display-journal-title' => 'hide',
    'add-link-to-blip'      => 'hide',
    'powered-by'            => 'hide',
  );

/**
  * @since    0.0.5
  * @access   private
  * @var      array     $default_styles           The widget's default styling
  */
  private $default_styles = array (
    'border-width'          => 'inherit',
    'border-style'          => 'inherit',
    'border-color'          => 'inherit',
  );

/**
  * @since    0.0.1
  * @access   private
  * @var      blipper_widget_Client     $client   The Polaroid|Blipfoto client
  */
  private $client;

/**
  * @since    0.0.1
  * @access   private
  * @var      blipper_widget_settings   $settings The Blipper Widget settings
  */
  private $settings;


/**
  * Construct an instance of the widget.
  * 
  * @since    0.0.1
  * @access   public
  */
  public function __construct() {

    $params = array(
      'description' => __( 'The latest blip from your Polaroid|Blipfoto account.', 'blipper-widget' ),
      'name'        => __( 'Blipper Widget', 'blipper-widget' ),
    );
    parent::__construct( 'blipper_widget', 'Blipper Widget', $params );

    // Not using is_active_widget here because that function is only supposed to
    // return true if the widget is on a sidebar.  The widget isn't necessarily 
    // on a sidebar when the OAuth access settings are set.
    $this->load_dependencies();
    $this->settings = new blipper_widget_settings();
    $this->client = null;
  }


/**
  * Render the widget on the WP site.  This is the front-end of the widget.
  * 
  * @since    0.0.1
  * @access   public
  */
  public function widget( $args, $instance ) {

    echo $args['before_widget'];

    if ( ! empty( $instance['title'] ) ) {
      echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
    }

    if ( $this->blipper_widget_create_blipfoto_client( $instance ) ) {
      $this->blipper_widget_display_blip( $instance );
    }

    echo $args['after_widget'];

  }

/**
  * Render the form used in the widget admin settings panel or the WordPress
  * customiser.  This is the back-end of the widget.  The form disaplys the
  * settings already saved in the database, and allows the user to change them
  * if desired.
  *
  * @since    0.0.1
  * @access   public
  * @param    array     $instance         The settings currently saved in the
  *                                         database
  */
  public function form( $instance ) {

    $settings_array = $this->blipper_widget_get_display_values( $instance );
    $this->blipper_widget_display_form( $settings_array );

  }

/**
  * Update the widget settings that were set using the form in the admin
  * panel/customiser.
  * 
  * @since    0.0.1
  * @access   public
  * @param    array     $new_instance     The settings the user wants to change
  * @param    array     $old_instance     The settings currenlty saved in the
  *                                         database
  * @return   array     $instance         The validated settings based on the
  *                                         user's input to be saved in the
  *                                         database
  */
  public function update( $new_instance, $old_instance ) {

    $title                  = $this->blipper_widget_validate( $new_instance, $old_instance, 'title' );
    $display_date           = $this->blipper_widget_validate( $new_instance, $old_instance, 'display-date' );
    $display_journal_title  = $this->blipper_widget_validate( $new_instance, $old_instance, 'display-journal-title' );
    $add_link_to_blip       = $this->blipper_widget_validate( $new_instance, $old_instance, 'add-link-to-blip' );
    $powered_by             = $this->blipper_widget_validate( $new_instance, $old_instance, 'powered-by' );

    $instance['title']                  = $title;
    $instance['display-date']           = $display_date;
    $instance['display-journal-title']  = $display_journal_title;
    $instance['add-link-to-blip']       = $add_link_to_blip;
    $instance['powered-by']             = $powered_by;

    error_log( "Blipper_Widget::update()\nTitle: " . $instance['title'] . "\nDisplay date: " . $instance['display-date'] . "\nDisplay journal title: " . $instance['display-journal-title'] . "\nAdd back-link to Blipfoto: " . $instance['add-link-to-blip'] . "\nPowered by: " . $instance['powered-by'] );

    return $instance;

  }

/**
  * Validate the input.
  * Make sure the input comprises only printable/alphanumeric (depending on the
  * field) characters; otherwise, return an empty string/the default value.
  *
  * This might become a loop at some point.
  *
  * @since    0.0.1
  * @access   private
  * @var      array     $new_instance     The setting the user wants to change
  * @var      array     $old_instance     The setting currently saved in the
  *                                         database
  * @var      string    $setting_field    The setting to validate.
  * @return   string    $instance         The validated setting.
  */
  private function blipper_widget_validate( $new_instance, $old_instance, $setting_field ) {

    error_log( "Blipper_Widget::blipper_widget_validate()\nold_instance: " . var_export( $old_instance, true ) . "\nnew_instance: " . var_export( $new_instance, true ) );

    $instance = $this->default_setting_values[$setting_field];

    if ( array_key_exists( $setting_field, $new_instance ) ) {
      $new_instance[$setting_field] = esc_attr( $new_instance[$setting_field] );
      error_log( "Input:\nnew_instance[" . $setting_field . "]: " . $new_instance[$setting_field] );
    } else {
      error_log( "Input:\nnew_instance[" . $setting_field . "] " . "doesn't exist" );
    }

    switch ( $setting_field ) {
      case 'title':
        if ( true == ctype_print( $new_instance[$setting_field] ) ) {
          $instance = trim( $new_instance[$setting_field] );
        } else if ( empty( $new_instance[$setting_field] ) ) {
          $instance = '';
        } else {
          $instance = 'Please enter printable characters only or leave the field blank';
        }
      break;
      case 'display-date':
      case 'display-journal-title':
      case 'add-link-to-blip':
      case 'powered-by':
        if ( array_key_exists( $setting_field, $new_instance ) ) {
          $instance = $new_instance[$setting_field] ? 'show' : 'hide';
        } else {
          $instance = 'hide';
        }
      break;
      default:
        $instance = null;
    }

    error_log( "Output:\ninstance[" . $setting_field . "] has just been set to: " . var_export( $instance, true ) );

    return $instance;
  }

/**
  * Get the values to display.
  *
  * @since    0.0.1
  * @access   private
  * @var      array     $instance         The widget settings saved in the
  *                                         database.
  * @return   array                       The widget settings saved in the
  *                                         database
  */
  private function blipper_widget_get_display_values( $instance ) {


    error_log( "blipper_widget_get_display_values()\nInput (instance):\n" . var_export( $instance, true ) );

    // Allow the title field to be blank, but if it hasn't been set yet
    // (because the widget has only just been added to the widget-enabled area,
    // for example), set it to the default.
    $new_instance['title'] = array_key_exists( 'title', $instance ) ? esc_attr( $instance['title'] ) : $this->default_setting_values['title'];
    $new_instance['display-date'] = array_key_exists( 'display-date', $instance ) ? esc_attr( $instance['display-date'] ) :$this->default_setting_values['display-date'];
    $new_instance['display-journal-title'] = array_key_exists( 'display-journal-title', $instance ) ? esc_attr( $instance['display-journal-title'] ) : $this->default_setting_values['display-journal-title'];
    $new_instance['add-link-to-blip'] = array_key_exists( 'add-link-to-blip', $instance ) ? esc_attr( $instance['add-link-to-blip'] ) : $this->default_setting_values['add-link-to-blip'];
    $new_instance['powered-by'] = array_key_exists( 'powered-by', $instance ) ? esc_attr( $instance['powered-by'] ) : $this->default_setting_values['powered-by'];

    error_log( "\nOutput:\n" . var_export( $new_instance, true ) );

    return $new_instance;

  }

/**
  * Load the files this widget needs.
  *
  * @since    0.0.1
  * @access   private
  */
  private function load_dependencies() {

    require( plugin_dir_path( __FILE__ ) . 'includes/class-settings.php' );

    $this->load_blipfoto_dependencies();

  }

/**
  * Load the Blipfoto API.
  *
  * @since    0.0.1
  * @access   private
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
  * Construct an instance of the Polaroid|Blipfoto client and test it's ok
  * 
  * @since    0.0.1
  * @access   private
  * @param    array     $instance         The settings just saved in the
  *                                         database.
  * @return   bool      $client_ok        True if the client was created
  *                                         successfully, else false
  */
  private function blipper_widget_create_blipfoto_client( $instance ) {

    error_log( "blipper_widget_create_blipfoto_client()\nInput (instance):\n" . var_export( $instance, true ) );

    $client_ok = false;
    $this->client = null;
    try {

      // Get the settings from the database
      $oauth_settings = $this->settings->blipper_widget_get_settings();
      if ( empty( $oauth_settings['username'] ) ) {

        throw new blipper_widget_OAuthException( 'Missing username.' );
      }
      if ( empty( $oauth_settings['access-token'] ) ) {

        throw new blipper_widget_OAuthException( 'Missing access token.' );

      }

      // Create a new client using the OAuth settings from the database

      $this->client = new blipper_widget_Client (
        '',
        '',
        $oauth_settings['access-token']
      );
      // error_log( var_export($this->client, true) );
      if ( empty( $this->client ) || ! isset( $this->client ) ) {

        throw new blipper_widget_ApiResponseException( 'Failed to create the Polaroid|Blipfoto client.' );

      } else {

        $client_ok = true;

      }

    } catch ( blipper_widget_OAuthException $e ) {

      if ( current_user_can( 'manage_options' ) ) {
        echo '<p>' . __( 'OAuth error.  ' . $e->getMessage() . '  Please check your OAuth settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.', 'blipper-widget' ) . '</p>';
      }

    } catch ( blipper_widget_ApiResponseException $e ) {

      if ( current_user_can( 'manage_options' ) ) {
        echo '<p>' . __( 'Polaroid|Blipfoto error.  ' . $e->getMessage() . 'Please try again later.', 'blipper-widget' ) . '</p>';
      }

    }

    if ( true == $client_ok ) {
      $client_ok = false;
      try {
        $user_profile = $this->client->get(
          'user/profile'
        );
        if ( $user_profile->error() ) {

          throw new blipper_widget_ApiResponseException( $user_profile->error() );

        }

        $user = $user_profile->data()['user'];

        if ( $user['username'] != $oauth_settings['username'] ) {

          throw new blipper_widget_OAuthException( 'Unable to verify user.  Please check the username you entered on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> is correct.' );

        } else {

          $client_ok = true;

        }

      } catch ( blipper_widget_OAuthException $e ) {

        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>' . __( 'OAuth error.  ' . $e->getMessage(), 'blipper-widget' ) . '</p>';
        }

      } catch ( blipper_widget_ApiResponseException $e ) {

        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>' . __( 'Polaroid|Blipfoto error.  ' . $e->getMessage(), 'blipper-widget' ) . '</p>';
        }

      } catch ( blipper_widget_BaseException $e ) {

        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>' . __( 'Error.  ' . $e->getMessage(), 'blipper-widget' ) . '</p>';
        }

      }
    }
    return $client_ok;

  }


/**
  * Display the blip.
  *
  * @since    0.0.1
  * @access   private
  * @param    array     $instance         The settings saved in the database
  */
  private function blipper_widget_display_blip( $instance ) {

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
        if ( empty( $user ) ) {
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
        // Together, these ensure that the most recent blip is obtained — which
        // is exactly what we want to display.
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
            throw new Exception( 'No blips found.  Do you have <a href="https://www.polaroidblipfoto.com/' . $user['username'] . '" rel="nofollow">any Polaroid|Blipfoto entries</a>?');
          break;
          case 1:
            $continue = true;
          break;
          default:
            throw new ErrorException( count( $blips ) . ' blips found, but was only looking for 1.  Something has gone wrong.  Please try again.');
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

      // Polaroid|Blipfoto has different quality images, each with its own URL.
      // Access is currently limited to standard, but I've optimistically
      // allowed for higher quality images to be selected if they're present.
      // The lowest quality image is obtained if the standard image isn't
      // available.
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
          throw new ErrorException('Unable to get URL of image.');
        }

      } catch ( ErrorException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>Error.  ' . $e->getMessage() . '</p>';
        }
      }

      $continue = !empty ( $image_url );
    }

    if ( $continue ) {
      $continue = false;

      // Display the blip.
      echo '<figure style="border-width:' . $this->blipper_widget_get_style( 'border-width') . ';border-style: ' .  $this->blipper_widget_get_style( 'border-style') . ';border-color:' .  $this->blipper_widget_get_style( 'border-color') . '">';

      // Link back to the blip on the Polaroid|Blipfoto site.
      if ( $instance['add-link-to-blip'] ) {
        echo '<a href="https://www.polaroidblipfoto.com/entry/' . $blip['entry_id_str'] . '" rel="nofollow">';
      }
      // Add the image.
      echo '<img src="' . $image_url . '" 
        class="blipper-widget-image"
        alt="' . $blip['title'] . '" 
        height="auto" 
        width="auto">
      ';
      // Close the link (anchor) tag.
      if ( $instance['add-link-to-blip'] ) {
        echo '</a>';
      }

      // Display any associated data.
      echo '<figcaption style="padding-top:7px">';

      // Date (optional) and title
      if ( $instance['display-date'] == 'show' ) {
        echo date( get_option( 'date_format' ), $blip['date_stamp'] );
        if ( !empty( $blip['title'] ) ) {
          echo '<br>';
        }
      }
      echo $blip['title'];

      // Journal title and/or powered-by link.
      if ( $instance['display-journal-title'] == 'show' && $instance['powered-by'] == 'show' ) {
        echo '<footer><p style="font-size:70%">From <a href="https://www.polaroidblipfoto.com/' . $user_settings->data( 'username' ) . '" rel="nofollow">' . $user_settings->data( 'journal_title' ) . '</a> | Powered by <a href="https://www.polaroidblipfoto.com/" rel="nofollow">Polaroid|Blipfoto</a></p></footer>';
      } else if ( $instance['display-journal-title'] == 'show' && $instance['powered-by'] == 'hide' ) {
        echo '<footer><p style="font-size:70%">From <a href="https://www.polaroidblipfoto.com/' . $user_settings->data( 'username' ) . '" rel="nofollow">' . $user_settings->data( 'journal_title' ) . '</a></p></footer>';
      } else if ( $instance['display-journal-title'] == 'hide' && $instance['powered-by'] == 'show' ) {
        echo '<footer><p style="font-size:70%">Powered by <a href="https://www.polaroidblipfoto.com/" rel="nofollow">Polaroid|Blipfoto</a></p></footer>';
      }

      echo '</figcaption></figure>';

    }
  }

/**
  * Display the back-end widget form.
  *
  * @since     0.0.1
  * @access    private
  * @param     array         $instance       The settings saved in the database
  */
  private function blipper_widget_display_form( $instance ) {

    $oauth_settings = $this->settings->blipper_widget_get_settings();

    if ( empty( $oauth_settings['username'] ) ||
         empty( $oauth_settings['access-token'] )

      ) {

      echo '<p>You need to set the Polaroid|Blipfoto settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.</p>';

    } else {

      error_log( "blipper_widget_display_form()\nInput (instance):\n" . var_export( $instance, true ) );

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
      <p class="description">Leave the widget title field blank if you don't want to display a title.  The default widget title is <i><?php _e( $this->default_setting_values['title'] ); ?></i>.</p>

      <p>
        <input
          class="fatwide"
          id="<?php echo $this->get_field_id( 'display-date' ); ?>"
          name="<?php echo $this->get_field_name( 'display-date' ); ?>"
          type="checkbox"
          value="1"
          <?php echo esc_attr( $instance['display-date'] ) == 'show' ? 'checked="checked"' : ''; ?>
        >
        <label for="<?php echo $this->get_field_id( 'display-date' ); ?>">
          <?php _e( 'Display date of your latest blip', 'display-date' ) ?>
        </label>
      </p>
      <p class="description">Untick the box to hide the date of your latest blip.  Leave it ticked if you want to display the date of your latest blip.  The box is ticked by default.</p>

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
      <p class="description">Tick the box to include a link from the image link back to the corresponding blip in your journal.  Leave it unticked if you don't want to include a link back to your latest blip.  The box is unticked by default.</p>

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
      <p class="description">Tick the box to show the name of your journal with a link back to your Polaroid|Blipfoto journal.  Leave it unticked if you don't want to show the name of your journal and link back to your journal.  The box is unticked by default.</p>

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
      <p class="description">Tick the box to include a 'powered by' link back to Polaroid|Blipfoto.  Leave it unticked if you don't want to include a 'powered by'-style link.  The box is unticked by default.</p>

      <?php
    }

  }

  private function blipper_widget_get_style( $style_element ) {

    return $this->default_styles[$style_element];

  }

}

