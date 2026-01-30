<?php
session_start();
require '../system/config_discord.php'; 

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->exec("UPDATE role_config SET can_post = 0, is_admin = 0");
    
    foreach ($_POST['roles'] as $role_id => $perms) {
        $can_post = isset($perms['can_post']) ? 1 : 0;
        $is_admin = isset($perms['is_admin']) ? 1 : 0;
        $role_name = $_POST['role_names'][$role_id];

        $stmt = $pdo->prepare("INSERT INTO role_config (role_id, role_name, can_post, is_admin) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE can_post=?, is_admin=?, role_name=?");
        $stmt->execute([$role_id, $role_name, $can_post, $is_admin, $can_post, $is_admin, $role_name]);
    }
    $success = "Permissions mises à jour !";
}

$discord_roles = discord_api("https://discord.com/api/guilds/".DISCORD_GUILD_ID."/roles", DISCORD_BOT_TOKEN, true);
usort($discord_roles, function($a, $b) { return $b['position'] - $a['position']; });
$saved_config = $pdo->query("SELECT * FROM role_config")->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Permissions Staff</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>body { padding:40px; text-align:center; }</style>
</head>
<body>
    <h1>Permissions Discord <a href="index.php" style="font-size:0.8rem; color:gray;">(Retour)</a></h1>
    <?php if(isset($success)) echo "<p style='color:green'>$success</p>"; ?>
    <form method="POST">
        <div style="display:inline-block; text-align:left; background:#222; padding:20px; border-radius:10px;">
            <?php foreach($discord_roles as $role): 
                if($role['name'] == '@everyone') continue;
                $rid = $role['id'];
                $can_post = isset($saved_config[$rid]) && $saved_config[$rid]['can_post'];
                $is_admin = isset($saved_config[$rid]) && $saved_config[$rid]['is_admin'];
            ?>
            <div style="margin-bottom:10px; border-bottom:1px solid #444; padding-bottom:5px;">
                <strong style="color:<?= $role['color'] ? '#'.dechex($role['color']) : '#aaa' ?>"><?= htmlspecialchars($role['name']) ?></strong>
                <input type="hidden" name="role_names[<?= $rid ?>]" value="<?= htmlspecialchars($role['name']) ?>">
                <label style="margin-left:20px;"><input type="checkbox" name="roles[<?= $rid ?>][can_post]" <?= $can_post ? 'checked' : '' ?>> Autorisé</label>
                <label style="margin-left:10px; color:red;"><input type="checkbox" name="roles[<?= $rid ?>][is_admin]" <?= $is_admin ? 'checked' : '' ?>> Admin</label>
            </div>
            <?php endforeach; ?>
        </div>
        <br><br>
        <button type="submit" style="padding:15px 30px; background:blue; color:white; border:none; cursor:pointer;">ENREGISTRER</button>
    </form>
</body>
</html>