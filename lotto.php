<?php
session_start();
require 'system/db.php'; 

// ============================================================
// 1. SÉCURITÉ & REDIRECTION
// ============================================================
// Si le joueur n'est pas connecté, on le redirige vers la connexion Discord
if (!isset($_SESSION['discord_user'])) {
    $_SESSION['redirect_url'] = 'https://losvegas.cloud/lotto.php'; // On retient où il voulait aller
    header('Location: dashboard/auth.php');
    exit;
}

// Configuration des IDs
$discord_id_raw = $_SESSION['discord_user']['id']; 
$discord_db_format = "discord:" . $discord_id_raw; // Format stocké en DB par le script FiveM
$username = $_SESSION['discord_user']['username'];

// On utilise la base de données du JEU (FiveM)
$pdo = $pdoFiveM; 

// ============================================================
// 2. RÉCUPÉRATION DES DONNÉES (Loto & Personnages)
// ============================================================

// A. Récupérer le loto actif
$stmt = $pdo->query("SELECT * FROM website_lotto WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$lotto = $stmt->fetch();
$message = "";

// B. Récupérer les personnages liés à ce Discord
// On sélectionne l'identifier, le nom, et l'argent (accounts est du JSON)
$stmtChars = $pdo->prepare("SELECT identifier, firstname, lastname, accounts FROM users WHERE discord = ?");
$stmtChars->execute([$discord_db_format]);
$characters = $stmtChars->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// 3. TRAITEMENT DE L'ACHAT
// ============================================================
if (isset($_POST['buy_ticket']) && $lotto) {
    $selected_char = $_POST['character_identifier'];
    
    // SÉCURITÉ : Vérifier que le personnage choisi appartient bien au joueur connecté
    $char_data = null;
    foreach ($characters as $c) {
        if ($c['identifier'] === $selected_char) {
            $char_data = $c;
            break;
        }
    }

    if ($char_data) {
        // --- LECTURE DU SOLDE BANCAIRE (JSON) ---
        $bank_money = 0;
        
        // On décode le JSON de la colonne 'accounts' (ex: {"bank":9000, "money":500})
        if (isset($char_data['accounts']) && !empty($char_data['accounts'])) {
            $accs = json_decode($char_data['accounts'], true);
            if (isset($accs['bank'])) {
                $bank_money = $accs['bank'];
            }
        }

        // --- VÉRIFICATION ---
        if ($bank_money >= $lotto['ticket_price']) {
            
            // --- MISE À JOUR (DÉBITER L'ARGENT) ---
            // On utilise JSON_SET pour mettre à jour uniquement la valeur 'bank' à l'intérieur du JSON
            // C'est la méthode propre pour MySQL 5.7+ et MariaDB
            $stmtPay = $pdo->prepare("UPDATE users SET accounts = JSON_SET(accounts, '$.bank', JSON_EXTRACT(accounts, '$.bank') - ?) WHERE identifier = ?");
            $success = $stmtPay->execute([$lotto['ticket_price'], $selected_char]);

            if ($success) {
                // --- CRÉATION DU TICKET ---
                $pdo->prepare("INSERT INTO website_lotto_tickets (lotto_id, identifier) VALUES (?, ?)")
                    ->execute([$lotto['id'], $selected_char]);
                
                // --- AUGMENTATION DE LA CAGNOTTE ---
                $pdo->prepare("UPDATE website_lotto SET jackpot_current = jackpot_current + ? WHERE id = ?")
                    ->execute([$lotto['ticket_price'], $lotto['id']]);
                
                // Refresh pour afficher les nouvelles valeurs
                header("Refresh:0");
                exit;
            } else {
                $message = "❌ Erreur technique lors du paiement.";
            }

        } else {
            $message = "❌ Solde bancaire insuffisant (" . number_format($bank_money, 0, ' ', ' ') . "$ disponibles).";
        }
    } else {
        $message = "❌ Tentative de fraude détectée (Personnage invalide).";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loto Los Santos - BlackWall</title>
    
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/panel.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Style spécifique pour cette page Loto */
        body {
            display: flex; flex-direction: column; min-height: 100vh;
        }
        .lotto-wrapper {
            flex: 1; display: flex; justify-content: center; align-items: center; padding: 20px;
        }
        .lotto-box {
            background: rgba(22, 22, 22, 0.95);
            border: 1px solid #333;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: 0 0 50px rgba(0,0,0,0.5);
            backdrop-filter: blur(10px);
        }
        .jackpot-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.2rem; text-transform: uppercase; color: #aaa; letter-spacing: 2px;
        }
        .jackpot-amount {
            font-family: 'Montserrat', sans-serif;
            font-size: 3.5rem; font-weight: 900; color: #ffd700;
            margin: 10px 0 30px 0;
            text-shadow: 0 0 25px rgba(255, 215, 0, 0.4);
        }
        .select-char {
            background: #0f0f11; color: white; border: 1px solid #444;
            padding: 15px; width: 100%; border-radius: 8px; font-size: 1rem; margin-top: 10px;
            outline: none; transition: 0.3s;
        }
        .select-char:focus { border-color: #00aaff; }
        .info-msg {
            margin-top: 20px; padding: 15px; border-radius: 8px; font-weight: bold;
        }
        .msg-error { background: rgba(255, 0, 0, 0.1); color: #ff4d4d; border: 1px solid rgba(255,0,0,0.2); }
        .msg-success { background: rgba(0, 255, 0, 0.1); color: #4ade80; border: 1px solid rgba(0,255,0,0.2); }
        .back-link {
            position: absolute; top: 20px; left: 20px; color: #888; text-decoration: none; font-size: 0.9rem;
        }
        .back-link:hover { color: white; }
    </style>
</head>
<body>
    
    <div class="background-image"></div>
    <div class="overlay"></div>

    <a href="index.html" class="back-link">← Retour à l'accueil</a>

    <div class="lotto-wrapper">
        <div class="lotto-box">
            
            <?php if ($lotto): ?>
                <div class="jackpot-title">Cagnotte Actuelle</div>
                <div class="jackpot-amount"><?= number_format($lotto['jackpot_current'], 0, ' ', ' ') ?> $</div>
                
                <h2 style="font-size:1.5rem; margin-bottom:10px;"><?= htmlspecialchars($lotto['name']) ?></h2>
                <p style="color:#888; margin-bottom:30px;">
                    <i class="fas fa-clock"></i> Tirage le : <?= date('d/m/Y à H:i', strtotime($lotto['end_date'])) ?>
                </p>

                <?php if ($message): ?>
                    <div class="info-msg msg-error"><?= $message ?></div>
                <?php endif; ?>

                <?php if (count($characters) > 0): ?>
                    <form method="POST" style="margin-top:30px; text-align:left;">
                        <label style="color:#ccc; font-size:0.9rem; margin-left:5px;">Sélectionnez votre personnage :</label>
                        <select name="character_identifier" class="select-char">
                            <?php foreach($characters as $c): 
                                // Calcul de l'argent depuis le JSON pour l'affichage
                                $displayMoney = 0;
                                if(isset($c['accounts'])) {
                                    $a = json_decode($c['accounts'], true);
                                    $displayMoney = $a['bank'] ?? 0;
                                }
                            ?>
                                <option value="<?= $c['identifier'] ?>">
                                    <?= htmlspecialchars($c['firstname'] . ' ' . $c['lastname']) ?> — Banque : <?= number_format($displayMoney, 0, ' ', ' ') ?> $
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" name="buy_ticket" class="btn-submit" style="width:100%; margin-top:20px; font-size:1.1rem; padding:15px;">
                            ACHETER UN TICKET (<?= number_format($lotto['ticket_price'], 0, ' ', ' ') ?> $)
                        </button>
                    </form>
                    <p style="text-align:center; font-size:0.8rem; color:#666; margin-top:15px;">
                        L'argent sera débité instantanément de votre compte bancaire en jeu.
                    </p>
                <?php else: ?>
                    <div class="info-msg msg-error" style="text-align:left;">
                        <h3 style="margin-bottom:5px;"><i class="fas fa-exclamation-triangle"></i> Personnage introuvable</h3>
                        <p style="font-size:0.9rem; font-weight:normal;">
                            Impossible de trouver vos personnages. Cela arrive si :<br>
                            1. Vous ne vous êtes jamais connecté au serveur depuis la mise à jour.<br>
                            2. Votre Steam/License n'est pas lié à ce compte Discord.
                        </p>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="padding:40px 0;">
                    <i class="fas fa-ticket-alt" style="font-size:4rem; color:#333; margin-bottom:20px;"></i>
                    <h2>Aucun loto en cours</h2>
                    <p style="color:#888;">Revenez plus tard pour le prochain tirage !</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>