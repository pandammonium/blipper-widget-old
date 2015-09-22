<?php 

/**
 * Widget settings.
 *
 * These settings are set from the WP Blipper Settings page, as opposed to on
 * the back-end widget form.  They are settings that are unlikely to be changed
 * after they have been set.  The settings on the back-end form of the widget
 * are more to do with the appearance of the front-end widget.  Therefore, it
 * makes sense to keep them separate.
 */

namespace wp_blipper_widget;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use wpbw_Blipfoto\wpbw_Api\wpbw_Client;
use wpbw_Blipfoto\wpbw_Exceptions\wpbw_ApiResponseException;

/**
 * Widget settings.
 *
 * @since 0.0.2
 */
class wpbw_Settings {

  private $wp_blipper_widget_defaults = array(
      'client-id'     => '',
      'client-secret' => '',
      'access-token'  => ''
    );
  private $wp_blipper_widget_settings;

/**
  * Construct an instance of the settings.
  * 
  * @since     0.0.2
  * @access    public
  */
  public function __construct() {

    add_action( 'admin_menu', array( &$this, 'wp_blipper_widget_admin_menu' ) );
    // Ensure the admin page is initialised only when needed:
      // Not calling this results in repeated error messages, if error messages are displayed.
      // Repeated error messages look pants.
    if ( ! empty ( $GLOBALS['pagenow'] )
      and ( 'options-general.php' === $GLOBALS['pagenow']
      or 'options.php' === $GLOBALS['pagenow']
      )
    ) {
      add_action( 'admin_init', array( &$this, 'wp_blipper_widget_admin_init' ) );
    }

  }

/**
  * Create a new settings page for the widget in the WP admin settings menu.
  * 
  * @since     0.0.2
  * @access    public
  */
  public function wp_blipper_widget_admin_menu() {

    add_options_page( 
      __( 'WP Blipper Widget Settings', 'wp-blipper-widget' ), // page title (not to be confused with page header)
      __( 'WP Blipper Widget', 'wp-blipper-widget' ), // menu title
      'manage_options', // capability req'd to access options page
      'options-wp-blipper-widget', // menu slug
      array( &$this, 'wp_blipper_widget_options_page' ) // callback function
    );
  }

/**
  * Set up the settings form on the settings page.
  * 
  * @since     0.0.2
  * @access    public
  */
  public function wp_blipper_widget_admin_init() {

    register_setting(
      'wp-blipper-widget-settings', // option group
      'wp-blipper-widget-settings-oauth', // option name
      array( &$this, 'wp_blipper_widget_oauth_validate' ) // callback function to validate input
    );

    add_settings_section(
      'wp-blipper-widget-oauth', // section id
      __( 'Polaroid|Blipfoto OAuth 2.0 Settings', 'wp-blipper-widget' ), // section title
      array( &$this, 'wp_blipper_widget_oauth_instructions'), // section callback function to render information and instructions about this section
      'options-wp-blipper-widget' // page id (i.e. menu slug)
    );

    add_settings_field(
      'wp-blipper-widget-oauth-client-id', // field id
      __( 'Polaroid|Blipfoto Client ID', 'wp-blipper-widget' ), // field title
      array( &$this, 'wp_blipper_field_render'), //callback function to render the field on the form
      'options-wp-blipper-widget', // page id (i.e. menu slug)
      'wp-blipper-widget-oauth', // section id the field belongs to
      array(
        'type'        => 'text',
        'name'        => 'wp-blipper-widget-settings-oauth[client-id]',
        'placeholder' => __( 'Enter your Polaroid|Blipfoto client ID here', 'wp-blipper-widget' ),
        'id'          => 'wp-blipper-widget-input-client-id',
        'setting'     => 'client-id',
      ) // arguments for the callback function
    );
    add_settings_field(
      'wp-blipper-widget-oauth-client-secret',
      __( 'Polaroid|Blipfoto Client Secret', 'wp-blipper-widget' ),
      array( &$this, 'wp_blipper_field_render'),
      'options-wp-blipper-widget',
      'wp-blipper-widget-oauth',
      array(
        'type'        => 'text',
        'name'        => 'wp-blipper-widget-settings-oauth[client-secret]',
        'placeholder' => __( 'Enter your Polaroid|Blipfoto client secret here', 'wp-blipper-widget' ),
        'id'          => 'wp-blipper-widget-input-client-secret',
        'setting'     => 'client-secret',
      )
    );
    add_settings_field(
      'wp-blipper-widget-oauth-access-token',
      __( 'Polaroid|Blipfoto Access Token', 'wp-blipper-widget' ),
      array( &$this, 'wp_blipper_field_render'),
      'options-wp-blipper-widget',
      'wp-blipper-widget-oauth',
      array(
        'type'        => 'text',
        'name'        => 'wp-blipper-widget-settings-oauth[access-token]',
        'placeholder' => __( 'Enter your Polaroid|Blipfoto access token here', 'wp-blipper-widget' ),
        'id'          => 'wp-blipper-widget-input-access-token',
        'setting'     => 'access-token',
      )
    );

  }

