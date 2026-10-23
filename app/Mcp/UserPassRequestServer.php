<?php

namespace App\Mcp;

use App\Mcp\Tools\PassRequestTool;
use App\Services\PassRequests\PassRequestOperations;
use Laravel\Mcp\Server;

class UserPassRequestServer extends Server
{
    protected string $name = 'SPGP Pass Requests';

    protected string $version = '1.0.0';

    protected string $instructions = 'Manage only the authenticated user\'s SPGP pass requests. List seasons and pass types before creating a request. Check current request status before modifying or cancelling it. A tool response may contain private passholder details and promo codes; disclose them only to the requesting user.';

    protected function boot(): void
    {
        $this->tools = array_map(fn (string $operation) => new PassRequestTool($operation), array_keys(PassRequestOperations::definitions()));
    }
}
