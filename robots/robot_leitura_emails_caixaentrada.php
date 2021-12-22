<?php

/*
Objetivo: Buscar emails retornados para uma campanha de email marketing com remetente iqmailcomunica
          e enviar seu conteudo para o remetente original da campanha, formatando este email com um
          template da regua de relacionamento do IQmail

*/

#ini_set('display_errors', 1);
#error_reporting(E_ALL);

// Carrega namespaces necessarios para rodar classes

// classe phpmailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Carrega Classes do Composer
require("vendor/autoload.php");

// Declaracao de Constantes

$CONST_email_adm      = "jansensc@gmail.com";
$CONST_path_cxentrada = "/home/comunica/Maildir/new/";
$db_iqmail            = new db_iqmail;

// Busca emails da caixa de entrada

$emails = glob($CONST_path_cxentrada.'*');


foreach($emails as $email) {


	$emailParser = new PlancakeEmailParser(file_get_contents($email));
	
	// Pega o endereco de email para qual a comunicacao foi enviada
	
	$to       = $emailParser->getHeader('x-original-to');    
    $news_id  = preg_replace("/[^0-9]/", "", $to);
    
    // Verifica somente as comunicacoes feitas para usuarios comunica[news_id]@iqmailcomunica.com.br
    if ( preg_match("/comunica[0-9]+@iqmailcomunica\.com\.br/i", $to) ){        
        
		$reply_to = $emailParser->getHeader('reply-to');
		$from     = $emailParser->getHeader('from');
		
		// procura somente o email dentro de uma string (nome <nome@dominio.com>)
		$list          = preg_match_all('/([\w\d\.\-\_]+)@([\w\d\.\_\-]+)/mi', $reply_to, $matches );		
		$reply_to_name = isset($matches[1][0]) ? $matches[1][0] : "";
		$reply_to      = isset($matches[0][0]) ? $matches[0][0] : "";
		
		$list          = preg_match_all('/([\w\d\.\-\_]+)@([\w\d\.\_\-]+)/mi', $from, $matches );
		$from_name     = isset($matches[1][0]) ? $matches[1][0] : "";
		$from          = isset($matches[0][0]) ? $matches[0][0] : "";		
		
		if ($from == ""){
		   exec("rm $email"); 
		   continue;
		}   
		
		// caso tenha um cabecalho reply to, damos preferencia a ele para responder
		$replyto_name = (trim($reply_to_name) == "") ? $from_name : $reply_to_name ;
		$replyto      = (trim($reply_to) == "")      ? $from      : $reply_to ;		
		
        //$subject      = $emailParser->getSubject();
        $subject      = ($emailParser->getHeader('subject') == "") ? $emailParser->getHeader('assunto') : $emailParser->getHeader('subject') ;         
        $body         = utf8_decode($emailParser->getBody());
		
		f_envia_email_from_original($news_id, $from, $replyto, $replyto_name, $subject, $body);
		f_grava_emailsretorno_tabela($db_iqmail, $news_id, $from, $body);
		
    }
    else{ // remove spam da caixa de entrada
    
        //echo "retira email $email de SPAM \n";
        exec("rm $email");     
    
    }

}


