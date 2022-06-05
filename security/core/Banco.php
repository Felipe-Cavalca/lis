<?php

class Banco extends Config
{
	public $conexao;
	public $tipo;

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Efetua a conexão com o banco de dados
	 * A conexão sera salva na variavel $conexao e o tipo em $tipo
	 * @return ["status" => boolean, "msg" => string]
	 */
	function conexao()
	{
		if (empty($this->conexao)) {
			try {
				$config = $this->getConfigBanco();
				$this->tipo = $config['tipo'];
				switch ($this->tipo) {
					case "mysql":
						$this->conexao = new PDO($config["stringConn"], $config["credenciais"]["login"], $config["credenciais"]["senha"]);
						break;
					case "sqlite":
					default:
						$this->conexao = new PDO($config["stringConn"]);
						break;
				}
			} catch (Exception $e) {
				return [
					"status" => false,
					"msg" => "Houve um erro ao se conectar com a base de dados " . ($this->debug ? $e : "")
				];
			}
		}
		return [
			"status" => true,
			"msg" => "Conectado a base de dados"
		];
	}

	/**
	 * Função para inserir dados no banco
	 * @param array $dados array associativo ['campo' => 'valor', 'campo2' => 'valor2', ...]
	 * @param string $tabela nome da tabela
	 * @param boolean $created - diz se a tabela contem os campos created e modified
	 * @return ["status" => boolean, "id" => int]
	 */
	public function insert($dados = [], $tabela = '', $created = true)
	{
		if (empty($dados)) {
			return [
				"status" => false,
				"msg" => "Não ha dados para inserir"
			];
		}
		if (empty($tabela)) {
			return [
				"status" => false,
				"msg" => "Nenhuma tabela definida para inserir os dados"
			];
		}

		if ($created) {
			//cria os campos de criado e modificado
			$dados["created"] = date("Y-m-d H:m:s");
			$dados["modified"] = date("Y-m-d H:m:s");
		}

		//transfomra o array associativo em script sql
		$campos = implode(", ", array_Keys($dados));
		$valores = "'" . implode("', '", $dados) . "'";
		$query = "INSERT INTO {$tabela} ({$campos}) VALUES ({$valores})";

		return $this->query($query, "insert");
	}

	/**
	 * Função para pesquisar no banco de dados
	 * @param array array com os dados :
	 * indices do array:
	 * @param array campos - campos que serão listados ["campo1","campo2","campo3","campo4".....]
	 * @param string tabela - Nome da tabela
	 * @param string as - alias da tabela
	 * @param array join - joins da tabela ["join1","join2","join3","join4".....]
	 * @param array igual - campos que serão pesquisados ["campo" => "valor", "campo2" => "valor", "campo3" => "valor", ...]
	 * @param string where - String que será adicionada apos o where
	 * @param boolean contar - se serão listados apenas a quantidade de registros
	 * @return array ["status" => boolean, "retorno" => array]
	 */
	public function select($arr = [])
	{
		$query = "SELECT ";

		if (isset($arr["campos"])) {
			foreach ($arr["campos"] as $campo) {
				if (isset($arr["as"]) && !isset($arr["join"])) {
					$query .= $arr["as"] . ".";
				}
				$query .= $campo;
				$query .= ", ";
			}
			$query = rtrim($query, ", ");
			$query .= " ";
		} else {
			if (isset($arr["as"])) {
				$query .= $arr["as"] . ".";
			}
			$query .= "* ";
		}

		if (isset($arr["tabela"])) {
			$query .= "FROM `" . $arr["tabela"] . "` ";
			if (isset($arr["as"])) {
				$query .= "AS " . $arr["as"] . " ";
			}
		} else {
			return [
				"status" => false,
			];
		}

		if (isset($arr["join"])) {
			foreach ($arr["join"] as $join) {
				$query .= $join . " ";
			}
		}

		if (isset($arr["igual"]) || isset($arr["where"])) {
			$query .= "WHERE ";
		}

		if (isset($arr["igual"])) {
			foreach ($arr["igual"] as $campo => $valor) {
				$query .= $campo . " = '" . $valor . "' AND ";
			}
			$query = rtrim($query, " AND");
		}

		if (isset($arr["where"])) {
			$query .= $arr["where"];
		}

		$query = rtrim($query, " ");
		$query .= ";";

		return $this->query($query);
	}

	/**
	 * executa uma query sql
	 *
	 * @param string $query
	 * @param string $tipo - tipo da query (select, update, delete, insert)
	 * @return array ["status" => boolean, "retorno" => array]
	 */
	function query($query)
	{
		$conn = $this->conexao();
		if (!$conn["status"]) {
			return [
				"status" => false
			];
		}

		if ($this->tipo == "sqlite") {
			$query = $this->sqlite($query);
		}

		switch (explode(" ", $query)[0]) {
			case "SELECT":
				$execucao = $this->conexao->query($query);
				return [
					"status" => true,
					"retorno" => $execucao->fetchAll(PDO::FETCH_ASSOC)
				];
				break;
			case "INSERT":
				$execucao = $this->conexao->query($query);
				return [
					"status" => true,
					"retorno" => $this->conexao->lastInsertId()
				];
				break;
		}
		return [
			"status" => false
		];
	}

	/**
	 * Função para transformar uma query mysql para sqlite
	 * @param string - query mysql
	 * @return string - query sqlite
	 */
	function sqlite(string $query)
	{
		$query = str_replace("AUTO_INCREMENT", "", $query);
		return $query;
	}
}
