<?php
session_start();
require '../system/config_discord.php'; 

// Synchro permissions
if (isset($_SESSION['discord_user']['all_roles']) && !empty($_SESSION['discord_user']['all_roles'])) {
    $my_roles = $_SESSION['discord_user']['all_roles'];
    $placeholders = str_repeat('?,', count($my_roles) - 1) . '?';
    $stmt = $pdo->prepare("SELECT can_post, is_admin FROM role_config WHERE role_id IN ($placeholders)");
    $stmt->execute($my_roles);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $_SESSION['discord_user']['can_post'] = false;
    $_SESSION['discord_user']['is_admin'] = false;

    foreach ($results as $perm) {
        if ($perm['can_post'] == 1) $_SESSION['discord_user']['can_post'] = true;
        if ($perm['is_admin'] == 1) $_SESSION['discord_user']['is_admin'] = true;
    }
}

if (isset($_GET['logout'])) { unset($_SESSION['discord_user']); header('Location: absences.php'); exit; }

if (isset($_GET['delete']) && isset($_SESSION['discord_user'])) {
    $id = $_GET['delete'];
    $user = $_SESSION['discord_user'];
    $stmt = $pdo->prepare("SELECT discord_id FROM staff_absences WHERE id = ?");
    $stmt->execute([$id]);
    $absence = $stmt->fetch();

    if ($absence && ($absence['discord_id'] == $user['id'] || $user['is_admin'])) {
        $pdo->prepare("DELETE FROM staff_absences WHERE id = ?")->execute([$id]);
    }
    header('Location: absences.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['discord_user'])) {
    $user = $_SESSION['discord_user'];
    if ($user['can_post']) { 
        $role = $user['role'];
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $reason = $_POST['reason'];
        $stmt = $pdo->prepare("INSERT INTO staff_absences (discord_id, username, avatar, role_name, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], $user['username'], $user['avatar'], $role, $start, $end, $reason]);
    }
    header('Location: absences.php'); exit;
}

$absences = $pdo->query("SELECT * FROM staff_absences WHERE end_date >= CURDATE() ORDER BY start_date ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Absences Staff - BlackWall</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg: #09090b; --card: #18181b; --accent: #ef4444; --text: #e4e4e7; --border: #27272a; }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Outfit', sans-serif; }
        body { background: var(--bg); color: var(--text); padding: 40px 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; border-bottom: 1px solid var(--border); padding-bottom: 20px; }
        .title h1 { font-size: 2rem; font-weight: 700; } .title span { color: var(--accent); }
        .btn-discord { background: #5865F2; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 10px; }
        .user-profile { display: flex; align-items: center; gap: 15px; }
        .user-profile img { width: 45px; height: 45px; border-radius: 50%; border: 2px solid var(--accent); }
        .user-role-badge { font-size: 0.75rem; background: #333; padding: 2px 6px; border-radius: 4px; color: #aaa; text-transform: uppercase; }
        .form-card { background: var(--card); padding: 30px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 50px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; color: #888; font-size: 0.9rem; }
        input, textarea { width: 100%; background: #0f0f11; border: 1px solid var(--border); color: white; padding: 12px; border-radius: 8px; outline: none; }
        input:focus { border-color: var(--accent); }
        .btn-submit { background: var(--accent); color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: bold; cursor: pointer; float: right; }
        .absences-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .absence-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; position: relative; overflow: hidden; }
        .absence-card::before { content: ''; position: absolute; top:0; left:0; width: 4px; height: 100%; background: var(--accent); }
        .card-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .card-avatar { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; }
        .card-role { color: var(--accent); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; }
        .card-dates { background: #222; padding: 10px; border-radius: 6px; font-size: 0.9rem; text-align: center; margin-bottom: 15px; border: 1px solid var(--border); }
        .card-reason { color: #ccc; font-size: 0.9rem; font-style: italic; }
        .btn-delete { position: absolute; top: 15px; right: 15px; color: #666; cursor: pointer; transition: 0.3s; }
        .btn-delete:hover { color: var(--accent); }
        .no-absences { text-align: center; color: #666; font-size: 1.2rem; margin-top: 50px; }
        .no-absences i { display: block; font-size: 3rem; margin-bottom: 20px; color: #333; }
        .permission-denied { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; padding: 15px; border-radius: 8px; margin-bottom: 30px; text-align: center; }
    </style>
</head>
<body>

    <div class="container">
        <header class="header">
            <div class="title">
                <h1>BlackWall <span>Absences</span></h1>
                <a href="../staff.php" style="color:#666; text-decoration:none; font-size:0.9rem;">← Retour au Staff</a>
            </div>

            <?php if(isset($_SESSION['discord_user'])): ?>
                <div class="user-profile">
                    <img src="<?= $_SESSION['discord_user']['avatar'] ?>" alt="Avatar">
                    <div>
                        <div style="font-weight:bold;"><?= htmlspecialchars($_SESSION['discord_user']['username']) ?></div>
                        <span class="user-role-badge"><?= htmlspecialchars($_SESSION['discord_user']['role']) ?></span>
                        <a href="?logout=true" style="color:#ef4444; font-size:0.8rem; margin-left:10px; text-decoration:none;">Déconnexion</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="auth.php" class="btn-discord"><i class="fab fa-discord"></i> Se connecter Staff</a>
            <?php endif; ?>
        </header>

        <?php if(isset($_SESSION['discord_user'])): ?>
            <?php if($_SESSION['discord_user']['can_post']): ?>
                <div class="form-card">
                    <h3 style="margin-bottom:20px; color:white;">Signaler une absence</h3>
                    <form method="POST">
                        <div class="form-grid">
                            <div class="input-group">
                                <label>Date de début</label>
                                <input type="date" name="start_date" required>
                            </div>
                            <div class="input-group">
                                <label>Date de retour prévue</label>
                                <input type="date" name="end_date" required>
                            </div>
                        </div>
                        <div class="input-group" style="margin-bottom:20px;">
                            <label>Motif (Facultatif)</label>
                            <textarea name="reason" rows="2" placeholder="Ex: Vacances, Examens..."></textarea>
                        </div>
                        <button type="submit" class="btn-submit">Valider</button>
                        <div style="clear:both;"></div>
                    </form>
                </div>
            <?php else: ?>
                <div class="permission-denied">
                    <i class="fas fa-lock"></i> Vous êtes connecté en mode <b>Consultation</b> uniquement.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if(!empty($absences)): ?>
            <h3 style="margin-bottom:20px;">Absences en cours</h3>
            <div class="absences-grid">
                <?php foreach($absences as $abs): 
                    $start = date("d/m/Y", strtotime($abs['start_date']));
                    $end = date("d/m/Y", strtotime($abs['end_date']));
                    $can_delete = false;
                    if(isset($_SESSION['discord_user'])) {
                        if($_SESSION['discord_user']['id'] == $abs['discord_id'] || $_SESSION['discord_user']['is_admin']) {
                            $can_delete = true;
                        }
                    }
                ?>
                <div class="absence-card">
                    <?php if($can_delete): ?>
                        <a href="?delete=<?= $abs['id'] ?>" class="btn-delete" onclick="return confirm('Supprimer cette absence ?')"><i class="fas fa-trash"></i></a>
                    <?php endif; ?>
                    
                    <div class="card-header">
                        <img src="<?= htmlspecialchars($abs['avatar']) ?>" class="card-avatar">
                        <div>
                            <h3><?= htmlspecialchars($abs['username']) ?></h3>
                            <div class="card-role"><?= htmlspecialchars($abs['role_name']) ?></div>
                        </div>
                    </div>
                    <div class="card-dates">
                        <i class="fas fa-calendar-alt"></i> Du <b><?= $start ?></b> au <b><?= $end ?></b>
                    </div>
                    <?php if(!empty($abs['reason'])): ?>
                        <div class="card-reason">"<?= htmlspecialchars($abs['reason']) ?>"</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-absences">
                <i class="fas fa-clipboard-check"></i>
                Aucune absence signalée.<br>
                <span style="font-size:1rem;">L'équipe est au complet !</span>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>