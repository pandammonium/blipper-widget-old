=== Plugin Name ===
Contributors: pandammonium
Tags: photos,photo,blipfoto,polaroid,widget,polaroid blipfoto
Requires at least: 4.3
Tested up to: 4.3
Stable tag: master
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Displays the latest entry on Polaroid|Blipfoto by a given user in a widget.

== Description ==

Displays the latest entry on Polaroid|Blipfoto by a given user in a widget on your WordPress website.  Note: you must have an account with Polaroid|Blipfoto to use this widget.  If you don't have one, you can [easily get one](https://www.polaroidblipfoto.com/account/signup).

[Polaroid|Blipfoto](https://www.polaroidblipfoto.com/) is a photo journal service, allowing users to post one photo a day along with descriptive text and tags.

A limited number of extra photos are allowed to be uploaded to a Polaroid|Blipfoto entry, but this plugin does not interact with those.

== Installation ==

1. Upload `wp-blipper.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php do_action('plugin_name_hook'); ?>` in your templates ???
1. You'll need OAuth2 credentials from Polaroid|Blipfoto; the plugin has details of how to obtain these.  Note that you must have a Polaroid|Blipfoto account in order to use this plugin.

== Changelog ==

= 0.0.1 =
* Initial version.
