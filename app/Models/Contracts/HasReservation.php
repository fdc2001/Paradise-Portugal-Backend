<?php

namespace App\Models\Contracts;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

trait HasReservation
{
    public function calculateTotalNightsCost(string $startDate, string $endDate): int
    {
        $dateRange = $this->getDatesRange($startDate, $endDate);

        $total = 0;
        foreach ($dateRange as $date) {
            $total += $this->getDayPrice($date);
        }

        return $total;
    }

    public function hasSpecialPrice(string $startDate, string $endDate)
    {
        return $this->prices()->whereBetween('date', [$startDate, $endDate])->exists();
    }

    public function getDatesRange(string $startDate, string $endDate): CarbonPeriod
    {
        $start = Carbon::make($startDate);
        $end = Carbon::make($endDate)->subDays(1);

        return CarbonPeriod::create($start, $end);
    }

    protected function getDayPrice($date):int
    {
        $price = $this->prices()->where('date', $date)->first();
        return $price ? $price->getRawOriginal('price') : $this->getRawOriginal('default_price');
    }

    public function getDetailedPrices(string $startDate, string $endDate): array
    {
        $dateRange = $this->getDatesRange($startDate, $endDate);

        $nights = [];

        foreach ($dateRange as $date) {
            $nights[$date->format('Y-m-d')] = $this->getDayPrice($date);
        }

        return $nights;
    }

    public function getPeriod(string $startDate, string $endDate): int
    {
        $dateRange = $this->getDatesRange($startDate, $endDate);

        return $dateRange->count();
    }
}
