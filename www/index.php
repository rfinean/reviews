<?php
/**
 * index.php aggregates reviews of a place using Google search
 *
 * @author		Rob Finean	<rfinean@iee.org>
 *
 * @version		1.0	28/Apr/12	Screenscrapes Google Mobile search
 */

/**
 * @var string Google search URL to find reviews. Bing and Y! are no good at this.
 */
//define(PLACES_URL, "http://www.google.com/m/search?q=");
define(PLACES_URL, "http://www.google.co.uk/m/search?q=");
//define(PLACES_URL, "http://maps.google.co.uk/maps/place?q=");

/**
 * @var string Referrer URL to give to Google
 */
define(REFERRER_URL, "http://" . $_SERVER['HTTP_HOST'] . "/");

/**
 * @var string Nokia non-WebKit User-Agent forces Google to format static XHTML for mobile
 */
define(MOBILE_UA, "NokiaN70-1/5.0705.3.0.1 Series60/2.8 Profile/MIDP-2.0 Configuration/CLDC-1.1");
//define(MOBILE_UA, "Mozilla/5.0 (SymbianOS/9.2; U; Series60/3.1 Nokia6120c/6.01; Profile/MIDP-2.0 Configuration/CLDC-1.1 ) AppleWebKit/413 (KHTML, like Gecko) Safari/413");
//define(MOBILE_UA, "Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_0 like Mac OS X; en-us) AppleWebKit/532.9 (KHTML, like Gecko) Version/4.0.5 Mobile/8A293 Safari/6531.22.7");
		
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
				'Content_Type: application/x_www_form_urlencoded'));
			curl_setopt($proxyGet, CURLOPT_POSTFIELDS, $postData);
		}
		$responseBody = curl_exec($proxyGet);
		curl_close($proxyGet);
		return $responseBody;
	}
	
	function cantContinue($backupURL)
	{
		header("HTTP/1.1 302 Found");
		header("Location: " . $backupURL);
		exit;		
	}

	$place = $_GET['q'];	
	$searchURL = PLACES_URL . urlencode($place);	
	$searchResults = httpRequest($searchURL, 4, null, MOBILE_UA);
	
	// Parse results with DOM
	$dom = new DOMDocument();
	if (!$dom->loadHTML($searchResults)) {
		cantContinue($searchURL);
	}
	$list = $dom->getElementById("universal");

	if (!$list) {
		cantContinue($searchURL);
	}

//	echo $dom->saveXML($list); exit;	// debug
	
	// Definitely have something to run with now...
	header("Cache-Control: max-age=300");	// cache 5 minutes
	echo '<' . '?xml version="1.0" encoding="UTF-8"?' . '>';
?>
<!DOCTYPE html> 
<html><head>
<title><?php echo $place; ?> reviews</title> 
<meta name="viewport" content="width=device-width, initial-scale=1"> 
<link rel="stylesheet" href="http://code.jquery.com/mobile/1.0.1/jquery.mobile-1.0.1.min.css" />
<style>.m{font-weight:normal}</style>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js"></script>
<script src="http://code.jquery.com/mobile/1.0.1/jquery.mobile-1.0.1.min.js"></script>
</head><body> 
<div data-theme="e" data-role="page" id="reviews">
    <div data-role="content">
		<ul data-role="listview">
<?php
	foreach ($list->childNodes as $review) {
		// display only if there is a <div class="m">, which contains the 5* rating
		if (get_class($review) != "DOMElement") continue;
		$rating = null;
		$website = null;
		$components = $review->getElementsByTagName("div");
		foreach ($components as $div) {
			if ($div->getAttribute("class") == "m") {
				$rating = $dom->saveXML($div);
				break;
			}
		}
		if (!$rating) continue;
		// find review URL and extract from Google Wireless Transcoder if neccessary
		$reviewSite = $review->getElementsByTagName("a");
		if (!$reviewSite) continue;
		$reviewSite = $reviewSite->item(0)->getAttribute("href");
		if (preg_match("#^/gwt/.*&u=(.*)$#", $reviewSite, $realURL)) {
			$reviewSite = urldecode($realURL[1]);
		} else if (preg_match("#^/m/url.*&q=(.*)$#", $reviewSite, $realURL)) {
			$reviewSite = urldecode($realURL[1]);
		}
		preg_match("#http://([a-zA-Z0-9\-\.]+)#", $reviewSite, $realURL);
		$website = $realURL[1];
		?><li data-theme="d"><a href="<?php echo $reviewSite; ?>" style="white-space:normal"><?php
//		echo $dom->saveXML($review);	// debug
		echo $website . $rating;
		?></a></li><?php
	}
?>
		</ul>
	</div>
</div>
<script>
var _gaq = [['_setAccount', 'UA-31436816-1'], ['_trackPageview']];
(function(ga, s) {
	ga.async = true;
	ga.src = 'http://www.google-analytics.com/ga.js';
	s.parentNode.insertBefore(ga, s);
}(
	document.createElement('script'),
	document.getElementsByTagName('script')[0]
));
</script>
</body></html>
