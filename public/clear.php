<?php
clearstatcache(true);
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared. ";
}
echo "Stat cache cleared.";
