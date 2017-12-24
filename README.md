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

## See more info and screenshots here:
https://g3ar.xyz/projects/wordpress-bludit-converter/