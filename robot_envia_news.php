<?php
error_reporting(E_ERROR | E_PARSE);

include_once("includes/classes/db_mysql.class.php");
include_once("includes/classes/PHPMailer_v5.2/PHPMailerAutoload.php");
include_once("includes/funcoes_regras_iqmail.php");
include_once("includes/classes/credito.class.php");

$news       = $argv[1];
$CONST_SLOT = $argv[2];

if(!isset($argv[1])) exit("parametro news nao foi passado\n");
if(!isset($argv[2])) exit("parametro slot nao foi passado\n");

#// verificando se o robot ja esta rodando
if(acha_processo("robot_envia_news.php $news")) exit("\n\nRobot ja esta rodando.\n\n");

$host = $mailer;

$smtp_max_diario['outlook'] = 10; // 6.000 no mes
$smtp_max_diario['gmail']   = 10; // 6.000 no mes
$smtp_max_diario['bol']     = 10; // 6.000 no mes
$smtp_max_diario['yahoo']   = 10; // 6.000 no mes

$DB = new Class_DB_iqmail;
$DB->Database = "iqmail_smtp";
$DB->connect();

// Verifica se existe tabela de envio para o modelo, senao cria

if (!f_existe_tabela("iqmail_smtp","envio_news_".$news)){;

   $query =
             "CREATE TABLE `envio_news_$news` (
               `envio2_id` int(11) NOT NULL AUTO_INCREMENT,
               `data_envio` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
               `ivn_destinatario_id` int(11) NOT NULL DEFAULT '0',
               `ivn_newsletter_id` int(11) NOT NULL DEFAULT '0',
               `email` char(60) NOT NULL DEFAULT '',
               `nome` varchar(60) DEFAULT '',
               `status` enum('A','R','N','E') NOT NULL DEFAULT 'E',
               `parametros` varchar(1000) NOT NULL,
               `bounce_status` int(11) NOT NULL DEFAULT '0',
               `ivc_cliente_id` int(11) NOT NULL DEFAULT '0',
               `lugar_fila` int(11) DEFAULT '0',
               `numero_testes` smallint(6) DEFAULT '0',
               `tentar_apos` datetime DEFAULT '0000-00-00 00:00:00',
               `data_ultima_tentativa` datetime DEFAULT '0000-00-00 00:00:00',
               `maquina_id` int(11) NOT NULL DEFAULT '0',
               `dominio` char(30) DEFAULT '',
               `motivo_bounce` varchar(300),
               `ind_abertura` enum('S','N') DEFAULT 'N',
               `datahora_abertura` datetime DEFAULT '0000-00-00 00:00:00',
               `quantidade_abertura` int(11) DEFAULT '0',
               PRIMARY KEY (`envio2_id`),
               KEY `idx_ivn_destinatario_id` (`ivn_destinatario_id`),
               KEY `idx_status` (`status`),
               KEY `idx_cliente_id` (`ivc_cliente_id`),
               KEY `idx_email` (`email`),
               KEY `idx_dominio` (`dominio`)
             ) ENGINE=MyISAM";

   $DB->query($query);
}

global $cliente_id;

$DB->Database = "iqmail";
$DB->connect();

// MAILERS
$query = "select finalidade, ip_publico from iqmail.controle_mailer where finalidade <> 'FORA' order by 1,2";
$DB->query($query);

while ($DB->next_record())
{   
	   $mailers[$DB->f(0)][] = $DB->f(1);
}

#exit(var_dump($mailers));

// PARAMETROS DE ENVIO

$query = "select a.ivc_cliente_id,
                 a.ivn_newsletter_sender_nome,
                 a.ivn_newsletter_sender_email,
                 a.ivn_newsletter_reply_nome,
                 a.ivn_newsletter_reply_email,
                 a.ivn_newsletter_assunto,
                 a.ivn_newsletter_html_controlado,
                 b.sub_dominio,
                 a.pesquisa_id,
                 a.ivn_newsletter_status,
                 if(b.empresa = '',b.nome,b.empresa) as empresa,
                 b.creditos_pagamento,
                 a.ivn_modelo_id, b.atacado_id
          from iqmail.ivn_newsletter a, iqmail.clientes b
          where a.ivn_newsletter_id = '$news'
          and   a.ivn_newsletter_status in ('D','S')
          and   a.ivc_cliente_id = b.id";

