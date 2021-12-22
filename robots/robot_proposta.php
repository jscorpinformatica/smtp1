<?php
$msg_err = "";
include_once("config.inc.php");
include_once("config_principal.inc.php");

// criar processo para enviar e-mail comercial do IQDirect

$pasta_usuario_inbox = $argv[1];
$sistema             = $argv[2]; // iqdirect ou iqmail

#exit("\n PASTA - ".$pasta_usuario_inbox."\n");

if(!isset($argv[1])) exit("parametro pasta usuario nao foi passado\n");
if(!isset($argv[2])) exit("parametro sistema nao foi passado\n");

// verificando se o robot ja esta rodando
if(acha_processo("robot_proposta.php $pasta_usuario_inbox")) exit("\n\nRobot ja esta rodando.\n\n");

$DB          = new Class_DB;
$DB_IQDIRECT = new Class_DB_IQDIRECT;
$DB_IQMAIL   = new Class_DB_IQMAIL;

if (preg_match('/iqmail/i',$pasta_usuario_inbox))  $DB_PRINCIPAL = $DB_IQMAIL;
else $DB_PRINCIPAL = $DB_IQDIRECT;

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
		  
		  #echo "passei 2 \n";		  
		  
		  $procura      = "From: ";
		  //--max-count=1
		  $comando      = "grep -A 1 '$procura' ".$file;		  		  
		  $comando      = "grep -E -o '\b[a-zA-Z0-9.-]+@[a-zA-Z0-9.-]+\.[a-zA-Z0-9.-]+\b' ".$file;
		  $to           = trim(preg_replace("/$procura/i","",@shell_exec($comando)));
          		  
		  $vet_to       = explode("\n",$to);
		  $vet_to_unico = array_unique($vet_to);
		  $tam = count($vet_to_unico);
		  		  		  
		  foreach($vet_to_unico as $to){
	
	          $clientes_atuais   = "gvt|abre|alfatest|clubmed|febeus|allianz|retorna|votorantim";
	          $concorrentes      = "|akna|exacttarget|dinamyze|allin|abuse|mailchimp|surveymonkey";
	          $naoresponda       = "|noreply|no reply|no_reply|no-reply|nao-responda|nao_responda|naoresponda";
	          $reclamacoes       = "|remover|bounce|complaint|unsubscribe";
	          $parceiros         = "|iqdirect|iqmail|risesocialcommerce|mediafactory|maildireto|senderdirect|jscorp|zendesk|moip";
	          $termos            = "|mailmkt|local|javamail";
	          $dominios_antispam = "|aitecdobrasil|loginaduana";
	          
	          $restricoes_envio  = $clientes_atuais.$concorrentes.$naoresponda.$reclamacoes.$parceiros.$termos.$dominios_antispam;
 
		      if (!preg_match("/$restricoes_envio/i",$to)){
		      
                  $url_envio_direto = "http://www.iqdirect.com.br/iqdirect/api/envio_direto/envio_direto.php?modelo=54275&subject=E-mail%20Marketing%20Corporativo.%201%20milhão%20de%20envios%20por%20R$%20500,00&sender_name=IQDirect&sender_email=parceiros@jscorp.com.br&to_name=Prospect%20IQDirect&to_email=".$to."&username=jscorp&password=jscorp01";
				  $ret = file_get_contents($url_envio_direto);
				  
				  echo "=> $url_envio_direto\n$ret\n\n";
				  
			  }	  
			  
		  }		  		  		  
		  
		  unlink("$file");			
	}		  
	 
} // FIM WHILE pasta usuario         

$PASTA_INBOX -> close();	


//**************************************************************
//*********************** INICIO FUNCOES ***********************
//**************************************************************
 
?>