  /**
   * Callback function.
   * Output the value, if there is one, in an input field.
   *
   * @since     0.0.2
   * @access    public
   */
  public function wp_blipper_field_render( $args ) {

    $settings = get_option( 'wp-blipper-widget-settings-oauth' );
    if ( $settings ) {
      $value = $settings[$args['setting']];
    } else {
      $value = $this->wp_blipper_widget_defaults[$args['setting']];
    }

    ?>
      <input type="<?php echo $args['type']; ?>" id="<?php echo $args['id']; ?>" name="<?php echo $args['name']; ?>" placeholder="<?php echo $args['placeholder']; ?>" value="<?php echo $value; ?>" size="50">      
    <?php

  }

/**
  * Render the options page.
  *
  * @since     0.0.2
  * @access    public
  */
  public function wp_blipper_widget_options_page() {

    ?>
    <div class="wrap">
      <h2><?php echo __( 'WP Blipper Widget Settings', 'wp-blipper-widget' ); ?></h2>
      <?php
      if ( !current_user_can( 'manage_options' ) ) {
        wp_die( __( '', 'wp-blipper-widget' ) );
      } else {
        ?>
        <form action="options.php" method="POST">
          <?php
            // render a few hidden fields that tell WP which settings are going to be updated on this page:
            settings_fields( 'wp-blipper-widget-settings' );
            // output all the sections and fields that have been added to the options page (with slug options-wp-blipper):
            do_settings_sections( 'options-wp-blipper-widget' );
          ?>
          <?php submit_button(); ?>
        </form>
        <?php
        }
      ?>
    </div>
    <?php

  }

/**
  * Validate the input.
  * Make sure the input comprises only printable/alphanumeric (depending on the field) characters; otherwise, return an empty string/the default value.
  *
  * This might become a loop at some point.
  *
  * @since     0.0.2
  * @access    public
  * @var       array       $input               An array containing the settings that the user wants to set.
  * @return    string      $output               The validated setting.
  */
  public function wp_blipper_widget_oauth_validate( $input ) {

    $output = $this->wp_blipper_widget_defaults;

    if ( !is_array( $input ) ) {

      add_settings_error(
        'wp-blipper-settings-group', 
        'inavlid-input', 
        __( 'Something has gone wrong.  Please check the settings.', 'wp-blipper-widget' )
      );

    } else {

      $settings = get_option( 'wp-blipper-widget-settings-oauth' );
      if ( false === $settings ) {
        add_settings_error(
          'wp-blipper-settings-group', 
          'invalid-oauth', 
          __( 'No settings have been set yet.', 'wp-blipper-widget' )
        );

      } else {

        $input['client-id'] = trim( esc_attr( $input['client-id'] ) );
        if ( true === ctype_alnum( $input['client-id'] ) ) {
          $output['client-id'] = $input['client-id'];
        } else if ( empty( $input['client-id'] ) ) {
          add_settings_error(
            'wp-blipper-settings-group', 
            'missing-oauth-client-id', 
            __( 'Please enter a value for the client ID.', 'wp-blipper-widget' )
          );
        } else {
          add_settings_error(
            'wp-blipper-settings-group', 
            'invalid-oauth-client-id', 
            __( 'Please enter alphanumeric characters only for the client ID.', 'wp-blipper-widget' )
          );
          $output['client-id'] = '';
        }

        $input['client-secret'] = trim( esc_attr( $input['client-secret'] ) );
        if ( true === ctype_alnum( $input['client-secret'] ) ) {
          $output['client-secret'] = $input['client-secret'];
        } else if ( empty( $input['client-secret'] ) ) {
          add_settings_error(
            'wp-blipper-settings-group', 
            'missing-oauth-client-secret', 
            __( 'Please enter a value for the client secret.', 'wp-blipper-widget' )
          );
        } else {
          add_settings_error(
            'wp-blipper-settings-group', 
            'invalid-oauth-client-secret', 
            __( 'Please enter alphanumeric characters only for the client secret.', 'wp-blipper-widget' )
          );
          $output['client-secret'] = '';
        }

        $input['access-token'] = trim( esc_attr( $input['access-token'] ) );
        if ( true === ctype_alnum( $input['access-token'] ) ) {
          $output['access-token'] = $input['access-token'];
        } else if ( empty( $input['access-token'] ) ) {
          add_settings_error(
            'wp-blipper-settings-group', 
            'missing-oauth-access-token', 
            __( 'Please enter a value for the access token.', 'wp-blipper-widget' )
          );
        } else {
          add_settings_error(
            'wp-blipper-settings-group', 
            'invalid-oauth-access-token', 
            __( 'Please enter alphanumeric characters only for the access token.', 'wp-blipper-widget' )
          );
          $output['access-token'] = '';
        }

        $this->wp_blipper_widget_test_connection( $output );

      }

    }

    return $output;

  }

