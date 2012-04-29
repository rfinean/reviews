<?php
/**
 * index.php aggregates reviews of a place using Google Places search
 *
 * @author		Rob Finean	<rfinean@iee.org>
 *
 * @version		1.0	28/Apr/12	Screenscrapes Google Mobile search
 */

/**
 * @var string Google Places search URL to find reviews. Bing and Y! are no good at this.
 */
define(PLACES_URL, "http://www.google.co.uk/m/search?q=");
//define(PLACES_URL, "http://maps.google.co.uk/maps/place?q=");

/**
 * @var string Referrer URL to give to Google Places
 */
define(REFERRER_URL, "http://" . $_SERVER['HTTP_HOST'] . "/");

/**
 * @var string Nokia Symbian User-Agent forces Google to format simply for mobile
 */
define(MOBILE_UA, "Mozilla/5.0 (SymbianOS/9.2; U; Series60/3.1 Nokia6120c/6.01; Profile/MIDP-2.0 Configuration/CLDC-1.1 ) AppleWebKit/413 (KHTML, like Gecko) Safari/413");

	/**
	 * Retrieves HTTP response from a URL.
	 * Warning: This is a comparatively slow function that may wait maxDelay
	 * seconds before returning so use these HTTP requests sparingly.
	 *
     * @see http://php.net/curl
     * @todo set up a proxy path on Apache to extend max-age and cache responses
     *
	 * @param string $url URL to request
	 * @param int $maxDelay maximum wait before returning null
	 * @param string $postData URL-encoded form POST data
	 * @param string $userAgent User-Agent to spoof instead of real phone's User-Agent
	 * @return string content returned by URL 
	 */
	function httpRequest($url, $maxDelay = 2, $postData = null, $userAgent = null)
	{
		$proxyGet = curl_init($url);
		curl_setopt($proxyGet, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($proxyGet, CURLOPT_TIMEOUT, $maxDelay);
		curl_setopt($proxyGet, CURLOPT_CONNECTTIMEOUT, $maxDelay);
		curl_setopt($proxyGet, CURLOPT_USERAGENT,
			$userAgent ? $userAgent : $_SERVER["HTTP_USER_AGENT"]);
		curl_setopt($proxyGet, CURLOPT_REFERER, REFERRER_URL);
		curl_setopt($proxyGet, CURLOPT_HTTPHEADER, array(
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Charset: utf-8',
			'Accept-Language: en',
			'Connection: Close'));
		if ($postData)
		{
			curl_setopt($proxyGet, CURLOPT_HTTPHEADER, array(
				'Content_Type: application/x_www_form_urlencoded', 'Connection: Close'));
			curl_setopt($proxyGet, CURLOPT_POSTFIELDS, $postData);
		}
		$responseBody = curl_exec($proxyGet);
		curl_close($proxyGet);
		return $responseBody;
	}
	
	/**
	 * Returns a new DOM of the XHTML source
	 *
	 * Accepts only Character-Encoding: UTF-8 source and converts it
	 * to pure ASCII that can be inserted into pages of any
	 * Character-Encoding.
	 *
	 * @see http://php.net/dom
	 *
	 * @param string $sourceXHTML source HTML to parse
	 * @return DOMDocument:: Tidied XHTML tree
	 */
	function parseDOM($sourceXHTML)
	{
		$tidy = new DOMDocument();
		if (!$tidy->loadHTML(charset_decode_utf_8($sourceXHTML)))
			return null;
		return $tidy;
	}
	
	/**
	 * Converts UTF-8 encoded string to ASCII with &#xxxx; unicode entities
	 * 
	 * @param string $string source UTF-8 string
	 * @return string ASCII HTML string
	 */
	function charset_decode_utf_8($string)
	{ 
	    // Only do the slow convert if there are 8-bit characters
	    // avoid using 0xA0 (\240) in ereg ranges. RH73 does not like that
	    if (!ereg("[\200-\237]", $string) and !ereg("[\241-\377]", $string)) 
	        return $string; 
	
	    // decode three byte unicode characters 
	    $string = preg_replace("/([\340-\357])([\200-\277])([\200-\277])/e",        
	    "'&#'.((ord('\\1')-224)*4096 + (ord('\\2')-128)*64 + (ord('\\3')-128)).';'",    
	    $string); 
	
	    // decode two byte unicode characters 
	    $string = preg_replace("/([\300-\337])([\200-\277])/e", 
	    "'&#'.((ord('\\1')-192)*64+(ord('\\2')-128)).';'", 
	    $string); 
	
	    return $string; 
	}	
		
	function cantContinue($backupURL)
	{
		header("HTTP/1.1 302 Found");
		header("Location: " . $backupURL);
		exit;		
	}

	$place = $_GET['q'];	
	$searchURL = PLACES_URL . urlencode($place);	
	$searchResults = httpRequest($searchURL, 3, null, "NokiaN70-1/5.0705.3.0.1 Series60/2.8 Profile/MIDP-2.0 Configuration/CLDC-1.1");
	
	// Parse results with DOM
	$dom = new DOMDocument();
	if (!$dom->loadHTML($searchResults)) {
		cantContinue($searchURL);
	}
	$list = $dom->getElementById("universal");

	if (!$list) {
		cantContinue($searchURL);
	}

//	echo $dom->saveXML($list); exit;
	
	
	
	// Definitely have something to run with now...
	header("Cache-Control: max-age=300");	// cache 5 minutes
	echo '<' . '?xml version="1.0" encoding="UTF-8"?' . '>';
?>
<!DOCTYPE html> 
<html><head>
	<title><?php echo $place; ?> reviews</title> 
	<meta name="viewport" content="width=device-width, initial-scale=1"> 
    <link rel="stylesheet" href="http://code.jquery.com/mobile/1.0.1/jquery.mobile-1.0.1.min.css" />
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js"></script>
	<script src="http://code.jquery.com/mobile/1.0.1/jquery.mobile-1.0.1.min.js"></script>
</head><body> 
<div data-role="page" id="reviews">
    <div data-theme="a" data-role="header">
        <h3>Reviews</h3>
    </div>
    <div data-role="content">

<?php

	echo $dom->saveXML($list);

?>
	</div>
</div>
<script type="text/javascript">
var GoSquared={};
GoSquared.acct = "GSN-621961-O";
(function(w){
	function gs(){
		w._gstc_lt=+(new Date); var d=document;
		var g = d.createElement("script"); g.type = "text/javascript"; g.async = true; g.src = "//d1l6p2sc9645hc.cloudfront.net/tracker.js";
		var s = d.getElementsByTagName("script")[0]; s.parentNode.insertBefore(g, s);
	}
	w.addEventListener?w.addEventListener("load",gs,false):w.attachEvent("onload",gs);
})(window);
</script>
</body></html>
