<?php
// error_reporting(E_ALL);
// ini_set('display_errors', TRUE);
// ini_set('display_startup_errors', TRUE);

/*
 * Génération d'un CSV pour la récupération à posteriori sur Infoclimat
 * des données issues d'une base de données locales WeeWX sous SQLite ou
 * MySQL, avec détection auto des unités et conversion
*/

// GO -- NE PLUS TOUCHER

// VERSION : V1.99

// Timestamp debut du script
	$timeStartScript = microtime(true);

// Date UTC
	date_default_timezone_set('UTC');

// CONF STATION CLI
	$paramsCli = getopt(null, array(
		'debug',           // SQLite ET MySQL - PAS DE VALEUR ATTENDUE
		'db-type:',        // SQLite ET MySQL
		'db-file:',        // SQLite
		'db-table:',       // SQLite ET MySQL
		'db-host:',        // MySQL
		'db-user:',        // MySQL
		'db-pass:',        // MySQL
		'db-name:',        // MySQL
		'id-station:',     // SQLite ET MySQL
		'repo-csv:',       // SQLite ET MySQL
		'periode-recup:',  // SQLite ET MySQL
		'ftp-enable',      // SQLite ET MySQL - PAS DE VALEUR ATTENDUE 
		'ftp-server:',     // SQLite ET MySQL
		'ftp-user:',       // SQLite ET MySQL
		'ftp-pass:',       // SQLite ET MySQL
	));

	// Récup des params en CLI ou dans le fichier de conf ?

// Debug CLI
	if (isset($paramsCli['debug'])) {
		$debug = True;
	} else {
		$debug = False;
	}

	if ($debug) {
		var_dump($paramsCli);
	}

// Type BDD CLI
	if (!isset($paramsCli['db-type'])) {
		if ($debug) {
			echo "Info : Type de BDD non précisé, les autres paramètres de la ligne de commande sont ignorés : utilisation du fichier de conf".PHP_EOL.PHP_EOL;
		}
		require_once "config.php";
	} else {
		if ($paramsCli['db-type'] === "sqlite") {
			if ($debug) {
				echo "Info : Params spécifié en CLI, pas de fichier de conf".PHP_EOL;
				echo "Info : BDD SQLite".PHP_EOL.PHP_EOL;
			}
			$db_type = "sqlite";
			if (isset($paramsCli['db-file'])) {
				$db_file = $paramsCli['db-file'];
			} else {
				exit("ERRO : params db-file non défini ou invalide\n");
			}
			if (isset($paramsCli['db-table'])) {
				$db_table_sqlite = $paramsCli['db-table'];
			} else {
				exit("ERRO : params db-table non défini ou invalide\n");
			}
		} elseif ($paramsCli['db-type'] === "mysql") {
			if ($debug) {
				echo "Info : Params spécifié en CLI, pas de fichier de conf".PHP_EOL;
				echo "Info : BDD MySQL".PHP_EOL.PHP_EOL;
			}
			$db_type = "mysql";
			if (isset($paramsCli['db-host'])) {
				$db_host = $paramsCli['db-host'];
			} else {
				exit("ERRO : params db-host non défini ou invalide\n");
			}
			if (isset($paramsCli['db-user'])) {
				$db_user = $paramsCli['db-user'];
			} else {
				exit("ERRO : params db-user non défini ou invalide\n");
			}
			if (isset($paramsCli['db-pass'])) {
				$db_pass = $paramsCli['db-pass'];
			} else {
				exit("ERRO : params db-pass non défini ou invalide\n");
			}
			if (isset($paramsCli['db-name'])) {
				$db_name_mysql = $paramsCli['db-name'];
			} else {
				exit("ERRO : params db-name non défini ou invalide\n");
			}
			if (isset($paramsCli['db-table'])) {
				$db_table_mysql = $paramsCli['db-table'];
			}
			else {
				exit("ERRO : params db-table non défini ou invalide\n");
			}
		} else {
			exit("ERRO : params db-type invalide\n");
		}

		// Params RECUP CLI
		if (isset($paramsCli['periode-recup'])) {
			$periodeRecup = $paramsCli['periode-recup'];
		} else {
			exit("ERRO : params periode-recup non défini ou invalide\n");
		}

		// Params communs CLI
		if (isset($paramsCli['id-station'])) {
			$id_station = $paramsCli['id-station'];
		} else {
			exit("ERRO : params id-station non défini ou invalide\n");
		}
		if (isset($paramsCli['repo-csv'])) {
			$repository = $paramsCli['repo-csv'];
		} else {
			exit("ERRO : params repo-csv non défini ou invalide\n");
		}

		// Params FTP CLI
		if (isset($paramsCli['ftp-enable']) ) {
			$ftp_enable = True;
			if (isset($paramsCli['ftp-server'])) {
				$ftp_server = $paramsCli['ftp-server'];
			} else {
				exit("ERRO : params ftp-server non défini ou invalide\n");
			}
			if (isset($paramsCli['ftp-user'])) {
				$ftp_username = $paramsCli['ftp-user'];
			} else {
				exit("ERRO : params ftp-user non défini ou invalide\n");
			}
			if (isset($paramsCli['ftp-pass'])) {
				$ftp_password = $paramsCli['ftp-pass'];
			} else {
				exit("ERRO : params ftp-pass non défini ou invalide\n");
			}
		} else {
			$ftp_enable = False;
		}
	}


// Définition du nom de la table en fonction du type de BDD utilisé
	if ($db_type === "sqlite") {
		$db_table = $db_table_sqlite;
	} elseif ($db_type === "mysql") {
		$db_name  = $db_name_mysql;
		$db_table = $db_table_mysql;
	}

