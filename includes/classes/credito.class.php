<?php
// include_once($_SERVER["DOCUMENT_ROOT"]."includes/classes/util.class.php");
include_once("/var/www/iqmail/cliente/includes/funcoes_regras_iqmail.php");

#CLASSE DE TESTE
class CreditoClass
{
	function __construct()
	{
	}

	function verificaCredito($cliente_id)
	{
		$DB = new Class_DB;
		$query = "SELECT SUM(credito_ativo) FROM iqmail.credito WHERE ivc_cliente_id='$cliente_id' AND ativo='S'";
		$DB->query($query);
		$DB->next_record();

		if($DB->f(0) == 0) return true;
		return false;
	}

	function removeCredito($cliente_id, $credito_remover=1)
	{
		$DB  = new Class_DB;
		$DB2 = new Class_DB;

		$query = "SELECT id, credito_ativo FROM iqmail.credito WHERE ivc_cliente_id='$cliente_id' AND ativo='S' ORDER BY prioridade_uso, data_expira";
		$DB->query($query);
		while($DB->next_record())
		{
			$credito_id    = $DB->f('id');
			$credito_ativo = $DB->f('credito_ativo');

			$creditoAcumulado=0;
			$credito_remover = $credito_ativo - $credito_remover;
			if($credito_remover < 0){ $creditoAcumulado = $credito_remover*-1; $credito_remover = 0; }

			$query = "UPDATE iqmail.credito SET credito_ativo='$credito_remover' WHERE id='$credito_id'";
			$DB2->query($query);

			$credito_remover = $creditoAcumulado;
			if($credito_remover == 0 ) RETURN FALSE;
		}
	}

	function adicionaCredito($cliente_id=NULL, $credito_qtd=0, $tipo='bonus', $descricao='Bonus', $tempo_expira=30, $plano=NULL, $prioridade_uso=0, $pagamento_id=NULL)
	{
		if(empty($cliente_id)) $cliente_id = $S_Cliente_id;
		if(empty($cliente_id)) return false;

		$DB = new Class_DB;

		######################################## NOVO INSERT
		$campos['var']['ivc_cliente_id']  = $cliente_id;
		$campos['var']['tipo']            = $tipo;
		$campos['var']['credito_total']   = $credito_qtd;
		$campos['var']['credito_ativo']   = $credito_qtd;
		$campos['mysql']['data_cadastro'] = "NOW()";
		$campos['mysql']['data_expira']   = "DATE_ADD(NOW(), INTERVAL $tempo_expira DAY)";
		$campos['var']['plano']           = $plano;
		$campos['var']['descricao']       = $descricao;
		$campos['var']['status_credito']  = "ativo";
		$campos['var']['ativo']           = "S";
		$campos['var']['prioridade']      = $prioridade_uso;
		$campos['var']['ip']              = $_SERVER['REMOTE_ADDR'];
		$campos['var']['pagamento_id']    = $pagamento_id;

		// $campos['var']['data_controle']  = $data_controle;

		$insertCampo = f_trata_campo_insert($campos);

		$query = "INSERT IGNORE INTO iqmail.credito ($insertCampo[parametro]) VALUES ($insertCampo[valor])";
		$DB->query($query);
	}

	function registraHistoricoCredito($cliente_id, $tipo, $credito_atual, $credito, $descricao, $data, $ivn_newsletter_id=null, $pagamento_id=null)
	{
		$DB = new Class_DB;

		######################################## NOVO INSERT
		$campos['var']['ivc_cliente_id']    = $cliente_id;
		$campos['var']['tipo']              = $tipo;
		$campos['var']['saldo']             = $credito_atual;
		$campos['var']['credito']           = $credito;
		$campos['var']['descricao']         = $descricao;
		$campos['var']['data']              = $data;

		$campos['var']['pagamento_id']      = $pagamento_id;
		$campos['var']['ivn_newsletter_id'] = $ivn_newsletter_id;

		$insertCampo = f_trata_campo_insert($campos);
		$parametro   = $insertCampo['parametro'];
		$valor       = $insertCampo['valor'];

		#$query = "INSERT IGNORE INTO iqmail.creditos_historico ($parametro) VALUES ($valor) ON DUPLICATE KEY UPDATE creditos_usados=$credito";
		$query = "INSERT IGNORE INTO iqmail.creditos_historico ($parametro) VALUES ($valor)";
		#echo "=>".$query;
		$DB->query($query);
	}

	function verificaCreditoCliente($cliente_id){

		// return $cliente_id;
		$DB    = new Class_DB();
		$query = "SELECT creditos_pagamento FROM clientes WHERE id = '$cliente_id'";
		$DB->query($query);
		$DB->next_record();
		$credito_atual = $DB->f('creditos_pagamento');
		return $credito_atual;
		// var_dump($_SESSION);
		// $_SESSION["cliente_total_credito"]        = $credito_atual;
		// $_SESSION["cliente_total_credito_format"] = number_format($credito_atual, 0 , '', '.');


	}


}

?>
