<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CleanDuplicateRoles extends Command
{
    protected $signature = 'clean:duplicate-roles {--dry-run : Show what would be done without making changes}';
    protected $description = 'Clean duplicate roles and fix role assignments';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 Mode simulation - aucune modification ne sera apportée');
        } else {
            $this->info('🧹 Nettoyage des rôles dupliqués...');
        }

        try {
            // Vérifier si la table roles existe
            if (!DB::getSchemaBuilder()->hasTable('roles')) {
                $this->error('La table "roles" n\'existe pas.');
                return 1;
            }

            // Définir les rôles standards
            $standardRoles = [
                'Administrateur' => 'Administrateur avec accès complet',
                'Préposé aux clients résidentiels' => 'Gestionnaire des clients résidentiels',
                'Préposé aux clients d\'affaire' => 'Gestionnaire des clients d\'affaire'
            ];

            $this->info("\n📋 Analyse des rôles existants...");
            
            // Analyser les rôles existants
            $existingRoles = Role::all();
            $this->info("Nombre total de rôles : " . $existingRoles->count());

            foreach ($existingRoles as $role) {
                $userCount = User::where('role_id', $role->id)->count();
                $this->line("- ID: {$role->id} | Nom: '{$role->name}' | Utilisateurs: {$userCount}");
            }

            // Chercher les doublons potentiels
            $duplicates = [];
            foreach ($standardRoles as $standardName => $description) {
                $matches = $existingRoles->filter(function ($role) use ($standardName) {
                    // Normaliser les noms pour la comparaison
                    $roleName = str_replace(['\\', "'"], ["'", "'"], $role->name);
                    $standardNameNorm = str_replace(['\\', "'"], ["'", "'"], $standardName);
                    return $roleName === $standardNameNorm || $role->name === $standardName;
                });

                if ($matches->count() > 1) {
                    $duplicates[$standardName] = $matches;
                }
            }

            if (empty($duplicates)) {
                $this->info("✅ Aucun doublon détecté.");
                return 0;
            }

            // Traiter les doublons
            foreach ($duplicates as $standardName => $roles) {
                $this->warn("\n⚠️  Doublons trouvés pour '{$standardName}':");
                
                // Trier par ID (garder le plus ancien)
                $sortedRoles = $roles->sortBy('id');
                $keepRole = $sortedRoles->first();
                $duplicateRoles = $sortedRoles->skip(1);

                $this->info("  Garder: ID {$keepRole->id} - '{$keepRole->name}'");
                
                foreach ($duplicateRoles as $duplicateRole) {
                    $userCount = User::where('role_id', $duplicateRole->id)->count();
                    $this->line("  Supprimer: ID {$duplicateRole->id} - '{$duplicateRole->name}' ({$userCount} utilisateurs)");
                    
                    if (!$dryRun) {
                        // Réassigner les utilisateurs au rôle principal
                        if ($userCount > 0) {
                            User::where('role_id', $duplicateRole->id)
                                ->update(['role_id' => $keepRole->id]);
                            $this->info("    → {$userCount} utilisateur(s) réassigné(s)");
                        }
                        
                        // Supprimer le rôle dupliqué
                        $duplicateRole->delete();
                        $this->info("    → Rôle supprimé");
                    }
                }
            }

            // Créer les rôles manquants
            $this->info("\n🔧 Vérification des rôles standards...");
            foreach ($standardRoles as $name => $description) {
                $exists = Role::where('name', $name)->exists();
                if (!$exists) {
                    $this->warn("Rôle manquant: '{$name}'");
                    if (!$dryRun) {
                        Role::create([
                            'name' => $name,
                            'description' => $description
                        ]);
                        $this->info("  → Rôle créé");
                    }
                }
            }

            // Vérifier les utilisateurs sans rôle valide
            $this->info("\n👥 Vérification des utilisateurs...");
            $usersWithoutRole = User::whereNull('role_id')
                ->orWhereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('roles')
                        ->whereRaw('roles.id = users.role_id');
                })->get();

            if ($usersWithoutRole->count() > 0) {
                $this->warn("Utilisateurs sans rôle valide: " . $usersWithoutRole->count());
                $defaultRole = Role::where('name', 'Préposé aux clients résidentiels')->first();
                
                if ($defaultRole && !$dryRun) {
                    foreach ($usersWithoutRole as $user) {
                        $user->update(['role_id' => $defaultRole->id]);
                        $this->info("  → {$user->name} assigné au rôle par défaut");
                    }
                }
            } else {
                $this->info("✅ Tous les utilisateurs ont un rôle valide");
            }

            if ($dryRun) {
                $this->info("\n💡 Exécutez sans --dry-run pour appliquer les changements");
            } else {
                $this->info("\n✅ Nettoyage terminé avec succès!");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Erreur: ' . $e->getMessage());
            return 1;
        }
    }
}