<?php
// 1. CONNEXION SITE / ABSENCES (TiDB Cloud)
$hostSite = 'gateway01.eu-central-1.prod.aws.tidbcloud.com';
$portSite = '4000';
$dbSite   = 'losvegas';
$userSite = 'Hfr42pnNRrJPy51.root';
$passSite = 'wVbFoxbaWOC6QFhT';

$optionsSite = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
];

try {
    $pdoSite = new PDO("mysql:host=$hostSite;port=$portSite;dbname=$dbSite;charset=utf8mb4", $userSite, $passSite, $optionsSite);
} catch (PDOException $e) {
    die("Erreur Connexion SITE : " . $e->getMessage());
}

// 2. CONNEXION FIVEM / LOTO (Serveur Jeu)
$hostFiveM = '83.150.218.23';
$portFiveM = '3306';
$dbFiveM   = 's99395_BlackWall_DB';
$userFiveM = 'u99395_iOOOQLGXO2';
$passFiveM = 'RjrZD7eLKhGoywH0xfQUAZEf';

try {
    $pdoFiveM = new PDO("mysql:host=$hostFiveM;port=$portFiveM;dbname=$dbFiveM;charset=utf8mb4", $userFiveM, $passFiveM, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erreur Connexion FIVEM : " . $e->getMessage());
}

// PAR DÉFAUT : On utilise la DB du SITE pour le staff
$pdo = $pdoSite;
?>