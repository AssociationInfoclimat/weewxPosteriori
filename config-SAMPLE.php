<?php
/*
 * Génération d'un CSV pour la récupération à posteriori sur Infoclimat
 * des données issues d'une base de données locales WeeWX sous SQLite ou
 * MySQL, avec détection auto des unités et conversion
*/

// VERSION : V1.2

/*
 * Pour lancer le script, après avoir modifier les valeurs par défaut du fichier de configuration -ci-dessous- : 
 * php /home/pi/weewxPosteriori/weewxPosteriori.php
 * 
 * Pour automatiser la création du fichier toutes les heures dans le crontab: 
 * 00 * * * * php /home/pi/weewxPosteriori/weewxPosteriori.php >/dev/null 2>&1
*/

/*
 * En CLI :
 * En fonctionnement normal sans debug pour un fichier sur les deux dernières heures (interval de 10 minutes) :
 * php /home/pi/weewxPosteriori/weewxPosteriori.php --db-type=mysql --db-host=localhost --db-user="user" --db-pass="pass" --db-name="db_name" --db-table="archive" --periode-recup=7200 --intvl-recup=10 --id-station="id_station" --repo-csv="/dev/shm/"
 * 
 * Pour avoir du debug :
 * php /home/pi/weewxPosteriori/weewxPosteriori.php --debug --db-type=mysql --db-host=localhost --db-user="user" --db-pass="pass" --db-name="db_name" --db-table="archive" --periode-recup=7200 --intvl-recup=10 --id-station="id_station" --repo-csv="/dev/shm/"
 * 
*/

// CONFIG DEBUG & RECUP
	$debug         = True;           // True ou False
	$periodeRecup  = 2 * 3600;       // Doit être en secondes | Par défaut = 2 heures
	$intervalRecup = 10;             // 10 ou 60 : doit être en minutes | Par défaut à 10 minutes quand récup sur quelques heures, pourra éventuellement être passé à 60 pour une récup de plusieurs jours

// CONFIG BDD & FILES

	/*
	* Mode SQLite ou MySQL ?
	*/
	$db_type = 'sqlite';  // deux valeurs possibles : sqlite ou mysql

	/*
	* Emplacement de la BDD SQLite de WeeWX (si $db_type = "sqlite")
	*/
	$db_file         = '/home/weewx/archive/weewx.sdb';  // Emplacement du fichier archive SQLite
	$db_table_sqlite = 'archive';                        // Nom de la table (par défaut : weewx)

	/*
	* Parametres de connexion à la base de données WeeWX (si $db_type = "mysql")
	*/
	$db_host        = 'localhost';
	$db_user        = 'weewx';
	$db_pass        = 'passe';
	$db_name_mysql  = 'weewx';
	$db_table_mysql = 'archive';

// ID STATION
	$id_station = '0001';  // ID de la station qui servira de nom au fichier texte créé (le nom du fichier aura le préfixe "weewxPosteriori_" suivi de l'ID, suivi du suffixe ".csv")

// EMPLACEMENT FICHIER CSV EN LOCAL
	$repository = '/dev/shm/';  // Emplacement du fichier CSV de sortie --> /dev/shm/ est généralement l'emplacement de la RAM, ce qui convient très bien pour un fichier temporaire

// CONFIG FTP
	$ftp_enable   = False;  // Activer ou désactiver l'envoi FTP
	$ftp_server   = '';     // Host
	$ftp_username = '';     // Utilisateur
	$ftp_password = '';     // Mot de passe

?>