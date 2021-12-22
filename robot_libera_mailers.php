<?php

require_once("includes/classes/db_mysql.class.php");

$query = "update iqmail.controle_mailer set ativa = 'S'";

$DB = new Class_DB_iqdirect;
$DB->query($query);


