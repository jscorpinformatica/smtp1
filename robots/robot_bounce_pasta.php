<?php
$msg_err = "";
include_once("config.inc.php");
include_once("config_principal.inc.php");

$pasta_usuario_inbox = $argv[1];

#exit("\n PASTA - ".$pasta_usuario_inbox."\n");

if(!isset($argv[1])) exit("parametro pasta usuario nao foi passado\n");

// verificando se o robot ja esta rodando
if(acha_processo("robot_bounce_pasta.php $pasta_usuario_inbox")) exit("\n\nRobot ja esta rodando.\n\n");

$DB          = new Class_DB;
$DB_IQDIRECT = new Class_DB_IQDIRECT;
$DB_IQMAIL   = new Class_DB_IQMAIL;

if (preg_match('/iqmail/i',$pasta_usuario_inbox)){
    $DB_PRINCIPAL = $DB_IQMAIL;
}    
else $DB_PRINCIPAL = $DB_IQDIRECT;

// Lê a pasta inbox 
	 
$PASTA_INBOX = dir($pasta_usuario_inbox);

while($arquivo = $PASTA_INBOX -> read())
{	   

	$file = $pasta_usuario_inbox.$arquivo;		 

	if (is_file($file))
	{
		echo "processando arquivo $arquivo.... \n";

		// id envio
		$log_id       = "";
		$log_envioid  = "";
		$log_news     = "";
		$log_action   = "";
		$log_status   = "";
		$log_erro     = "";		  
		$dominio      = "";

		  
		#echo "passei 2 \n";

		$procura      = "Message-ID: <";
		$comando      = "grep --max-count=2 '$procura' ".$file;
		$message_id   = trim(preg_replace("/$procura/","",@shell_exec($comando)));

        /*
		if (!strpos($log_id,"|")) // NAO EH RESPOSTA DE NEWS
		{
			unlink("$file");		  
			continue; 	              	              
		}
		*/

		#list($log_news, $log_envioid) = explode("|", $log_id);
		#if(eregi("356609",$log_id)) echo "LOGID: $log_id\n";
	
	    $message_id = preg_replace("/\@email\.amazonses\.com\>/i","",$message_id);
	    $vet_log_id = explode("\n",$message_id);
	    @$log_id     = $vet_log_id[1];
	
	    if (!f_busca_dados_envio($DB_PRINCIPAL, $log_id, $log_news, $log_envioid)){
	       #echo "$file - $log_id \n";

           // Nao achou por Message-ID, procura por Return-Path que na amazon recebe o message_id@dominio	       
		   $procura      = "Return-Path:";
		   $comando      = "grep -v 'Return-Path: <>' $file | grep --max-count=1  -A 1 '$procura' ";
		   $message_id   = trim(preg_replace("/$procura/","",@shell_exec($comando)));
		   #$message_id   = trim(preg_replace("/\<\>/","",$message_id));
		   $vet_log_id   = explode("@",$message_id);		   
		   #$log_id       = trim(preg_replace("/@s=/","",$message_id));
		   @$log_id     = $vet_log_id[0];
		   
		   #echo("***** $file - $message_id - $log_id *****\n");
		   
		   if (!f_busca_dados_envio($DB_PRINCIPAL, $log_id, $log_news, $log_envioid)){
		      echo "$file - $log_id - NAO ACHOU \n";
		      $comando = "mv $file $pasta_usuario_inbox"."avaliar/";
		      @shell_exec($comando);
		      continue;
		   }   
	    }   
	
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

		  
		echo " FILE: $file\n LOG ID: [$log_id] \n LOG_NEWS: $log_news \n LOG_ENVIOID = $log_envioid  \n LOG_STATUS = $log_status \n LOG_ERRO: $log_erro \n";			   
		
		//exit($file);

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

		$query = "SELECT texto, tipo_erro_id FROM iqmail.erros ORDER BY tipo_erro_id DESC";
		$DB_PRINCIPAL->query($query);
		while( ($DB_PRINCIPAL->next_record()) and (!$achei) )
		{		  
			##echo "passei 4 \n";
			$texto = $DB_PRINCIPAL->f("texto");

			if( stripos($log_erro, $texto) )
			{
				$tipo_erro_id = $DB_PRINCIPAL->f("tipo_erro_id");
				$bounce       = $tipo_erro_id;
				$achei        = true;
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
		  
		// conserta baseado no status code 4.X.X ou 5.X.X
		switch ($bounce){
		  
				  // hard bounces	  
				  case "1" : // dominio nao existe
				  case "3" : // usuario nao existe				   
				  case "10": // usuario inativo					  
				  
						// busca email e nr tentativas
						$query = "select email from iqmail_smtp.envio_news_$log_news where envio2_id = '$log_envioid'";
						$DB_PRINCIPAL->query($query);
						
						if ($DB_PRINCIPAL->next_record()){                       						                                
							$email = $DB_PRINCIPAL->f(0);
						} 					   
						// coloca na tabela de HARD BOUNCES
						if (trim($email) <> "") f_cadastra_hard($DB_PRINCIPAL, $email, $log_erro, $bounce);						    				    
				  break;		  
		  
				  // soft bounces definitivos
				  case "2":  // caixa lotada
				  case "4":  // usuario de ferias			  				  

				  break;				  	  
				  
				  case "11": // permissao negada
				  case "12": // dominio sobrecarregado				  				  
				  case "13": // indefinido					 							 
										  
						if (preg_match("/X\-Spam\- Yes/i",$log_status)) $bounce = 11; // spam                        				  																																																																											  
				  
				  break;
		  }		  
		  
		  // atualiza bounce na tabela de envio da news
		  $query = "select count(0) from iqmail.ivn_newsletter where ivn_newsletter_id = '$log_news'";
          $DB_PRINCIPAL->query($query);
          if ($DB_PRINCIPAL->next_record())
              if ($DB_PRINCIPAL->f(0) == 0){
                  #echo "DELETEI: $file\n\n";
                  unlink("$file");              
                  continue; 
              } 
		  $query = "UPDATE ignore iqmail_smtp.envio_news_$log_news SET bounce_status='$bounce', motivo_bounce='$log_erro', numero_testes=numero_testes +1 WHERE envio2_id='$log_envioid'";
		  
		  #if (!preg_match('/iqmail/i',$pasta_usuario_inbox)) exit($query);
		  #if($log_news=="356811") echo "$query\n\n";
		  
		  $DB_PRINCIPAL->query($query);  		  				  			        		  
						  
		  //loga bounces para analise - descomentar quando quiser analisar
		  #$query = "INSERT IGNORE INTO bounces.log_bounces (log_modelo,log_envioid,log_action, log_status, log_erro, bounce, dominio) VALUES ('$log_news', '$log_envioid', '$log_action', '$log_status', '$log_erro', '$bounce', '$dominio')";
		  #$DB->query($query);

		  #echo "DELETEI: $file\n\n";
		  
		  #if (!preg_match('/iqmail/i',$pasta_usuario_inbox)) 
		  unlink("$file");
		  
		  #exit();
			
	}		  
	 
} // FIM WHILE pasta usuario         

$PASTA_INBOX -> close();	


//**************************************************************
//*********************** INICIO FUNCOES ***********************
//**************************************************************

function f_cadastra_hard($DB_PRINCIPAL, $email, $motivo_bounce, $bounce)
{	
	$query = "INSERT IGNORE INTO iqmail.controle_hard 
			  (email, motivo_bounce, bounce_status)
			  VALUES
			  ('$email','$motivo_bounce', '$bounce')
			  ON DUPLICATE KEY UPDATE motivo_bounce='$motivo_bounce',bounce_status='$bounce',data_cadastro=now()";
	
	$DB_PRINCIPAL->query($query);
}

function f_busca_dados_envio($DB_PRINCIPAL, $log_id, &$log_news, &$log_envioid){
  
	$query = "SELECT news_id, envio2_id from iqmail.amazon_de_para where amazon_id = '$log_id'";
 	
	$DB_PRINCIPAL->query($query);
	if ($DB_PRINCIPAL->next_record())
	{
	   $log_news    = $DB_PRINCIPAL->f(0);
	   $log_envioid = $DB_PRINCIPAL->f(1);	   
	   
	   return true;
	}   
	
	return false;
}
 
?>
