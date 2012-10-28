<?php
    
    /*  Installing
        
            The folder "adaptive-images" must have the permission 755.
            Place the WordPress rewrite rules in the .htaccess from the root directory.
            In some cases "Options +FollowSymlinks" causes a server error. Then just 
            comment out. Observed at the provider "Internet24".
        
        
        Description
    
            The easy original "Adaptive Images" behavior:
            
                <img src="image.jpg" />
            
                The original "Adaptive Images" by Matt Wilcox.
                This will resizes every image on the rewrite conditions (.htaccess). The size 
                of the image will be the one value of the $config['resolutions'] that is equal 
                or closest higher than the device maximum width. You can enable this behavior 
                by setting $config['enable_resolutions'] = TRUE. Then all images without a 
                size-parameter will be sized by the original behavior of "Adaptive Images".
            
            
            The more spezific behavior:
            
                <img src="image.jpg?size=term" />
            
                Breakpoints and sizes
                    Now you can define, what size an image has to have on spezific configurable 
                    breakpoints equal to your media-queries. Just start with an default 
                    breakpoint  and size 0. If you do not setup an higher breakpoint than 0, 
                    the image will be this size on every device-dimensions. You can set the 
                    width in pixel or percentage. If you use for instance 100% in this case, 
                    the image will be 100% the size of the resolution, that is equal or 
                    closest higher than the device maximum width, but maximum width of the 
                    larges configured resolution.
            
                Cropping (cutting to fit)
                    You can define an aspect ratio for a term. 
                    All depending images will be cropped.
            
                Sharpen
                    You can define the amount of sharpness for a term. 0 is no sharpness, 
                    up to 30 is pleasant, higher than 30 is really sharp (max 500).
            
                JPEG quality
                    You can define the JPEG quality for a term from 100, which is best, 
                    down to 70, which is pleasant.
                    
                Prevent cache
                    For developping, you might will see changes of the setup on every 
                    page-reload. Then setup:
                        config['browser_cache'] = 0;
                        $config['prevent_cache'] = TRUE;
                    
                Debug mode
                    This inserts some informations into the image.
                    - image dimensions
                    - the aspect ratio
                    - the device-width
            
    */
    
    $config['enable_resolutions']   = FALSE;
    $config['resolutions']          = array(0, 320, 480, 600, 768, 1024, 1100);
    
    $config['breakpoints']          = array(
                                        'default' => 0, 
                                        'micro'   => 320, 
                                        'mini'    => 480, 
                                        'small'   => 600, 
                                        'medium'  => 768, 
                                        'normal'  => 1024, 
                                        'large'   => 1100
                                    );
                                    
    $config['cache_path']           = 'ai-cache'; 
    $config['jpg_quality']          = 80; // 100 to 0
    $config['jpg_quality_retina']   = 50; // 100 to 0
    $config['watch_cache']          = TRUE;
    $config['browser_cache']        = 60 * 60 * 24; // period of time in second, the images will stay in cache of browsers
    $config['prevent_cache']        = FALSE; // images will resized on every image request
    $config['debug_mode']           = FALSE; // insert the image dimensions, the ratio and the device-width into the image
    $config['sharpen']['status']    = TRUE; // enables sharpen
    $config['sharpen']['amount']    = 20; // 0 is none, 30 is pleasant, max is 500
    
    
    /* below, this is just an example */
    $setup['term']['breakpoints']['default'] = '100%';
    $setup['term']['breakpoints']['normal'] = '960px';
    $setup['term']['ratio'] = '2:1';
    $setup['term']['jpg_quality'] = 95;
    $setup['term']['jpg_quality_retina'] = 40;
    $setup['term']['sharpen']['amount'] = 40;
    
?>