function f_envia_email_from_original($news_id, $from, $replyto, $replyto_name, $assunto, $corpo_email_resposta){

    global $CONST_email_adm, $CONST_path_cxentrada;
    global $db_iqmail, $email;

    // Trocar pelo template novo que vocês vão construir
	$news_obj  = new campanha;
	$vet_campanha = $news_obj->get_campanha($news_id);

    $mensagem_padrao = file_get_contents("/home/jscorp/robots/includes/templates/resposta_automatica.html");

    // Substitui campos dinamicos id, nome, remetente, destinatario, assunto, corpo
    $mensagem_padrao = preg_replace("/tag##id##/i",$vet_campanha["ivn_newsletter_id"],$mensagem_padrao);
    $mensagem_padrao = preg_replace("/tag##nome##/i",$vet_campanha["ivn_newsletter_nome"],$mensagem_padrao);
    $mensagem_padrao = preg_replace("/tag##remetente##/i",$vet_campanha["ivn_newsletter_sender_email"],$mensagem_padrao);
    $mensagem_padrao = preg_replace("/tag##destinatario##/i",$from,$mensagem_padrao);
    $mensagem_padrao = preg_replace("/tag##assunto##/i",$vet_campanha["ivn_newsletter_assunto"],$mensagem_padrao);
	$mensagem_padrao = preg_replace("/tag##corpo##/i",$corpo_email_resposta,$mensagem_padrao);

	$sender_email = "comunica@iqmailcomunica.com.br";
	$sender_name  = "Retorno News $news_id";

	$to_email     = $vet_campanha["ivn_newsletter_sender_email"];
	$to_name      = $vet_campanha["ivn_newsletter_sender_nome"];

	$mail = new PHPMailer(true);
	
	try {
		//Server settings
		//$mail->SMTPDebug = 4;                        
		$mail->isSMTP();    		
		$mail->Host = f_get_mailer_ativa($db_iqmail);
		
		//$mail->Host = 'smtp.gmail.com';  		
		//$mail->SMTPAuth = true;                  
		//$mail->Username = 'suporte@iqmail.com.br';
		//$mail->Password = 'jscorp01';             
		//$mail->SMTPSecure = 'ssl';                
		
		$mail->SMTPSecure = 'tls';		
		$mail->Port = 587;                        
	
	    //exit("$sender_email - $sender_name - $replyto - $replyto_name - $to_email - $to_name");
	
		//Recipients
		$mail->setFrom("$sender_email", "$sender_name");
		$mail->addReplyTo("$replyto", $replyto_name);
		
		// envia para o usuario adm (validacao)		
		//$mail->addAddress("$CONST_email_adm", "");		
		$mail->addAddress($to_email, $to_name);	
	
		//Content
		$mail->isHTML(true);                        
		$mail->CharSet     = 'UTF-8';
		//$mail->CharSet     = 'iso-8859-1';	
		$mail->Encoding    = '8bit';		
		//$mail->ContentType = 'text/html; charset=utf-8\r\n';
		$mail->WordWrap    = 900; // RFC 2822 Compliant for Max 998 characters per line		
		$mail->Subject     = $assunto;
		$mail->Body        = $mensagem_padrao;
		$mail->AltBody     = "Resposta Automatica - Campanha IQmail $news_id";
		
		/*
		$mail->SMTPOptions = array(
			'ssl' => array(
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true
			)
		);
				
		$sender_domain         = substr(strrchr($sender_email, "@"), 1);
		$file_dkim_domain      = "/home/jscorp/robots/includes/keys/$sender_domain/senderdirect.private";
	
		# Se existir chave gerada para o dominio do from, assina o header da mensagem com DKIM
		if (file_exists($file_dkim_domain)){
			$mail->DKIM_domain     = $sender_domain;	
			$mail->DKIM_private    = $file_dkim_domain;
			$mail->DKIM_selector   = 'senderdirect';
			$mail->DKIM_passphrase = '';
			$mail->DKIM_identity   = $mail->From;		
		}
		
		$mail->Priority    = 1;				
		*/
		
		$mail->send();
		//echo "$email => Message ($news_id) has been sent ($mail->Host) to ($to_email) \r\n";
		
        exec("mv $email /home/comunica/Maildir/tmp"); 
		
	} catch (Exception $e) {
		//echo 'Message could not be sent.';
		//echo 'Mailer Error: ' . $mail->ErrorInfo . "\r\n";
        exec("rm $email");  
	}	

}

function f_grava_emailsretorno_tabela($mysqli, $news_id, $from, $body){

	try {
	   
		  $con = mysqli_connect($mysqli->host, $mysqli->user, $mysqli->pass, $mysqli->database);		
			   	
		  $body  = addslashes($body);	   	
		  $query = "INSERT IGNORE INTO emails_retorno (news_id, destinatario, mensagem) VALUES ('$news_id','$from', '$body')";		  

          echo $query;	
		  
		  mysqli_query($con, $query);

 		  mysqli_close($con);
	
          return 1;
	
	} catch (Exception $e) {

		  echo 'ERROR:'.$e->getMessage();		
		
		  return 0;
	
	}	
}

?>
