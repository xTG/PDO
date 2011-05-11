<?php
/*
* Class émulant l'extension PDO - PDOStatement
* @author Baptiste ROUSSEL
* @version 1.1
* @changelog
* 		1.0 Driver MySQL et MySQLi
*		1.1 Driver PostgreSQL
*
* The PDO class is free software.
* It is released under the terms of the following BSD License.
*
* Copyright (c) 2011, Baptiste ROUSSEL
* All rights reserved.
* Redistribution and use in source and binary forms, with or without
* modification, are permitted provided that the following conditions are met:
*
*
*    * Redistributions of source code must retain the above copyright notice,
*      this list of conditions and the following disclaimer.
*
*    * Redistributions in binary form must reproduce the above copyright notice,
*      this list of conditions and the following disclaimer in the documentation
*      and/or other materials provided with the distribution.
*
*    * Neither the name of Baptiste ROUSSEL nor the names of its
*      contributors may be used to endorse or promote products derived from this
*      software without specific prior written permission.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
* ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
* WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
* DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
* ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
* (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
* LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
* ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
* (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
* SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

class PDOStatement implements Iterator {
	########################################################################################################
	# Variables
	########################################################################################################
	
	private $queryString = "";
	private $column = array();
	private $params = array();
	
	private $position = 0;
    private $result = array();
	
	private $connexion = null;
	private $driver = null;
	private $error_code = null;
	private $error_info = null;
	
	private $g_erreur = null;
	
	########################################################################################################
	# Méthodes
	########################################################################################################
	
	/*
	* Constructeur
	* @param (String) Requête
	* @param (String) Driver
	* @param (mixed) Lien de connexion
	* @param (int) Gestion des erreurs
	*/
	public function __construct($statement, $driver, $connexion, $g_erreur, $exec = false)
	{
		$this->res = array();
		if( !isSet($statement) )
			trigger_error('PDOStatement::_construct($statement, $driver, $connexion, $g_erreur) : $statement est vide.',E_USER_ERROR);
		$this->queryString = $statement;
		if( !isSet($connexion) )
			trigger_error('PDOStatement::_construct($statement, $driver, $connexion, $g_erreur) : $connexion est vide.',E_USER_ERROR);
		$this->connexion = $connexion;
		if( !isSet($g_erreur) )
			trigger_error('PDOStatement::_construct($statement, $driver, $connexion, $g_erreur) : $g_erreur est vide.',E_USER_ERROR);
		$this->g_erreur = $g_erreur;
		if( !isSet($driver) )
			trigger_error('PDOStatement::_construct($statement, $driver, $connexion, $g_erreur) : $driver est vide.',E_USER_ERROR);
		switch($driver)
		{
			case 'mysql' :
			case 'mysqli' :
			case 'postgresql' :
				$this->driver = $driver;
			break;
			default :
				if( $this->g_erreur == PDO::ERRMODE_WARNING )
					trigger_error("Erreur de driver inconnu lors de la tentative de connexion.",E_USER_WARNING);
				elseif( $this->g_erreur == PDO::ERRMODE_EXCEPTION )
					throw new PDOException("Erreur de driver inconnu lors de la tentative de connexion.");
				else
					return false;
			break;
		}
		
		// Exécution de la requête
		if( $exec === true )
			$this->execute();
	}
	
	/*
	* Lie une colonne à une variable PHP 
	* @param (int) numéro de la colonne (en commençant à 1)
	* @param (mixed) Nom de la variable PHP à laquelle la colonne doit être liée.
	* @param (int) Non implémenté
	* @param (int) Non implémenté
	* @param (mixed) Non implémenté
	*/
	public function bindColumn($column, &$param, $type = "", $maxlen = "", $driverdata = "")
	{
		if( isSet($this->column[$column]) )
			$param = $this->column[$column];
	}
	
	/*
	* Lie un paramètre à un nom de variable spécifique 
	* @param (mixed) indice de la colonne ou nom du paramètre
	* @param (mixed) variable à lier
	* @param (int) non implémenté
	* @param (int) non implémenté
	* @param (mixed) non implémenté
	*/
	public function bindParam($parameter, &$variable, $data_type = PDO::PARAM_STR, $length = 0, $driver_options = null)
	{
		if( ( is_int($parameter) || is_string($parameter) ) && strlen($parameter) > 0 )
			$this->params[$parameter] = $variable;
	}
	
	/*
	* Associe une valeur à un paramètre
	* @param (mixed) indice de la colonne ou nom du paramètre
	* @param (mixed) valeur
	* @param (int) non implémenté
	*/
	public function bindValue($parameter, $value, $data_type = PDO::PARAM_STR)
	{
		if( ( is_int($parameter) || is_string($parameter) ) && strlen($parameter) > 0 )
			$this->params[$parameter] = $value;
	}	
	
	/*
	* Ferme le curseur, permettant à la requête d'être de nouveau exécutée 
	* @return (bool)
	*/
	public function closeCursor()
	{
		// La mémoire est libérée dans PDO::execute()
		return true;
	}

	/*
	* Renvoie le n-uplet courant
	* @return (mixed)
	*/
	public function current() {
        return $this->result[$this->position];
    }
	
	/*
	* Retourne le nombre de colonnes dans le jeu de résultats
	* @return (int)
	*/
	public function columnCount()
	{
		if( isSet($this->result[0]) )
			return count($this->result[0]);
		return 0;
	}
	
	/*
	* Non implémentée
	* @TODO debugDumpParams()
	*/
	public function debugDumpParams()
	{
		return false;
	}
	
	/*
	* Récupère le SQLSTATE associé lors de la dernière opération sur la requête
	* @return (mixed)
	*/
	public function errorCode()
	{
		return $this->error_code;
	}
	
	/*
	* Récupère les informations sur l'erreur associée lors de la dernière opération sur la requête
	* @return (array)
	*/
	public function errorInfo()
	{
		return $this->error_info;
	}
	
	/*
	* Exécute une requête préparée
	* @param (mixed) (facultatif = null)
	* @return (PDOStatement)
	*/
	public function execute($input_parameters = null)
	{
		$query = $this->queryString;
		// On vide le jeu de données
		$this->result = array();
		
		// Gestion des paramètres provenant de bindParam()
		if( !empty($this->params) )
		{
			if( $input_parameters == null )
				$input_parameters = $this->params;
			else
			{
				// On écrase les valeurs par ceux de bindParam()
				foreach($this->params as $key => $val)
					$input_parameters[$key] = $val;
			}
		}
		
		// Vérification des paramètres
		if( $input_parameters != null )
		{
			switch($this->driver)
			{
				case 'mysql' : // bind manuel
					foreach($input_parameters as $param => $val)
					{
						$val = @mysql_real_escape_string($val,$this->connexion);
						if( $param[0] != ':' )
							$param = '?';
						$position = strpos($query,$param);
						if( $position != 0 )
							$query = substr_replace($query,"'$val'",$position,strlen($param));
					}
				break;
				case 'mysqli' : // bind manuel
					foreach($input_parameters as $param => $val)
					{
						$val = @$this->connexion->real_escape_string($val);
						if( $param[0] != ':' )
							$param = '?';
						$position = strpos($query,$param);
						if( $position != 0 )
							$query = substr_replace($query,"'$val'",$position,strlen($param));
					}
				break;
				case 'postgresql' :
					foreach($input_parameters as $param => $val)
					{
						$val = @pg_escape_string($this->connexion,$val);
						if( $param[0] != ':' )
							$param = '?';
						$position = strpos($query,$param);
						if( $position != 0 )
							$query = substr_replace($query,"'$val'",$position,strlen($param));
					}
				break;
				default : // Driver inconnu => comment a-t-on pu arriver jusque là ?
					return false;
				break;
			}
		}
		
		// Vérification de la connexion
		if( $this->connexion == null )
		{
			if( $this->g_erreur == PDO::ERRMODE_WARNING )
				trigger_error("Erreur de connexion.",E_USER_WARNING);
			elseif( $this->g_erreur == PDO::ERRMODE_EXCEPTION )
				throw new PDOException("Erreur de connexion.");
			else
				return false;
		}
		
		// Exécution de la requête en fonction du driver
		switch($this->driver)
		{
			case 'mysql' : // driver mysql
				$res = @mysql_query($query,$this->connexion); // Exécution de la requête
				if( $res !== false && strtolower(substr($query,0,6)) == "select" )
				{
					// Récupération des résultats
					while( $r = @mysql_fetch_array($res) )
						$this->result[] = $r;
					$this->saveError();
					@mysql_free_result($res); // Libération de la mémoire
				}
				else
					$this->saveError();
			break;
			case 'mysqli' : // driver mysqli
				$res = $this->connexion->query($query); // Exécution de la requête
				if( $res !== false && strtolower(substr($query,0,6)) == "select" )
				{
					// Récupération des résultats en cas de requête SELECT
					while( $r = @$res->fetch_array(MYSQLI_BOTH))
						$this->result[] = $r;
					$this->saveError();
					@$res->free(); // Libération de la mémoire
				}
				else
					$this->saveError();
			break;
			case 'postgresql' :
				$res = pg_query($query); // Exécution de la requête
				if( $res !== false && strtolower(substr($query,0,6)) == "select" )
				{
					// Récupération des résultats en cas de requête SELECT
					$this->result[] = pg_fetch_all($res);
					$this->saveError();
					@pg_free_result($res); // Libération de la mémoire
				}
				else
					$this->saveError();
			break;
			default : // Driver inconnu
				return false;
			break;
		}

		// Mise à jour de column pour le bindColumn
		if( isSet($this->result[0]) && count($this->result[0]) > 0 )
			foreach($this->result[0] as $cle => $valeur)
				$this->column[$cle] = "";
		
		return $this;
	}
	
	/*
	* Récupère la ligne suivante d'un jeu de résultat PDO
	* @return (array)
	*/
	public function fetch()
	{
		// Vérification de l'élément
		if( $this->valid() === false )
			return false;
		// Récupération de l'élément courant
		$el = $this->current();
		foreach($el as $cle => $val) // Stockage de la valeur des colonnes pour bindColumn
			$this->column[$cle] = $val;
		$this->next(); // On déplace le curseur
		return $el;
	}
	
	/*
	* Retourne un tableau contenant toutes les lignes du jeu d'enregistrements
	* @return (array)
	*/
	public function fetchAll()
	{
		return $this->result;
	}
	
	/*
	* Retourne une colonne depuis la ligne suivante d'un jeu de résultats 
	* @param int index colonne
	* @return (String)
	*/
	public function fetchColumn($column_number = 0)
	{
		if( $this->valid() === false )
			return false;
		$res = $this->fetch();
		if( isSet($res[$column_number]) )
			return $res[$column_number];
		else
			return null;
	}
	
	/*
	* Non implémentée
	* @TODO fetchObject()
	*/
	public function fetchObject($class_name = "stdClass", $ctor_args = null)
	{
		return false;
	}
	
	/*
	* Non implémenté
	* @TODO getAttribute
	*/
	public function getAttribute($attribute)
	{
		return false;
	}
	
	/*
	* Non implémentée
	* @TODO getColumnMeta
	*/
	public function getColumnMeta($column)
	{
		return false;
	}
	
	/*
	* Retourne la position courante
	* @return (int)
	*/
	public function key() {
        return $this->position;
    }

	/*
	* Avance le curseur
	*/
    public function next() {
        ++$this->position;
    }
	
	/*
	* Avance à la prochaine ligne de résultats d'un gestionnaire de lignes de résultats multiples 
	* @return (bool)
	*/
	public function nextRowset ()
	{
		$this->next();
		return $this->valid();
	}
	
	/*
	* Replace le curseur au début
	*/
	public function rewind() {
        $this->position = 0;
    }
	
	/*
	* Retourne le nombre de lignes affectées par le dernier appel à la fonction PDOStatement::execute()
	* @return (int) Nombre de ligne
	*/
	public function rowCount ()
	{
		return count($this->result);
	}
	
	/*
	* Non implémentée
	* @TODO setAttribute()
	*/
	public function setAttribute ($attribute, $value )
	{
		return false;
	}
	
	/*
	* Non implémentée
	* @TODO setFetchMode()
	*/
	public function setFetchMode ($mode)
	{
		return false;
	}
	
	/*
	* Méthode permettant de renseigner PDO::error_code et PDO::error_info
	* @TODO gérer convenablement les erreurs pour postgresql
	*/
	private function saveError()
	{
		switch($this->driver)
		{
			case 'mysql' :
				$this->error_code = mysql_errno($this->connexion);
				$this->error_info = array(	0 => $this->error_code,
											1 => "",
											2 => mysql_error($this->connexion));
				if( $this->error_code != 0 )
				{
					// On déclenche la signalisation de l'erreur
					if( $this->g_erreur == PDO::ERRMODE_WARNING )
						trigger_error("{$this->error_code} : {$this->error_info[2]}",E_USER_WARNING);
					elseif( $this->g_erreur == PDO::ERRMODE_EXCEPTION )
						throw new PDOException("{$this->error_code} : {$this->error_info[2]}");
					else
						return false;
				}
			break;
			case 'mysqli' : 
				$this->error_code = $this->connexion->errno;
				$this->error_info = array(	0 => $this->error_code,
											1 => "",
											2 => $this->connexion->error);
				
				if( $this->error_code != 0 )
				{
					// On déclenche la signalisation de l'erreur
					if( $this->g_erreur == PDO::ERRMODE_WARNING )
						trigger_error("{$this->error_code} : {$this->error_info[2]}",E_USER_WARNING);
					elseif( $this->g_erreur == PDO::ERRMODE_EXCEPTION )
						throw new PDOException("{$this->error_code} : {$this->error_info[2]}");
					else
						return false;
				}
			break;
			case 'postgresql' :
				$this->error_code = '?';
				$this->error_info = array(	0 => $this->error_code,
											1 => "",
											2 => pg_last_error($this->connexion));
											
			break;
			default :
				return false;
			break;
		}
	}

	/*
	* Vérifie que le curseur n'est pas arrivé à la fin
	* @return (bool)
	*/
    public function valid() {
        return isset($this->result[$this->position]);
    }
}