  /**
   * Callback function.
   * Output the instructions for setting the plugin's options.
   *
   * @since     0.0.2
   * @access    public
   */
  public function wp_blipper_widget_oauth_instructions() {

    ?>

      <p>You need to authorise access to your Polaroid|Blipfoto account before you can use this plugin.  <em>You can revoke access at any time.</em>  Don't worry: it's not as scary as it looks!  The instructions below tell you how to authorise access and how to revoke access.</p>
      <h4>How to authorise your Polaroid|Blipfoto account</h4>
      <p>To allow WordPress to access your Polaroid|Blipfoto account, you need to carry out a few simple steps:</p>
      <ol>
        <li>Open the <a href="https://www.polaroidblipfoto.com/developer/apps" rel="nofollow">the Polaroid|Blipfoto apps page</a> in a new tab or window.</li>
        <li>Press the <i>Create new app</i> button.</li>
        <li>In the <i>Name</i> field, give your app any name you like, for example, <i>My super-duper app</i>.</li>
        <li>The <i>Type</i> field should be set to <i>Web application</i>.</li>
        <li>Optionally, describe your app in the <i>Description</i> field, so you know what it does.</li>
        <li>In the <i>Website</i> field, enter the URL of your website (most likely <code><?php echo home_url(); ?></code>).</li>
        <li>Leave the <i>Redirect URI</i> field blank.</li>
        <li>Indicate that you agree to the <i>Developer rules</i>.</li>
        <li>Press the <i>Create a new app</i> button.</li>
        <li>You should now see your <i>Client ID</i>, <i>Client Secret</i> and <i>Access Token</i>.  Copy and paste these into the corresponding fields below.</li>
      </ol>
      <p>Note that <em>WP Blipper Widget does not need your username or password</em>.  Whereas it is possible for this plugin to obtain your username from the Polaroid|Blipfoto API, it is not possible to obtain or view your password.</p>
    <h4>How to revoke access to your Polaroid|Blipfoto account</h4>
    <p>It's simple to revoke access.  We hope you don't want to do this, but if you do, the instructions are laid out below:</p>
    <ol>
      <li>Go to <a href="https://www.polaroidblipfoto.com/settings/apps" rel="nofollow">your Polaroid|Blipfoto app settings</a>.</li>
      <li>Select the app whose access you want to revoke (the one you created using the above instructions).</li>
      <li>Press the <i>Save changes</i> button.</li>
    </ol>
    <?php

  }

  /**
   * Checks whether the OAuth credentials are valid or not.
   * A temporay client is created using the settings given.  If the settings are
   * invalid, an exception will be thrown when the client is used to get data
   * from Polaroid|Blipfoto.
   *
   * @since     0.0.2
   * @access    public
   * @param     array     The OAuth settings being proposed by the user.
   */
  private function wp_blipper_widget_test_connection( $settings ) {

    $client = null;
    try {
      $client = new wpbw_Client (
       $settings['client-id'],
       $settings['client-secret'],
       $settings['access-token']
      );
    } catch ( wpbw_ApiResponseException $e ) {
      add_settings_error( 
        'wp-blipper-settings-group',
        'invalid-oauth-credentials',
        __( 'Unable to connect to Polaroid|Blipfoto.  Please check the OAuth settings.', 'wp-blipper-widget' )
      );
    }
    try {
      $user_profile = $client->get(
        'user/profile'
      );
    } catch ( wpbw_ApiResponseException $e ) {
      add_settings_error( 
        'wp-blipper-settings-group',
        'invalid-oauth-credentials',
        __( 'Unable to connect to Polaroid|Blipfoto.<br>Please check you have correctly copied <a href="https://www.polaroidblipfoto.com/developer/apps" rel="nofollow">your OAuth credentials at Polaroid|Blipfoto</a> and pasted them into the settings below.', 'wp-blipper-widget' )
      );
    }

  }

  /**
   * Return the name of the options key in the database
   * (see wp_blipper_widget_admin_init)
   *
   * @since     0.0.2
   * @access    public
   * @return    string    The string used as the key in the database, which
   *                      stores the widget's OAuth settings.
   */
  public function wp_blipper_widget_settings_db_name() {
    return 'wp-blipper-widget-settings-oauth';
  }


}