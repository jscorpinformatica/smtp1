<?php
#error_reporting(E_ALL);
#ini_set('display_errors', 1);

include_once("includes/classes/db_mysql.class.php");
include_once("includes/classes/mailer.class.php");
include_once("includes/classes/PHPMailer_v5.2/PHPMailerAutoload.php");
include_once("includes/funcoes_regras_iqdirect.php");

#// verificando se o robot ja esta rodando
if(acha_processo("robot_verifica_blacklists.php")) exit("\n\nRobot ja esta rodando.\n\n");


$DB  = new Class_DB_iqdirect;

$Obj_mailer = new mailer;
$vet_mailer = $Obj_mailer->listaMailers();

foreach($vet_mailer as $mailer){

        $ret = $Obj_mailer->temBlacklist($mailer);
        //echo "$mailer - $ret \n";
        
        //if ($Obj_mailer->checaBlacklists($mailer)) $Obj_mailer->desativaMailer($mailer);
        //else $Obj_mailer->ativaMailer($mailer);

}	


?>