$DB->query($query);

if ($DB->next_record()){
    $cliente_id                 = $DB->f(0);
    $sender_name                = $DB->f(1);
    $sender_email               = $DB->f(2);
    $reply_name                 = $DB->f(3);
    $reply_email                = $DB->f(4);
    $assunto                    = stripslashes($DB->f(5));
    $html_controlado            = stripslashes($DB->f(6));
    
    $CONST_dominio_envio        = $DB->f(7);
    $CONST_dominio_controle     = $DB->f(7);
    $segmentacao                = $DB->f(8);
    $news_status                = $DB->f(9);
    $nome_fantasia              = $DB->f(10);
    $creditos                   = $DB->f(11);
    $modelo_id                  = $DB->f(12);
    $atacado_id                 = $DB->f(13);
}
else exit("News $news nao esta mais ativa !");

// Verifica saldo de creditos de envio
$flag_fim_credito = false;

$finalidade = "";

switch ($news_status){

        case "S": // REENVIO
			 if($creditos < $CONST_SLOT){ $CONST_SLOT = $creditos; $flag_fim_credito=true;};        
			 $finalidade = "ENVIO";
        break;
        
        case "D": // ENVIO
			 $finalidade = "ENVIO";           
        break;        

}

switch ($cliente_id){

	   case "2802": // admin do iqmail
		   $finalidade  = "IQMAILADMIN";
	   break;
/*
	   case "25473": // buscatrips
		   $finalidade  = "BUSCATRIPS";
           break;

           case "25633": // qualcartao
		   $finalidade = "QUALCARTAO";
	   break;
 */

	   default:
		   $finalidade = "ENVIO";
	   break;

}

$max_mailers = count($mailers[$finalidade]);

// Verifica se o remetente eh valido
f_checa_remetente_valido($sender_email, $news, $CONST_dominio_envio);
//f_checa_remetente_valido($reply_email, $news, $CONST_dominio_envio);

echo "\n############  RODADA $CONST_SLOT SLOTS ATIVOS | News: ".$assunto." #############\n";

// Lista de destinatarios
// Lista de destinatarios
$query_reenvio = "";

$rowstart = 0;
$query = "select ponteiro from iqmail.ivn_newsletter where ivn_newsletter_id = '$news'";
$DB->query($query);


if ($DB->next_record()) $rowstart = $DB->f(0);
else $rowstart = 0;

if ($segmentacao == 0) // TODA BASE
    $query_total = "select * from USR$cliente_id";
else{
    $query = "select sql_segmentacao from iqmail.segmentacoes where id = '$segmentacao'";
    $DB->query($query);
    if ($DB->next_record()) $query_total = $DB->f(0);
}

$query_total  = preg_replace("/USR$cliente_id/","iqmail_contatos.USR$cliente_id a",$query_total);

if ($news_status == "S"){
    $query_reenvio = " exists (select * from iqmail_smtp.envio_news_$news b  USE INDEX (idx_ivn_destinatario_id) where b.status = 'N' and b.ivn_destinatario_id = a.usr_id)";
    if (preg_match("/where/i",$query_total))
        $query_total .= " and ".$query_reenvio;
    else
        $query_total .= " where ".$query_reenvio;

    $query  = $query_total;
    $query .= " limit 0,$CONST_SLOT";
}
else{
    $query = $query_total;
    $query .= " limit $rowstart,$CONST_SLOT";
}

$DB->query($query);

$DB_meta = new Class_DB_iqmail;
$DB_meta->Database = 'iqmail_contatos';
$metadados         = $DB_meta->metadata("USR$cliente_id", "true"); // metadados sobre a tabela

$i = 0;
while ($DB->next_record()){
       $destinatario[$i]['name']       = $DB->f('nome');
       $destinatario[$i]['email']      = $DB->f('email');
       $destinatario[$i]['id']         = $DB->f('usr_id');
       $destinatario[$i]['quarentena'] = $DB->f('quarentena');
       $destinatario[$i]['parametros'] = f_gera_parametros($metadados,$DB);
       $i++;
}

