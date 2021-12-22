<?php

#-----------------------------------------------------------------------------------------------------------#
#											CONEXAO AO BANCO DE DADOS										#
#-----------------------------------------------------------------------------------------------------------#
#if (!isset($_PHPLIB) or !is_array($_PHPLIB)) $_PHPLIB["libdir"] = "/home/mailer/robot/";		 
#require($_PHPLIB["libdir"] . "db_mysql.inc.php");

Class Class_DB_IQDIRECT extends DB_Sql
{
	var $Host     = "emm.ckwcfc1effku.us-east-1.rds.amazonaws.com";
	var $Database = "versao2";
	var $User     = "emm";
	var $Password = "rmc3284k";
	#var $Halt_On_Error = "no";
	

	function haltmsg($msg) {echo "ERRO! $msg\n";
   }
}

Class Class_DB_IQMAIL extends DB_Sql
{
	#var $Halt_On_Error = "no";

	var $Host     = "emm.ckwcfc1effku.us-east-1.rds.amazonaws.com";
	var $Database = "iqmail";
	var $User     = "emm";
	var $Password = "rmc3284k";	
	
	//var $Host     = "iqmail.ckwcfc1effku.us-east-1.rds.amazonaws.com";


	function haltmsg($msg) {echo "ERRO! $msg\n";
   }
}

?>
