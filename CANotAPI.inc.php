<?php
//////////////////
		//	FILE: CANotAPI (Canadian Notam API)
		//	BY: rt-2(http://www.rt-2.net)
		//	PROJECT: https://github.com/rt-2/CANotAPI/
		//
		/////////////////////////////////////////////////////////////////////////////
    
    require_once('includes/definitions.inc.php');
    require_once('includes/notam.class.inc.php');
    
    
    $total_shown_notams = 0;

	//
	//	FUNCTION: CANotAPI_GetReadableDate
	//	PURPOSE: returns the string of a readable date from a 10 digit date format
	//	ARGUMENTS:
	//		$date10char: 10 char date/time to be converted
	//		$fields: Array of key/value containng the query data (GET);
	//	RETURNS: A string with all data responsded.
	//
	function CANotAPI_GetReadableDate($date10char)
	{
        $result_str = '';
        preg_match_all("/^(?<year>\d{2})(?<month>\d{2})(?<day>\d{2})(?<zulu>\d{4})$/", $date10char, $data);
        $result_str = '20'.$data[year][0].'-'.$data[month][0].'-'.$data[day][0].' '.$data[zulu][0].'Z';
        return $result_str;
    }

	//
	//	FUNCTION: CANotAPI_GetUrlData
	//	PURPOSE: returns the string of data from a remote URL
	//	ARGUMENTS:
	//		$url: String of the url to be queried;
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
        
        //echo '<br /><br />';
        

		foreach($all_notams_list as $notam_data)
		{
            
			$this_notam_isSearched = false;
			$this_notam_isGoodAirport = false;
            $this_notam_text = $notam_data['text'];
            $regex = "/^\((?<id>\w\d{4}\/\d{2})\X+(?:A\)\s(?<icao>\w{4})\s)(?:B\)\s(?<time_from>\d{10}(?:\w{3})?)\s)(?:C\)\s(?<time_to>\d{10}(?:\w{3})?)\s)(?:D\)\s(?<time_human>\X+)\s)?(?:E\)\s(?:(?:(?<message_en>\X+)\sFR:\s(?<message_fr>\X+)\)$)|(?:(?<message>\X+)\)$)))/mUu";
            
            //echo '<br><br>';
            //var_dump($this_notam_text);
            preg_match($regex, $this_notam_text, $matches);
            //print_r(array_filter($matches));
            if(false)
            {
                echo '<textarea>';
                echo '<br>$regex<br>';
                var_dump($regex);
                echo '</textarea>';
                echo '<br>$notam_data<br>';
                var_dump($notam_data);
                echo '<br>$matches<br>';
                json_encode($matches);
                var_dump($matches);
            }
            
            //echo '<br>';
            //var_dump($matches['message_en']);
            //var_dump($matches['message']);
            
            //echo '<br>';

            $this_notam_obj = New Notam([
                'ident' => $matches['id'],
                'airport' => $matches['icao'],
                'time_from' => $matches['time_from'],
                'time_to' => $matches['time_to'],
                'time_human' => $matches['time_human'],
                'text' => ( isset($matches['message_en']) && strlen($matches['message_en']) > 0 ? $matches['message_en'] : $matches['message'] ),
            ]);
            //var_dump($search);
            //var_dump($this_notam_obj);
            //var_dump($airport);
            //var_dump($this_notam_obj->GetAirport() === $airport);
            



            //echo '<br><br>';




            if($this_notam_obj->GetAirport() === $airport)
            {
			    $this_notam_isGoodAirport = true;
            }

			if(!is_array($search))
			{
				//search is a string
				if(strpos($this_notam_obj->GetText(), strtoupper($search)) !== false) $this_notam_isSearched = true;
			}
			else
			{
				//search is an array
				foreach($search as $search_text)
				{
					if(strpos($this_notam_obj->GetText(), strtoupper($search_text)) !== false) $this_notam_isSearched = true;
				}
			}
            
            //var_dump($this_notam_obj->GetText());
            //var_dump($this_notam_isGoodAirport);
            //var_dump($this_notam_isSearched);


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
					
                    if(strlen($this_notam_obj->GetText()) > 0)
                    {
					    // Add Notam to return string
					    $ret .= '<span class="'.$classes.'">';
					    $ret .= '<b>'.$this_notam_obj->GetAirport().'</b> - '.$this_notam_obj->GetIdent().'<br>';
					    $ret .= $this_notam_obj->GetText().'<br>';
					    $ret .= '<small><u>'.CANotAPI_GetReadableDate($this_notam_obj->GetTimeFrom()).' to '.CANotAPI_GetReadableDate($this_notam_obj->GetTimeTo()).'</u></small>';
					    $ret .= '</span><br><br>';
                        global $total_shown_notams;
                        $total_shown_notams++;
                    }
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
        global $total_shown_notams;
        if(strlen($airport) > 0)
        {
		    echo CANotAPI_GetNotamsString($airport, $search, $showFooter);
            echo '<br><br>';
            echo '<small>';
            echo 'Showing '.$total_shown_notams.' NOTAMs for '.$airport;
            echo '</small>';
        }
		return true;
	}
	
    // Var(s)


	//
	//	CLASS: CANotAPI_Notam
	//	PURPOSE: represent a NOTAM
	//	ARGUMENTS:
	//		$data: String of the url to be ;
	//		$fields: Array of key/value containng the query data (GET);
	//
?>
