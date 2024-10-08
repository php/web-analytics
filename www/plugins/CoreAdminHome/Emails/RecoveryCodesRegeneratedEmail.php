<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CoreAdminHome\Emails;

use Piwik\Piwik;
use Piwik\Plugins\CoreAdminHome\Emails\SecurityNotificationEmail;

class RecoveryCodesRegeneratedEmail extends SecurityNotificationEmail
{
    protected function getBody()
    {
        return Piwik::translate('CoreAdminHome_SecurityNotificationRecoveryCodesRegeneratedBody') . ' ' . Piwik::translate('UsersManager_IfThisWasYouPasswordChange');
    }
}
