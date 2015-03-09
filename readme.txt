=== WP to diaspora ===
Contributors: gutobenn, noplanman
Donate link: http://github.com/gutobenn/wp-to-diaspora/
Tags: diaspora, integration, share, post
Requires at least: 3.5
Tested up to: 4.1
Stable tag: 1.3.1
Author URI: http://github.com/gutobenn
Plugin URI: http://github.com/gutobenn/wp-to-diaspora/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically share your WordPress posts on your diaspora* profile.

== Description ==

With WP to diaspora* and just a few clicks, you can automatically share your WordPress posts on your diaspora* profile.

With the focus on being **intuitive**, sharing your posts to diaspora* is made **as easy as possible**.

Simply write your post and have it shared to diaspora* at the same time you publish it on your website.

= Minimum requirements =
WordPress 3.5, PHP 5.3 with [cURL extension](https://php.net/manual/book.curl.php).

= Getting started =
After installing and activating the plugin, be sure to visit the plugin's settings page at 'Settings -> WP to diaspora' on your WordPress Admin page.
Simply add your diaspora* pod, username and password to get started.

On this page you can also define the default behaviour when publishing new posts.
These settings can easily be adjusted for each individual post by using the meta box that has been added to the post types that you have selected.

= What's diaspora*? =
diaspora* is a decentralized social network. More about it on https://diasporafoundation.org/

= This plugin speaks your language =
* Portuguese (Brazil)
* Russian -- contributed by [Vitalie Ciubotaru](http://ciubotaru.tk)
* Japanese -- ""
* Romanian -- ""
* French -- contributed by [Fabián Rodriguez](http://fabianrodriguez.com)
* German -- contributed by [Katrin Leinweber](http://www.konscience.de)
* Italian -- contributed by [Giulio Roberti](http://www.viroproject.com)
* Spanish -- contributed by [Armando Lüscher](http://noplanman.ch)

Your language isn't listed? Then feel free to [contribute your language skills](https://poeditor.com/join/project?hash=c085b3654a5e04c69ec942e0f136716a) and help make this plugin more accessible!

= Development =
This plugin is completely open source and is a work of love by all who have contributed.
If you would like to be part of it and join in, make your way over to the [project page on GitHub](https://github.com/gutobenn/wp-to-diaspora) now.
Also, if you have an idea you would like to see in this plugin or if you've found a bug, please [let us know](https://github.com/gutobenn/wp-to-diaspora/issues/new).

= Credits =
* Dandelion banner image: [Pixabay](http://pixabay.com/en/dandelion-sky-flower-nature-seeds-463928/)

== Installation ==

You can either use the built in WordPress installer or install the plugin manually.

For an automated installation:
1. Go to 'Plugins -> Add New' on your WordPress Admin page.
2. Search for the 'WP to diaspora' plugin.
3. Install by clicking the 'Install Now' button.
4. Activate the plugin on the 'Plugins' page in your WordPress Admin.

For a manual installation:
1. Upload 'wp-to-diaspora' folder to the '/wp-content/plugins/' directory of your WordPress installation.
2. Activate the plugin on the 'Plugins' page in your WordPress Admin.

== Screenshots ==

1. Configuration page
2. Meta box on post editor page
3. Example post on diaspora*
4. Example post on WordPress

== Learn ==

A side effect of this plugin is to help anybody who is interested to learn from and especially understand how the insides of this plugin work.
When you look into the code, you will see that it is well documented, making it easier for you to understand what's going on.
We encourage you to discover something new and learn a thing or two!

= How does this plugin do it? =
So you want to know what happens in the background, to get your post to diaspora*?
Well, this might be a little technical, but it's simple enough to understand the concept.

Basically, the server your WordPress installation is set up on makes a connection to your diaspora* pod, logs you in using the provided username and password, adds a new post (which is your WordPress post) and responds to say if everything was successful.

Quite straightforward, right?

== Changelog ==

= 1.3.2 =
* Posting to connected services via diaspora* is now possible

= 1.3.1 =
* Security improved by removing the cookie jar so that the cookie is never written to the temporary folder on the server
* Simple debug feature added when appending `&debugging` to the URL on the settings page

= 1.3.0 =
* Internal code restructuring, making the plugin completely OOP
* Updated readme file structure and add more details about the plugin

= 1.2.7 =
* Custom post types support added
* Posting to specific aspects is now possible
* Password encryption added to save the user credentials safely
* Pre-populated list of pods for easier selection when setting up the plugin
* Admin notices after posting to diaspora*, WordPress style

= 1.2.6 =
* Added support for videos embedding using [embed] shortcode
* Hashtags support to add global and custom hashtags to posts
* Posting Defaults configuration
* Interface improvements and some bug fixed

= 1.2.5.2 =
* Fixes a bug included in on 1.2.4: not able to set 'full entry on' link false

= 1.2.4 =
* Possibility of choosing to post between 'full post' and 'excerpt'
* Fixed bug: password overwritten when updating settings
* Translation term fixed

= 1.2.3 =
* Support servers with safe_mode enabled
* German translation

= 1.2.2 =
* New translations: Russian, Japanese, Romanian, French. Partially translated: Spanish, German, Italian (translators needed!)
* Fixed conflict with plugins that use the_content filters
* Fixed conflict with plugins using same libraries

= 1.2.1 =
* Connection test added
* Enable / Disable 'full entry on' link

= 1.2 =
* Image support added
* Posts are now converted to markdown before sharing on diaspora

= 1.1 =
* Possibility of posting or not to diaspora*
* Required fields
* i18n support
* pt_BR translation

= 1.0 =
* First version

== Upgrade Notice ==
* This version contains security improvements and includes and a debugger
