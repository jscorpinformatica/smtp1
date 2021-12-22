<?php

class campanha extends db_iqmail{

   private $mysqli   = null;

   function __construct(){

	   mysqli_report(MYSQLI_REPORT_STRICT);

	   try {

		   $this->mysqli = new mysqli( $this->host, $this->user , $this->pass , $this->database );		   
		   
	   } catch (Exception $e) {
	   
		   echo 'ERROR:'.$e->getMessage();
		   
	   }
   
   }
   
   function __destruct(){
   
       $this->mysqli->close();
   
   }

   function get_campanha($news_id){
	   
	   $vet_campanha = array();

	   $result = $this->mysqli->query("SELECT * FROM ivn_newsletter WHERE ivn_newsletter_id = '$news_id'");
	   
       $row = mysqli_fetch_array($result);
	   
	   $metadata = $result->fetch_fields();
	   
	   foreach($metadata as $campo){	   
	           $vet_campanha[$campo->name] = utf8_encode($row["$campo->name"]);
	   }
	   
	   $result->close();
	   
	   return $vet_campanha;
	   
   }

}