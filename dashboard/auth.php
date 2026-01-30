<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require '../system/config_discord.php'; // On remonte d'un dossier

if (isset($_GET['code'])) {
    $data = [
        "client_id" => DISCORD_CLIENT_ID,
        "client_secret" => DISCORD_CLIENT_SECRET,
        "grant_type" => "authorization_code",
        "code" => $_GET['code'],
        "redirect_uri" => DISCORD_REDIRECT_URI
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://discord.com/api/oauth2/token");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    if (!isset($response['access_token'])) {
        die("Erreur Discord : " . ($response['error_description'] ?? 'Inconnue'));
    }

    $access_token = $response['access_token'];
    $user_global = discord_api("https://discord.com/api/users/@me", $access_token);
    $user_id = $user_global['id'];

    $member = discord_api("https://discord.com/api/guilds/".DISCORD_GUILD_ID."/members/$user_id", DISCORD_BOT_TOKEN, true);

    if (isset($member['message']) && $member['message'] == 'Unknown Member') {
        die("Erreur : Tu n'es pas sur le serveur Discord BlackWall !");
    }

    // Gestion des rôles
    $user_roles = $member['roles'] ?? [];
    $best_role_name = "Membre";
    $highest_position = -1;
    $all_guild_roles = discord_api("https://discord.com/api/guilds/".DISCORD_GUILD_ID."/roles", DISCORD_BOT_TOKEN, true);
    
    if (is_array($all_guild_roles)) {
        foreach ($all_guild_roles as $r) {
            if (in_array($r['id'], $user_roles)) {
                if ($r['position'] > $highest_position) {
                    $highest_position = $r['position'];
                    $best_role_name = $r['name'];
                }
            }
        }
    }

    $final_username = $member['nick'] ?? $user_global['global_name'] ?? $user_global['username'];
    $avatar_id = $member['avatar'] ?? $user_global['avatar'];
    $avatar_url = isset($member['avatar']) 
        ? "https://cdn.discordapp.com/guilds/".DISCORD_GUILD_ID."/users/$user_id/avatars/$avatar_id.png"
        : (isset($user_global['avatar']) ? "https://cdn.discordapp.com/avatars/$user_id/$avatar_id.png" : "https://cdn.discordapp.com/embed/avatars/0.png");

    $_SESSION['discord_user'] = [
        'id' => $user_id,
        'username' => $final_username,
        'avatar' => $avatar_url,
        'role' => $best_role_name,
        'all_roles' => $user_roles,
        'can_post' => false,
        'is_admin' => false
    ];

    header('Location: absences.php');
    exit();
}

$params = [ "client_id" => DISCORD_CLIENT_ID, "redirect_uri" => DISCORD_REDIRECT_URI, "response_type" => "code", "scope" => "identify" ];
header('Location: https://discord.com/api/oauth2/authorize?' . http_build_query($params));
exit();
?>