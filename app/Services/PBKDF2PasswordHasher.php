<?php

namespace App\Services;

use Exception;

class PBKDF2PasswordHasher
{
    /**
     * Nombre d'itérations par défaut pour PBKDF2
     */
    private int $iterations;
    
    /**
     * Longueur du sel en octets (32 octets = 256 bits)
     */
    private int $saltLength = 32;
    
    /**
     * Longueur de sortie du hachage en octets (64 octets = 512 bits)
     */
    private int $hashLength = 64;
    
    /**
     * Algorithme de hachage à utiliser
     */
    private string $algorithm = 'sha256';
    
    /**
     * Constructeur
     * 
     * @param int $iterations Nombre d'itérations PBKDF2 (défaut: 100 000)
     */
    public function __construct(int $iterations = 100000)
    {
        $this->iterations = $iterations;
    }
    
    /**
     * Hacher un mot de passe en utilisant PBKDF2
     * 
     * @param string $password Le mot de passe en texte clair à hacher
     * @return array Tableau contenant le sel, le hachage et les itérations
     * @throws Exception Si la génération d'octets aléatoires échoue
     */
    public function hash(string $password): array
    {
        // Générer un sel cryptographiquement sécurisé
        $salt = $this->generateSalt();
        
        // Appliquer PBKDF2 avec plusieurs itérations
        $hash = $this->pbkdf2($password, $salt, $this->iterations);
        
        return [
            'salt' => base64_encode($salt),
            'hash' => base64_encode($hash),
            'iterations' => $this->iterations,
            'algorithm' => $this->algorithm
        ];
    }
    
    /**
     * Vérifier un mot de passe contre les données de hachage stockées
     * 
     * @param string $password Le mot de passe en texte clair à vérifier
     * @param string $storedSalt Sel encodé en Base64
     * @param string $storedHash Hachage encodé en Base64
     * @param int $iterations Nombre d'itérations utilisées pour le hachage stocké
     * @return bool Vrai si le mot de passe correspond, faux sinon
     */
    public function verify(string $password, string $storedSalt, string $storedHash, int $iterations): bool
    {
        try {
            // Décoder le sel et le hachage stockés
            $salt = base64_decode($storedSalt, true);
            $expectedHash = base64_decode($storedHash, true);
            
            // Valider les données décodées
            if ($salt === false || $expectedHash === false) {
                return false;
            }
            
            // Recalculer le hachage avec les mêmes paramètres
            $computedHash = $this->pbkdf2($password, $salt, $iterations);
            
            // Comparaison sécurisée temporellement pour prévenir les attaques de timing
            return hash_equals($expectedHash, $computedHash);
            
        } catch (Exception $e) {
            // Enregistrer l'erreur en production, retourner faux pour la sécurité
            return false;
        }
    }
    
    /**
     * Générer un sel sécurisé
     * 
     * @return string Sel binaire brut
     * @throws Exception Si la génération d'octets aléatoires échoue
     */
    private function generateSalt(): string
    {
        $salt = random_bytes($this->saltLength);
        
        if ($salt === false || strlen($salt) !== $this->saltLength) {
            throw new Exception('Échec de la génération d\'un sel sécurisé');
        }
        
        return $salt;
    }
    
    /**
     * Appliquer la fonction de dérivation de clé PBKDF2
     * 
     * @param string $password Le mot de passe à hacher
     * @param string $salt Le sel à utiliser
     * @param int $iterations Nombre d'itérations
     * @return string Hachage binaire brut
     */
    private function pbkdf2(string $password, string $salt, int $iterations): string
    {
        return hash_pbkdf2(
            $this->algorithm,
            $password,
            $salt,
            $iterations,
            $this->hashLength,
            true // Retourner des données binaires brutes
        );
    }
    
    /**
     * Obtenir la configuration actuelle
     * 
     * @return array Paramètres de configuration
     */
    public function getConfig(): array
    {
        return [
            'iterations' => $this->iterations,
            'salt_length' => $this->saltLength,
            'hash_length' => $this->hashLength,
            'algorithm' => $this->algorithm
        ];
    }
    
    /**
     * Définir le nombre d'itérations
     * 
     * @param int $iterations Nombre d'itérations (minimum 10 000)
     * @throws Exception Si le nombre d'itérations est trop faible
     */
    public function setIterations(int $iterations): void
    {
        if ($iterations < 10000) {
            throw new Exception('Les itérations doivent être d\'au moins 10 000 pour la sécurité');
        }
        
        $this->iterations = $iterations;
    }
}