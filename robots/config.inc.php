<?php

if (!isset($_PHPLIB) or !is_array($_PHPLIB)) $_PHPLIB["libdir"] = "/home/jscorp/robots/";

require($_PHPLIB["libdir"] . "db_mysql.inc.php");

Class Class_DB extends DB_Sql
{
	var $Host     = "localhost";
	var $Database = "mailer";
	var $User     = "root";
	var $Password = "rmc3284K";

	function haltmsg($msg)
	{
		echo "ERRO! $msg_err";
	}
}

function dados_maquina($param, $DB)
{
	$query = "SELECT * FROM maquinas";
	$DB->query($query);	 	 	 	 
	$DB->next_record();
	
	$metadados  = $DB->metadata('', "true"); // metadados sobre a tabela
	$tot_campos = $metadados['num_fields'];	 	 

	for ($i=0;$i<$tot_campos;$i++)
	{
		$campo = $DB->f($i);
		$nome_campo = $metadados[$i]['name'];			
		if($nome_campo == $param) return $campo;	 
	}
}

function acha_processo($nome)
{
	$quant = 0;
	$processos = shell_exec("ps aux | grep '$nome'");
	
	$linhas = explode("\n", $processos);
	#print_r($linhas);
	

	foreach($linhas as $key => $value)
	{
		#file_put_contents("/home/mailer/debug/debug.txt","$value\n",FILE_APPEND);
		if(stripos($value, "-c"))    $value = "";
		if(stripos($value, "grep"))  $value = "";
		if(stripos($value, "$nome")) $quant++;
	}
	
	if( $quant > 1 ) 
		return (1);
	else
		return (0);
}

function f_dados_mailer($dado)
{
	$dados_mailer = "/home/jscorp/robots/dados_mailer.txt";

	$file_handle = fopen($dados_mailer, 'r');
	$file_return = fread($file_handle, filesize($dados_mailer));
	$file_lines  = explode("\n",$file_return);
	$count       = count($file_lines);

	for($i=0; $i <= $count; $i++)
		if( eregi($dado,$file_lines[$i]) )
			return trim( str_replace($dado,"",$file_lines[$i]) );
	
	fclose($file_handle);
}

function f_valida_dominio($dominio)
{
	$motivo_bounce = "Dominio: $dominio sem suporte a emails.";
	$DB = new Class_DB;
	
	$result = shell_exec("dig mx $dominio +short");
	if($result == "")
	{
		echo $motivo_bounce."\n";
		$query = "UPDATE envio2 SET status = 'E', bounce_status = '12',
					motivo_bounce = concat('$numero_maquina => ', '$motivo_bounce', '\n', motivo_bounce)
					WHERE dominio = '$dominio'
					AND dominio not in ('gmail.com','hotmail.com','yahoo.com.br','yahoo.com','bol.com.br','terra.com.br','msn.com','uol.com.br','ig.com.br','globo.com')";
		$DB->query($query);
	}
}

?>
