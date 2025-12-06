<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'max_login_attempts',
        'lockout_duration_minutes',
        'password_min_length',
        'password_require_uppercase',
        'password_require_lowercase',
        'password_require_numbers',
        'password_require_special',
        'password_history_count',
        'password_expiry_days',
        'pbkdf2_iterations',
        'session_timeout_minutes'
    ];

    protected $casts = [
        'max_login_attempts' => 'integer',
        'lockout_duration_minutes' => 'integer',
        'password_min_length' => 'integer',
        'password_require_uppercase' => 'boolean',
        'password_require_lowercase' => 'boolean',
        'password_require_numbers' => 'boolean',
        'password_require_special' => 'boolean',
        'password_history_count' => 'integer',
        'password_expiry_days' => 'integer',
        'pbkdf2_iterations' => 'integer',
        'session_timeout_minutes' => 'integer'
    ];

    protected $attributes = [
        'max_login_attempts' => 5,
        'lockout_duration_minutes' => 30,
        'password_min_length' => 12,
        'password_require_uppercase' => true,
        'password_require_lowercase' => true,
        'password_require_numbers' => true,
        'password_require_special' => true,
        'password_history_count' => 5,
        'password_expiry_days' => 90,
        'pbkdf2_iterations' => 100000,
        'session_timeout_minutes' => 120
    ];

    /**
     * Obtenir l'instance singleton de configuration de sécurité
     */
    public static function getInstance()
    {
        return static::first() ?? static::create([]);
    }

    /**
     * Règles de validation pour la configuration de sécurité
     */
    public static function validationRules()
    {
        return [
            'max_login_attempts' => 'sometimes|integer|min:1|max:20',
            'lockout_duration_minutes' => 'sometimes|integer|min:1|max:1440', // Maximum 24 heures
            'password_min_length' => 'sometimes|integer|min:8|max:128',
            'password_require_uppercase' => 'sometimes|boolean',
            'password_require_lowercase' => 'sometimes|boolean',
            'password_require_numbers' => 'sometimes|boolean',
            'password_require_special' => 'sometimes|boolean',
            'password_history_count' => 'sometimes|integer|min:0|max:50',
            'password_expiry_days' => 'sometimes|integer|min:0|max:365',
            'pbkdf2_iterations' => 'sometimes|integer|min:10000|max:1000000',
            'session_timeout_minutes' => 'sometimes|integer|min:5|max:1440'
        ];
    }

    /**
     * Obtenir les messages de validation
     */
    public static function validationMessages()
    {
        return [
            'max_login_attempts.min' => 'Le nombre maximum de tentatives de connexion doit être d\'au moins 1',
            'max_login_attempts.max' => 'Le nombre maximum de tentatives de connexion ne peut pas dépasser 20',
            'lockout_duration_minutes.min' => 'La durée de verrouillage doit être d\'au moins 1 minute',
            'lockout_duration_minutes.max' => 'La durée de verrouillage ne peut pas dépasser 24 heures',
            'password_min_length.min' => 'La longueur minimale du mot de passe doit être d\'au moins 8 caractères',
            'password_min_length.max' => 'La longueur minimale du mot de passe ne peut pas dépasser 128 caractères',
            'password_history_count.max' => 'Le nombre d\'historique de mots de passe ne peut pas dépasser 50',
            'password_expiry_days.max' => 'L\'expiration du mot de passe ne peut pas dépasser 365 jours',
            'pbkdf2_iterations.min' => 'Les itérations PBKDF2 doivent être d\'au moins 10 000 pour la sécurité',
            'pbkdf2_iterations.max' => 'Les itérations PBKDF2 ne peuvent pas dépasser 1 000 000 pour les performances',
            'session_timeout_minutes.min' => 'Le délai d\'expiration de session doit être d\'au moins 5 minutes',
            'session_timeout_minutes.max' => 'Le délai d\'expiration de session ne peut pas dépasser 24 heures'
        ];
    }
}