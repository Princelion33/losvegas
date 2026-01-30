<?php
require 'system/db.php';

try {
    $sql = "SELECT * FROM staff_members 
            ORDER BY 
            FIELD(category, 'direction', 'gestion', 'admin', 'commu'), 
            FIELD(role, 'Fondateur', 'Développeur', 'Développeuse Discord', 'Manager', 'Gérant Staff', 'Gérant Légal', 'Gérant Illégal', 'Gérant Événementiel', 'Gérant Streamer', 'Super Admin', 'Administrateur', 'Modérateur', 'Helpeur', 'Community Manager', 'Support Discord'),
            priority ASC,
            name ASC";
            
    $stmt = $pdo->query($sql);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Erreur SQL : " . $e->getMessage());
}

$roleIcons = [
    'Fondateur' => 'fa-chess-king',
    'Développeur' => 'fa-code',
    'Développeuse Discord' => 'fa-discord',
    'Manager' => 'fa-user-tie',
    'Gérant Staff' => 'fa-users-cog',
    'Gérant Légal' => 'fa-balance-scale',
    'Gérant Illégal' => 'fa-skull',
    'Gérant Événementiel' => 'fa-calendar-alt',
    'Gérant Streamer' => 'fa-video',
    'Super Admin' => 'fa-user-shield',
    'Administrateur' => 'fa-tools',
    'Modérateur' => 'fa-gavel',
    'Helpeur' => 'fa-hands-helping',
    'Community Manager' => 'fa-comments',
    'Support Discord' => 'fa-headset'
];

$org = ['direction' => [], 'gestion' => [], 'admin' => [], 'commu' => []];

foreach ($members as $m) {
    $cat = $m['category'];
    $role = $m['role'];
    if (isset($org[$cat])) {
        if (!isset($org[$cat][$role])) { $org[$cat][$role] = []; }
        $org[$cat][$role][] = $m;
    }
}

function renderSection($title, $icon, $data, $iconsMap) {
    if (empty($data)) return;
    echo '<section class="staff-section">';
    echo '<h2 class="section-title"><i class="fas '.$icon.'"></i> '.$title.'</h2>';
    echo '<div class="card-grid">';
    foreach ($data as $roleName => $people) {
        $faIcon = isset($iconsMap[$roleName]) ? $iconsMap[$roleName] : 'fa-user';
        $faPrefix = strpos($faIcon, 'discord') !== false ? 'fab' : 'fas';
        
        echo '<div class="card">';
        echo '  <div class="card-header"><i class="'.$faPrefix.' '.$faIcon.'"></i><h3>'.htmlspecialchars($roleName).'</h3></div>';
        echo '  <ul>';
        foreach ($people as $person) {
            $class = $person['is_available'] ? 'disponible' : '';
            echo '<li class="'.$class.'">'.htmlspecialchars($person['name']).'</li>';
        }
        echo '  </ul></div>';
    }
    echo '</div></section>';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hiérarchie Staff - BlackWall FA</title>
    <link rel="stylesheet" href="assets/panel.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        
        <header class="header">
            <h1>Hiérarchie Staff <span class="accent">BlackWall FA</span></h1>
            <p class="subtitle">L'équipe qui fait vivre votre expérience RP</p>
            <a href="index.html" style="color: #666; margin-top:20px; display:inline-block; font-size:0.9rem;">← Retour à l'accueil</a>
        </header>

        <?php 
            renderSection('Direction & Développement', 'fa-crown', $org['direction'], $roleIcons);
            renderSection('Gestion & Pôles', 'fa-briefcase', $org['gestion'], $roleIcons);
            renderSection('Administration & Modération', 'fa-cogs', $org['admin'], $roleIcons);
            renderSection('Communauté', 'fa-bullhorn', $org['commu'], $roleIcons);
        ?>
        
        <footer class="footer">
            <p>&copy; 2025 BlackWall FA. Tous droits réservés.</p>
        </footer>
    </div> 
</body>
</html>