// reenvio de soft bounces, quando cliente excluir a segmentacao da base
if (($i == 0) and ($news_status == "S")){

    $query = "select * from iqmail_smtp.envio_news_$news where status = 'N'";
    $query_total = $query;
	$DB->query($query);

	while ($DB->next_record()){
		   $destinatario[$i]['name']       = $DB->f('nome');
		   $destinatario[$i]['email']      = $DB->f('email');
		   $destinatario[$i]['id']         = $DB->f('ivn_destinatario_id');
		   $destinatario[$i]['quarentena'] = 0;
		   $destinatario[$i]['parametros'] = $DB->f('parametros');
		   $i++;
	}

}

$num_registros_tabela = 0;
$query_total          = str_replace("*", "count(0)", $query_total);
$DB->query($query_total);
if ($DB->next_record()) $num_registros_tabela = $DB->f(0);

$i = 0; // contador destinatarios
$contador_creditos = 0;

$cont_mailers = 0;

foreach ($destinatario as $destino)
{
	$subject        = $assunto;
	$to_name        = $destinatario[$i]['name'];
	$to_email       = $destinatario[$i]['email'];
	$id_usr         = $destinatario[$i]['id'];
	$quarentena     = $destinatario[$i]['quarentena'];
	$parametros     = $destinatario[$i]['parametros'];

	# RORIZ - MODIFICACAO- NOME DO AFILIADO TEM ASPAS SIMPLES
	$to_name        = addslashes($to_name);
	$to_email       = addslashes($to_email);
    
    $mailer = $mailers[$finalidade][$cont_mailers];
    
	$ret = f_envio_direto_mailers($mailer);

	if($ret == "OK") $contador_creditos++;

	echo $destinatario[$i]['id']." - $sender_email - ".$mailer." - $i - ($ret) => $to_email - $news \n";

	$i++;
	$cont_mailers++;
	
	if ($cont_mailers == $max_mailers) $cont_mailers = 0;
}


// Grava a nova posicao a ser processada

switch ($news_status){

        case "S": // reenvio soft bounces (11,12,13)

                $query = "select count(0) from iqmail_smtp.envio_news_$news where status = 'N'";
                $DB->query($query);
                $DB->next_record();

                if ($DB->f(0) == 0)
                    f_atualiza_status_news($news, $news_status);
        break;

        default:
				$ponteiro = $rowstart + $i;

				echo "\n\n $rowstart - $i - ($ponteiro >= $num_registros_tabela) \n";

				if ($ponteiro >= $num_registros_tabela OR $flag_fim_credito) f_atualiza_status_news($news, $news_status);

				$query = "update iqmail.ivn_newsletter SET ponteiro = '$ponteiro' where ivn_newsletter_id = '$news'";
				$DB->query($query);

				echo "\nATUALIZA POSICAO PONTEIRO RODADAS - iqmail.ivn_newsletter $news => $ponteiro \n";

                // Promocao Template Ativa - IQ Mail Indica - NAO TIRA CREDITO DO CLIENTE
                if ( array_search($modelo_id,f_template_IQMail_Indica($categorias,$promocao)) and ($atacado_id == 0) ) $contador_credito = 0;

				$query = "UPDATE iqmail.clientes SET creditos_pagamento=creditos_pagamento - $contador_creditos where id = '$cliente_id'";
				echo "\nCREDITOS - $query \n";
				$DB->query($query);

				$query = "UPDATE iqmail.ivn_newsletter SET envios=envios + $contador_creditos WHERE ivn_newsletter_id='$news'";
				$DB->query($query);

        $credito = New CreditoClass;
        $credito->removeCredito($cliente_id, $contador_creditos);

        f_atualiza_detalhe_retirados($news);
	    break;
}

atualiza_variaveis_envio($news);

