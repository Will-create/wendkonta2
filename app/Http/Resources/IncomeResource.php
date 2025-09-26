<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => date(config('date_format'), strtotime($this->created_at->toDateString())),
            'reference_no' => $this->reference_no,
            'warehouse' => new WarehouseResource($this->warehouse),
            'category' => new IncomeCategoryResource($this->incomeCategory),
            'amount' => number_format($this->amount, config('decimal')),
            'note' => $this->note
        ];
    }
}
