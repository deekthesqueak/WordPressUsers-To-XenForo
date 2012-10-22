<?php
/**
 * Listener to include WordPress 3.x (Users Only) as a source for data import
 *
 * @license https://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 * @copyright Copyright (c) 2012, Derek Bonner
 * @author Derek Bonner <derek@derekbonner.com>
 */
class TheCollectiveMind_Listener_Importer_WordPress
{
    /**
     * @param string $class The name of the class to be created
     * @param array $extend A modifiable list of classes that wish to extend the class
     */
    public static function loadClassModel($class, array &$extend)
    {
        if ($class == 'XenForo_Model_Import') {
            $extend[] = 'TheCollectiveMind_Model_Import_WordPress';
        }
    }
}
