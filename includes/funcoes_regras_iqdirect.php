<?php
#header('Content-Type: text/html; charset=UTF-8');
#header('Content-Type: text/html; charset=iso-8859-1');

register_globals();

if(!isset($_SESSION)) session_start(); 


// Função que define qual tabela de envio será utilizada em uma consulta
//***********************************************************************

function f_atualiza_detalhe_retirados($news){

    $DB_var = new Class_DB_iqdirect;

	// 20 - INCORRETOS_OUT
	$variavel = "INCORRETOS_OUT";

	$query = "select count(0) from smtp.envio_news_$news where bounce_status = 20";
	$DB_var->query($query);
	if ($DB_var->next_record()) $valor = $DB_var->f(0); else $valor = 0;

    f_VAR_CONSOLIDADAS($DB_var,$news,$variavel,$valor);

	// 21 - QUARENTENA_OUT
	$variavel = "QUARENTENA_OUT";

	$query = "select count(0) from smtp.envio_news_$news where bounce_status = 21";
	$DB_var->query($query);
	if ($DB_var->next_record()) $valor = $DB_var->f(0); else $valor = 0;

    f_VAR_CONSOLIDADAS($DB_var,$news,$variavel,$valor);

	// 22 - LISTANEGRA_OUT
	$variavel = "LISTANEGRA_OUT";

	$query = "select count(0) from smtp.envio_news_$news where bounce_status = 22";
	$DB_var->query($query);
	if ($DB_var->next_record()) $valor = $DB_var->f(0); else $valor = 0;

    f_VAR_CONSOLIDADAS($DB_var,$news,$variavel,$valor);

	// 23 - REPETIDOS_OUT
	$variavel = "REPETIDOS_OUT";

	$query = "select count(0) from smtp.envio_news_$news where bounce_status = 23";
	$DB_var->query($query);
	if ($DB_var->next_record()) $valor = $DB_var->f(0); else $valor = 0;

    f_VAR_CONSOLIDADAS($DB_var,$news,$variavel,$valor);

}

function f_VAR_CONSOLIDADAS($DB_var, $news,$variavel,$valor){

	$query = "update versao2.var_consolidadas set valor = '$valor' where ivn_newsletter_id = '$news' and variavel = '$variavel'";
	$DB_var->query($query);
	$DB_var->next_record();

	if ($DB_var->affected_rows() == 0){

		$query = "insert IGNORE into versao2.var_consolidadas (variavel, valor, ivn_newsletter_id, datahora) values ('$variavel', '$valor', '$news', now())";
		$DB_var->query($query);

	}

}

	
function tabela_envio($news_id, $controle_id = 0)
{
	$resultado = "versao2.envio2";

	$DB = new Class_DB_iqdirect;
	
	if ($controle_id <> 0){
	    $query = "SELECT ivn_newsletter_id FROM ivn.ivn_controle WHERE ivn_controle_id = '$controle_id'";
	    $DB->query($query);
	    $DB->next_record();
	    $news_id = $DB->f(0);			
	}
	
	$query = "SELECT ivc_cliente_id, ind_database_envios, versao
	          FROM   ivn.ivn_newsletter
			  WHERE  ivn_newsletter_id = '$news_id'";	
	
	$DB->query($query);
	$DB->next_record();
	$cliente_id = $DB->f(0);
	$status     = $DB->f(1);
	$versao     = $DB->f(2);	
	//echo "status = $status<p>";	
	
    switch ($versao){
    
            case "4": // ofertadinamica e retorna            
                    return ("smtp.envio_news_".$news_id);    
            break;
    
            default:	
                    if ($status == 'S')
                    {
                         return ("envios.envio2_".$cliente_id);
                    }
                    else
                    {
                         $tabela_envio = "envio2_cliente_".$cliente_id;
         
                         f_existe_tabela_envio($tabela_envio);			 
         
                         $tabela_envio = "enviando.".$tabela_envio;		
              
                         return ($tabela_envio);		 
                    }	 
            break;
    }
		 
}	 	 


