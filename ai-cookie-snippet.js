/*
 * 
 * The über-extended version of device width detection and cookie setting
 * More robust and reliable on newer and future mobile devices with accompanied browsers
 * 
 * Uses original screen.width method as a fallback
 * Progressive enhances using media queries through mediaMatch if available
 * Runs only on first visit, when our cookie is not yet present
 *   
 */


// Perform test if cookie is already present
var cookie = (readCookie('resolution') ? true : false );

// Only do stuff when neccesary!
// Remember: in case a corrupted cookie is once set, Adaptive Images will erase it later in php
// So we always have a second chance
if (!cookie) {


// Use original strategy as a "fallback" now
// For older browsers/devices, this will usually return valid values
// We set this FIRST because it seems faster to do
document.cookie = 'resolution='
			 	+ Math.max( screen.width, screen.height )
//				+ ( "devicePixelRatio" in window ? "," + devicePixelRatio : ",1")    // Better to leave out device pixel ratio in the fallback
				+ ",1"
				+ '; path=/';
	


// Progressive enhance if available		 
// Use sophisicated and more reliable strategy to determine device width via media queries
// For newer and future browsers this will be no problem
// Http://caniuse.com/#feat=matchmedia 
if (window.matchMedia) {
	
	// Instantiate the variable for resolution
	var resolution = null;
	
	
	// Loop through plausible possibilities of screen resolutions
	// Make sure all used values are multiples of 8!	
	for ( var i=240; i<=4088; i=i+8 ) {
		
				// Build our media query strings for assessing width and height
				width  =  '(max-device-width: ' + i + 'px)';
				height = '(max-device-height: ' + i + 'px)';
				
				// Test if and when both of the maximum dimensions of the devices screen are met
				// Inspired by: http://stackoverflow.com/questions/6850164/get-the-device-width-in-javascript
				if ( (window.matchMedia( width ).matches == true) && (window.matchMedia( height ).matches == true) )
					 { break; }
	}
	
		
	// Assing found resolution "late" (= after and outside of the for-loop)
	// In case the if-statement doesn’t break the for-loop – means that screen size exceeds max value of for-loop
	// This way, we do catch at least the biggest value from the for-loop
	// It’s like a "fallback" with a built-in "limiter"
	// Feel free to tailor to your needst he max value of the for-loop above (in multiples of 8!)
	resolution = i;
		

    // In case something useful was detected: set our sophisticated cookie		
    if ( (resolution) && (resolution != "") && (typeof resolution === 'number') && (resolution % 1 === 0) ) {		
    
    		document.cookie = 'resolution=' 
    						+ resolution
    						+ ( 'devicePixelRatio' in window ? ',' + devicePixelRatio : ',1')
    						+ '; path=/';
    }
    else {
    // Otherwise rely on our fallback AGAIN!
    // Looks like redundancy, but is actually necessary to be more safe and robust
    // Imagine the sophisticated version above returns crap or null and then sets this as a cookie ...
    document.cookie = 'resolution='
    			 	+ Math.max( screen.width, screen.height )
//					+ ( "devicePixelRatio" in window ? "," + devicePixelRatio : ",1")    // Let’s leave out device pixel ratio in the fallback
    				+ ",1"
    				+ '; path=/';
    
    }

} // End of: sophisticated media query version

} // End of: is a cookie set?




// Helper function; credits: ppk, quirksmode.org
// Returns cookie value(s)
// We use it just to see if a cookie is present
function readCookie(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
}


