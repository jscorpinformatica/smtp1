<?php
$numero_maquina = 408;

$mailer_host = "mailer$numero_maquina.maildireto.com";
$mailer_sender ="mailer@mailer$numero_maquina.maildireto.com";

// DADOS config.ini.php
$config_dominio = "localhost";
$config_maquina_id = "mailer$numero_maquina";
$config_banco_news = "ivox_news";

// DADOS SISTEMA1
$sistema1_maq_id = $numero_maquina;
$sistema1_tamanho_fila_ativos = 9;
$sistema1_numero_tentativas = 3;
$sistema1_intervalo = 2;
$sistema1_time_out = 10;

//DADOS SISTEMA2

//DADOS SISTEMA3
$sistema3_mensagem_postfix = "This is the Postfix program at host mailer$numero_maquina.maildireto.com";
$sistema3_mailbox_path = "/var/spool/mail/mailer.1";
$sistema3_timeout_sistema3 = 120;


//DADOS SISTEMA4
$sistema4_timeout_ativo = 10; // minutos

// DADOS DO POPULA MAILER
$slot_limit = 2000;
?>
