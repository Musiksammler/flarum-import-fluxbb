<?php

use ArchLinux\ImportFluxBB\Console\ImportFromFluxBB;
use Flarum\Extend;

return [
    (new Extend\Console())->command(ImportFromFluxBB::class),
];