// Connection à la BDD
	if ($db_type === "sqlite") {
		try {
			$db_handle_pdo = new PDO("sqlite:$db_file");
			// Activation des erreurs PDO
			$db_handle_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			// mode de fetch par défaut : FETCH_ASSOC / FETCH_OBJ / FETCH_BOTH
			$db_handle_pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			// Ajout de la fonction ceil pour SQLite
			$db_handle_pdo->sqliteCreateFunction('ceil', 'ceil', 1);
		} catch (PDOException $exception) {
			echo 'Échec lors de la connexion : ' . $exception->getMessage();
		}
	} elseif ($db_type === "mysql") {
		try {
			$db_handle_pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
			// Activation des erreurs PDO
			$db_handle_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			// mode de fetch par défaut : FETCH_ASSOC / FETCH_OBJ / FETCH_BOTH
			$db_handle_pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		} catch (PDOException $exception) {
			echo 'Échec lors de la connexion : ' . $exception->getMessage();
		}
	}


// FONCTION arondi des minutes
	/**
	 * Round down minutes to the nearest lower interval of a DateTime object.
	 * 
	 * @param \DateTime $dateTime
	 * @param int $minuteInterval
	 * @return \DateTime
	 */
	function roundDownToMinuteInterval(\DateTime $dateTime) {
		$minuteInterval = 10;
		return $dateTime->setTime(
			$dateTime->format('H'),
			floor($dateTime->format('i') / $minuteInterval) * $minuteInterval,
			0
		);
	}



// FONCTION moyenne d'angles angulaires
	function mean_of_angles( $angles, $degrees = true ) {
		if ( $degrees ) {
			$angles = array_map("deg2rad", $angles);  // Convert to radians
		}
		$s_  = 0;
		$c_  = 0;
		$len = count( $angles );
		for ($i = 0; $i < $len; $i++) {
			$s_ += sin( $angles[$i] );
			$c_ += cos( $angles[$i] );
		}
		// $s_ /= $len;
		// $c_ /= $len;
		$mean = atan2( $s_, $c_ );
		if ( $degrees ) {
			$mean = rad2deg( $mean );  // Convert to degrees
		}
		if ($mean < 0) {
			$mean_ok = $mean + 360;
		} else {
			$mean_ok = $mean;
		}
		return $mean_ok;
	}

###############################################################
##### MAIN
###############################################################

// Établissement des timestamp stop et start et de l'unité
	$query_string = "SELECT `dateTime`, `usUnits` AS `unit` FROM $db_table ORDER BY `dateTime` DESC LIMIT 1;";
	$result       = $db_handle_pdo->query($query_string);
	
	if (!$result and $debug) {
		// Erreur et debug activé
		echo "Erreur dans la requete ".$query_string."\n";
		echo "\nPDO::errorInfo():\n";
		print_r($db_handle_pdo->errorInfo());
		exit("Impossible de déterminer la date du dernier relevé dans la BDD WeeWX de votre station.\n");
	}
	if ($result) {
		$row = $result->fetch(PDO::FETCH_ASSOC);

		// stop = dernier relevé dispo en BDD en timestamp Unix
		$tsStop = $row['dateTime'];
		
		// Arrondi du datetime Stop
		$datetimeStop = new DateTime();
		$datetimeStop->setTimestamp($tsStop);
		$dtStop = roundDownToMinuteInterval($datetimeStop);

		$dtStop = $dtStop->format("d-m-Y H:i:s");
		$tsStop = strtotime($dtStop);

		$tsStart = $tsStop-($periodeRecup);       // start = dernier relevé - le temps demandé dans le fichier de config (en secondes)
		$dtStart = date('d-m-Y H:i:s',$tsStart);

		// UNITS
		$unit = $row['unit'];

		if ($debug) {
			echo "dtStart : ".date('Y-m-d H:i:s',$tsStart)."\n";
			echo "dtStop : ".date('Y-m-d H:i:s',$tsStop)."\n";
		}
	}

// csvTab insertion du header
	$csvTab = [];
	$csvTab ['header'] ['dtUTC'] = null;
	$csvTab ['header'] ['TempNow'] = null;
	$csvTab ['header'] ['HrNow'] = null;
	$csvTab ['header'] ['TdNow'] = null;
	$csvTab ['header'] ['barometerNow'] = null;
	$csvTab ['header'] ['rainRateNow'] = null;
	$csvTab ['header'] ['radiationNow'] = null;
	$csvTab ['header'] ['UvNow'] = null;
	$csvTab ['header'] ['Tn10m'] = null;
	$csvTab ['header'] ['Tx10m'] = null;
	$csvTab ['header'] ['rainCumul10m'] = null;
	$csvTab ['header'] ['rainCumulYear'] = null;
	$csvTab ['header'] ['rainRateMax10m'] = null;
	$csvTab ['header'] ['radiationMax10m'] = null;
	$csvTab ['header'] ['UvMax10m'] = null;
	$csvTab ['header'] ['Tn1h'] = null;
	$csvTab ['header'] ['Tx1h'] = null;
	$csvTab ['header'] ['rainCumul1h'] = null;
	$csvTab ['header'] ['rainRateMax1h'] = null;
	$csvTab ['header'] ['radiationMax1h'] = null;
	$csvTab ['header'] ['UvMax1h'] = null;
	$csvTab ['header'] ['windGustMax1h'] = null;
	$csvTab ['header'] ['windGustMaxDir1h'] = null;
	$csvTab ['header'] ['windGustMaxdt1h'] = null;
	$csvTab ['header'] ['windGustMax10m'] = null;
	$csvTab ['header'] ['windGustMaxDir10m'] = null;
	$csvTab ['header'] ['windGustMaxdt10m'] = null;
	$csvTab ['header'] ['windSpeedAvg10m'] = null;
	$csvTab ['header'] ['windDirAvg10m'] = null;
	$csvTab ['header'] ['Tn12h'] = null;
	$csvTab ['header'] ['TnDt12h'] = null;
	$csvTab ['header'] ['Tx12h'] = null;
	$csvTab ['header'] ['TxDt12h'] = null;
	$csvTab ['header'] ['rainCumul12h'] = null;

