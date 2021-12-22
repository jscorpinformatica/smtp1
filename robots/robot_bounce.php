<?php
$msg_err = "";
include_once("config.inc.php");
include_once("config_principal.inc.php");

$ret = acha_processo("robot_bounce");
if ( $ret ) exit("Processo ja rodando, SAI!\n");

$DB        = new Class_DB;
$DB_PRINC  = new Class_DBPRINC;
$DB_PRINC2 = new Class_DBPRINC;


// CONSTANTES
$CONST_HOME           = "/home/";
$CONST_INBOX          = "/Maildir/new/";
$CONST_MAX_TENTATIVAS = 100;  // passa o controle para o processo de reenvio.

// Lê a pasta inbox 
#$diretorio = dir($CONST_INBOX); 
$diretorio = dir("/home/"); 

while($USUARIO = $diretorio -> read())
{	    
    #if ($USUARIO <> "ofertadinamica") continue;
    
    $pasta_usuario_home = "/home/".$USUARIO;	
	if (!is_dir($pasta_usuario_home)) continue;
		  		
	 $pasta_usuario_inbox = $pasta_usuario_home . $CONST_INBOX;
	 echo "processando pasta $pasta_usuario_inbox.... \n\n";	      
	 
	 if (!is_dir($pasta_usuario_inbox)) continue;
	 
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
			   
			   #echo "passei 2 \n";		  
			   
			   $procura      = "iqdirect-tag:";
			   $comando      = "grep --max-count=1 '$procura' ".$file;
			   $log_id       = trim(preg_replace("/$procura/","",@shell_exec($comando)));
	   
	           if (!strpos($log_id,"|")){ // NAO EH RESPOSTA DE NEWS
				   unlink("$file");		  
				   continue; 	              	              
	           }
			   list($log_news,$log_envioid) = explode("|", $log_id);			   			   
		 
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
			   $comando      = "grep -A 3 '$procura' ".$file; // pega a linha e mais 3 a seguir
			   $log_erro     = addslashes(trim(preg_replace("/$procura/","",@shell_exec($comando))));
			   $log_erro     = addslashes(trim(preg_replace("/\n/","",$log_erro)));		  		  		
			   
			   echo "LOG ID: [$log_id] \n LOG_NEWS: $log_news \n LOG_ENVIOID = $log_envioid  \n LOG_STATUS = $log_status \n LOG_ERRO: $log_erro \n";			   
			   
			   if ( (trim($log_status) == "") or (!ctype_digit($log_news)) or (!ctype_digit($log_envioid)) ){ // nao foi bounce
				  unlink("$file");		  
				  continue; 
			   } 
               
			   $achei = false;					  
			   $soft_bounce = false;
			   $hard_bounce = false;
			   
			   if (preg_match("/^4/",$log_status)) $soft_bounce = true; // soft bounce
			   if (preg_match("/^5/",$log_status)) $hard_bounce = true; // hard bounce 
			   
			   $bounce = 13; // soft bounce indefinido
			   
			   $query = "SELECT texto, tipo_erro_id FROM versao2.erros ORDER BY tipo_erro_id DESC";
			   $DB_PRINC->query($query);		  
			   
			   #echo "passei 3 \n";
			   
			   while( ($DB_PRINC->next_record()) and (!$achei) )
			   {		  
				   ##echo "passei 4 \n";		                
				   $texto = $DB_PRINC->f("texto");
							   
				   if( stripos($log_erro, $texto) )
				   {
					   $tipo_erro_id = $DB_PRINC->f("tipo_erro_id");							
					   $bounce       = $tipo_erro_id;				
					   $achei        = true;
				   }						  										  
			   }	          		  		                           			  			   			   
	 
			   if (preg_match("/Permanent failure/i",$log_status)) $nr_tentativas = $CONST_MAX_TENTATIVAS;
			   else $nr_tentativas++;
							   
			   if ( (($bounce == 1) or ($bounce == 3)) and ($soft_bounce) ){
					
					// conserta casos em que a tabela erros marcou hard bounce, mas o postfix status code eh 4.X.X
					if (preg_match("/X\-Spam\- Yes/i",$log_status)) $bounce = 11; // spam
					else $bounce = 13; // indefinido						   
					
			   }      		  	 
			   
			   // conserta baseado no status code 4.X.X ou 5.X.X
			   switch ($bounce){
			   
					   // hard bounces	  
					   case "1":
					   case "3":					   
					   
							 // busca email e nr tentativas
							 $query = "select email from smtp.envio_news_$log_news where envio2_id = '$log_envioid'";
				 
							 $DB_PRINC2->query($query);
							 
							 if ($DB_PRINC2->next_record()){                       						                                
								 $email         = $DB_PRINC2->f(0);
							 } 					   
							 // coloca na tabela de HARD BOUNCES
							 f_cadastra_hard($email, $log_erro, $bounce);						    				    
					   break;		  
			   
					   // soft bounces definitivos
					   case "2":  // caixa lotada
					   case "4":  // usuario de ferias
					   case "10": // usuario inativo				  				  

					   break;				  	  
					   
					   case "11": // permissao negada
					   case "12": // dominio sobrecarregado				  				  
					   case "13": // indefinido					 							 
											   
							 if (preg_match("/X\-Spam\- Yes/i",$log_status)) $bounce = 11; // spam                        				  																																																																											  
					   
					   break;
			   }		  
			   
			   // atualiza bounce na tabela de envio da news
			   $query = "UPDATE smtp.envio_news_$log_news SET bounce_status = '$bounce', motivo_bounce = '$log_erro', numero_testes = numero_testes + 1 where envio2_id = '$log_envioid'";
			   $DB_PRINC2->query($query);  		  				  			        		  
							   
			   //loga bounces para analise - descomentar quando quiser analisar
			   #$query = "INSERT IGNORE INTO ofertadinamica.log_bounces (log_modelo,log_envioid,log_action, log_status, log_erro, bounce) VALUES ('$log_modelo', '$log_envioid', '$log_action', '$log_status', '$log_erro', '$bounce')";		  
			   #$DB->query($query);
	 
			   echo "DELETEI: $file\n\n";
			   unlink("$file");
			     
		  }		  
		  
	} // FIM WHILE pasta usuario         
	
	$PASTA_INBOX -> close();	
    
} // FIM WHILE pasta home

$diretorio -> close();


//**************************************************************
//*********************** INICIO FUNCOES ***********************
//**************************************************************

function f_cadastra_hard($email, $motivo_bounce, $bounce)
{
    $DB_PRINC = new Class_DBPRINC;	
	
	$query = "INSERT IGNORE INTO controle_email.controle_hard 
			  (email, motivo_bounce, bounce_status)
			  VALUES
			  ('$email','$motivo_bounce', '$bounce')
			  ON DUPLICATE KEY UPDATE motivo_bounce='$motivo_bounce',bounce_status='$bounce',data_cadastro=now()";
	
	$DB_PRINC->query($query);
}
 
?>