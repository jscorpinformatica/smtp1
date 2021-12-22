<?php
// TEM QUE SALVAR COMO ISO LATIN 9 O ARQUIVO PHP

//error_reporting(E_ALL);
//ini_set('display_errors', 1);

require_once("includes/classes/db_mysql.class.php");
require_once("includes/funcoes_regras_iqdirect.php");

// verificando se o robot ja esta rodando
if(acha_processo("robot_carrega_ENVIOS_iqdirect.php")) exit("\n\nRobot ja esta rodando.\n\n");

// CONSTANTES
$CONST_SLOT  = 1;
$path        = "/root/smtp/";

$DB = new Class_DB_iqdirect;

$query = "select finalidade, sum(slot) from iqmail.controle_mailer where finalidade <> 'FORA' group by 1";
$DB->query($query);

while ($DB->next_record())
{   
       $mailers[$DB->f(0)] = $DB->f(1);
}

$query = "select mailer, slot from iqmail.controle_mailer where finalidade <> 'FORA'";
$DB->query($query);

while ( $DB->next_record() ) {
       $mailers_pesquisa[$DB->f(0)] = $DB->f(1);
}

//exit(var_dump($mailers));

// PARAMETROS DE ENVIO

$query = "SELECT ivc_cliente_id, ivn_newsletter_id, ivn_newsletter_status, pesquisa_id
		  FROM ivn.ivn_newsletter
		  WHERE ivn_newsletter_status IN ('R','D','S')
		  AND   ivn_newsletter_ativa='S'
		  UNION
		  SELECT ivc_cliente_id, ivn_newsletter_id, ivn_newsletter_status, pesquisa_id
					FROM ivn.ivn_newsletter
					WHERE ivn_newsletter_status = 'H'
					AND   ivn_newsletter_ativa  ='S'
                                        AND   ponteiro = 0 
					AND   ivc_cliente_id IN (1339,2059,2167,1162,1108,1189)	  
		  ORDER BY ivc_cliente_id DESC";

$DB->query($query);

$i            = 0;
$cont_envio   = 0;
$cont_reenvio = 0;
$tem_news     = false;

while ($DB->next_record())
{
       $tem_news = true;

       $cliente_id       = $DB->f(0);
       $news             = $DB->f(1);
       $status           = $DB->f(2); 
       $pesquisa_id      = $DB->f(3);

       if($status == "R") f_atualiza_status_news($news, $status, "D");
       if($status == "H") f_atualiza_status_news($news, $status, "N");

       $php              = "php ";
       $comando          = "robot_envia_news_iqdirect.php";
       $comando         .= " '$news'";
       $FLAG_PREENVIO    = "N";       

       # define qual parque de IPs a utilizar
       $cliente         = f_parque_IP($cliente_id);
       $mailer_pesquisa = f_parque_IP_pesquisa($pesquisa_id);

       switch ($status){
  
              case "S": // REENVIO
                  $slot    = ($mailer_pesquisa == '') ? $mailers[$cliente] : $mailers_pesquisa[$mailer_pesquisa];  
                  $cont_reenvio++;
              break;

              case "H": // PREENVIO CHECK QUALIDADE
                  $slot    = 1;  
	          $FLAG_PREENVIO = "S";
                  $cont_envio++;
              break;

	      default:
                  f_atualiza_status_news($news, $status, "D");
                  $slot    = ($mailer_pesquisa == '') ? $mailers[$cliente] : $mailers_pesquisa[$mailer_pesquisa];                    
		  $cont_envio++;
              break;

       }
    
	   $parametros = " '$slot' '$FLAG_PREENVIO' '$pesquisa_id' >> /dev/null &";
	   $comando_completo = $php.$path.$comando.$parametros;
	   exec($comando_completo);
	   echo "$i - $status - $cliente - ".$comando_completo."\n";

       $i++;
}

if (!$tem_news) exit("Nao existem newsletters a serem enviadas !\n");


//########################################################################################

function f_atualiza_status_news($news, $status_atual, $status){

  $DB_news = new Class_DB_iqdirect;

  switch ($status_atual){

          case "R":
                  $query = "update ivn.ivn_newsletter
                            set ivn_newsletter_status = '$status', ivn_newsletter_data_envio = now(), data_envio = now()
                            where ivn_newsletter_id = '$news' limit 1";
          break;

          default:
                  $query = "update ivn.ivn_newsletter set ivn_newsletter_status = '$status' where ivn_newsletter_id = '$news' limit 1";
          break;

  }

  $DB_news->query($query);
}

?>
