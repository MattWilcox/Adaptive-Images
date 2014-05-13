[![Build Status](https://travis-ci.org/alexsomeoddpilot/Adaptive-Images.svg?branch=master)](https://travis-ci.org/alexsomeoddpilot/Adaptive-Images)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/alexsomeoddpilot/Adaptive-Images/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/alexsomeoddpilot/Adaptive-Images/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/alexsomeoddpilot/Adaptive-Images/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/alexsomeoddpilot/Adaptive-Images/?branch=master)

# Adaptive Images

Is a solution to automatically create, cache, and deliver device-appropriate versions of your website's content images. It does not require you to change your mark-up. It is intended for use with [Responsive Designs](http://www.abookapart.com/products/responsive-web-design) and to be combined with [Fluid Image](http://unstoppablerobotninja.com/entry/fluid-images/) techniques.

## Benefits

* Ensures you and your visitors are not wasting bandwidth delivering images at a higher resolution than the vistor needs.
* Will work on your existing site, as it requires no changes to your mark-up.
* Is device agnostic (it works by detecting the size of the visitors screen)
* Is CMS agnostic (it manages its own image re-sizing, and will work on any CMS or even on flat HTML pages)
* Is entirely automatic. Once added to your site, you need do no further work.
* Highly configurable
    * Set the resolutions you want to become adaptive (usually the same as the ones in your CSS @media queries)
    * Choose where you want the cached files to be stored
    * Configure directories to ignore (protect certain directories so AI is not applied to images within them)

Find out more, and view examples at [http://adaptive-images.com](http://adaptive-images.com)

##Instructions

### Basic instructions

    require "alexsomeoddpilot/adaptive-images": "*"

Create a new processor instance

    $adaptiveImages = new \alexsomeoddpilot\AdaptiveImages();

Add the rules from `.htaccess` to your own `.htaccess` file.

Copy the following Javascript into the `<head>` of your site. It MUST go in the head as the first bit of JS, before any other JS. This is because it needs to work as soon as possible, any delay wil have adverse effects.

    <script>document.cookie='resolution='+Math.max(screen.width,screen.height)+'; path=/';</script>

That's it, you're done. You should proberbly configure some preferences though.

#### Retina

If you would like to take advantage of high-pixel density devices such as the iPhone4 or iPad3 Retina display you can use the following JavaScript instead.

It will send higher-resolution images to such devices - be aware this will mean slower downloads for Retina users, but better images.

    <script>document.cookie='resolution='+Math.max(screen.width,screen.height)+("devicePixelRatio" in window ? ","+devicePixelRatio : ",1")+'; path=/';</script>

#### Security

If you are extra paranoid about security you can have the ai-cache directory sit outside of your web-root so it's not publicly accessible. Just set the paths properly in the .htaccess file and the processor class.

### Additional settings and configuration

### .htaccess
Instructions are in the file as comments (any line that starts with a # is a comment, and doesn't actually do anything)
Follow the instructions inside that code to specify any directories you don't want to use Adaptive-Images on.

### PHP
You can pass settings to the processor class. By default it looks like this:

**resolutions**

`array(1382, 992, 768, 480)`

The screen widths we'll work with. In the default it will store a re-sized image for large screens, normal screens, tablets, phones, and tiny phones.
In general, if you're using a responsive design in CSS, these breakpoints will be exactly the same as the ones you use in your media queries.

**cachePath**

`"ai-cache"`

If you don't like the cached images being written to that folder, you can put it somewhere else. Just put the path to the folder here and make sure you create it on the server if for some reason that couldn't be done autmoatically by adaptive-images.php.

**jpgQuality**

`75`

**sharpen**

`true`

Will perform a subtle sharpening on the rescaled images. Usually this is fine, but you may want to turn it off if your server is very very busy.

**watchCache**

`true`

If your server gets very busy it may help performance to turn this to FALSE. It will mean however that you will have to manually clear out the cache directory if you change a resource file

**browserCache**

`604800`

60 seconds &times; 60 minutes &times; 24 hours &times; 7 days

### Troubleshooting

Most of the time people report problems it is due to one of two things:

**If images vanish**

There is something wrong with your .htaccess configuration. This happens mostly on WordPress sites - it's because the server, and wordpress, have specific requirements that are different from most servers. You'll have to play about in the .htaccess file and read up on how to use ModRewrite.

**If you're seeing error images (big black ones)**

That's AI working, so your .htaccess is fine. Read the messages on the image. Most of the time you'll only see this problem because your server requires less strict permissions to write to the disc. Try setting the ai-cache directory to 775, and if all else fails use 777 - but be aware this is not very secure if you're on a shared server, and you ought to instead contact your administrator to get the server set up properly.

## Legal

Adaptive Images by Matt Wilcox is licensed under a [Creative Commons Attribution 3.0 Unported License](http://creativecommons.org/licenses/by/3.0/)
