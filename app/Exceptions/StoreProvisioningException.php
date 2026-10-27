<?php

namespace App\Exceptions;

use Exception;

/**
 * A store database could not be prepared. The message is safe to show to a super admin.
 */
class StoreProvisioningException extends Exception {}
