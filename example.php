<html>
	<head>
		<title>CANotAPI Example Page</title>
	</head>
	<body>
		<?php
			// Requires the CANotAPI script from this package
			require('./CANotAPI.inc');
			
			// Show notams with a list or search words
			echo '<h2>Important Notams for CYUL</h2>';
			//this function echos the html result
			CANotAPI_EchoNotamsString('cyul', ['CLSD', 'rnw', 'twy', 'APCH']);
			
			// Show all notams by searching " " (a space).
			echo '<h2>All Notams for CYUL</h2>';
			//this function returns the html result
			echo CANotAPI_GetNotamsString('cyul', ' ');
      
		?>
	</body>
</html>
