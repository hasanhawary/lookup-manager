<?php

namespace HasanHawary\LookupManager;

class LookupManager
{
    public function __construct(
        private ?EnumLookupManager $enumManager = null,
        private ?ModelLookupManager $modelManager = null,
    ) {
        $this->enumManager = $enumManager ?? new EnumLookupManager();
        $this->modelManager = $modelManager ?? new ModelLookupManager();
    }

    /**
     * Delegate to ModelLookupManager
     */
    public function getModels(array $data): array
    {
        return $this->modelManager->getModels($data);
    }

    /**
     * Delegate to EnumLookupManager
     */
    public function getEnums(array $data): array
    {
        return $this->enumManager->getEnums($data);
    }
}