// Génération de la liste des DATES à générer
	$query_string = "SELECT CEIL(`dateTime`/600.0)*600 AS `ts` FROM $db_table WHERE `dateTime` >= $tsStart AND `dateTime` <= $tsStop GROUP BY `ts` ORDER BY `ts` ASC;";
	$result       = $db_handle_pdo->query($query_string);

	if (!$result and $debug) {
		// Erreur et debug activé
		echo "Erreur dans la requete ".$query_string."\n";
		echo "\nPDO::errorInfo():\n";
		print_r($db_handle_pdo->errorInfo());
		exit("Impossible de déterminer la date du dernier relevé dans la BDD WeeWX de votre station.\n");
	}
	if ($result) {
		while($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$row['ts'] = (string)round($row['ts']);
			// Insertion dans le tableau des date time UTC en tant que key
			$csvTab [$row['ts']] = array();
			$csvTab [$row['ts']] ['dtUTC'] = date('Y-m-d H:i:s',$row['ts']);
		}
	}

// CALCUL SUM RAIN YEAR
	// On calcul dés le départ le cumul annuel AVANT le tsStart
	// On l'incrémentera ensuite toutes les 10 minutes du cumul sur 10 minutes
	// Mais il faut vérifier que le tsStart et le tsStop ne chevauche pas plusieurs années
	$firstYear = null;
	$lastYear = date('Y',$tsStart);
	if (date('Y',$tsStart) != date('Y',$tsStop)) {
		$firstYear = date('Y',$tsStart); // Variable texte de l'année
		if ($debug) {
			echo "Chevauchement d'année, méthode du cumul annuel multiple\n";
			echo "firstYear : ".$firstYear."\n";
		}
		$dtFirstYear = DateTime::createFromFormat('Y', $firstYear); // Objet dt de l'année
		${"tsStartYear".$firstYear} = strtotime(date('Y-01-01 00:00:00', $dtFirstYear->format('U')));
		// Requete
		$query_string = "SELECT SUM(`rain`) AS `rainCumulYear`
							FROM $db_table
							WHERE `dateTime` > ${'tsStartYear'.$firstYear}
							AND `dateTime`< $tsStart;";
		$result       = $db_handle_pdo->query($query_string);

		if (!$result and $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string."\n";
			echo "\nPDO::errorInfo():\n";
			print_r($db_handle_pdo->errorInfo());
			exit("Erreur RR YEAR SIMPLE.\n");
		}
		if ($result) {
			$rainCumulYear = null;
			${"rainCumulYear".$firstYear} = null;
			while($row = $result->fetch(PDO::FETCH_ASSOC)) {
				if (!is_null ($row['rainCumulYear'])) {
					if ($unit == '1') {
						${"rainCumulYear".$firstYear} = round($row['rainCumulYear']*25.4,1);
					}elseif ($unit == '16') {
						${"rainCumulYear".$firstYear} = round($row['rainCumulYear']*10,1);
					}elseif ($unit == '17') {
						${"rainCumulYear".$firstYear} = round($row['rainCumulYear'],1);
					}
				}
			}
			if ($debug) {
				echo "Cumul $firstYear avant tsStart : ".${"rainCumulYear".$firstYear}." mm\n";
			}
		}
	} else {
		if ($debug) {
			echo "Pas de chevauchement d'année, méthode du cumul annuel simple\n";
		}
		$tsStartYear = strtotime(date('Y-01-01 00:00:00', $tsStart));
		// Requete
		$query_string = "SELECT SUM(`rain`) AS `rainCumulYear`
							FROM $db_table
							WHERE `dateTime` > $tsStartYear
							AND `dateTime`< $tsStart;";
		$result       = $db_handle_pdo->query($query_string);

		if (!$result and $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string."\n";
			echo "\nPDO::errorInfo():\n";
			print_r($db_handle_pdo->errorInfo());
			exit("Erreur RR YEAR SIMPLE.\n");
		}
		if ($result) {
			$rainCumulYear = null;
			while($row = $result->fetch(PDO::FETCH_ASSOC)) {
				if (!is_null ($row['rainCumulYear'])) {
					if ($unit == '1') {
						$rainCumulYear = round($row['rainCumulYear']*25.4,1);
					}elseif ($unit == '16') {
						$rainCumulYear = round($row['rainCumulYear']*10,1);
					}elseif ($unit == '17') {
						$rainCumulYear = round($row['rainCumulYear'],1);
					}
				}
			}
			if ($debug) {
				echo "Cumul annuel avant tsStart : ".$rainCumulYear." mm\n";
			}
		}
	}

