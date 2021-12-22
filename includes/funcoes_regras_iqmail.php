<?php

//******************************************************************************
// Funcoes de negocio
// *****************************************************************************

// Verifica se um determinado cliente È cliente atacado.
function cliente_atacado ( $cliente_id, &$atacado_id )
{
    $DB = new Class_DB_iqmail;

    $query = "SELECT atacado_id
              FROM   iqmail.clientes
              WHERE  id = '$cliente_id'";

    $DB->query($query);
    $DB->next_record();
    $atacado_id = $DB->f(0);

	if ( (!ISSET($atacado_id)) or
		 ($atacado_id == 0)   )
		 return false;
	else
		 return true;

}

// Verifica se o master atacado tem um rodape personalizado
function rodape_atacado ( $atacado_id, $pagina, &$link_rodape, &$logo_rodape )
{
    $DB = new Class_DB_iqmail;
	$DB->Database = $pagina->banco_iqmail_contatos;
	$DB->connect();

    $query = "SELECT link_rodape
              FROM   atacado_clientes
              WHERE  id = '$atacado_id'";

    $DB->query($query);
    $DB->next_record();
    $link_rodape = $DB->f(0);

	if ( (!ISSET($link_rodape)) and ($link_rodape <> '')   )
		 return false;
	else{
	   $logo = "USR$atacado_id"."_logo.gif";
	   $arquivo = "$pagina->path_grava_rodape_cliente_atacado$logo";
	   if (file_exists($arquivo))
			 $logo_rodape = "$pagina->path_recupera_rodape_cliente_atacado$logo";
	   else
			 $logo_rodape = "http://www.iqmail.com.br/imagens_templates/testdrive.gif";

	   return true;
	}

}

function f_grava_historico($descricao,$cliente_id=0) {
	global $S_cliente_id;

	if ($cliente_id == 0)
		$cliente_id = $S_cliente_id;

	$ip = $_SERVER['REMOTE_ADDR'];

	$query = "INSERT INTO historico
			 (cliente_id, data, descricao, ip)
			  VALUES
			 ($cliente_id, now(), '$descricao', '$ip')";

	$DB = new Class_DB_iqmail;
	$DB->query($query);
}

// FunÁ„o que define qual tabela de envio ser· utilizada em uma consulta
//***********************************************************************

function tabela_envio($news_id)
{
	$DB = new Class_DB_iqmail;

	$query = "SELECT ivc_cliente_id, ivn_newsletter_status, versao
	          FROM   iqmail.ivn_newsletter
			  WHERE  ivn_newsletter_id = '$news_id'";

	$DB->query($query);
	$DB->Database = "envios";
	$DB->next_record();
	$cliente_id = $DB->f(0);
	$status     = $DB->f(1);
	$versao     = $DB->f(2);

    switch ($versao){
      case "4": // ofertadinamica e retorna
        $resultado = "iqmail_smtp.envio_news_".$news_id;
      break;

      default:
        // $resultado = "iqmail.envio2";
        $resultado = "iqmail_smtp.envio_news_".$news_id;
      break;
    }


	return($resultado);
}


function tabela_envio_sms($news_id){
	$DB = new Class_DB_iqmail;

	$query = "SELECT cliente_id, ind_database_envios FROM sms_mkt.newsletter_sms WHERE id = $news_id";

	$DB->query($query);
	$DB->next_record();

	$cliente_id 	= $DB->f(0);
	$ind_db_envios	= $DB->f(1);

	if($ind_db_envios == "S")
		return("envios_sms");
	else
		return("enviando_sms");
}

//******************************************************************************
//Funcoes que atualizam var_consolidadas

function atualiza_variaveis_envio($news)
{
	$newsletter_id = $news;

	atualiza_var_TOTAL_ENVIOS($newsletter_id);
	atualiza_var_TOTAL_UNSUB($newsletter_id);
	atualiza_var_TOTAL_IMPRESSOES($newsletter_id);
	atualiza_var_USUARIOS_CLICARAM($newsletter_id);
	atualiza_var_TOTAL_CLICKS($newsletter_id);
	atualiza_var_ENVIOS_CORRETOS($newsletter_id);
	atualiza_var_TOTAL_BOUNCES($newsletter_id);
	atualiza_var_TREAL_VIEW($newsletter_id);
	atualiza_var_TREAL_CLICK($newsletter_id);

    f_atualiza_detalhe_retirados($newsletter_id);
}


