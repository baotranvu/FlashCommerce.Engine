<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Sovereign Systems: Architecture over willpower.');
})->purpose('Display an inspiring quote');
