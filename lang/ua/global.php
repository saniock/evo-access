<?php

/*
 * EVO currently uses 'ua' as its Ukrainian locale string, while the
 * ISO standard is 'uk'. This file is a thin alias that delegates to
 * the canonical uk/global.php so we only maintain one set of
 * translations. Once EVO migrates to 'uk' this file can be removed.
 */
return require __DIR__ . '/../uk/global.php';