function atualiza_var_TOTAL_BOUNCES($newsletter_id)
{

	//echo ("total bounces\n");

	$query = "SELECT valor
	          FROM   iqmail.var_consolidadas
			  WHERE  ivn_newsletter_id='$newsletter_id'
			  AND    variavel='TOTAL_ENVIOS'";

	$DB = new Class_DB_iqmail;
	$DB->query($query);
	$DB->next_record();

	$total_enviados = $DB->f(0);

	$query = "SELECT valor
	          FROM   iqmail.var_consolidadas
			  WHERE  ivn_newsletter_id='$newsletter_id'
			  AND    variavel='ENVIOS_CORRETOS'";

	$DB->query($query);
	$DB->next_record();
	$total_corretos = $DB->f(0);
	$total_bounces = $total_enviados - $total_corretos;

	incluir_variavel($newsletter_id,'TOTAL_BOUNCES',$total_bounces);

}


function atualiza_var_TOTAL_ENVIOS($newsletter_id)
{
	//echo ("total envios\n");

	$tabela_envio = tabela_envio($newsletter_id);


	$query = "SELECT count(*)
			  FROM   $tabela_envio
			  WHERE  ivn_newsletter_id=$newsletter_id
			  AND    status='E'
			  AND    ivn_destinatario_id <> 0";

    #exit($query);

	$DB = new Class_DB_iqmail;
	$DB->query($query);
	$DB->next_record();

	$total_enviados = $DB->f(0);

	#echo "$query - $tabela_envio - $newsletter_id - $total_enviados";

	incluir_variavel($newsletter_id,'TOTAL_ENVIOS',$total_enviados);

	// Atualiza ivn_newsletter - campo envios
	$query = "UPDATE iqmail.ivn_newsletter SET envios = '$total_enviados' WHERE ivn_newsletter_id = '$newsletter_id'";
	$DB->query($query);

}


function atualiza_var_TOTAL_UNSUB($newsletter_id)
{
	//echo "unsubscribes\n";

	$query2 = "SELECT count(distinct email)
	           FROM   iqmail.lista_negra
			   WHERE  ivn_newsletter_id='$newsletter_id'";

	$DB2 = new Class_DB_iqmail;
	$DB2->query($query2);
	$DB2->next_record();

	$numero_unsubscribes = $DB2->f(0);

	incluir_variavel($newsletter_id,'TOTAL_UNSUB',$numero_unsubscribes);

}


function atualiza_var_TOTAL_IMPRESSOES($newsletter_id)
{
	$DB2 = new Class_DB_iqmail;

	$tabela_envio = tabela_envio($newsletter_id);

	//echo "impressoes total\n";

	$query2 = "SELECT sum(quantidade_abertura)
	           FROM   $tabela_envio
			   WHERE  ind_abertura = 'S'
			   AND    ivn_destinatario_id <> 0";

	$DB2->query($query2);
	$DB2->next_record();

	$impressoes_total = $DB2->f(0);

	incluir_variavel($newsletter_id,'TOTAL_IMPRESSOES',$impressoes_total);

}

function atualiza_var_TREAL_VIEW($newsletter_id)
{
	$DB2 = new Class_DB_iqmail;

	$tabela_envio = tabela_envio($newsletter_id);

	//echo "impressoes total\n";


	$query2 = "SELECT count(*)
	           FROM   $tabela_envio b
			   WHERE  b.ind_abertura = 'S'
			   AND    b.ivn_destinatario_id <> 0";

	$DB2->query($query2);
	$DB2->next_record();

	$impressoes_total = $DB2->f(0);

	incluir_variavel($newsletter_id,'TREAL_VIEW',$impressoes_total);

}

function atualiza_var_USUARIOS_CLICARAM($newsletter_id)
{
	$DB2 = new Class_DB_iqmail;

	$tabela_envio = tabela_envio($newsletter_id);

	//echo "usuarios clicaram\n";

	$query2 = "SELECT count(distinct a.envio2_id)
	           FROM   iqmail.log_controles a,
					  $tabela_envio b
			   WHERE  a.ivn_newsletter_id='$newsletter_id'
			   AND    a.ivn_newsletter_id = b.ivn_newsletter_id
			   AND    a.envio2_id = b.envio2_id
			   AND    b.ivn_destinatario_id <> 0";
	$DB2->query($query2);
	$DB2->next_record();

	$usuarios_clicaram = $DB2->f(0);

	incluir_variavel($newsletter_id,'USUARIOS_CLICARAM',$usuarios_clicaram);

}

