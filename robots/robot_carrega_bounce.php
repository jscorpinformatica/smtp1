<?php
$msg_err = "";
include_once("config.inc.php");

$ret = acha_processo("robot_carrega_bounce");
if ( $ret ) exit("Processo ja rodando, SAI!\n");

// CONSTANTES
$CONST_HOME           = "/home/";
$CONST_INBOX          = "/Maildir/new/";

// Lê a pasta inbox 
$diretorio = dir("/home/"); 
$i = 0;

while($USUARIO = $diretorio -> read())
{	
	$i++;
	$pasta_usuario_home = "/home/".$USUARIO;	
	if (!is_dir($pasta_usuario_home)) continue;
	
	$pasta_usuario_inbox = $pasta_usuario_home . $CONST_INBOX;

	if (!is_dir($pasta_usuario_inbox)) continue;
	//echo "PASTA: $USUARIO\n";
	
	switch ($USUARIO)
	{    		
		# LIMPA ARQUIVOS E TRANSFORMA EM BANCO OS EMAILS
		case "descadastro":
			$comando = "php /home/jscorp/robots/robot_bounce_descadastro.php '$pasta_usuario_inbox' >> /dev/null &";
			exec($comando);
			#echo "$i - ".$comando."\n";
		break;
		
		case "postmasterbox":
			# NAO FAZ NADA POR ENQUANTO
		break;
		
		/*
		# ENVIA UM EMAIL COM PROMOCAO DO IQDIRECT
		case "iqdirect":
		case "comercial":
		case "contato":
			$comando = "php /home/jscorp/robots/robot_proposta.php '$pasta_usuario_inbox' $USUARIO >> /dev/null &";
			exec($comando);
			#echo "$i - ".$comando."\n";
		break;
		*/

		case "iqmail":
			$comando = "php /home/jscorp/robots/robot_bounce_pasta.php '$pasta_usuario_inbox' >> /dev/null &";
			exec($comando);
			echo "$USUARIO - $i - ".$comando."\n";
		break;

		case "iqdirect":
			$comando = "php /home/jscorp/robots/robot_bounce_pasta.php '$pasta_usuario_inbox' >> /dev/null &";
			exec($comando);
			echo "$USUARIO - $i - ".$comando."\n";
		break;

		case "mailer":
			$comando = "php /home/jscorp/robots/robot_bounce_pasta_mailer.php '$pasta_usuario_inbox' >> /dev/null &";
			exec($comando);
			echo "$USUARIO - $i - ".$comando."\n";
		break;
		
		/*
		default:
			$comando = "php /home/jscorp/robots/robot_bounce_pasta.php '$pasta_usuario_inbox' >> /dev/null &";
			exec($comando);
			#echo "$i - ".$comando."\n";
			
			$comando = "php /home/jscorp/robots/robot_bounce_pasta_iqmail.php '$pasta_usuario_inbox' >> /dev/null &";
			exec($comando);			
		break;
		*/
	}
} // FIM WHILE pasta home

$diretorio -> close();
 
?>
