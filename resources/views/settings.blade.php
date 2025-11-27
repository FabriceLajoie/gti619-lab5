<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
        }
        
        .navbar {
            background-color: #333;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar h1 {
            color: white;
            font-size: 20px;
        }
        
        .back-btn, .logout-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .back-btn {
            background-color: #2196F3;
            color: white;
            margin-right: 10px;
        }
        
        .back-btn:hover {
            background-color: #0b7dda;
        }
        
        .logout-btn {
            background-color: #d32f2f;
            color: white;
        }
        
        .logout-btn:hover {
            background-color: #b71c1c;
        }
        
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        h2 {
            margin-bottom: 30px;
            color: #333;
        }
        
        .settings-box {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .setting-item {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .setting-item:last-child {
            border-bottom: none;
        }
        
        .setting-item h3 {
            font-size: 16px;
            margin-bottom: 8px;
            color: #333;
        }
        
        .setting-item p {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Paramètres</h1>
        <div>
            <a href="{{ route('dashboard') }}" class="back-btn">← Retour</a>
            <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                @csrf
                <button type="submit" class="logout-btn">Déconnexion</button>
            </form>
        </div>
    </div>

    <div class="container">
        <div class="settings-box">
            <div class="setting-item">
                <h3>Profil</h3>
                <p>Gérez vos informations de profil et vos préférences personnelles.</p>
            </div>
            
            <div class="setting-item">
                <h3>Sécurité</h3>
                <p>Modifiez votre mot de passe et gérez les paramètres de sécurité de votre compte.</p>
            </div>
            
            <div class="setting-item">
                <h3>Notifications</h3>
                <p>Configurez les préférences de notifications de l'application.</p>
            </div>
            
            <div class="setting-item">
                <h3>À propos</h3>
                <p>Version 1.0 - Système de gestion des clients</p>
            </div>
        </div>
    </div>
</body>
</html>