// PARAMS TEMPS REEL
	$query_string = "SELECT `dateTime` AS `ts`,
					`outTemp` AS `TempNow`,
					`outHumidity` AS `HrNow`,
					`dewpoint` AS `TdNow`,
					`barometer` AS `barometerNow`,
					`rainRate` AS `rainRateNow`,
					`radiation` AS `radiationNow`,
					`UV` AS `UvNow`
				FROM $db_table
				WHERE `dateTime` % 600 = 0
				AND `dateTime` >= $tsStart
				AND `dateTime` <= $tsStop;";
	$result       = $db_handle_pdo->query($query_string);

	if (!$result and $debug) {
		// Erreur et debug activé
		echo "Erreur dans la requete ".$query_string."\n";
		echo "\nPDO::errorInfo():\n";
		print_r($db_handle_pdo->errorInfo());
		exit("Erreur\n");
	}
	if ($result) {
		while($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$TempNow      = null;
			$HrNow        = null;
			$TdNow        = null;
			$barometerNow = null;
			$rainRateNow  = null;
			$radiationNow = null;
			$UvNow        = null;
			$row['ts'] = (string)round($row['ts']);

			// Traitement des données
			if (!is_null ($row['TempNow'])) {
				if ($unit == '1') {
					$TempNow = round(($row['TempNow']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$TempNow = round($row['TempNow'],1);
				}
			}
			if (!is_null ($row['HrNow'])) {
				$HrNow  = round($row['HrNow'],1);
			}
			if (!is_null ($row['TdNow'])) {
				if ($unit == '1') {
					$TdNow = round(($row['TdNow']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$TdNow = round($row['TdNow'],1);
				}
			}
			if (!is_null ($row['barometerNow'])) {
				if ($unit == '1') {
					$barometerNow = round($row['barometerNow']*33.8639,1);
				}elseif ($unit == '16' || $unit == '17') {
					$barometerNow = round($row['barometerNow'],1);
				}
			}
			if (!is_null ($row['rainRateNow'])) {
				if ($unit == '1') {
					$rainRateNow = round($row['rainRateNow']*25.4,1);
				}elseif ($unit=='16') {
					$rainRateNow = round($row['rainRateNow']*10,1);
				}elseif ($unit=='17') {
					$rainRateNow = round($row['rainRateNow'],1);
				}
			}
			if (!is_null ($row['radiationNow'])) {
				$radiationNow  = round($row['radiationNow'],0);
			}
			if (!is_null ($row['UvNow'])) {
				$UvNow  = round($row['UvNow'],1);
			}

			// Insertion dans le tableau des params temps réel
			$csvTab [$row['ts']] ['TempNow'] = $TempNow;
			$csvTab [$row['ts']] ['HrNow'] = $HrNow;
			$csvTab [$row['ts']] ['TdNow'] = $TdNow;
			$csvTab [$row['ts']] ['barometerNow'] = $barometerNow;
			$csvTab [$row['ts']] ['rainRateNow'] = $rainRateNow;
			$csvTab [$row['ts']] ['radiationNow'] = $radiationNow;
			$csvTab [$row['ts']] ['UvNow'] = $UvNow;
		}
	}

// PARAMS SUR 10 minutes
	$query_string = "SELECT CEIL(`dateTime`/600.0)*600 AS `ts`,
									MIN(`outTemp`) AS `Tn10m`,
									MAX(`outTemp`) AS `Tx10m`,
									SUM(`rain`) AS `rainCumul10m`,
									MAX(`rainRate`) AS `rainRateMax10m`,
									MAX(`radiation`) AS `radiationMax10m`,
									MAX(`UV`) AS `UvMax10m`
					FROM $db_table WHERE `dateTime` >= $tsStart AND `dateTime` <= $tsStop GROUP BY `ts` ORDER BY `ts` ASC;";
	$result       = $db_handle_pdo->query($query_string);

	if (!$result and $debug) {
		// Erreur et debug activé
		echo "Erreur dans la requete ".$query_string."\n";
		echo "\nPDO::errorInfo():\n";
		print_r($db_handle_pdo->errorInfo());
		exit("Erreur.\n");
	}
	if ($result) {
		while($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$Tn10m  = null;
			$Tx10m  = null;
			$rainCumul10m = null;
			$rainRateMax10m = null;
			$radiationMax10m = null;
			$UvMax10m = null;
			$row['ts'] = (string)round($row['ts']);
			if ($lastYear != date('Y',$row['ts'])) {
				// On reinit le cumul year si on vient de changer d'année
				$rainCumulYear = 0;
				$lastYear = date('Y',$row['ts']);
				if ($debug) {
					echo "Reinit du rainCumulYear car changement d'année | ".date('Y-m-d H:i:s', $row['ts'])."\n";
				}
			}
			if (!is_null ($row['Tn10m'])) {
				if ($unit == '1') {
					$Tn10m = round(($row['Tn10m']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$Tn10m = round($row['Tn10m'],1);
				}
			}
			if (!is_null ($row['Tx10m'])) {
				if ($unit == '1') {
					$Tx10m = round(($row['Tx10m']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$Tx10m = round($row['Tx10m'],1);
				}
			}
			if (!is_null ($row['rainCumul10m'])) {
				if ($unit == '1') {
					$rainCumul10m = round($row['rainCumul10m']*25.4,1);
					if (date('Y',$row['ts']) == $firstYear) {
						${"rainCumulYear".$firstYear} = ${"rainCumulYear".$firstYear} + $rainCumul10m;
						$rainCumulYear = ${"rainCumulYear".$firstYear};
					} else {
						$rainCumulYear = $rainCumulYear + $rainCumul10m;
					}
				}elseif ($unit == '16') {
					$rainCumul10m = round($row['rainCumul10m']*10,1);
					if (date('Y',$row['ts']) == $firstYear) {
						${"rainCumulYear".$firstYear} = ${"rainCumulYear".$firstYear} + $rainCumul10m;
						$rainCumulYear = ${"rainCumulYear".$firstYear};
					} else {
						$rainCumulYear = $rainCumulYear + $rainCumul10m;
					}
				}elseif ($unit == '17') {
					$rainCumul10m = round($row['rainCumul10m'],1);
					if (date('Y',$row['ts']) == $firstYear) {
						${"rainCumulYear".$firstYear} = ${"rainCumulYear".$firstYear} + $rainCumul10m;
						$rainCumulYear = ${"rainCumulYear".$firstYear};
					} else {
						$rainCumulYear = $rainCumulYear + $rainCumul10m;
					}
				}
			}
			if (!is_null ($row['rainRateMax10m'])) {
				if ($unit == '1') {
					$rainRateMax10m = round($row['rainRateMax10m']*25.4,1);
				}elseif ($unit == '16') {
					$rainRateMax10m = round($row['rainRateMax10m']*10,1);
				}elseif ($unit == '17') {
					$rainRateMax10m = round($row['rainRateMax10m'],1);
				}
			}
			if (!is_null ($row['radiationMax10m'])) {
				$radiationMax10m = round($row['radiationMax10m'],0);
			}
			if (!is_null ($row['UvMax10m'])) {
				$UvMax10m = round($row['UvMax10m'],1);
			}

			// Insertion dans le tableau des données
			$csvTab [$row['ts']] ['Tn10m'] = $Tn10m;
			$csvTab [$row['ts']] ['Tx10m'] = $Tx10m;
			$csvTab [$row['ts']] ['rainCumul10m'] = $rainCumul10m;
			$csvTab [$row['ts']] ['rainCumulYear'] = $rainCumulYear;
			$csvTab [$row['ts']] ['rainRateMax10m'] = $rainRateMax10m;
			$csvTab [$row['ts']] ['radiationMax10m'] = $radiationMax10m;
			$csvTab [$row['ts']] ['UvMax10m'] = $UvMax10m;
		}
	}


// PARAMS SUR 1 heure
	$query_string = "SELECT CEIL(`dateTime`/3600.0)*3600 AS `ts`,
									MIN(`outTemp`) AS `Tn1h`,
									MAX(`outTemp`) AS `Tx1h`,
									SUM(`rain`) AS `rainCumul1h`,
									MAX(`rainRate`) AS `rainRateMax1h`,
									MAX(`radiation`) AS `radiationMax1h`,
									MAX(`UV`) AS `UvMax1h`-- ,
									-- MAX(`windGust`) AS `windGustMax1h`
					FROM $db_table WHERE `dateTime` >= $tsStart AND `dateTime` <= $tsStop GROUP BY `ts` ORDER BY `ts` ASC;";
	$result       = $db_handle_pdo->query($query_string);

	if (!$result and $debug) {
		// Erreur et debug activé
		echo "Erreur dans la requete ".$query_string."\n";
		echo "\nPDO::errorInfo():\n";
		print_r($db_handle_pdo->errorInfo());
		exit("Erreur.\n");
	}
	if ($result) {
		while($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$Tn1h  = null;
			$Tx1h  = null;
			$rainCumul1h = null;
			$rainRateMax1h = null;
			$radiationMax1h = null;
			$UvMax1h = null;
			$row['ts'] = (string)round($row['ts']);
			if (!is_null ($row['Tn1h'])) {
				if ($unit == '1') {
					$Tn1h = round(($row['Tn1h']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$Tn1h = round($row['Tn1h'],1);
				}
			}
			if (!is_null ($row['Tx1h'])) {
				if ($unit == '1') {
					$Tx1h = round(($row['Tx1h']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$Tx1h = round($row['Tx1h'],1);
				}
			}
			if (!is_null ($row['rainCumul1h'])) {
				if ($unit == '1') {
					$rainCumul1h = round($row['rainCumul1h']*25.4,1);
				}elseif ($unit == '16') {
					$rainCumul1h = round($row['rainCumul1h']*10,1);
				}elseif ($unit == '17') {
					$rainCumul1h = round($row['rainCumul1h'],1);
				}
			}
			if (!is_null ($row['rainRateMax1h'])) {
				if ($unit == '1') {
					$rainRateMax1h = round($row['rainRateMax1h']*25.4,1);
				}elseif ($unit == '16') {
					$rainRateMax1h = round($row['rainRateMax1h']*10,1);
				}elseif ($unit == '17') {
					$rainRateMax1h = round($row['rainRateMax1h'],1);
				}
			}
			if (!is_null ($row['radiationMax1h'])) {
				$radiationMax1h = round($row['radiationMax1h'],0);
			}
			if (!is_null ($row['UvMax1h'])) {
				$UvMax1h = round($row['UvMax1h'],1);
			}

			// Insertion dans le tableau des données
			$csvTab [$row['ts']] ['Tn1h'] = $Tn1h;
			$csvTab [$row['ts']] ['Tx1h'] = $Tx1h;
			$csvTab [$row['ts']] ['rainCumul1h'] = $rainCumul1h;
			$csvTab [$row['ts']] ['rainRateMax1h'] = $rainRateMax1h;
			$csvTab [$row['ts']] ['radiationMax1h'] = $radiationMax1h;
			$csvTab [$row['ts']] ['UvMax1h'] = $UvMax1h;
		}
	}


// PARAMS VENT RAFALES 1 heure
	// On sort un tableau contenant le dt "CEIL", et ensuite le dt de la rafale max, sa direction et sa vitesse
	$query_string = "SELECT CEIL(`dateTime`/3600.0)*3600 AS `ts`, a.`dateTime`, a.`windGust`, a.`windGustDir`
					FROM $db_table a
					INNER JOIN (
						SELECT CEIL(`dateTime`/3600.0)*3600 AS `dtUTC2`, MAX(`windGust`) AS `windGustMax`
						FROM $db_table
						WHERE `dateTime` >= $tsStart AND `dateTime` <= $tsStop
						GROUP BY `dtUTC2`
					) b
					ON CEIL(a.`dateTime`/3600.0)*3600 = b.`dtUTC2` AND b.`windGustMax` = a.`windGust`
					WHERE `dateTime` >= $tsStart AND `dateTime` <= $tsStop
					ORDER BY `a`.`dateTime` ASC;";
	$result       = $db_handle_pdo->query($query_string);

	if (!$result and $debug) {
		// Erreur et debug activé
		echo "Erreur dans la requete ".$query_string."\n";
		echo "\nPDO::errorInfo():\n";
		print_r($db_handle_pdo->errorInfo());
		exit("Erreur.\n");
	}
	if ($result) {
		while($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$windGustMax1h    = null;
			$windGustMaxDir1h = null;
			$windGustMaxdt1h  = null;
			$row['ts'] = (string)round($row['ts']);
			if (!is_null ($row['windGust'])) {
				if ($unit=='1') {
					$windGustMax1h = round($row['windGust']*1.60934,1);
				}elseif ($unit=='16') {
					$windGustMax1h = round($row['windGust'],1);
				}elseif ($unit=='17') {
					$windGustMax1h = round($row['windGust']*3.6,1);
				}
				if (!is_null ($row['windGustDir'])) { $windGustMaxDir1h = round($row['windGustDir'],1); }
				$windGustMaxdt1h = date('Y-m-d H:i:s',$row['dateTime']);
			}
			// Insertion dans le tableau des données
			// Sauf que notre résultat comprend des doublons (plusieurs rafales max identique dans la même heure), donc on n'insert que si la valeur n'a pas déjà été enregistrée pour cette même KEY (ts) == On garde donc seulement la première rafale max
			if (!isset($csvTab [$row['ts']] ['windGustMax1h'])) {
				$csvTab [$row['ts']] ['windGustMax1h'] = $windGustMax1h;
				$csvTab [$row['ts']] ['windGustMaxDir1h'] = $windGustMaxDir1h;
				$csvTab [$row['ts']] ['windGustMaxdt1h'] = $windGustMaxdt1h;
			}
		}
	}

// PARAMS VENT RAFALES 10 min
	// On sort un tableau contenant le dt "CEIL", et ensuite le dt de la rafale max, sa direction et sa vitesse
	$query_string = "SELECT CEIL(`dateTime`/600.0)*600 AS `ts`, a.`dateTime`, a.`windGust`, a.`windGustDir`
					FROM $db_table a
					INNER JOIN (
						SELECT CEIL(`dateTime`/600.0)*600 AS `dtUTC2`, MAX(`windGust`) AS `windGustMax`
						FROM $db_table
						WHERE `dateTime` >= $tsStart AND `dateTime` <= $tsStop
						GROUP BY `dtUTC2`
					) b
					ON CEIL(a.`dateTime`/600.0)*600 = b.`dtUTC2` AND b.`windGustMax` = a.`windGust`
					WHERE `dateTime` >= $tsStart AND `dateTime` <= $tsStop
					ORDER BY `a`.`dateTime` ASC;";
	$result       = $db_handle_pdo->query($query_string);

	if (!$result and $debug) {
		// Erreur et debug activé
		echo "Erreur dans la requete ".$query_string."\n";
		echo "\nPDO::errorInfo():\n";
		print_r($db_handle_pdo->errorInfo());
		exit("Erreur.\n");
	}
	if ($result) {
		while($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$windGustMax10m    = null;
			$windGustMaxDir10m = null;
			$windGustMaxdt10m  = null;
			$row['ts'] = (string)round($row['ts']);
			if (!is_null ($row['windGust'])) {
				if ($unit=='1') {
					$windGustMax10m = round($row['windGust']*1.60934,1);
				}elseif ($unit=='16') {
					$windGustMax10m = round($row['windGust'],1);
				}elseif ($unit=='17') {
					$windGustMax10m = round($row['windGust']*3.6,1);
				}
				if (!is_null ($row['windGustDir'])) { $windGustMaxDir10m = round($row['windGustDir'],1); }
				$windGustMaxdt10m = date('Y-m-d H:i:s',$row['dateTime']);
			}
			// Insertion dans le tableau des données
			// Sauf que notre résultat comprend des doublons (plusieurs rafales max identique dans la tranche de 10 minutes), donc on n'insert que si la valeur n'a pas déjà été enregistrée pour cette même KEY (ts) == On garde donc seulement la première rafale max
			if (!isset($csvTab [$row['ts']] ['windGustMax10m'])) {
				$csvTab [$row['ts']] ['windGustMax10m'] = $windGustMax10m;
				$csvTab [$row['ts']] ['windGustMaxDir10m'] = $windGustMaxDir10m;
				$csvTab [$row['ts']] ['windGustMaxdt10m'] = $windGustMaxdt10m;
			}
		}
	}

// PARAMS VENT MOYEN VITESSE sur 10 minutes
	$query_string = "SELECT CEIL(`dateTime`/600.0)*600 AS `ts`,
									AVG(`windSpeed`) AS `windSpeedAvg10m`
					FROM $db_table WHERE `dateTime` >= $tsStart AND `dateTime` <= $tsStop GROUP BY `ts` ORDER BY `ts` ASC;";
	$result       = $db_handle_pdo->query($query_string);

	if (!$result and $debug) {
		// Erreur et debug activé
		echo "Erreur dans la requete ".$query_string."\n";
		echo "\nPDO::errorInfo():\n";
		print_r($db_handle_pdo->errorInfo());
		exit("Erreur.\n");
	}
	if ($result) {
		while($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$windSpeedAvg10m = null;
			$row['ts'] = (string)round($row['ts']);
			if (!is_null ($row['windSpeedAvg10m'])) {
				if ($unit=='1') {
					$windSpeedAvg10m = round($row['windSpeedAvg10m']*1.60934,1);
				}elseif ($unit=='16') {
					$windSpeedAvg10m = round($row['windSpeedAvg10m'],1);
				}elseif ($unit=='17') {
					$windSpeedAvg10m = round($row['windSpeedAvg10m']*3.6,1);
				}
			}
			$csvTab [$row['ts']] ['windSpeedAvg10m'] = $windSpeedAvg10m;
		}
	}

// PARAMS VENT MOYEN direction sur 10 minutes
	$query_string = "SELECT CEIL(`dateTime`/600.0)*600 AS `ts`,
									GROUP_CONCAT(`windDir`) AS `windDirConcat`
					FROM $db_table WHERE `dateTime` >= $tsStart AND `dateTime` <= $tsStop GROUP BY `ts` ORDER BY `ts` ASC;";
	$result       = $db_handle_pdo->query($query_string);

	if (!$result and $debug) {
		// Erreur et debug activé
		echo "Erreur dans la requete ".$query_string."\n";
		echo "\nPDO::errorInfo():\n";
		print_r($db_handle_pdo->errorInfo());
		exit("Erreur.\n");
	}
	if ($result) {
		// Construction du tableau
		while($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$windDirArray = null;
			$windDirAvg10minTemp = null;
			$row['ts'] = (string)round($row['ts']);
			if (!is_null($row['windDirConcat'])) {
				$windDirArray[] = explode(',', $row['windDirConcat']);
			}
			// Calcul de la moyenne avec la fonction `mean_of_angles` et le tableau
			if (!is_null ($windDirArray)) {
				$windDirAvg10minTemp = mean_of_angles($windDirArray['0']);
			}
			// Vérif not null
			$windDirAvg10m = null;
			if (!is_null ($windDirAvg10minTemp)) {
				$windDirAvg10m = round($windDirAvg10minTemp,1);
			}
			// Insertion dans le tableau CSV
			$csvTab [$row['ts']] ['windDirAvg10m'] = $windDirAvg10m;
		}
	}

// PARAMS Tn12h
	$tsStart12h = $tsStart-(12*3600);
	$query_string = "SELECT CEIL((`dateTime`+6*3600)/(3600.0*12.0))*3600*12-(6*3600) AS `ts`,
						a.`dateTime`, a.`outTemp`
					FROM $db_table a
					INNER JOIN (
						SELECT CEIL((`dateTime`+6*60*60)/(3600.0*12.0))*3600*12 AS `dtUTC2`, MIN(`outTemp`) AS `Tn12h`
						FROM $db_table
						WHERE `dateTime` >= $tsStart12h AND `dateTime` <= $tsStop
						GROUP BY `dtUTC2`
					) b
					ON CEIL((a.`dateTime`+6*60*60)/(3600.0*12.0))*3600*12 = b.`dtUTC2` AND b.`Tn12h` = a.`outTemp`
					WHERE `dateTime` >= $tsStart12h AND `dateTime` <= $tsStop
					ORDER BY `a`.`dateTime` ASC;";
	$result       = $db_handle_pdo->query($query_string);

	if (!$result and $debug) {
		// Erreur et debug activé
		echo "Erreur dans la requete ".$query_string."\n";
		echo "\nPDO::errorInfo():\n";
		print_r($db_handle_pdo->errorInfo());
		exit("Erreur.\n");
	}
	if ($result) {
		while($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$Tn12h   = null;
			$TnDt12h = null;
			$row['ts'] = (string)round($row['ts']);
			if (!is_null ($row['outTemp'])) {
				if ($unit == '1') {
					$Tn12h = round(($row['outTemp']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$Tn12h = round($row['outTemp'],1);
				}
				$TnDt12h = date('Y-m-d H:i:s',$row['dateTime']);
			}
			// Insertion dans le tableau des données
			// Sauf que notre résultat comprend des doublons (plusieurs rafales max identique dans la même heure), donc on n'insert que si la valeur n'a pas déjà été enregistrée pour cette même KEY (ts) == On garde donc seulement la première rafale max
			if (!isset($csvTab [$row['ts']] ['Tn12h'])) {
				$csvTab [$row['ts']] ['Tn12h'] = $Tn12h;
				$csvTab [$row['ts']] ['TnDt12h'] = $TnDt12h;
			}

			// $Tn12hArray = null;
			// if (!is_null($row['outTemp'])) {
			// 	if (!isset($Tn12hArray [$row['ts']] ['Tn1h'])) {
			// 		$Tn12hArray [$row['ts']] ['dtUtcTn1h'] = date('Y-m-d H:i:s',$row['dateTime']);
			// 		$Tn12hArray [$row['ts']] ['Tn1h'] = $row['outTemp'];
			// 	}
			// }
		}
	}

// PARAMS Tx12h
	$tsStart12h = $tsStart-(12*3600);
	$query_string = "SELECT CEIL((`dateTime`+6*3600)/(3600.0*12.0))*3600*12-(6*3600) AS `ts`,
						a.`dateTime`, a.`outTemp`
					FROM $db_table a
					INNER JOIN (
						SELECT CEIL((`dateTime`+6*60*60)/(3600.0*12.0))*3600*12 AS `dtUTC2`, MAX(`outTemp`) AS `Tx12h`
						FROM $db_table
						WHERE `dateTime` >= $tsStart12h AND `dateTime` <= $tsStop
						GROUP BY `dtUTC2`
					) b
					ON CEIL((a.`dateTime`+6*60*60)/(3600.0*12.0))*3600*12 = b.`dtUTC2` AND b.`Tx12h` = a.`outTemp`
					WHERE `dateTime` >= $tsStart12h AND `dateTime` <= $tsStop
					ORDER BY `a`.`dateTime` ASC;";
	$result       = $db_handle_pdo->query($query_string);

	if (!$result and $debug) {
		// Erreur et debug activé
		echo "Erreur dans la requete ".$query_string."\n";
		echo "\nPDO::errorInfo():\n";
		print_r($db_handle_pdo->errorInfo());
		exit("Erreur.\n");
	}
	if ($result) {
		while($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$Tx12h   = null;
			$TxDt12h = null;
			$row['ts'] = (string)round($row['ts']);
			if (!is_null ($row['outTemp'])) {
				if ($unit == '1') {
					$Tx12h = round(($row['outTemp']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$Tx12h = round($row['outTemp'],1);
				}
				$TxDt12h = date('Y-m-d H:i:s',$row['dateTime']);
			}
			// Insertion dans le tableau des données
			// Sauf que notre résultat comprend des doublons (plusieurs rafales max identique dans la même heure), donc on n'insert que si la valeur n'a pas déjà été enregistrée pour cette même KEY (ts) == On garde donc seulement la première rafale max
			if (!isset($csvTab [$row['ts']] ['Tx12h'])) {
				$csvTab [$row['ts']] ['Tx12h'] = $Tx12h;
				$csvTab [$row['ts']] ['TxDt12h'] = $TxDt12h;
			}
		}
	}

// PARAMS RR cumuls sur 12h
	// On remonte sur 24h avant le tsStart pour être sur d'avoir au moins 12 h avant
	$tsStart12h = $tsStart-(24*3600);
	$query_string = "SELECT CEIL(`dateTime`/3600.0)*3600 AS `ts`,
						SUM(`rain`) AS `rainCumul1h`
						FROM $db_table
						WHERE `dateTime` >= $tsStart12h AND `dateTime` <= $tsStop
						GROUP BY `ts`
						ORDER BY `ts` ASC;";
	$result       = $db_handle_pdo->query($query_string);

	if (!$result and $debug) {
		// Erreur et debug activé
		echo "Erreur dans la requete ".$query_string."\n";
		echo "\nPDO::errorInfo():\n";
		print_r($db_handle_pdo->errorInfo());
		exit("Erreur.\n");
	}
	if ($result) {
		$Rr12hArray = null;
		$Rr12hArray2 = null;
		while($row = $result->fetch(PDO::FETCH_ASSOC)) {
			$row['ts'] = (string)round($row['ts']);
			if (!is_null($row['rainCumul1h'])) {
				$Rr12hArray [$row['ts']] ['dtUtcRr1h'] = date('Y-m-d H:i:s',$row['ts']);
				$Rr12hArray [$row['ts']] ['Rr1h'] = $row['rainCumul1h'];
			}
		}
		// print_r($Rr12hArray);
		$Rr12hTemp = null;
		foreach($Rr12hArray as $datetime => $valeurs) {
			if (date('H:i:s',$datetime) == '06:00:00' OR date('H:i:s',$datetime) == '18:00:00') {
				// echo "06h ou 18h : ".date('Y-m-d H:i:s',$datetime)." \n";
				if ($datetime < $tsStart12h+12*3600) {
					// echo "Supression d'une valeur trop vieille.\n";
					// echo date('Y-m-d H:i:s',$datetime)."\n";
					continue;
				} else {
					$Rr12hTemp2 = null;
					foreach($Rr12hArray as $datetime2 => $valeurs2) {
						// Si supérieur à 12h en arrière et si inférieur ou égale à celui sélectionné
						if($datetime2 > $datetime-12*3600 && $datetime2 <= $datetime) {
							$Rr12hTemp2 = $Rr12hTemp2 + $valeurs2['Rr1h'];
						}
					}
					if (!is_null($Rr12hTemp2)) {
						$Rr12hArray2 [$datetime] ['Rr12h'] = $Rr12hTemp2;
					}
				}
			}
		}
		foreach($Rr12hArray2 as $datetime => $valeurs) {
			if (!is_null ($valeurs['Rr12h'])) {
				if ($unit == '1') {
					$rainCumul12h = round($valeurs['Rr12h']*25.4,1);
				}elseif ($unit == '16') {
					$rainCumul12h = round($valeurs['Rr12h']*10,1);
				}elseif ($unit == '17') {
					$rainCumul12h = round($valeurs['Rr12h'],1);
				}
				$csvTab [$datetime] ['rainCumul12h'] = $rainCumul12h;
			}
		}
	}

	if ($debug) {
		print_r($csvTab);
	}

// Insert dans le fichier CSV
	$fieldsNumber = count($csvTab['header']);
	$csvFile = $repository."/weewxPosteriori_".$id_station.".csv";
	$fp      = fopen($csvFile, 'w');
	fputcsv($fp,array_keys($csvTab['header']));
	foreach($csvTab as $k => $fields) {
		if (!isset($fields['dtUTC'])) continue;
		if ($k === 'header') continue;
		if ($k > $tsStop || $k < $tsStart) continue;
		$w = 0;
		foreach($csvTab['header'] as $fieldName => $_dummy) {
			$ligne = @$fields[$fieldName];
			$w ++;
			if ($w != $fieldsNumber) $ligne = $ligne.",";
			fwrite($fp, $ligne);
		}
		fwrite($fp, "\n");
	}
	$fcloseOK = fclose($fp);

	if ($debug && $fcloseOK) {
		echo "Fin de l'écriture du fichier : ".$csvFile.PHP_EOL;
	}

	// Push du fichier sur le FTP IC
	if ($ftp_enable) {
		passthru("gzip -fc ${csvFile} > ${csvFile}.gz");
		$conn_id = ftp_connect($ftp_server) or die("Connexion impossible à $ftp_server");
		if (!@ftp_login($conn_id, $ftp_username, $ftp_password)) { die("Identifiants FTP incorects");}
		$remote = "weewxPosteriori_".$id_station.".csv.gz";
		$ftpPut = ftp_put($conn_id, $remote, $csvFile.".gz", FTP_ASCII);
		ftp_close($conn_id);
		if ($debug && $ftpPut) {
			echo "Fichier ".$csvFile." envoyé avec succès (et compressé) sur ".$ftp_server."\n";
		}
	} else {
		if ($debug) {
			echo "Envoi FTP désactivé.\n";
		}
	}


// FIN
	$timeEndScript        = microtime(true);
	$timeGenerationScript = $timeEndScript - $timeStartScript;
	$pageLoadTime         = number_format($timeGenerationScript, 3);
	if ($debug) {
		echo "Execution en ".$pageLoadTime."secondes".PHP_EOL;
	}