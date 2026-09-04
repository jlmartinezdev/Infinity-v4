<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrityVerdict extends Model
{
    protected $table = 'integrity_verdicts';

    protected $fillable = [
        'device_name',
        'nonce',
        'package_name',
        'app_recognition_verdict',
        'device_recognition_verdict',
        'app_licensing_verdict',
        'ok',
        'error',
        'enforced',
        'blocked',
        'ip',
        'payload_summary',
    ];

    protected function casts(): array
    {
        return [
            'ok' => 'boolean',
            'enforced' => 'boolean',
            'blocked' => 'boolean',
            'payload_summary' => 'array',
        ];
    }
}
