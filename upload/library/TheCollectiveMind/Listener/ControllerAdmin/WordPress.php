<?php
/**
 * Listener to modify default ControllerAdmin_Import->actionImport()
 *
 * @license https://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 * @copyright Copyright (c) 2012, Derek Bonner
 * @author Derek Bonner <derek@derekbonner.com>
 */
class TheCollectiveMind_Listener_ControllerAdmin_WordPress
{
    /**
     * @param string $class The name of the class to be created
     * @param array $extend A modifiable list of classes that wish to extend the class
     */
    public static function loadClassController($class, array &$extend)
    {
        if ($class == 'XenForo_ControllerAdmin_Import') {
            $extend[] = 'TheCollectiveMind_ControllerAdmin_Import';
        }
    }
}