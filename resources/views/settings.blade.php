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
        

        
        .admin-link {
            display: block;
            padding: 12px 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #333 !important;
            text-decoration: none;
            margin-bottom: 10px;
        }
        
        .admin-link:hover {
            background-color: #e9ecef;
            border-color: #2196F3;
        }
        
        .admin-link strong {
            display: block;
            font-size: 14px;
            font-weight: bold;
            color: #333;
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
        @if(auth()->user()->role->name === 'Administrateur')
            <div class="admin-section" style="margin-bottom: 30px;">
                <h2 style="color: #333; margin-bottom: 15px; font-size: 18px;">Administration</h2>
                <div class="admin-links">
                    <a href="{{ route('admin.security-config') }}" class="admin-link">
                        <strong>Configuration de sécurité</strong>
                    </a>
                    <a href="{{ route('admin.users') }}" class="admin-link">
                        <strong>Gestion des utilisateurs</strong>
                    </a>
                    <a href="{{ route('admin.audit-logs') }}" class="admin-link">
                        <strong>Journaux d'audit</strong>
                    </a>
                    <a href="{{ route('admin.audit-statistics') }}" class="admin-link">
                        <strong>Statistiques d'audit</strong>
                    </a>
                </div>
            </div>
        @endif

    </div>
</body>
</html>
