<?php
session_start();
$admin_password = "6-4aERy7z[x=S7"; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['is_admin'] = true;
        header('Location: index.php'); // Redirige vers index.php du dossier admin
        exit;
    } else {
        $error = "Mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Login Admin</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; height: 100vh; flex-direction: column; }
        form { background: #161616; padding: 40px; border-radius: 10px; border: 1px solid #333; text-align: center; }
        input { padding: 10px; border-radius: 5px; border: 1px solid #444; background: #222; color: white; width: 100%; margin-bottom: 20px;}
        button { background: #00aaff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <form method="POST">
        <h2 style="color:white; margin-bottom:20px;">Accès Administration</h2>
        <?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
        <input type="password" name="password" placeholder="Mot de passe" required>
        <button type="submit">Se connecter</button>
    </form>
</body>
</html>