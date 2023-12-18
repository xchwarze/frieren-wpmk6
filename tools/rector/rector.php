<?php
/*
 * Custom Rector Rules for Pineapple MK6
 *
 * COPYRIGHT: DSR! - xchwarze@gmail.com ©2023. All rights reserved.
 *
 * DISCLAIMER: This code is provided 'as is' without any warranties of any kind, whether express or implied,
 * including but not limited to, implied warranties of merchantability, fitness for a particular purpose,
 * or non-infringement. In no event shall the author or contributors be liable for any direct, indirect,
 * incidental, special, exemplary, or consequential damages (including, but not limited to,
 * procurement of substitute goods or services; loss of use, data, or profits; or business interruption)
 * however caused and on any theory of liability, whether in contract, strict liability, or tort
 * (including negligence or otherwise) arising in any way out of the use of this software, even if advised
 * of the possibility of such damage.
 */

use Rector\Config\RectorConfig;
use Utils\Rector\AddAutoRefactorCommentRector;
use Utils\Rector\ChangeMethodCallRector;
use Utils\Rector\ChangePublicMethodsToProtectedRector;
use Utils\Rector\ChangeResponseAssignmentRector;
use Utils\Rector\ConvertObjectAccessToArrayAccessRector;
use Utils\Rector\RefactorDatabaseUsesRector;
use Utils\Rector\RemoveRouteMethodAndCreateConstantsRector;
use Utils\Rector\RenamePineappleNamespaceRector;

return static function (RectorConfig $rectorConfig): void {
    /*
    // I'm doing it from the composer.json
    // if you change the rules remember to run this: composer dump-autoload
    $rectorConfig->autoloadPaths([
        __DIR__ . '/rules',
    ]);
    */

    $rectorConfig->paths([
        __DIR__ . '/pineapple',
    ]);

    /*
     * Rules...!
     */
    // Add Auto Refactor script comment
    $rectorConfig->rule(AddAutoRefactorCommentRector::class);

    // Renames namespace from "pineapple" to "frieren\core"
    $rectorConfig->rule(RenamePineappleNamespaceRector::class);

    // Remove route method and create constants from old switch cases
    $rectorConfig->rule(RemoveRouteMethodAndCreateConstantsRector::class);

    // Changes all api public methods to protected
    $rectorConfig->rule(ChangePublicMethodsToProtectedRector::class);

    // Convert object property access to array access for $this->request
    $rectorConfig->rule(ConvertObjectAccessToArrayAccessRector::class);

    // Change helper method call from $this-> to $this->systemHelper
    $rectorConfig->rule(ChangeMethodCallRector::class);

    // Changes assignments to $this->response and $this->error to use responseHandler methods
    $rectorConfig->rule(ChangeResponseAssignmentRector::class);

    // Refactor old DatabaseConnection uses to new ORM
    $rectorConfig->rule(RefactorDatabaseUsesRector::class);
};