//########################################################################################
function f_envio_direto_mailers($host){
    global $cliente_id,$to_name,$to_email,$assunto,$html_controlado;
    global $sender_name,$sender_email,$reply_name,$reply_email,$id_usr;
    global $smtp, $smtp_max_diario;
    global $dia_da_rodada_inicial;
    global $ultimo_envio_especial;
    global $CONST_dominio_envio, $CONST_dominio_controle;
    global $news,$mailer,$id_usr;
    global $quarentena, $news_status, $nome_fantasia, $parametros;
    global $amazon_id;
    global $returnpath;
    global $link_descadastro;
    global $creditos;

		 // TESTES
		 #$to_name  = "Jansen Schemidt";
		 #$to_email = "jansen@jscorp.com.br";

		 $dominio = substr(strrchr($to_email,"@"),1);

		 // Insere na tabela de envio direto
		 $DB_envio =  new Class_DB_iqmail;
		 $DB_envio -> Database = "iqmail_smtp";

		 $mailer_sem_pontos = preg_replace('/[^0-9]/', '', $host);

		 $flag_listanegra = false;

		 $status = "E";

		 switch ($news_status){

		        case "S":

						$query = "select envio2_id from iqmail_smtp.envio_news_$news where ivn_destinatario_id = '$id_usr'";
						$DB_envio->query($query);
						if ($DB_envio->next_record()) $id_envio = $DB_envio->f(0);
						else $id_envio = 0;

						$query = "update iqmail_smtp.envio_news_$news set maquina_id = '$mailer_sem_pontos',numero_testes = numero_testes + 1, status = 'E', bounce_status = 0, motivo_bounce = '' where ivn_destinatario_id = '$id_usr'";
						$DB_envio->query($query);

						$status = "E";
                                                $envio_id = $id_envio;
		        break;

		        default:
                        $flag_listanegra = f_listas_negras($to_email,$dominio,$TIPO,$bounce_status);

                        switch ($TIPO){

                               case "JA_ENVIADO":
                                    return $TIPO;
                               break;

                               default:

                                    if ($bounce_status >= 20)  $status = "R";       // retirados do envio
                                    else $status = "E";

                                    $ret = $TIPO;
                               break;
                        }

						$query = "insert into iqmail_smtp.envio_news_$news (data_envio,ivn_newsletter_id,ivn_destinatario_id,nome,email,numero_testes,dominio,maquina_id, status, parametros)
								  values(now(),'$news','$id_usr','$to_name','$to_email',1,'$dominio','$mailer_sem_pontos', '$status', '$parametros')";

						$DB_envio->query($query);

						if($DB_envio->affected_rows()){
							$envio_id  = $DB_envio->getLast();
							$id_envio  = $envio_id;
						}

		        break;

		 }

         $html_final  = $html_controlado;
		 $sub_dominio = $CONST_dominio_controle;

		 // Trata variaveis dinamicas
         $uid = crypt($id_usr, "news");
         $eid = crypt($envio_id, "news");

         $nome_x = explode(" ",$to_name);
         $primeiro_nome = $nome_x[0];

         //$assunto = utf8_decode($assunto); // para acenturar corretamente 18/08/2017 - tirado em 24/08/2017, pois passamos a usar o header utf8 da phpmailer
         $assunto_final   = str_replace("troca##destinatario_nome##","$to_name","$assunto");
         $assunto_final   = str_replace("troca##primeiro_nome##","$primeiro_nome",$assunto_final);
         $assunto_final   = str_replace("troca##nome##","$primeiro_nome",$assunto_final);	
	     $assunto_final   = str_replace("troca##destinatario_email##","$to_email","$assunto_final");
		 $creditos_ativos = number_format($creditos, 0, '', '.');
	     $assunto_final   = str_replace("troca##creditos##","$creditos_ativos","$assunto_final");	     

         //*******************************

         // coloca os parametros em html_final
         $parametros_array = parse_parametros($parametros);

         $i =0;
         while (list($par,$valor) = each ($parametros_array))
         {
             $html_final = str_replace("troca##$par##","$valor","$html_final");

             $assunto_final = str_replace("troca##$par##","$valor",$assunto_final);
             $texto = str_replace("troca##$par##","$valor","$texto");

             // agora tambem pode variar o sender e o email de retorno
             // (basta gravar o troca##campo_da_base## no nome_sender e ou email_sender
             // da tabela iqmail.ivn_newsletter
             $sender_name  = str_replace("troca##$par##","$valor","$sender_name");
             $sender_email = str_replace("troca##$par##","$valor","$sender_email");

             $campocond[$i] = $par;
             $valorcond[$i] = $valor;
             $i++;
         }// Fim do While

         if($numcond = substr_count($html_final,'##CONDICAO'))
         {
             $html_final = troca_condicoes($html_final,$valorcond,$campocond, $numcond);
         }

         //*******************************


         $link_descadastro     = "http://$sub_dominio/descadastro/?a=$cliente_id&b=$envio_id&email=$to_email&n=$news";
         $link_encaminhe_amigo = "http://$sub_dominio/indique/?newsletter_id=$news&cliente=$cliente_id&from=$sender_email";

         $html_final = str_replace("troca##user_id##","$envio_id","$html_final");
         $html_final = str_replace("troca##nome##","$to_name","$html_final");
         $html_final = str_replace("troca##primeiro_nome##","$primeiro_nome","$html_final");
         $html_final = str_replace("troca##envio2_id##","$envio_id","$html_final");
         $html_final = str_replace("troca##destinatario_nome##","$to_name","$html_final");
         $html_final = str_replace("troca##destinatario_email##","$to_email","$html_final");
         $html_final = str_replace("troca##nome_fantasia##","$nome_fantasia","$html_final");
         $html_final = str_replace("troca##sender_name##","$sender_name","$html_final");
         $html_final = str_replace("troca##link_descadastro##","$link_descadastro","$html_final");

         $html_final = str_replace("troca##email##","$to_email","$html_final");
         $html_final = str_replace("troca##ivn_newsletter_sender_name##","$sender_name","$html_final");
         $html_final = str_replace("troca##ivn_newsletter_sender_email##","$sender_email","$html_final");
         $html_final = str_replace("troca##nome_remetente##","$to_name","$html_final");
         $html_final = str_replace("troca##descadastro##","$link_descadastro","$html_final");
         $html_final = str_replace("troca##encaminhe_amigo##","href=\"$link_encaminhe_amigo\"","$html_final");
         $html_final = str_replace("troca##news_id##","$news","$html_final");
         $html_final = str_replace("troca##cliente_id##","$cliente_id","$html_final");
         $html_final = str_replace("troca##creditos##","$creditos_ativos","$html_final");         

		 #echo "\n\n ######## ASSUNTO #######\n$assunto_final\n\n";
		 #echo "\n\n ######## HTML #######\n$html_final\n\n";
		 #exit();

		 $amazon_id = "";

         if (!$flag_listanegra){

             $ret        = enviar_email_mailer($host, $sender_name,$sender_email,$assunto_final,$html_final,$to_email,$to_name, $sub_dominio, $id_envio, $bounce_status);
         }

         if ($ret <> "OK"){
            $query = "update iqmail_smtp.envio_news_$news set status = '$status', bounce_status = '$bounce_status', motivo_bounce = '$ret' where envio2_id = '$id_envio'";
            $DB_envio->query($query);
         }


         return $ret;
}

