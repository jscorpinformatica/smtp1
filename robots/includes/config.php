<?php

function f_get_mailer_ativa($db){

	try {
	   
		$mysqli = new mysqli( $db->host, $db->user , $db->pass , $db->database );		   
	
	    // busca mailers ativas
	    $result = $mysqli->query("select ip_publico from controle_mailer where ativa = 'S' ORDER BY RAND() LIMIT 1");
		$row    = mysqli_fetch_array($result);				
		
		$mysqli->close();
		
		return $row["ip_publico"];
		
	} catch (Exception $e) {
	
		//echo 'ERROR:'.$e->getMessage();		
		return "localhost";
		
	}
}

?>