function acha_processo($nome)
{
	$quant = 0;
	#$processos = shell_exec("ps aux |grep -v 'grep $nome'| grep -v 'sh -c' | grep $nome");
	$processos = shell_exec("ps aux | grep -v 'sh -c' | grep '$nome'");
	
	$linhas = explode("\n", $processos);
	#print_r($linhas);
	
	foreach($linhas as $key => $value)
	{
		if(preg_match("/-c/",$value)) $value = "";
		if(preg_match("/grep/",  $value)) $value = "";
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

function remove_acentos($str, $enc = 'iso-8859-1'){

$acentos = array(
    'A' => '/&Agrave;|&Aacute;|&Acirc;|&Atilde;|&Auml;|&Aring;/',
    'a' => '/&agrave;|&aacute;|&acirc;|&atilde;|&auml;|&aring;/',
    'C' => '/&Ccedil;/',
    'c' => '/&ccedil;/',
    'E' => '/&Egrave;|&Eacute;|&Ecirc;|&Euml;/',
    'e' => '/&egrave;|&eacute;|&ecirc;|&euml;/',
    'I' => '/&Igrave;|&Iacute;|&Icirc;|&Iuml;/',
    'i' => '/&igrave;|&iacute;|&icirc;|&iuml;/',
    'N' => '/&Ntilde;/',
    'n' => '/&ntilde;/',
    'O' => '/&Ograve;|&Oacute;|&Ocirc;|&Otilde;|&Ouml;/',
    'o' => '/&ograve;|&oacute;|&ocirc;|&otilde;|&ouml;/',
    'U' => '/&Ugrave;|&Uacute;|&Ucirc;|&Uuml;/',
    'u' => '/&ugrave;|&uacute;|&ucirc;|&uuml;/',
    'Y' => '/&Yacute;/',
    'y' => '/&yacute;|&yuml;/',
    'a.' => '/&ordf;/',
    'o.' => '/&ordm;/');

    return preg_replace($acentos, array_keys($acentos), htmlentities($str, ENT_NOQUOTES, $enc));
}

function f_tecnologia()
{
	if($_SERVER['REMOTE_ADDR'] == "201.76.185.175")
		return true;
	else
		return false;
}

function f_define_prioridade($quantidade_envio)
{
	$lugar_fina = 3;

	if($quantidade_envio >= 500000) $lugar_fina = 3;
	if($quantidade_envio <= 500000 AND $quantidade_envio >= 10000)    $lugar_fina = 2;
	if($quantidade_envio <= 10000) $lugar_fina = 1;

	return $lugar_fina;
}

function anti_injection2($sql)
{
	// remove palavras que contenham sintaxe sql
	$sql = preg_replace(sql_regcase("/(from|truncate|select|insert|delete|where|drop table|show tables|#|\*|--|\\\\)/"),"",$sql);
	$sql = trim($sql);//limpa espaços vazio
	$sql = strip_tags($sql);//tira tags html e php
	$sql = addslashes($sql);//Adiciona barras invertidas a uma string
	$sql = trim($sql);
	return $sql;
}

function register_global_array( $sg ) {
    Static $superGlobals    = array(
        'e' => '_ENV'       ,
        'g' => '_GET'       ,
        'p' => '_POST'      ,
        'c' => '_COOKIE'    ,
        'r' => '_REQUEST'   ,
        's' => '_SERVER'    ,
        'f' => '_FILES'
    );
   
    Global ${$superGlobals[$sg]};
   
    foreach( ${$superGlobals[$sg]} as $key => $val ) {
        $GLOBALS[$key]  = $val;
    }
}
 
function register_globals( $order = 'gpc' ) {
    $_SERVER;       //See Note Below
    $_ENV;
    $_REQUEST;    
   
    $order  = str_split( strtolower( $order ) );
    array_map( 'register_global_array' , $order );
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

function f_insere_aprovacao(&$html_final, $news_nome, $newsletter_id, $parametro_chave)
{
	// Recuperar o ID da segmentacao e lingua de descadastro contida na NEWS
	$DB_seg = new Class_DB_iqdirect;
	$query  = "SELECT pesquisa_id, lingua_descadastro, ivc_cliente_id FROM ivn.ivn_newsletter WHERE ivn_newsletter_id = '$newsletter_id'";
	
	$DB_seg->query($query);
	$DB_seg->next_record();
	$segmentacao_id     = $DB_seg->f(0);
	$lingua_descadastro = $DB_seg->f(1);
	$ivc_cliente_id     = $DB_seg->f(2);

	$query  = "SELECT sub_dominio FROM ivc.ivc_cliente WHERE ivc_cliente_id = '$ivc_cliente_id'";
	$DB_seg->query($query);
	$DB_seg->next_record();
	$sub_dominio = $DB_seg->f(0);


	// Se a segmentacao for toda a base
	if($segmentacao_id == 0)
	{
	  	$nome_segmentacao = "TODA BASE";
	}

	// Pegar o nome da segmentacao
	else
	{
	  	$query = "SELECT nome FROM ivc_clientes.pesquisas WHERE id = '$segmentacao_id'";
	  	$DB_seg->query($query);
	  	$DB_seg->next_record();
	  	$nome_segmentacao = $DB_seg->f(0);
	}

	/* NEWS encriptada para concatenar no link */
	$newsletter_id = encripta_news($newsletter_id);

	/* envio2_id encriptado e passado pelo link */
	$epc = crypt($parametro_chave,"news");

	/* Define idioma do texto de aprovacao */
	$texto = Array();

	if($lingua_descadastro == 99)
	{
		//echo $lingua_descadastro;exit;
		$query = "SELECT lingua FROM ivc.ivc_cliente WHERE ivc_cliente_id = '$ivc_cliente_id'";
	  	$DB_seg->query($query);
	  	$DB_seg->next_record();
	  	$lingua_descadastro = $DB_seg->f(0);

	}

	$texto[] = "Atenc&atilde;o!";
	$texto[] = "Esse email &eacute; um email teste da newsletter";
	$texto[] = "que ser&aacute; enviado para a segmenta&ccedil;&atilde;o";
	$texto[] = "Para voc&ecirc; a newsletter est&aacute; OK para envio?";
	$texto[] = "Sim";
	$texto[] = "N&atilde;o";


	if( eregi("</body",$html_final) ) //verifica case-insensitive
	{
		$pos_body = strripos($html_final, "</body>");
		$aux_html = substr($html_final, 0, $pos_body); //FIM DO BODY

		/* Enfim texto de aprovacao concatenado com o HTML original do envio teste */
		$aux_html .= "<table align=\"center\" width=\"600\" border=\"1\" style=\"border: #6B696A 1px solid; 1px solid;\">
			<tr>
			  <td>
					<table align=\"center\" width=\"600\" >
			  <tr>
					  <td width=\"20%\">&nbsp;</td>
				<td width=\"60%\" align=\"center\" style=\"font-family: Verdana; font-size: 10 px; padding: 1\"><b>".utf8_decode($texto[0])."</b></font><br></td>
					  <td width=\"20%\">&nbsp;</td>
				  </tr>
				</table>

				<table align=\"center\" width=\"600\" >
				  <tr>
					  <td>&nbsp;</td>
				<td width=\"90%\" align=\"left\" style=\"font-family: Verdana; font-size: 10 px; padding: 1\"><br>
							  <p>".utf8_decode($texto[1])." <b>$news_nome</b>, ".utf8_decode($texto[2])." <b>$nome_segmentacao</b>.</p>
						<!--<p>".utf8_decode($texto[3])."</p>-->
						</font><br>
							</td>
					  <td>&nbsp;</td>
				  </tr>
				</table>

				<table align=\"center\" width=\"600\">
			    <tr>
				<td align=\"right\" width=\"45%\" style=\"font-family: Verdana; font-size: 10 px; padding: 1\">
					<a href=\"https://$sub_dominio/iqdirect/preenvio/aprovar.php?op=S&nid=$newsletter_id&epc=$epc&pc=$parametro_chave\" style=\"color:#000000;\"><b>".utf8_decode($texto[4])."</b></a>
				  </td>
				  <td width=\"10%\">&nbsp;</td>
				  <td align=\"left\" width=\"45%\" style=\"font-family: Verdana; font-size: 10 px; padding: 1\">
							  <a href=\"https://$sub_dominio/iqdirect/preenvio/aprovar.php?op=N&nid=$newsletter_id&epc=$epc&pc=$parametro_chave\" style=\"color:#000000;\"><b>".utf8_decode($texto[5])."</b></a>
				</td>
			</tr>
		  </table>
			</td>
		  </tr>
		</table>";

		$html_final = $aux_html.substr($html_final, $pos_body);
	}
	else
	{
		/* Enfim texto de aprovacao concatenado com o HTML original do envio teste */
		$html_final .= "<table align=\"center\" width=\"600\" border=\"1\" style=\"border: #6B696A 1px solid; 1px solid;\">
			<tr>
			  <td>
					<table align=\"center\" width=\"600\" >
			  <tr>
					  <td width=\"20%\">&nbsp;</td>
				<td width=\"60%\" align=\"center\" style=\"font-family: Verdana; font-size: 10 px; padding: 1\"><b>".utf8_decode($texto[0])."</b></font><br></td>
					  <td width=\"20%\">&nbsp;</td>
				  </tr>
				</table>

				<table align=\"center\" width=\"600\" >
				  <tr>
					  <td>&nbsp;</td>
				<td width=\"90%\" align=\"left\" style=\"font-family: Verdana; font-size: 10 px; padding: 1\"><br>
							  <p>".utf8_decode($texto[1])." <b>$news_nome</b>, ".utf8_decode($texto[2])." <b>$nome_segmentacao</b>.</p>
						<!--<p>".utf8_decode($texto[3])."</p>-->
						</font><br>
							</td>
					  <td>&nbsp;</td>
				  </tr>
				</table>

				<table align=\"center\" width=\"600\">
			    <tr>
				<td align=\"right\" width=\"45%\" style=\"font-family: Verdana; font-size: 10 px; padding: 1\">
					<a href=\"https://$sub_dominio/iqdirect/preenvio/aprovar.php?op=S&nid=$newsletter_id&epc=$epc&pc=$parametro_chave\" style=\"color:#000000;\"><b>".utf8_decode($texto[4])."</b></a>
				  </td>
				  <td width=\"10%\">&nbsp;</td>
				  <td align=\"left\" width=\"45%\" style=\"font-family: Verdana; font-size: 10 px; padding: 1\">
					<a href=\"https://$sub_dominio/iqdirect/preenvio/aprovar.php?op=N&nid=$newsletter_id&epc=$epc&pc=$parametro_chave\" style=\"color:#000000;\"><b>".utf8_decode($texto[5])."</b></a>
				</td>
			    </tr>
		        </table>
			</td>
		  </tr>
		</table>";
	}


}

function encripta_news($news)
{
if (intval($news) == 0) return 0;
//global $S_cliente;
//echo "criando senha para $news<br>";
$DB = new Class_DB_iqdirect;
$query = "SELECT ivn_newsletter_nome,ivn_newsletter_data_cadastro,ivc_cliente_id 
		  FROM ivn.ivn_newsletter 
		  WHERE ivn_newsletter_id = '$news'";
$DB->query($query);
$DB->next_record();
$nome_news = trim($DB->f(0));
$data_cadastro = $DB->f(1);
$cliente = $DB->f(2);
$segundos = substr($data_cadastro,strlen($data_cadastro)-2,2);
$minutos = substr($data_cadastro,strlen($data_cadastro)-5,2);
//echo "$nome_news,$data_cadastro,seg = $segundos,min = $minutos<br>";
//$primeiras_letras = substr($nome_news,0,2);
//$ultimas_letras = substr($nome_news,strlen($nome_news)-2,2);
$base = "$segundos$minutos".strlen($news)."$news";
$valor = 0;
for ($i = 0;$i<strlen($base);$i++)
		{
		//echo "char = ".substr($base,$i,1)."<br>";
		$valor += intval(substr($base,$i,1));
		}
//echo "valor = $valor<br>";
while (strlen($valor) <2) $valor = "0".$valor;
//echo "crc = $valor<br>";
$base = $base.$valor;
$retorna = "";
$transforma = array("X","T","A","H","U","Y","W","F","S","M");
for ($i = 0;$i<strlen($base);$i++)
		{
		$char=substr($base,$i,1);
		$retorna .= $transforma[intval($char)];
		}
//echo "vou retornar $retorna<br>";
return ($retorna);
}

function decripta_news($base)
{
$transforma = array("X","T","A","H","U","Y","W","F","S","M");
$working = "";
for ($i = 0;$i<strlen($base);$i++)
		{
		$char = substr($base,$i,1);
		$working .= array_search ($char,$transforma);
		}
//echo "working = $working<br>";
$segundos = substr($working,0,2);
$minutos = substr($working,2,2);
$tamanho = substr($working,4,1);
$news = substr($working,5,$tamanho);
$certo = encripta_news($news);
if ($certo == $base)
	 {
	 return ($news);
	 }
else
   {
	 return(0);
	 }
}


function troca_condicoes($refazer,$valorfora,$campofora, $numero_cond)
{
	for($i=1;$i<=$numero_cond;$i++){

		$comeco = strpos($refazer,'##CONDICAO');
		$fim = strpos($refazer,'##FIM CONDICAO##')+ (16 - $comeco);
		$blocoi = substr($refazer, $comeco, $fim);
		$bloco = explode('##',$blocoi);
		$conteudo = $bloco[2];
		$separa_linha_condicao = explode(',',$bloco[1]);
		$campo_condicao = $separa_linha_condicao[1];
		$campo_valor = $separa_linha_condicao[2];
		$achou = 'n';

		for($j=0;$j<count($campofora);$j++){

				if((strtoupper($campo_condicao) == strtoupper($campofora[$j])) AND (strtoupper($campo_valor) == strtoupper($valorfora[$j]))){
					$refazer = substr_replace($refazer, $conteudo, $comeco, $fim );
					$achou='s';
				}
				elseif( ( strtoupper($campo_condicao) == strtoupper($campofora[$j]) ) AND ( strtoupper($campo_valor) == "&&" ) AND ( $valorfora[$j] <> "" AND $valorfora[$j] <> null ) )
				{
					$refazer = substr_replace($refazer, $conteudo, $comeco, $fim );
					$achou='s';
				}
		}

	 	if($achou == 'n'){ $refazer = substr_replace($refazer, "", $comeco, $fim ); }
	}

	return $refazer;
}

function f_parque_IP($cliente_id) {

	$cliente = "ENVIO";
       
        $DB    = new Class_DB_iqdirect;
	$query = "select mailers from ivc.ivc_cliente where ivc_cliente_id = '$cliente_id'";
	$DB->query($query);
	if ($DB->next_record()) $cliente = trim($DB->f(0));

        return $cliente;
}

function f_parque_IP_pesquisa($pesquisa_id) {

        $mailer = "";
        $DB = new Class_DB_iqdirect;
        $query = "select mailer from iqmail.controle_pesquisa_mailer where pesquisa_id = '$pesquisa_id'";
        $DB->query($query);
        if ($DB->next_record()) $mailer = trim($DB->f(0));

        return $mailer; 

}

?>