function enviar_email_mailer($host, $sender_name, $sender_email, $subject, $modelo_html, $to_email, $to_name, $sub_dominio, $envio_id, &$bounce_status)
{
	global $smtp, $smtp_max_diario;
	global $dia_da_rodada_inicial;
	global $ultimo_envio_especial;
	global $CONST_dominio_envio, $CONST_dominio_controle;
    global $cliente_id,$id_usr;
    global $news,$mailer;
    global $mail;
    global $returnpath;
    global $link_descadastro;
    global $reply_name,$reply_email;    


    $mail = new PHPMailer();
	
    switch ($cliente_id){

               case "2802": // admin
                    $mail->AddBCC('admin', 'iqmail@iqmailcomunica.com.br');
               break;
    }

    // para usar SMTP acessando outra máquina com a phpmailer 5.2, tem que usar usuario de autenticacao	
	 
    $mail->IsSMTP(); // Use SMTP
    $mail->Host     = $host; // Sets SMTP server
	$mail->Timeout  =   1; // em segundos	    
	$mail->IsHTML(true);	
      
    
	$mail->SMTPSecure  = "tls"; //Secure conection
    $mail->Port        = 587; // set the SMTP port
	$mail->CharSet     = 'UTF-8';
    $mail->Encoding    = '8bit';
	$mail->Subject     = $subject;
	$mail->AltBody     = strip_tags($html_final);	
    $mail->ContentType = 'text/html; charset=utf-8\r\n';
    $mail->WordWrap    = 900; // RFC 2822 Compliant for Max 998 characters per line
    

	$mail->SMTPOptions = array(
		'ssl' => array(
			'verify_peer' => false,
			'verify_peer_name' => false,
			'allow_self_signed' => true
		)
	);	


    $dominio = f_extrai_dominio($to_email,$dominio_inteiro);

	$mail->AddCustomHeader("Precedence: bulk");   				  
	$mail->AddCustomHeader("List-Unsubscribe: <".$link_descadastro.">");

    switch ($dominio){

            default:
            break;

            case "outlook":
            case "live":
            case "hotmail":

            break;

            case "gmail":
            
				  # Feedback Loop do Gmail (https://support.google.com/mail/answer/6254652)
				  # a - campaign (optional)
				  # b - customer (optional)
				  # c - other (optional)
				  # SenderId - unique identifier 5-15 characters (mandatory) 
				  # Feedback-ID: a:b:c:SenderId
				  $mail->AddCustomHeader("Feedback-ID: $news:$cliente_id:$envio_id:$news|$envio_id");
				  $mail->AddCustomHeader("Precedence: bulk");    				

            break;

            case "bol":
            case "uol":
            case "terra":
            case "ig":

            break;

            case "yahoo":

            break;

    }
    
	// mailers

	$mail->Hostname = $CONST_dominio_envio;
	$mail->AddCustomHeader("iqmail-tag: $news|$envio_id");

    $returnpath     = "mailer@".$CONST_dominio_envio; 
	$mail->Sender   =  $returnpath;
	$mail->FromName = "$sender_name";
	$mail->From     = "$sender_email";	
	$sender_nome    = "$sender_name";

	$mail->Body     = "$modelo_html"; // Corpo da mensagem
    $mail->AltBody  = strip_tags($modelo_html);

	// Corpo da mensagem - modo texto
	$mail->AddAddress("$to_email","$to_name");
	$mail->AddReplyTo("$reply_email","$reply_name");
	
	#$mail->AddAddress("jansen@jscorp.com.br","Jansen");	
	//$mail->AddCC('person1@domain.com', 'Person One');	
	
	$sender_domain         = substr(strrchr($sender_email, "@"), 1);
	$file_dkim_domain      = "/etc/opendkim/keys/$sender_domain/senderdirect.private";

    # Se existir chave gerada para o dominio do from, assina o header da mensagem com DKIM
	if (file_exists($file_dkim_domain)){
		$mail->DKIM_domain     = $sender_domain;	
		$mail->DKIM_private    = $file_dkim_domain;
		$mail->DKIM_selector   = 'senderdirect';
		$mail->DKIM_passphrase = '';
		$mail->DKIM_identity   = $mail->From;		
	}

    #exit(var_dump($mail));

    $retorno = $mail->Send();

    # Limpa o vetor de envios. Estamos enviando emails 1 a 1, ou seja, somente 1 TO na mensagem
	$mail->ClearAddresses();
	$mail->ClearAllRecipients();
	$mail->ClearCustomHeaders();
    
    $mail->SmtpClose();	    
    
	if(!$retorno){
		#echo "Mensagem nao enviada";
		echo "Erro no envio:" . $mail->ErrorInfo;

		$bounce_status = 12;
		return "ERRO";
	}
	else{

		$bounce_status = 0;
		return "OK";
    }		

}


