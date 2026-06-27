<?php

namespace App\Http\Resources;

use App\Models\KycDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * KYC evidence metadata. Deliberately omits `storage_path` — the document is served only via short-lived
 * signed URLs through a dedicated, audited endpoint, never inline in an API body.
 *
 * @mixin KycDocument
 */
class KycDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'uploaded_at' => $this->uploaded_at?->toIso8601String(),
        ];
    }
}
