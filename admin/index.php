<?php
session_start();
require '../system/db.php'; 

// Sécurité
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

$editMode = false;
$memberToEdit = null;

// --- ACTIONS PHP ---

// Supprimer
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM staff_members WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header('Location: index.php');
    exit;
}

// Préparer l'édition
if (isset($_GET['edit'])) {
    $editMode = true;
    $stmt = $pdo->prepare("SELECT * FROM staff_members WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $memberToEdit = $stmt->fetch();
}

// Sauvegarder (Ajout ou Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $role = $_POST['role'];
    $category = $_POST['category'];
    $priority = (int)$_POST['priority'];
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    if (isset($_POST['update_id'])) {
        $stmt = $pdo->prepare("UPDATE staff_members SET name=?, role=?, category=?, priority=?, is_available=? WHERE id=?");
        $stmt->execute([$name, $role, $category, $priority, $is_available, $_POST['update_id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO staff_members (name, role, category, priority, is_available) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $role, $category, $priority, $is_available]);
    }
    header('Location: index.php');
    exit;
}

// --- REQUÊTE DE TRI ---
$sql = "SELECT * FROM staff_members 
        ORDER BY 
        FIELD(category, 'direction', 'gestion', 'admin', 'commu'), 
        FIELD(role, 'Fondateur', 'Développeur', 'Développeuse Discord', 'Manager', 'Gérant Staff', 'Gérant Légal', 'Gérant Illégal', 'Gérant Événementiel', 'Gérant Streamer', 'Super Admin', 'Administrateur', 'Modérateur', 'Helpeur', 'Community Manager', 'Support Discord'),
        priority ASC, 
        name ASC";

$members = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Noms jolis pour les catégories
$catNames = [
    'direction' => '👑 Direction & Développement', 
    'gestion' => '💼 Gestion & Pôles', 
    'admin' => '🛠️ Administration', 
    'commu' => '📢 Communauté'
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Staff - Admin</title>
    <link rel="stylesheet" href="../assets/panel.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Roboto:wght@300;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <nav class="navbar">
        <div class="brand">BlackWall <span>Admin</span></div>
        <div class="nav-actions">
            <a href="admin_lotto.php"><i class="fas fa-ticket-alt"></i> Gérer le Loto</a>
            <a href="permissions.php"><i class="fas fa-lock"></i> Permissions</a>
            <a href="../staff.php" target="_blank"><i class="fas fa-eye"></i> Voir le site</a>
            <a href="logout.php" class="btn-logout"><i class="fas fa-power-off"></i></a>
        </div>
    </nav>

    <div class="container">
        
        <div class="admin-card">
            <div class="card-header" style="margin-bottom:20px; border-bottom:1px solid #333; padding-bottom:10px;">
                <h3 style="color:white;">
                    <i class="fas <?= $editMode ? 'fa-pen' : 'fa-plus-circle' ?>" style="color:#00aaff; margin-right:10px;"></i>
                    <?= $editMode ? 'Modifier le membre' : 'Ajouter un membre' ?>
                </h3>
            </div>
            
            <form method="POST">
                <?php if($editMode): ?> <input type="hidden" name="update_id" value="<?= $memberToEdit['id'] ?>"> <?php endif; ?>
                
                <div class="form-grid">
                    <div class="input-group">
                        <label>Nom ou Mention</label>
                        <input type="text" name="name" placeholder="Ex: Steve Camusa" value="<?= $editMode ? htmlspecialchars($memberToEdit['name']) : '' ?>" required>
                    </div>

                    <div class="input-group">
                        <label>Rôle (Grade)</label>
                        <input type="text" name="role" placeholder="Ex: Fondateur" list="rolesList" value="<?= $editMode ? htmlspecialchars($memberToEdit['role']) : '' ?>" required>
                        <datalist id="rolesList">
                            <option value="Fondateur"><option value="Développeur"><option value="Manager"><option value="Gérant Staff"><option value="Administrateur"><option value="Modérateur">
                        </datalist>
                    </div>

                    <div class="input-group">
                        <label>Catégorie</label>
                        <select name="category">
                            <option value="direction" <?= ($editMode && $memberToEdit['category'] == 'direction') ? 'selected' : '' ?>>👑 Direction</option>
                            <option value="gestion" <?= ($editMode && $memberToEdit['category'] == 'gestion') ? 'selected' : '' ?>>💼 Gestion</option>
                            <option value="admin" <?= ($editMode && $memberToEdit['category'] == 'admin') ? 'selected' : '' ?>>🛠️ Administration</option>
                            <option value="commu" <?= ($editMode && $memberToEdit['category'] == 'commu') ? 'selected' : '' ?>>📢 Communauté</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Priorité (1 = Haut)</label>
                        <input type="number" name="priority" value="<?= $editMode ? $memberToEdit['priority'] : '50' ?>" min="1" max="999">
                    </div>
                </div>

                <div class="checkbox-wrapper">
    <label class="custom-checkbox">
        <input type="checkbox" name="is_available" <?= ($editMode && $memberToEdit['is_available']) ? 'checked' : '' ?>>
        <span class="checkmark"></span>
        <span style="color:#aaa; font-size:0.95rem;">Marquer comme "Poste Vacant"</span>
    </label>
</div>

                <button type="submit" class="btn-submit"><?= $editMode ? 'Modifier' : 'Ajouter au staff' ?></button>
                <?php if($editMode): ?>
                    <a href="index.php" style="float:right; margin-top:20px; margin-right:15px; color:#666; text-decoration:none;">Annuler</a>
                <?php endif; ?>
                <div style="clear:both;"></div>
            </form>
        </div>

        <h2 class="section-title" style="font-size:1.5rem;"><i class="fas fa-list"></i> Liste des Membres</h2>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th width="60">#</th>
                        <th>Nom</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $lastCat = '';
                    foreach($members as $m): 
                        // Séparateur de catégorie
                        if($m['category'] != $lastCat):
                            echo '<tr><td colspan="5" style="background:#222; color:#00aaff; font-weight:bold; letter-spacing:1px; font-size:0.9rem;">'.($catNames[$m['category']] ?? strtoupper($m['category'])).'</td></tr>';
                            $lastCat = $m['category'];
                        endif;
                    ?>
                    <tr>
                        <td><span class="priority-pill"><?= $m['priority'] ?></span></td>
                        <td style="font-weight:bold;"><?= htmlspecialchars($m['name']) ?></td>
                        <td style="color:#aaa;"><?= htmlspecialchars($m['role']) ?></td>
                        <td>
                            <?php if($m['is_available']): ?>
                                <span class="badge badge-vacant">VACANT</span>
                            <?php else: ?>
                                <span class="badge badge-online">ACTIF</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <a href="index.php?edit=<?= $m['id'] ?>" class="btn-icon btn-edit" title="Éditer"><i class="fas fa-pen"></i></a>
                            <a href="index.php?delete=<?= $m['id'] ?>" class="btn-icon btn-del" onclick="return confirm('Supprimer ?')" title="Supprimer"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</body>
</html>