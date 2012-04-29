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
define(REFERRER_URL, $_SERVER['HTTP_HOST']);

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
//		curl_setopt($proxyGet, CURLOPT_REFERER, REFERRER_URL);
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
	 * Returns a new tidy tree of the XHTML source
	 *
	 * Accepts only Character-Encoding: UTF-8 source and converts it
	 * to pure ASCII that can be inserted into pages of any
	 * Character-Encoding.
	 *
	 * @see http://php.net/tidy
	 *
	 * @param string $bannerXHTML source HTML to parse
	 * @return tidy:: Tidied XHTML tree
	 */
	function parseTidy($bannerXHTML)
	{
		$tidy = new tidy();
		$config = array(
				'output-xhtml' => true,
				'doctype' => "strict",
				//			'clean' => true,
				'preserve-entities' => true,
				'numeric-entities' => true,
				'char-encoding' => "ascii",
				'wrap' => 0);
		if (!$tidy->parseString(charset_decode_utf_8($bannerXHTML), $config))
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
	
	/**
	 * Returns the block of the tag type specified.
	 *
	 * Recursive method accepts a node tree and name to find
	 * then returns the matching node.
	 *
	 * @see http://php.net/tidy
	 *
	 * @param NODE_TYPE $findTag TIDY_TAG_ type of tag to find
	 * @param tidyNode:: $node TidyNode to walk DOM from
	 * @param string $attName name of attribute to check
	 * @param string $attValue value of attribute to find
	 * @return tidyNode:: "findTag" node or null if not found
	 */
	function findElement($findTag, $node, $attName, $attValue)
	{
		if ($node->id == $findTag
			&& (!$attName || $node->attribute[$attName] == $attValue)) return $node;
		if ($node->hasChildren())
			foreach($node->child as $child)
			{
				$found = findElement($findTag, $child, $attName, $attValue);
				if ($found) return $found;
			}
			return null;
	}
	
	function cantContinue($backupURL)
	{
		header("HTTP/1.1 302 Found");
		header("Location: " . $backupURL);
		exit;		
	}

	$place = $_GET['q'];	
	$searchURL = PLACES_URL . urlencode($place);
	
//	echo $searchURL;	exit;
	
	$searchResults = httpRequest($searchURL, 3, null, "NokiaN70-1/5.0705.3.0.1 Series60/2.8 Profile/MIDP-2.0 Configuration/CLDC-1.1");

//	echo $searchResults;	exit;
	
	
	// Parse results with tidy()
	$tidy = parseTidy($searchResults);
	if (!$tidy) {
		cantContinue($searchURL);
	}
	// Find the div tag with id="universal"
	$node = findElement(TIDY_TAG_DIV, $tidy->root(), "id", "universal");
	if (!$node) {
		cantContinue($searchURL);
	}

	// Definitely have something to run with now...
	header("Cache-Control: max-age=300");	// cache 5 minutes
	?>
<!DOCTYPE html> 
<html><head>
	<title>Reviews</title> 
	<meta name="viewport" content="width=device-width, initial-scale=1"> 
<!--
	<link rel="stylesheet" href="styles/jquery.mobile-1.0.1.min.css" />
	<script src="scripts/jquery-1.7.1.min.js"></script>
	<script src="scripts/jquery.mobile-1.0.1.min.js"></script>
	<script src="scripts/knockout-2.0.0.js"></script>
-->
	</head><body> 
	<?php

	echo $node;
	?>
</body></html>
