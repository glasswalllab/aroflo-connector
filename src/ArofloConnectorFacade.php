<?php

namespace glasswalllab\arofloonnector;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Glasswalllab\WiiseConnector\Skeleton\SkeletonClass
 */
class ArofloConnectorFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'arofloconnector';
    }
}
