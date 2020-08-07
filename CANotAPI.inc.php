<?php
//////////////////
		//	FILE: CANotAPI (Canadian Notam API)
		//	BY: rt-2(http://www.rt-2.net)
		//	PROJECT: https://github.com/rt-2/CANotAPI/
		//
		/////////////////////////////////////////////////////////////////////////////
    
    require_once(dirname(__FILE__).'/includes/definitions.inc.php');
    require_once(dirname(__FILE__).'/includes/notam.class.inc.php');
    
    
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
        $result_str = '20'.$data['year'][0].'-'.$data['month'][0].'-'.$data['day'][0].' '.$data['zulu'][0].'Z';
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
	//	FUNCTION: CANotAPI_GetNotamsSearch
	//	PURPOSE: returns all ids of notams from an airport search
	//	ARGUMENTS:
	//		$airport: String of the canadian airport you want to search notams for;
	//		$search: String or array of strings of keyword(s) that notam must contain to be shown;
	//	RETURNS: An array with all relevant notams.
	//
	function CANotAPI_GetHTMLBiutifulForNotam($this_notam_obj)
	{
    
		$ret = '';
		$classes = 'CANotAPI_Notam';

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
        return $ret;
    }
	//
	//	FUNCTION: CANotAPI_GetNotamsSearch
	//	PURPOSE: returns all ids of notams from an airport search
	//	ARGUMENTS:
	//		$airport: String of the canadian airport you want to search notams for;
	//		$search: String or array of strings of keyword(s) that notam must contain to be shown;
	//	RETURNS: An array with all relevant notams.
	//
	function CANotAPI_GetNotamsSearch($airport, $search)
	{
		//
		// Variables
		//
		$ret = [];
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
        $args = [
			'filter[value]' => urlencode($airport),
			'_' => urlencode(time()),
        ];
		$result = CANotAPI_GetUrlData('https://plan.navcanada.ca/weather/api/search/en', $args);
		$result_json = json_decode($result, true);
        $airportGeoPoint = $result_json['data'][0]['geometry']['coordinates'];
        $airportName = $result_json['data'][0]['properties']['displayName'];
        
        $args = [
			'point' => urlencode($airportGeoPoint[0].','.$airportGeoPoint[1].','.$airport.',site'),
			'alpha' => urlencode('notam'),
			'metar_historical_hours' => urlencode('1'),
			'_' => urlencode(time()),
        ];
		$result = CANotAPI_GetUrlData('https://plan.navcanada.ca/weather/api/alpha/', $args);

		
		$result_json = json_decode($result, true);
		
        $all_notams_list = $result_json['data'];


        

		foreach($all_notams_list as $notam_data)
		{
            
			$this_notam_isSearched = false;
			$this_notam_isGoodAirport = false;

            $this_notam_text = $notam_data['text'];
            $regex = "/^\((?<id>\w\d{4}\/\d{2})\X+(?:A\)\s(?<icao>\w{4})\s)(?:B\)\s(?<time_from>\d{10}(?:\w{3})?)\s)(?:C\)\s(?<time_to>\d{10}(?:\w{3})?)\s)(?:D\)\s(?<time_human>\X+)\s)?(?:E\)\s(?:(?:(?<message_en>\X+)\sFR:\s(?<message_fr>\X+)\)$)|(?:(?<message>\X+)\)$)))/mUu";
            
            //echo '<br><br>';
            //var_dump($this_notam_text);
            preg_match($regex, $this_notam_text, $matches);



            $this_notam_obj = New Notam([
                'ident' => $matches['id'],
                'airport' => $matches['icao'],
                'time_from' => $matches['time_from'],
                'time_to' => $matches['time_to'],
                'time_human' => $matches['time_human'],
                'text' => ( isset($matches['message_en']) && strlen($matches['message_en']) > 0 ? $matches['message_en'] : $matches['message'] ),
            ]);
            



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
            

			// Check if the Notam is actually for the searched airport
			if($this_notam_isSearched && $this_notam_isGoodAirport)
			{
                $ret[] = $this_notam_obj;
			}

        }

		return $ret;
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


		$all_notams_list = CANotAPI_GetNotamsSearch($airport, $search);
        
		foreach($all_notams_list as $this_notam_obj)
		{
            
			// Check if Notam has already been displayed
            $ret .= CANotAPI_GetHTMLBiutifulForNotam($this_notam_obj);
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
