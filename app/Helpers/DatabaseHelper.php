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

    /**
     * Get top referrers including direct access (null referrers)
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  The page views query builder
     * @param  int  $limit  Maximum number of referrers to return
     * @return array Array of referrer data with 'referrer' and 'count' keys
     */
    public static function getTopReferrersWithDirectAccess($query, int $limit = 10): array
    {
        // Get referrers with actual referrer values
        $referrers = $query->clone()
            ->whereNotNull('referrer')
            ->selectRaw('referrer, COUNT(*) as count')
            ->groupBy('referrer')
            ->orderByDesc('count')
            ->limit($limit - 1) // Leave room for direct access
            ->get()
            ->map(function ($item) {
                return [
                    'referrer' => $item->referrer,
                    'count' => $item->count,
                ];
            })
            ->toArray();

        // Get direct access count (null referrers)
        $directAccessCount = $query->clone()
            ->whereNull('referrer')
            ->count();

        // Add direct access to the list if there are any
        if ($directAccessCount > 0) {
            $referrers[] = [
                'referrer' => null,
                'count' => $directAccessCount,
            ];
        }

        // Sort by count and take top items
        usort($referrers, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        return array_slice($referrers, 0, $limit);
    }
}
