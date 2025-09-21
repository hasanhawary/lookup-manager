<?php

namespace HasanHawary\LookupManager;

use App\Http\Requests\Global\Help\HelpEnumRequest;
use App\Http\Requests\Global\Help\HelpModelRequest;

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
    public function getModels(HelpModelRequest $request): array
    {
        return $this->modelManager->getModels($request);
    }

    /**
     * Delegate to EnumLookupManager
     */
    public function getEnums(HelpEnumRequest $request): array
    {
        return $this->enumManager->getEnums($request);
    }
}
