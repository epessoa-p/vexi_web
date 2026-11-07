<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Documento o imagen de un estudio, guardado en el disco privado. */
class LabAttachment extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'lab_request_id', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'];

    protected $casts = [
        'size' => 'integer',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class, 'lab_request_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