function f_listas_negras($email,$dominio,&$TIPO,&$bounce_status){
    global $cliente_id;
    global $news,$mailer;
    global $quarentena, $id_usr;

	// ATUALIZA DADOS RELATORIO ( REPETIDOS / LISTA NEGRA / INCORRETOS / QUARENTENA )

    $DB_lista_negra = new Class_DB_iqmail;

    // Verifica repetido de envio que caiu no meio
    $query = "select count(0) from iqmail_smtp.envio_news_$news where ivn_destinatario_id = '$id_usr'";
    #echo $query."\n";
    $DB_lista_negra->query($query);
    if($DB_lista_negra->next_record()){
       if ($DB_lista_negra->f(0) <> 0){ $TIPO = "JA_ENVIADO"; return true; }
    }

    /*
    // Dominio problematico
    switch ($dominio){
            case "hotmail.com":
            case "live.com":
            case "outlook.com":

                 if ($news <> 354001) // Retorna VIP
                 {
                     $TIPO = "DOMINIO BLOQUEADO";
                     return true;
                 }

            break;

            case "gmail.com":
            case "yahoo.com":
            case "yahoo.com.br":
                  $TIPO = "DOMINIO BLOQUEADO";
                  return true;
            break;
    }
    */


    // campo quarentena
    switch ($quarentena){
            case 2:
                  $bounce_status = 20;
                  $TIPO = "INCORRETO";
                  return true;
            break;

            case 0:
            break;

            default:
                  $bounce_status = 21;
                  $TIPO = "QUARENTENA";
                  return true;
            break;
    }



    // Verifica tabela lista_negra
    $query = "select count(0) from iqmail.lista_negra where email = '$email' and ivc_cliente_id = '$cliente_id'";
    $DB_lista_negra->query($query);
    if ($DB_lista_negra->next_record()){
       if ($DB_lista_negra->f(0) <> 0){ $TIPO = "LISTA_NEGRA"; $bounce_status = 22; return true; }
    }

    // Verifica repetido
    $query = "select count(0) from iqmail_smtp.envio_news_$news where email = '$email' and ivn_destinatario_id <> 0";
    $DB_lista_negra->query($query);
    if ($DB_lista_negra->next_record()){
       if ($DB_lista_negra->f(0) <> 0){ $TIPO = "LISTA_REPETIDO"; $bounce_status = 23; return true; }
    }

    // Verifica tabela dominio proibido notificacoes
    $query = "select * from iqmail.dominios_proibidos where UCASE(dominio) = UCASE('$dominio')";
    $DB_lista_negra->query($query);
    if ($DB_lista_negra->next_record()){
       if ($DB_lista_negra->f(0) <> 0){ $TIPO = "LISTA_DOMINIO_PROIBIDO"; $bounce_status = 22; return true; }
    }

    // Verifica hard bounces
    $query = "select count(0) from iqmail.controle_hard where email = '$email'";
    $DB_lista_negra->query($query);
    if ($DB_lista_negra->next_record()){
       if ($DB_lista_negra->f(0) <> 0){ $TIPO = "LISTA_HARD_BOUNCE"; $bounce_status = 21; return true; }
    }


    return false;
}

