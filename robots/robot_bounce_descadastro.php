<?php
$msg_err = "";
include_once("config.inc.php");
include_once("config_principal.inc.php");

$pasta_usuario_inbox = $argv[1];
if(!isset($argv[1])) exit("parametro pasta usuario nao foi passado\n");

// verificando se o robot ja esta rodando
if(acha_processo("robot_bounce_descadastro.php $pasta_usuario_inbox")) exit("\n\nRobot ja esta rodando.\n\n");

// Lê a pasta inbox 	 
$PASTA_INBOX = dir($pasta_usuario_inbox);
while($arquivo = $PASTA_INBOX -> read())
{
	$file = $pasta_usuario_inbox.$arquivo;

	if (is_file($file))
	{
		echo "processando arquivo $arquivo.... \n";

		$resposta     = "";

		$procura      = "X-Original-To: ";
		$comando      = "grep --max-count=1 '$procura' ".$file;
		$resposta     = trim(preg_replace("/$procura/", "", @shell_exec($comando)));

		if(!eregi("unsubscribe",$resposta)) // NAO EH RESPOSTA DE NEWS
		{
			echo "ERRO NAO ENCONTREI unsubscribe\n";
			continue;    
		}

		$resposta = str_replace("unsubscribe-", "", $resposta);
		$resposta = explode("@",$resposta);
		$resposta = $resposta[0];

		$url_descadastro = "http://www.iqdirect.com.br/iqdirect/descadastro2/autodesc.php?sd=$resposta";
		$resposta = file_get_contents($url_descadastro);

		if(eregi("successful",$resposta))
		{
			echo "DESCADASTRP COM SUCESSO\n";
			unlink("$file");
		}		  
	}
} // FIM WHILE pasta usuario

$PASTA_INBOX -> close();
?>