function atualiza_var_TREAL_CLICK($newsletter_id)
{
	$DB2 = new Class_DB_iqmail;

	$tabela_envio = tabela_envio($newsletter_id);

	//echo "usuarios clicaram\n";

	$query2 = "SELECT count(distinct a.envio2_id)
	           FROM   iqmail.log_controles a,
					  $tabela_envio b
			   WHERE  a.ivn_newsletter_id='$newsletter_id'
			   AND    a.ivn_newsletter_id = b.ivn_newsletter_id
			   AND    a.envio2_id = b.envio2_id
			   AND    b.ivn_destinatario_id <> 0";
	$DB2->query($query2);
	$DB2->next_record();

	$usuarios_clicaram = $DB2->f(0);

	incluir_variavel($newsletter_id,'TREAL_CLICK',$usuarios_clicaram);

}


function atualiza_var_TOTAL_CLICKS($newsletter_id)
{
	$DB2 = new Class_DB_iqmail;

	$tabela_envio = tabela_envio($newsletter_id);

	//echo "total de clicks\n";

	$query2 = "SELECT count(*)
	           FROM		iqmail.log_controles a,
						$tabela_envio b
			   WHERE  a.ivn_newsletter_id='$newsletter_id'
			   AND    a.ivn_newsletter_id = b.ivn_newsletter_id
			   AND    a.envio2_id = b.envio2_id
			   AND    b.ivn_destinatario_id <> 0";
	$DB2->query($query2);
	$DB2->next_record();

	$clicks_total = $DB2->f(0);

	incluir_variavel($newsletter_id,'TOTAL_CLICKS',$clicks_total);

}


function atualiza_var_ENVIOS_CORRETOS($newsletter_id)
{
	$DB2 = new Class_DB_iqmail;

	$tabela_envio = tabela_envio($newsletter_id);

	//echo "emails corretos\n";

	$query2 = "SELECT count(*)
	           FROM   $tabela_envio
			   WHERE  ivn_newsletter_id='$newsletter_id'
			   AND    status = 'E'
			   AND    bounce_status in ('0','7','8')
			   AND    ivn_destinatario_id <> 0";

						 //echo $query2;
	$DB2->query($query2);
	$DB2->next_record();

	$emails_enviados_corretamente = $DB2->f(0);

	incluir_variavel($newsletter_id,'ENVIOS_CORRETOS',$emails_enviados_corretamente);

}


function incluir_variavel($newsletter_id,$variavel,$valor)
{
	$DB2 = new Class_DB_iqmail;

	$query2 = "SELECT count(*)
	           FROM   iqmail.var_consolidadas
			   WHERE  ivn_newsletter_id = '$newsletter_id'
			   AND    variavel='$variavel'";
	$DB2->query($query2);
	$DB2->next_record();
	$existe  = $DB2->f(0);

	if ($existe == '0')
  {
		 //echo "inserindo uma nova variavel em var_consolidadas\n";

		 $query2 = "INSERT INTO iqmail.var_consolidadas
		           (ivn_newsletter_id,variavel,valor,datahora)
					VALUES
				   ('$newsletter_id','$variavel','$valor',NOW())";
		 $DB2->query($query2);

  }
	else
  {
		 //echo "fazendo update em var_consolidadas\n";

		 $query2 = "UPDATE iqmail.var_consolidadas
		            SET    valor='$valor',datahora=now()
					WHERE  ivn_newsletter_id='$newsletter_id'
					AND    variavel = '$variavel'";

         #exit("=> ".$query2);
		 $DB2->query($query2);

	}
}

function incluir_variavel_registra($newsletter_id,$variavel,$valor)
{
	$DB2 = new Class_DB_iqmail;

	$query2 = "SELECT count(*)
	           FROM   iqmail.var_consolidadas
			   WHERE  ivn_newsletter_id = '$newsletter_id'
			   AND    variavel='$variavel'";
	$DB2->query($query2);
	$DB2->next_record();
	$existe  = $DB2->f(0);

	if ($existe == '0')
  {
		 //echo "inserindo uma nova variavel em var_consolidadas\n";

		 $query2 = "INSERT INTO iqmail.var_consolidadas
		           (ivn_newsletter_id,variavel,valor,datahora)
					VALUES
				   ('$newsletter_id','$variavel','$valor',NOW())";
		 $DB2->query($query2);

  }
	else
  {
		 //echo "fazendo update em var_consolidadas\n";

		 $query2 = "UPDATE iqmail.var_consolidadas
		            SET    valor = valor + $valor,datahora=now()
					WHERE  ivn_newsletter_id='$newsletter_id'
					AND    variavel = '$variavel'";

         #exit("=> ".$query2);
		 $DB2->query($query2);

	}
}