function f_existe_tabela($database,$tabela){

    $DB_tabela = new Class_DB_iqmail;
	$DB_tabela->Database = $database;
	$DB_tabela->connect();

	$query = "show tables";

	$DB_tabela->query($query);

	$achou = false;
	while ( ($DB_tabela->next_record()) and (!$achou) )
	{
		  $tabela_lida  = $DB_tabela->f(0);
		  if($tabela_lida == $tabela) $achou = true;
	}

    return $achou;
}


function f_atualiza_status_news($news, $status)
{
    global $cliente_id;

	$DB_news = new Class_DB_iqmail;

	switch ($status)
	{
		case "D":
			$query = "UPDATE iqmail.ivn_newsletter SET ivn_newsletter_status='E', data_termino_envio=NOW() WHERE ivn_newsletter_id='$news' LIMIT 1";
			$DB_news->query($query);

			// Marcar que o cliente já enviou news
			$query = "UPDATE clientes SET status = 3 WHERE id = '$cliente_id' LIMIT 1";
			$DB_news->query($query);
		break;

		case "S":
			$query = "UPDATE iqmail.ivn_newsletter SET ivn_newsletter_status='E' WHERE ivn_newsletter_id='$news' LIMIT 1";
			$DB_news->query($query);
		break;

		default:
		break;
	}

	######################################## ADICIONAR FUNCOES ABAIXO NA CLASSE 'credito_controle.class.php'
	echo "HISTORICO CREDITO - ATUALZIANDO\n";

	######################################## BUSCA INFORMACOES DO CLIENTE
	$DB = new Class_DB_iqmail;
	$query = "SELECT ivc_cliente_id FROM iqmail.ivn_newsletter WHERE ivn_newsletter_id='$news'";
	$DB->query($query);
	$DB->next_record();
	$ivc_cliente_id = $DB->f('ivc_cliente_id');

	######################################## DEFINE QUANTIDADE ENVIADA
	$query = "SELECT count(0) FROM iqmail_smtp.envio_news_$news WHERE bounce_status='E' AND ivn_destinatario_id <>'0'";
	$DB->query($query);
	$DB->next_record();
	$qtd_envio = $DB->f(0);

	######################################## DEFINE CREDITO - ATIVO
	$query = "SELECT creditos_pagamento FROM iqmail.clientes WHERE id='$ivc_cliente_id'";
	$DB->query($query);
	$DB->next_record();
	$creditos_pagamento = $DB->f('creditos_pagamento');

	######################################## NOVO INSERT
	$campos['var']['ivc_cliente_id']    = $ivc_cliente_id;
	$campos['var']['tipo']              = 'debito';
	$campos['var']['saldo']             = $creditos_pagamento;
	$campos['var']['credito']           = $qtd_envio;
	$campos['var']['ivn_newsletter_id'] = $news;
	$campos['var']['descricao']         = "Campanha: $news";

	$insertCampo = f_trata_campo_insert($campos);
	$parametro   = $insertCampo['parametro'];
	$valor       = $insertCampo['valor'];

	$query = "INSERT IGNORE INTO iqmail.creditos_historico ($parametro) VALUES ($valor) ON DUPLICATE KEY UPDATE credito=$qtd_envio";
	$DB->query($query);
}


