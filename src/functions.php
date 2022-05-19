<?php

declare(strict_types=1);

namespace DWM;

if (function_exists('isEmpty')) {
    return;
}

/**
 * It seems that empty() is not enough to check, if something is really empty.
 * This function makes sure of the edge cases.
 *
 * @see https://stackoverflow.com/questions/718986/checking-if-the-string-is-empty
 */
function isEmpty(string|null $input): bool
{
    $input = trim((string) $input);

    return 0 == strlen($input) || 1 !== preg_match('/\S/', $input);
}
