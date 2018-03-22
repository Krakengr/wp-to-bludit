# WordPress to Bludit converter
Converts the wordpress database xml file to the bludit's data structure

It converts Pages, Posts, tags, featured images, captions, Video/Twitter links to embed codes and creates an xml file 
with the comments if needed (for disqus). It doesn’t convert galleries though. 

You can select if you want to convert media url (Facebook videos, Vimeo, Youtube, Dailymotion videos and Tweets) to embed code 
or leave them as they are.

## How to use it

Upload the plugin and enable it. Upload your xml file in the uploads folder (bl-content/uploads). On the settings, enter the filename, your discuss name, if you want to also convert the comments and check if you want the plugin to convert every video/tweet URL to embed code.

## Keep your old data
You can keep your data (pages, photos, tags and more) from Bludit. This is currently a beta feature and you should backup your data first.

## Merge Multiple XMLs

You can convert multiple xml files. To do this, upload first your first xml file and when finish with the conversion, upload the next file and change the settings for this new file. Continue this until all files are converted. Do not disable the plugin until done, otherwise it will delete everything.

## 301 redirects
If your WP blog is in English, you can redirect your old posts to avoid loose your traffic. Open your htaccess file and enter this code below. You need only one, so keep only the line you need, based on your WP’s permalinks settings.

```
RewriteEngine On
RewriteBase /

# REDIRECT WP TO BLUDIT
# Month and name
RedirectMatch 301 ^/([0-9]+)/([0-9]+)/(.*)$ http://example.com/$3

# Day and name
RedirectMatch 301 ^/([0-9]+)/([0-9]+)/([0-9]+)/(.*)$ http://example.com/$4

# Custom Structure
RedirectMatch 301 ^/post/(.*)$ http://example.com/$1
```

Remember to test thoroughly after making any changes.

## 301 redirects (v1.0)
The plugin creates a links.php file, where the old and the new links will be stored after the conversion. Then you can use the redirect plugin to redirect your old URLs to the new ones.

The plugin also replaces the in-post URLs to the Bludit's link structure.

## URLify
Plugin now has URLify as a slug generator. URLify is a PHP port of URLify.js from the Django project. Transliterates non-ascii characters for use in URLs. 
https://github.com/jbroadway/urlify

## See more info and screenshots here:
https://g3ar.xyz/projects/wordpress-bludit-converter/
