<?php
// 1. Charger la config locale (TES VRAIS SECRETS) si elle existe
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// 2. Définir les valeurs par défaut (POUR GITHUB) seulement si elles ne sont pas déjà définies
if (!defined('DISCORD_CLIENT_ID')) define('DISCORD_CLIENT_ID', '1454477096175800411');
if (!defined('DISCORD_GUILD_ID')) define('DISCORD_GUILD_ID', '1428765848658776207');
if (!defined('DISCORD_REDIRECT_URI')) define('DISCORD_REDIRECT_URI', 'https://losvegas.cloud/dashboard/auth.php');

// Ces deux-là sont des secrets. Si config.local.php n'est pas là, on met des faux pour GitHub.
if (!defined('DISCORD_CLIENT_SECRET')) define('DISCORD_CLIENT_SECRET', 'CHANGE_MOI_SUR_LE_SERVEUR');
if (!defined('DISCORD_BOT_TOKEN')) define('DISCORD_BOT_TOKEN', 'METS_TON_TOKEN_ICI_MAIS_NE_PUSH_PAS');

// On inclut db.php
require_once 'db.php';

if (!function_exists('discord_api')) {
    function discord_api($url, $token = null, $bot = false) {
        $ch = curl_init();
        $headers = [];
        if ($token) {
            $headers[] = $bot ? "Authorization: Bot $token" : "Authorization: Bearer $token";
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($headers)) { curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        if (curl_errno($ch)) { die('Erreur cURL : ' . curl_error($ch)); }
        curl_close($ch);
        return json_decode($response, true);
    }
}
?>