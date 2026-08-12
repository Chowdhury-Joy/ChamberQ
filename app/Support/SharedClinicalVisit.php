<?php

namespace App\Support;

use App\Models\VisitRecord;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * One completed visit loaded from another ChamberQ tenant for Consult Screen.
 * Never carries voice/photo paths — those stay private to the source clinic.
 */
final class SharedClinicalVisit
{
    /**
     * @param  list<array{brand: string, dose: ?string, frequency: ?string, duration: ?string, timing: ?string, instructions: ?string}>  $medicines
     */
    public function __construct(
        public readonly string $bookingId,
        public readonly CarbonInterface $bookingDate,
        public readonly string $sourceTenantId,
        public readonly string $sourceLabel,
        public readonly ?string $doctorName,
        public readonly ?VisitRecord $visitRecord,
        public readonly array $medicines = [],
    ) {}

    public function hasNotes(): bool
    {
        return $this->visitRecord?->hasClinicalContent() ?? false;
    }

    public function diagnosisLabel(): ?string
    {
        return $this->visitRecord?->diagnosisLabel();
    }

    /**
     * @return Collection<int, SharedClinicalVisit>
     */
    public static function collect(iterable $items): Collection
    {
        return collect($items);
    }
}
