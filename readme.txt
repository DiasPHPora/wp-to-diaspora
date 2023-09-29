=== WP to diaspora ===
Contributors: gutobenn, noplanman
Donate link: https://github.com/DiasPHPora/wp-to-diaspora/
Tags: diaspora, integration, share, post, social, network
Requires at least: 4.6
Tested up to: 6.3.1
Minimum PHP: 8.0
Stable tag: 4.0.0
Author URI: https://github.com/DiasPHPora/wp-to-diaspora/graphs/contributors
Plugin URI: https://github.com/DiasPHPora/wp-to-diaspora/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically share your WordPress posts on your diaspora* profile.

== Description ==

**We are looking for new maintainers for this plugin!**

**Get in touch with [@noplanman](https://noplanman.ch) if you'd like to help keep this project alive.**

With WP to diaspora* and just a few clicks, you can automatically share your WordPress posts on your diaspora* profile.

With the focus on being **intuitive**, sharing your posts to diaspora* is made **as easy as possible**.

Simply write your post and have it shared to diaspora* at the same time you publish it on your website.

= Minimum requirements =
WordPress 4.6, PHP 8.0.

= Getting started =
After installing and activating the plugin, be sure to visit the plugin's settings page at 'Settings -> WP to diaspora' on your WordPress Admin page.
Simply add your diaspora* pod, username and password to get started.

On this page you can also define the default behaviour when publishing new posts.
These settings can easily be adjusted for each individual post by using the meta box that has been added to the post types that you have selected.

Additional documentation can be found in our [wiki](https://github.com/DiasPHPora/wp-to-diaspora/wiki)

= What's diaspora*? =
diaspora* is a decentralized social network. Read more about the [diaspora* project](https://diasporafoundation.org/).

= This plugin speaks your language =
Many thanks to our many friendly translators, doing an amazing job.

Feel free to help and [contribute translations](https://translate.wordpress.org/projects/wp-plugins/wp-to-diaspora).

= Development =
**We are looking for new maintainers for this plugin!**

**Get in touch with [@noplanman](https://noplanman.ch) if you'd like to help keep this project alive.**

This plugin is completely open source and is a work of love by all who have [contributed](https://github.com/DiasPHPora/wp-to-diaspora/graphs/contributors).
If you would like to be part of it and join in, make your way over to the [project page on GitHub](https://github.com/DiasPHPora/wp-to-diaspora) now.
Also, if you have an idea you would like to see in this plugin or if you've found a bug, please [let us know](https://github.com/DiasPHPora/wp-to-diaspora/issues/new).

= Credits =
* Dandelion banner image: [Pixabay](https://pixabay.com/en/dandelion-sky-flower-nature-seeds-463928/)

== Installation ==

You can either use the built-in WordPress installer or install the plugin manually.

For an automated installation:
1. Go to 'Plugins -> Add New' on your WordPress Admin page.
2. Search for the 'WP to diaspora' plugin.
3. Install by clicking the 'Install Now' button.
4. Activate the plugin on the 'Plugins' page in your WordPress Admin.

For a manual installation:
1. Upload 'wp-to-diaspora' folder to the '/wp-content/plugins/' directory of your WordPress installation.
2. Activate the plugin on the 'Plugins' page in your WordPress Admin.

== Screenshots ==

1. Account setup page
2. Default settings setup page
3. Creating an example post in WordPress
4. The example post created on diaspora*

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

= 4.0.0 =
* Min PHP 8.0.
* Integrate PHP dependencies into WP2D namespace.
* Move tests to GitHub Actions and test up to PHP 8.2.

= 3.0.2 =
* Fix duplicate posting problem by updating meta data earlier, preventing any race condition

= 3.0.1 =
* Fix deploy script, which borked up version 3.0.0

= 3.0.0 =
* Rewrite for usage with Gutenberg
* Fix admin notice for Gutenberg
* Min WP 4.6 and PHP 7.2
* Test for WP 5.0 and up to PHP 7.3
* Update html-to-markdown to 4.8.1
* Fetch pod list from the-federation.info API (which allows filtering only diaspora pods)
* Add WP2D_ENC_KEY constant for custom password encryption key

= 2.1.0 =
* Add new filters (wp2d_post_filter, wp2d_excerpt_filter, wp2d_tags_filter)
* Add new display type "None", to not display the post content

= 2.0.2 =
* Update pod list
* Test for WP 4.9
* Fix tests

= 2.0.1 =
* Update screenshots
* Update Russian translation

= 2.0.0 =
* Update components and optimise various parts of the code
* New minimum PHP version is 5.4
* Remove dynamic Pod list loading
* Remove custom SSL certificate management

= 1.9.1 =
* Fix wrong parameter order for filters

= 1.9.0 =
* Remove AJAX pod list update and add static list instead
* Remove certificate bundle downloader and simply refer to the wiki in the help tab
* Remove some superfluous member value assignments

= 1.8.0 =
* Update filters to pass WP2D_Post object

= 1.7.2 =
* Manage translations via translate.wordpress.org

= 1.7.1 =
* Lower save_post hook priority to make the plugin compatible with WP to Twitter plugin

= 1.7.0 =
* Make plugin no-js friendly, so that it works properly even if Javascript is disabled
* Add extra tests and introduce a few helper methods
* Spanish translation available

= 1.6.0 =
* API now uses the WP_HTTP API for requests, cURL no longer required
* Contextual help updated with troubleshooting tips
* Add initial unit tests for WP2D_API
* Fixed a bug: the posts were published on diaspora every time they were edited via Quick Edit

= 1.5.4.1 =
* Fix and correct filters

= 1.5.4 =
* Requires at least version 3.9.2 due to WordPress repository structure and PHP 7 compatibility
* Add minimum WordPress and PHP version checks
* Add four filters for: post title,"Originally posted at:", content shortcodes and content filters. Usage information available in wp-to-diaspora's github wiki.

= 1.5.3 =
* Gallery images and single images with captions get a pretty caption added to them

= 1.5.2 =
* Fixed scenario where old posts would get posted to diaspora* by default
* Fixed problem with special characters in the post title
* Add debugging with AJAX
* Fixed language includes
* Strip HTML tags from the post

= 1.5.1 =
* Fixed bug affecting scheduled posts
* Update html-to-markdown

= 1.5.0 =
* SSL CA bundle check and download option
* Move post related functionality to an own WP2D_Post class
* Add a helper to facilitate API connections
* Make code adhere to WordPress coding standards
* Make singleton classes initialisation and setup simpler
* Simplify the contextual help class
* Correct how tags are added to the post

= 1.4.0 =
* Split Settings page into tabs to give a better overview
* The initial setup of the plugin is a lot easier now, requiring the connection details first, before being able to change default behaviour
* Aspects and Services get loaded automatically on initial setup
* Implement chosen to simplify option inputs
* Add contextual help to the settings and new post pages
* Correct fetching and posting of the excerpt
* Minor code optimisations

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

= 4.0.0 =
This update requires at least PHP 8.0.
We are looking for new maintainers to keep this project alive, [get in touch](https://noplanman.ch)!

= 3.0.1 =
This update requires at least WordPress 4.6 and PHP 7.2.
We are looking for new maintainers to keep this project alive, [get in touch](https://noplanman.ch)!

= 2.0.0 =
This update requires at least PHP 5.4.
If you are still using PHP 5.3, stay on version 1.9.1.

= 1.8.0 =
Filters have been modified and now get the WP2D_Post object to allow access to all post details.
If you have any custom filters, they MUST be rewritten to the new format. Check the [Wiki on GitHub](https://github.com/DiasPHPora/wp-to-diaspora/wiki/Filters).