function f_gera_parametros($metadados,$DB){

  //echo "Busca metadados \n";
  $cont = 0;
  $tot_campos = 0;

  $tot_metadados     = $metadados['num_fields'];

  //echo "$tot_metadados\n";
  $campos = array();

  // Pega nomes dos campos

  for ($j=0; $j<$tot_metadados; $j++)
  {
      $campo = $metadados[$j]['name'];
      //echo "campo = $campo\n";
      switch ($campo)
      {
              case "email":break;
              case "status":break;
              case "nome":break;
              default: $campos[] = $campo;
      }
  }

  // Pega valores dos campos

  $parametros = "";
  for ($k=0; $k<count($campos); $k++)
  {
       $campo = $campos[$k];
       //echo "abrindo $campo\n";
       $parametros .= "$campo=".urlencode($DB->f($campo))."&";
  }

  return $parametros;

}

function parse_parametros($par)
{
	//echo ("parametros = $par\n");
	$retorno = Array();
	$array1 = explode ("&",$par);
	foreach ($array1 as $duplas)
	{
		$array2 = explode ("=",$duplas);
		if (count($array2) == 2)
		{
				$retorno[$array2[0]] = urldecode($array2[1]);
		}
	}

	return($retorno);
}

function f_checa_remetente_valido(&$remetente, $news, $dominio_envio){
    global $cliente_id; 

    $remetente_original = $remetente;
    
    switch ($cliente_id){
    
           default:

			   $dominio = substr(strrchr($remetente,"@"),1);			
			   
			   $comando = "dig txt $dominio +short | grep -e senderdirect.com -e maildireto.com";				  
			   $retorno = exec($comando);	

			   // retira o e. do e.exemplo.com.br
			   $dominio_envio = preg_replace("/e\./i","",$dominio_envio);
			   if ( (!$retorno) or (preg_match("/iqmailcomunica/i",$remetente)) ) $remetente = "comunica".$news."@".$dominio_envio;	  
           
           break;
           
           case "#23800": //rdigital@directmkt.com.br
               $remetente = $remetente_original;
           break;
    
    }


}

?>
