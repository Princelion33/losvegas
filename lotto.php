<?php
session_start();
require 'system/db.php'; 

// IMPORTANT : Le loto utilise la base de données FiveM !
$pdo = $pdoFiveM; 
$user_identifier = "steam:110000112345678"; // À remplacer par la vraie session plus tard

$stmt = $pdo->query("SELECT * FROM website_lotto WHERE status = 'active' ORDER BY id DESC LIMIT 1");
$lotto = $stmt->fetch();
$message = "";

if (isset($_POST['buy_ticket']) && $lotto) {
    // Vérification argent fictive pour l'exemple
    $player_money = 100000; 
    
    if ($player_money >= $lotto['ticket_price']) {
        $stmtInsert = $pdo->prepare("INSERT INTO website_lotto_tickets (lotto_id, identifier) VALUES (?, ?)");
        $stmtInsert->execute([$lotto['id'], $user_identifier]);
        
        $stmtUpdatePot = $pdo->prepare("UPDATE website_lotto SET jackpot_current = jackpot_current + ? WHERE id = ?");
        $stmtUpdatePot->execute([$lotto['ticket_price'], $lotto['id']]);
        
        header("Refresh:0");
        $message = "✅ Ticket acheté !";
    } else {
        $message = "❌ Fonds insuffisants.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Loto Los Santos</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        .lotto-container { text-align: center; color: white; padding: 50px; }
        .jackpot-display { font-size: 4rem; font-weight: 900; color: #ffd700; text-shadow: 0 0 20px rgba(255, 215, 0, 0.6); margin: 20px 0; }
        .buy-btn { background: #28a745; color: white; padding: 15px 40px; font-size: 1.5rem; border: none; border-radius: 50px; cursor: pointer; transition: 0.3s; margin-top:20px;}
        .buy-btn:hover { transform: scale(1.1); box-shadow: 0 0 30px #28a745; }
    </style>
</head>
<body>
    <div class="container lotto-container">
        <?php if ($lotto): ?>
            <h1>🎰 <?php echo htmlspecialchars($lotto['name']); ?></h1>
            <p>Cagnotte actuelle :</p>
            <div class="jackpot-display"><?php echo number_format($lotto['jackpot_current'], 0, ',', ' '); ?> $</div>
            <p>Tirage le : <?php echo date('d/m/Y à H:i', strtotime($lotto['end_date'])); ?></p>
            <?php if ($message) echo "<p style='font-size:1.2rem; font-weight:bold;'>$message</p>"; ?>
            <form method="POST">
                <button type="submit" name="buy_ticket" class="buy-btn">ACHETER UN TICKET (<?php echo $lotto['ticket_price']; ?> $)</button>
            </form>
        <?php else: ?>
            <h1>Aucun tirage en cours.</h1>
        <?php endif; ?>
    </div>
</body>
</html>