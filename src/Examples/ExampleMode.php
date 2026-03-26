<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Examples;

enum ExampleMode: string
{
    case MinimalValid = 'minimal_valid';
    case HappyPath = 'happy_path';
    case RealisticFull = 'realistic_full';
}
