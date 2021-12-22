<?php

include_once("db_mysql.class.php");

class mailer
{
    private $url = "https://mxtoolbox.com/SuperTool.aspx?run=toolpage#&action=blacklist%3a";

	function __construct()
	{
	}

	function listaMailers()
	{
		$DB  = new Class_DB_iqdirect;
		$query = "select ip_publico from iqmail.controle_mailer order by 1 limit 1";
		$DB->query($query);

        $vet_mailers = array();		
		while ($DB->next_record()) $vet_mailers[] = $DB->f(0);

		return $vet_mailers;
	}

	function ativaMailer($mailer)
	{
		$DB    = new Class_DB_iqdirect;
		$query = "update iqmail.controle_mailer set ativo = 'S' where ip_publico = '$mailer'";
		$DB->query($query);
	}

	function desativaMailer($mailer)
	{
		$DB    = new Class_DB_iqdirect;
		$query = "update iqmail.controle_mailer set ativo = 'N' where ip_publico = '$mailer'";
		$DB->query($query);
	}

	function temBlacklist($mailer)
	{

		$resultado = file_get_contents($this->url);
		echo $resultado;
		
		//if (preg_match("/Listed 0 times with 0 timeouts/i",$resultado)) return "NAO";
		//else return "SIM";

	}

}

?>