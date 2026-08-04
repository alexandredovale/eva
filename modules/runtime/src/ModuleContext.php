<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use PDO;

final readonly class ModuleContext
{
    public function __construct(
        public ModuleManifest $manifest,
        public PDO $storage,
        public CoreReadApi $core,
        public LanguageModelInterface $language
    ) {
    }
}
