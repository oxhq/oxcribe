<?php

declare(strict_types=1);

namespace Spatie\LaravelData {
    if (! class_exists(Data::class, false)) {
        abstract class Data
        {
        }
    }

    if (! class_exists(Optional::class, false)) {
        final class Optional
        {
        }
    }

    if (! class_exists(Lazy::class, false)) {
        final class Lazy
        {
        }
    }
}

namespace Spatie\LaravelData\Attributes {
    if (! class_exists(DataCollectionOf::class, false)) {
        #[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
        final class DataCollectionOf
        {
            public function __construct(public string $class)
            {
            }
        }
    }
}

namespace Spatie\Translatable {
    if (! trait_exists(HasTranslations::class, false)) {
        trait HasTranslations
        {
        }
    }
}
