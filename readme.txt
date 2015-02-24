=== WP to Diaspora ===
Contributors: gutobenn, noplanman
Donate link: http://github.com/gutobenn/wp-to-diaspora/
Tags: diaspora
Requires at least: 3.2.1
Tested up to: 4.1
Stable tag: 1.2.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically shares WordPress posts on Diaspora*

== Description ==

Automatically shares WordPress posts on Diaspora*

= What's diaspora*? =
diaspora* is a decentralized social network. More about on https://diasporafoundation.org/

= i18n =
Available:

* Portuguese (Brazil)
* Russian -- contributed by [Vitalie Ciubotaru](http://ciubotaru.tk)
* Japanese -- ""
* Romanian -- ""
* French -- contributed by [Fabián Rodriguez](http://fabianrodriguez.com)
* German -- contributed by [Katrin Leinweber](http://www.konscience.de)
* Italian -- contributed by [Giulio Roberti](http://www.viroproject.com)
* Spanish -- contributed by [Armando Lüscher](http://www.noplanman.ch)

= Development =
https://github.com/gutobenn/wp-to-diaspora

= Credit =
Dandelion banner image: [Pixabay](http://pixabay.com/en/dandelion-sky-flower-nature-seeds-463928/)

== Installation ==

1. Upload 'wp-to-diaspora' folder to the '/wp-content/plugins/' directory
2. Go to the options page and set your pod, username and password

== Screenshots ==

1. Configuration page
2. Meta box on post editor page
3. Example post on Diaspora*
4. Example post on WordPress

== Changelog ==

= 1.2.7 =
* Custom post types support added
* Posting to specific aspects is now possible
* Password encryption
* Prepopulated list of pods
* Admin notices after posting

= 1.2.6 =
* Added support for videos embedding using [embed] shortcode
* Hashtags support
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
What's new: Custom post types support; Posting to specific aspects is now possible; There's a prepopulated list of pods; Password is now encrypted on database; Admin notices are displayed after posting.
