<?php

declare(strict_types=1);

$configure = require __DIR__ . '/%VENDOR%/drupheus/drupheus-cs/config/rector.php';

return $configure([__DIR__ . '/%PATH%']);
