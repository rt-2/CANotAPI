<?php
	//
	//
	//	FILE: CANotAPI (Canadian Notam API)
	//	BY: rt-2(http://www.rt-2.net)
	//	PROJECT: https://github.com/rt-2/CANotAPI/
	//		
	//
	//
	
	//
	//	FUNCTION: CANotAPI_GetNotamsString
	//	PURPOSE: returns the string of notams from an airport search
	//	ARGUMENTS:
	//		$airport: String of the canadian airport you want to search notams for;
	//		$search: String or array of strings of keyword(s) that notam must contain to be shown;
	//		$showFooter: Boolean default:true, set to false if you want to remove the footer or
	//			alternatively change its style with class 'CANotAPI_Footer';
	//	RETURNS: A string with all relevant notams.
	//
	function CANotAPI_GetNotamsString($airport, $search, $showFooter = true)
	{
		//
		// Variables
		//
		$ret = '';
		$fields_string = '';
		$airport = strtoupper($airport);
		$time_format = 'ymdHi';
		$time_obj = new DateTime("now", new DateTimeZone('UTC'));
		$time_now = $time_obj->format($time_format);
		$time_obj->add(new DateInterval('PT6H'));
		$time_soon = $time_obj->format($time_format);
		
		//
		// Access Remote Server
		//
		// Set connection/POST variables
		$url = 'https://flightplanning.navcanada.ca/cgi-bin/Fore-obs/notam.cgi';
		$fields = array(
			'Langue' => urlencode('anglais'),
			'TypeBrief' => urlencode('N'),
			'NoSession' => urlencode(''),
			'Stations' => urlencode($airport),
			'Location' => urlencode(''),
			'ni_File' => urlencode('on'),
			'ni_Aerodrome' => urlencode('on')
		);
		// Url-ify the data for the POST
		foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
		rtrim($fields_string, '&');
		// Open curl connection
		$ch = curl_init();
		// Set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch,CURLOPT_POST, count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
		// Execute post
		ob_start();
		curl_exec($ch);
		$result = ob_get_contents();
		ob_end_clean();
		// Close connection
		curl_close($ch);
		
		
		//
		// Work the Received data
		//
		// Get body content
		$html = strip_tags($result,'<body>');
		// Replace HTML spaces for normal spaces
		$html = str_replace('&nbsp;', ' ', $html);
		// Remove all excess spaces
		$html = preg_replace('/\s+/', ' ', $html);
		// Remove all extra text
		$html = str_replace('<body onpageshow="if (!ol_done){window.onload();};ol_done=0;" onload="ol_done=1;putFocus();doTime()" topmargin="0" leftmargin="2" marginwidth="2" marginheight="0" bgcolor="#ffffff">AWWS - NOTAM','',$html);
		$html = str_replace('AWWS - NOTAM function MM_popupMsg(msg) { //v1.0 alert(msg); } function MM_callJS(jsStr) { //v2.0 return eval(jsStr) } @media print { .noprint, .printcheckbox, .printbutton, .printtext { display:none; } .print { display:block; } }','',$html);
		$html = str_replace('Your browser does not support iframe, click here to view AWWS News. NOTAM PrintingAll NOTAM, groups of NOTAM or individual NOTAM can be printed by selecting the adjacent checkbox.','',$html);
		$html = str_replace('Select All NOTAM','',$html);
		$html = str_replace('Your time','',$html);
		$html = str_replace('UTC time','',$html);
		$html = str_replace('Log out','',$html);
		$html = preg_replace('/(Local NOTAM only\s+C[A-Z]{3})/', ' ', $html);
		$html = str_replace('Weather data provided by Environment Canada and NAV CANADA','',$html);
		$html = preg_replace('/(Aerodrome NOTAM file\s+C[A-Z]{3})/', ' ', $html);
		// Format the text
		$formatted_text = preg_replace('/\s+/', ' ', $html);
		// Seperate into individual notams
		preg_match_all('/([0-9]{6} C[A-Z]{3})/' , $formatted_text , $all_notams_indexes, PREG_OFFSET_CAPTURE);
		
		//
		// Show Wanted Notams
		//
		// Variables
		$Already_Notam_List = [];
		// Check every notams
		
		foreach($all_notams_indexes[0] as $key => $value)
		{
			// Variables
			$this_index = $all_notams_indexes[0][$key][1];
			$length = -1;
			if(isset($all_notams_indexes[0][$key+1])) $length = $all_notams_indexes[0][$key+1][1] - $this_index;
			$this_notam_id = +substr($formatted_text, $this_index, 6);
			$this_notam_text = substr($formatted_text, $this_index, $length);
			
			//Check if notam is wanted.
			$this_notam_isSearched = false;
			if(!is_array($search))
			{
				//search is a string
				if(strpos($this_notam_text, strtoupper($search))) $this_notam_isSearched = true;
			}
			else
			{
				//search is an array
				foreach($search as $search_text)
				{
					if(strpos($this_notam_text, strtoupper($search_text))) $this_notam_isSearched = true;
				}
			}
			
			// Eliminate notams from other airports
			$this_notam_isGoodAirport = preg_match('/(C[A-Z]{3} [\/\-() A-Z0-9,.]+'.$airport.')/', $this_notam_text);
			
			// Check if the Notam is actually for the searched airport
			if($this_notam_isSearched && $this_notam_isGoodAirport)
			{
				// Check if Notam has already been displayed
				if(!isset($Already_Notam_List[$this_notam_id]))
				{
					// Variables
					$Already_Notam_List[$this_notam_id] = true;
					$classes = 'CANotAPI_Notam';
					preg_match('/[0-9]{10} TIL[ A-Z]+[0-9]{10}/', $this_notam_text, $this_notam_active_text);
					
					// Check if Notam contains validity times
					if(isset( $this_notam_active_text[0] ))
					{
						// Variables
						$this_notam_active_begin = substr($this_notam_active_text[0], 0, 10);
						$this_notam_active_end = substr($this_notam_active_text[0], -10);
						
						// Check if Notam is active, not active, or active soon.
						if($this_notam_active_begin < $time_now and $time_now < $this_notam_active_end) {
							// Notam is active
							$classes .= ' CANotAPI_Notam_active';
						} elseif ($this_notam_active_begin < $time_soon and $time_soon < $this_notam_active_end) {
							// Notam is active soon
							$classes .= ' CANotAPI_Notam_soonActive';
						} else {
							// Notam is not active
							$classes .= ' CANotAPI_Notam_inactive';
						}
					}
					else
					{
						// Notam has no time specified
						$classes .= ' CANotAPI_Notam_timeUndef';
					}
					
					// Add Notam to return string
					$ret .= '<span class="'.$classes.'">'.$this_notam_text.'</span><br><br>';
				}
			}
		}
		// Add footer
		if($showFooter) $ret .= '<span class="CANotAPI_Footer"><small>Made possible by <a href="https://github.com/rt-2/CANotAPI" target="_blank">CANotAPI</a> (Canadian Notam API)</small></span><br><br>';
		// Return string
		return $ret;
	}
	
	//
	//	FUNCTION: CANotAPI_EchoNotamsString
	//	PURPOSE: echos the string of notams from an airport search
	//	ARGUMENTS:
	//		$airport: string of the canadian airport you want to search notams for;
	//		$search: string or array of strings of keyword(s) that notam must contain to be shown;
	//		$showFooter: Boolean default:true, set to false if you want to remove the footer or
	//			alternatively change its style with class 'CANotAPI_Footer';
	//	RETURNS: should return true;
	//
	function CANotAPI_EchoNotamsString($airport, $search, $showFooter = true)
	{
		echo CANotAPI_GetNotamsString($airport, $search, $showFooter);
		return true;
	}
	
?>
