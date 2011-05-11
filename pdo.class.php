<?php
/*
* Class émulant l'extension PDO
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

// Inclusion des autres class
require_once("pdoexception.class.php");
require_once("pdostatement.class.php");

class PDO {
	
	########################################################################################################
	# Constantes
	########################################################################################################
	const PARAM_BOOL = 0x1;
	const PARAM_NULL = 0x2;
	const PARAM_INT = 0x3;
	const PARAM_STR = 0x4;
	const PARAM_LOB = 0x5;
	const PARAM_STMT = 0x6;
	const PARAM_INPUT_OUTPUT = 0x7;
	const FETCH_LAZY = 0x8;
	const FETCH_ASSOC = 0x9;
	const FETCH_NAMED = 0xA;
	const FETCH_NUM = 0xB;
	const FETCH_BOTH = 0xC;
	const FETCH_OBJ = 0xD;
	const FETCH_BOUND = 0xE;
	const FETCH_COLUMN = 0xF;
	const FETCH_CLASS = 0x10;
	const FETCH_INTO = 0x11;
	const FETCH_FUNC = 0x12;
	const FETCH_GROUP = 0x13;
	const FETCH_UNIQUE = 0x14;
	const FETCH_KEY_PAIR = 0x15;
	const FETCH_CLASSTYPE = 0x16;
	const FETCH_SERIALIZE = 0x17;
	const FETCH_PROPS_LATE = 0x18;
	const ATTR_AUTOCOMMIT = 0x19;
	const ATTR_PREFETCH = 0x1A;
	const ATTR_TIMEOUT = 0x1B;
	const ATTR_ERRMODE = 0x1C;
	const ATTR_SERVER_VERSION = 0x1D;
	const ATTR_CLIENT_VERSION = 0x1E;
	const ATTR_SERVER_INFO = 0x1F;
	const ATTR_CONNECTION_STATUS = 0x20;
	const ATTR_CASE = 0x21;
	const ATTR_CURSOR_NAME = 0x22;
	const ATTR_CURSOR = 0x23;
	const ATTR_DRIVER_NAME = 0x24;
	const ATTR_ORACLE_NULLS = 0x25;
	const ATTR_PERSISTENT = 0x26;
	const ATTR_STATEMENT_CLASS = 0x27;
	const ATTR_FETCH_CATALOG_NAMES = 0x28;
	const ATTR_FETCH_TABLE_NAMES = 0x29;
	const ATTR_STRINGIFY_FETCHES = 0x2A;
	const ATTR_MAX_COLUMN_LEN = 0x2B;
	const ATTR_DEFAULT_FETCH_MODE = 0x2C;
	const ATTR_EMULATE_PREPARES = 0x2D;
	const ERRMODE_SILENT = 0x2E;
	const ERRMODE_WARNING = 0x2F;
	const ERRMODE_EXCEPTION = 0x30;
	const CASE_NATURAL = 0x31;
	const CASE_LOWER = 0x32;
	const CASE_UPPER = 0x33;
	const NULL_NATURAL = 0x34;
	const NULL_EMPTY_STRING = 0x35;
	const NULL_TO_STRING = 0x36;
	const FETCH_ORI_NEXT = 0x37;
	const FETCH_ORI_PRIOR = 0x38;
	const FETCH_ORI_FIRST = 0x39;
	const FETCH_ORI_LAST = 0x3A;
	const FETCH_ORI_ABS = 0x3B;
	const FETCH_ORI_REL = 0x3C;
	const CURSOR_FWDONLY = 0x3D;
	const CURSOR_SCROLL = 0x3E;
	const ERR_NONE = 0x3F;
	const PARAM_EVT_ALLOC = 0x40;
	const PARAM_EVT_FREE = 0x41;
	const PARAM_EVT_EXEC_PRE = 0x42;
	const PARAM_EVT_EXEC_POST = 0x43;
	const PARAM_EVT_FETCH_PRE = 0x44;
	const PARAM_EVT_FETCH_POST = 0x45;
	const PARAM_EVT_NORMALIZE = 0x46;

	########################################################################################################
	# Variables
	########################################################################################################
	// Liste des drivers disponibles
	private $drivers = array('mysql','mysqli','postgresql');
	
	// Informations de connexion
	private $driver = null;
	private $serveur = null;
	private $port = null;
	private $bdd = null;
	private $user = null;
	private $password = null;
	private $init_commands = array(); // commandes lancées à la connexion
	
	// Connexion
	private $connexion = null;
	private $error_code = null;
	private $error_info = null;
	
	// Gestion des erreurs
	private $g_erreur = ERRMODE_EXCEPTION;
	
	########################################################################################################
	# Méthodes
	########################################################################################################
	
	/*
	* Constructeur
	* @param (String) DSN
	* @param (String) user
	* @param (String) password
	* @param (array) (facultatif = null) options
	* @TODO $options
	*/
	public function __construct($dsn, $user, $password, $options = null)
	{
		// Gestion DSN
		$tmp = explode(':',$dsn);
		if( count($tmp) == 2 )
		{
			// Récupération du driver
			$tmp[0] = strtolower($tmp[0]);
			switch($tmp[0])
			{
				case 'mysql' :
					if( extension_loaded("mysqli") === true ) // Priorité à mysqli si disponible
						$this->driver = "mysqli";
					elseif( extension_loaded("mysql") === true )
						$this->driver = "mysql";
					else
						throw new PDOException("Le driver mysql n'est pas chargé.");
				break;
				case 'mysqli' :
					if( extension_loaded("mysqli") === true )
						$this->driver = "mysqli";
					else
						throw new PDOException("Le driver mysqli n'est pas chargé.");
				break;
				case 'postgresql' :
					if( extension_loaded("pgsql") === true )
						$this->driver = "postgresql";
					else
						throw new PDOException("Le driver pgsql n'est pas chargé.");
				break;
				default : // Exception driver
					throw new PDOException("Le driver est inconnu.");
				break;
			}
				
			$tmp = explode(';',$tmp[1]);
			foreach($tmp as $champ)
			{
				$tmp2 = explode('=',$champ);
				if( count($tmp2) == 2 )
				switch($tmp2[0])
				{
					case 'port' : // Port
						$this->port = intval($tmp2[1]);
					break;
					case 'host' : // Host
						$this->serveur = $tmp2[1];
					break;
					case 'dbname' : // Dbname
						$this->bdd = $tmp2[1];
					break;
					default : break;
				}
			}
		}
		else
			// Exception DSN
			throw new PDOException("La chaîne DSN est incorrecte.");
		
		// Gestion USER
		if( !empty($user) )
			$this->user = $user;
		// Gestion PASSWORD
		if( !empty($password) )
			$this->password = $password;
		// Gestion des options
		if( $options != null && is_array($options) )
		foreach($options as $attribute => $value)
			$this->setAttribute($attribute, $value);
	}
	
	/*
	* Destructeur
	*/
	public function __destruct()
	{
		// Déconnexion
		switch($this->driver)
		{
			case "mysql" :
				if( $this->connexion != null )
				{
					mysql_close($this->connexion);
					$this->connexion = null;
				}
			break;
			case "mysqli" :
				if( $this->connexion != null )
				{
					$this->connexion->close();
					$this->connexion = null;
				}
			break;
			case "pgsql" :
				if( $this->connexion != null )
				{
					pg_close($this->connexion);
					$this->connexion = null;
				}
			break;
			default :
				if( $this->g_erreur == PDO::ERRMODE_WARNING )
					trigger_error("Erreur de driver inconnu lors de la tentative de déconnexion.",E_USER_WARNING);
				elseif( $this->g_erreur == PDO::ERRMODE_EXCEPTION )
					throw new PDOException("Erreur de driver inconnu lors de la tentative de déconnexion.");
				else
					return false;
			break;
		}
	}
	
	
	/*
	* Non implémentée
	* @TODO beginTransaction()
	*/
	public function beginTransaction()
	{
		return false;
	}
	
	// Connexion au serveur et sélection de la base de données
	private function connect()
	{
		if( $this->connexion != null )
			return true; // connexion déjà établie
		if( $this->port == null )
			$this->port = 3307;
		if( $this->serveur == null )
			$this->serveur = "localhost";
		if( $this->bdd == null ) // Exception bdd
		{
			if( $this->g_erreur == PDO::ERRMODE_WARNING )
				trigger_error("Aucune base de données n'a été sélectionnée.",E_USER_WARNING);
			elseif( $this->g_erreur == PDO::ERRMODE_EXCEPTION )
				throw new PDOException("Aucune base de données n'a été sélectionnée.");
			else
				return false;
		}

		// Connexion
		switch($this->driver)
		{
			case "mysql" :
				$this->connexion = mysql_connect($this->host,$this->user,$this->password);
				$this->saveError(); 
				mysql_select_db($this->bdd,$this->connexion);
				$this->saveError(); 
				foreach($this->init_commands as $commande) // Exécution des commandes post-connexion
					mysql_query($commande,$this->connexion);
			break;
			case "mysqli" :
				$this->connexion = @new mysqli($this->host,$this->user,$this->password);
				$this->saveError(); 
				@$this->connexion->select_db($this->bdd);
				$this->saveError();
				foreach($this->init_commands as $commande) // Exécution des commandes post-connexion
					$this->connexion->query($commande);
			break;
			case "postgresql" :
				$this->connexion = pg_connect("host={$this->host} port={$this->port} dbname={$this->bdd} user={$this->user} password={$this->password}");
				$this->saveError();
				foreach($this->init_commands as $commande) // Exécution des commandes post-connexion
					pg_query($this->connexion,$commande);
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
		return true;
	}
	
	/*
	* Retourne le SQLSTATE associé avec la dernière opération sur la base de données 
	* Ne renvoie rien avec le driver postgresql
	* @return (mixed)
	*/
	public function errorCode()
	{
		return $this->error_code;
	}
	
	/*
	* Retourne les informations associées à l'erreur lors de la dernière opération sur la base de données
	* @return (array)
	*/
	public function errorInfo()
	{
		return $this->error_info;
	}
	
	/*
	* Exécute une requête SQL et retourne le nombre de lignes affectées 
	* @param (String) Requête
	* @return (int) nombre de lignes affectées
	*/
	public function exec($statement)
	{
		if( $this->connexion == null )
		{
			$r = $this->connect();
			if( $r === false )
				return false;
		}
		$res = new PDOStatement($statement, $this->driver, $this->connexion, $this->g_erreur);
		$res->execute();
		return $res->rowCount();
	}
	
	/*
	* non implémentée
	* @TODO getAttribute()
	*/
	public function getAttribute($attribute)
	{
		return false;
	}
	
	/*
	* Retourne la liste des pilotes PDO disponibles 
	* @return (array)
	*/
	public function getAvailableDrivers()
	{
		return $this->drivers;
	}
	
	/*
	* Non implémentée
	* @TODO inTransaction()
	*/
	public function inTransaction()
	{
		return false;
	}
	
	/*
	* Retourne l'identifiant de la dernière ligne insérée ou la valeur d'une séquence 
	* Ne fonctionne pas avec le driver postgresql
	* @param (String) (facultatif = null) non implémenté
	* @return (String) dernier identifiant
	*/
	public function lastInsertId($name = null)
	{
		if( $this->connexion == null )
		{
			$r = $this->connect();
			if( $r === false )
				return false;
		}
		switch($this->driver)
		{
			case 'mysql' :
				$id = @mysql_insert_id();
				$this->saveError();
				return $id;
			break;
			case 'mysqli' :
				$id = $this->connexion->insert_id;
				return $id;
			break;
			case 'postgresql' :
				return -1;
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
	}
	
	/*
	* Prépare une requête à l'exécution et retourne un objet
	* @param (String) Requête
	* @param (array) (facultatif = null) Driver options (non implémenté)
	* @return PDOStatement
	*/
	public function prepare($statement, $driver_options = null)
	{
		if( $this->connexion == null )
		{
			$r = $this->connect();
			if( $r === false )
				return false;
		}
		$res = new PDOStatement($statement, $this->driver, $this->connexion, $this->g_erreur);
		return $res;
	}
	
	/*
	* Exécute une requête SQL, retourne un jeu de résultats en tant qu'objet PDOStatement
	* @param (String) requête
	* @return (PDOStatement)
	*/
	public function query($statement)
	{
		if( $this->connexion == null )
		{
			$r = $this->connect();
			if( $r === false )
				return false;
		}
		$res = new PDOStatement($statement, $this->driver, $this->connexion, $this->g_erreur, true);
		return $res;
	}
	
	/*
	* Protège une chaîne pour l'utiliser dans une requête SQL PDO 
	* @param (String) La requête
	* @param (int) Le type de données pour les drivers qui ont des styles particuliers de protection. (non implémenté)
	* @return   (bool) false si le driver ne supporte pas cette protection
	*			(String) La requête échappée en cas de réussite
	*
	* @TODO gérer le second paramètre
	*/
	public function quote($string, $parameter_type = PDO::PARAM_STR)
	{
		if( $this->connexion == null )
		{
			$r = $this->connect();
			if( $r === false )
				return false;
		}
		switch($this->driver)
		{
			case 'mysql' :
				$string = mysql_real_escape_string($string, $this->connexion);
				// Echappement des caractères non pris en compte par la fonction de mysql
				$string = str_replace('_','\_',$string);
				$string = str_replace('%','\%',$string);
				return $string;
			break;
			case 'mysqli' :
				$string = $this->connexion->real_escape_string($string);
				// Echappement des caractères non pris en compte par la fonction de mysqli
				$string = str_replace('_','\_',$string);
				$string = str_replace('%','\%',$string);
				return $string;
			break;
			default :
				return false;
			break;
		}
	}
	
	/*
	* Non implémentée
	* @TODO rollback()
	*/
	public function rollBack()
	{
		return false;
	}
	
	/*
	* Méthode permettant de renseigner PDO::error_code et PDO::error_info
	*/
	private function saveError()
	{
		switch($this->driver)
		{
			case 'mysql' :
				$this->error_code = mysql_errno();
				$this->error_info = array(	0 => $this->error_code,
											1 => "",
											2 => mysql_error());
				if( $this->error_code != 0 )
				// On déclenche la signalisation de l'erreur
				if( $this->g_erreur == PDO::ERRMODE_WARNING )
					trigger_error("{$this->error_code} : {$this->error_info[2]}",E_USER_WARNING);
				elseif( $this->g_erreur == PDO::ERRMODE_EXCEPTION )
					throw new PDOException("{$this->error_code} : {$this->error_info[2]}");
				else
					return false;
			break;
			case 'mysqli' :
				$this->error_code = mysqli_connect_errno();
				$this->error_info = array(	0 => $this->error_code,
											1 => "",
											2 => mysqli_connect_error());
				if( $this->error_code != 0 )
				// On déclenche la signalisation de l'erreur
				if( $this->g_erreur == PDO::ERRMODE_WARNING )
					trigger_error("{$this->error_code} : {$this->error_info[2]}",E_USER_WARNING);
				elseif( $this->g_erreur == PDO::ERRMODE_EXCEPTION )
					throw new PDOException("{$this->error_code} : {$this->error_info[2]}");
				else
					return false;
			break;
			default :
			break;
		}
	}
	
	/*
	* Configure un attribut PDO 
	* @param (int) attribut
	* @param (mixed) valeur
	* @return (bool)
	*
	* @TODO gérer les autres attributs
	*/
	public function setAttribute($attribute, $value)
	{
		switch($attribute)
		{
			case PDO::ATTR_ERRMODE :
				if( in_array($value, array(PDO::ERRMODE_SILENT, PDO::ERRMODE_WARNING, PDO::ERRMODE_EXCEPTION)) === true )
				{
					$this->g_erreur = $value;
					return true;
				}
				else
					return false;
			break;
			case PDO::MYSQL_ATTR_INIT_COMMAND :
				if( $this->connexion == null && ($this->driver == "mysql" || $this->driver == "mysqli" ) )
				{
					$this->init_commands[] = $value;
					return true;
				}
				else
					return false;
			break;
			case PDO::ATTR_STRINGIFY_FETCHES :
				return true; // Dans notre cas tout est String
			break;
			case PDO::MYSQL_ATTR_USE_BUFFERED_QUERY :
				return false; // D'après la doc cela ne fonctionne pas avec PDO via cette fonction
			break;
			default :
				return false;
			break;
		}
	}
}

