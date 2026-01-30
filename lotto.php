<?php
session_start();
require 'system/db.php'; 

// 1. REDIRECTION SI NON CONNECTÉ
if (!isset($_SESSION['discord_user'])) {
    $_SESSION['redirect_url'] = 'https://losvegas.cloud/lotto.php'; 
    header('Location: dashboard/auth.php');
    exit;
}

$discord_id_raw = $_SESSION['discord_user']['id']; 
// Le format en DB sera "discord:123456..." grâce au script FiveM
$discord_db_format = "discord:" . $discord_id_raw;

$username = $_SESSION['discord_user']['username'];
$pdo = $pdoFiveM; 

// Récupérer le loto actif
$stmt = $pdo->query("SELECT * FROM website_lotto WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$lotto = $stmt->fetch();
$message = "";

// 2. RÉCUPÉRER LES PERSONNAGES VIA LA NOUVELLE COLONNE 'discord'
// C'est ici que la magie opère : on cherche par la colonne qu'on vient de créer
$stmtChars = $pdo->prepare("SELECT identifier, firstname, lastname, accounts, bank FROM users WHERE discord = ?");
$stmtChars->execute([$discord_db_format]);
$characters = $stmtChars->fetchAll(PDO::FETCH_ASSOC);

// Si on ne trouve rien avec "discord:ID", on essaie juste l'ID (au cas où)
if (empty($characters)) {
    $stmtChars->execute([$discord_id_raw]);
    $characters = $stmtChars->fetchAll(PDO::FETCH_ASSOC);
}

// 3. TRAITEMENT ACHAT
if (isset($_POST['buy_ticket']) && $lotto) {
    $selected_char = $_POST['character_identifier'];
    
    // Vérif sécurité : le perso appartient bien au joueur connecté ?
    $char_data = null;
    foreach ($characters as $c) {
        if ($c['identifier'] === $selected_char) {
            $char_data = $c;
            break;
        }
    }

    if ($char_data) {
        // Gestion Argent (Compatible ESX Legacy JSON et Old ESX)
        $money = 0;
        if (isset($char_data['accounts']) && !empty($char_data['accounts'])) {
            // Décodage JSON pour ESX récent
            $accs = json_decode($char_data['accounts'], true);
            $money = (isset($accs['bank'])) ? $accs['bank'] : 0;
        } elseif (isset($char_data['bank'])) {
            // Ancienne méthode colonne directe
            $money = $char_data['bank'];
        }

        if ($money >= $lotto['ticket_price']) {
            // A. Retirer l'argent (Mise à jour complexe selon JSON ou colonne)
            // On fait simple : si colonne bank existe, on l'utilise. Sinon JSON.
            // POUR ESX STANDARD (colonne bank souvent utilisée pour compatibilité) :
            
            // Note : Mettre à jour du JSON en SQL pur est risqué. 
            // Si tu utilises ESX Legacy, l'argent est souvent aussi synchronisé dans une colonne ou géré autrement.
            // Ici, je tente la mise à jour colonne 'bank' qui est le plus courant pour les sites.
            $stmtPay = $pdo->prepare("UPDATE users SET bank = bank - ? WHERE identifier = ?");
            $stmtPay->execute([$lotto['ticket_price'], $selected_char]);

            // B. Enregistrer le ticket
            $pdo->prepare("INSERT INTO website_lotto_tickets (lotto_id, identifier) VALUES (?, ?)")
                ->execute([$lotto['id'], $selected_char]);
            
            // C. Augmenter la cagnotte
            $pdo->prepare("UPDATE website_lotto SET jackpot_current = jackpot_current + ? WHERE id = ?")
                ->execute([$lotto['ticket_price'], $lotto['id']]);
            
            header("Refresh:0");
        } else {
            $message = "❌ Solde bancaire insuffisant sur ce personnage.";
        }
    } else {
        $message = "❌ Erreur : Personnage invalide.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Loto Los Santos</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/panel.css">
    <style>
        .lotto-box { background: #161616; border: 1px solid #333; border-radius: 20px; padding: 40px; max-width: 600px; margin: 50px auto; text-align: center; }
        .jackpot { font-size: 3.5rem; color: #ffd700; font-weight: 900; margin: 10px 0; text-shadow: 0 0 20px rgba(255,215,0,0.4); }
        select { background: #222; color: white; padding: 15px; border: 1px solid #444; width: 100%; border-radius: 8px; font-size: 1rem; margin-top: 10px;}
        .char-option { padding: 10px; border-bottom: 1px solid #333; }
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
                    <label style="display:block; text-align:left; color:#aaa; margin-bottom:5px;">Choisir le personnage :</label>
                    <select name="character_identifier">
                        <?php foreach($characters as $c): 
                             // Affichage intelligent de l'argent
                             $displayMoney = 0;
                             if(isset($c['bank'])) $displayMoney = $c['bank'];
                             elseif(isset($c['accounts'])) {
                                 $a = json_decode($c['accounts'], true);
                                 $displayMoney = $a['bank'] ?? 0;
                             }
                        ?>
                            <option value="<?= $c['identifier'] ?>">
                                <?= htmlspecialchars($c['firstname'] . ' ' . $c['lastname']) ?> (Banque: <?= number_format($displayMoney) ?> $)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="buy_ticket" class="btn-submit" style="width:100%; margin-top:20px;">ACHETER (<?= $lotto['ticket_price'] ?> $)</button>
                </form>
            <?php else: ?>
                <div style="margin-top:20px; color:#ff4d4d;">
                    <h3>Aucun personnage trouvé !</h3>
                    <p style="font-size:0.9rem; color:#ccc;">Connecte-toi au serveur FiveM une fois pour lier ton Discord.</p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <h2>Aucun Loto en cours.</h2>
        <?php endif; ?>
    </div>
</body>
</html>