<?php

error_reporting(E_ERROR | E_PARSE);

$msg_err = "";
include_once("config.inc.php");
include_once("config_principal.inc.php");

$pasta_usuario_inbox = $argv[1];

#exit("\n PASTA - ".$pasta_usuario_inbox."\n");

if(!isset($argv[1])) exit("parametro pasta usuario nao foi passado\n");

// verificando se o robot ja esta rodando
if(acha_processo("robot_bounce_pasta_mailer.php $pasta_usuario_inbox")) exit("\n\nRobot ja esta rodando.\n\n");

#$DB          = new Class_DB;
$DB_IQDIRECT = new Class_DB_IQDIRECT;
$DB_IQMAIL   = new Class_DB_IQMAIL;

// Lê a pasta inbox 
	 
$PASTA_INBOX = dir($pasta_usuario_inbox);

while($arquivo = $PASTA_INBOX -> read())
{	   

	$file = $pasta_usuario_inbox.$arquivo;		 

	if (is_file($file))
	{
		#echo "processando arquivo $arquivo.... \n";

		// id envio
		$log_id       = "";
		$log_envioid  = "";
		$log_news     = "";
		$log_action   = "";
		$log_status   = "";
		$log_erro     = "";		  
		$dominio      = "";
		 
		#echo "passei 2 \n";

		$procura      = "iqdirect-tag:";
		$comando      = "grep --max-count=1 '$procura' ".$file;
		$log_id       = trim(preg_replace("/$procura/","",@shell_exec($comando)));


        $flag_iqmail   = false;
        $flag_iqdirect = false;
        
		if (strpos($log_id,"|")) // EH RESPOSTA DE NEWS DO IQDIRECT
		{
			$SISTEMA = "IQDIRECT";
			$db_hard = "controle_email";
			$db_smtp = "smtp";
			$db_ivn  = "ivn";
			$db_usr  = "ivc_clientes";
		    $DB_PRINCIPAL  = $DB_IQDIRECT;
		    $flag_iqdirect = true;
		}
		else
		{
			$procura      = "iqmail-tag:";
			$comando      = "grep --max-count=1 '$procura' ".$file;
			$log_id       = trim(preg_replace("/$procura/","",@shell_exec($comando)));
			
			if (strpos($log_id,"|")) // EH RESPOSTA DE NEWS DO IQMAIL
			{	
			   $SISTEMA = "IQMAIL";
			   $db_hard = "iqmail";
			   $db_smtp = "iqmail_smtp";
			   $db_ivn  = "iqmail";
			   $db_usr  = "iqmail_contatos";
			   $DB_PRINCIPAL = $DB_IQMAIL;
			   $flag_iqmail  = true;
			}
			else // NAO EH RESPOSTA DE BOUNCE
			{
			   unlink("$file");		  
			   continue;
		    }
		    
		}

		list($log_news, $log_envioid) = explode("|", $log_id);
	
		// action erro
		$procura      = "Action:";
		$comando      = "grep '$procura' ".$file;
		$log_action   = trim(preg_replace("/$procura/","",@shell_exec($comando)));

		// status erro
		$procura      = "Status:";
		$comando      = "grep -A 1 '$procura' ".$file;
		$log_status   = trim(preg_replace("/$procura/","",@shell_exec($comando)));
		$vet_log      = preg_split("/Diagnostic\-Code:/",$log_status);
		$log_status   = addslashes(trim(preg_replace("/\n/","",$vet_log[0])));		  

		// log erro
		$procura      = "Diagnostic\-Code:";
		$comando      = "grep -A 5 '$procura' ".$file; // pega a linha e mais 3 a seguir
		$log_erro     = addslashes(trim(preg_replace("/$procura/","",@shell_exec($comando))));
		$log_erro     = addslashes(trim(preg_replace("/\n/","",$log_erro)));		  		  		

		// dominio
		$procura      = "Final-Recipient:";
		$comando      = "grep '$procura' ".$file; // pega a linha e mais 3 a seguir	  
		$dominio      = addslashes(trim(preg_replace("/$procura/","",@shell_exec($comando))));
		$dominio      = str_replace("rfc822;","",$dominio);
		$dominio      = addslashes(trim(preg_replace("/\n/","",$dominio)));
		$dominio      = substr(strrchr($dominio, "@"), 1);
		 
		echo " SISTEMA: $SISTEMA \n LOG ID: [$log_id] \n LOG_NEWS: $log_news \n LOG_ENVIOID = $log_envioid  \n LOG_STATUS = $log_status \n LOG_ERRO: $log_erro \n";			   

		if ( (trim($log_status) == "") or (!ctype_digit($log_news)) or (!ctype_digit($log_envioid)) ) // nao foi bounce
		{
			unlink("$file");		  
			continue; 
		} 
		  
		$achei = false;					  
		$soft_bounce = false;
		$hard_bounce = false;

		if (preg_match("/^4/",$log_status)) $soft_bounce = true; // soft bounce
		if (preg_match("/^5/",$log_status)) $hard_bounce = true; // hard bounce 
		  
		$bounce = 13; // soft bounce indefinido

		$query = "SELECT texto, tipo_erro_id FROM erros ORDER BY tipo_erro_id DESC";
		$DB_PRINCIPAL->query($query);		

		$string_procura = "";
		
		while( ($DB_PRINCIPAL->next_record()) and (!$achei) )
		{		  
			##echo "passei 4 \n";
			$texto = $DB_PRINCIPAL->f("texto");

			$texto_com_wildcard = preg_replace('/\s+/', '(.+)', $texto);
			$texto_com_wildcard = addslashes($texto_com_wildcard);			


			if( preg_match("/$texto_com_wildcard/i", $log_erro) )
			{
				$tipo_erro_id   = $DB_PRINCIPAL->f("tipo_erro_id");
				$bounce         = $tipo_erro_id;
				$achei          = true;
				$string_procura = $texto_com_wildcard;
			}						  										  
		}                          			  			   			   
		

/*
		if ( (($bounce == 1) or ($bounce == 3)) and ($soft_bounce) )
		{
			// conserta casos em que a tabela erros marcou hard bounce, mas o postfix status code eh 4.X.X
			if (preg_match("/X\-Spam\- Yes/i",$log_status)) $bounce = 11; // spam
			else $bounce = 13; // indefinido
		}      		  	 
*/		

		if (preg_match("/X\-Spam\- Yes/i",$log_status)) $bounce = 11; // spam		  		  
		
		if ($bounce <> 0){	
		
		   // busca cliente_id e email
		   $query = "select b.ivc_cliente_id, a.email from $db_smtp.envio_news_$log_news a, $db_ivn.ivn_newsletter b where a.envio2_id = '$log_envioid' and a.ivn_newsletter_id = b.ivn_newsletter_id";
		   $DB_PRINCIPAL->query($query);
		   
		   if ($DB_PRINCIPAL->next_record()){    
		       $cliente_id = $DB_PRINCIPAL->f(0);
			   $email      = $DB_PRINCIPAL->f(1);
		   } 					   		  
		
		}		
		
		// conserta baseado no status code 4.X.X ou 5.X.X
		switch ($bounce){
		  
				  // hard bounces	  
				  case "1":
				  case "3":	
				  case "10": // usuario inativo				  
				  
						// coloca na tabela de HARD BOUNCES
						if (trim($email) <> "") f_cadastra_hard($DB_PRINCIPAL, $email, $log_erro, $bounce);						    				    
						
						$tipo_bounce = "H";
						
				  break;		  
		  
				  // soft bounces definitivos
				  case "2":  // caixa lotada
				  case "4":  // usuario de ferias
						$tipo_bounce = "S";
				  break;				  	  
				  
				  case "11": // permissao negada
				  case "12": // dominio sobrecarregado				  				  
				  case "13": // indefinido					 							 
										  
						if (preg_match("/X\-Spam\- Yes/i",$log_status)) $bounce = 11; // spam       
						$tipo_bounce = "S";
				  
				  break;
		}		  
		
		echo " BOUNCE_STATUS: $bounce - $string_procura \n\n";
		  
		if ($bounce <> 0){				
		   if (($flag_iqmail) and (trim($email) <> "")){
				$query = "UPDATE $db_usr.USR$cliente_id SET usr_bounces = concat(usr_bounces,'$tipo_bounce') WHERE email = '$email'";		
				$DB_PRINCIPAL->query($query);		  		
		   }  
		}				  
		  
		  // atualiza bounce na tabela de envio da news
		  $query = "select count(0) from $db_ivn.ivn_newsletter where ivn_newsletter_id = '$log_news'";
          $DB_PRINCIPAL->query($query);
          if ($DB_PRINCIPAL->next_record())
              if ($DB_PRINCIPAL->f(0) == 0){
                  #echo "DELETEI: $file\n\n";
                  unlink("$file");              
                  continue; 
              } 
		  
		  
		  try {

			   $query = "UPDATE ignore $db_smtp.envio_news_$log_news SET bounce_status='$bounce', motivo_bounce='$log_erro', numero_testes=numero_testes +1 WHERE envio2_id='$log_envioid'";
			   $DB_PRINCIPAL->query($query);  		  				  			        		  

			   //loga bounces para analise - descomentar quando quiser analisar
			   //$query = "INSERT IGNORE INTO bounces.log_bounces (log_modelo,log_envioid,log_action, log_status, log_erro, bounce, dominio) VALUES ('$log_news', '$log_envioid', '$log_action', '$log_status', '$log_erro', '$bounce', '$dominio')";
			   //$DB->query($query);

		  } catch (Exception $e) {
			  echo 'Exceção capturada: ',  $e->getMessage(), "\n";
			  continue;
		  }		  

		  #echo "DELETEI: $file\n\n";		  
		  #if (!preg_match('/iqmail/i',$pasta_usuario_inbox)) 
		  unlink("$file");
			
	}		  
	 
} // FIM WHILE pasta usuario         

$PASTA_INBOX -> close();	


//**************************************************************
//*********************** INICIO FUNCOES ***********************
//**************************************************************

function f_cadastra_hard($DB_PRINCIPAL, $email, $motivo_bounce, $bounce)
{	
    global $db_hard, $db_usr, $cliente_id, $flag_iqmail, $flag_iqdirect;

	$query = "INSERT IGNORE INTO $db_hard.controle_hard 
			  (email, motivo_bounce, bounce_status)
			  VALUES
			  ('$email','$motivo_bounce', '$bounce')
			  ON DUPLICATE KEY UPDATE motivo_bounce='$motivo_bounce',bounce_status='$bounce',data_cadastro=now()";
	
	$DB_PRINCIPAL->query($query);

    // Atualiza tabela USR colocando quarentena = 1
	$query = "UPDATE $db_usr.USR$cliente_id SET quarentena = 1 WHERE email = '$email'";		
	$DB_PRINCIPAL->query($query);		
		
}
 
?>