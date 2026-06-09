<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MenuResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'price'        => (int) $this->price,
            'description'  => $this->description,
            'category_id'  => $this->category_id,
            'station_id'   => $this->station_id,
            'station_name' => $this->whenLoaded('station', fn () => $this->station?->name),
            'has_variety'  => (bool) $this->has_variety,
            'varieties'    => $this->varieties ?? [],
            'img'          => $this->resolveImageUrl($this->img),
            'updated_at'   => optional($this->updated_at)->toIso8601String(),
        ];
    }

    private function resolveImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
