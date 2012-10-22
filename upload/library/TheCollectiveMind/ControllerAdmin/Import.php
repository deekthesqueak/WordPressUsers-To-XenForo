<?php
/**
 * This fuction overrides the default XenForo_ControllerAdmin_Import->actionImport() function
 * It will use the import_wordpress_steps template instead of the default import_steps
 *
 * @license https://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 * @copyright Copyright (c) 2012, Derek Bonner
 * @author Derek Bonner <derek@derekbonner.com>
 */
class TheCollectiveMind_ControllerAdmin_Import extends XFCP_TheCollectiveMind_ControllerAdmin_Import
{
    /**
     * Class copied from XenForo_ControllerAdmin_Import
     */
    public function actionImport()
    {
        $importModel = $this->_getImportModel();

        $session = new XenForo_ImportSession();
        if (!$session->getImporterKey()) {
            return $this->responseReroute(__CLASS__, 'index');
        }

        $stepInfo = $session->getStepInfo();

        $importer = $importModel->getImporter($session->getImporterKey());

        $showList = $this->_input->filterSingle('list', XenForo_Input::UINT);
        if (!$stepInfo['step'] || $showList) {
            $runStep = false;
        }
        else {
            $runStep = ($stepInfo['stepStart'] || $this->_request->isPost());
        }

        if ($runStep) {
            $response = $this->_runStep($importer, $session, $stepInfo['step'], $stepInfo['stepStart'], $stepInfo['stepOptions']);
            return $response;
        }
        else {
            $steps = $importModel->addImportStateToSteps($importer->getSteps(), $session->getRunSteps());
            $viewParams = array(
                'steps' => $steps,
                'importerName' => $importer->getName()
            );

            //The template has been changed to allow for a custom message after mapping Wordpress roles to Xenforo Groups
            return $this->responseView('XenForo_ViewAdmin_Import_Steps', 'import_wordpress_steps', $viewParams);
        }
    }
}