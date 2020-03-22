<?php
/*
 * Génération d'un CSV pour la récupération à posteriori sur Infoclimat
 * des données issues d'une base de données locales WeeWX sous SQLite ou
 * MySQL, avec détection auto des unités et conversion
*/

// GO -- NE PLUS TOUCHER

// VERSION : V1.3

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
		'intvl-recup:',    // SQLite ET MySQL
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
		if (isset($paramsCli['intvl-recup']) && $paramsCli['intvl-recup'] == 10 | $paramsCli['intvl-recup'] == 60) {
			$intervalRecup = $paramsCli['intvl-recup'];
		} else {
			exit("ERRO : params intvl-recup non défini ou invalide\n");
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
			// Connection
			$db_handle = new SQLite3($db_file);
		}
		catch (Exception $exception) {
			// sqlite3 throws an exception when it is unable to connect
			echo "Erreur de connexion à la base de données SQLite".PHP_EOL;
			if ($debug) {
				echo $exception->getMessage().PHP_EOL.PHP_EOL;
				exit();
			}
		}
	} elseif ($db_type === "mysql") {
		$db_handle = mysqli_connect($db_host,$db_user,$db_pass,$db_name);
		// Vérification de la connexion
		if ($db_handle->connect_errno && $debug) {
			printf("Echec de la connexion: %s\n", $db_handle->connect_error);
			exit();
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
		global $intervalRecup;
		if ($intervalRecup == 10) {
			$minuteInterval = 10;
		} elseif ($intervalRecup == 60) {
			$minuteInterval = 60;
		}
		return $dateTime->setTime(
			$dateTime->format('H'),
			floor($dateTime->format('i') / $minuteInterval) * $minuteInterval,
			0
		);
	}

// FONCTION : RECUP des intervalles à traiter
	function getDatesFromRange($start, $end, $format = 'Y-m-d H:i:00') {
		global $intervalRecup;
		$array = array();
		if ($intervalRecup == 10) {
			$interval = new DateInterval('PT10M');
		} elseif ($intervalRecup == 60) {
			$interval = new DateInterval('PT1H');
		}

		$realEnd = new DateTime($end);
		$realEnd->add($interval);

		$period = new DatePeriod(new DateTime($start), $interval, $realEnd);

		foreach($period as $date) {
			$array[] = $date->format($format);
		}

		return $array;
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


// CSV FILE PUSH HEADER
	$prepareCSV = array();
	$prepareCSV[] = array ('dateTime', 'TempNow', 'HrNow', 'TdNow', 'barometerNow', 'rainRateNow', 'radiationNow', 'UvNow', 'Tn10min', 'Tx10min', 'rainCumul10min', 'rainRateMax10min', 'radiationMax10min', 'UvMax10min', 'windGustMax1h', 'windGustMaxDir1h', 'windGustMaxdt1h', 'windGustMax10min', 'windGustMaxDir10min', 'windGustMaxdt10min', 'windSpeedAvg10min', 'windDirAvg10min', 'Tn1h', 'Tx1h', 'rainCumul1h', 'rainRateMax1h', 'radiationMax1h', 'UvMax1h', 'Tn12h', 'Tx12h', 'rainCumul3h', 'rainCumul6h', 'rainCumul12h', 'rainCumul24h', 'rainCumulMonth', 'rainCumulYear');

// Établissement des timestamp stop et start
	$query_string = "SELECT `dateTime` FROM $db_table ORDER BY `dateTime` DESC LIMIT 1;";
	$result       = $db_handle->query($query_string);

	if (!$result and $debug) {
		// Erreur et debug activé
		echo "Erreur dans la requete ".$query_string.PHP_EOL;
		if ($db_type === "sqlite") {
			echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
		} elseif ($db_type === "mysql") {
			printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
		}
		exit("Impossible de déterminer le dernier relevé en date dans la BDD WeeWX de votre station.\n");
	}
	if ($result) {
		if ($db_type === "sqlite") {
			$row = $result->fetchArray(SQLITE3_ASSOC);
		} elseif ($db_type === "mysql") {
			$row = mysqli_fetch_assoc($result);
		}

		$tsStop = $row['dateTime'];  // stop = dernier relevé dispo en BDD en timestamp Unix
		
		// Arrondi du datetime Stop
		$datetimeStop = new DateTime();
		$datetimeStop->setTimestamp($tsStop);
		$dtStop = roundDownToMinuteInterval($datetimeStop);

		$dtStop = $dtStop->format("d-m-Y H:i:s");
		$tsStop = strtotime($dtStop);

		$tsStart = $tsStop-($periodeRecup);       // start = dernier relevé - le temps demandé dans le fichier de config (en secondes)
		$dtStart = date('d-m-Y H:i:s',$tsStart);
	}

// Génération de la liste (dans un tableau) des dates a générer
	$dtGenerations = getDatesFromRange($dtStart, $dtStop);

// Affichage du tableau contenant les dates à générer
	if ($debug) {
		echo "Liste des dates à générer :".PHP_EOL;
		echo '<pre>';
		print_r($dtGenerations);
		echo '</pre>';
	}
	if ($debug) {
		echo PHP_EOL.PHP_EOL."Affichage du résultat du traitement pour chaque date :".PHP_EOL.PHP_EOL.PHP_EOL;
	}

	// Boucle sur chaque dates à générer
	foreach ($dtGenerations as $dtGeneration) {
		// CONVERT date en timestamp | Stop = fin && Start = début
		$dtStopActu  = $dtGeneration;
		$tsStopActu  = strtotime($dtStopActu);
		$tsStartActu = $tsStopActu-($intervalRecup*60);
		$dtStartActu = date('Y-m-d H:i:s',$tsStartActu);

		$tsStart10m = $tsStopActu-(10*60);
		$tsStart1h = $tsStopActu-(60*60);
		$tsStart3h = $tsStopActu-(3*60*60);
		$tsStart6h = $tsStopActu-(6*60*60);
		$tsStart12h = $tsStopActu-(12*60*60);
		$tsStart24h = $tsStopActu-(24*60*60);

		if ($debug) {
			echo "Traitement du ".$dtStopActu.PHP_EOL;
		}

		###############################################################
		##### PARAMS TEMPS REEL
		###############################################################

		$query_string = "SELECT * FROM $db_table WHERE `dateTime` = '$tsStopActu';";
		$result       = $db_handle->query($query_string);

		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			$tempNow      = null;
			$HrNow        = null;
			$TdNow        = null;
			$barometerNow = null;
			$rainRateNow  = null;
			$radiationNow = null;
			$UvNow        = null;

			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}

			// UNITS
			$unit = $row['usUnits'];

			// PARAMS TEMPS REEL
			if (!is_null ($row['outTemp'])) {
				if ($unit == '1') {
					$tempNow = round(($row['outTemp']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$tempNow = round($row['outTemp'],1);
				}
			}
			if (!is_null ($row['outHumidity'])) {
				$HrNow  = round($row['outHumidity'],1);
			}
			if (!is_null ($row['dewpoint'])) {
				if ($unit == '1') {
					$TdNow = round(($row['dewpoint']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$TdNow = round($row['dewpoint'],1);
				}
			}
			if (!is_null ($row['barometer'])) {
				if ($unit == '1') {
					$barometerNow = round($row['barometer']*33.8639,1);
				}elseif ($unit == '16' || $unit == '17') {
					$barometerNow = round($row['barometer'],1);
				}
			}
			if (!is_null ($row['rainRate'])) {
				if ($unit == '1') {
					$rainRateNow = round($row['rainRate']*25.4,1);
				}elseif ($unit=='16') {
					$rainRateNow = round($row['rainRate']*10,1);
				}elseif ($unit=='17') {
					$rainRateNow = round($row['rainRate'],1);
				}
			}
			if (!is_null ($row['radiation'])) {
				$radiationNow  = round($row['radiation'],0);
			}
			if (!is_null ($row['UV'])) {
				$UvNow  = round($row['UV'],1);
			}
		}

		###############################################################
		##### PARAMS 10 minutes
		###############################################################

		// Calcul Tn10m
		$query_string = "SELECT MIN(`outTemp`) AS `Tn10m` FROM $db_table WHERE `dateTime` > '$tsStart10m' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$Tn10m  = null;
			if (!is_null ($row['Tn10m'])) {
				if ($unit == '1') {
					$Tn10m = round(($row['Tn10m']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$Tn10m = round($row['Tn10m'],1);
				}
			}
		}

		// Calcul Tx10m
		$query_string = "SELECT MAX(`outTemp`) AS `Tx10m` FROM $db_table WHERE `dateTime` > '$tsStart10m' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$Tx10m  = null;
			if (!is_null ($row['Tx10m'])) {
				if ($unit == '1') {
					$Tx10m = round(($row['Tx10m']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$Tx10m = round($row['Tx10m'],1);
				}
			}
		}

		// Calcul RAIN cumul 10m
		$query_string = "SELECT SUM(`rain`) AS `rainCumul10m` FROM $db_table WHERE `dateTime` > '$tsStart10m' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$rainCumul10m = null;
			if (!is_null ($row['rainCumul10m'])) {
				if ($unit == '1') {
					$rainCumul10m = round($row['rainCumul10m']*25.4,1);
				}elseif ($unit == '16') {
					$rainCumul10m = round($row['rainCumul10m']*10,1);
				}elseif ($unit == '17') {
					$rainCumul10m = round($row['rainCumul10m'],1);
				}
			}
		}

		// Récup rainRate max 10m
		$query_string = "SELECT MAX(`rainRate`) AS `rainRateMax10m` FROM $db_table WHERE `dateTime` > '$tsStart10m' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$rainRateMax10m = null;
			if (!is_null ($row['rainRateMax10m'])) {
				if ($unit == '1') {
					$rainRateMax10m = round($row['rainRateMax10m']*25.4,1);
				}elseif ($unit == '16') {
					$rainRateMax10m = round($row['rainRateMax10m']*10,1);
				}elseif ($unit == '17') {
					$rainRateMax10m = round($row['rainRateMax10m'],1);
				}
			}
		}

		// Récup rayonnement max 10m
		$query_string = "SELECT MAX(`radiation`) AS `radiationMax10m` FROM $db_table WHERE `dateTime` > '$tsStart10m' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$radiationMax10m = null;
			if (!is_null ($row['radiationMax10m'])) {
				$radiationMax10m = round($row['radiationMax10m'],0);
			}
		}

		// Récup UV max 10 min
		$query_string = "SELECT MAX(`UV`) AS `UvMax10m` FROM $db_table WHERE `dateTime` > '$tsStart10m' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$UvMax10m = null;
			if (!is_null ($row['UvMax10m'])) {
				$UvMax10m = round($row['UvMax10m'],1);
			}
		}

		###############################################################
		##### PARAMS VENT
		###############################################################

		// Récup rafales max et sa direction sur une heure
		$tsStart1hour = $tsStopActu-(60*60);
		$query_string = "SELECT `dateTime`, `windGust`, `windGustDir` FROM $db_table WHERE `dateTime` > '$tsStart1hour' AND `dateTime` <= '$tsStopActu' AND windGust = (SELECT MAX(`windGust`) FROM $db_table WHERE `dateTime` > '$tsStart1hour' AND `dateTime` <= '$tsStopActu');";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$windGustMax1h    = null;
			$windGustMaxDir1h = null;
			$windGustMaxdt1h  = null;
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
		}

		// Récup rafales max et sa direction sur 10m
		$query_string = "SELECT `dateTime`, `windGust`, `windGustDir` FROM $db_table WHERE `dateTime` > '$tsStart10m' AND `dateTime` <= '$tsStopActu' AND windGust = (SELECT MAX(`windGust`) FROM $db_table WHERE `dateTime` > '$tsStart10m' AND `dateTime` <= '$tsStopActu');";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$windGustMax10m    = null;
			$windGustMaxDir10m = null;
			$windGustMaxdt10m  = null;
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
		}

		// Calcul vitesse moyenne du vent moyen sur 10m
		$query_string = "SELECT AVG(`windSpeed`) AS `windSpeedAvg10m` FROM $db_table WHERE `dateTime` > '$tsStart10m' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$windSpeedAvg10m = null;
			if (!is_null ($row['windSpeedAvg10m'])) {
				if ($unit=='1') {
					$windSpeedAvg10m = round($row['windSpeedAvg10m']*1.60934,1);
				}elseif ($unit=='16') {
					$windSpeedAvg10m = round($row['windSpeedAvg10m'],1);
				}elseif ($unit=='17') {
					$windSpeedAvg10m = round($row['windSpeedAvg10m']*3.6,1);
				}
			}
		}

		// Calcul de la direction moyenne du vent moyen sur 10m
		// Requete + mise en tableau de la réponse
		$windDirArray        = null;
		$windDirAvg10minTemp = null;
		$query_string        = "SELECT `windDir` AS `windDir` FROM $db_table WHERE `dateTime` > '$tsStart10m' AND `dateTime` <= '$tsStopActu';";
		$result              = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			// Construct tableau
			if ($db_type === "sqlite") {
				while ($row=$result->fetchArray(SQLITE3_ASSOC)) {
					if (!is_null ($row['windDir'])) {
						$windDirArray[] = $row['windDir'];
					}
				}
			} elseif ($db_type === "mysql") {
				while ($row=mysqli_fetch_assoc($result)) {
					if (!is_null ($row['windDir'])) {
						$windDirArray[] = $row['windDir'];
					}
				}
			}
			// Calcul de la moyenne avec la fonction `mean_of_angles` et le tableau
			if (!is_null ($windDirArray)) { $windDirAvg10minTemp = mean_of_angles($windDirArray); }
			// Vérif not null
			$windDirAvg10m = null;
			if (!is_null ($windDirAvg10minTemp)) {
				$windDirAvg10m = round($windDirAvg10minTemp,1);
			}
		}

		###############################################################
		##### PARAMS 1 heure
		###############################################################

		// Calcul Tn1h
		$query_string = "SELECT MIN(`outTemp`) AS `Tn1h` FROM $db_table WHERE `dateTime` > '$tsStart1h' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$Tn1h  = null;
			if (!is_null ($row['Tn1h'])) {
				if ($unit == '1') {
					$Tn1h = round(($row['Tn1h']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$Tn1h = round($row['Tn1h'],1);
				}
			}
			
		}

		// Calcul Tx1h
		$query_string = "SELECT MAX(`outTemp`) AS `Tx1h` FROM $db_table WHERE `dateTime` > '$tsStart1h' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$Tx1h  = null;
			if (!is_null ($row['Tx1h'])) {
				if ($unit == '1') {
					$Tx1h = round(($row['Tx1h']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$Tx1h = round($row['Tx1h'],1);
				}
			}
		}

		// Calcul RAIN cumul 1h
		$query_string = "SELECT SUM(`rain`) AS `rainCumul1h` FROM $db_table WHERE `dateTime` > '$tsStart1h' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$rainCumul1h = null;
			if (!is_null ($row['rainCumul1h'])) {
				if ($unit == '1') {
					$rainCumul1h = round($row['rainCumul1h']*25.4,1);
				}elseif ($unit == '16') {
					$rainCumul1h = round($row['rainCumul1h']*10,1);
				}elseif ($unit == '17') {
					$rainCumul1h = round($row['rainCumul1h'],1);
				}
			}
		}

		// Récup rainRate max 1h
		$query_string = "SELECT MAX(`rainRate`) AS `rainRateMax1h` FROM $db_table WHERE `dateTime` > '$tsStart1h' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$rainRateMax1h = null;
			if (!is_null ($row['rainRateMax1h'])) {
				if ($unit == '1') {
					$rainRateMax1h = round($row['rainRateMax1h']*25.4,1);
				}elseif ($unit == '16') {
					$rainRateMax1h = round($row['rainRateMax1h']*10,1);
				}elseif ($unit == '17') {
					$rainRateMax1h = round($row['rainRateMax1h'],1);
				}
			}
		}

		// Récup rayonnement max 1h
		$query_string = "SELECT MAX(`radiation`) AS `radiationMax1h` FROM $db_table WHERE `dateTime` > '$tsStart1h' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$radiationMax1h = null;
			if (!is_null ($row['radiationMax1h'])) {
				$radiationMax1h = round($row['radiationMax1h'],0);
			}
		}

		// Récup UV max 1h
		$query_string = "SELECT MAX(`UV`) AS `UvMax1h` FROM $db_table WHERE `dateTime` > '$tsStart1h' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$UvMax1h = null;
			if (!is_null ($row['UvMax1h'])) {
				$UvMax1h = round($row['UvMax1h'],1);
			}
		}

		###############################################################
		##### PARAMS autres temps
		###############################################################

		// Calcul Tn12h
		$query_string = "SELECT MIN(`outTemp`) AS `Tn12h` FROM $db_table WHERE `dateTime` > '$tsStart12h' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$Tn12h  = null;
			if (!is_null ($row['Tn12h'])) {
				if ($unit == '1') {
					$Tn12h = round(($row['Tn12h']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$Tn12h = round($row['Tn12h'],1);
				}
			}
			
		}

		// Calcul Tx12h
		$query_string = "SELECT MAX(`outTemp`) AS `Tx12h` FROM $db_table WHERE `dateTime` > '$tsStart12h' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$Tx12h  = null;
			if (!is_null ($row['Tx12h'])) {
				if ($unit == '1') {
					$Tx12h = round(($row['Tx12h']-32)/1.8,1);
				}elseif ($unit == '16' || $unit == '17') {
					$Tx12h = round($row['Tx12h'],1);
				}
			}
		}

		// Cumul pluie sur 3 heures glissante
		$query_string = "SELECT SUM(`rain`) AS `rainCumul3h` FROM $db_table WHERE `dateTime` > '$tsStart3h' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$rainCumul3h = null;
			if (!is_null ($row['rainCumul3h'])) {
				if ($unit == '1') {
					$rainCumul3h = round($row['rainCumul3h']*25.4,1);
				}elseif ($unit == '16') {
					$rainCumul3h = round($row['rainCumul3h']*10,1);
				}elseif ($unit == '17') {
					$rainCumul3h = round($row['rainCumul3h'],1);
				}
			}
		}

		// Cumul pluie sur 6 heures glissante
		$query_string = "SELECT SUM(`rain`) AS `rainCumul6h` FROM $db_table WHERE `dateTime` > '$tsStart6h' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$rainCumul6h = null;
			if (!is_null ($row['rainCumul6h'])) {
				if ($unit == '1') {
					$rainCumul6h = round($row['rainCumul6h']*25.4,1);
				}elseif ($unit == '16') {
					$rainCumul6h = round($row['rainCumul6h']*10,1);
				}elseif ($unit == '17') {
					$rainCumul6h = round($row['rainCumul6h'],1);
				}
			}
		}

		// Cumul pluie sur 12 heures glissante
		$query_string = "SELECT SUM(`rain`) AS `rainCumul12h` FROM $db_table WHERE `dateTime` > '$tsStart12h' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$rainCumul12h = null;
			if (!is_null ($row['rainCumul12h'])) {
				if ($unit == '1') {
					$rainCumul12h = round($row['rainCumul12h']*25.4,1);
				}elseif ($unit == '16') {
					$rainCumul12h = round($row['rainCumul12h']*10,1);
				}elseif ($unit == '17') {
					$rainCumul12h = round($row['rainCumul12h'],1);
				}
			}
		}

		// Cumul pluie sur 24 heures glissante peu importe l'intervalle de récup
		$query_string = "SELECT SUM(`rain`) AS `rainCumul24h` FROM $db_table WHERE `dateTime` > '$tsStart24h' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$rainCumul24h = null;
			if (!is_null ($row['rainCumul24h'])) {
				if ($unit == '1') {
					$rainCumul24h = round($row['rainCumul24h']*25.4,1);
				}elseif ($unit == '16') {
					$rainCumul24h = round($row['rainCumul24h']*10,1);
				}elseif ($unit == '17') {
					$rainCumul24h = round($row['rainCumul24h'],1);
				}
			}
		}

		// Calcul RAIN cumul month
		// Premier jour de ce mois
		$dtStartMonth = date('Y-m-01 00:00:00', strtotime($dtStartActu)); // $dtStartActu = le ts de début de l'interval en cours
		// $tsStartMonth  = strtotime($dtStartMonth)-1; // -1 seconde pour inclure dans la requete ci dessous l'enregistrement de l'heure pile
		$tsStartMonth  = strtotime($dtStartMonth);

		$query_string = "SELECT SUM(`rain`) AS `rainCumulMonth` FROM $db_table WHERE `dateTime` > '$tsStartMonth' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($debug) {
				echo '1er du mois : '.$dtStartMonth.PHP_EOL;
			}
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$rainCumulMonth = null;
			if (!is_null ($row['rainCumulMonth'])) {
				if ($unit == '1') {
					$rainCumulMonth = round($row['rainCumulMonth']*25.4,1);
				}elseif ($unit == '16') {
					$rainCumulMonth = round($row['rainCumulMonth']*10,1);
				}elseif ($unit == '17') {
					$rainCumulMonth = round($row['rainCumulMonth'],1);
				}
			}
		}

		// Calcul RAIN cumul year
		// Premier jour de cette année
		$dtStartYear = date('Y-01-01 00:00:00', strtotime($dtStartActu));
		// $tsStartYear  = strtotime($dtStartYear)-1; // -1 seconde pour inclure dans la requete ci dessous l'enregistrement de l'heure pile
		$tsStartYear  = strtotime($dtStartYear);

		$query_string = "SELECT SUM(`rain`) AS `rainCumulYear` FROM $db_table WHERE `dateTime` > '$tsStartYear' AND `dateTime` <= '$tsStopActu';";
		$result       = $db_handle->query($query_string);
		if (!$result && $debug) {
			// Erreur et debug activé
			echo "Erreur dans la requete ".$query_string.PHP_EOL;
			if ($db_type === "sqlite") {
				echo $db_handle->lastErrorMsg().PHP_EOL.PHP_EOL;
			} elseif ($db_type === "mysql") {
				printf("Message d'erreur : %s\n", $db_handle->error).PHP_EOL.PHP_EOL;
			}
		}
		if ($result) {
			if ($debug) {
				echo '1er de l\'année : '.$dtStartYear.PHP_EOL;
			}
			if ($db_type === "sqlite") {
				$row = $result->fetchArray(SQLITE3_ASSOC);
			} elseif ($db_type === "mysql") {
				$row = mysqli_fetch_assoc($result);
			}
			$rainCumulYear = null;
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
			echo "Unite BDD  | ".$unit." | (1 = US ; 16 = METRIC ; 17 = METRICWX)".PHP_EOL;
			echo "Intervalle de ".$intervalRecup." minutes (de ".$dtStartActu." à ".$dtStopActu.")".PHP_EOL;
			echo "CLIMATO		| Tn10m : ".$Tn10m."°C".PHP_EOL;
			echo "		| Tx10m : ".$Tx10m."°C".PHP_EOL;
			echo "		| Tn1h : ".$Tn1h."°C".PHP_EOL;
			echo "		| Tx1h : ".$Tx1h."°C".PHP_EOL;
			echo "		| rainCumul10m : ".$rainCumul10m." mm".PHP_EOL;
			echo "		| rainCumul1h : ".$rainCumul1h." mm".PHP_EOL;
			echo "		| rainCumulMonth : ".$rainCumulMonth." mm".PHP_EOL;
			echo "		| rainCumulYear : ".$rainCumulYear." mm".PHP_EOL;
			echo "		| rainRateMax10m : ".$rainRateMax10m." mm".PHP_EOL;
			echo "		| rainRateMax1h : ".$rainRateMax1h." mm".PHP_EOL;
			echo "		| radiationMax10m : ".$radiationMax10m.PHP_EOL;
			echo "		| radiationMax1h : ".$radiationMax1h.PHP_EOL;
			echo "		| UvMax10m : ".$UvMax10m.PHP_EOL;
			echo "		| UvMax1h : ".$UvMax1h.PHP_EOL;
			echo "		|".PHP_EOL;
			echo "TEMPS REEL	| tempNow : ".$tempNow.PHP_EOL;
			echo "		| HrNow : ".$HrNow.PHP_EOL;
			echo "		| TdNow : ".$TdNow.PHP_EOL;
			echo "		| barometerNow : ".$barometerNow.PHP_EOL;
			echo "		| rainRateNow : ".$rainRateNow.PHP_EOL;
			echo "		| radiationNow : ".$radiationNow.PHP_EOL;
			echo "		| UvNow : ".$UvNow.PHP_EOL;
			echo "		|".PHP_EOL;
			echo "VENT		| Sur une heure gliss. : Raf de ".$windGustMax1h." km/h, dir ".$windGustMaxDir1h."° à ".$windGustMaxdt1h.PHP_EOL;
			echo "VENT		| Sur 10 min.          : Raf de ".$windGustMax10m." km/h, dir ".$windGustMaxDir10m."° à ".$windGustMaxdt10m.PHP_EOL;
			echo "VENT MOY	| Sur 10 min.          : Moy de ".$windSpeedAvg10m." km/h, dir moy ".$windDirAvg10m."°".PHP_EOL.PHP_EOL.PHP_EOL;
		}

		// Insert dans le tableau des valeurs
		$prepareCSV[] = array ($dtStopActu, $tempNow, $HrNow, $TdNow, $barometerNow, $rainRateNow, $radiationNow, $UvNow, $Tn10m, $Tx10m, $rainCumul10m, $rainRateMax10m, $radiationMax10m, $UvMax10m, $windGustMax1h, $windGustMaxDir1h, $windGustMaxdt1h, $windGustMax10m, $windGustMaxDir10m, $windGustMaxdt10m, $windSpeedAvg10m, $windDirAvg10m, $Tn1h, $Tx1h, $rainCumul1h, $rainRateMax1h, $radiationMax1h, $UvMax1h, $Tn12h, $Tx12h, $rainCumul3h, $rainCumul6h, $rainCumul12h, $rainCumul24h, $rainCumulMonth, $rainCumulYear);
	}

	// Insert dans le fichier CSV
	$csvFile = $repository."/weewxPosteriori_".$id_station.".csv";
	$fp      = fopen($csvFile, 'w');
	foreach ($prepareCSV as $fields) {
		fputcsv($fp, $fields);
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

?>
