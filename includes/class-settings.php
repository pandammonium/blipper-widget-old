<?php 

/**
  * Widget settings.
  *
  * These settings are set from the Blipper Widhet settings page, as opposed to
  * on the back-end widget form.  They are settings, such as OAuth credentials,
  * that are unlikely to be changed after they have been set.  The settings on
  * the back-end form of the widget are more to do with the appearance of the
  * front-end widget.  Therefore, it makes sense to keep them separate.
  */

namespace blipper_widget;

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use blipper_widget_Blipfoto\blipper_widget_Api\blipper_widget_client;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_ApiResponseException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_OAuthException;

// -- Blipper Widget Settings ----------------------------------------------- //

/**
  * Widget settings.
  *
  * @since 0.0.2
  */
class blipper_widget_settings {

/**
  * @since    0.0.2
  * @access   private
  * @var      array    $blipper_widget_defaults   The widget's default settings
  */
  private $blipper_widget_defaults = array(
      'username'              => '',
      'access-token'          => '',
    );

/**
  * @since    0.0.2
  * @access   private
  * @var      array    $blipper_widget_settings   The widget's user-defined
  *                                                 settings
  */
  // private $blipper_widget_settings;

/**
  * Construct an instance of the settings.
  * 
  * @since     0.0.2
  * @access    public
  */
  public function __construct() {

    add_action( 'admin_menu', array( &$this, 'blipper_widget_admin_menu' ) );
    // Ensure the admin page is initialised only when needed:
    // Not calling this results in repeated error messages, if error messages
    // are displayed.  Repeated error messages look pants.
    if ( ! empty ( $GLOBALS['pagenow'] )
      and ( 'options-general.php' === $GLOBALS['pagenow']
      or 'options.php' === $GLOBALS['pagenow']
      or 'options-general/php?page=blipper-widget'  === $GLOBALS['pagenow']
      )
    ) {
      add_action( 'admin_init', array( &$this, 'blipper_widget_admin_init' ) );
    }

  }

/**
  * Create a new settings page for the widget in the WP admin settings menu.
  * 
  * @since     0.0.2
  * @access    public
  */
  public function blipper_widget_admin_menu() {

    add_options_page( 
      // page title (not to be confused with page header):
      __( 'Blipper Widget Settings', 'blipper-widget' ),
      // menu title:
      __( 'Blipper Widget', 'blipper-widget' ),
      // capability required to access options page:
      'manage_options',
      // menu slug:
      'blipper-widget',
      // callback function:
      array( &$this, 'blipper_widget_options_page' )
    );
  }

/**
  * Set up the settings form on the Blipper Widget settings page.
  * 
  * @since     0.0.2
  * @access    public
  */
  public function blipper_widget_admin_init() {

    register_setting(
      // option group:
      'blipper-widget-settings',
      // option name:
      'blipper-widget-settings-oauth',
      // callback function to validate input
      array( &$this, 'blipper_widget_oauth_validate' )
    );

    add_settings_section(
      // section id:
      'blipper-widget-oauth',
      // section title:
      __( 'Polaroid|Blipfoto OAuth 2.0 Settings', 'blipper-widget' ),
      // section callback function to render information and instructions about
      // this section:
      array( &$this, 'blipper_widget_oauth_instructions'),
      // page id (i.e. menu slug):
      'blipper-widget'
    );

    add_settings_field(
      // field id:
      'blipper-widget-username',
      // field title:
      __( 'Polaroid|Blipfoto Username', 'blipper-widget' ),
      // callback function to render the field on the form:
      array( &$this, 'wp_blipper_field_render'),
      // page id (i.e. menu slug):
      'blipper-widget',
      // section id the field belongs to:
      'blipper-widget-oauth',
      // arguments for the callback function:
      array(
        'type'        => 'text',
        'name'        => 'blipper-widget-settings-oauth[username]',
        'placeholder' => __( 'Enter your Polaroid|Blipfoto username here', 'blipper-widget' ),
        'id'          => 'blipper-widget-input-username',
        'setting'     => 'username',
      )
    );
    add_settings_field(
      // field id:
      'blipper-widget-oauth-access-token',
      // field title:
      __( 'Polaroid|Blipfoto Access Token', 'blipper-widget' ),
      // callback function to render the field on the form:
      array( &$this, 'wp_blipper_field_render'),
      // page id (i.e. menu slug):
      'blipper-widget',
      // section id the field belongs to:
      'blipper-widget-oauth',
      // arguments for the callback function:
      array(
        'type'        => 'text',
        'name'        => 'blipper-widget-settings-oauth[access-token]',
        'placeholder' => __( 'Enter your Polaroid|Blipfoto access token here', 'blipper-widget' ),
        'id'          => 'blipper-widget-input-access-token',
        'setting'     => 'access-token',
      )
    );

  }

