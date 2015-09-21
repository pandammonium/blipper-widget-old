=== Plugin Name ===
Contributors: pandammonium
Tags: photos,photo,blipfoto,polaroid,widget,polaroid blipfoto
Requires at least: 4.3
Tested up to: 4.3
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Displays the latest entry on Polaroid|Blipfoto by a given user in a widget.

== Description ==

Displays the latest entry on Polaroid|Blipfoto by a given user in a widget on your WordPress website.  Note: you must have an account with Polaroid|Blipfoto to use this widget.  If you don't have one, you can [easily get one from Polaroid|Blipfoto](https://www.polaroidblipfoto.com/account/signup).

The image shown is hotlinked to from your website.  It is not stored locally, otherwise you could run into storage problems on your server.

[Polaroid|Blipfoto](https://www.polaroidblipfoto.com/) is a photo journal service, allowing users to post one photo a day along with descriptive text and tags.  It uses OAuth 2.0 to ensure that your password is kept secure.  You will need to obtain these from Polaroid|Blipfoto.  This is a straightforward process and instructions are given below.

NB By using this app, you consent to this plugin performing actions involving your account, including, but not limited to, obtaining your account details (excluding username and password).  By using this widget, you also consent to sharing any content or private information (excluding username and password) with…?

This plug-in is loosely based on BlipPress by Simon Blackbourne.

== Frequently Asked Questions ==

= Do you have a Polaroid|Blipfoto account? =
Yes, my username is [pandammonium](https://www.polaroidblipfoto.com/pandammonium).

== Installation ==

1. Upload `wp-blipper.php` to the `/wp-content/plugins/` directory or use the automatic plugin installer.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Add the WP Blipper Widget to a widget-enabled area.

You'll need OAuth credentials from Polaroid|Blipfoto:

1. Open the the Polaroid|Blipfoto apps page in a new tab or window.
1. Press the Create new app button.
1. In the Name field, give your app any name you like, for example, My super-duper app.
1. The Type field should be set to Web application.
1. Optionally, describe your app in the Description field, so you know what it does.
1. In the Website field, enter the URL of your website.
1. Leave the Redirect URI field blank.
1. Indicate that you agree to the Developer rules.
1. Press the Create a new app button.
1. You should now see your Client ID, Client Secret and Access Token. Copy and paste these into the corresponding fields below.

== Screenshots ==

1. An example of the plugin in use in the Twenty Fifteen light theme.
2. An example of the plugin in use in the Twenty Fifteen dark theme.
3. An example of the plugin in use in the Make theme (taken from my website at [pandammonium.org](http://pandammonium.org/)).
4. Part of the back-end widget form in the admin area.

== Changelog ==

= 0.0.1 =
Initial version.
