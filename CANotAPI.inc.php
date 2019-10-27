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
	//	FUNCTION: CANotAPI_GetUrlData
	//	PURPOSE: returns the string of data from a remote URL
	//	ARGUMENTS:
	//		$url: String of the url to be ;
	//		$fields: Array of key/value containng the query data (GET);
	//	RETURNS: A string with all data responsded.
	//
	function CANotAPI_GetUrlData($url, $fields)
	{
		// Url-ify the data for the POST
        $fields_string = '';
		foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
		rtrim($fields_string, '&');
		// Open curl connection
		$ch = curl_init();
		// Set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL, $url.'?'.$fields_string);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
		// Execute post
		ob_start();
		curl_exec($ch);
		$result = ob_get_contents();
		ob_end_clean();
		// Close connection
		curl_close($ch);
        return $result;
    }

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
		$result = CANotAPI_GetUrlData('https://plan.navcanada.ca/weather/api/search/en', [
			'filter[value]' => urlencode($airport),
			'_' => urlencode(time()),
        ]);
		$result_json = json_decode($result, true);
        $airportGeoPoint = $result_json['data'][0]['geometry']['coordinates'];
        $airportName = $result_json['data'][0]['properties']['displayName'];
		$result = CANotAPI_GetUrlData('https://plan.navcanada.ca/weather/api/alpha/', [
			'point' => urlencode($airportGeoPoint[0].','.$airportGeoPoint[1].','.$airport.',site'),
			'alpha' => urlencode('notam'),
			'metar_historical_hours' => urlencode('1'),
			'_' => urlencode(time()),
        ]);

		
		$result_json = json_decode($result, true);
		
        $all_notams_list = $result_json['data'];


		foreach($all_notams_list as $notam_data)
		{
            
			$this_notam_isSearched = false;
			$this_notam_isGoodAirport = false;
            $this_notam_text = $notam_data['text'];

            if($notam_data['location'] === $airport)
            {
			    $this_notam_isGoodAirport = true;
            }

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
            
			// Check if the Notam is actually for the searched airport
			if($this_notam_isSearched && $this_notam_isGoodAirport)
			{
				// Check if Notam has already been displayed
				//if(!isset($Already_Notam_List[$this_notam_id]))
				//{
					// Variables
					//$Already_Notam_List[$this_notam_id] = true;
					$classes = 'CANotAPI_Notam';
					//preg_match('/[0-9]{10} TIL[ A-Z]+[0-9]{10}/', $this_notam_text, $this_notam_active_text);
					
					// Check if Notam contains validity times
					//if(isset( $this_notam_active_text[0] ))
					//{
						// Variables
						//$this_notam_active_begin = substr($this_notam_active_text[0], 0, 10);
						//$this_notam_active_end = substr($this_notam_active_text[0], -10);
						
						// Check if Notam is active, not active, or active soon.
						//if($this_notam_active_begin < $time_now and $time_now < $this_notam_active_end) {
							// Notam is active
							//$classes .= ' CANotAPI_Notam_active';
						//} elseif ($this_notam_active_begin < $time_soon and $time_soon < $this_notam_active_end) {
							// Notam is active soon
							//$classes .= ' CANotAPI_Notam_soonActive';
						//} else {
							// Notam is not active
							//$classes .= ' CANotAPI_Notam_inactive';
						//}
					//}
					//else
					//{
						// Notam has no time specified
						//$classes .= ' CANotAPI_Notam_timeUndef';
					//}
					
					// Add Notam to return string
					$ret .= '<span class="'.$classes.'">'.$this_notam_text.'</span><br><br>';
				//}
			}
        }
        /*
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
			$this_notam_isGoodAirport = preg_match('/(C[A-Z0-9]{3} [\/\-() A-Z0-9,.]+'.$airport.')/', $this_notam_text);
			
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
        */
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
