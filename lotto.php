<?php
session_start();
require 'system/db.php'; 

// 1. DÉFINIR LA REDIRECTION (Pour auth.php)
if (!isset($_SESSION['discord_user'])) {
    $_SESSION['redirect_url'] = 'https://losvegas.cloud/lotto.php'; // <-- L'URL pour revenir ici
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
            <p style="color:#aaa; margin-bottom:30px;">Identifiez-vous pour accéder à vos personnages.</p>
            <a href="dashboard/auth.php" class="btn-play" style="background:#5865F2;">
                <i class="fab fa-discord"></i> Se connecter via Discord
            </a>
            <br><br>
            <a href="index.html" style="color:#666;">Retour accueil</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 2. RÉCUPÉRATION DONNÉES JOUEUR
$discord_id = $_SESSION['discord_user']['id'];
$username = $_SESSION['discord_user']['username'];
$user_identifier = "discord:" . $discord_id; 

$pdo = $pdoFiveM; // DB Jeu

// Récupérer le loto actif
$stmt = $pdo->query("SELECT * FROM website_lotto WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$lotto = $stmt->fetch();
$message = "";

// 3. RÉCUPÉRER LES PERSONNAGES (Multi-char)
// On cherche tous les users qui ont cet identifiant Discord
// Note: Si ta DB n'utilise pas 'identifier' ou 'firstname', adapte ici.
$stmtChars = $pdo->prepare("SELECT identifier, firstname, lastname, accounts FROM users WHERE identifier LIKE ?");
$stmtChars->execute(["%$discord_id%"]); // On cherche large au cas où (discord:ID ou juste ID)
$characters = $stmtChars->fetchAll(PDO::FETCH_ASSOC);

// 4. TRAITEMENT ACHAT
if (isset($_POST['buy_ticket']) && $lotto) {
    $selected_char = $_POST['character_identifier'];
    
    // Vérification de sécurité : est-ce que ce char appartient bien au mec connecté ?
    $is_owner = false;
    foreach ($characters as $c) {
        if ($c['identifier'] === $selected_char) {
            $is_owner = true;
            // Récupérer l'argent (Gestion accounts JSON ou colonne bank)
            $money = 0;
            // Cas 1 : Colonne 'bank' directe
            if (isset($c['bank'])) { 
                $money = $c['bank']; 
            } 
            // Cas 2 : JSON 'accounts' (ESX moderne)
            elseif (isset($c['accounts'])) {
                $accs = json_decode($c['accounts'], true);
                $money = $accs['bank'] ?? 0;
            }
            break;
        }
    }

    if ($is_owner) {
        if ($money >= $lotto['ticket_price']) {
            // A. Retirer l'argent
            // Note: Mettre à jour du JSON en SQL pur est chiant, ici je suppose une colonne 'bank' pour faire simple.
            // Si tu as du JSON, c'est plus complexe, dis-le moi.
            $stmtPay = $pdo->prepare("UPDATE users SET bank = bank - ? WHERE identifier = ?");
            $stmtPay->execute([$lotto['ticket_price'], $selected_char]);

            // B. Ticket + Cagnotte
            $pdo->prepare("INSERT INTO website_lotto_tickets (lotto_id, identifier) VALUES (?, ?)")
                ->execute([$lotto['id'], $selected_char]);
            
            $pdo->prepare("UPDATE website_lotto SET jackpot_current = jackpot_current + ? WHERE id = ?")
                ->execute([$lotto['ticket_price'], $lotto['id']]);
            
            header("Refresh:0");
        } else {
            $message = "❌ Ce personnage n'a pas assez d'argent en banque.";
        }
    } else {
        $message = "❌ Erreur de sécurité : Personnage invalide.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Loto Los Santos</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/panel.css"> <style>
        .lotto-box { 
            background: #161616; border: 1px solid #333; border-radius: 20px; 
            padding: 40px; max-width: 600px; margin: 50px auto; text-align: center;
        }
        .jackpot { font-size: 3.5rem; color: #ffd700; font-weight: 900; margin: 10px 0; text-shadow: 0 0 20px rgba(255,215,0,0.4); }
        select { background: #222; color: white; padding: 15px; border: 1px solid #444; width: 100%; border-radius: 8px; font-size: 1rem; margin-top: 10px;}
    </style>
</head>
<body>
    <div style="padding:20px;"><a href="index.html" style="color:#888;">← Retour</a></div>

    <div class="lotto-box">
        <?php if ($lotto): ?>
            <h1 style="text-transform:uppercase;"><?= htmlspecialchars($lotto['name']) ?></h1>
            <p>Fin du tirage : <?= date('d/m à H:i', strtotime($lotto['end_date'])) ?></p>
            
            <div class="jackpot"><?= number_format($lotto['jackpot_current'], 0, ' ', ' ') ?> $</div>

            <?php if ($message) echo "<p style='color:#ff4d4d; background:rgba(255,0,0,0.1); padding:10px; border-radius:5px;'>$message</p>"; ?>

            <?php if (count($characters) > 0): ?>
                <form method="POST" style="margin-top:30px;">
                    <label style="display:block; text-align:left; color:#aaa; margin-bottom:5px;">Choisir le personnage qui paye :</label>
                    <select name="character_identifier">
                        <?php foreach($characters as $c): 
                            // Tentative d'affichage propre de l'argent
                            $moneyDisplay = isset($c['bank']) ? $c['bank'] : '?';
                        ?>
                            <option value="<?= $c['identifier'] ?>">
                                <?= htmlspecialchars($c['firstname'] . ' ' . $c['lastname']) ?> (Banque: <?= $moneyDisplay ?> $)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" name="buy_ticket" class="btn-submit" style="width:100%; margin-top:20px; font-size:1.2rem;">
                        ACHETER UN TICKET (<?= $lotto['ticket_price'] ?> $)
                    </button>
                </form>
            <?php else: ?>
                <div style="background:rgba(255,0,0,0.1); padding:20px; border-radius:10px; margin-top:20px; text-align:left;">
                    <h3 style="color:#ff4d4d;"><i class="fas fa-exclamation-triangle"></i> Aucun personnage trouvé</h3>
                    <p style="font-size:0.9rem; color:#ccc;">
                        Nous n'avons trouvé aucun personnage lié à ton Discord (<code><?= $discord_id ?></code>).<br><br>
                        <strong>Pourquoi ?</strong><br>
                        1. Tu ne t'es jamais connecté au serveur avec ce compte Discord.<br>
                        2. Le serveur n'a pas lié ton Steam à ton Discord.<br>
                        3. La base de données ne stocke pas les IDs Discord.
                    </p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <h2>Aucun Loto en cours.</h2>
        <?php endif; ?>
    </div>
</body>
</html>