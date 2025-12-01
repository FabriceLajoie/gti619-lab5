<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord</title>
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
        
        .logout-btn {
            background-color: #d32f2f;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .logout-btn:hover {
            background-color: #b71c1c;
        }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        h2 {
            text-align: center;
            margin-bottom: 40px;
            color: #333;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }
        
        .card h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
        }
        
        .card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .card a {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .card a:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Tableau de bord</h1>
        <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
            @csrf
            <button type="submit" class="logout-btn">Déconnexion</button>
        </form>
    </div>

    <div class="container">
        <h2>Bienvenue au tableau de bord</h2>
        
        <div class="dashboard-grid">
            @if(Auth::user()->hasRole('Administrateur'))
                <div class="card">
                    <h3>Paramètres</h3>
                    <p>Gérez vos paramètres de compte et les préférences de l'application.</p>
                    <a href="{{ route('settings') }}">Accéder</a>
                </div>
                
                <div class="card">
                    <h3>Clients résidentiels</h3>
                    <p>Consultez et gérez la liste des clients résidentiels.</p>
                    <a href="{{ route('clients.residential') }}">Accéder</a>
                </div>
                
                <div class="card">
                    <h3>Clients d'affaires</h3>
                    <p>Consultez et gérez la liste des clients d'affaires.</p>
                    <a href="{{ route('clients.business') }}">Accéder</a>
                </div>
            @elseif(Auth::user()->hasRole('Préposé aux clients résidentiels'))
                <div class="card">
                    <h3>Clients résidentiels</h3>
                    <p>Consultez et gérez la liste des clients résidentiels.</p>
                    <a href="{{ route('clients.residential') }}">Accéder</a>
                </div>
            @elseif(Auth::user()->hasRole('Préposé aux clients d\'affaire'))
                <div class="card">
                    <h3>Clients d'affaires</h3>
                    <p>Consultez et gérez la liste des clients d'affaires.</p>
                    <a href="{{ route('clients.business') }}">Accéder</a>
                </div>
            @else
                <div class="card">
                    <h3>Accès limité</h3>
                    <p>Votre compte n'a pas de rôle assigné. Contactez l'administrateur.</p>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
