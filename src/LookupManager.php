<?php

namespace HasanHawary\LookupManager;

class LookupManager
{
    public function __construct(
        private ?EnumLookupManager $enumManager = null,
        private ?ModelLookupManager $modelManager = null,
        private ?ConfigLookupManager $configManager = null,
    ) {
        $this->enumManager = $enumManager ?? new EnumLookupManager();
        $this->modelManager = $modelManager ?? new ModelLookupManager();
        $this->configManager = $configManager ?? new ConfigLookupManager();
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

    /**
     * Delegate to EnumLookupManager
     */
    public function getConfigs(array $data): array
    {
        return $this->configManager->getConfigs($data);
    }
}