function acha_processo($nome)
{
	$quant = 0;
	$processos = shell_exec("ps aux");

	$linhas = explode("\n", $processos);
	#print_r($linhas);

	foreach($linhas as $key => $value)
	{
		if(preg_match("/-c/", $value)) $value = "";
		if(preg_match("/$nome/", $value)) $quant++;
	}

	if( $quant > 1 )
		return true;
	else
		return false;
}

function acha_processo2($nome)
{
	$quant = 0;
	$processos = shell_exec("ps aux");

	$linhas = explode("\n", $processos);
	#print_r($linhas);

	foreach($linhas as $key => $value)
	{
		if(preg_match("/-c/", $value)) $value = "";
		if(preg_match("/$nome/", $value)) $quant++;
	}

	if( $quant > 1 )
		return true;
	else
		return false;
}

function f_determina_revenda()
{
	GLOBAL $NOME_SISTEMA;

	#echo $NOME_SISTEMA;
	#exit;

	if( preg_match("/iq mail/", $NOME_SISTEMA) OR preg_match("/iq trade/", $NOME_SISTEMA) OR preg_match("/iqmail/", $NOME_SISTEMA) OR preg_match("/iqtrade/", $NOME_SISTEMA)) return(false);

	return(true);
}


function f_atualiza_detalhe_retirados($news){

    $DB_var = new Class_DB_iqmail;

	// 20 - INCORRETOS_OUT
	$variavel = "INCORRETOS_OUT";

	$query = "select count(0) from iqmail_smtp.envio_news_$news where bounce_status = 20";
	$DB_var->query($query);
	if ($DB_var->next_record()) $valor = $DB_var->f(0); else $valor = 0;

    f_VAR_CONSOLIDADAS($DB_var,$news,$variavel,$valor);

	// 21 - HARD BOUNCE + QUARENTENA
	$variavel = "QUARENTENA_OUT";

	$query = "select count(0) from iqmail_smtp.envio_news_$news where bounce_status = 21";
	$DB_var->query($query);
	if ($DB_var->next_record()) $valor = $DB_var->f(0); else $valor = 0;

    f_VAR_CONSOLIDADAS($DB_var,$news,$variavel,$valor);

	// 22 - LISTANEGRA OUT
	$variavel = "LISTANEGRA_OUT";

	$query = "select count(0) from iqmail_smtp.envio_news_$news where bounce_status = 22";
	$DB_var->query($query);
	if ($DB_var->next_record()) $valor = $DB_var->f(0); else $valor = 0;

    f_VAR_CONSOLIDADAS($DB_var,$news,$variavel,$valor);

	// 23 - REPETIDOS_OUT
	$variavel = "REPETIDOS_OUT";

	$query = "select count(0) from iqmail_smtp.envio_news_$news where bounce_status = 23";
	$DB_var->query($query);
	if ($DB_var->next_record()) $valor = $DB_var->f(0); else $valor = 0;

    f_VAR_CONSOLIDADAS($DB_var,$news,$variavel,$valor);

}

function f_VAR_CONSOLIDADAS($DB_var, $news,$variavel,$valor){


	$query = "delete from iqmail.var_consolidadas where ivn_newsletter_id = '$news' and variavel = '$variavel'";
	$DB_var->query($query);
	$DB_var->next_record();

	$query = "insert IGNORE into iqmail.var_consolidadas (variavel, valor, ivn_newsletter_id, datahora) values ('$variavel', '$valor', '$news', now())";
	$DB_var->query($query);

}


function f_template_IQMail_Indica(&$categorias,&$promocao){

    $categorias = "1,7,16,113";
    $data_busca = date("Y-m-d");

    # Consulta a proxima data valida dentro do calendario publicitario
    # Traz os templates relativos a esta proxima data
    $DB_var = new Class_DB_iqmail;
    
    $query = "select concat('.*',pesquisa,'.*') from iqmail.calendario_publicitario c 
			  where c.data = (select min(data) from iqmail.calendario_publicitario c where c.data >= date_format('$data_busca','%Y-%m-%d') and c.ativo = 'S')";

	$DB_var->query($query);
    if ($DB_var->next_record()) $promocao = $DB_var->f(0);
    else $promocao = "sem_promocao";

    
    $query = "select a.id_template 
			  from iqmail.template a
			  where id_categoria in ($categorias) 
			  and clientes = ''
			  and   nome_template regexp '$promocao'";
	
	//echo $query;
	
	#if($_SERVER["REMOTE_ADDR"] == "201.53.54.42") echo $query;

	$DB_var->query($query);

	$vet = array();

	while ($DB_var->next_record()){
	    $id_template = $DB_var->f(0);
	    $vet[] = $id_template;
		#if($_SERVER["REMOTE_ADDR"] == "152.237.75.184") echo "template - $id_template <br>";
	}

	#if($_SERVER["REMOTE_ADDR"] == "201.53.54.42") exit(var_dump($vet));
	return $vet;

}

