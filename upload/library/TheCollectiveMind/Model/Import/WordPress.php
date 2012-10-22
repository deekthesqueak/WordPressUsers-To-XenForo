<?php
/**
 * Adds Wordpress 3.x (Users Only) to list of importers
 *
 * @license https://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 * @copyright Copyright (c) 2012, Derek Bonner
 * @author Derek Bonner <derek@derekbonner.com>
 */
class TheCollectiveMind_Model_Import_WordPress extends XFCP_TheCollectiveMind_Model_Import_WordPress
{
    public function __construct()
    {
        self::$extraImporters[] = 'TheCollectiveMind_Importer_WordPress';
    }
}
