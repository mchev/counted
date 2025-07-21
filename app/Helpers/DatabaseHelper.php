<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class DatabaseHelper
{
    /**
     * Retourne la fonction de formatage de date appropriée selon la base de données
     */
    public static function getDateFormatFunction(string $format, string $column): string
    {
        $connection = DB::connection()->getDriverName();
        
        return match ($connection) {
            'sqlite' => self::getSqliteDateFormat($format, $column),
            'mysql' => self::getMysqlDateFormat($format, $column),
            default => self::getMysqlDateFormat($format, $column), // Par défaut MySQL
        };
    }
    
    private static function getSqliteDateFormat(string $format, string $column): string
    {
        return match ($format) {
            'H:i' => "strftime('%H:%M', {$column})",
            'H:M' => "strftime('%H:%M', {$column})",
            'j M' => "strftime('%d %b', {$column})",
            'd b' => "strftime('%d %b', {$column})",
            'Y-m-d' => "strftime('%Y-%m-%d', {$column})",
            'Y-m' => "strftime('%Y-%m', {$column})",
            default => "strftime('%Y-%m-%d', {$column})",
        };
    }
    
    private static function getMysqlDateFormat(string $format, string $column): string
    {
        return match ($format) {
            'H:i' => "DATE_FORMAT({$column}, '%H:%i')",
            'H:M' => "DATE_FORMAT({$column}, '%H:%i')",
            'j M' => "DATE_FORMAT({$column}, '%j %b')",
            'd b' => "DATE_FORMAT({$column}, '%d %b')",
            'Y-m-d' => "DATE_FORMAT({$column}, '%Y-%m-%d')",
            'Y-m' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
        };
    }
    
    /**
     * Retourne la fonction de formatage de mois appropriée selon la base de données
     */
    public static function getMonthFormatFunction(string $column): string
    {
        $connection = DB::connection()->getDriverName();
        
        return match ($connection) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'mysql' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
} 