  /**
   * Output the value, if there is one, in an input field.
   * Callback function.
   *
   * @since     0.0.2
   * @access    public
   */
  public function wp_blipper_field_render( $args ) {

    $settings = get_option( 'blipper-widget-settings-oauth' );
    $value = false == $settings ? $this->blipper_widget_defaults[$args['setting']] : $settings[$args['setting']];

    ?>
      <input type="<?php echo $args['type']; ?>" id="<?php echo $args['id']; ?>" name="<?php echo $args['name']; ?>" placeholder="<?php echo $args['placeholder']; ?>" value="<?php echo $value; ?>" size="50">      
    <?php

  }

/**
  * Render the options page.
  * Callback function.
  *
  * @since     0.0.2
  * @access    public
  */
  public function blipper_widget_options_page() {

    ?>
    <div class="wrap">
      <h2><?php echo __( 'Blipper Widget Settings', 'blipper-widget' ); ?></h2>
      <script type="text/javascript">pause('inside the options page')</script>
      <?php
      if ( !current_user_can( 'manage_options' ) ) {
        wp_die( __( '', 'blipper-widget' ) );
      } else {
        ?>
        <form action="options.php" method="POST">
          <?php
            // Render a few hidden fields that tell WP which settings are going
            // to be updated on this page:
            settings_fields( 'blipper-widget-settings' );
            // Output all the sections and fields that have been added to the
            // options page (with slug options-wp-blipper):
            do_settings_sections( 'blipper-widget' );
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
  * Validate the OAuth input.
  * Make sure the input comprises only printable/alphanumeric (depending on the
  * field) characters; otherwise, return an empty string/the default value.
  * Callback function.
  *
  * (This might become a loop at some point.)
  *
  * @since     0.0.2
  * @access    public
  * @var       array         $input             An array containing the settings
  *                                               that the user wants to set.
  * @return    string        $output            The validated setting.
  */
  public function blipper_widget_oauth_validate( $input ) {

    $output = $this->blipper_widget_defaults;

    if ( !is_array( $input ) ) {

      add_settings_error(
        'wp-blipper-settings-group', 
        'inavlid-input', 
        __( 'Something has gone wrong.  Please check the OAuth settings.', 'blipper-widget' )
      );

    } else {

      $settings = get_option( 'blipper-widget-settings-oauth' );

      $input['username'] = trim( esc_attr( $input['username'] ) );
      if ( true === ctype_print( $input['username'] ) ) {
        $output['username'] = $input['username'];
      } else if ( empty( $input['username'] ) ) {
        add_settings_error(
          'wp-blipper-settings-group', 
          'missing-oauth-username', 
          __( 'Please enter a value for the username.', 'blipper-widget' )
        );
      } else {
        add_settings_error(
          'wp-blipper-settings-group', 
          'invalid-oauth-access-token', 
          __( 'Please enter printable characters only for the username.', 'blipper-widget' )
        );
        $output['username'] = '';
      }

      $input['access-token'] = trim( esc_attr( $input['access-token'] ) );
      if ( true === ctype_alnum( $input['access-token'] ) ) {
        $output['access-token'] = $input['access-token'];
      } else if ( empty( $input['access-token'] ) ) {
        add_settings_error(
          'wp-blipper-settings-group', 
          'missing-oauth-access-token', 
          __( 'Please enter a value for the access token.', 'blipper-widget' )
        );
      } else {
        add_settings_error(
          'wp-blipper-settings-group', 
          'invalid-oauth-access-token', 
          __( 'Please enter alphanumeric characters only for the access token.', 'blipper-widget' )
        );
        $output['access-token'] = '';
      }

      $this->blipper_widget_test_connection( $output );

    }

    return $output;

  }

  /**
   * Output the instructions for setting the plugin's options.
   * Callback function.
   *
   * @since     0.0.2
   * @access    public
   */
  public function blipper_widget_oauth_instructions() {

    ?>

      <p>You need to authorise access to your Polaroid|Blipfoto account before you can use this plugin.  <em>You can revoke access at any time.</em></p><p>Just follow the instructions below to authorise access and to revoke access.</p>
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
        <li>You should now see your <i>Client ID</i>, <i>Client Secret</i> and <i>Access Token</i>.  Copy and paste your <i>Access Token</i> only into the corresponding field below.</li>
      </ol>
      <p><abbr title="Nota Bene 'note well'">NB</abbr> Whereas authorisation gives Blipper Widget permission to access your Polaroid|Blipfoto account, it does not give Blipper Widget access to your password.</p>
    <h4>How to revoke access to your Polaroid|Blipfoto account</h4>
    <p>It's simple to revoke access.  We hope you don't want to do this, but if you do, the instructions are laid out below:</p>
    <ol>
      <li>Go to <a href="https://www.polaroidblipfoto.com/settings/apps" rel="nofollow">your Polaroid|Blipfoto app settings</a>.</li>
      <li>Select the app whose access you want to revoke (the one you created using the above instructions).</li>
      <li>Press the <i>Save Changes</i> button.</li>
    </ol>
    <h4>Polaroid|Blipfoto username</h4>
    <p>You also need to enter your username in the appropriate field below.  The widget will check to see that the access token is valid for your account.

  <?php

  }

  /**
   * Checks whether the OAuth credentials are valid or not.
   * A temporary client is created using the settings given.  If the settings
   * are invalid, an exception will be thrown when the client is used to get
   * data from Polaroid|Blipfoto.
   *
   * @since     0.0.2
   * @access    public
   * @param     array     The OAuth settings being proposed by the user.
   */
  private function blipper_widget_test_connection( $oauth_settings ) {

    $client = null;
    $user_profile = null;
    try {
      $client = new blipper_widget_client (
       '',
       '',
       $oauth_settings['access-token']
      );
    } catch ( blipper_widget_ApiResponseException $e ) {
      add_settings_error( 
        'wp-blipper-settings-group',
        'invalid-oauth-credentials',
        __( 'Unable to connect to Polaroid|Blipfoto.  Please check the OAuth settings.', 'blipper-widget' )
      );
    }
    if ( !empty( $client ) && isset( $client ) ) {

      try {

        $user_profile = $client->get(
          'user/profile'
        );

        $user = $user_profile->data()['user'];

        if ( $user['username'] != $oauth_settings['username'] ) {
          throw new blipper_widget_OAuthException( 'Please check the username you entered is correct.' );
        }

      } catch ( blipper_widget_OAuthException $e ) {
        add_settings_error( 
          'wp-blipper-settings-group',
          'invalid-oauth-credentials',
          __( 'Error.  ' . $e->getMessage(), 'blipper-widget' )
        );
      } catch ( blipper_widget_ApiResponseException $e ) {
        add_settings_error( 
          'wp-blipper-settings-group',
          'invalid-oauth-credentials',
          __( 'Unable to connect to your Polaroid|Blipfoto user profile.<br>Please check you have correctly copied <a href="https://www.polaroidblipfoto.com/developer/apps" rel="nofollow">your access token at Polaroid|Blipfoto</a> and pasted it into the settings below.<br>If you have refreshed your Polaroid|Blipfoto OAuth access token, you need to update it below.<br>If you have entered it correctly, try <a href="https://www.polaroidblipfoto.com/developer/apps" rel="nofollow">refreshing your access token at Polaroid|Blipfoto</a>.', 'blipper-widget' )
        );
      }
    }
    return $client && $user_profile;
  }

  /**
   * Check if the settings have been set or not.
   *
   * @since     0.0.2
   * @access    public
   * @return    string    The string used as the key in the database, which
   *                        stores the widget's OAuth settings.
   */
  public function blipper_widget_settings_have_been_set() {

    return false != get_option( 'blipper-widget-settings-oauth' );

  }

  /**
   * Return the settings in the database
   *
   * @since     0.0.2
   * @access    public
   * @return    array     The settings in the database or false if not set.
   */
  public function blipper_widget_get_settings() {

    return get_option( 'blipper-widget-settings-oauth' );

  }

  /**
   * Return the name of the options key in the database
   * (see blipper_widget_admin_init)
   *
   * @since     0.0.2
   * @access    public
   * @return    string    The string used as the key in the database, which
   *                        stores the widget's OAuth settings.
   */
  public function blipper_widget_get_settings_db_name() {

    return 'blipper-widget-settings-oauth';

  }

}