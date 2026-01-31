<?php

namespace App\Models;

use App\Traits\SerializesDatesAsLocal;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use SerializesDatesAsLocal;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'event',
        'email_to',
        'email_from',
        'subject',
        'body',
        'result',
        'error_message',
    ];
}
