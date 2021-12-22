<?php
// TEM QUE SALVAR COMO ISO LATIN 9 O ARQUIVO PHP

error_reporting(E_ERROR | E_PARSE);

require_once("includes/classes/db_mysql.class.php");
require_once("includes/funcoes_regras_iqmail.php");

// verificando se o robot ja esta rodando
if(acha_processo("robot_carrega_ENVIOS.php")) exit("\n\nRobot ja esta rodando.\n\n");

// CONSTANTES
$CONST_SLOT  = 1;
$path        = "/root/smtp/";

$DB = new Class_DB_iqmail;

$query = "select finalidade, sum(slot) from iqmail.controle_mailer where ativa = 'S' group by 1";
$DB->query($query);

while ($DB->next_record())
{   
       $mailers[$DB->f(0)] = $DB->f(1);
}

#exit(var_dump($mailers));

// PARAMETROS DE ENVIO

$query = "SELECT ivc_cliente_id, ivn_newsletter_id, ivn_newsletter_status, pesquisa_id
		  FROM iqmail.ivn_newsletter
		  WHERE versao in(4, -1)
		  AND   ivn_newsletter_status in ('A','S','R','D')
		  AND   now() >= ivn_newsletter_data_envio
		  AND   ivn_newsletter_ativa = 'S'
		  AND   viral = 'N'
		  ORDER BY 1 desc";

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

       $php              = "php ";
       $comando          = "robot_envia_news.php";
       $comando         .= " '$news'";

       switch ($status){
   
              case "S": // REENVIO
                  $cliente = "ENVIO";
              break;

              default:
                  f_atualiza_status_news($news,"D");
                  $cliente = "ENVIO";
              break;

       }

       switch ($cliente_id){
   
              case "2802": // admin do iqmail
                  $cliente = "IQMAILADMIN";
              break;   
   
              case "20519": // deltafiltros
                  $cliente = "DELTAFILTROS";
              break;

              default:
                  $cliente = "ENVIO";
              break;

       }
       
	   $slot    = $mailers[$cliente];

	   $parametros       = " '$slot' >> /dev/null &";
	   $comando_completo = $php.$path.$comando.$parametros;
	   exec($comando_completo);
	   echo "$i - $status - $cliente - ".$comando_completo."\n";

       $i++;
}

if (!$tem_news) exit("Nao existem newsletters a serem enviadas no login $cliente_id !\n");

//########################################################################################

function f_atualiza_status_news($news,$status){

  $DB_news = new Class_DB_iqmail;

  switch ($status){

          case "D":
                  $query = "update iqmail.ivn_newsletter
                            set ivn_newsletter_status = '$status', ivn_newsletter_data_envio = now(), data_envio = now()
                            where ivn_newsletter_id = '$news' and ivn_newsletter_status <> 'D' and viral = 'N' limit 1";
          break;

          default:
                  $query = "update iqmail.ivn_newsletter set ivn_newsletter_status = '$status' where ivn_newsletter_id = '$news' and viral = 'N' limit 1";
          break;

  }

  $DB_news->query($query);
}

?>