function f_setaCookie($nome_cookie, $valor, $dias = 365, $path = "/", $domain="iqmail.com.br"){
	//SINTAX setcookie(name,value,expire,path,domain,secure)

	// echo '<pre>';
	// 		print_r($_COOKIE);
	// 	echo '</pre>';

	$expire = time()+60*60*24*$dias; // n dias

	setcookie($nome_cookie, $valor, $expire, $path, $domain, false);
}

function anti_injection($string)
{
	// remove palavras que contenham sintaxe sql
	$string = preg_replace("/(from|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/", "", $string);
	$string = trim($string);//limpa espacos vazio
	$string = strip_tags($string);//tira tags html e php
	$string = addslashes($string);//Adiciona barras invertidas a uma string
	return $string;
}

function anti_injection_password($string)
{
	// remove palavras que contenham sintaxe sql
	$string = preg_replace("/(from|select|insert|delete|where|drop table|show tables|#|\|--|\\\\)/", "", $string);
	$string = trim($string);//limpa espacos vazio
	$string = strip_tags($string);//tira tags html e php
	$string = addslashes($string);//Adiciona barras invertidas a uma string
	return $string;
}


function f_corrige_acentuacao($texto)
{
    GLOBAL $pagina, $DB;

	$texto = preg_replace("/·/","&aacute;",$texto);
	$texto = preg_replace("/¡/","&Aacute;",$texto);
	$texto = preg_replace("/„/","&atilde;",$texto);
	$texto = preg_replace("/√/","&Atilde;",$texto);
	$texto = preg_replace("/‚/","&acirc;",$texto);
	$texto = preg_replace("/¬/","&Acirc;",$texto);

	$texto = preg_replace("/‡/","&agrave;",$texto);
	$texto = preg_replace("/¿/","&Agrave;",$texto);
	$texto = preg_replace("/È/","&eacute;",$texto);
	$texto = preg_replace("/…/","&Eacute;",$texto);
	$texto = preg_replace("/Í/","&ecirc;",$texto);
	$texto = preg_replace("/ /","&Ecirc;",$texto);

	$texto = preg_replace("/Ì/","&iacute;",$texto);
	$texto = preg_replace("/Õ/","&Iacute;",$texto);

	$texto = preg_replace("/Û/","&oacute;",$texto);
	$texto = preg_replace("/”/","&Oacute;",$texto);
	$texto = preg_replace("/ı/","&otilde;",$texto);
	$texto = preg_replace("/’/","&Otilde;",$texto);
	$texto = preg_replace("/Ù/","&ocirc;",$texto);
	$texto = preg_replace("/‘/","&Ocirc;",$texto);

	$texto = preg_replace("/˙/","&uacute;",$texto);
	$texto = preg_replace("/⁄/","&Uacute;",$texto);

	$texto = preg_replace("/Á/","&ccedil;",$texto);
	$texto = preg_replace("/«/","&Ccedil;",$texto);

	#echo htmlspecialchars($texto);
	return $texto;
}


function f_trata_campo_insert(&$campos)
{
	foreach($campos as $tipo => $campo)
	{
		foreach($campo as $parametro=>$valor)
		{
			$lista_parametro[] = $parametro;
			if($tipo=="var")   $lista_valor[] = "'".$valor."'";
			if($tipo=="mysql") $lista_valor[] = $valor;
		}
	}

	$campos    = NULL; # RESTART O VETOR

	$parametro = implode(",", $lista_parametro);
	$valor     = implode(",", $lista_valor);

	$retorno['parametro'] = $parametro;
	$retorno['valor'] = $valor;

	return $retorno;

	# INSERE EXEMPLO
	######################################## NOVO INSERT
	#$campos['var']['email']        = $email;
	#$campos['var']['nivel']        = $nivel;
	#$campos['var']['num_abertura'] = $num_abertura;

	#$insertCampo = f_trata_campo_insert($campos);
	#$parametro   = $insertCampo['parametro'];
	#$valor       = $insertCampo['valor'];

	#$query = "INSERT IGNORE INTO ivc_clientes.USR2158_novo ($parametro) VALUES ($valor)";
	#$DB2->query($query);
}

function f_extrai_dominio($email,&$dominio_inteiro){

    $dominio = "";

	$dominio = explode('@', $email);
	$dominio = $dominio[1];

	$dominio_inteiro = $dominio;

	// corta extensoes

	$dominio = explode('.', $dominio);
	$dominio = $dominio[0];

	return $dominio;

}


?>
