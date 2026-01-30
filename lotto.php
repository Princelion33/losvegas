<?php
session_start();
require 'system/db.php'; 

// 1. VÉRIFICATION CONNEXION DISCORD
// Si le joueur n'est pas connecté, on ne charge pas la suite
if (!isset($_SESSION['discord_user'])) {
    // On affiche une page simple pour demander la connexion
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <title>Connexion Requise</title>
        <link rel="stylesheet" href="assets/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body>
        <div class="container" style="text-align:center; padding-top:100px;">
            <h1>🔒 Connexion Requise</h1>
            <p style="color:#aaa; margin-bottom:30px;">Vous devez vous connecter avec Discord pour jouer au Loto.</p>
            <a href="dashboard/auth.php" class="btn-play" style="background:#5865F2;">
                <i class="fab fa-discord"></i> Se connecter via Discord
            </a>
            <br><br>
            <a href="index.html" style="color:#666;">Retour accueil</a>
        </div>
    </body>
    </html>
    <?php
    exit; // On arrête le script ici
}

// 2. RÉCUPÉRATION DES INFOS JOUEUR
$discord_id = $_SESSION['discord_user']['id'];
$username = $_SESSION['discord_user']['username'];
// Format FiveM pour Discord (souvent "discord:12345..." ou juste l'ID selon ta DB)
// IMPORTANT : Vérifie dans ta table 'users' comment sont stockés les IDs Discord.
// Si c'est "discord:8474...", garde la ligne ci-dessous.
$user_identifier = "discord:" . $discord_id; 

$pdo = $pdoFiveM; // On utilise la DB FiveM

// Récupérer le loto actif
$stmt = $pdo->query("SELECT * FROM website_lotto WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$lotto = $stmt->fetch();
$message = "";

// 3. TRAITEMENT ACHAT TICKET
if (isset($_POST['buy_ticket']) && $lotto) {
    
    // A. Vérifier l'argent du joueur en Base de Données (Table users)
    // Adapte 'bank' si ton argent est dans 'accounts' (JSON) ou ailleurs
    $stmtUser = $pdo->prepare("SELECT bank FROM users WHERE identifier = ?"); 
    $stmtUser->execute([$user_identifier]);
    $userDB = $stmtUser->fetch();

    if ($userDB) {
        $player_money = $userDB['bank']; // Argent en banque

        if ($player_money >= $lotto['ticket_price']) {
            // B. Retirer l'argent (Mise à jour DB)
            $stmtPay = $pdo->prepare("UPDATE users SET bank = bank - ? WHERE identifier = ?");
            $stmtPay->execute([$lotto['ticket_price'], $user_identifier]);

            // C. Créer le ticket
            $stmtInsert = $pdo->prepare("INSERT INTO website_lotto_tickets (lotto_id, identifier) VALUES (?, ?)");
            $stmtInsert->execute([$lotto['id'], $user_identifier]);
            
            // D. Augmenter la cagnotte
            $stmtUpdatePot = $pdo->prepare("UPDATE website_lotto SET jackpot_current = jackpot_current + ? WHERE id = ?");
            $stmtUpdatePot->execute([$lotto['ticket_price'], $lotto['id']]);
            
            // Refresh pour voir le changement
            header("Refresh:0");
            // Note : Le message ne s'affichera pas à cause du header refresh, 
            // mais le joueur verra son argent baisser et la cagnotte monter.
        } else {
            $message = "❌ Tu n'as pas assez d'argent en banque (" . number_format($player_money) . "$).";
        }
    } else {
        $message = "❌ Ton personnage est introuvable. Connecte-toi au moins une fois en jeu avec ce Discord.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Loto Los Santos</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .lotto-container { text-align: center; color: white; padding: 50px; background: rgba(0,0,0,0.5); border-radius: 20px; margin-top: 50px; border: 1px solid rgba(255,255,255,0.1); }
        .jackpot-display { font-size: 4rem; font-weight: 900; color: #ffd700; text-shadow: 0 0 20px rgba(255, 215, 0, 0.6); margin: 20px 0; }
        .buy-btn { background: #28a745; color: white; padding: 15px 40px; font-size: 1.5rem; border: none; border-radius: 50px; cursor: pointer; transition: 0.3s; margin-top:20px; font-weight:bold;}
        .buy-btn:hover { transform: scale(1.05); box-shadow: 0 0 30px #28a745; }
        .user-info { margin-bottom: 20px; color: #aaa; font-size: 0.9rem; }
        .user-info span { color: #00aaff; font-weight: bold; }
    </style>
</head>
<body>
    
    <div style="position: absolute; top: 20px; left: 20px;">
        <a href="index.html" style="color:white; text-decoration:none;">← Retour</a>
    </div>

    <div class="container">
        <div class="lotto-container">
            <?php if ($lotto): ?>
                <div class="user-info">Connecté en tant que <span><?= htmlspecialchars($username) ?></span></div>

                <h1>🎰 <?php echo htmlspecialchars($lotto['name']); ?></h1>
                <p>Cagnotte actuelle :</p>
                <div class="jackpot-display"><?php echo number_format($lotto['jackpot_current'], 0, ',', ' '); ?> $</div>
                <p>Tirage le : <?php echo date('d/m/Y à H:i', strtotime($lotto['end_date'])); ?></p>
                
                <?php if ($message) echo "<p style='font-size:1.2rem; font-weight:bold; color:#ff4d4d; background:rgba(0,0,0,0.3); padding:10px; border-radius:10px; display:inline-block;'>$message</p>"; ?>
                
                <form method="POST">
                    <button type="submit" name="buy_ticket" class="buy-btn" onclick="return confirm('Confirmer l\'achat du ticket pour <?php echo $lotto['ticket_price']; ?> $ ?')">
                        ACHETER UN TICKET (<?php echo $lotto['ticket_price']; ?> $)
                    </button>
                </form>
                <p style="margin-top:15px; font-size:0.8rem; color:#666;">L'argent sera débité de votre compte bancaire en jeu.</p>

            <?php else: ?>
                <h1>Aucun tirage en cours.</h1>
                <p>Revenez plus tard !</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>