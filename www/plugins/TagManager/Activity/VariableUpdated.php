<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\TagManager\Activity;

class VariableUpdated extends VariableBaseActivity
{
    protected $eventName = 'API.TagManager.updateContainerVariable.end';

    public function extractParams($eventData)
    {
        if (!$this->hasRequestedApiMethod('updateContainerVariable')) {
            return false;
        }
        list($return, $finalAPIParameters) = $eventData;

        $idEntity = $finalAPIParameters['parameters']['idVariable'];
        $idSite = $finalAPIParameters['parameters']['idSite'];
        $idContainer = $finalAPIParameters['parameters']['idContainer'];
        $idContainerVersion = $finalAPIParameters['parameters']['idContainerVersion'];

        return $this->formatActivityData($idSite, $idContainer, $idContainerVersion, $idEntity);
    }

    public function getTranslatedDescription($activityData, $performingUser)
    {
        $siteName = $this->getSiteNameFromActivityData($activityData);
        $entityName = $this->getEntityNameFromActivityData($activityData);
        $containerName = $this->getContainerNameFromActivityData($activityData);

        $desc = sprintf('updated the variable "%1$s" in container "%2$s" for site "%3$s"', $entityName, $containerName, $siteName);

        return $desc;
    }
}
