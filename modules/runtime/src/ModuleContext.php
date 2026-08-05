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
        public LanguageModelInterface $language,
        public ?CoreQueryApi $query = null
    ) {
    }

    public function scopedQuery(): CoreQueryApi
    {
        if (!$this->query instanceof CoreQueryApi) {
            throw new ModuleException('A consulta documental escopada nÃ£o estÃ¡ disponÃ­vel neste contexto modular.');
        }

        return $this->query;
    }
}
