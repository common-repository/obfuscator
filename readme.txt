=== ObfuscaTOR ===
Contributors: ryanday
Tags: tor, anonymity
Tested up to: 2.8.5
Requires at least: 2.5
Donate link: https://www.torproject.org/donate.html
Stable tag: 1.2


This plugin grabs Tor bridge information and displays that information in an 
obfuscated form.


== Description ==

This plugin will allow you to display Tor bridge information on your blog.

The intent is to let your readers find bridge IP information  without the 
addresses being grabbed by censoring governments and filtered. The hope is 
that many people will run this plugin and make it impossible (or just very 
hard) for Tor bridge information to be filtered, but still allow that 
information to be available to large groups of people.

You can aggregate the bridge information from the main Tor site, or from
an RSS feed. This is configurable per widget.

You can display the bridge information with shortcodes, or widgets. You
can change the display size and location of the shortcode and of the 
individual widgets.

== Installation ==

1. Unzip this plugin into your wp-content/plugins/ directory
2. Activate it in the wordpress admin section.
3. Put the obfuscaTOR widget wherever you like in your sidebar

This has been tested on 2.8.4 and 2.8.5 on a Linux and Windows system. You 
should immediatley be up and running. 

There are more configuration options however:

- Image Placement:
This is where you would like your image displayed. I have included a few 
popular areas that are used in most Wordpress themes. If you need a new 
area please let me know.

As of 1.1 there is now widget support, and that is the default operation.

- Checking for new bridges:
This plugins uses a simple cacheing system to store bridge information. The 
information you receive from the bridges.torproject site is only renewed every 
few hours(12 or more I think). This means there is no need to generate extra 
traffic for your own network, and for Tor's network, with unecessary requests. 
This option lets you choose how often to check for new bridge information.

- Recreate Image:
Generating the image takes a second or two, so it can end up slowing down your 
blog load time. Since there isn't new bridge information very often, there is 
really no need generate a new image on every page request. Every now and then 
you get a bad image though, so there is a box you can check which will generate 
a new image immediatley.

- Image Size:
The height and width of the generated image



== Extraneous ==
RSS Support
I plan to read Tor bridge information via RSS in the following format:

<rss version="2.0">
  <channel>
    <title>RSS Feed Site</title>
    <link>http://feedsite.com/rss/</link>
    <description>
        TOR bridge RSS feed
    </description>
    <item>
        <title>Extra information</title>
        <link>Extra link information</link>
        <description>
           1.2.3.4:80
        </description>
    </item>
    <item>
        <title>Extra information</title>
        <link>Extra link information</link>
        <description>
           4.3.2.1:8080
        </description>
    </item>
  </channel>
</rss>

I'm not sure where this will go, but I like the idea of having a lot of
meta for each bridge address. For now it won't be display, but in the future
it could possibly be used for something. Maybe the person providing the feed
could specify the level of obfuscation each bridge should have? Or a personal
donation link or something to keep the bridge in oepration.


Shortcode Support
You can use the shortcode tag [obfuscaTOR] to embed the image in your posts.
You can set the width, height, and alignment as well. Example:

   Here is the bridge information [obfuscaTOR width=150 height=50 align=right]



This Wordpress plugin is based on the ObfuscaTOR library that I put together 
using several publicly available CAPTCHA image programs. You can either copy 
that library out, or grab the latest from Github, and use it to develop plugins 
for your favorite CMS. This is encouraged! The more plugins out there, the more
people can distribute bridge information without the censors being able to 
automatically filter it.

If you write a plugin, please let me know and I can link to you. Also if you 
have more CAPTCHA creation libraries or better CAPTCHA libraries please let me 
know or fork the ObfuscaTOR lib on Github so we can keep improving this.

Please see the ObfuscaTOR library readme for further info.

== Changelog ==
= 1.2 =
* Additional configuration specific to each widget
* Added RSS feed support

= 1.1 =
* Added shortcode support
* Added widget support
* Removed wp_head section support
* Made widget mode the default placing
* Decreased amount of horizontal wave in the WaveCaptcha for readability
