<?php
session_start();
require '../system/db.php'; 

// Sécurité : Vérifier Admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

// Initialisation Variable de succès
$msg = "";

// --- ACTIONS ---

// 1. CRÉER UN LOTO
if (isset($_POST['create_lotto'])) {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $base_pot = $_POST['base_pot'];
    $days = $_POST['days'];
    $end_date = date('Y-m-d H:i:s', strtotime("+$days days"));

    // On utilise pdoFiveM car c'est lié à l'économie du jeu
    $stmt = $pdoFiveM->prepare("INSERT INTO website_lotto (name, ticket_price, jackpot_base, jackpot_current, end_date, status) VALUES (?, ?, ?, ?, ?, 'active')");
    $stmt->execute([$name, $price, $base_pot, $base_pot, $end_date]);
    $msg = "✅ Loto lancé avec succès !";
}

// 2. CLÔTURER / TIRER AU SORT (Simplifié)
if (isset($_GET['stop'])) {
    $id = $_GET['stop'];
    $pdoFiveM->prepare("UPDATE website_lotto SET status='finished' WHERE id=?")->execute([$id]);
    $msg = "⏹️ Loto arrêté.";
}

// Récupérer les Lotos
$lottos = $pdoFiveM->query("SELECT * FROM website_lotto ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion Loto - Admin</title>
    <link rel="stylesheet" href="../assets/panel.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Roboto:wght@300;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <nav class="navbar">
        <div class="brand">BlackWall <span>Loto</span></div>
        <div class="nav-actions">
            <a href="index.php"><i class="fas fa-users"></i> Gérer le Staff</a>
            <a href="../lotto.php" target="_blank"><i class="fas fa-eye"></i> Voir le Loto</a>
            <a href="logout.php" class="btn-logout"><i class="fas fa-power-off"></i></a>
        </div>
    </nav>

    <div class="container">
        
        <?php if($msg): ?>
            <div style="background:rgba(0,255,0,0.1); color:#4ade80; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid rgba(0,255,0,0.2);">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <div class="card-header" style="margin-bottom:20px; border-bottom:1px solid #333; padding-bottom:10px;">
                <h3 style="color:white;"><i class="fas fa-ticket-alt" style="color:#00aaff; margin-right:10px;"></i> Lancer un nouveau Tirage</h3>
            </div>
            
            <form method="POST">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Nom de l'événement</label>
                        <input type="text" name="name" placeholder="Ex: Grand Loto Semaine 1" required>
                    </div>

                    <div class="input-group">
                        <label>Prix du Ticket ($)</label>
                        <input type="number" name="price" value="500" required>
                    </div>

                    <div class="input-group">
                        <label>Cagnotte de Départ ($)</label>
                        <input type="number" name="base_pot" value="50000" required>
                    </div>

                    <div class="input-group">
                        <label>Durée</label>
                        <select name="days">
                            <option value="1">24 Heures</option>
                            <option value="3">3 Jours</option>
                            <option value="7" selected>1 Semaine</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="create_lotto" class="btn-submit">Lancer le Loto</button>
                <div style="clear:both;"></div>
            </form>
        </div>

        <h2 class="section-title" style="font-size:1.5rem;"><i class="fas fa-history"></i> Historique</h2>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Cagnotte</th>
                        <th>Fin</th>
                        <th>Statut</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($lottos as $l): ?>
                    <tr>
                        <td>#<?= $l['id'] ?></td>
                        <td><?= htmlspecialchars($l['name']) ?></td>
                        <td style="color:#ffd700; font-weight:bold;"><?= number_format($l['jackpot_current'], 0, ' ', ' ') ?> $</td>
                        <td><?= date('d/m H:i', strtotime($l['end_date'])) ?></td>
                        <td>
                            <?php if($l['status'] == 'active'): ?>
                                <span class="badge badge-online">EN COURS</span>
                            <?php else: ?>
                                <span class="badge badge-vacant">TERMINÉ</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <?php if($l['status'] == 'active'): ?>
                                <a href="?stop=<?= $l['id'] ?>" class="btn-icon btn-del" onclick="return confirm('Arrêter ce loto ?')" title="Arrêter"><i class="fas fa-stop"></i></a>
                            <?php else: ?>
                                <span style="color:#666;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>